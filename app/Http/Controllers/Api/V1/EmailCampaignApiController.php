<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EmailCampaignRecipientStatus;
use App\Enums\EmailCampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Email\StoreEmailCampaignRequest;
use App\Http\Requests\Email\UpdateEmailCampaignRequest;
use App\Models\EmailCampaign;
use App\Services\Email\EmailAttributionService;
use App\Services\Email\EmailCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmailCampaignApiController extends Controller
{
    public function __construct(
        protected EmailCampaignService $campaignService,
        protected EmailAttributionService $attributionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailCampaign::class);

        $campaigns = EmailCampaign::query()
            ->with(['creator:id,name,email', 'smartList:id,name', 'template:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->status($request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.trim((string) $request->input('search')).'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $search)->orWhere('subject', 'like', $search));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'campaigns' => $campaigns,
            'statuses' => array_column(EmailCampaignStatus::cases(), 'value'),
        ]);
    }

    public function store(StoreEmailCampaignRequest $request): JsonResponse
    {
        Gate::authorize('create', EmailCampaign::class);

        $campaign = EmailCampaign::create([
            ...$request->validated(),
            'status' => EmailCampaignStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        if ($campaign->smart_list_id || ! empty($campaign->segment_criteria)) {
            $this->campaignService->resolveRecipients($campaign);
        }

        $action = $request->input('action', 'draft');

        if ($action === 'send_now') {
            $this->campaignService->dispatchCampaign($campaign);
            $message = 'Campaign created and queued for immediate delivery.';
        } elseif ($action === 'schedule' && $request->filled('scheduled_at')) {
            $this->campaignService->scheduleCampaign($campaign, Carbon::parse($request->input('scheduled_at')));
            $message = 'Campaign created and scheduled successfully.';
        } else {
            $message = 'Campaign draft created successfully.';
        }

        return response()->json([
            'message' => $message,
            'campaign' => $campaign->fresh(['smartList', 'template']),
        ], HttpResponse::HTTP_CREATED);
    }

    public function show(EmailCampaign $emailCampaign, Request $request): JsonResponse
    {
        Gate::authorize('view', $emailCampaign);

        $emailCampaign->load(['creator:id,name,email', 'smartList:id,name', 'template:id,name']);

        $recipients = $emailCampaign->recipients()
            ->with('contact:id,uuid,first_name,last_name,email,phone')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'campaign' => $emailCampaign,
            'recipients' => $recipients,
            'progress' => [
                'percentage' => $emailCampaign->progressPercentage(),
                'open_rate' => $emailCampaign->openRate(),
                'click_rate' => $emailCampaign->clickRate(),
                'bounce_rate' => $emailCampaign->bounceRate(),
            ],
            'recipient_statuses' => array_column(EmailCampaignRecipientStatus::cases(), 'value'),
        ]);
    }

    public function update(UpdateEmailCampaignRequest $request, EmailCampaign $emailCampaign): JsonResponse
    {
        Gate::authorize('update', $emailCampaign);

        if (! $emailCampaign->status->canBeEdited()) {
            return response()->json([
                'message' => "Campaign in status '{$emailCampaign->status->label()}' cannot be edited.",
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $emailCampaign->update($request->validated());

        if ($request->hasAny(['smart_list_id', 'segment_criteria'])) {
            $this->campaignService->resolveRecipients($emailCampaign);
        }

        return response()->json([
            'message' => 'Campaign updated successfully.',
            'campaign' => $emailCampaign->fresh(['smartList', 'template']),
        ]);
    }

    public function destroy(EmailCampaign $emailCampaign): JsonResponse
    {
        Gate::authorize('delete', $emailCampaign);

        $emailCampaign->delete();

        return response()->json([
            'message' => 'Campaign deleted successfully.',
        ]);
    }

    public function previewAudience(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailCampaign::class);

        $smartListId = $request->filled('smart_list_id') ? $request->integer('smart_list_id') : null;
        $segmentCriteria = $request->input('segment_criteria');

        $preview = $this->campaignService->previewAudience($smartListId, $segmentCriteria);

        return response()->json($preview);
    }

    public function sendTest(Request $request, EmailCampaign $emailCampaign): JsonResponse
    {
        Gate::authorize('view', $emailCampaign);

        $validated = $request->validate([
            'recipient_email' => ['required', 'email', 'max:255'],
        ]);

        $message = $this->campaignService->sendTest(
            $emailCampaign,
            $validated['recipient_email'],
            $request->user()
        );

        return response()->json([
            'message' => "Test campaign email dispatched successfully to {$validated['recipient_email']}.",
            'email' => $message,
        ], HttpResponse::HTTP_ACCEPTED);
    }

    public function sendNow(EmailCampaign $emailCampaign): JsonResponse
    {
        Gate::authorize('update', $emailCampaign);

        $this->campaignService->dispatchCampaign($emailCampaign);

        return response()->json([
            'message' => 'Campaign has been queued for immediate delivery.',
            'campaign' => $emailCampaign->fresh(),
        ]);
    }

    public function schedule(Request $request, EmailCampaign $emailCampaign): JsonResponse
    {
        Gate::authorize('update', $emailCampaign);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $this->campaignService->scheduleCampaign($emailCampaign, Carbon::parse($validated['scheduled_at']));

        return response()->json([
            'message' => 'Campaign scheduled successfully.',
            'campaign' => $emailCampaign->fresh(),
        ]);
    }

    public function pause(EmailCampaign $emailCampaign): JsonResponse
    {
        Gate::authorize('update', $emailCampaign);

        $this->campaignService->pauseCampaign($emailCampaign);

        return response()->json([
            'message' => 'Campaign sending paused.',
            'campaign' => $emailCampaign->fresh(),
        ]);
    }

    public function resume(EmailCampaign $emailCampaign): JsonResponse
    {
        Gate::authorize('update', $emailCampaign);

        $this->campaignService->resumeCampaign($emailCampaign);

        return response()->json([
            'message' => 'Campaign delivery resumed.',
            'campaign' => $emailCampaign->fresh(),
        ]);
    }

    public function cancel(Request $request, EmailCampaign $emailCampaign): JsonResponse
    {
        Gate::authorize('update', $emailCampaign);

        $this->campaignService->cancelCampaign($emailCampaign, $request->input('reason'));

        return response()->json([
            'message' => 'Campaign has been cancelled.',
            'campaign' => $emailCampaign->fresh(),
        ]);
    }

    public function progress(EmailCampaign $emailCampaign): JsonResponse
    {
        Gate::authorize('view', $emailCampaign);

        $fresh = $emailCampaign->fresh();

        return response()->json([
            'status' => $fresh->status->value,
            'status_label' => $fresh->status->label(),
            'badge_class' => $fresh->status->badgeClass(),
            'progress_percentage' => $fresh->progressPercentage(),
            'total_recipients' => $fresh->total_recipients,
            'eligible_recipients' => $fresh->eligible_recipients,
            'skipped_recipients' => $fresh->skipped_recipients,
            'sent_count' => $fresh->sent_count,
            'failed_count' => $fresh->failed_count,
            'delivered_count' => $fresh->delivered_count,
            'opened_count' => $fresh->opened_count,
            'clicked_count' => $fresh->clicked_count,
            'started_at' => $fresh->started_at?->toIso8601String(),
            'completed_at' => $fresh->completed_at?->toIso8601String(),
        ]);
    }

    public function attribution(Request $request, EmailCampaign $emailCampaign): JsonResponse
    {
        Gate::authorize('view', $emailCampaign);

        $windowDays = $request->integer('window_days', 90);
        $data = $this->attributionService->getCampaignAttribution($emailCampaign, $windowDays);

        return response()->json($data);
    }

    public function attributionReport(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailCampaign::class);

        $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $report = $this->attributionService->getExecutiveAttributionReport($startDate, $endDate);

        return response()->json($report);
    }
}
