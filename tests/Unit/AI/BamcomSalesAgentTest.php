<?php

namespace Tests\Unit\AI;

use App\Enums\ConversationMode;
use App\Enums\LeadTemperature;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Estate;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyPrice;
use App\Services\AI\BamcomSalesAgent;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BamcomSalesAgentTest extends TestCase
{
    use RefreshDatabase;

    protected BamcomSalesAgent $agent;

    protected ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = app(BamcomSalesAgent::class);
        $this->registry = app(ToolRegistry::class);
    }

    public function test_approved_system_prompt_enforces_anti_hallucination_and_real_estate_rules(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Emeka',
            'last_name' => 'Okafor',
            'phone' => '+2348031234567',
            'location' => 'Lekki, Lagos',
        ]);

        $lead = Lead::factory()->create([
            'contact_id' => $contact->id,
            'temperature' => LeadTemperature::Hot,
            'score' => 88,
            'property_interest' => 'Grace Haven Estate',
            'budget_min' => 20_000_000,
            'budget_max' => 35_000_000,
        ]);

        $prompt = $this->agent->getApprovedSystemPrompt($contact, 'Tell me about Grace Haven');

        // 1. Identity & Persona
        $this->assertStringContainsString('Bamcom Sales Agent', $prompt);
        $this->assertStringContainsString('Bamcom Properties & Real Estate Ltd', $prompt);

        // 2. Strict Anti-Hallucination & Property Integrity Mandate
        $this->assertStringContainsString('THE AI MUST NEVER INVENT PROPERTY INFORMATION', $prompt);
        $this->assertStringContainsString('You must NEVER make up or guess property prices', $prompt);

        // 3. Controlled Tools Named in Prompt
        $this->assertStringContainsString('searchProperties', $prompt);
        $this->assertStringContainsString('getPropertyDetails', $prompt);
        $this->assertStringContainsString('getPropertyPrice', $prompt);
        $this->assertStringContainsString('getPaymentPlan', $prompt);
        $this->assertStringContainsString('checkAvailability', $prompt);
        $this->assertStringContainsString('getContactProfile', $prompt);
        $this->assertStringContainsString('updateLeadQualification', $prompt);
        $this->assertStringContainsString('scheduleInspection', $prompt);
        $this->assertStringContainsString('createSalesTask', $prompt);
        $this->assertStringContainsString('requestHumanHandover', $prompt);

        // 4. Currency & Legal Real Estate Context
        $this->assertStringContainsString('Nigerian Naira', $prompt);
        $this->assertStringContainsString("Governor's Consent", $prompt);
        $this->assertStringContainsString('Certificate of Occupancy', $prompt);
        $this->assertStringContainsString('Plot 12, Admiralty Way, Lekki Phase 1', $prompt);

        // 5. CRM Client Context
        $this->assertStringContainsString('Emeka Okafor', $prompt);
        $this->assertStringContainsString('+2348031234567', $prompt);
        $this->assertStringContainsString('HOT', $prompt);
        $this->assertStringContainsString('Grace Haven Estate', $prompt);
    }

    public function test_all_ten_controlled_tools_are_registered_and_accessible(): void
    {
        $expectedTools = [
            'searchProperties',
            'getPropertyDetails',
            'getPropertyPrice',
            'getPaymentPlan',
            'checkAvailability',
            'getContactProfile',
            'updateLeadQualification',
            'scheduleInspection',
            'createSalesTask',
            'requestHumanHandover',
        ];

        foreach ($expectedTools as $toolName) {
            $this->assertTrue(
                $this->registry->has($toolName),
                "Expected controlled tool '{$toolName}' is not registered in ToolRegistry."
            );
        }
    }

    public function test_search_properties_tool_returns_live_inventory(): void
    {
        $estate = Estate::factory()->create([
            'name' => 'Victoria Crest Estate',
            'location' => 'Ibeju-Lekki',
            'status' => 'active',
        ]);

        $property = Property::factory()->create([
            'estate_id' => $estate->id,
            'title' => 'Victoria Crest 600sqm Plot',
            'plot_size' => '600sqm',
            'available_units' => 8,
            'availability' => 'available',
            'status' => 'published',
        ]);

        PropertyPrice::create([
            'property_id' => $property->id,
            'regular_price' => 18_000_000,
            'is_active' => true,
        ]);

        $result = $this->agent->executeTool('searchProperties', [
            'location' => 'Ibeju-Lekki',
        ]);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(1, $result['found_count']);
        $this->assertEquals('Victoria Crest 600sqm Plot', $result['properties'][0]['title']);
    }

    public function test_get_property_details_tool_never_invents_missing_property(): void
    {
        $estate = Estate::factory()->create([
            'name' => 'Royal Haven Palms',
            'title_document' => "Governor's Consent",
            'status' => 'active',
        ]);

        $property = Property::factory()->create([
            'estate_id' => $estate->id,
            'title' => 'Royal Haven Plot 14',
            'status' => 'published',
        ]);

        // 1. Existing property returns details
        $existing = $this->agent->executeTool('getPropertyDetails', [
            'property_id' => $property->id,
        ]);
        $this->assertTrue($existing['success']);
        $this->assertTrue($existing['found']);
        $this->assertEquals('Royal Haven Plot 14', $existing['property']['title']);

        // 2. Non-existent property returns not found instead of hallucinating
        $missing = $this->agent->executeTool('getPropertyDetails', [
            'property_id' => 999999,
        ]);
        $this->assertTrue($missing['success']);
        $this->assertFalse($missing['found']);
        $this->assertStringContainsString('Property not found in verified database', $missing['message']);
    }

    public function test_get_property_price_tool_returns_exact_database_pricing(): void
    {
        $property = Property::factory()->create([
            'title' => 'Lekki Pearl 500sqm Plot',
            'status' => 'published',
        ]);

        PropertyPrice::create([
            'property_id' => $property->id,
            'regular_price' => 40_000_000,
            'promo_price' => 36_000_000,
            'initial_deposit' => 8_000_000,
            'is_active' => true,
        ]);

        $result = $this->agent->executeTool('getPropertyPrice', [
            'property_id' => $property->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(40_000_000, $result['regular_price']);
        $this->assertEquals(36_000_000, $result['effective_price']);
        $this->assertEquals(4_000_000, $result['savings']);
        $this->assertEquals('₦36,000,000.00', $result['effective_price_formatted']);
        $this->assertEquals(8_000_000, $result['initial_deposit']);
    }

    public function test_get_payment_plan_tool_calculates_installments_correctly(): void
    {
        $result = $this->agent->executeTool('getPaymentPlan', [
            'total_amount' => 30_000_000,
            'duration_months' => 6,
            'initial_deposit' => 6_000_000,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(30_000_000, $result['total_amount']);
        $this->assertEquals(6_000_000, $result['initial_deposit']);
        $this->assertEquals('20%', $result['deposit_percentage']);
        $this->assertEquals(6, $result['duration_months']);
        $this->assertEquals(4_000_000, $result['monthly_payment']); // (30m - 6m) / 6 = 4m
        $this->assertEquals('₦4,000,000.00', $result['monthly_payment_formatted']);
    }

    public function test_check_availability_tool_verifies_stock(): void
    {
        $property = Property::factory()->create([
            'title' => 'Silver Spring Plot',
            'available_units' => 3,
            'total_units' => 20,
            'availability' => 'available',
            'status' => 'published',
        ]);

        $result = $this->agent->executeTool('checkAvailability', [
            'property_id' => $property->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_available']);
        $this->assertEquals(3, $result['available_units']);
        $this->assertEquals(20, $result['total_units']);
    }

    public function test_get_contact_profile_tool_retrieves_crm_lead_data(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Aisha',
            'last_name' => 'Bello',
            'phone' => '+2348055551234',
        ]);

        $lead = Lead::factory()->create([
            'contact_id' => $contact->id,
            'temperature' => LeadTemperature::Hot,
            'score' => 92,
            'property_interest' => 'Epe Industrial Park',
        ]);

        $result = $this->agent->executeTool('getContactProfile', [
            'contact_id' => $contact->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Aisha Bello', $result['name']);
        $this->assertEquals('+2348055551234', $result['phone']);
        $this->assertEquals('HOT', $result['lead']['temperature']);
        $this->assertEquals(92, $result['lead']['score']);
        $this->assertEquals('Epe Industrial Park', $result['lead']['property_interest']);
    }

    public function test_update_lead_qualification_tool_updates_crm_and_records_activity(): void
    {
        $contact = Contact::factory()->create();
        $lead = Lead::factory()->create([
            'contact_id' => $contact->id,
            'temperature' => LeadTemperature::Warm,
            'score' => 60,
        ]);

        $result = $this->agent->executeTool('updateLeadQualification', [
            'lead_id' => $lead->id,
            'temperature' => 'hot',
            'property_interest' => 'Atlantic Breeze Estate',
            'budget_min' => 15_000_000,
            'budget_max' => 25_000_000,
            'notes' => 'Buyer plans to build residential duplex by December.',
        ], ['contact' => $contact]);

        $this->assertTrue($result['success']);
        $this->assertEquals('hot', $result['temperature']);
        $this->assertGreaterThan(60, $result['score']);

        $lead->refresh();
        $this->assertEquals(LeadTemperature::Hot, $lead->temperature);
        $this->assertEquals('Atlantic Breeze Estate', $lead->property_interest);
        $this->assertStringContainsString('Buyer plans to build residential duplex', $lead->notes);

        $this->assertDatabaseHas('activities', [
            'lead_id' => $lead->id,
            'activity_type' => 'qualification',
        ]);
    }

    public function test_schedule_inspection_tool_creates_crm_activity_and_upgrades_lead(): void
    {
        $contact = Contact::factory()->create();
        $lead = Lead::factory()->create([
            'contact_id' => $contact->id,
            'temperature' => LeadTemperature::Warm,
            'score' => 65,
        ]);

        $inspectionDate = now()->addDays(3)->format('Y-m-d');

        $result = $this->agent->executeTool('scheduleInspection', [
            'preferred_date' => $inspectionDate,
            'preferred_time' => '10:00 AM',
            'notes' => 'Client requires 2 seats in the inspection bus.',
        ], ['contact' => $contact]);

        $this->assertTrue($result['success']);
        $this->assertEquals($inspectionDate, $result['preferred_date']);
        $this->assertEquals('10:00 AM', $result['preferred_time']);
        $this->assertStringContainsString('Plot 12, Admiralty Way, Lekki Phase 1', $result['pickup_location']);

        $lead->refresh();
        $this->assertEquals(LeadTemperature::Hot, $lead->temperature);

        $this->assertDatabaseHas('activities', [
            'lead_id' => $lead->id,
            'activity_type' => 'inspection',
        ]);
    }

    public function test_create_sales_task_tool_records_team_followup(): void
    {
        $contact = Contact::factory()->create();
        $lead = Lead::factory()->create(['contact_id' => $contact->id]);

        $result = $this->agent->executeTool('createSalesTask', [
            'title' => 'Follow up on C of O verification documents',
            'description' => 'Client requested scan of registered survey for plot 20.',
            'due_date' => now()->addDays(2)->format('Y-m-d'),
            'priority' => 'high',
        ], ['contact' => $contact]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Follow up on C of O verification documents', $result['title']);
        $this->assertEquals('high', $result['priority']);

        $this->assertDatabaseHas('activities', [
            'lead_id' => $lead->id,
            'activity_type' => 'task',
        ]);
    }

    public function test_request_human_handover_tool_switches_conversation_mode(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create([
            'contact_id' => $contact->id,
            'mode' => ConversationMode::Ai,
        ]);

        $result = $this->agent->executeTool('requestHumanHandover', [
            'reason' => 'Client wants to negotiate bulk discount with managing director.',
            'urgency' => 'high',
        ], ['conversation' => $conversation]);

        $this->assertTrue($result['success']);
        $this->assertEquals('human', $result['conversation_mode']);

        $conversation->refresh();
        $this->assertEquals(ConversationMode::Human, $conversation->mode);
    }

    public function test_log_execution_persists_audit_trail_in_database(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        $log = $this->agent->logExecution([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'agent_name' => 'BamcomSalesAgent',
            'provider' => 'mock',
            'user_message' => 'What is the price of Grace Haven?',
            'tool_calls' => [
                ['tool' => 'getPropertyPrice', 'arguments' => ['property_name' => 'Grace Haven']],
            ],
            'tool_results' => [
                'getPropertyPrice' => ['effective_price' => 25_000_000],
            ],
            'response_content' => 'Grace Haven is currently *₦25,000,000*.',
            'finish_reason' => 'tool_execution_complete',
            'duration_ms' => 145.50,
            'total_tokens' => 380,
            'status' => 'success',
        ]);

        $this->assertNotNull($log->id);
        $this->assertNotNull($log->uuid);
        $this->assertDatabaseHas('ai_execution_logs', [
            'id' => $log->id,
            'conversation_id' => $conversation->id,
            'agent_name' => 'BamcomSalesAgent',
            'provider' => 'mock',
            'status' => 'success',
        ]);
    }
}
