<?php

namespace Tests\Feature\Api\V1;

use App\Enums\DealStatus;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Events\DealLost;
use App\Events\DealWon;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DealApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Pipeline $pipeline;

    protected PipelineStage $stage;

    protected Contact $contact;

    protected Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->user->assignRole($superAdminRole);

        $this->pipeline = Pipeline::factory()->create(['is_default' => true]);
        $this->stage = PipelineStage::factory()->create(['pipeline_id' => $this->pipeline->id]);

        $this->contact = Contact::factory()->create();
        $this->property = Property::factory()->create();
    }

    public function test_api_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson(route('api.v1.deals.index'));
        $response->assertUnauthorized();
    }

    public function test_api_can_list_deals_with_pagination(): void
    {
        Sanctum::actingAs($this->user);

        Deal::factory()->count(10)->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $response = $this->getJson(route('api.v1.deals.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'title',
                        'contact_id',
                        'deal_value',
                        'status',
                    ],
                ],
                'metrics' => [
                    'total_deals',
                    'open_deals_count',
                    'won_deals_count',
                    'lost_deals_count',
                    'win_rate',
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_api_can_filter_deals_by_status(): void
    {
        Sanctum::actingAs($this->user);

        Deal::factory()->count(4)->open()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
        Deal::factory()->count(2)->won()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $response = $this->getJson(route('api.v1.deals.index', ['status' => 'won']));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_api_can_create_deal_connecting_entities(): void
    {
        Sanctum::actingAs($this->user);

        $lead = Lead::factory()->create(['contact_id' => $this->contact->id]);

        $payload = [
            'title' => 'Victoria Island Penthouse Deal',
            'contact_id' => $this->contact->id,
            'lead_id' => $lead->id,
            'property_id' => $this->property->id,
            'assigned_user_id' => $this->user->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'deal_value' => 120000000,
            'expected_close_date' => Carbon::now()->addDays(45)->toDateString(),
            'notes' => 'High-net-worth client interested in immediate inspection.',
        ];

        $response = $this->postJson(route('api.v1.deals.store'), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.title', 'Victoria Island Penthouse Deal')
            ->assertJsonPath('data.contact_id', $this->contact->id)
            ->assertJsonPath('data.deal_value', '120000000.00');

        $this->assertDatabaseHas('deals', [
            'title' => 'Victoria Island Penthouse Deal',
            'contact_id' => $this->contact->id,
            'deal_value' => 120000000,
            'status' => DealStatus::Open->value,
        ]);
    }

    public function test_api_create_deal_validation(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(route('api.v1.deals.store'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contact_id', 'deal_value']);
    }

    public function test_api_can_view_single_deal(): void
    {
        Sanctum::actingAs($this->user);

        $deal = Deal::factory()->create([
            'contact_id' => $this->contact->id,
            'property_id' => $this->property->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $response = $this->getJson(route('api.v1.deals.show', $deal));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $deal->id)
            ->assertJsonPath('data.uuid', $deal->uuid);
    }

    public function test_api_can_update_deal(): void
    {
        Sanctum::actingAs($this->user);

        $deal = Deal::factory()->open()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'deal_value' => 50000000,
        ]);

        $response = $this->putJson(route('api.v1.deals.update', $deal), [
            'title' => 'Updated Penthouse Deal Value',
            'deal_value' => 65000000,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.deal_value', '65000000.00');

        $deal->refresh();
        $this->assertEquals(65000000, $deal->deal_value);
    }

    public function test_api_can_mark_deal_as_won(): void
    {
        Sanctum::actingAs($this->user);
        Event::fake([DealWon::class]);

        $deal = Deal::factory()->open()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $response = $this->postJson(route('api.v1.deals.won', $deal));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', DealStatus::Won->value);

        $deal->refresh();
        $this->assertEquals(DealStatus::Won, $deal->status);
        $this->assertNotNull($deal->actual_close_date);

        Event::assertDispatched(DealWon::class);
    }

    public function test_api_can_mark_deal_as_lost_with_reason(): void
    {
        Sanctum::actingAs($this->user);
        Event::fake([DealLost::class]);

        $deal = Deal::factory()->open()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        // Missing lost_reason triggers 422
        $failResponse = $this->postJson(route('api.v1.deals.lost', $deal), []);
        $failResponse->assertStatus(422)
            ->assertJsonValidationErrors(['lost_reason']);

        // Valid lost_reason marks as lost
        $response = $this->postJson(route('api.v1.deals.lost', $deal), [
            'lost_reason' => 'Client relocated abroad',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', DealStatus::Lost->value)
            ->assertJsonPath('data.lost_reason', 'Client relocated abroad');

        $deal->refresh();
        $this->assertEquals(DealStatus::Lost, $deal->status);
        $this->assertEquals('Client relocated abroad', $deal->lost_reason);

        Event::assertDispatched(DealLost::class);
    }

    public function test_api_can_delete_deal(): void
    {
        Sanctum::actingAs($this->user);

        $deal = Deal::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $response = $this->deleteJson(route('api.v1.deals.destroy', $deal));

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('deals', ['id' => $deal->id]);
    }
}
