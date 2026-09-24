<?php

namespace App\Services\SmartList;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Advanced recursive query evaluator for dynamic CRM Smart Lists.
 * Evaluates rule groups with arbitrary AND/OR logical operators and nested groups
 * without duplicating contact records.
 */
class SmartListQueryBuilder
{
    /**
     * Build an Eloquent builder for Contact based on dynamic rule groups.
     *
     * @param  array<string, mixed>  $ruleGroups
     * @return Builder<Contact>
     */
    public function buildQuery(array $ruleGroups = []): Builder
    {
        $query = Contact::query()
            ->select('contacts.*')
            ->distinct();

        if (empty($ruleGroups) || empty($ruleGroups['rules'])) {
            return $query;
        }

        $this->applyRuleGroup($query, $ruleGroups, 'and');

        return $query;
    }

    /**
     * Recursively apply a rule group to the Eloquent builder.
     *
     * @param  Builder<Contact>  $query
     * @param  array<string, mixed>  $group
     */
    public function applyRuleGroup(Builder $query, array $group, string $boolean = 'and'): void
    {
        $logicalOperator = strtoupper((string) ($group['logical_operator'] ?? 'AND'));
        $rules = (array) ($group['rules'] ?? []);

        if (empty($rules)) {
            return;
        }

        $method = ($boolean === 'or') ? 'orWhere' : 'where';

        $query->{$method}(function (Builder $nestedQuery) use ($rules, $logicalOperator): void {
            $childBoolean = ($logicalOperator === 'OR') ? 'or' : 'and';

            foreach ($rules as $rule) {
                // If it's a nested rule group
                if (isset($rule['rules']) && is_array($rule['rules'])) {
                    $this->applyRuleGroup($nestedQuery, $rule, $childBoolean);
                } elseif (isset($rule['field']) && isset($rule['operator'])) {
                    $this->applySingleRule($nestedQuery, $rule, $childBoolean);
                }
            }
        });
    }

