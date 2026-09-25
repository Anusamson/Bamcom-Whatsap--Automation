<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\TitleDocument;
use App\Http\Requests\Property\CreatePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Models\Estate;
use App\Models\Promotion;
use App\Models\Property;
use App\Services\Property\PropertyIntelligenceService;
use App\Services\Property\PropertyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function __construct(
        protected PropertyService $propertyService,
        protected PropertyIntelligenceService $intelligenceService
    ) {}

    /**
     * Display a listing of properties with filtering and inventory metrics.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Property::class);

        $filters = $request->only([
            'search',
            'estate_id',
            'property_type',
            'availability',
            'status',
            'title_document',
            'min_price',
            'max_price',
            'is_featured',
        ]);

        $properties = $this->propertyService->getPaginatedProperties($filters);

        $estates = Estate::query()
            ->select('id', 'name', 'slug', 'city', 'state')
            ->orderBy('name')
            ->get();

        $metrics = [
            'total_properties' => Property::count(),
            'available_units' => (int) Property::where('availability', 'available')->sum('available_units'),
            'sold_units' => (int) Property::where('availability', 'sold_out')->count(),
            'total_estates' => Estate::where('status', 'active')->count(),
        ];

        $propertyTypes = array_map(fn (PropertyType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'badge' => $type->badgeClass(),
        ], PropertyType::cases());

        $availabilities = array_map(fn (PropertyStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'badge' => $status->badgeClass(),
        ], PropertyStatus::cases());

        $titleDocuments = array_map(fn (TitleDocument $doc): array => [
            'value' => $doc->value,
            'label' => $doc->label(),
            'badge' => $doc->badgeClass(),
        ], TitleDocument::cases());

        return Inertia::render('Properties/Index', [
            'properties' => $properties,
            'filters' => $filters,
            'estates' => $estates,
            'metrics' => $metrics,
            'propertyTypes' => $propertyTypes,
            'availabilities' => $availabilities,
            'titleDocuments' => $titleDocuments,
        ]);
    }

    /**
     * Show the form for creating a new property.
     */
    public function create(): Response
    {
        Gate::authorize('create', Property::class);

        $estates = Estate::query()
            ->select('id', 'name', 'location', 'title_document')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $promotions = Promotion::query()
            ->select('id', 'name', 'code', 'discount_type', 'discount_value')
            ->active()
            ->get();

        $propertyTypes = array_map(fn (PropertyType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
        ], PropertyType::cases());

        $availabilities = array_map(fn (PropertyStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ], PropertyStatus::cases());

        $titleDocuments = array_map(fn (TitleDocument $doc): array => [
            'value' => $doc->value,
            'label' => $doc->label(),
        ], TitleDocument::cases());

        return Inertia::render('Properties/Create', [
            'estates' => $estates,
            'promotions' => $promotions,
            'propertyTypes' => $propertyTypes,
            'availabilities' => $availabilities,
            'titleDocuments' => $titleDocuments,
        ]);
    }

    /**
     * Store a newly created property in storage.
     */
    public function store(CreatePropertyRequest $request): RedirectResponse
    {
        Gate::authorize('create', Property::class);

        $property = $this->propertyService->createProperty($request->validated());

        return redirect()->route('properties.show', $property)
            ->with('success', "Property '{$property->title}' added successfully.");
    }

    /**
     * Display the specified property 360 profile, payment schedule, and AI facts.
     */
    public function show(Property $property): Response
    {
        Gate::authorize('view', $property);

        $property->load([
            'estate',
            'activePrice',
            'prices' => fn ($q) => $q->latest(),
            'media',
            'primaryMedia',
            'promotion',
            'leads.contact',
            'leads.assignedUser',
        ]);

        $aiFactSheet = $this->intelligenceService->formatPropertyForAi($property);
        $whatsappPitch = $this->intelligenceService->getWhatsappPitch($property);

        return Inertia::render('Properties/Show', [
            'property' => $property,
            'aiFactSheet' => $aiFactSheet,
            'whatsappPitch' => $whatsappPitch,
        ]);
    }

    /**
     * Show the form for editing the specified property.
     */
    public function edit(Property $property): Response
    {
        Gate::authorize('update', $property);

        $property->load(['estate', 'activePrice', 'media', 'primaryMedia', 'promotion']);

        $estates = Estate::query()
            ->select('id', 'name', 'location', 'title_document')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $promotions = Promotion::query()
            ->select('id', 'name', 'code', 'discount_type', 'discount_value')
            ->active()
            ->get();

        $propertyTypes = array_map(fn (PropertyType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
        ], PropertyType::cases());

        $availabilities = array_map(fn (PropertyStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ], PropertyStatus::cases());

        $titleDocuments = array_map(fn (TitleDocument $doc): array => [
            'value' => $doc->value,
            'label' => $doc->label(),
        ], TitleDocument::cases());

        return Inertia::render('Properties/Edit', [
            'property' => $property,
            'estates' => $estates,
            'promotions' => $promotions,
            'propertyTypes' => $propertyTypes,
            'availabilities' => $availabilities,
            'titleDocuments' => $titleDocuments,
        ]);
    }

    /**
     * Update the specified property in storage.
     */
    public function update(UpdatePropertyRequest $request, Property $property): RedirectResponse
    {
        Gate::authorize('update', $property);

        $this->propertyService->updateProperty($property, $request->validated());

        return redirect()->route('properties.show', $property)
            ->with('success', "Property '{$property->title}' updated successfully.");
    }

    /**
     * Remove the specified property from storage.
     */
    public function destroy(Property $property): RedirectResponse
    {
        Gate::authorize('delete', $property);

        $title = $property->title;
        $this->propertyService->deleteProperty($property);

        return redirect()->route('properties.index')
            ->with('success', "Property '{$title}' deleted successfully.");
    }
}
