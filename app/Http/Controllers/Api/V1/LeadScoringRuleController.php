<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadScoringRule;
use App\Services\Lead\LeadScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadScoringRuleController extends Controller
{
    public function __construct(
        protected LeadScoringService $scoringService
    ) {}

    /**
     * List all lead scoring rules.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $rules = $this->scoringService->getRules();

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    /**
     * Update an existing scoring rule.
     */
    public function update(Request $request, LeadScoringRule $rule): JsonResponse
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

        $this->scoringService->updateRule($rule, $validated);

        return response()->json([
            'success' => true,
            'message' => "Scoring rule '{$rule->name}' updated successfully.",
            'data' => $rule,
        ]);
    }

    /**
     * Toggle active state of a scoring rule.
     */
    public function toggle(Request $request, LeadScoringRule $rule): JsonResponse
    {
        $this->authorizeEdit($request);

        $this->scoringService->toggleRule($rule);

        return response()->json([
            'success' => true,
            'message' => "Scoring rule '{$rule->name}' status updated.",
            'data' => $rule,
        ]);
    }

    /**
     * Reset rules to system defaults.
     */
    public function resetDefaults(Request $request): JsonResponse
    {
        $this->authorizeEdit($request);

        $rules = $this->scoringService->resetToDefaults();

        return response()->json([
            'success' => true,
            'message' => 'Scoring rules reset to initial system defaults.',
            'data' => $rules,
        ]);
    }

    /**
     * Trigger a lead scoring event on a specific lead.
     */
    public function recordEvent(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeEdit($request);

        $validated = $request->validate([
            'event_key' => ['required', 'string'],
            'context' => ['nullable', 'array'],
        ]);

        $log = $this->scoringService->recordEvent(
            $lead,
            $validated['event_key'],
            $validated['context'] ?? [],
            $request->user(),
            source: 'api'
        );

        if (! $log) {
            return response()->json([
                'success' => false,
                'message' => "Event '{$validated['event_key']}' was not applied (rule not active or already satisfied).",
                'data' => [
                    'current_score' => $lead->score,
                    'temperature' => $lead->temperature->value,
                ],
            ], 422);
        }

        $lead->refresh();

        return response()->json([
            'success' => true,
            'message' => "Lead scoring event '{$validated['event_key']}' applied (+{$log->points_awarded} pts).",
            'data' => [
                'log' => $log,
                'lead' => [
                    'id' => $lead->id,
                    'score' => $lead->score,
                    'temperature' => $lead->temperature->value,
                ],
            ],
        ]);
    }

    /**
     * View history of score changes for a specific lead.
     */
    public function history(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeView($request);

        $history = $lead->scoreLogs()
            ->with(['rule', 'actor'])
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
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