    /**
     * Apply an individual rule condition to the query.
     *
     * @param  Builder<Contact>  $query
     * @param  array<string, mixed>  $rule
     */
    public function applySingleRule(Builder $query, array $rule, string $boolean = 'and'): void
    {
        $field = strtolower(trim((string) $rule['field']));
        $operator = strtolower(trim((string) $rule['operator']));
        $value = $rule['value'] ?? null;

        // Normalise field aliases
        $field = $this->normaliseField($field);

        switch ($field) {
            // --- Contact Native Fields ---
            case 'contact.location':
                $this->applyStringFilter($query, 'contacts.location', $operator, $value, $boolean);
                break;

            case 'contact.lead_source':
                $this->applyBasicFilter($query, 'contacts.lead_source', $operator, $value, $boolean);
                break;

            case 'contact.status':
                $this->applyBasicFilter($query, 'contacts.status', $operator, $value, $boolean);
                break;

            case 'contact.assigned_user_id':
                $this->applyBasicFilter($query, 'contacts.assigned_user_id', $operator, $value, $boolean);
                break;

            case 'contact.has_opted_out':
                $this->applyBooleanFilter($query, 'contacts.has_opted_out', $operator, $value, $boolean);
                break;

            case 'contact.last_contact_days':
                $this->applyLastContactDaysFilter($query, $operator, (int) $value, $boolean);
                break;

            case 'contact.last_contact_at':
                $this->applyDateFilter($query, 'contacts.last_contact_at', $operator, $value, $boolean);
                break;

            case 'contact.tags':
            case 'tags':
                $this->applyTagsFilter($query, $operator, $value, $boolean);
                break;

                // --- Lead Fields ---
            case 'lead.temperature':
                $this->applyRelationFilter($query, 'leads', function (Builder $lq) use ($operator, $value): void {
                    $this->applyBasicFilter($lq, 'temperature', $operator, $value, 'and');
                }, $boolean);
                break;

            case 'lead.score':
                $this->applyRelationFilter($query, 'leads', function (Builder $lq) use ($operator, $value): void {
                    $this->applyNumericFilter($lq, 'score', $operator, $value, 'and');
                }, $boolean);
                break;

            case 'lead.stage_name':
            case 'lead.stage':
                $this->applyRelationFilter($query, 'leads.stage', function (Builder $sq) use ($operator, $value): void {
                    $this->applyStringFilter($sq, 'name', $operator, $value, 'and');
                }, $boolean);
                break;

            case 'lead.pipeline_stage_id':
                $this->applyRelationFilter($query, 'leads', function (Builder $lq) use ($operator, $value): void {
                    $this->applyBasicFilter($lq, 'pipeline_stage_id', $operator, $value, 'and');
                }, $boolean);
                break;

            case 'lead.status':
                $this->applyRelationFilter($query, 'leads', function (Builder $lq) use ($operator, $value): void {
                    $this->applyBasicFilter($lq, 'status', $operator, $value, 'and');
                }, $boolean);
                break;

                // --- Deal / Payment Fields ---
            case 'deal.stage_name':
            case 'deal.stage':
            case 'deal_stage':
                $this->applyRelationFilter($query, 'deals.stage', function (Builder $dsq) use ($operator, $value): void {
                    $this->applyStringFilter($dsq, 'name', $operator, $value, 'and');
                }, $boolean);
                break;

            case 'deal.pipeline_stage_id':
                $this->applyRelationFilter($query, 'deals', function (Builder $dq) use ($operator, $value): void {
                    $this->applyBasicFilter($dq, 'pipeline_stage_id', $operator, $value, 'and');
                }, $boolean);
                break;

            case 'deal.deal_value':
            case 'deal.budget':
                $this->applyRelationFilter($query, 'deals', function (Builder $dq) use ($operator, $value): void {
                    $this->applyNumericFilter($dq, 'deal_value', $operator, $value, 'and');
                }, $boolean);
                break;

            case 'deal.status':
                $this->applyRelationFilter($query, 'deals', function (Builder $dq) use ($operator, $value): void {
                    $this->applyBasicFilter($dq, 'status', $operator, $value, 'and');
                }, $boolean);
                break;

                // --- Inspection Fields ---
            case 'inspection.status':
            case 'inspection_status':
                $this->applyRelationFilter($query, 'inspections', function (Builder $iq) use ($operator, $value): void {
                    $this->applyBasicFilter($iq, 'status', $operator, $value, 'and');
                }, $boolean);
                break;

            case 'inspection.estate_name':
                $this->applyRelationFilter($query, 'inspections', function (Builder $iq) use ($operator, $value): void {
                    $this->applyStringFilter($iq, 'estate_name', $operator, $value, 'and');
                }, $boolean);
                break;

                // --- Property / Estate Fields ---
            case 'property.title':
            case 'property_name':
            case 'estate.name':
                $this->applyPropertyOrEstateFilter($query, $operator, $value, $boolean);
                break;

            default:
                // Fallback: try direct column if it exists on contacts table
                $this->applyBasicFilter($query, "contacts.{$field}", $operator, $value, $boolean);
                break;
        }
    }

    /**
     * Normalise field names from UI aliases.
     */
    protected function normaliseField(string $field): string
    {
        $map = [
            'location' => 'contact.location',
            'lead_source' => 'contact.lead_source',
            'source' => 'contact.lead_source',
            'contact_status' => 'contact.status',
            'assigned_user_id' => 'contact.assigned_user_id',
            'assigned_agent' => 'contact.assigned_user_id',
            'has_opted_out' => 'contact.has_opted_out',
            'opted_out' => 'contact.has_opted_out',
            'last_contact_days' => 'contact.last_contact_days',
            'last_contact_at' => 'contact.last_contact_at',
            'temperature' => 'lead.temperature',
            'lead_temperature' => 'lead.temperature',
            'score' => 'lead.score',
            'lead_score' => 'lead.score',
            'stage' => 'lead.stage_name',
            'lead_stage' => 'lead.stage_name',
            'payment_pending' => 'deal.stage_name',
            'deal_stage' => 'deal.stage_name',
            'inspection_status' => 'inspection.status',
            'estate' => 'estate.name',
            'property' => 'property.title',
        ];

        return $map[$field] ?? $field;
    }

