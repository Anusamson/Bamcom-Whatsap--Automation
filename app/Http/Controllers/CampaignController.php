<?php

namespace App\Http\Controllers;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\WhatsAppTemplateStatus;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\WhatsAppTemplate;
use App\Services\Campaign\CampaignService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    /**
     * Display a listing of campaigns.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status');
        $search = $request->input('search');

        $query = Campaign::query()
            ->with(['audience:id,name', 'template:id,name,category', 'creator:id,name'])
            ->latest();

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $campaigns = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_campaigns' => Campaign::count(),
            'running_campaigns' => Campaign::where('status', CampaignStatus::Running->value)->count(),
            'scheduled_campaigns' => Campaign::where('status', CampaignStatus::Scheduled->value)->count(),
            'completed_campaigns' => Campaign::where('status', CampaignStatus::Completed->value)->count(),
            'total_sent' => (int) Campaign::sum('sent_count'),
            'total_delivered' => (int) Campaign::sum('delivered_count'),
            'total_read' => (int) Campaign::sum('read_count'),
            'total_opted_out' => (int) Campaign::sum('opted_out_count'),
        ];

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
            'metrics' => $metrics,
            'filters' => [
                'status' => $status ?? 'all',
                'search' => $search ?? '',
            ],
            'statuses' => array_map(fn (CampaignStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
                'badge' => $s->badgeClass(),
                'color' => $s->color(),
            ], CampaignStatus::cases()),
        ]);
    }

    /**
     * Show form for creating a new campaign.
     */
    public function create(): Response
    {
        return Inertia::render('Campaigns/Create', [
            'audiences' => Audience::query()->orderBy('name')->get(['id', 'name', 'cached_count', 'filters']),
            'templates' => WhatsAppTemplate::query()
                ->where('status', WhatsAppTemplateStatus::Approved)
                ->orderBy('name')
                ->get(['id', 'name', 'category', 'language', 'body_text', 'components']),
        ]);
    }

    /**
     * Store a newly created campaign.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:draft,scheduled',
            'audience_id' => 'required|exists:audiences,id',
            'whatsapp_template_id' => 'nullable|exists:whatsapp_templates,id',
            'message_type' => 'required|string|in:template,custom_text',
            'message_content' => 'nullable|string',
            'template_parameters' => 'nullable|array',
            'batch_size' => 'nullable|integer|min:1|max:500',
            'batch_delay_seconds' => 'nullable|integer|min:1|max:60',
            'scheduled_at' => 'nullable|date',
        ]);

        $campaign = $this->campaignService->createCampaign($validated, $request->user());

        if ($request->boolean('launch_immediately')) {
            try {
                $this->campaignService->launchCampaign($campaign);

                return redirect()->route('campaigns.show', $campaign)
                    ->with('success', "Campaign '{$campaign->name}' created and launched successfully.");
            } catch (Exception $e) {
                return redirect()->route('campaigns.show', $campaign)
                    ->with('warning', "Campaign created, but launch failed: {$e->getMessage()}");
            }
        }

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', "Campaign '{$campaign->name}' saved successfully.");
    }

    /**
     * Display campaign details, recipients, events, and metrics.
     */
    public function show(Request $request, Campaign $campaign): Response
    {
        $campaign->load(['audience', 'template', 'creator']);

        $recipientStatus = $request->input('recipient_status');
        $recipientsQuery = $campaign->recipients()
            ->with(['contact:id,first_name,last_name,phone,has_opted_out', 'lead:id,title,status'])
            ->latest('updated_at');

        if ($recipientStatus && $recipientStatus !== 'all') {
            $recipientsQuery->where('status', $recipientStatus);
        }

        $recipients = $recipientsQuery->paginate(20)->withQueryString();

        $events = $campaign->events()
            ->with('recipient:id,phone')
            ->latest('created_at')
            ->limit(50)
            ->get();

        return Inertia::render('Campaigns/Show', [
            'campaign' => $campaign,
            'recipients' => $recipients,
            'events' => $events,
            'recipientStatuses' => array_map(fn (CampaignRecipientStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
                'badge' => $s->badgeClass(),
            ], CampaignRecipientStatus::cases()),
            'filters' => [
                'recipient_status' => $recipientStatus ?? 'all',
            ],
        ]);
    }

    /**
     * Launch campaign immediately.
     */
    public function launch(Campaign $campaign): RedirectResponse
    {
        try {
            $this->campaignService->launchCampaign($campaign);

            return back()->with('success', "Campaign '{$campaign->name}' launched successfully.");
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Pause a running campaign.
     */
    public function pause(Campaign $campaign): RedirectResponse
    {
        try {
            $this->campaignService->pauseCampaign($campaign);

            return back()->with('success', "Campaign '{$campaign->name}' has been paused.");
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Resume a paused campaign.
     */
    public function resume(Campaign $campaign): RedirectResponse
    {
        try {
            $this->campaignService->resumeCampaign($campaign);

            return back()->with('success', "Campaign '{$campaign->name}' has been resumed.");
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel a campaign.
     */
    public function cancel(Request $request, Campaign $campaign): RedirectResponse
    {
        $reason = $request->input('reason', 'Cancelled by administrator');

        try {
            $this->campaignService->cancelCampaign($campaign, $reason);

            return back()->with('success', "Campaign '{$campaign->name}' was cancelled.");
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Delete campaign.
     */
    public function destroy(Campaign $campaign): RedirectResponse
    {
        $name = $campaign->name;
        $this->campaignService->deleteCampaign($campaign);

        return redirect()->route('campaigns.index')
            ->with('success', "Campaign '{$name}' deleted successfully.");
    }
}
