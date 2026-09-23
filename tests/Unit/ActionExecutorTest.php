<?php

namespace Tests\Unit;

use App\Enums\AutomationActionType;
use App\Enums\HandoverTrigger;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Enums\UserRole;
use App\Models\AutomationAction;
use App\Models\AutomationRun;
use App\Models\AutomationWorkflow;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Notifications\AutomationNotification;
use App\Services\Automation\ActionExecutor;
use Database\Seeders\LeadScoringRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ActionExecutorTest extends TestCase
{
    use RefreshDatabase;

    protected ActionExecutor $executor;

    protected Contact $contact;

    protected Lead $lead;

    protected AutomationWorkflow $workflow;

    protected AutomationRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LeadScoringRuleSeeder::class);

        Config::set('whatsapp.base_url', 'https://graph.facebook.com');
        Config::set('whatsapp.api_version', 'v21.0');
        Config::set('whatsapp.access_token', 'mock_token');
        Config::set('whatsapp.phone_number_id', '109876543210');

        $this->executor = app(ActionExecutor::class);

        $this->contact = Contact::factory()->create([
            'first_name' => 'Chinedu',
            'phone' => '+2348031234567',
        ]);

        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'score' => 40,
        ]);

        $this->workflow = AutomationWorkflow::factory()->create();

        $this->run = AutomationRun::create([
            'workflow_id' => $this->workflow->id,
            'subject_type' => Lead::class,
            'subject_id' => $this->lead->id,
            'status' => 'running',
        ]);
    }

    public function test_send_whatsapp_action(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/109876543210/messages' => Http::response([
                'messages' => [['id' => 'wamid.HBgLMjM0ODAzMTIzNDU2NxUCMRIA']],
            ], 200),
        ]);

        $action = new AutomationAction([
            'workflow_id' => $this->workflow->id,
            'action_type' => AutomationActionType::SendWhatsApp,
            'action_config' => [
                'message' => 'Hello {{contact.first_name}}, thank you for your interest!',
            ],
        ]);

        $result = $this->executor->execute($action, $this->run);

        $this->assertTrue($result['success']);
        $this->assertEquals('SEND_WHATSAPP', $result['action_type']);
        $this->assertStringContainsString('Hello Chinedu', $result['output']['message']);
    }

    public function test_create_task_action(): void
    {
        $agent = User::factory()->create();

        $action = new AutomationAction([
            'workflow_id' => $this->workflow->id,
            'action_type' => AutomationActionType::CreateTask,
            'action_config' => [
                'title' => 'Call {{contact.first_name}} about Lekki plot',
                'priority' => TaskPriority::High->value,
                'type' => TaskType::Call->value,
                'assigned_user_id' => $agent->id,
                'due_in_hours' => 24,
            ],
        ]);

        $result = $this->executor->execute($action, $this->run);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('tasks', [
            'title' => 'Call Chinedu about Lekki plot',
            'contact_id' => $this->contact->id,
            'assigned_user_id' => $agent->id,
            'priority' => TaskPriority::High->value,
        ]);
    }

    public function test_assign_agent_action(): void
    {
        $agent = User::factory()->create([
            'status' => 'active',
            'role' => UserRole::SalesExecutive,
        ]);

        $action = new AutomationAction([
            'workflow_id' => $this->workflow->id,
            'action_type' => AutomationActionType::AssignAgent,
            'action_config' => [
                'agent_id' => $agent->id,
            ],
        ]);

        $result = $this->executor->execute($action, $this->run);

        $this->assertTrue($result['success']);
        $this->assertEquals($agent->id, $this->lead->fresh()->assigned_user_id);
        $this->assertEquals($agent->id, $this->contact->fresh()->assigned_user_id);
    }

    public function test_change_pipeline_stage_action(): void
    {
        $pipeline = Pipeline::factory()->create();
        $stage = PipelineStage::factory()->create([
            'pipeline_id' => $pipeline->id,
            'name' => 'Inspection Scheduled',
        ]);

        $action = new AutomationAction([
            'workflow_id' => $this->workflow->id,
            'action_type' => AutomationActionType::ChangePipelineStage,
            'action_config' => [
                'stage_id' => $stage->id,
            ],
        ]);

        $result = $this->executor->execute($action, $this->run);

        $this->assertTrue($result['success'], $result['error'] ?? 'unknown error');
        $this->assertEquals($stage->id, $this->lead->fresh()->pipeline_stage_id);
    }

    public function test_add_and_remove_tag_actions(): void
    {
        // 1. Add Tag
        $addAction = new AutomationAction([
            'workflow_id' => $this->workflow->id,
            'action_type' => AutomationActionType::AddTag,
            'action_config' => [
                'tag' => 'VIP Buyer',
            ],
        ]);

        $addResult = $this->executor->execute($addAction, $this->run);
        $this->assertTrue($addResult['success']);
        $this->assertTrue($this->lead->fresh()->hasTag('VIP Buyer'));

        // 2. Remove Tag
        $removeAction = new AutomationAction([
            'workflow_id' => $this->workflow->id,
            'action_type' => AutomationActionType::RemoveTag,
            'action_config' => [
                'tag' => 'VIP Buyer',
            ],
        ]);

        $removeResult = $this->executor->execute($removeAction, $this->run);
        $this->assertTrue($removeResult['success']);
        $this->assertFalse($this->lead->fresh()->hasTag('VIP Buyer'));
    }

    public function test_update_lead_score_action(): void
    {
        $action = new AutomationAction([
            'workflow_id' => $this->workflow->id,
            'action_type' => AutomationActionType::UpdateLeadScore,
            'action_config' => [
                'points' => 25,
                'reason' => 'Requested site inspection brochure',
            ],
        ]);

        $result = $this->executor->execute($action, $this->run);

        $this->assertTrue($result['success']);
        $this->assertEquals(65, $this->lead->fresh()->score);
    }

    public function test_send_notification_action(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
        ]);

        $action = new AutomationAction([
            'workflow_id' => $this->workflow->id,
            'action_type' => AutomationActionType::SendNotification,
            'action_config' => [
                'title' => 'Hot Lead Detected: {{contact.first_name}}',
                'message' => 'Client score has reached {{lead.score}}.',
                'user_id' => $admin->id,
            ],
        ]);

        $result = $this->executor->execute($action, $this->run);

        $this->assertTrue($result['success']);
        Notification::assertSentTo($admin, AutomationNotification::class);
    }

    public function test_request_human_action(): void
    {
        Conversation::factory()->create([
            'contact_id' => $this->contact->id,
        ]);

        $action = new AutomationAction([
            'workflow_id' => $this->workflow->id,
            'action_type' => AutomationActionType::RequestHuman,
            'action_config' => [
                'trigger' => HandoverTrigger::CustomerRequest->value,
                'reason' => 'Automation detected high value negotiation',
            ],
        ]);

        $result = $this->executor->execute($action, $this->run);

        $this->assertTrue($result['success']);
        $this->assertEquals('escalated', $result['output']['handover_result']['status']);
    }
}
