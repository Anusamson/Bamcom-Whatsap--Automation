<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Events\PipelineStageChanged;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PipelineApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Pipeline $pipeline;

    protected PipelineStage $stage1;

    protected PipelineStage $stage2;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->user->assignRole($superAdminRole);

        $this->pipeline = Pipeline::factory()->create([
            'name' => 'Commercial Sales Pipeline',
            'is_default' => true,
        ]);

        $this->stage1 = PipelineStage::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Initial Discovery',
            'order_column' => 1,
            'probability' => 10,
        ]);

        $this->stage2 = PipelineStage::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Site Inspection Done',
            'order_column' => 2,
            'probability' => 60,
        ]);
    }

    public function test_api_can_list_pipelines(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(route('api.v1.pipelines.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'name',
                        'is_default',
                        'stages',
                    ],
                ],
            ])
            ->assertJsonPath('data.0.name', 'Commercial Sales Pipeline');
    }

    public function test_api_can_show_pipeline_with_kanban_data(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(route('api.v1.pipelines.show', $this->pipeline->uuid));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pipeline' => ['id', 'uuid', 'name'],
                    'columns' => [
                        '*' => ['stage', 'leads', 'count', 'total_value'],
                    ],
                    'summary' => ['total_leads', 'total_value', 'won_leads'],
                ],
            ]);
    }

    public function test_api_can_move_lead_stage_and_creates_activity_and_dispatches_event(): void
    {
        Event::fake([PipelineStageChanged::class]);
        Sanctum::actingAs($this->user);

        $lead = Lead::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage1->id,
        ]);

        $response = $this->postJson(route('api.v1.leads.stage.move', $lead->uuid), [
            'pipeline_stage_id' => $this->stage2->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.pipeline_stage_id', $this->stage2->id);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'pipeline_stage_id' => $this->stage2->id,
        ]);

        $this->assertDatabaseHas('activities', [
            'lead_id' => $lead->id,
            'user_id' => $this->user->id,
            'activity_type' => 'stage_change',
        ]);

        Event::assertDispatched(PipelineStageChanged::class);
    }

    public function test_unauthenticated_api_request_returns_401(): void
    {
        $response = $this->getJson(route('api.v1.pipelines.index'));

        $response->assertUnauthorized();
    }
}
