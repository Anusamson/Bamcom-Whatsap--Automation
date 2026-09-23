<?php

namespace App\Http\Controllers;

use App\Enums\PermissionEnum;
use App\Models\LeadScoreLog;
use App\Models\LeadScoringRule;
use App\Services\Lead\LeadScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadScoringRuleController extends Controller
{
    public function __construct(
        protected LeadScoringService $scoringService
    ) {}

    /**
     * Display the lead scoring rules administration dashboard.
     */
    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $rules = $this->scoringService->getRules();

        $recentLogs = LeadScoreLog::query()
            ->with(['lead.contact', 'rule', 'actor'])
            ->latest('id')
            ->limit(20)
            ->get();

        $temperatureScale = [
            [
                'name' => 'Cold',
                'range' => '0 - 29 pts',
                'min' => 0,
                'max' => 29,
                'color' => 'sky',
                'icon' => '❄️',
                'description' => 'Unqualified or early stage inquiry with initial contact information.',
            ],
            [
                'name' => 'Warm',
                'range' => '30 - 59 pts',
                'min' => 30,
                'max' => 59,
                'color' => 'amber',
                'icon' => '☀️',
                'description' => 'Qualified buyer actively exploring locations, budgets, and payment spreads.',
            ],
            [
                'name' => 'Hot',
                'range' => '60+ pts',
                'min' => 60,
                'max' => 100,
                'color' => 'rose',
                'icon' => '🔥',
                'description' => 'High-intent buyer ready to inspect, make deposit, or close transaction.',
            ],
        ];

        return Inertia::render('Settings/LeadScoring/Index', [
            'rules' => $rules,
            'recentLogs' => $recentLogs,
            'temperatureScale' => $temperatureScale,
            'stats' => [
                'total_rules' => $rules->count(),
                'active_rules' => $rules->where('is_active', true)->count(),
                'total_events_awarded' => LeadScoreLog::count(),
                'total_points_distributed' => (int) LeadScoreLog::sum('points_awarded'),
            ],
        ]);
    }

    /**
     * Update points or settings for a specific scoring rule.
     */
    public function update(Request $request, LeadScoringRule $leadScoringRule): RedirectResponse
    {
        $this->authorizeEdit($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'points' => ['required', 'integer', 'between:-100,100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'allow_multiple' => ['sometimes', 'boolean'],
            'cooldown_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->scoringService->updateRule($leadScoringRule, $validated);

        return back()->with('success', "Scoring rule '{$leadScoringRule->name}' updated to {$leadScoringRule->points} points.");
    }

    /**
     * Create a new custom scoring rule.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_key' => ['required', 'string', 'max:100', 'unique:lead_scoring_rules,event_key', 'regex:/^[a-z0-9_]+$/'],
            'category' => ['required', 'string', 'max:50'],
            'points' => ['required', 'integer', 'between:-100,100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'allow_multiple' => ['sometimes', 'boolean'],
        ]);

        LeadScoringRule::create($validated);

        return back()->with('success', "New scoring rule '{$validated['name']}' created successfully.");
    }

    /**
     * Toggle active state of a scoring rule.
     */
    public function toggleActive(Request $request, LeadScoringRule $leadScoringRule): RedirectResponse
    {
        $this->authorizeEdit($request);

        $this->scoringService->toggleRule($leadScoringRule);

        $statusStr = $leadScoringRule->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Scoring rule '{$leadScoringRule->name}' {$statusStr}.");
    }

    /**
     * Reset scoring rules to system defaults.
     */
    public function resetDefaults(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);

        $this->scoringService->resetToDefaults();

        return back()->with('success', 'Lead scoring rules have been restored to initial defaults.');
    }

    /**
     * Delete a custom scoring rule.
     */
    public function destroy(Request $request, LeadScoringRule $leadScoringRule): RedirectResponse
    {
        $this->authorizeEdit($request);

        $name = $leadScoringRule->name;
        $leadScoringRule->delete();

        return back()->with('success', "Scoring rule '{$name}' deleted.");
    }

    protected function authorizeView(Request $request): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasPermissionTo(PermissionEnum::SettingsView->value) || $user->hasPermissionTo(PermissionEnum::LeadsView->value)) {
            return;
        }

        abort(403, 'Unauthorized to view lead scoring rules.');
    }

    protected function authorizeEdit(Request $request): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasPermissionTo(PermissionEnum::SettingsEdit->value) || $user->hasPermissionTo(PermissionEnum::LeadsEdit->value)) {
            return;
        }

        abort(403, 'Unauthorized to modify lead scoring rules.');
    }
}