    /**
     * Apply string operators (contains, equals, starts_with, etc.).
     *
     * @param  Builder<Contact>  $query
     */
    protected function applyStringFilter(Builder $query, string $column, string $operator, mixed $value, string $boolean): void
    {
        $method = ($boolean === 'or') ? 'orWhere' : 'where';

        switch ($operator) {
            case 'contains':
                $query->{$method}($column, 'like', '%'.trim((string) $value).'%');
                break;
            case 'not_contains':
                $query->{$method}($column, 'not like', '%'.trim((string) $value).'%');
                break;
            case 'starts_with':
                $query->{$method}($column, 'like', trim((string) $value).'%');
                break;
            case 'ends_with':
                $query->{$method}($column, 'like', '%'.trim((string) $value));
                break;
            case 'equals':
            case '=':
                $query->{$method}($column, '=', $value);
                break;
            case 'not_equals':
            case '!=':
                $query->{$method}($column, '!=', $value);
                break;
            case 'is_empty':
                $query->{$method}(function (Builder $q) use ($column): void {
                    $q->whereNull($column)->orWhere($column, '=', '');
                });
                break;
            case 'is_not_empty':
                $query->{$method}(function (Builder $q) use ($column): void {
                    $q->whereNotNull($column)->where($column, '!=', '');
                });
                break;
            case 'in':
                $list = is_array($value) ? $value : array_map('trim', explode(',', (string) $value));
                $inMethod = ($boolean === 'or') ? 'orWhereIn' : 'whereIn';
                $query->{$inMethod}($column, $list);
                break;
            default:
                $query->{$method}($column, '=', $value);
                break;
        }
    }

    /**
     * Apply basic comparison filters (=, !=, in, not in, empty).
     *
     * @param  Builder<Contact>  $query
     */
    protected function applyBasicFilter(Builder $query, string $column, string $operator, mixed $value, string $boolean): void
    {
        $method = ($boolean === 'or') ? 'orWhere' : 'where';

        switch ($operator) {
            case 'equals':
            case '=':
                $query->{$method}($column, '=', $value);
                break;
            case 'not_equals':
            case '!=':
                $query->{$method}($column, '!=', $value);
                break;
            case 'in':
                $list = is_array($value) ? $value : array_map('trim', explode(',', (string) $value));
                $inMethod = ($boolean === 'or') ? 'orWhereIn' : 'whereIn';
                $query->{$inMethod}($column, $list);
                break;
            case 'not_in':
                $list = is_array($value) ? $value : array_map('trim', explode(',', (string) $value));
                $notInMethod = ($boolean === 'or') ? 'orWhereNotIn' : 'whereNotIn';
                $query->{$notInMethod}($column, $list);
                break;
            case 'is_empty':
                $query->{$method}(function (Builder $q) use ($column): void {
                    $q->whereNull($column)->orWhere($column, '=', '');
                });
                break;
            case 'is_not_empty':
                $query->{$method}(function (Builder $q) use ($column): void {
                    $q->whereNotNull($column)->where($column, '!=', '');
                });
                break;
            default:
                $query->{$method}($column, '=', $value);
                break;
        }
    }

    /**
     * Apply numeric filter.
     *
     * @param  Builder<Contact>  $query
     */
    protected function applyNumericFilter(Builder $query, string $column, string $operator, mixed $value, string $boolean): void
    {
        $method = ($boolean === 'or') ? 'orWhere' : 'where';
        $num = (float) $value;

        switch ($operator) {
            case 'greater_than':
            case '>':
                $query->{$method}($column, '>', $num);
                break;
            case 'greater_than_or_equal':
            case '>=':
                $query->{$method}($column, '>=', $num);
                break;
            case 'less_than':
            case '<':
                $query->{$method}($column, '<', $num);
                break;
            case 'less_than_or_equal':
            case '<=':
                $query->{$method}($column, '<=', $num);
                break;
            case 'equals':
            case '=':
                $query->{$method}($column, '=', $num);
                break;
            default:
                $query->{$method}($column, '=', $num);
                break;
        }
    }

    /**
     * Apply boolean filter.
     *
     * @param  Builder<Contact>  $query
     */
    protected function applyBooleanFilter(Builder $query, string $column, string $operator, mixed $value, string $boolean): void
    {
        $method = ($boolean === 'or') ? 'orWhere' : 'where';
        $boolVal = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if ($operator === 'not_equals' || $operator === '!=') {
            $boolVal = ! $boolVal;
        }

        $query->{$method}($column, '=', $boolVal);
    }

