<?php

namespace App\Http\Controllers;

use App\Models\PipelineStage;
use App\Models\SmartList;
use App\Services\SmartList\SmartListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmartListController extends Controller
{
    public function __construct(
        protected SmartListService $smartListService
    ) {}

    /**
     * Display a listing of smart lists.
     */
    public function index(Request $request): Response
    {
        $tab = $request->input('tab', 'all'); // 'all', 'favorites', 'presets', 'custom'
        $search = $request->input('search');

        $query = SmartList::query()
            ->with('creator:id,name')
            ->orderByDesc('is_favorite')
            ->orderByDesc('is_preset')
            ->orderBy('name');

        if ($tab === 'favorites') {
            $query->where('is_favorite', true);
        } elseif ($tab === 'presets') {
            $query->where('is_preset', true);
        } elseif ($tab === 'custom') {
            $query->where('is_preset', false);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $smartLists = $query->paginate(20)->withQueryString();

        // Refresh cached count dynamically for current page items
        foreach ($smartLists->items() as $list) {
            $list->refreshCount();
        }

        $fieldCatalog = [
            [
                'field' => 'contact.location',
                'label' => 'Contact Location / City',
                'category' => 'Contact',
                'operators' => ['contains', 'equals', 'not_contains', 'is_empty', 'is_not_empty'],
                'type' => 'text',
                'placeholder' => 'e.g. Abuja, Lekki, Ikoyi',
            ],
            [
                'field' => 'lead.temperature',
                'label' => 'Lead Temperature',
                'category' => 'Lead',
                'operators' => ['equals', 'not_equals', 'in'],
                'type' => 'select',
                'options' => [
                    ['value' => 'hot', 'label' => 'Hot 🔥'],
                    ['value' => 'warm', 'label' => 'Warm ⚡'],
                    ['value' => 'cold', 'label' => 'Cold ❄️'],
                ],
            ],
            [
                'field' => 'contact.last_contact_days',
                'label' => 'Inactive Touchpoint Days (Recency)',
                'category' => 'Contact',
                'operators' => ['greater_than_or_equal', 'less_than_or_equal', 'greater_than', 'less_than'],
                'type' => 'number',
                'placeholder' => 'e.g. 30 (dormant prospects)',
            ],
            [
                'field' => 'inspection.status',
                'label' => 'Inspection Status',
                'category' => 'Inspection',
                'operators' => ['equals', 'in', 'not_equals'],
                'type' => 'select',
                'options' => [
                    ['value' => 'requested', 'label' => 'Requested'],
                    ['value' => 'scheduled', 'label' => 'Scheduled'],
                    ['value' => 'completed', 'label' => 'Completed'],
                    ['value' => 'cancelled', 'label' => 'Cancelled'],
                ],
            ],
            [
                'field' => 'deal.stage_name',
                'label' => 'Deal Pipeline Stage',
                'category' => 'Deal',
                'operators' => ['contains', 'equals', 'not_contains'],
                'type' => 'text',
                'placeholder' => 'e.g. Payment Pending, Negotiation',
            ],
            [
                'field' => 'property.title',
                'label' => 'Property / Estate Interest',
                'category' => 'Property',
                'operators' => ['contains', 'equals'],
                'type' => 'text',
                'placeholder' => 'e.g. Peace Court, Silverstone',
            ],
            [
                'field' => 'lead.score',
                'label' => 'Lead Score',
                'category' => 'Lead',
                'operators' => ['greater_than_or_equal', 'less_than_or_equal', 'greater_than', 'less_than', 'equals'],
                'type' => 'number',
                'placeholder' => 'e.g. 75',
            ],
            [
                'field' => 'contact.lead_source',
                'label' => 'Lead Acquisition Source',
                'category' => 'Contact',
                'operators' => ['equals', 'not_equals', 'in'],
                'type' => 'select',
                'options' => [
                    ['value' => 'whatsapp', 'label' => 'WhatsApp'],
                    ['value' => 'website', 'label' => 'Website'],
                    ['value' => 'referral', 'label' => 'Referral'],
                    ['value' => 'instagram', 'label' => 'Instagram'],
                    ['value' => 'facebook', 'label' => 'Facebook'],
                    ['value' => 'billboard', 'label' => 'Billboard'],
                ],
            ],
            [
                'field' => 'contact.status',
                'label' => 'Contact Status',
                'category' => 'Contact',
                'operators' => ['equals', 'not_equals', 'in'],
                'type' => 'select',
                'options' => [
                    ['value' => 'lead', 'label' => 'Lead'],
                    ['value' => 'prospect', 'label' => 'Prospect'],
                    ['value' => 'customer', 'label' => 'Customer'],
                    ['value' => 'lost', 'label' => 'Lost'],
                ],
            ],
            [
                'field' => 'contact.has_opted_out',
                'label' => 'Opted Out Status',
                'category' => 'Safety',
                'operators' => ['equals'],
                'type' => 'select',
                'options' => [
                    ['value' => 'true', 'label' => 'Yes (Opted Out)'],
                    ['value' => 'false', 'label' => 'No (Subscribed)'],
                ],
            ],
        ];

        return Inertia::render('SmartLists/Index', [
            'smartLists' => $smartLists,
            'filters' => [
                'tab' => $tab,
                'search' => $search ?? '',
            ],
            'fieldCatalog' => $fieldCatalog,
            'pipelineStages' => PipelineStage::query()->orderBy('order_column')->get(['id', 'name']),
        ]);
    }

    /**
     * Display a specific smart list and its dynamically matching contacts.
     */
    public function show(Request $request, SmartList $smartList): Response
    {
        $search = $request->input('search');
        $smartList->load('creator:id,name');

        $smartList->refreshCount();
        $contacts = $this->smartListService->getContactsForList($smartList, 25, $search);

        return Inertia::render('SmartLists/Show', [
            'smartList' => $smartList,
            'contacts' => $contacts,
            'filters' => [
                'search' => $search ?? '',
            ],
        ]);
    }

    /**
     * Store a newly created smart list.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rule_groups' => 'required|array',
            'rule_groups.logical_operator' => 'required|string|in:AND,OR,and,or',
            'rule_groups.rules' => 'required|array|min:1',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'is_favorite' => 'nullable|boolean',
        ]);

        $smartList = $this->smartListService->createSmartList($validated, $request->user());

        return redirect()->route('smart-lists.show', $smartList)
            ->with('success', "Smart List '{$smartList->name}' created ({$smartList->cached_count} matching contacts).");
    }

    /**
     * Update an existing smart list.
     */
    public function update(Request $request, SmartList $smartList): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rule_groups' => 'required|array',
            'rule_groups.logical_operator' => 'required|string|in:AND,OR,and,or',
            'rule_groups.rules' => 'required|array|min:1',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'is_favorite' => 'nullable|boolean',
        ]);

        $this->smartListService->updateSmartList($smartList, $validated);

        return back()->with('success', "Smart List '{$smartList->name}' updated ({$smartList->cached_count} matching contacts).");
    }

    /**
     * Delete smart list.
     */
    public function destroy(SmartList $smartList): RedirectResponse
    {
        $name = $smartList->name;
        $this->smartListService->deleteSmartList($smartList);

        return redirect()->route('smart-lists.index')
            ->with('success', "Smart List '{$name}' deleted.");
    }

    /**
     * Toggle favorite status.
     */
    public function toggleFavorite(SmartList $smartList): RedirectResponse
    {
        $smartList->update(['is_favorite' => ! $smartList->is_favorite]);

        return back()->with('success', $smartList->is_favorite ? 'Added to favorites.' : 'Removed from favorites.');
    }

    /**
     * Real-time preview of matching count and sample records.
     */
    public function preview(Request $request): JsonResponse
    {
        $ruleGroups = (array) $request->input('rule_groups', []);
        $preview = $this->smartListService->previewCount($ruleGroups);

        return response()->json($preview);
    }
}
