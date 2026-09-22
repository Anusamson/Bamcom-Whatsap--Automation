<?php

namespace Tests\Feature\Deals;

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
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DealManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $salesUser;

    protected User $unauthorizedUser;

    protected Pipeline $pipeline;

    protected PipelineStage $stage;

    protected Contact $contact;

    protected Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Permissions
        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        // Super Admin role & user
        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->adminUser->assignRole($superAdminRole);

        // Sales Executive role & user
        $salesRole = Role::firstOrCreate(['name' => UserRole::SalesExecutive->value, 'guard_name' => 'web']);
        $salesRole->givePermissionTo([
            PermissionEnum::DealsView->value,
            PermissionEnum::DealsCreate->value,
            PermissionEnum::DealsEdit->value,
            PermissionEnum::DealsDelete->value,
            PermissionEnum::ContactsView->value,
        ]);
        $this->salesUser = User::factory()->create(['role' => UserRole::SalesExecutive]);
        $this->salesUser->assignRole($salesRole);

        // Unauthorized user without deal permissions
        $this->unauthorizedUser = User::factory()->create(['role' => UserRole::CustomerSupport]);

        // Default Pipeline & Stage
        $this->pipeline = Pipeline::factory()->create(['is_default' => true]);
        $this->stage = PipelineStage::factory()->create(['pipeline_id' => $this->pipeline->id]);

        $this->contact = Contact::factory()->create();
        $this->property = Property::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_deals(): void
    {
        $response = $this->get(route('deals.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_view_deals(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('deals.index'));
        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_deals_list_with_metrics(): void
    {
        Deal::factory()->count(3)->open()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'deal_value' => 10000000,
        ]);
        Deal::factory()->count(2)->won()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'deal_value' => 25000000,
        ]);

        $response = $this->actingAs($this->salesUser)->get(route('deals.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Deals/Index')
                ->has('deals.data', 5)
                ->has('metrics')
                ->where('metrics.total_deals', 5)
                ->where('metrics.open_deals_count', 3)
                ->where('metrics.won_deals_count', 2)
            );
    }

    public function test_opportunities_route_redirects_to_deals(): void
    {
        $response = $this->actingAs($this->salesUser)->get('/opportunities');
        $response->assertRedirect(route('deals.index'));
    }

    public function test_user_can_filter_deals_by_status(): void
    {
        Deal::factory()->count(3)->open()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
        Deal::factory()->count(2)->won()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $response = $this->actingAs($this->salesUser)->get(route('deals.index', ['status' => 'won']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Deals/Index')
                ->has('deals.data', 2)
            );
    }

    public function test_authorized_user_can_view_create_deal_page(): void
    {
        $response = $this->actingAs($this->salesUser)->get(route('deals.create'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Deals/Create')
                ->has('contacts')
                ->has('properties')
                ->has('pipeline')
                ->has('users')
            );
    }

    public function test_authorized_user_can_create_deal_connecting_all_entities(): void
    {
        $lead = Lead::factory()->create(['contact_id' => $this->contact->id]);

        $payload = [
            'title' => 'Epe Waterfront 2 Plots Acquisition',
            'contact_id' => $this->contact->id,
            'lead_id' => $lead->id,
            'property_id' => $this->property->id,
            'assigned_user_id' => $this->salesUser->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'deal_value' => 35000000,
            'currency' => 'NGN',
            'expected_close_date' => Carbon::now()->addDays(30)->toDateString(),
            'notes' => 'Customer confirmed high interest during initial consultation.',
        ];

        $response = $this->actingAs($this->salesUser)->post(route('deals.store'), $payload);

        $deal = Deal::latest('id')->first();
        $this->assertNotNull($deal);

        $response->assertRedirect(route('deals.show', $deal));

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'title' => 'Epe Waterfront 2 Plots Acquisition',
            'contact_id' => $this->contact->id,
            'lead_id' => $lead->id,
            'property_id' => $this->property->id,
            'assigned_user_id' => $this->salesUser->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'deal_value' => 35000000,
            'status' => DealStatus::Open->value,
        ]);

        // Verify activity log was created
        $this->assertDatabaseHas('activities', [
            'deal_id' => $deal->id,
            'user_id' => $this->salesUser->id,
            'activity_type' => 'deal_created',
        ]);
    }

    public function test_create_deal_validation_requires_mandatory_fields(): void
    {
        $response = $this->actingAs($this->salesUser)->post(route('deals.store'), []);

        $response->assertSessionHasErrors([
            'contact_id',
            'deal_value',
        ]);
    }

    public function test_user_can_view_deal_detail(): void
    {
        $deal = Deal::factory()->create([
            'contact_id' => $this->contact->id,
            'property_id' => $this->property->id,
            'assigned_user_id' => $this->salesUser->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $response = $this->actingAs($this->salesUser)->get(route('deals.show', $deal));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Deals/Show')
                ->where('deal.id', $deal->id)
                ->where('deal.title', $deal->title)
            );
    }

    public function test_marking_deal_as_won_dispatches_deal_won_event(): void
    {
        Event::fake([DealWon::class]);

        $deal = Deal::factory()->open()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $response = $this->actingAs($this->salesUser)
            ->from(route('deals.show', $deal))
            ->post(route('deals.won', $deal));

        $response->assertRedirect(route('deals.show', $deal));

        $deal->refresh();
        $this->assertEquals(DealStatus::Won, $deal->status);
        $this->assertNotNull($deal->actual_close_date);

        Event::assertDispatched(DealWon::class, function ($event) use ($deal) {
            return $event->deal->id === $deal->id && $event->causer->id === $this->salesUser->id;
        });
    }

    public function test_marking_deal_as_lost_requires_reason_and_dispatches_deal_lost_event(): void
    {
        Event::fake([DealLost::class]);

        $deal = Deal::factory()->open()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        // Attempt without lost reason fails validation
        $failResponse = $this->actingAs($this->salesUser)->post(route('deals.lost', $deal), []);
        $failResponse->assertSessionHasErrors(['lost_reason']);

        // Submit with valid lost reason
        $response = $this->actingAs($this->salesUser)
            ->from(route('deals.show', $deal))
            ->post(route('deals.lost', $deal), [
                'lost_reason' => 'Client purchased another estate from a competitor',
            ]);

        $response->assertRedirect(route('deals.show', $deal));

        $deal->refresh();
        $this->assertEquals(DealStatus::Lost, $deal->status);
        $this->assertEquals('Client purchased another estate from a competitor', $deal->lost_reason);
        $this->assertNotNull($deal->actual_close_date);

        Event::assertDispatched(DealLost::class, function ($event) use ($deal) {
            return $event->deal->id === $deal->id
                && $event->lostReason === 'Client purchased another estate from a competitor'
                && $event->causer->id === $this->salesUser->id;
        });
    }

    public function test_reopening_deal_clears_lost_reason_and_resets_status_to_open(): void
    {
        $deal = Deal::factory()->lost('Client was unresponsive')->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $response = $this->actingAs($this->salesUser)
            ->from(route('deals.show', $deal))
            ->post(route('deals.reopen', $deal));

        $response->assertRedirect(route('deals.show', $deal));

        $deal->refresh();
        $this->assertEquals(DealStatus::Open, $deal->status);
        $this->assertNull($deal->lost_reason);
        $this->assertNull($deal->actual_close_date);
    }

    public function test_authorized_user_can_update_deal(): void
    {
        $deal = Deal::factory()->open()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'deal_value' => 20000000,
        ]);

        $response = $this->actingAs($this->salesUser)->put(route('deals.update', $deal), [
            'title' => 'Updated Deal Title',
            'deal_value' => 25000000,
            'notes' => 'Negotiation increased plot requirement.',
        ]);

        $response->assertRedirect(route('deals.show', $deal));

        $deal->refresh();
        $this->assertEquals('Updated Deal Title', $deal->title);
        $this->assertEquals(25000000, $deal->deal_value);
    }

    public function test_authorized_user_can_soft_delete_deal(): void
    {
        $deal = Deal::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $response = $this->actingAs($this->salesUser)->delete(route('deals.destroy', $deal));

        $response->assertRedirect(route('deals.index'));
        $this->assertSoftDeleted('deals', ['id' => $deal->id]);
    }

    public function test_contact_page_renders_deals_history(): void
    {
        $deal = Deal::factory()->create([
            'contact_id' => $this->contact->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'deal_value' => 45000000,
        ]);

        $response = $this->actingAs($this->salesUser)->get(route('contacts.show', $this->contact));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contacts/Show')
                ->where('contact.id', $this->contact->id)
                ->has('contact.deals', 1)
                ->where('contact.deals.0.id', $deal->id)
            );
    }
}
