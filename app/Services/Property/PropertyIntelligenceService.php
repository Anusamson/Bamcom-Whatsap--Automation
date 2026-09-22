<?php

namespace App\Services\Property;

use App\Models\Estate;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Authoritative Intelligence Service for AI Agents & WhatsApp Automation.
 *
 * Guarantees zero-hallucination real estate data by serving directly from the database
 * with live pricing, payment plans, legal title documents, and inventory availability.
 */
class PropertyIntelligenceService
{
    /**
     * Get authoritative real estate knowledge base array for AI ingestion.
     *
     * @return array<string, mixed>
     */
    public function getAuthoritativeKnowledgeBase(?string $estateSlug = null): array
    {
        $estatesQuery = Estate::query()->active()->withCount('properties');
        if (! empty($estateSlug)) {
            $estatesQuery->where('slug', $estateSlug);
        }
        $estates = $estatesQuery->get();

        $propertiesQuery = Property::query()
            ->published()
            ->with(['estate', 'activePrice', 'promotion', 'primaryMedia'])
            ->orderBy('is_featured', 'desc')
            ->latest('id');

        if (! empty($estateSlug)) {
            $propertiesQuery->whereHas('estate', fn (Builder $q) => $q->where('slug', $estateSlug));
        }

        $properties = $propertiesQuery->get();

        $propertyCatalog = $properties->map(function (Property $property): array {
            return $this->formatPropertyForAi($property);
        })->toArray();

        return [
            'authoritative_source' => 'Bamcom Real Estate Live Database',
            'generated_at' => now()->toIso8601String(),
            'company_name' => 'Bamcom Properties & Real Estate Ltd',
            'currency' => 'NGN (₦)',
            'ai_guardrails' => [
                'Do not quote prices different from the regular_price or promo_price listed here.',
                'Do not invent titles or legal documents; only state the verified title_document.',
                'If available_units is 0 or availability is sold_out, politely inform the client and suggest an alternative in the same estate or price range.',
                'Always mention the initial_deposit requirement and flexible payment plans when buyers inquire about affordability.',
                'Highlight promotional discounts and total savings when promo_price is active.',
            ],
            'estates' => $estates->map(function (Estate $estate): array {
                return [
                    'id' => $estate->id,
                    'name' => $estate->name,
                    'slug' => $estate->slug,
                    'location' => $estate->location,
                    'city' => $estate->city,
                    'state' => $estate->state,
                    'landmarks' => $estate->landmarks,
                    'title_document' => $estate->title_document,
                    'total_properties_count' => $estate->properties_count,
                    'description' => $estate->description,
                    'features' => $estate->features ?? [],
                ];
            })->toArray(),
            'inventory' => $propertyCatalog,
        ];
    }

    /**
     * Format a single property record into authoritative AI fact dictionary.
     *
     * @return array<string, mixed>
     */
    public function formatPropertyForAi(Property $property): array
    {
        $regular = $property->regular_price ?? 0.0;
        $promo = $property->promo_price;
        $effective = $property->effective_price ?? $regular;
        $deposit = $property->initial_deposit ?? ($effective * 0.20);
        $savings = ($regular > $effective) ? ($regular - $effective) : 0.0;

        $plans = $property->activePrice?->payment_plans ?? [];

        return [
            'id' => $property->id,
            'uuid' => $property->uuid,
            'slug' => $property->slug,
            'title' => $property->title,
            'estate_name' => $property->estate?->name ?? 'Independent Development',
            'estate_slug' => $property->estate?->slug,
            'property_type' => $property->property_type,
            'plot_size' => $property->plot_size,
            'plot_number' => $property->plot_number,
            'location' => $property->effective_location,
            'title_document' => $property->effective_title_document,
            'availability' => $property->availability,
            'available_units' => $property->available_units,
            'total_units' => $property->total_units,
            'is_in_stock' => $property->is_available,
            'pricing' => [
                'currency' => 'NGN',
                'regular_price' => $regular,
                'regular_price_formatted' => '₦'.number_format($regular, 2),
                'promo_price' => $promo,
                'promo_price_formatted' => $promo ? '₦'.number_format($promo, 2) : null,
                'effective_price' => $effective,
                'effective_price_formatted' => '₦'.number_format($effective, 2),
                'savings' => $savings,
                'savings_formatted' => $savings > 0 ? '₦'.number_format($savings, 2) : '₦0.00',
                'has_active_promo' => $savings > 0,
                'initial_deposit' => $deposit,
                'initial_deposit_formatted' => '₦'.number_format($deposit, 2),
            ],
            'payment_plan_summary' => $property->payment_plan_summary,
            'payment_plans' => $plans,
            'features' => $property->features ?? [],
            'description' => $property->description,
            'hero_image' => $property->primaryMedia?->file_url ?? $property->primaryMedia?->file_path,
        ];
    }

