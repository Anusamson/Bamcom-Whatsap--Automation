<?php

namespace App\Http\Controllers;

use App\Enums\EmailCampaignRecipientStatus;
use App\Enums\EmailCampaignStatus;
use App\Http\Requests\Email\StoreEmailCampaignRequest;
use App\Http\Requests\Email\UpdateEmailCampaignRequest;
use App\Models\EmailCampaign;
use App\Models\EmailTemplate;
use App\Models\SmartList;
use App\Services\Email\EmailAttributionService;
use App\Services\Email\EmailCampaignService;
use App\Services\Email\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmailCampaignController extends Controller
{
    public function __construct(
        protected EmailCampaignService $campaignService,
        protected EmailTemplateService $templateService,
        protected EmailAttributionService $attributionService
    ) {}

    /**
     * Display a listing of email marketing campaigns.
     */
    public function index(Request $request): Response|JsonResponse
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
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_campaigns' => EmailCampaign::count(),
            'total_sent' => (int) EmailCampaign::sum('sent_count'),
            'total_opened' => (int) EmailCampaign::sum('opened_count'),
            'total_clicked' => (int) EmailCampaign::sum('clicked_count'),
            'active_count' => EmailCampaign::whereIn('status', [EmailCampaignStatus::Processing->value, EmailCampaignStatus::Sending->value])->count(),
        ];

        $statuses = array_map(fn (EmailCampaignStatus $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'badgeClass' => $s->badgeClass(),
        ], EmailCampaignStatus::cases());

        if ($request->wantsJson()) {
            return response()->json([
                'campaigns' => $campaigns,
                'stats' => $stats,
                'statuses' => $statuses,
            ]);
        }

        return Inertia::render('Email/Campaigns/Index', [
            'campaigns' => $campaigns,
            'stats' => $stats,
            'statuses' => $statuses,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    /**
     * Show the campaign creation interface.
     */
    public function create(): Response
    {
        Gate::authorize('create', EmailCampaign::class);

        $templates = EmailTemplate::query()
            ->where('status', 'active')
            ->latest()
            ->get(['id', 'name', 'subject', 'preheader', 'body_html', 'body_plain', 'category']);

        $smartLists = SmartList::query()
            ->latest()
            ->get(['id', 'name', 'slug', 'description', 'cached_count']);

        return Inertia::render('Email/Campaigns/Create', [
            'templates' => $templates,
            'smartLists' => $smartLists,
            'sampleVariables' => $this->templateService->generateSampleVariables(),
        ]);
    }

    /**
     * Store a newly created email campaign.
     */
    public function store(StoreEmailCampaignRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', EmailCampaign::class);

        $validated = $request->validated();

        $campaign = EmailCampaign::create([
            ...$validated,
            'status' => EmailCampaignStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        // If audience was specified, resolve recipients
        if ($campaign->smart_list_id || ! empty($campaign->segment_criteria)) {
            $this->campaignService->resolveRecipients($campaign);
        }

        // Action options: 'draft', 'send_now', 'schedule'
        $action = $request->input('action', 'draft');

        if ($action === 'send_now') {
            $this->campaignService->dispatchCampaign($campaign);
            $message = 'Campaign created and queued for immediate delivery!';
        } elseif ($action === 'schedule' && $request->filled('scheduled_at')) {
            $this->campaignService->scheduleCampaign($campaign, Carbon::parse($request->input('scheduled_at')));
            $message = 'Campaign created and scheduled successfully.';
        } else {
            $message = 'Campaign draft created successfully.';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'campaign' => $campaign->fresh(['smartList', 'template']),
            ], HttpResponse::HTTP_CREATED);
        }

        return redirect()->route('email-campaigns.show', $campaign)->with('success', $message);
    }

    /**
     * Display the specified email campaign with live metrics and recipient breakdown.
     */
    public function show(EmailCampaign $emailCampaign, Request $request): Response|JsonResponse
    {
        Gate::authorize('view', $emailCampaign);

        $emailCampaign->load(['creator:id,name,email', 'smartList:id,name', 'template:id,name']);

        $recipientsQuery = $emailCampaign->recipients()
            ->with('contact:id,uuid,first_name,last_name,email,phone')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.trim((string) $request->input('search')).'%';
                $q->where(function ($sub) use ($search) {
                    $sub->where('email', 'like', $search)
                        ->orWhereHas('contact', fn ($cq) => $cq->where('first_name', 'like', $search)->orWhere('last_name', 'like', $search));
                });
            })
            ->latest('id');

        $recipients = $recipientsQuery->paginate(20)->withQueryString();

        $recipientStatuses = array_map(fn (EmailCampaignRecipientStatus $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'badgeClass' => $s->badgeClass(),
        ], EmailCampaignRecipientStatus::cases());

        $summary = $emailCampaign->getAnalyticsSummary();

        if ($request->wantsJson()) {
            return response()->json([
                'campaign' => $emailCampaign,
                'recipients' => $recipients,
                'summary' => $summary,
                'recipient_statuses' => $recipientStatuses,
            ]);
        }

        return Inertia::render('Email/Campaigns/Show', [
            'campaign' => $emailCampaign,
            'recipients' => $recipients,
            'summary' => $summary,
            'recipientStatuses' => $recipientStatuses,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    /**
     * Show the campaign editing interface.
     */
    public function edit(EmailCampaign $emailCampaign): Response|RedirectResponse
    {
        Gate::authorize('update', $emailCampaign);

        if (! $emailCampaign->status->canBeEdited()) {
            return redirect()->route('email-campaigns.show', $emailCampaign)
                ->with('error', "Campaign in status '{$emailCampaign->status->label()}' cannot be edited.");
        }

        $templates = EmailTemplate::query()
            ->where('status', 'active')
            ->latest()
            ->get(['id', 'name', 'subject', 'preheader', 'body_html', 'body_plain', 'category']);

        $smartLists = SmartList::query()
            ->latest()
            ->get(['id', 'name', 'slug', 'description', 'cached_count']);

        return Inertia::render('Email/Campaigns/Edit', [
            'campaign' => $emailCampaign,
            'templates' => $templates,
            'smartLists' => $smartLists,
            'sampleVariables' => $this->templateService->generateSampleVariables(),
        ]);
    }

    /**
     * Update the specified email campaign.
     */
    public function update(UpdateEmailCampaignRequest $request, EmailCampaign $emailCampaign): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $emailCampaign);

        if (! $emailCampaign->status->canBeEdited()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "Campaign in status '{$emailCampaign->status->label()}' cannot be edited.",
                ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            return back()->with('error', 'Campaign cannot be edited in its current status.');
        }

        $emailCampaign->update($request->validated());

        // If audience was modified, re-resolve recipients
        if ($request->hasAny(['smart_list_id', 'segment_criteria'])) {
            $this->campaignService->resolveRecipients($emailCampaign);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Campaign updated successfully.',
                'campaign' => $emailCampaign->fresh(['smartList', 'template']),
            ]);
        }

        return redirect()->route('email-campaigns.show', $emailCampaign)->with('success', 'Campaign updated successfully.');
    }

    /**
     * Remove the specified email campaign.
     */
    public function destroy(EmailCampaign $emailCampaign, Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $emailCampaign);

        $emailCampaign->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Campaign deleted successfully.',
            ]);
        }

        return redirect()->route('email-campaigns.index')->with('success', 'Campaign deleted successfully.');
    }

    /**
     * Preview audience segmentation and calculate recipient counts.
     */
    public function previewAudience(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailCampaign::class);

        $smartListId = $request->filled('smart_list_id') ? $request->integer('smart_list_id') : null;
        $segmentCriteria = $request->input('segment_criteria');

        $preview = $this->campaignService->previewAudience($smartListId, $segmentCriteria);

        return response()->json($preview);
    }

    /**
     * Send a single test email of this campaign to a designated preview recipient.
     */
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
            'message' => "Test campaign email sent successfully to {$validated['recipient_email']}.",
            'email' => $message,
        ], HttpResponse::HTTP_ACCEPTED);
    }

    /**
     * Dispatch the campaign sending immediately across queued batches.
     */
    public function sendNow(EmailCampaign $emailCampaign, Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $emailCampaign);

        $this->campaignService->dispatchCampaign($emailCampaign);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Campaign has been queued for immediate delivery.',
                'campaign' => $emailCampaign->fresh(),
            ]);
        }

        return back()->with('success', 'Campaign queued for immediate delivery.');
    }

    /**
     * Schedule the campaign for future automated sending.
     */
    public function schedule(Request $request, EmailCampaign $emailCampaign): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $emailCampaign);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $this->campaignService->scheduleCampaign($emailCampaign, Carbon::parse($validated['scheduled_at']));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Campaign scheduled successfully.',
                'campaign' => $emailCampaign->fresh(),
            ]);
        }

        return back()->with('success', 'Campaign scheduled successfully.');
    }

    /**
     * Pause an actively sending campaign.
     */
    public function pause(EmailCampaign $emailCampaign, Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $emailCampaign);

        $this->campaignService->pauseCampaign($emailCampaign);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Campaign sending paused.',
                'campaign' => $emailCampaign->fresh(),
            ]);
        }

        return back()->with('success', 'Campaign sending paused.');
    }

    /**
     * Resume a paused campaign.
     */
    public function resume(EmailCampaign $emailCampaign, Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $emailCampaign);

        $this->campaignService->resumeCampaign($emailCampaign);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Campaign delivery resumed.',
                'campaign' => $emailCampaign->fresh(),
            ]);
        }

        return back()->with('success', 'Campaign delivery resumed.');
    }

    /**
     * Cancel a campaign.
     */
    public function cancel(Request $request, EmailCampaign $emailCampaign): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $emailCampaign);

        $reason = $request->input('reason');
        $this->campaignService->cancelCampaign($emailCampaign, $reason);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Campaign has been cancelled.',
                'campaign' => $emailCampaign->fresh(),
            ]);
        }

        return back()->with('success', 'Campaign has been cancelled.');
    }

    /**
     * Polling endpoint for real-time campaign progress tracking.
     */
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

    /**
     * View sales attribution and ROI funnel for a specific campaign.
     */
    public function attribution(Request $request, EmailCampaign $emailCampaign): Response|JsonResponse
    {
        Gate::authorize('view', $emailCampaign);

        $windowDays = $request->integer('window_days', 90);
        $attributionData = $this->attributionService->getCampaignAttribution($emailCampaign, $windowDays);

        if ($request->wantsJson()) {
            return response()->json($attributionData);
        }

        return Inertia::render('Email/Campaigns/Attribution', [
            'campaign' => $emailCampaign,
            'attribution' => $attributionData,
        ]);
    }

    /**
     * Cross-campaign executive management report on sales attribution and generated revenue.
     */
    public function attributionReport(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', EmailCampaign::class);

        $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $report = $this->attributionService->getExecutiveAttributionReport($startDate, $endDate);

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return Inertia::render('Email/Campaigns/AttributionReport', [
            'report' => $report,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }
}
