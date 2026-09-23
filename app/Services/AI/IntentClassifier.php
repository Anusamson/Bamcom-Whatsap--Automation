<?php

namespace App\Services\AI;

use App\Enums\AIIntent;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\IntentResult;

/**
 * Intelligent Real Estate Intent Classifier.
 *
 * Fast two-tier classification combining heuristic regex pattern matching
 * with AI LLM model classification for ambiguous inquiries.
 */
class IntentClassifier
{
    public function __construct(
        protected ?AIProviderInterface $provider = null
    ) {}

    /**
     * Classify an incoming customer message.
     */
    public function classify(string $message): IntentResult
    {
        $trimmed = trim($message);
        if (empty($trimmed)) {
            return new IntentResult(AIIntent::Unknown, 0.0, method: 'empty');
        }

        // Tier 1: Fast heuristic pattern matcher
        $heuristic = $this->matchHeuristic($trimmed);
        if ($heuristic !== null && $heuristic->confidence >= 0.85) {
            return $heuristic;
        }

        // Tier 2: Model-based fallback if provider is available
        if ($this->provider && $this->provider->isAvailable()) {
            try {
                return $this->provider->classifyIntent($trimmed);
            } catch (\Throwable) {
                // If model fails, fallback to heuristic or unknown
            }
        }

        return $heuristic ?? new IntentResult(AIIntent::Unknown, 0.5, method: 'heuristic_fallback');
    }

    /**
     * Heuristic pattern matcher for high-confidence common customer intents.
     */
    protected function matchHeuristic(string $message): ?IntentResult
    {
        $lower = strtolower($message);
        $entities = $this->extractEntities($message);

        // 1. Human Handover Request
        if (preg_match('/\b(human|agent|person|representative|manager|call me|speak to someone|customer care|receptionist|staff)\b/i', $lower)) {
            return new IntentResult(AIIntent::HumanHandover, 0.98, $entities, requiresHumanTakeover: true, method: 'heuristic');
        }

        // 2. Site Inspection Booking
        if (preg_match('/\b(inspection|site visit|visit the site|tour|come and see|view the land|book a visit|go to epe|see the property)\b/i', $lower)) {
            return new IntentResult(AIIntent::InspectionBooking, 0.95, $entities, method: 'heuristic');
        }

        // 3. Pricing, Promos & Payment Plans
        if (preg_match('/\b(price|cost|how much|rate|promo|discount|installment|spread|deposit|initial deposit|payment plan|naira|million|\bngn\b|\b\d+m\b)\b/i', $lower)) {
            return new IntentResult(AIIntent::PricingInquiry, 0.92, $entities, method: 'heuristic');
        }

        // 4. Legal Title & Documentation
        if (preg_match('/\b(title|c of o|certificate of occupancy|governor\'?s consent|gazette|deed|survey|registered survey|excision|freehold)\b/i', $lower)) {
            return new IntentResult(AIIntent::TitleVerification, 0.94, $entities, method: 'heuristic');
        }

        // 5. General FAQ & Legitimacy
        if (preg_match('/\b(legit|scam|office address|where are you located|cac|account number|bank details|who is bamcom)\b/i', $lower)) {
            return new IntentResult(AIIntent::GeneralFaq, 0.90, $entities, method: 'heuristic');
        }

        // 6. Property & Inventory Inquiry
        if (preg_match('/\b(plot|plots|land|estate|houses|duplex|bungalow|terrace|sqm|square meters|available|units|epe|lekki|ibeju|ikoyi)\b/i', $lower)) {
            return new IntentResult(AIIntent::PropertyInquiry, 0.88, $entities, method: 'heuristic');
        }

        // 7. Greetings
        if (preg_match('/^(hello|hi|hey|good day|good morning|good afternoon|good evening|greetings)\b/i', $lower)) {
            return new IntentResult(AIIntent::Greeting, 0.95, $entities, method: 'heuristic');
        }

        return null;
    }

    /**
     * Extract real estate entities like budget, locations, plot size, dates.
     *
     * @return array<string, mixed>
     */
    public function extractEntities(string $message): array
    {
        $entities = [];
        $lower = strtolower($message);

        // Extract Location
        if (preg_match('/\b(epe|ibeju[-\s]?lekki|lekki|ikoyi|ajah|victoria island|vi|sangotedo|bogije)\b/i', $lower, $matches)) {
            $entities['location'] = ucwords(trim($matches[1]));
        }

        // Extract Plot Size (e.g. 300sqm, 500 sqm, 600 square meters, 1 acre)
        if (preg_match('/\b(\d+)\s*(sqm|square meters?|plots?|acres?)\b/i', $lower, $matches)) {
            $entities['plot_size'] = "{$matches[1]} ".(str_contains(strtolower($matches[2]), 'sq') ? 'SQM' : ucfirst($matches[2]));
        }

        // Extract Budget (e.g. 15m, 20 million, ₦30,000,000)
        if (preg_match('/\b(?:₦|ngn\s*)?(\d+(?:\.\d+)?)\s*(?:m|million|k)\b/i', $lower, $matches)) {
            $num = (float) $matches[1];
            $entities['budget_raw'] = $matches[0];
            $entities['budget_numeric'] = str_contains(strtolower($matches[0]), 'k') ? $num * 1000 : $num * 1000000;
        }

        // Extract Preferred Day for Inspection
        if (preg_match('/\b(monday|tuesday|wednesday|thursday|friday|saturday|weekend|tomorrow|today)\b/i', $lower, $matches)) {
            $entities['preferred_day'] = ucfirst($matches[1]);
        }

        return $entities;
    }
}