    /**
     * Build formatted Markdown string for injection into WhatsApp AI System Instructions.
     */
    public function formatAiSystemPromptContext(?string $estateSlug = null): string
    {
        $kb = $this->getAuthoritativeKnowledgeBase($estateSlug);

        $markdown = "### AUTHORITATIVE REAL ESTATE INVENTORY (BAMCOM PROPERTIES)\n";
        $markdown .= "The following information is retrieved directly from our live database. Treat it as absolute ground truth. Do not hallucinate prices or titles.\n\n";

        $markdown .= "#### ESTATES OVERVIEW:\n";
        foreach ($kb['estates'] as $estate) {
            $markdown .= "- **{$estate['name']}**: Located at {$estate['location']}, {$estate['city']}, {$estate['state']}. Title: {$estate['title_document']}. Landmarks: {$estate['landmarks']}.\n";
        }
        $markdown .= "\n";

        $markdown .= "#### LIVE PROPERTY CATALOG:\n";
        foreach ($kb['inventory'] as $item) {
            $statusStr = $item['is_in_stock'] ? "AVAILABLE ({$item['available_units']} units left)" : "STATUS: {$item['availability']}";
            $priceStr = $item['pricing']['has_active_promo']
                ? "Promo: {$item['pricing']['effective_price_formatted']} (Regular: {$item['pricing']['regular_price_formatted']}, Save: {$item['pricing']['savings_formatted']})"
                : "Price: {$item['pricing']['effective_price_formatted']}";

            $markdown .= "##### {$item['title']} [{$item['estate_name']}]\n";
            $markdown .= "- **Type & Size**: {$item['property_type']} | Plot Size: {$item['plot_size']}\n";
            $markdown .= "- **Location**: {$item['location']}\n";
            $markdown .= "- **Title**: {$item['title_document']}\n";
            $markdown .= "- **{$priceStr}**\n";
            $markdown .= "- **Initial Deposit**: {$item['pricing']['initial_deposit_formatted']}\n";
            $markdown .= "- **Payment Terms**: {$item['payment_plan_summary']}\n";
            $markdown .= "- **Availability**: {$statusStr}\n";

            if (! empty($item['features'])) {
                $markdown .= '- **Features**: '.implode(', ', $item['features'])."\n";
            }
            if (! empty($item['description'])) {
                $markdown .= "- **Description**: {$item['description']}\n";
            }
            $markdown .= "\n";
        }

        return $markdown;
    }

    /**
     * Query live properties tailored for AI function calls (e.g. from WhatsApp bot).
     *
     * @param  array<string, mixed>  $criteria
     * @return Collection<int, array<string, mixed>>
     */
    public function queryPropertiesForAi(array $criteria): Collection
    {
        $query = Property::query()
            ->published()
            ->with(['estate', 'activePrice', 'promotion', 'primaryMedia']);

        if (! empty($criteria['in_stock_only'])) {
            $query->available();
        }

        if (! empty($criteria['property_type'])) {
            $query->where('property_type', $criteria['property_type']);
        }

        if (! empty($criteria['estate_slug'])) {
            $query->whereHas('estate', fn (Builder $q) => $q->where('slug', $criteria['estate_slug']));
        }

        if (! empty($criteria['location'])) {
            $loc = $criteria['location'];
            $query->where(function (Builder $q) use ($loc): void {
                $q->where('location', 'like', "%{$loc}%")
                    ->orWhereHas('estate', fn (Builder $eq) => $eq->where('location', 'like', "%{$loc}%")->orWhere('city', 'like', "%{$loc}%")->orWhere('state', 'like', "%{$loc}%"));
            });
        }

        if (! empty($criteria['title_document'])) {
            $title = $criteria['title_document'];
            $query->where(function (Builder $q) use ($title): void {
                $q->where('title_document', 'like', "%{$title}%")
                    ->orWhereHas('estate', fn (Builder $eq) => $eq->where('title_document', 'like', "%{$title}%"));
            });
        }

        if (! empty($criteria['max_price'])) {
            $max = (float) $criteria['max_price'];
            $query->whereHas('activePrice', function (Builder $pq) use ($max): void {
                $pq->where(function (Builder $sub) use ($max): void {
                    $sub->whereNotNull('promo_price')->where('promo_price', '<=', $max)
                        ->orWhere(function (Builder $sub2) use ($max): void {
                            $sub2->whereNull('promo_price')->where('regular_price', '<=', $max);
                        });
                });
            });
        }

        if (! empty($criteria['min_price'])) {
            $min = (float) $criteria['min_price'];
            $query->whereHas('activePrice', function (Builder $pq) use ($min): void {
                $pq->where('regular_price', '>=', $min);
            });
        }

        return $query->take($criteria['limit'] ?? 10)
            ->get()
            ->map(fn (Property $p): array => $this->formatPropertyForAi($p));
    }

    /**
     * Generate ready-to-send WhatsApp pitch text for a property.
     */
    public function getWhatsappPitch(Property $property): string
    {
        $info = $this->formatPropertyForAi($property);

        $out = "🏡 *{$info['title']}*\n";
        $out .= "📍 Location: {$info['location']}\n";
        $out .= "📐 Plot Size: {$info['plot_size']}\n";
        $out .= "📜 Title: {$info['title_document']}\n\n";

        if ($info['pricing']['has_active_promo']) {
            $out .= "🔥 *Special Promo Price: {$info['pricing']['effective_price_formatted']}*\n";
            $out .= "❌ Original Price: ~{$info['pricing']['regular_price_formatted']}~\n";
            $out .= "💰 You Save: *{$info['pricing']['savings_formatted']}*\n";
        } else {
            $out .= "💰 Price: *{$info['pricing']['effective_price_formatted']}*\n";
        }

        $out .= "💳 Initial Deposit: *{$info['pricing']['initial_deposit_formatted']}*\n";
        $out .= "⏱ Payment Spread: {$info['payment_plan_summary']}\n";
        $out .= "🔑 Units Left: *{$info['available_units']} Units*\n\n";

        if (! empty($info['features'])) {
            $out .= '✨ Highlights: '.implode(' • ', array_slice($info['features'], 0, 4))."\n\n";
        }

        $out .= 'Interested in scheduling an inspection or reserving a unit? Reply to this message!';

        return $out;
    }
}