    /**
     * Apply date filter.
     *
     * @param  Builder<Contact>  $query
     */
    protected function applyDateFilter(Builder $query, string $column, string $operator, mixed $value, string $boolean): void
    {
        $method = ($boolean === 'or') ? 'orWhere' : 'where';

        if ($operator === 'is_empty') {
            $query->{$method.'Null'}($column);

            return;
        }

        if ($operator === 'is_not_empty') {
            $query->{$method.'NotNull'}($column);

            return;
        }

        $dt = Carbon::parse($value);

        switch ($operator) {
            case 'greater_than':
            case '>':
                $query->{$method}($column, '>', $dt);
                break;
            case 'less_than':
            case '<':
                $query->{$method}($column, '<', $dt);
                break;
            default:
                $query->{$method}($column, '=', $dt);
                break;
        }
    }

    /**
     * Filter by last contact days (e.g. dormant prospects >= 30 days).
     *
     * @param  Builder<Contact>  $query
     */
    protected function applyLastContactDaysFilter(Builder $query, string $operator, int $days, string $boolean): void
    {
        $method = ($boolean === 'or') ? 'orWhere' : 'where';
        $cutoff = now()->subDays($days);

        switch ($operator) {
            case 'greater_than':
            case 'greater_than_or_equal':
            case '>':
            case '>=':
                // Contact touchpoint is older than X days or was never contacted
                $query->{$method}(function (Builder $q) use ($cutoff): void {
                    $q->whereNull('contacts.last_contact_at')
                        ->orWhere('contacts.last_contact_at', '<=', $cutoff);
                });
                break;

            case 'less_than':
            case 'less_than_or_equal':
            case '<':
            case '<=':
                // Contacted recently within the last X days
                $query->{$method}(function (Builder $q) use ($cutoff): void {
                    $q->whereNotNull('contacts.last_contact_at')
                        ->where('contacts.last_contact_at', '>=', $cutoff);
                });
                break;

            default:
                $query->{$method}('contacts.last_contact_at', '<=', $cutoff);
                break;
        }
    }

    /**
     * Filter by tags (MorphToMany).
     *
     * @param  Builder<Contact>  $query
     */
    protected function applyTagsFilter(Builder $query, string $operator, mixed $value, string $boolean): void
    {
        $tags = is_array($value) ? $value : array_map('trim', explode(',', (string) $value));

        if ($operator === 'not_in') {
            $method = ($boolean === 'or') ? 'orWhereDoesntHave' : 'whereDoesntHave';
            $query->{$method}('tags', function (Builder $tq) use ($tags): void {
                $tq->whereIn('name', $tags)->orWhereIn('slug', $tags);
            });
        } else {
            $method = ($boolean === 'or') ? 'orWhereHas' : 'whereHas';
            $query->{$method}('tags', function (Builder $tq) use ($tags): void {
                $tq->whereIn('name', $tags)->orWhereIn('slug', $tags);
            });
        }
    }

    /**
     * Filter by property title or estate name across Leads, Deals, and Inspections.
     *
     * @param  Builder<Contact>  $query
     */
    protected function applyPropertyOrEstateFilter(Builder $query, string $operator, mixed $value, string $boolean): void
    {
        $val = trim((string) $value);
        $method = ($boolean === 'or') ? 'orWhere' : 'where';

        $query->{$method}(function (Builder $q) use ($val): void {
            // 1. Leads interested in property matching name
            $q->whereHas('leads.property', function (Builder $pq) use ($val): void {
                $pq->where('title', 'like', "%{$val}%");
            })
            // 2. Or Deals associated with property matching name
                ->orWhereHas('deals.property', function (Builder $dpq) use ($val): void {
                    $dpq->where('title', 'like', "%{$val}%");
                })
            // 3. Or Inspections with estate_name matching
                ->orWhereHas('inspections', function (Builder $iq) use ($val): void {
                    $iq->where('estate_name', 'like', "%{$val}%");
                })
            // 4. Or Inspections associated with property matching title
                ->orWhereHas('inspections.property', function (Builder $ipq) use ($val): void {
                    $ipq->where('title', 'like', "%{$val}%");
                });
        });
    }

    /**
     * Helper to wrap relation query in whereHas or orWhereHas.
     *
     * @param  Builder<Contact>  $query
     */
    protected function applyRelationFilter(Builder $query, string $relation, \Closure $callback, string $boolean): void
    {
        $method = ($boolean === 'or') ? 'orWhereHas' : 'whereHas';
        $query->{$method}($relation, $callback);
    }
}
