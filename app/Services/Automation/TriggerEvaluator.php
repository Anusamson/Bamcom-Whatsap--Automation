<?php

namespace App\Services\Automation;

use App\Enums\AutomationConditionOperator;
use App\Enums\AutomationTriggerType;
use App\Models\AutomationCondition;
use App\Models\AutomationTrigger;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Evaluator for automation triggers, filter criteria, and workflow condition trees.
 */
class TriggerEvaluator
{
    /**
     * Determine if an incoming event matches an automation trigger.
     *
     * @param  array<string, mixed>  $context
     */
    public function matchesTrigger(AutomationTrigger $trigger, string $eventType, array $context = []): bool
    {
        if (! $trigger->is_active) {
            return false;
        }

        $triggerTypeValue = $trigger->trigger_type instanceof AutomationTriggerType
            ? $trigger->trigger_type->value
            : (string) $trigger->trigger_type;

        if (strcasecmp($triggerTypeValue, $eventType) !== 0) {
            return false;
        }

        // If filter criteria are specified, ensure all match against the event context
        if (! empty($trigger->filter_criteria)) {
            foreach ($trigger->filter_criteria as $key => $expected) {
                if ($expected === null || $expected === '') {
                    continue;
                }

                $actual = data_get($context, $key);
                if (is_numeric($actual) && is_numeric($expected)) {
                    if ((float) $actual != (float) $expected) {
                        return false;
                    }
                } elseif (is_bool($expected)) {
                    if (filter_var($actual, FILTER_VALIDATE_BOOLEAN) !== $expected) {
                        return false;
                    }
                } elseif (is_array($expected)) {
                    if (! in_array($actual, $expected, false)) {
                        return false;
                    }
                } elseif (strcasecmp((string) $actual, (string) $expected) !== 0) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Evaluate a sequence of conditions against a subject model and event context.
     *
     * @param  Collection<int, AutomationCondition>|array<int, AutomationCondition>  $conditions
     * @param  array<string, mixed>  $context
     */
    public function evaluateConditions(Collection|array $conditions, mixed $subject, array $context = []): bool
    {
        $conditionList = $conditions instanceof Collection ? $conditions : collect($conditions);

        if ($conditionList->isEmpty()) {
            return true;
        }

        $result = true;
        $isFirst = true;

        foreach ($conditionList as $condition) {
            $actual = $this->extractFieldValue($condition->field, $subject, $context);
            $operator = $condition->operator instanceof AutomationConditionOperator
                ? $condition->operator
                : AutomationConditionOperator::tryFrom((string) $condition->operator) ?? AutomationConditionOperator::Equals;

            $matches = $operator->evaluate($actual, $condition->value);

            if ($isFirst) {
                $result = $matches;
                $isFirst = false;
            } else {
                $gate = strtoupper((string) ($condition->logical_operator ?: 'AND'));
                if ($gate === 'OR') {
                    $result = $result || $matches;
                } else {
                    $result = $result && $matches;
                }
            }
        }

        return $result;
    }

    /**
     * Extract a field value from subject model or context with dot notation support.
     *
     * @param  array<string, mixed>  $context
     */
    public function extractFieldValue(string $field, mixed $subject, array $context = []): mixed
    {
        $normalizedField = trim($field);

        // 1. Direct match on context
        if (str_starts_with($normalizedField, 'context.') || str_starts_with($normalizedField, 'event.')) {
            $path = preg_replace('/^(context|event)\./', '', $normalizedField);

            return data_get($context, $path);
        }

        // 2. Explicit model prefixes: lead., contact., deal.
        if ($subject instanceof Model) {
            if (str_starts_with($normalizedField, 'lead.')) {
                $lead = $subject instanceof Lead ? $subject : ($subject instanceof Contact ? $subject->leads()->latest()->first() : null);
                $key = substr($normalizedField, 5);

                return $lead ? $this->getModelValue($lead, $key) : null;
            }

            if (str_starts_with($normalizedField, 'contact.')) {
                $contact = $subject instanceof Contact ? $subject : ($subject instanceof Lead ? $subject->contact : null);
                $key = substr($normalizedField, 8);

                return $contact ? $this->getModelValue($contact, $key) : null;
            }

            if (str_starts_with($normalizedField, 'deal.')) {
                $deal = $subject instanceof Deal ? $subject : null;
                $key = substr($normalizedField, 5);

                return $deal ? $this->getModelValue($deal, $key) : null;
            }

            // 3. Fallback direct property on subject
            $val = $this->getModelValue($subject, $normalizedField);
            if ($val !== null) {
                return $val;
            }

            // If subject is Lead and property is on contact
            if ($subject instanceof Lead && $subject->contact && $subject->contact->getAttribute($normalizedField) !== null) {
                return $this->getModelValue($subject->contact, $normalizedField);
            }

            // If subject is Contact and property is on latest lead
            if ($subject instanceof Contact) {
                $latestLead = $subject->leads()->latest()->first();
                if ($latestLead && $latestLead->getAttribute($normalizedField) !== null) {
                    return $this->getModelValue($latestLead, $normalizedField);
                }
            }
        }

        // 4. Try context data as fallback
        return data_get($context, $normalizedField);
    }

    /**
     * Read a model attribute with enum resolution and accessor support.
     */
    protected function getModelValue(Model $model, string $key): mixed
    {
        $value = data_get($model, $key);

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return $value;
    }
}
