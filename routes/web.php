<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationInboxController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\EstateController;
use App\Http\Controllers\KnowledgeRecordController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserRoleAssignmentController;
use App\Http\Controllers\WhatsAppSettingsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Access Control: Roles & Permissions
    Route::resource('roles', RoleController::class);
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::patch('/users/{user}/roles', [UserRoleAssignmentController::class, 'update'])->name('users.roles.update');

    // Users & Teams Module
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::patch('/users/{user}/team', [UserController::class, 'assignTeam'])->name('users.assign-team');
    Route::resource('users', UserController::class);

    Route::post('/teams/{team}/members', [TeamController::class, 'assignMembers'])->name('teams.members.assign');
    Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.members.remove');
    Route::resource('teams', TeamController::class);

    // CRM Contacts Module
    Route::patch('/contacts/{contact}/status', [ContactController::class, 'updateStatus'])->name('contacts.status');
    Route::post('/contacts/{contact}/touchpoint', [ContactController::class, 'logTouchpoint'])->name('contacts.touchpoint');
    Route::resource('contacts', ContactController::class);

    // Sales Leads Module
    Route::match(['post', 'patch'], '/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status');
    Route::match(['post', 'patch'], '/leads/{lead}/assign', [LeadController::class, 'assign'])->name('leads.assign');
    Route::match(['post', 'patch'], '/leads/{lead}/stage', [PipelineController::class, 'moveStage'])->name('leads.stage.move');
    Route::resource('leads', LeadController::class);

    // Sales Pipelines & Kanban Board
    Route::get('/pipelines', [PipelineController::class, 'index'])->name('pipelines.index');

    // Property Management & Estates
    Route::resource('estates', EstateController::class);
    Route::resource('properties', PropertyController::class);

    // Sales Opportunities & Deals Module
    Route::match(['post', 'patch'], '/deals/{deal}/won', [DealController::class, 'markWon'])->name('deals.won');
    Route::match(['post', 'patch'], '/deals/{deal}/lost', [DealController::class, 'markLost'])->name('deals.lost');
    Route::match(['post', 'patch'], '/deals/{deal}/reopen', [DealController::class, 'reopen'])->name('deals.reopen');
    Route::resource('deals', DealController::class);
    Route::get('/opportunities', fn () => redirect()->route('deals.index'))->name('opportunities.index');

    // WhatsApp Business Platform Settings
    Route::get('/settings/whatsapp', [WhatsAppSettingsController::class, 'index'])->name('whatsapp.settings.index');
    Route::post('/settings/whatsapp/test-connection', [WhatsAppSettingsController::class, 'testConnection'])->name('whatsapp.settings.test-connection');
    Route::post('/settings/whatsapp/sync-templates', [WhatsAppSettingsController::class, 'syncTemplates'])->name('whatsapp.settings.sync-templates');

    // WhatsApp Team Inbox & Real-time Messaging
    Route::get('/inbox', [ConversationInboxController::class, 'index'])->name('conversations.inbox');
    Route::get('/conversations', [ConversationInboxController::class, 'index'])->name('conversations.index');
    Route::post('/inbox/{conversation}/messages', [ConversationInboxController::class, 'sendMessage'])->name('inbox.messages.store');
    Route::patch('/inbox/{conversation}/mode', [ConversationInboxController::class, 'updateMode'])->name('inbox.mode');
    Route::patch('/inbox/{conversation}/status', [ConversationInboxController::class, 'updateStatus'])->name('inbox.status');
    Route::patch('/inbox/{conversation}/assign', [ConversationInboxController::class, 'assign'])->name('inbox.assign');
    Route::post('/inbox/{conversation}/read', [ConversationInboxController::class, 'markRead'])->name('inbox.read');
    Route::post('/inbox/{conversation}/inspections', [ConversationInboxController::class, 'scheduleInspection'])->name('inbox.inspections.store');

    // AI Knowledge Base Subsystem
    Route::patch('/knowledge/{knowledge}/toggle-status', [KnowledgeRecordController::class, 'toggleStatus'])->name('knowledge.toggle-status');
    Route::resource('knowledge', KnowledgeRecordController::class);
});

require __DIR__.'/auth.php';
