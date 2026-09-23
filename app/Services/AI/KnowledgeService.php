<?php

namespace App\Services\AI;

use App\Models\Estate;
use App\Models\Property;
use App\Services\Property\PropertyIntelligenceService;
use Illuminate\Support\Facades\Cache;

/**
 * Enterprise Knowledge Retrieval Engine for AI Grounding.
 *
 * Provides authoritative company, estate, and property inventory context
 * ensuring zero hallucinations for customer communications.
 */
class KnowledgeService
{
    public function __construct(
        protected PropertyIntelligenceService $propertyIntelligence
    ) {}

    /**
     * Retrieve authoritative real estate knowledge base with optional caching.
     *
     * @return array<string, mixed>
     */
    public function getAuthoritativeKnowledgeBase(?string $estateSlug = null): array
    {
        $cacheKey = 'ai_knowledge_base_'.($estateSlug ?? 'all');

        return Cache::remember($cacheKey, 300, function () use ($estateSlug): array {
            $base = $this->propertyIntelligence->getAuthoritativeKnowledgeBase($estateSlug);
            $base['company_faq'] = $this->getCompanyFaqs();

            return $base;
        });
    }

    /**
     * Search knowledge base for facts matching a customer's query.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function searchKnowledge(string $query, array $filters = []): array
    {
        $term = strtolower(trim($query));

        // 1. Check matching estates
        $estates = Estate::query()
            ->active()
            ->where(function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('location', 'like', "%{$term}%")
                    ->orWhere('city', 'like', "%{$term}%")
                    ->orWhere('title_document', 'like', "%{$term}%");
            })
            ->get();

        // 2. Query properties matching criteria or query term
        $propertyCriteria = array_merge([
            'location' => $filters['location'] ?? null,
            'max_price' => $filters['max_price'] ?? null,
            'min_price' => $filters['min_price'] ?? null,
            'property_type' => $filters['property_type'] ?? null,
            'estate_slug' => $filters['estate_slug'] ?? null,
            'in_stock_only' => true,
            'limit' => 5,
        ], ! empty($term) && empty($filters['location']) ? ['location' => $term] : []);

        $properties = $this->propertyIntelligence->queryPropertiesForAi($propertyCriteria);

        // If no properties found by specific location, fallback to featured available inventory
        if ($properties->isEmpty()) {
            $properties = Property::query()
                ->available()
                ->published()
                ->with(['estate', 'activePrice', 'primaryMedia'])
                ->orderBy('is_featured', 'desc')
                ->take(3)
                ->get()
                ->map(fn (Property $p): array => $this->propertyIntelligence->formatPropertyForAi($p));
        }

        // 3. Search Company FAQs
        $faqs = collect($this->getCompanyFaqs())
            ->filter(function (array $item) use ($term): bool {
                if (empty($term)) {
                    return true;
                }

                return str_contains(strtolower($item['question']), $term)
                    || str_contains(strtolower($item['answer']), $term)
                    || in_array($term, array_map('strtolower', $item['keywords']));
            })
            ->values()
            ->toArray();

        return [
            'query' => $query,
            'matched_estates' => $estates->map(fn (Estate $e): array => [
                'name' => $e->name,
                'location' => $e->location,
                'title_document' => $e->title_document,
                'landmarks' => $e->landmarks,
                'features' => $e->features ?? [],
            ])->toArray(),
            'matched_properties' => $properties->toArray(),
            'matched_faqs' => $faqs,
        ];
    }

    /**
     * Build formatted system prompt context injection string.
     */
    public function buildPromptContext(string $query, array $filters = []): string
    {
        $search = $this->searchKnowledge($query, $filters);

        $out = "=== AUTHORITATIVE BAMCOM REAL ESTATE GROUND TRUTH ===\n";
        $out .= "The following information is retrieved directly from Bamcom's live database. It represents 100% verified facts. NEVER contradict or invent prices, titles, or units.\n\n";

        // Estates Section
        if (! empty($search['matched_estates'])) {
            $out .= "## ESTATES:\n";
            foreach ($search['matched_estates'] as $est) {
                $out .= "- **{$est['name']}** in {$est['location']}. Verified Title: {$est['title_document']}. Landmarks: {$est['landmarks']}.\n";
            }
            $out .= "\n";
        }

        // Properties Section
        if (! empty($search['matched_properties'])) {
            $out .= "## AVAILABLE INVENTORY:\n";
            foreach ($search['matched_properties'] as $prop) {
                $priceStr = $prop['pricing']['has_active_promo']
                    ? "Promo: {$prop['pricing']['effective_price_formatted']} (Save {$prop['pricing']['savings_formatted']})"
                    : "Price: {$prop['pricing']['effective_price_formatted']}";

                $out .= "### {$prop['title']} ({$prop['estate_name']})\n";
                $out .= "- Location: {$prop['location']}\n";
                $out .= "- Plot Size: {$prop['plot_size']} | Title: {$prop['title_document']}\n";
                $out .= "- {$priceStr}\n";
                $out .= "- Initial Deposit: {$prop['pricing']['initial_deposit_formatted']}\n";
                $out .= "- Payment Plan: {$prop['payment_plan_summary']}\n";
                $out .= "- Units Available: {$prop['available_units']}\n";
                if (! empty($prop['features'])) {
                    $out .= '- Features: '.implode(', ', array_slice($prop['features'], 0, 4))."\n";
                }
                $out .= "\n";
            }
        }

        // FAQs Section
        if (! empty($search['matched_faqs'])) {
            $out .= "## FREQUENTLY ASKED QUESTIONS & POLICIES:\n";
            foreach ($search['matched_faqs'] as $faq) {
                $out .= "Q: {$faq['question']}\nA: {$faq['answer']}\n\n";
            }
        }

        return $out;
    }

    /**
     * Standard company legitimacy and policy FAQs.
     *
     * @return list<array{question: string, answer: string, keywords: list<string>}>
     */
    public function getCompanyFaqs(): array
    {
        return [
            [
                'question' => 'Is Bamcom Properties a legally registered company in Nigeria?',
                'answer' => 'Yes, Bamcom Properties and Real Estate Ltd is fully incorporated with the Corporate Affairs Commission (CAC) of Nigeria. All our estates possess verified government-approved land titles (such as Certificate of Occupancy, Governor\'s Consent, or Registered Survey).',
                'keywords' => ['legit', 'real', 'scam', 'cac', 'registration', 'legal'],
            ],
            [
                'question' => 'How can I schedule a physical site inspection?',
                'answer' => 'Site inspections are conducted from Monday through Saturday (10:00 AM and 2:00 PM). We provide designated pickup points from our Lekki office. You can schedule an inspection directly with us here by letting us know your preferred date and time.',
                'keywords' => ['inspection', 'visit', 'site', 'tour', 'physical'],
            ],
            [
                'question' => 'What payment plans are offered?',
                'answer' => 'We offer outright purchase discounts as well as flexible installment structures spanning 3, 6, and 12 months with an initial deposit (typically 20% to 30%). Zero interest applies to selected payment milestones.',
                'keywords' => ['payment plan', 'installment', 'spread', 'deposit', 'monthly'],
            ],
            [
                'question' => 'What documents do I receive upon initial deposit and full payment?',
                'answer' => 'Upon initial deposit, you receive an Official Payment Receipt and Contract of Sale / Provisional Letter of Allocation. Upon full payment, you receive a Deed of Assignment, Registered Survey, and Physical Plot Allocation Certificate.',
                'keywords' => ['documents', 'deed of assignment', 'survey', 'receipt', 'allocation'],
            ],
        ];
    }
}
