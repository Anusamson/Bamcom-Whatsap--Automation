<?php

namespace Tests\Unit\AI;

use App\Enums\ConversationMode;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Estate;
use App\Models\Property;
use App\Models\PropertyPrice;
use App\Services\AI\Tools\BookInspectionTool;
use App\Services\AI\Tools\CalculatePaymentPlanTool;
use App\Services\AI\Tools\EscalateToHumanTool;
use App\Services\AI\Tools\SearchInventoryTool;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\Property\PropertyIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ToolSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $intelligence = new PropertyIntelligenceService;

        $this->registry = new ToolRegistry([
            new SearchInventoryTool($intelligence),
            new BookInspectionTool,
            new EscalateToHumanTool,
            new CalculatePaymentPlanTool,
        ]);
    }

    public function test_can_execute_whitelisted_search_inventory_tool(): void
    {
        $estate = Estate::factory()->create(['name' => 'Oasis Heights', 'location' => 'Epe']);
        $prop = Property::factory()->create(['estate_id' => $estate->id, 'title' => 'Residential 500sqm Plot']);
        PropertyPrice::create(['property_id' => $prop->id, 'regular_price' => 12000000, 'is_active' => true]);

        $result = $this->registry->execute('search_inventory', [
            'location' => 'Epe',
            'limit' => 2,
        ]);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(1, $result['count']);
        $this->assertEquals('Residential 500sqm Plot', $result['properties'][0]['title']);
    }

    public function test_can_execute_whitelisted_inspection_booking_tool(): void
    {
        $estate = Estate::factory()->create(['name' => 'Grandview Meadows']);
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        $result = $this->registry->execute('book_inspection', [
            'estate_name' => $estate->name,
            'date' => '2026-10-20',
            'time' => '10:00 AM',
            'notes' => 'Client needs transport from Lekki.',
        ], ['conversation' => $conversation]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('activities', [
            'activity_type' => 'inspection_scheduled',
        ]);
    }

    public function test_can_execute_whitelisted_human_escalation_tool(): void
    {
        $conversation = Conversation::factory()->ai()->create();

        $result = $this->registry->execute('escalate_to_human', [
            'reason' => 'Negotiation requested by prospective investor',
        ], ['conversation' => $conversation]);

        $this->assertTrue($result['success']);
        $this->assertEquals('human', $result['mode']);
        $this->assertEquals(ConversationMode::Human, $conversation->fresh()->mode);
    }

    public function test_can_execute_payment_plan_calculation_tool(): void
    {
        $property = Property::factory()->create();
        PropertyPrice::create([
            'property_id' => $property->id,
            'regular_price' => 20000000,
            'initial_deposit' => 4000000,
            'is_active' => true,
        ]);

        $result = $this->registry->execute('calculate_payment_plan', [
            'property_id' => $property->id,
            'duration_months' => 6,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(20000000, $result['total_price']);
        $this->assertEquals(4000000, $result['initial_deposit']);
        $this->assertEquals(16000000, $result['remaining_balance']);
        $this->assertEquals('₦2,666,666.67', $result['monthly_installment_formatted']);
    }

    public function test_rejects_unregistered_arbitrary_actions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unauthorized or unrecognized AI action 'execute_arbitrary_sql'");

        $this->registry->execute('execute_arbitrary_sql', [
            'query' => 'SELECT * FROM users',
        ]);
    }

    public function test_rejects_sql_injection_in_arguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Security Violation: Potential arbitrary SQL pattern detected');

        $this->registry->execute('search_inventory', [
            'location' => "Epe'; DROP TABLE properties; --",
        ]);
    }
}
