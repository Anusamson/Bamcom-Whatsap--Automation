<?php

namespace Tests\Unit\Notifications;

use App\Enums\DealStatus;
use App\Enums\LeadTemperature;
use App\Enums\MessageDeliveryStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\UserRole;
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
use App\Notifications\HumanHandoverNotification;
use App\Notifications\InspectionReminderNotification;
use App\Notifications\InspectionRequestNotification;
use App\Notifications\NewAssignedLeadNotification;
use App\Notifications\NewCustomerReplyNotification;
use App\Notifications\OverdueTaskNotification;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationDispatchService $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = app(NotificationDispatchService::class);
    }

    public function test_notify_lead_assigned_dispatches_notification(): void
    {
        Notification::fake();

        $agent = User::factory()->create(['role' => UserRole::SalesExecutive, 'status' => 'active']);
        $causer = User::factory()->create(['role' => UserRole::SalesManager, 'status' => 'active']);
        $contact = Contact::factory()->create();

        $lead = Lead::create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $agent->id,
            'title' => 'Asokoro Luxury Villa Inquiry',
            'score' => 60,
            'budget' => 150000000,
        ]);

        $this->dispatcher->notifyLeadAssigned($lead, $causer);

        Notification::assertSentTo($agent, NewAssignedLeadNotification::class, function ($n) use ($lead) {
            return $n->lead->id === $lead->id;
        });

        // Causer should not be notified
        Notification::assertNotSentTo($causer, NewAssignedLeadNotification::class);
    }

    public function test_notify_hot_lead_dispatches_notification(): void
    {
        Notification::fake();

        $agent = User::factory()->create(['role' => UserRole::SalesExecutive, 'status' => 'active']);
        $manager = User::factory()->create(['role' => UserRole::SalesManager, 'status' => 'active']);
        $contact = Contact::factory()->create();

        $lead = Lead::create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $agent->id,
            'title' => 'Maitama Heights Duplex',
            'score' => 90,
            'temperature' => LeadTemperature::Hot,
        ]);

        $this->dispatcher->notifyHotLead($lead, 90);

        Notification::assertSentTo($agent, HotLeadNotification::class);
        Notification::assertSentTo($manager, HotLeadNotification::class);
    }

    public function test_notify_human_handover_dispatches_notification(): void
    {
        Notification::fake();

        $agent = User::factory()->create(['role' => UserRole::SalesExecutive, 'status' => 'active']);
        $support = User::factory()->create(['role' => UserRole::CustomerSupport, 'status' => 'active']);
        $contact = Contact::factory()->create();

        $conversation = Conversation::create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $agent->id,
            'channel' => 'whatsapp',
        ]);

        $this->dispatcher->notifyHumanHandover($conversation, 'Customer Request', 'Client wants to speak to human agent');

        Notification::assertSentTo($agent, HumanHandoverNotification::class);
        Notification::assertSentTo($support, HumanHandoverNotification::class);
    }

    public function test_notify_inspection_requested_dispatches_notification(): void
    {
        Notification::fake();

        $officer = User::factory()->create(['role' => UserRole::InspectionOfficer, 'status' => 'active']);
        $rep = User::factory()->create(['role' => UserRole::SalesExecutive, 'status' => 'active']);
        $contact = Contact::factory()->create();

        $inspection = Inspection::create([
            'contact_id' => $contact->id,
            'representative_id' => $rep->id,
            'estate_name' => 'Peace Court Estate',
            'inspection_date' => now()->addDays(2),
            'inspection_time' => '11:00 AM',
            'status' => 'scheduled',
        ]);

        $this->dispatcher->notifyInspectionRequested($inspection);

        Notification::assertSentTo($rep, InspectionRequestNotification::class);
        Notification::assertSentTo($officer, InspectionRequestNotification::class);
    }

    public function test_notify_inspection_reminder_dispatches_notification(): void
    {
        Notification::fake();

        $rep = User::factory()->create(['role' => UserRole::SalesExecutive, 'status' => 'active']);
        $contact = Contact::factory()->create();

        $inspection = Inspection::create([
            'contact_id' => $contact->id,
            'representative_id' => $rep->id,
            'estate_name' => 'Sunrise Hills',
            'inspection_date' => now()->toDateString(),
            'inspection_time' => '02:00 PM',
            'status' => 'scheduled',
        ]);

        $this->dispatcher->notifyInspectionReminder($inspection, 'Today');

        Notification::assertSentTo($rep, InspectionReminderNotification::class, function ($n) {
            return $n->timing === 'Today';
        });
    }

    public function test_notify_task_overdue_dispatches_notification(): void
    {
        Notification::fake();

        $agent = User::factory()->create(['role' => UserRole::SalesExecutive, 'status' => 'active']);
        $contact = Contact::factory()->create();

        $task = Task::create([
            'title' => 'Follow up on payment receipt',
            'contact_id' => $contact->id,
            'assigned_user_id' => $agent->id,
            'due_at' => now()->subDay(),
            'status' => TaskStatus::Pending,
            'priority' => TaskPriority::High,
            'type' => TaskType::FollowUp,
        ]);

        $this->dispatcher->notifyTaskOverdue($task);

        Notification::assertSentTo($agent, OverdueTaskNotification::class, function ($n) use ($task) {
            return $n->task->id === $task->id;
        });
    }

    public function test_notify_customer_reply_dispatches_notification(): void
    {
        Notification::fake();

        $agent = User::factory()->create(['role' => UserRole::SalesExecutive, 'status' => 'active']);
        $contact = Contact::factory()->create();

        $conversation = Conversation::create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $agent->id,
            'channel' => 'whatsapp',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'sender_type' => 'contact',
            'direction' => 'inbound',
            'body' => 'I would like to schedule an inspection for Saturday',
            'type' => 'text',
            'delivery_status' => MessageDeliveryStatus::Received,
            'sent_at' => now(),
        ]);

        $this->dispatcher->notifyCustomerReply($message, $conversation);

        Notification::assertSentTo($agent, NewCustomerReplyNotification::class, function ($n) use ($message) {
            return $n->message->id === $message->id;
        });
    }

    public function test_notify_deal_activity_dispatches_notification(): void
    {
        Notification::fake();

        $agent = User::factory()->create(['role' => UserRole::SalesExecutive, 'status' => 'active']);
        $manager = User::factory()->create(['role' => UserRole::SalesManager, 'status' => 'active']);
        $contact = Contact::factory()->create();
        $pipeline = Pipeline::create(['name' => 'Residential Sales']);
        $stage = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Negotiation', 'position' => 1]);

        $deal = Deal::create([
            'title' => 'Guzape 5-Bedroom Penthouse',
            'contact_id' => $contact->id,
            'assigned_user_id' => $agent->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'deal_value' => 250000000,
            'status' => DealStatus::Won,
        ]);

        $this->dispatcher->notifyDealActivity($deal, 'won', 'Deal closed won successfully');

        Notification::assertSentTo($agent, DealActivityNotification::class);
        Notification::assertSentTo($manager, DealActivityNotification::class);
    }
}
