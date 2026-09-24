<?php

namespace Tests\Feature\SmartLists;

use App\Models\Contact;
use App\Models\SmartList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartListManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 'active',
        ]);
    }

    /**
     * User can view smart lists index page.
     */
    public function test_user_can_view_smart_lists_index(): void
    {
        SmartList::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get(route('smart-lists.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('SmartLists/Index')
            ->has('smartLists.data')
            ->has('fieldCatalog')
        );
    }

    /**
     * User can view smart list show page with dynamically queried contacts.
     */
    public function test_user_can_view_smart_list_show_with_dynamic_contacts(): void
    {
        $smartList = SmartList::factory()->create([
            'name' => 'Abuja VIP Buyers',
            'rule_groups' => [
                'logical_operator' => 'AND',
                'rules' => [
                    ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Abuja'],
                ],
            ],
        ]);

        $c1 = Contact::factory()->create(['location' => 'Wuse, Abuja']);
        $c2 = Contact::factory()->create(['location' => 'Ikeja, Lagos']);

        $response = $this->actingAs($this->user)->get(route('smart-lists.show', $smartList));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('SmartLists/Show')
            ->has('smartList')
            ->has('contacts.data', 1)
        );
    }

    /**
     * User can create a new smart list with rule groups.
     */
    public function test_user_can_create_smart_list(): void
    {
        Contact::factory()->count(2)->create(['location' => 'Lekki']);

        $payload = [
            'name' => 'Lekki High Net Worth',
            'description' => 'Targeting Lekki corridor prospects',
            'icon' => 'Flame',
            'color' => 'rose',
            'rule_groups' => [
                'logical_operator' => 'AND',
                'rules' => [
                    ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Lekki'],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('smart-lists.store'), $payload);

        $smartList = SmartList::where('name', 'Lekki High Net Worth')->first();
        $this->assertNotNull($smartList);

        $response->assertRedirect(route('smart-lists.show', $smartList));
        $this->assertEquals(2, $smartList->cached_count);
    }

    /**
     * User can update an existing smart list.
     */
    public function test_user_can_update_smart_list(): void
    {
        $smartList = SmartList::factory()->create(['name' => 'Original Name']);

        $payload = [
            'name' => 'Updated Smart List',
            'description' => 'New description',
            'icon' => 'Home',
            'color' => 'indigo',
            'rule_groups' => [
                'logical_operator' => 'OR',
                'rules' => [
                    ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Abuja'],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->put(route('smart-lists.update', $smartList), $payload);

        $response->assertSessionHas('success');
        $smartList->refresh();

        $this->assertEquals('Updated Smart List', $smartList->name);
        $this->assertEquals('OR', $smartList->rule_groups['logical_operator']);
    }

    /**
     * User can delete a smart list.
     */
    public function test_user_can_delete_smart_list(): void
    {
        $smartList = SmartList::factory()->create();

        $response = $this->actingAs($this->user)->delete(route('smart-lists.destroy', $smartList));

        $response->assertRedirect(route('smart-lists.index'));
        $this->assertSoftDeleted('smart_lists', ['id' => $smartList->id]);
    }

    /**
     * User can toggle favorite status.
     */
    public function test_user_can_toggle_favorite_status(): void
    {
        $smartList = SmartList::factory()->create(['is_favorite' => false]);

        $this->actingAs($this->user)->post(route('smart-lists.toggle-favorite', $smartList));
        $smartList->refresh();
        $this->assertTrue($smartList->is_favorite);

        $this->actingAs($this->user)->post(route('smart-lists.toggle-favorite', $smartList));
        $smartList->refresh();
        $this->assertFalse($smartList->is_favorite);
    }

    /**
     * Preview endpoint returns dynamic count and sample contacts.
     */
    public function test_preview_endpoint_returns_count_and_sample(): void
    {
        Contact::factory()->count(3)->create(['location' => 'Ikoyi']);
        Contact::factory()->count(2)->create(['location' => 'Enugu']);

        $response = $this->actingAs($this->user)->postJson(route('smart-lists.preview'), [
            'rule_groups' => [
                'logical_operator' => 'AND',
                'rules' => [
                    ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Ikoyi'],
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('count', 3);
        $response->assertJsonPath('total_count', 3);
        $response->assertJsonCount(3, 'sample');
    }
}
