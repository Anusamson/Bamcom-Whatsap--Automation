<?php

namespace App\Services\AI\DTOs;

use App\Enums\AIIntent;

/**
 * Standardized result from intent classification.
 */
class IntentResult
{
    /**
     * @param  AIIntent  $intent  The classified intent
     * @param  float  $confidence  Confidence score (0.0 to 1.0)
     * @param  array<string, mixed>  $entities  Extracted real estate entities (location, budget, estate, plot size)
     * @param  bool  $requiresHumanTakeover  Whether this intent mandates human agent takeover
     * @param  string  $method  Classification method ('heuristic', 'model', 'fallback')
     */
    public function __construct(
        public AIIntent $intent,
        public float $confidence = 1.0,
        public array $entities = [],
        public bool $requiresHumanTakeover = false,
        public string $method = 'heuristic'
    ) {
        if ($this->intent->requiresHumanEscalation()) {
            $this->requiresHumanTakeover = true;
        }
    }

    /**
     * Serialize to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent->value,
            'intent_label' => $this->intent->label(),
            'confidence' => $this->confidence,
            'entities' => $this->entities,
            'requires_human_takeover' => $this->requiresHumanTakeover,
            'method' => $this->method,
        ];
    }
}
