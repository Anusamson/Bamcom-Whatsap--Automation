<?php

namespace Tests\Unit;

use App\Enums\LeadTemperature;
use App\Enums\PurchaseTimeline;
use App\Events\LeadScoreChanged;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\LeadScoreLog;
use App\Models\LeadScoringRule;
use App\Models\Property;
use App\Services\Lead\LeadScoringService;
use Database\Seeders\LeadScoringRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LeadScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LeadScoringService $service;

    protected Lead $lead;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LeadScoringRuleSeeder::class);
        $this->service = app(LeadScoringService::class);

        $this->contact = Contact::factory()->create();
        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'score' => 0,
            'temperature' => LeadTemperature::Cold,
        ]);
    }

    public function test_lead_temperature_from_score_thresholds(): void
    {
        // Cold: 0 - 29
        $this->assertEquals(LeadTemperature::Cold, LeadTemperature::fromScore(-10));
        $this->assertEquals(LeadTemperature::Cold, LeadTemperature::fromScore(0));
        $this->assertEquals(LeadTemperature::Cold, LeadTemperature::fromScore(15));
        $this->assertEquals(LeadTemperature::Cold, LeadTemperature::fromScore(29));

        // Warm: 30 - 59
        $this->assertEquals(LeadTemperature::Warm, LeadTemperature::fromScore(30));
        $this->assertEquals(LeadTemperature::Warm, LeadTemperature::fromScore(45));
        $this->assertEquals(LeadTemperature::Warm, LeadTemperature::fromScore(59));

        // Hot: 60+
        $this->assertEquals(LeadTemperature::Hot, LeadTemperature::fromScore(60));
        $this->assertEquals(LeadTemperature::Hot, LeadTemperature::fromScore(85));
        $this->assertEquals(LeadTemperature::Hot, LeadTemperature::fromScore(100));
        $this->assertEquals(LeadTemperature::Hot, LeadTemperature::fromScore(150));
    }

    public function test_initial_ten_rules_seeded_with_expected_points(): void
    {
        $expectedRules = [
            'new_enquiry' => 5,
            'property_identified' => 5,
            'location_supplied' => 5,
            'price_enquiry' => 5,
            'budget_supplied' => 10,
            'payment_plan_enquiry' => 10,
            'purchase_within_30_days' => 15,
            'inspection_request' => 20,
            'inspection_completed' => 20,
            'payment_process_request' => 25,
        ];

        foreach ($expectedRules as $eventKey => $points) {
            $rule = LeadScoringRule::where('event_key', $eventKey)->first();
            $this->assertNotNull($rule, "Expected rule for {$eventKey} to exist");
            $this->assertEquals($points, $rule->points, "Points for {$eventKey} mismatch");
            $this->assertTrue($rule->is_active);
        }
    }

    public function test_records_event_and_updates_lead_score_and_temperature(): void
    {
        $this->assertEquals(0, $this->lead->score);
        $this->assertEquals(LeadTemperature::Cold, $this->lead->temperature);

        $log = $this->service->recordEvent($this->lead, 'new_enquiry');

        $this->assertNotNull($log);
        $this->assertInstanceOf(LeadScoreLog::class, $log);
        $this->assertEquals(5, $log->points_awarded);
        $this->assertEquals(0, $log->score_before);
        $this->assertEquals(5, $log->score_after);

        $this->lead->refresh();
        $this->assertEquals(5, $this->lead->score);
        $this->assertEquals(LeadTemperature::Cold, $this->lead->temperature);
    }

    public function test_transitions_temperature_across_tiers(): void
    {
        $this->lead->update(['score' => 25, 'temperature' => LeadTemperature::Cold]);

        // Cold to Warm transition (25 + 10 = 35 => Warm)
        $this->service->recordEvent($this->lead, 'budget_supplied');
        $this->lead->refresh();
        $this->assertEquals(35, $this->lead->score);
        $this->assertEquals(LeadTemperature::Warm, $this->lead->temperature);

        // Warm to Hot transition (35 + 25 = 60 => Hot)
        $this->service->recordEvent($this->lead, 'payment_process_request');
        $this->lead->refresh();
        $this->assertEquals(60, $this->lead->score);
        $this->assertEquals(LeadTemperature::Hot, $this->lead->temperature);
        $this->assertTrue($this->lead->is_hot);
    }

    public function test_dispatches_lead_score_changed_event(): void
    {
        Event::fake([LeadScoreChanged::class]);

        $this->service->recordEvent($this->lead, 'inspection_request');

        Event::assertDispatched(LeadScoreChanged::class, function (LeadScoreChanged $event) {
            return $event->lead->id === $this->lead->id
                && $event->oldScore === 0
                && $event->newScore === 20
                && $event->oldTemperature === LeadTemperature::Cold
                && $event->newTemperature === LeadTemperature::Cold
                && $event->eventKey === 'inspection_request'
                && $event->pointsAwarded === 20;
        });
    }

    public function test_idempotency_prevents_duplicate_points_for_one_off_milestone(): void
    {
        // 'location_supplied' has allow_multiple => false
        $firstLog = $this->service->recordEvent($this->lead, 'location_supplied');
        $this->assertNotNull($firstLog);
        $this->assertEquals(5, $this->lead->fresh()->score);

        // Duplicate call should return null and not increment score
        $secondLog = $this->service->recordEvent($this->lead, 'location_supplied');
        $this->assertNull($secondLog);
        $this->assertEquals(5, $this->lead->fresh()->score);
    }

    public function test_repeatable_rules_allow_multiple_awards(): void
    {
        // 'inspection_request' has allow_multiple => true
        $firstLog = $this->service->recordEvent($this->lead, 'inspection_request');
        $this->assertNotNull($firstLog);
        $this->assertEquals(20, $this->lead->fresh()->score);

        $secondLog = $this->service->recordEvent($this->lead, 'inspection_request');
        $this->assertNotNull($secondLog);
        $this->assertEquals(40, $this->lead->fresh()->score);
    }

    public function test_score_is_clamped_between_zero_and_one_hundred(): void
    {
        $this->lead->update(['score' => 90, 'temperature' => LeadTemperature::Hot]);

        // 90 + 25 = 115 => clamped to 100
        $this->service->recordEvent($this->lead, 'payment_process_request');
        $this->assertEquals(100, $this->lead->fresh()->score);

        // Score cannot go below 0
        $this->service->adjustScore($this->lead, -150, 'Penalty');
        $this->assertEquals(0, $this->lead->fresh()->score);
        $this->assertEquals(LeadTemperature::Cold, $this->lead->fresh()->temperature);
    }

    public function test_admin_can_modify_rule_points_dynamically(): void
    {
        $rule = LeadScoringRule::where('event_key', 'new_enquiry')->firstOrFail();

        // Admin increases points from 5 to 15
        $this->service->updateRule($rule, ['points' => 15]);

        $this->service->recordEvent($this->lead, 'new_enquiry');
        $this->assertEquals(15, $this->lead->fresh()->score);
    }

    public function test_inactive_rule_does_not_award_points(): void
    {
        $rule = LeadScoringRule::where('event_key', 'new_enquiry')->firstOrFail();
        $this->service->toggleRule($rule); // toggles to false

        $log = $this->service->recordEvent($this->lead, 'new_enquiry');
        $this->assertNull($log);
        $this->assertEquals(0, $this->lead->fresh()->score);
    }

    public function test_reset_to_defaults_restores_initial_rules(): void
    {
        $rule = LeadScoringRule::where('event_key', 'new_enquiry')->firstOrFail();
        $rule->update(['points' => 99]);

        LeadScoringRule::where('event_key', 'inspection_request')->delete();

        $this->service->resetToDefaults();

        $this->assertEquals(5, LeadScoringRule::where('event_key', 'new_enquiry')->value('points'));
        $this->assertDatabaseHas('lead_scoring_rules', ['event_key' => 'inspection_request', 'points' => 20]);
    }

    public function test_evaluate_profile_events_detects_attributes(): void
    {
        $property = Property::factory()->create();

        $this->lead->update([
            'property_id' => $property->id,
            'preferred_locations' => ['Epe', 'Ibeju Lekki'],
            'budget_max' => 45000000,
            'purchase_timeline' => PurchaseTimeline::Immediate,
        ]);

        $awarded = $this->service->evaluateProfileEvents($this->lead);

        // property_identified (+5) + location_supplied (+5) + budget_supplied (+10) + purchase_within_30_days (+15) = +35
        $this->assertCount(4, $awarded);
        $this->assertEquals(35, $this->lead->fresh()->score);
        $this->assertEquals(LeadTemperature::Warm, $this->lead->fresh()->temperature);
    }
}
