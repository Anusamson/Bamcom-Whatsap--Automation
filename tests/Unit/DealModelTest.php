<?php

namespace Tests\Unit;

use App\Events\DealLost;
use App\Events\DealWon;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_deal_generates_uuid_on_creation(): void
    {
        $deal = Deal::factory()->create();

        $this->assertNotEmpty($deal->uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $deal->uuid);
    }

    public function test_deal_status_helpers(): void
    {
        $openDeal = Deal::factory()->open()->create();
        $this->assertTrue($openDeal->is_open);
        $this->assertFalse($openDeal->is_won);
        $this->assertFalse($openDeal->is_lost);

        $wonDeal = Deal::factory()->won()->create();
        $this->assertFalse($wonDeal->is_open);
        $this->assertTrue($wonDeal->is_won);
        $this->assertFalse($wonDeal->is_lost);

        $lostDeal = Deal::factory()->lost('Budget exceeded')->create();
        $this->assertFalse($lostDeal->is_open);
        $this->assertFalse($lostDeal->is_won);
        $this->assertTrue($lostDeal->is_lost);
        $this->assertEquals('Budget exceeded', $lostDeal->lost_reason);
    }

    public function test_deal_formatted_deal_value(): void
    {
        $deal = Deal::factory()->create([
            'deal_value' => 50000000,
            'currency' => 'NGN',
        ]);

        $this->assertEquals('₦50,000,000.00', $deal->formatted_deal_value);
    }

    public function test_deal_scopes_filter_correctly(): void
    {
        Deal::factory()->count(3)->open()->create();
        Deal::factory()->count(2)->won()->create();
        Deal::factory()->count(4)->lost()->create();

        $this->assertCount(3, Deal::open()->get());
        $this->assertCount(2, Deal::won()->get());
        $this->assertCount(4, Deal::lost()->get());
    }

    public function test_deal_search_scope(): void
    {
        $contact = Contact::factory()->create(['first_name' => 'Adewale', 'last_name' => 'Adeyemi']);
        $deal = Deal::factory()->create([
            'title' => 'Epe Waterfront Plots Deal',
            'contact_id' => $contact->id,
        ]);
        Deal::factory()->create(['title' => 'Abuja Commercial Plaza']);

        $results = Deal::search('Waterfront')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($deal->id, $results->first()->id);

        $resultsByContact = Deal::search('Adewale')->get();
        $this->assertCount(1, $resultsByContact);
        $this->assertEquals($deal->id, $resultsByContact->first()->id);
    }

    public function test_deal_relationships(): void
    {
        $contact = Contact::factory()->create();
        $lead = Lead::factory()->create(['contact_id' => $contact->id]);
        $property = Property::factory()->create();
        $user = User::factory()->create();
        $pipeline = Pipeline::factory()->create();
        $stage = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id]);

        $deal = Deal::factory()->create([
            'contact_id' => $contact->id,
            'lead_id' => $lead->id,
            'property_id' => $property->id,
            'assigned_user_id' => $user->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $this->assertEquals($contact->id, $deal->contact->id);
        $this->assertEquals($lead->id, $deal->lead->id);
        $this->assertEquals($property->id, $deal->property->id);
        $this->assertEquals($user->id, $deal->assignedUser->id);
        $this->assertEquals($pipeline->id, $deal->pipeline->id);
        $this->assertEquals($stage->id, $deal->stage->id);
    }

    public function test_deal_won_and_lost_events(): void
    {
        $deal = Deal::factory()->create();
        $causer = User::factory()->create();

        $wonEvent = new DealWon($deal, $causer);
        $this->assertSame($deal, $wonEvent->deal);
        $this->assertSame($causer, $wonEvent->causer);

        $lostEvent = new DealLost($deal, 'Client chose another estate', $causer);
        $this->assertSame($deal, $lostEvent->deal);
        $this->assertEquals('Client chose another estate', $lostEvent->lostReason);
        $this->assertSame($causer, $lostEvent->causer);
    }

    public function test_deal_route_binding_by_id_and_uuid(): void
    {
        $deal = Deal::factory()->create();

        $resolvedById = (new Deal)->resolveRouteBinding($deal->id);
        $this->assertNotNull($resolvedById);
        $this->assertEquals($deal->id, $resolvedById->id);

        $resolvedByUuid = (new Deal)->resolveRouteBinding($deal->uuid);
        $this->assertNotNull($resolvedByUuid);
        $this->assertEquals($deal->id, $resolvedByUuid->id);
    }
}
