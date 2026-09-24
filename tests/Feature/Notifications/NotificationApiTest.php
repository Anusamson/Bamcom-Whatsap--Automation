<?php

namespace Tests\Feature\Notifications;

use App\Enums\DealStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\PermissionEnum;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\UserRole;
use App\Events\DealWon;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DealActivityNotification;
use App\Notifications\HotLeadNotification;
use App\Notifications\InspectionReminderNotification;
use App\Notifications\NewAssignedLeadNotification;
use App\Notifications\OverdueTaskNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->user = User::factory()->create(['role' => UserRole::SuperAdmin, 'status' => 'active']);
        $this->user->assignRole($superAdminRole);
    }

    public function test_user_can_retrieve_notifications_list(): void
    {
        $contact = Contact::factory()->create();
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'title' => 'Maitama Villa Lead',
            'score' => 85,
        ]);

        $this->user->notify(new NewAssignedLeadNotification($lead));
        $this->user->notify(new HotLeadNotification($lead, 85));

        $response = $this->actingAs($this->user)
            ->getJson(route('notifications.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'notifications' => [
                    'data' => [
                        '*' => ['id', 'type', 'title', 'message', 'read', 'created_at', 'time_ago'],
                    ],
                ],
                'unread_count',
            ]);

        $this->assertEquals(2, $response->json('unread_count'));
    }

    public function test_user_can_filter_unread_notifications(): void
    {
        $contact = Contact::factory()->create();
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'title' => 'Guzape Inquiry',
        ]);

        $this->user->notify(new NewAssignedLeadNotification($lead));
        $this->user->notify(new HotLeadNotification($lead, 90));

        // Mark one as read
        $first = $this->user->notifications()->first();
        $first->markAsRead();

        $response = $this->actingAs($this->user)
            ->getJson(route('notifications.index', ['filter' => 'unread']));

        $response->assertOk();
        $this->assertCount(1, $response->json('notifications.data'));
        $this->assertEquals(1, $response->json('unread_count'));
    }

    public function test_unread_count_endpoint_returns_accurate_counters(): void
    {
        $contact = Contact::factory()->create();
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'title' => 'Asokoro Inquiry',
        ]);

        $this->user->notify(new NewAssignedLeadNotification($lead));

        $conversation = Conversation::create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $this->user->id,
            'unread_count' => 3,
            'channel' => 'whatsapp',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'body' => 'Hello, I want to view the duplex',
            'type' => 'text',
            'delivery_status' => MessageDeliveryStatus::Received,
            'is_read' => false,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('notifications.unread-count'));

        $response->assertOk()
            ->assertJson([
                'unread_notifications' => 1,
                'unread_messages' => 1,
                'unread_conversations' => 1,
                'my_unread_conversations' => 1,
            ]);
    }

    public function test_user_can_mark_single_notification_as_read(): void
    {
        $contact = Contact::factory()->create();
        $lead = Lead::create(['contact_id' => $contact->id, 'title' => 'Sample Lead']);
        $this->user->notify(new NewAssignedLeadNotification($lead));

        $notification = $this->user->unreadNotifications()->first();

        $response = $this->actingAs($this->user)
            ->patchJson(route('notifications.read', $notification->id));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'unread_count' => 0,
            ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $contact = Contact::factory()->create();
        $lead = Lead::create(['contact_id' => $contact->id, 'title' => 'Lead Multi']);
        $this->user->notify(new NewAssignedLeadNotification($lead));
        $this->user->notify(new HotLeadNotification($lead, 80));

        $this->assertEquals(2, $this->user->unreadNotifications()->count());

        $response = $this->actingAs($this->user)
            ->postJson(route('notifications.mark-all-read'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'unread_count' => 0,
            ]);

        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }

    public function test_inbox_sync_delta_updates_without_polling_entire_inbox(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $this->user->id,
            'channel' => 'whatsapp',
        ]);

        $msg1 = Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'body' => 'Welcome to Bamcom Real Estate',
            'type' => 'text',
            'delivery_status' => MessageDeliveryStatus::Sent,
            'is_read' => true,
            'sent_at' => now()->subMinutes(10),
        ]);

        $msg2 = Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'body' => 'Thank you, is the Katampe duplex still available?',
            'type' => 'text',
            'delivery_status' => MessageDeliveryStatus::Received,
            'is_read' => false,
            'sent_at' => now(),
        ]);

        // Client polls with after_message_id = $msg1->id
        $response = $this->actingAs($this->user)
            ->getJson(route('inbox.sync', [
                'conversation_id' => $conversation->id,
                'after_message_id' => $msg1->id,
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'new_messages',
                'conversation_meta' => ['id', 'mode', 'status', 'unread_count'],
                'counts',
                'unread_notifications',
                'timestamp',
            ]);

        // Only message 2 should be returned in delta sync
        $newMessages = $response->json('new_messages');
        $this->assertCount(1, $newMessages);
        $this->assertEquals($msg2->id, $newMessages[0]['id']);
        $this->assertEquals('Thank you, is the Katampe duplex still available?', $newMessages[0]['body']);
    }

    public function test_lead_assignment_via_observer_dispatches_notification(): void
    {
        Notification::fake();

        $agent = User::factory()->create(['role' => UserRole::SalesExecutive, 'status' => 'active']);
        $contact = Contact::factory()->create();

        // LeadObserver should hook into created
        Lead::create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $agent->id,
            'title' => 'Wuse II Terrace House',
            'score' => 45,
        ]);

        Notification::assertSentTo($agent, NewAssignedLeadNotification::class);
    }

    public function test_deal_won_event_triggers_deal_activity_notification(): void
    {
        Notification::fake();

        $agent = User::factory()->create(['role' => UserRole::SalesExecutive, 'status' => 'active']);
        $manager = User::factory()->create(['role' => UserRole::SalesManager, 'status' => 'active']);
        $contact = Contact::factory()->create();
        $pipeline = Pipeline::create(['name' => 'Commercial Pipeline']);
        $stage = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Closed Won', 'position' => 5]);

        $deal = Deal::create([
            'title' => 'Central Business District Plaza Deal',
            'contact_id' => $contact->id,
            'assigned_user_id' => $agent->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'deal_value' => 500000000,
            'status' => DealStatus::Won,
        ]);

        event(new DealWon($deal, $this->user));

        Notification::assertSentTo($agent, DealActivityNotification::class);
        Notification::assertSentTo($manager, DealActivityNotification::class);
    }

    public function test_crm_check_reminders_command_dispatches_reminders(): void
    {
        Notification::fake();

        $agent = User::factory()->create(['role' => UserRole::SalesExecutive, 'status' => 'active']);
        $contact = Contact::factory()->create();

        // 1. Upcoming inspection scheduled today
        Inspection::create([
            'contact_id' => $contact->id,
            'representative_id' => $agent->id,
            'estate_name' => 'Katame Green Hills',
            'inspection_date' => now()->toDateString(),
            'inspection_time' => '10:00 AM',
            'status' => 'scheduled',
        ]);

        // 2. Overdue task
        Task::create([
            'title' => 'Verify deeds of conveyance',
            'contact_id' => $contact->id,
            'assigned_user_id' => $agent->id,
            'due_at' => now()->subHours(5),
            'status' => TaskStatus::Pending,
            'priority' => TaskPriority::High,
            'type' => TaskType::FollowUp,
        ]);

        $this->artisan('crm:check-reminders --force')
            ->expectsOutputToContain('Completed reminder checks')
            ->assertSuccessful();

        Notification::assertSentTo($agent, InspectionReminderNotification::class);
        Notification::assertSentTo($agent, OverdueTaskNotification::class);
    }
}
