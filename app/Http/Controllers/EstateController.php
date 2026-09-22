<?php

namespace App\Http\Controllers;

use App\Enums\TitleDocument;
use App\Http\Requests\Estate\CreateEstateRequest;
use App\Http\Requests\Estate\UpdateEstateRequest;
use App\Models\Estate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EstateController extends Controller
{
    /**
     * Display a listing of estates.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Estate::class);

        $search = $request->input('search');

        $estates = Estate::query()
            ->withCount(['properties', 'media'])
            ->when($search, fn ($q) => $q->search((string) $search))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $titleDocuments = array_map(fn (TitleDocument $doc): array => [
            'value' => $doc->value,
            'label' => $doc->label(),
        ], TitleDocument::cases());

        return Inertia::render('Estates/Index', [
            'estates' => $estates,
            'filters' => ['search' => $search],
            'titleDocuments' => $titleDocuments,
        ]);
    }

    /**
     * Show the form for creating a new estate.
     */
    public function create(): Response
    {
        Gate::authorize('create', Estate::class);

        $titleDocuments = array_map(fn (TitleDocument $doc): array => [
            'value' => $doc->value,
            'label' => $doc->label(),
        ], TitleDocument::cases());

        return Inertia::render('Estates/Create', [
            'titleDocuments' => $titleDocuments,
        ]);
    }

    /**
     * Store a newly created estate in storage.
     */
    public function store(CreateEstateRequest $request): RedirectResponse
    {
        Gate::authorize('create', Estate::class);

        $estate = Estate::create($request->validated());

        return redirect()->route('estates.show', $estate)
            ->with('success', "Estate '{$estate->name}' registered successfully.");
    }

    /**
     * Display the specified estate, including its property inventory.
     */
    public function show(Estate $estate): Response
    {
        Gate::authorize('view', $estate);

        $estate->load([
            'properties.activePrice',
            'properties.primaryMedia',
            'media',
        ]);

        return Inertia::render('Estates/Show', [
            'estate' => $estate,
            'properties' => $estate->properties,
        ]);
    }

    /**
     * Show the form for editing the specified estate.
     */
    public function edit(Estate $estate): Response
    {
        Gate::authorize('update', $estate);

        $titleDocuments = array_map(fn (TitleDocument $doc): array => [
            'value' => $doc->value,
            'label' => $doc->label(),
        ], TitleDocument::cases());

        return Inertia::render('Estates/Edit', [
            'estate' => $estate,
            'titleDocuments' => $titleDocuments,
        ]);
    }

    /**
     * Update the specified estate in storage.
     */
    public function update(UpdateEstateRequest $request, Estate $estate): RedirectResponse
    {
        Gate::authorize('update', $estate);

        $estate->update($request->validated());

        return redirect()->route('estates.show', $estate)
            ->with('success', "Estate '{$estate->name}' updated successfully.");
    }

    /**
     * Remove the specified estate from storage.
     */
    public function destroy(Estate $estate): RedirectResponse
    {
        Gate::authorize('delete', $estate);

        $name = $estate->name;
        $estate->delete();

        return redirect()->route('estates.index')
            ->with('success', "Estate '{$name}' deleted successfully.");
    }
}
