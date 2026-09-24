<?php

namespace Tests\Unit;

use App\Enums\InspectionStatus;
use App\Enums\LeadTemperature;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Property;
use App\Services\SmartList\SmartListQueryBuilder;
use App\Services\SmartList\SmartListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartListQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected SmartListQueryBuilder $builder;

    protected SmartListService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = app(SmartListQueryBuilder::class);
        $this->service = app(SmartListService::class);
    }

    /**
     * Single rule: contact location contains "Abuja".
     */
    public function test_single_rule_matches_contact_location(): void
    {
        $c1 = Contact::factory()->create(['location' => 'Maitama, Abuja']);
        $c2 = Contact::factory()->create(['location' => 'Lekki Phase 1, Lagos']);

        $rules = [
            'logical_operator' => 'AND',
            'rules' => [
                ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Abuja'],
            ],
        ];

        $results = $this->builder->buildQuery($rules)->get();

        $this->assertTrue($results->contains('id', $c1->id));
        $this->assertFalse($results->contains('id', $c2->id));
    }

    /**
     * AND condition: Hot Abuja Prospects (location contains "Abuja" AND temperature equals "hot").
     */
    public function test_and_condition_matches_hot_abuja_prospects(): void
    {
        // Contact 1: Abuja + Hot -> Matches
        $c1 = Contact::factory()->create(['location' => 'Wuse 2, Abuja']);
        Lead::factory()->create(['contact_id' => $c1->id, 'temperature' => LeadTemperature::Hot]);

        // Contact 2: Abuja + Cold -> Fails temperature
        $c2 = Contact::factory()->create(['location' => 'Garki, Abuja']);
        Lead::factory()->create(['contact_id' => $c2->id, 'temperature' => LeadTemperature::Cold]);

        // Contact 3: Lagos + Hot -> Fails location
        $c3 = Contact::factory()->create(['location' => 'Ikoyi, Lagos']);
        Lead::factory()->create(['contact_id' => $c3->id, 'temperature' => LeadTemperature::Hot]);

        $ruleGroups = [
            'logical_operator' => 'AND',
            'rules' => [
                ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Abuja'],
                ['field' => 'lead.temperature', 'operator' => 'equals', 'value' => 'hot'],
            ],
        ];

        $results = $this->builder->buildQuery($ruleGroups)->get();

        $this->assertTrue($results->contains('id', $c1->id));
        $this->assertFalse($results->contains('id', $c2->id));
        $this->assertFalse($results->contains('id', $c3->id));
        $this->assertCount(1, $results);
    }

    /**
     * OR condition: Peace Court Prospects (property title contains "Peace Court" OR inspection estate_name contains "Peace Court").
     */
    public function test_or_condition_matches_peace_court_prospects(): void
    {
        $propertyPeaceCourt = Property::factory()->create(['title' => 'Peace Court Luxury Villa']);
        $propertyOther = Property::factory()->create(['title' => 'Silverstone Penthouse']);

        // C1 matches via lead property title
        $c1 = Contact::factory()->create();
        Lead::factory()->create(['contact_id' => $c1->id, 'property_id' => $propertyPeaceCourt->id]);

        // C2 matches via inspection estate name
        $c2 = Contact::factory()->create();
        Inspection::factory()->create([
            'contact_id' => $c2->id,
            'estate_name' => 'Peace Court Estate Phase 2',
        ]);

        // C3 has different property and inspection
        $c3 = Contact::factory()->create();
        Lead::factory()->create(['contact_id' => $c3->id, 'property_id' => $propertyOther->id]);
        Inspection::factory()->create([
            'contact_id' => $c3->id,
            'estate_name' => 'Atlantic Bay Resort',
        ]);

        $ruleGroups = [
            'logical_operator' => 'OR',
            'rules' => [
                ['field' => 'property.title', 'operator' => 'contains', 'value' => 'Peace Court'],
                ['field' => 'inspection.estate_name', 'operator' => 'contains', 'value' => 'Peace Court'],
            ],
        ];

        $results = $this->builder->buildQuery($ruleGroups)->get();

        $this->assertTrue($results->contains('id', $c1->id));
        $this->assertTrue($results->contains('id', $c2->id));
        $this->assertFalse($results->contains('id', $c3->id));
        $this->assertCount(2, $results);
    }

    /**
     * Dormant Prospects condition: last_contact_days >= 30.
     */
    public function test_dormant_prospects_condition(): void
    {
        // C1: Contacted 45 days ago -> Dormant
        $c1 = Contact::factory()->create(['last_contact_at' => now()->subDays(45)]);

        // C2: Never contacted (last_contact_at is null) -> Dormant
        $c2 = Contact::factory()->create(['last_contact_at' => null]);

        // C3: Contacted 3 days ago -> Active, NOT dormant
        $c3 = Contact::factory()->create(['last_contact_at' => now()->subDays(3)]);

        $ruleGroups = [
            'logical_operator' => 'AND',
            'rules' => [
                ['field' => 'contact.last_contact_days', 'operator' => 'greater_than_or_equal', 'value' => 30],
            ],
        ];

        $results = $this->builder->buildQuery($ruleGroups)->get();

        $this->assertTrue($results->contains('id', $c1->id));
        $this->assertTrue($results->contains('id', $c2->id));
        $this->assertFalse($results->contains('id', $c3->id));
    }

    /**
     * Inspection Pending condition: inspection status in ['requested', 'scheduled'].
     */
    public function test_inspection_pending_condition(): void
    {
        // C1: Inspection Requested -> Pending
        $c1 = Contact::factory()->create();
        Inspection::factory()->create(['contact_id' => $c1->id, 'status' => InspectionStatus::Requested]);

        // C2: Inspection Scheduled -> Pending
        $c2 = Contact::factory()->create();
        Inspection::factory()->create(['contact_id' => $c2->id, 'status' => InspectionStatus::Scheduled]);

        // C3: Inspection Completed -> NOT pending
        $c3 = Contact::factory()->create();
        Inspection::factory()->create(['contact_id' => $c3->id, 'status' => InspectionStatus::Completed]);

        $ruleGroups = [
            'logical_operator' => 'AND',
            'rules' => [
                ['field' => 'inspection.status', 'operator' => 'in', 'value' => ['requested', 'scheduled']],
            ],
        ];

        $results = $this->builder->buildQuery($ruleGroups)->get();

        $this->assertTrue($results->contains('id', $c1->id));
        $this->assertTrue($results->contains('id', $c2->id));
        $this->assertFalse($results->contains('id', $c3->id));
    }

    /**
     * Payment Pending condition: deal stage name contains "Payment Pending".
     */
    public function test_payment_pending_condition(): void
    {
        $pipeline = Pipeline::factory()->create();
        $paymentStage = PipelineStage::factory()->create([
            'pipeline_id' => $pipeline->id,
            'name' => 'Payment Pending',
            'order_column' => 8,
        ]);
        $negotiationStage = PipelineStage::factory()->create([
            'pipeline_id' => $pipeline->id,
            'name' => 'Negotiation',
            'order_column' => 7,
        ]);

        // C1: Has deal in Payment Pending stage -> Matches
        $c1 = Contact::factory()->create();
        Deal::factory()->create(['contact_id' => $c1->id, 'pipeline_stage_id' => $paymentStage->id]);

        // C2: Has deal in Negotiation stage -> Fails
        $c2 = Contact::factory()->create();
        Deal::factory()->create(['contact_id' => $c2->id, 'pipeline_stage_id' => $negotiationStage->id]);

        $ruleGroups = [
            'logical_operator' => 'AND',
            'rules' => [
                ['field' => 'deal.stage_name', 'operator' => 'contains', 'value' => 'Payment Pending'],
            ],
        ];

        $results = $this->builder->buildQuery($ruleGroups)->get();

        $this->assertTrue($results->contains('id', $c1->id));
        $this->assertFalse($results->contains('id', $c2->id));
    }

    /**
     * Nested rule groups: (Location in Abuja OR Location in Lekki) AND Lead Temperature is Hot.
     */
    public function test_nested_rule_groups(): void
    {
        // C1: Abuja + Hot -> Matches
        $c1 = Contact::factory()->create(['location' => 'Asokoro, Abuja']);
        Lead::factory()->create(['contact_id' => $c1->id, 'temperature' => LeadTemperature::Hot]);

        // C2: Lekki + Hot -> Matches
        $c2 = Contact::factory()->create(['location' => 'Lekki Phase 1']);
        Lead::factory()->create(['contact_id' => $c2->id, 'temperature' => LeadTemperature::Hot]);

        // C3: Lekki + Cold -> Fails temperature
        $c3 = Contact::factory()->create(['location' => 'Lekki Phase 1']);
        Lead::factory()->create(['contact_id' => $c3->id, 'temperature' => LeadTemperature::Cold]);

        // C4: Kano + Hot -> Fails location sub-group
        $c4 = Contact::factory()->create(['location' => 'Nassarawa, Kano']);
        Lead::factory()->create(['contact_id' => $c4->id, 'temperature' => LeadTemperature::Hot]);

        $ruleGroups = [
            'logical_operator' => 'AND',
            'rules' => [
                [
                    'logical_operator' => 'OR',
                    'rules' => [
                        ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Abuja'],
                        ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Lekki'],
                    ],
                ],
                [
                    'field' => 'lead.temperature',
                    'operator' => 'equals',
                    'value' => 'hot',
                ],
            ],
        ];

        $results = $this->builder->buildQuery($ruleGroups)->get();

        $this->assertTrue($results->contains('id', $c1->id));
        $this->assertTrue($results->contains('id', $c2->id));
        $this->assertFalse($results->contains('id', $c3->id));
        $this->assertFalse($results->contains('id', $c4->id));
        $this->assertCount(2, $results);
    }

    /**
     * Prevents duplicate contact records when matching multiple related entities.
     */
    public function test_does_not_duplicate_contacts_when_matching_multiple_records(): void
    {
        $contact = Contact::factory()->create(['location' => 'Abuja']);

        // Contact has 3 hot leads and 2 scheduled inspections
        Lead::factory()->count(3)->create([
            'contact_id' => $contact->id,
            'temperature' => LeadTemperature::Hot,
        ]);
        Inspection::factory()->count(2)->create([
            'contact_id' => $contact->id,
            'status' => InspectionStatus::Scheduled,
        ]);

        $ruleGroups = [
            'logical_operator' => 'AND',
            'rules' => [
                ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Abuja'],
                ['field' => 'lead.temperature', 'operator' => 'equals', 'value' => 'hot'],
            ],
        ];

        $query = $this->builder->buildQuery($ruleGroups);

        $this->assertEquals(1, $query->count());
        $this->assertCount(1, $query->get());
    }

    /**
     * Preview count helper returns count and sample data.
     */
    public function test_preview_count_returns_expected_structure(): void
    {
        Contact::factory()->count(3)->create(['location' => 'Abuja']);

        $preview = $this->service->previewCount([
            'logical_operator' => 'AND',
            'rules' => [
                ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Abuja'],
            ],
        ]);

        $this->assertArrayHasKey('count', $preview);
        $this->assertArrayHasKey('total_count', $preview);
        $this->assertArrayHasKey('sample', $preview);
        $this->assertEquals(3, $preview['count']);
        $this->assertCount(3, $preview['sample']);
    }
}
