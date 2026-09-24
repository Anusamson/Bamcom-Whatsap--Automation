<?php

namespace Tests\Unit;

use App\Enums\InspectionStatus;
use App\Enums\LeadSource;
use App\Enums\LeadTemperature;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Property;
use App\Models\Tag;
use App\Models\User;
use App\Services\Campaign\AudienceSegmentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudienceSegmentationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AudienceSegmentationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AudienceSegmentationService::class);
    }

    /**
     * Opted out contacts are excluded by default.
     */
    public function test_excludes_opted_out_contacts_by_default(): void
    {
        $activeContact = Contact::factory()->create([
            'phone' => '+2348011111111',
            'has_opted_out' => false,
        ]);

        $optedOutContact = Contact::factory()->create([
            'phone' => '+2348022222222',
            'has_opted_out' => true,
        ]);

        $contacts = $this->service->getContacts([]);

        $this->assertTrue($contacts->contains('id', $activeContact->id));
        $this->assertFalse($contacts->contains('id', $optedOutContact->id));
    }

    /**
     * Segmentation by pipeline stage.
     */
    public function test_segments_by_pipeline_stage(): void
    {
        $pipeline = Pipeline::factory()->create();
        $stage1 = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'order_column' => 1]);
        $stage2 = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'order_column' => 2]);

        $contact1 = Contact::factory()->create(['phone' => '+2348033333333', 'has_opted_out' => false]);
        Lead::factory()->create(['contact_id' => $contact1->id, 'pipeline_stage_id' => $stage1->id]);

        $contact2 = Contact::factory()->create(['phone' => '+2348044444444', 'has_opted_out' => false]);
        Lead::factory()->create(['contact_id' => $contact2->id, 'pipeline_stage_id' => $stage2->id]);

        $results = $this->service->getContacts(['stage_ids' => [$stage1->id]]);

        $this->assertTrue($results->contains('id', $contact1->id));
        $this->assertFalse($results->contains('id', $contact2->id));
    }

    /**
     * Segmentation by temperature.
     */
    public function test_segments_by_lead_temperature(): void
    {
        $contactHot = Contact::factory()->create(['phone' => '+2348055555555', 'has_opted_out' => false]);
        Lead::factory()->create(['contact_id' => $contactHot->id, 'temperature' => LeadTemperature::Hot]);

        $contactCold = Contact::factory()->create(['phone' => '+2348066666666', 'has_opted_out' => false]);
        Lead::factory()->create(['contact_id' => $contactCold->id, 'temperature' => LeadTemperature::Cold]);

        $results = $this->service->getContacts(['temperatures' => ['hot']]);

        $this->assertTrue($results->contains('id', $contactHot->id));
        $this->assertFalse($results->contains('id', $contactCold->id));
    }

    /**
     * Segmentation by tags.
     */
    public function test_segments_by_tags(): void
    {
        $tagVip = Tag::factory()->create(['name' => 'VIP Buyer', 'slug' => 'vip-buyer']);
        $tagInvestor = Tag::factory()->create(['name' => 'Foreign Investor', 'slug' => 'foreign-investor']);

        $contactVip = Contact::factory()->create(['phone' => '+2348077777777', 'has_opted_out' => false]);
        $contactVip->tags()->attach($tagVip);

        $contactOther = Contact::factory()->create(['phone' => '+2348088888888', 'has_opted_out' => false]);
        $contactOther->tags()->attach($tagInvestor);

        $results = $this->service->getContacts(['tags' => ['VIP Buyer']]);

        $this->assertTrue($results->contains('id', $contactVip->id));
        $this->assertFalse($results->contains('id', $contactOther->id));
    }

    /**
     * Segmentation by location.
     */
    public function test_segments_by_location(): void
    {
        $contactLekki = Contact::factory()->create([
            'phone' => '+2348099999991',
            'location' => 'Lekki Phase 1, Lagos',
            'has_opted_out' => false,
        ]);

        $contactAbuja = Contact::factory()->create([
            'phone' => '+2348099999992',
            'location' => 'Maitama, Abuja',
            'has_opted_out' => false,
        ]);

        $results = $this->service->getContacts(['locations' => ['Lekki']]);

        $this->assertTrue($results->contains('id', $contactLekki->id));
        $this->assertFalse($results->contains('id', $contactAbuja->id));
    }

    /**
     * Segmentation by property interest.
     */
    public function test_segments_by_property_interest(): void
    {
        $propertyA = Property::factory()->create(['title' => 'Eko Atlantic Penthouse']);
        $propertyB = Property::factory()->create(['title' => 'Banana Island Villa']);

        $contactA = Contact::factory()->create(['phone' => '+2348099999993', 'has_opted_out' => false]);
        Lead::factory()->create(['contact_id' => $contactA->id, 'property_id' => $propertyA->id]);

        $contactB = Contact::factory()->create(['phone' => '+2348099999994', 'has_opted_out' => false]);
        Lead::factory()->create(['contact_id' => $contactB->id, 'property_id' => $propertyB->id]);

        $results = $this->service->getContacts(['property_ids' => [$propertyA->id]]);

        $this->assertTrue($results->contains('id', $contactA->id));
        $this->assertFalse($results->contains('id', $contactB->id));
    }

    /**
     * Segmentation by budget range.
     */
    public function test_segments_by_budget_range(): void
    {
        $pipeline = Pipeline::factory()->create();
        $stage = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'order_column' => 1]);

        $contactHigh = Contact::factory()->create(['phone' => '+2348099999995', 'has_opted_out' => false]);
        Deal::factory()->create([
            'contact_id' => $contactHigh->id,
            'pipeline_stage_id' => $stage->id,
            'deal_value' => 250000000,
        ]);

        $contactLow = Contact::factory()->create(['phone' => '+2348099999996', 'has_opted_out' => false]);
        Deal::factory()->create([
            'contact_id' => $contactLow->id,
            'pipeline_stage_id' => $stage->id,
            'deal_value' => 50000000,
        ]);

        $results = $this->service->getContacts([
            'min_budget' => 200000000,
            'max_budget' => 300000000,
        ]);

        $this->assertTrue($results->contains('id', $contactHigh->id));
        $this->assertFalse($results->contains('id', $contactLow->id));
    }

    /**
     * Segmentation by assigned agent.
     */
    public function test_segments_by_assigned_agent(): void
    {
        $agentA = User::factory()->create();
        $agentB = User::factory()->create();

        $contactA = Contact::factory()->create([
            'phone' => '+2348099999997',
            'assigned_user_id' => $agentA->id,
            'has_opted_out' => false,
        ]);

        $contactB = Contact::factory()->create([
            'phone' => '+2348099999998',
            'assigned_user_id' => $agentB->id,
            'has_opted_out' => false,
        ]);

        $results = $this->service->getContacts(['agent_ids' => [$agentA->id]]);

        $this->assertTrue($results->contains('id', $contactA->id));
        $this->assertFalse($results->contains('id', $contactB->id));
    }

    /**
     * Segmentation by lead source.
     */
    public function test_segments_by_lead_source(): void
    {
        $contactWhatsApp = Contact::factory()->create([
            'phone' => '+2348099999911',
            'lead_source' => LeadSource::WhatsApp,
            'has_opted_out' => false,
        ]);

        $contactReferral = Contact::factory()->create([
            'phone' => '+2348099999912',
            'lead_source' => LeadSource::Referral,
            'has_opted_out' => false,
        ]);

        $results = $this->service->getContacts(['sources' => [LeadSource::WhatsApp->value]]);

        $this->assertTrue($results->contains('id', $contactWhatsApp->id));
        $this->assertFalse($results->contains('id', $contactReferral->id));
    }

    /**
     * Segmentation by inspection status.
     */
    public function test_segments_by_inspection_status(): void
    {
        $property = Property::factory()->create();
        $user = User::factory()->create();

        $contactCompleted = Contact::factory()->create(['phone' => '+2348099999921', 'has_opted_out' => false]);
        Inspection::factory()->create([
            'contact_id' => $contactCompleted->id,
            'property_id' => $property->id,
            'status' => InspectionStatus::Completed,
        ]);

        $contactRequested = Contact::factory()->create(['phone' => '+2348099999922', 'has_opted_out' => false]);
        Inspection::factory()->create([
            'contact_id' => $contactRequested->id,
            'property_id' => $property->id,
            'status' => InspectionStatus::Requested,
        ]);

        $results = $this->service->getContacts(['inspection_statuses' => [InspectionStatus::Completed->value]]);

        $this->assertTrue($results->contains('id', $contactCompleted->id));
        $this->assertFalse($results->contains('id', $contactRequested->id));
    }

    /**
     * Segmentation by last contact recency.
     */
    public function test_segments_by_last_contact(): void
    {
        $contactRecent = Contact::factory()->create([
            'phone' => '+2348099999931',
            'last_contact_at' => now()->subDays(2),
            'has_opted_out' => false,
        ]);

        $contactOld = Contact::factory()->create([
            'phone' => '+2348099999932',
            'last_contact_at' => now()->subDays(30),
            'has_opted_out' => false,
        ]);

        // Within 7 days
        $within7 = $this->service->getContacts(['last_contact_within_days' => 7]);
        $this->assertTrue($within7->contains('id', $contactRecent->id));
        $this->assertFalse($within7->contains('id', $contactOld->id));

        // Inactive before 14 days
        $before14 = $this->service->getContacts(['last_contact_before_days' => 14]);
        $this->assertFalse($before14->contains('id', $contactRecent->id));
        $this->assertTrue($before14->contains('id', $contactOld->id));
    }

    /**
     * Preview segmentation returns count and sample.
     */
    public function test_preview_segmentation_returns_count_and_sample(): void
    {
        Contact::factory()->count(5)->create([
            'has_opted_out' => false,
            'location' => 'Lekki Phase 1',
        ]);

        $preview = $this->service->previewSegmentation(['locations' => ['Lekki']]);

        $this->assertArrayHasKey('count', $preview);
        $this->assertArrayHasKey('sample', $preview);
        $this->assertEquals(5, $preview['count']);
        $this->assertCount(5, $preview['sample']);
    }
}
