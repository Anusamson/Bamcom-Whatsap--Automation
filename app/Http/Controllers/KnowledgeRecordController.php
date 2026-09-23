<?php

namespace App\Http\Controllers;

use App\Enums\KnowledgeCategory;
use App\Enums\KnowledgeStatus;
use App\Http\Requests\Knowledge\CreateKnowledgeRecordRequest;
use App\Http\Requests\Knowledge\UpdateKnowledgeRecordRequest;
use App\Models\KnowledgeRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeRecordController extends Controller
{
    /**
     * Display a listing of knowledge records across the 8 administrative domains.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeRecord::class);

        $category = $request->input('category');
        $status = $request->input('status');
        $search = $request->input('search');

        $query = KnowledgeRecord::query()
            ->with(['creator', 'updater'])
            ->when($category, fn ($q) => $q->category($category))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->search((string) $search))
            ->prioritized();

        $records = $query->paginate(15)->withQueryString();

        // Calculate Category Counts
        $categoryCounts = KnowledgeRecord::query()
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $categories = array_map(fn (KnowledgeCategory $cat): array => [
            'value' => $cat->value,
            'label' => $cat->label(),
            'description' => $cat->description(),
            'icon' => $cat->icon(),
            'badgeClasses' => $cat->badgeClasses(),
            'count' => $categoryCounts[$cat->value] ?? 0,
        ], KnowledgeCategory::cases());

        $statuses = array_map(fn (KnowledgeStatus $st): array => [
            'value' => $st->value,
            'label' => $st->label(),
            'badgeClasses' => $st->badgeClasses(),
        ], KnowledgeStatus::cases());

        // Overall stats
        $stats = [
            'total' => KnowledgeRecord::count(),
            'active_ai' => KnowledgeRecord::active()->count(),
            'draft' => KnowledgeRecord::where('status', KnowledgeStatus::Draft->value)->count(),
            'archived' => KnowledgeRecord::where('status', KnowledgeStatus::Archived->value)->count(),
        ];

        return Inertia::render('Knowledge/Index', [
            'records' => $records,
            'categories' => $categories,
            'statuses' => $statuses,
            'stats' => $stats,
            'filters' => [
                'category' => $category,
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new knowledge record.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', KnowledgeRecord::class);

        $categories = array_map(fn (KnowledgeCategory $cat): array => [
            'value' => $cat->value,
            'label' => $cat->label(),
            'description' => $cat->description(),
            'icon' => $cat->icon(),
            'badgeClasses' => $cat->badgeClasses(),
        ], KnowledgeCategory::cases());

        $statuses = array_map(fn (KnowledgeStatus $st): array => [
            'value' => $st->value,
            'label' => $st->label(),
            'badgeClasses' => $st->badgeClasses(),
        ], KnowledgeStatus::cases());

        return Inertia::render('Knowledge/Create', [
            'categories' => $categories,
            'statuses' => $statuses,
            'initialCategory' => $request->input('category', KnowledgeCategory::CompanyInformation->value),
        ]);
    }

    /**
     * Store a newly created knowledge record in storage.
     */
    public function store(CreateKnowledgeRecordRequest $request): RedirectResponse
    {
        Gate::authorize('create', KnowledgeRecord::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $record = KnowledgeRecord::create($data);

        return redirect()->route('knowledge.index', ['category' => $record->category->value])
            ->with('success', "Knowledge record '{$record->title}' created successfully.");
    }

    /**
     * Display the specified knowledge record.
     */
    public function show(KnowledgeRecord $knowledge): Response
    {
        Gate::authorize('view', $knowledge);

        $knowledge->load(['creator', 'updater']);

        return Inertia::render('Knowledge/Show', [
            'record' => array_merge($knowledge->toArray(), [
                'is_active_for_ai' => $knowledge->isActive(),
                'is_expired' => $knowledge->isExpired(),
                'is_scheduled' => $knowledge->isScheduled(),
                'category_label' => $knowledge->category->label(),
                'category_description' => $knowledge->category->description(),
                'category_badge' => $knowledge->category->badgeClasses(),
                'status_label' => $knowledge->status->label(),
                'status_badge' => $knowledge->status->badgeClasses(),
            ]),
        ]);
    }

    /**
     * Show the form for editing the specified knowledge record.
     */
    public function edit(KnowledgeRecord $knowledge): Response
    {
        Gate::authorize('update', $knowledge);

        $categories = array_map(fn (KnowledgeCategory $cat): array => [
            'value' => $cat->value,
            'label' => $cat->label(),
            'description' => $cat->description(),
            'icon' => $cat->icon(),
            'badgeClasses' => $cat->badgeClasses(),
        ], KnowledgeCategory::cases());

        $statuses = array_map(fn (KnowledgeStatus $st): array => [
            'value' => $st->value,
            'label' => $st->label(),
            'badgeClasses' => $st->badgeClasses(),
        ], KnowledgeStatus::cases());

        return Inertia::render('Knowledge/Edit', [
            'record' => $knowledge,
            'categories' => $categories,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Update the specified knowledge record in storage.
     */
    public function update(UpdateKnowledgeRecordRequest $request, KnowledgeRecord $knowledge): RedirectResponse
    {
        Gate::authorize('update', $knowledge);

        $data = $request->validated();
        $data['updated_by'] = $request->user()?->id;

        $knowledge->update($data);

        return redirect()->route('knowledge.index', ['category' => $knowledge->category->value])
            ->with('success', "Knowledge record '{$knowledge->title}' updated successfully.");
    }

    /**
     * Remove the specified knowledge record from storage.
     */
    public function destroy(KnowledgeRecord $knowledge): RedirectResponse
    {
        Gate::authorize('delete', $knowledge);

        $title = $knowledge->title;
        $category = $knowledge->category->value;
        $knowledge->delete();

        return redirect()->route('knowledge.index', ['category' => $category])
            ->with('success', "Knowledge record '{$title}' removed successfully.");
    }

    /**
     * Toggle the status between Active and Draft.
     */
    public function toggleStatus(KnowledgeRecord $knowledge): RedirectResponse
    {
        Gate::authorize('update', $knowledge);

        $newStatus = $knowledge->status === KnowledgeStatus::Active
            ? KnowledgeStatus::Draft
            : KnowledgeStatus::Active;

        $knowledge->update([
            'status' => $newStatus,
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', "Status of '{$knowledge->title}' updated to {$newStatus->label()}.");
    }
}
