<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\Property\PropertyIntelligenceService;
use App\Services\Property\PropertyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function __construct(
        protected PropertyService $propertyService,
        protected PropertyIntelligenceService $intelligenceService
    ) {}

    /**
     * Get paginated properties list.
     */
    public function index(Request $request): JsonResponse
    {
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

        $perPage = (int) $request->input('per_page', 15);
        $properties = $this->propertyService->getPaginatedProperties($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $properties->items(),
            'pagination' => [
                'current_page' => $properties->currentPage(),
                'last_page' => $properties->lastPage(),
                'per_page' => $properties->perPage(),
                'total' => $properties->total(),
            ],
        ]);
    }

    /**
     * Get single property details.
     */
    public function show(Property $property): JsonResponse
    {
        $property->load([
            'estate',
            'activePrice',
            'prices',
            'media',
            'promotion',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $property,
            'ai_facts' => $this->intelligenceService->formatPropertyForAi($property),
        ]);
    }

    /**
     * Get authoritative AI context for chatbot and system prompt grounding.
     */
    public function aiContext(Request $request): JsonResponse
    {
        $estateSlug = $request->input('estate_slug');
        $format = $request->input('format', 'json');

        if ($format === 'markdown') {
            $markdown = $this->intelligenceService->formatAiSystemPromptContext($estateSlug);

            return response()->json([
                'status' => 'success',
                'format' => 'markdown',
                'context' => $markdown,
            ]);
        }

        $kb = $this->intelligenceService->getAuthoritativeKnowledgeBase($estateSlug);

        return response()->json([
            'status' => 'success',
            'format' => 'json',
            'data' => $kb,
        ]);
    }

    /**
     * Real-time property inventory query for AI function calling / tools.
     */
    public function aiQuery(Request $request): JsonResponse
    {
        $criteria = $request->validate([
            'location' => ['nullable', 'string'],
            'max_price' => ['nullable', 'numeric'],
            'min_price' => ['nullable', 'numeric'],
            'property_type' => ['nullable', 'string'],
            'title_document' => ['nullable', 'string'],
            'estate_slug' => ['nullable', 'string'],
            'in_stock_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $results = $this->intelligenceService->queryPropertiesForAi($criteria);

        return response()->json([
            'status' => 'success',
            'count' => $results->count(),
            'authoritative_source' => 'Bamcom Live Database',
            'data' => $results,
        ]);
    }
}
