<?php

namespace App\Services\SmartList;

use App\Models\Contact;
use App\Models\SmartList;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SmartListService
{
    public function __construct(
        protected SmartListQueryBuilder $queryBuilder
    ) {}

    /**
     * Create a new smart list with calculated initial count.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSmartList(array $data, ?User $creator = null): SmartList
    {
        return DB::transaction(function () use ($data, $creator): SmartList {
            $ruleGroups = (array) ($data['rule_groups'] ?? ['logical_operator' => 'AND', 'rules' => []]);
            $count = $this->queryBuilder->buildQuery($ruleGroups)->count();

            return SmartList::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'rule_groups' => $ruleGroups,
                'icon' => $data['icon'] ?? 'Filter',
                'color' => $data['color'] ?? 'indigo',
                'is_preset' => (bool) ($data['is_preset'] ?? false),
                'is_favorite' => (bool) ($data['is_favorite'] ?? false),
                'cached_count' => $count,
                'created_by_user_id' => $creator?->id,
            ]);
        });
    }

    /**
     * Update an existing smart list.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSmartList(SmartList $smartList, array $data): SmartList
    {
        return DB::transaction(function () use ($smartList, $data): SmartList {
            $ruleGroups = isset($data['rule_groups']) ? (array) $data['rule_groups'] : $smartList->rule_groups;
            $count = $this->queryBuilder->buildQuery($ruleGroups)->count();

            $smartList->update([
                'name' => $data['name'] ?? $smartList->name,
                'description' => $data['description'] ?? $smartList->description,
                'rule_groups' => $ruleGroups,
                'icon' => $data['icon'] ?? $smartList->icon,
                'color' => $data['color'] ?? $smartList->color,
                'is_favorite' => isset($data['is_favorite']) ? (bool) $data['is_favorite'] : $smartList->is_favorite,
                'cached_count' => $count,
            ]);

            return $smartList;
        });
    }

    /**
     * Delete a smart list.
     */
    public function deleteSmartList(SmartList $smartList): bool
    {
        return (bool) $smartList->delete();
    }

    /**
     * Get paginated contacts dynamically matching this smart list without duplicating contacts.
     */
    public function getContactsForList(
        SmartList $smartList,
        int $perPage = 20,
        ?string $search = null
    ): LengthAwarePaginator {
        $query = $smartList->contactsQuery()
            ->with([
                'assignedUser:id,name',
                'tags:id,name,color',
                'leads' => fn ($q) => $q->with('pipelineStage:id,name,color')->latest(),
                'deals' => fn ($q) => $q->with('pipelineStage:id,name')->latest(),
                'inspections' => fn ($q) => $q->latest('inspection_date'),
            ])
            ->latest('contacts.updated_at');

        if ($search) {
            $term = trim($search);
            $query->where(function (Builder $q) use ($term): void {
                $q->where('contacts.first_name', 'like', "%{$term}%")
                    ->orWhere('contacts.last_name', 'like', "%{$term}%")
                    ->orWhere('contacts.phone', 'like', "%{$term}%")
                    ->orWhere('contacts.email', 'like', "%{$term}%")
                    ->orWhere('contacts.location', 'like', "%{$term}%");
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Preview count and sample contacts for dynamic rule groups.
     *
     * @param  array<string, mixed>  $ruleGroups
     * @return array{
     *     count: int,
     *     total_count: int,
     *     sample: array<int, array<string, mixed>>
     * }
     */
    public function previewCount(array $ruleGroups): array
    {
        $query = $this->queryBuilder->buildQuery($ruleGroups);
        $count = $query->count();

        $samples = (clone $query)
            ->with(['leads:id,contact_id,temperature,score', 'assignedUser:id,name'])
            ->limit(5)
            ->get();

        $sampleData = $samples->map(fn (Contact $c): array => [
            'id' => $c->id,
            'name' => $c->full_name,
            'phone' => $c->phone,
            'location' => $c->location,
            'status' => $c->status instanceof \BackedEnum ? $c->status->value : $c->status,
            'temperature' => $c->leads->first()?->temperature instanceof \BackedEnum ? $c->leads->first()->temperature->value : ($c->leads->first()?->temperature ?? 'warm'),
            'score' => $c->leads->first()?->score ?? 50,
            'last_contact_at' => $c->last_contact_at?->diffForHumans() ?? 'Never',
        ])->all();

        return [
            'count' => $count,
            'total_count' => $count,
            'sample' => $sampleData,
        ];
    }

    /**
     * Seed or update out-of-the-box smart list presets requested in CRM requirements.
     */
    public function seedPresets(?User $creator = null): void
    {
        $presets = [
            [
                'name' => 'Hot Abuja Prospects',
                'slug' => 'hot-abuja-prospects',
                'description' => 'High-intent hot leads looking for prime property acquisitions across Abuja',
                'icon' => 'Flame',
                'color' => 'rose',
                'rule_groups' => [
                    'logical_operator' => 'AND',
                    'rules' => [
                        ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Abuja'],
                        ['field' => 'lead.temperature', 'operator' => 'equals', 'value' => 'hot'],
                    ],
                ],
            ],
            [
                'name' => 'Dormant Prospects',
                'slug' => 'dormant-prospects',
                'description' => 'Leads and clients with no recorded contact touchpoint in the last 30 days',
                'icon' => 'Clock',
                'color' => 'amber',
                'rule_groups' => [
                    'logical_operator' => 'AND',
                    'rules' => [
                        ['field' => 'contact.last_contact_days', 'operator' => 'greater_than_or_equal', 'value' => 30],
                        ['field' => 'contact.status', 'operator' => 'not_equals', 'value' => 'lost'],
                    ],
                ],
            ],
            [
                'name' => 'Inspection Pending',
                'slug' => 'inspection-pending',
                'description' => 'Clients who have requested or are currently scheduled for physical site inspections',
                'icon' => 'CalendarCheck',
                'color' => 'teal',
                'rule_groups' => [
                    'logical_operator' => 'AND',
                    'rules' => [
                        ['field' => 'inspection.status', 'operator' => 'in', 'value' => ['requested', 'scheduled']],
                    ],
                ],
            ],
            [
                'name' => 'Payment Pending',
                'slug' => 'payment-pending',
                'description' => 'Active deals awaiting deposit confirmation or final settlement milestone',
                'icon' => 'DollarSign',
                'color' => 'emerald',
                'rule_groups' => [
                    'logical_operator' => 'AND',
                    'rules' => [
                        ['field' => 'deal.stage_name', 'operator' => 'contains', 'value' => 'Payment Pending'],
                    ],
                ],
            ],
            [
                'name' => 'Peace Court Prospects',
                'slug' => 'peace-court-prospects',
                'description' => 'Prospects who expressed interest or requested site tours for Peace Court development',
                'icon' => 'Home',
                'color' => 'indigo',
                'rule_groups' => [
                    'logical_operator' => 'OR',
                    'rules' => [
                        ['field' => 'property.title', 'operator' => 'contains', 'value' => 'Peace Court'],
                        ['field' => 'inspection.estate_name', 'operator' => 'contains', 'value' => 'Peace Court'],
                    ],
                ],
            ],
        ];

        foreach ($presets as $preset) {
            $count = $this->queryBuilder->buildQuery($preset['rule_groups'])->count();

            SmartList::updateOrCreate(
                ['slug' => $preset['slug']],
                [
                    'name' => $preset['name'],
                    'description' => $preset['description'],
                    'rule_groups' => $preset['rule_groups'],
                    'icon' => $preset['icon'],
                    'color' => $preset['color'],
                    'is_preset' => true,
                    'is_favorite' => true,
                    'cached_count' => $count,
                    'created_by_user_id' => $creator?->id,
                ]
            );
        }
    }
}
