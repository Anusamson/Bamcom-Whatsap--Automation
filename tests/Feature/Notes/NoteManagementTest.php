<?php

namespace Tests\Feature\Notes;

use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NoteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Contact $contact;

    protected Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->user->assignRole($superAdminRole);

        $this->contact = Contact::factory()->create();
        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
        ]);
    }

    public function test_user_can_create_a_note(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('notes.store'), [
                'contact_id' => $this->contact->id,
                'lead_id' => $this->lead->id,
                'content' => 'Customer requested an updated quote for 2 plots at Silverstone.',
                'is_pinned' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notes', [
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'user_id' => $this->user->id,
            'content' => 'Customer requested an updated quote for 2 plots at Silverstone.',
            'is_pinned' => true,
        ]);

        $this->assertDatabaseHas('activities', [
            'contact_id' => $this->contact->id,
            'activity_type' => 'note_added',
        ]);
    }

    public function test_note_creation_validates_required_content(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('notes.store'), [
                'contact_id' => $this->contact->id,
                'content' => '',
            ]);

        $response->assertSessionHasErrors(['content']);
    }

    public function test_user_can_update_a_note(): void
    {
        $note = Note::factory()->create([
            'contact_id' => $this->contact->id,
            'user_id' => $this->user->id,
            'content' => 'Original note text',
            'is_pinned' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('notes.update', $note), [
                'content' => 'Updated note content with extra details',
                'is_pinned' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'content' => 'Updated note content with extra details',
            'is_pinned' => true,
        ]);
    }

    public function test_user_can_toggle_pinned_status(): void
    {
        $note = Note::factory()->create([
            'contact_id' => $this->contact->id,
            'user_id' => $this->user->id,
            'is_pinned' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('notes.pin', $note));

        $response->assertRedirect();
        $this->assertTrue($note->fresh()->is_pinned);

        // Toggle back
        $this->actingAs($this->user)
            ->patch(route('notes.pin', $note));

        $this->assertFalse($note->fresh()->is_pinned);
    }

    public function test_user_can_delete_a_note(): void
    {
        $note = Note::factory()->create([
            'contact_id' => $this->contact->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('notes.destroy', $note));

        $response->assertRedirect();
        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    }
}
