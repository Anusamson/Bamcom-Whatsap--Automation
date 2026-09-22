<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DealController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\PipelineController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Bamcom AI CRM
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {
    // Health & System Info
    Route::get('/health', [HealthController::class, 'index'])->name('api.v1.health');
    Route::get('/version', [HealthController::class, 'version'])->name('api.v1.version');

    // Public Authentication
    Route::post('/auth/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // Property Management & Authoritative AI Grounding Endpoints
    Route::get('/properties/ai-context', [PropertyController::class, 'aiContext'])->name('api.v1.properties.ai-context');
    Route::post('/properties/ai-query', [PropertyController::class, 'aiQuery'])->name('api.v1.properties.ai-query');
    Route::get('/properties', [PropertyController::class, 'index'])->name('api.v1.properties.index');
    Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('api.v1.properties.show');

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        // Users Management
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('api.v1.users.status');
        Route::patch('/users/{user}/team', [UserController::class, 'assignTeam'])->name('api.v1.users.team');
        Route::apiResource('users', UserController::class)->names('api.v1.users');

        // Teams Management & Sales Assignments
        Route::get('/teams/sales', [TeamController::class, 'sales'])->name('api.v1.teams.sales');
        Route::post('/teams/{team}/members', [TeamController::class, 'assignMembers'])->name('api.v1.teams.members.assign');
        Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('api.v1.teams.members.remove');
        Route::apiResource('teams', TeamController::class)->names('api.v1.teams');

        // Contacts Management
        Route::apiResource('contacts', ContactController::class)->names('api.v1.contacts');

        // Sales Leads Management
        Route::match(['post', 'patch'], '/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('api.v1.leads.status');
        Route::match(['post', 'patch'], '/leads/{lead}/assign', [LeadController::class, 'assign'])->name('api.v1.leads.assign');
        Route::match(['post', 'patch'], '/leads/{lead}/stage', [PipelineController::class, 'moveStage'])->name('api.v1.leads.stage.move');
        Route::apiResource('leads', LeadController::class)->names('api.v1.leads');

        // Sales Pipelines & Stages
        Route::get('/pipelines', [PipelineController::class, 'index'])->name('api.v1.pipelines.index');
        Route::get('/pipelines/{pipeline}', [PipelineController::class, 'show'])->name('api.v1.pipelines.show');

        // Opportunities & Deals Management
        Route::match(['post', 'patch'], '/deals/{deal}/won', [DealController::class, 'markWon'])->name('api.v1.deals.won');
        Route::match(['post', 'patch'], '/deals/{deal}/lost', [DealController::class, 'markLost'])->name('api.v1.deals.lost');
        Route::apiResource('deals', DealController::class)->names('api.v1.deals');
    });
});
