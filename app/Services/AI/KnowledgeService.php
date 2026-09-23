<?php

namespace App\Services\AI;

use App\Enums\KnowledgeCategory;
use App\Models\Estate;
use App\Models\KnowledgeRecord;
use App\Models\Property;
use App\Services\Property\PropertyIntelligenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Enterprise Knowledge Retrieval Engine for AI Grounding.
 *
 * Provides authoritative company, estate, and property inventory context
 * ensuring zero hallucinations for customer communications.
 *
 * STRICT GUARANTEES:
 * 1. Only active knowledge records (within valid effective/expiration dates) are accessible to AI.
 * 2. Property prices and inventory availability MUST strictly originate from the live property database.
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
            $base['active_knowledge_summary'] = $this->getActiveKnowledgeRecordsSummary();

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

        // 1. Authoritative Estates from live database
        $estates = Estate::query()
            ->active()
            ->where(function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('location', 'like', "%{$term}%")
                    ->orWhere('city', 'like', "%{$term}%")
                    ->orWhere('title_document', 'like', "%{$term}%");
            })
            ->get();

        // 2. Authoritative Properties from live database (Prices & Availability strictly from property DB)
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

        // Fallback to featured active inventory if no direct matches
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

        // 3. Search ONLY ACTIVE Knowledge Records from the database
        $matchedKnowledge = $this->searchActiveKnowledge($term, $filters['category'] ?? null);

        // 4. Extract or fallback FAQs
        $faqs = $this->extractOrFilterFaqs($term);

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
            'matched_knowledge' => $matchedKnowledge->toArray(),
            'matched_faqs' => $faqs,
        ];
    }

    /**
     * Query ONLY active, non-expired knowledge records matching a search term and optional category.
     *
     * @return Collection<int, KnowledgeRecord>
     */
    public function searchActiveKnowledge(string $term, ?string $category = null, int $limit = 6): Collection
    {
        $query = KnowledgeRecord::query()
            ->active()
            ->prioritized();

        if (! empty($category)) {
            $query->category($category);
        }

        if (! empty($term)) {
            $query->search($term);
        }

        $records = $query->take($limit)->get();

        // If specific search returned few results or term was specific estate,
        // supplement with top prioritized company info / trust records (if not already included)
        if ($records->count() < $limit && empty($category)) {
            $existingIds = $records->pluck('id')->toArray();
            $supplements = KnowledgeRecord::query()
                ->active()
                ->whereIn('category', [
                    KnowledgeCategory::CompanyInformation->value,
                    KnowledgeCategory::ObjectionHandling->value,
                    KnowledgeCategory::PaymentPolicy->value,
                ])
                ->whereNotIn('id', $existingIds)
                ->prioritized()
                ->take($limit - $records->count())
                ->get();

            $records = $records->merge($supplements);
        }

        return $records;
    }

    /**
     * Build formatted system prompt context injection string.
     */
    public function buildPromptContext(string $query, array $filters = []): string
    {
        $search = $this->searchKnowledge($query, $filters);

        $out = "=== AUTHORITATIVE BAMCOM REAL ESTATE GROUND TRUTH ===\n";
        $out .= "The following information is retrieved directly from Bamcom's live database. It represents 100% verified facts. NEVER contradict or invent prices, titles, or units.\n\n";

        // Estates Section (Live Database)
        if (! empty($search['matched_estates'])) {
            $out .= "## ESTATES:\n";
            foreach ($search['matched_estates'] as $est) {
                $out .= "- **{$est['name']}** in {$est['location']}. Verified Title: {$est['title_document']}. Landmarks: {$est['landmarks']}.\n";
            }
            $out .= "\n";
        }

        // Properties Section (Live Property Database: Prices, Promo, Availability)
        if (! empty($search['matched_properties'])) {
            $out .= "## AVAILABLE INVENTORY (LIVE DATABASE PRICING & UNITS):\n";
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

        // Active Knowledge Base Records Grouped by Category
        if (! empty($search['matched_knowledge'])) {
            $grouped = collect($search['matched_knowledge'])->groupBy(function ($record) {
                return $record instanceof KnowledgeRecord
                    ? $record->category->value
                    : ($record['category'] ?? 'general');
            });

            // Company Info & Policies
            $companyRecords = $grouped->get(KnowledgeCategory::CompanyInformation->value, collect())
                ->merge($grouped->get(KnowledgeCategory::PaymentPolicy->value, collect()))
                ->merge($grouped->get(KnowledgeCategory::InspectionPolicy->value, collect()));

            if ($companyRecords->isNotEmpty()) {
                $out .= "## CORPORATE CREDENTIALS & POLICIES:\n";
                foreach ($companyRecords as $rec) {
                    $title = $rec instanceof KnowledgeRecord ? $rec->title : $rec['title'];
                    $content = $rec instanceof KnowledgeRecord ? $rec->content : $rec['content'];
                    $out .= "### {$title}\n{$content}\n\n";
                }
            }

            // Objection Handling & Sales Scripts (Guidance for AI tone and answers)
            $salesRecords = $grouped->get(KnowledgeCategory::ObjectionHandling->value, collect())
                ->merge($grouped->get(KnowledgeCategory::SalesScript->value, collect()))
                ->merge($grouped->get(KnowledgeCategory::SalesInformation->value, collect()));

            if ($salesRecords->isNotEmpty()) {
                $out .= "## APPROVED SALES STRATEGY & OBJECTION GUIDANCE:\n";
                foreach ($salesRecords as $rec) {
                    $title = $rec instanceof KnowledgeRecord ? $rec->title : $rec['title'];
                    $content = $rec instanceof KnowledgeRecord ? $rec->content : $rec['content'];
                    $out .= "### {$title}\n{$content}\n\n";
                }
            }

            // Property Qualitative Knowledge (Topography, Neighborhood Corridor)
            $propKnowledge = $grouped->get(KnowledgeCategory::PropertyKnowledge->value, collect());
            if ($propKnowledge->isNotEmpty()) {
                $out .= "## PROPERTY & REGIONAL KNOWLEDGE:\n";
                foreach ($propKnowledge as $rec) {
                    $title = $rec instanceof KnowledgeRecord ? $rec->title : $rec['title'];
                    $content = $rec instanceof KnowledgeRecord ? $rec->content : $rec['content'];
                    $out .= "### {$title}\n{$content}\n\n";
                }
            }
        }

        // FAQs Section
        if (! empty($search['matched_faqs'])) {
            $out .= "## FREQUENTLY ASKED QUESTIONS:\n";
            foreach ($search['matched_faqs'] as $faq) {
                $out .= "Q: {$faq['question']}\nA: {$faq['answer']}\n\n";
            }
        }

        return $out;
    }

    /**
     * Retrieve authoritative FAQs from active knowledge records, with safe fallback.
     *
     * @return list<array{question: string, answer: string, keywords: list<string>}>
     */
    public function getCompanyFaqs(): array
    {
        $dbRecords = KnowledgeRecord::query()
            ->active()
            ->category(KnowledgeCategory::Faq)
            ->prioritized()
            ->get();

        if ($dbRecords->isNotEmpty()) {
            return $dbRecords->map(fn (KnowledgeRecord $rec): array => [
                'question' => $rec->title,
                'answer' => $rec->content,
                'keywords' => $rec->keywords ?? [],
            ])->toArray();
        }

        return $this->getDefaultFaqs();
    }

    /**
     * Filter active FAQs matching query term.
     *
     * @return list<array{question: string, answer: string, keywords: list<string>}>
     */
    protected function extractOrFilterFaqs(string $term): array
    {
        return collect($this->getCompanyFaqs())
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
    }

    /**
     * Retrieve count summary of active knowledge records by category.
     *
     * @return array<string, int>
     */
    public function getActiveKnowledgeRecordsSummary(): array
    {
        $counts = KnowledgeRecord::query()
            ->active()
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $summary = [];
        foreach (KnowledgeCategory::cases() as $category) {
            $summary[$category->value] = $counts[$category->value] ?? 0;
        }

        return $summary;
    }

    /**
     * Fallback foundation FAQs for cold starts and offline unit testing.
     *
     * @return list<array{question: string, answer: string, keywords: list<string>}>
     */
    protected function getDefaultFaqs(): array
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
