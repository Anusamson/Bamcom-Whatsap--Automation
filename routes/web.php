<?php

use App\Http\Controllers\AudienceController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\ConversationInboxController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\EmailCampaignController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\EmailSuppressionController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\EstateController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\KnowledgeRecordController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadScoringRuleController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesIntelligenceController;
use App\Http\Controllers\SequenceController;
use App\Http\Controllers\SmartListController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserRoleAssignmentController;
use App\Http\Controllers\WhatsAppSettingsController;
use Illuminate\Support\Facades\Route;

// Public Email Compliance & Unsubscribe Portal
Route::get('/email/unsubscribe', [EmailController::class, 'unsubscribe'])->name('emails.unsubscribe');
Route::post('/email/unsubscribe', [EmailController::class, 'processUnsubscribe'])->name('emails.unsubscribe.process');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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

    // CRM Contacts Module & Batch Imports (CSV & Google Sheets)
    Route::get('/contacts/import/template', [ContactImportController::class, 'downloadTemplate'])->name('contacts.import.template');
    Route::post('/contacts/import/preview', [ContactImportController::class, 'preview'])->name('contacts.import.preview');
    Route::post('/contacts/import/csv', [ContactImportController::class, 'importCsv'])->name('contacts.import.csv');
    Route::post('/contacts/import/google-sheet', [ContactImportController::class, 'importGoogleSheet'])->name('contacts.import.google-sheet');
    Route::get('/contacts/{contact}/timeline', [ContactController::class, 'timeline'])->name('contacts.timeline');
    Route::patch('/contacts/{contact}/status', [ContactController::class, 'updateStatus'])->name('contacts.status');
    Route::post('/contacts/{contact}/touchpoint', [ContactController::class, 'logTouchpoint'])->name('contacts.touchpoint');
    Route::resource('contacts', ContactController::class);

    // CRM Tasks & Reminders Subsystem
    Route::match(['post', 'patch'], '/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::match(['post', 'patch'], '/tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');
    Route::resource('tasks', TaskController::class);

    // CRM Notes & Memos
    Route::match(['post', 'patch'], '/notes/{note}/pin', [NoteController::class, 'togglePin'])->name('notes.pin');
    Route::resource('notes', NoteController::class)->only(['store', 'update', 'destroy']);

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

    // Site Inspections Management Subsystem
    Route::get('/inspections/calendar', [InspectionController::class, 'calendar'])->name('inspections.calendar');
    Route::post('/inspections/{inspection}/status', [InspectionController::class, 'updateStatus'])->name('inspections.status');
    Route::post('/inspections/{inspection}/reschedule', [InspectionController::class, 'reschedule'])->name('inspections.reschedule');
    Route::post('/inspections/{inspection}/assign', [InspectionController::class, 'assignRepresentative'])->name('inspections.assign');
    Route::resource('inspections', InspectionController::class);

    // Sales Opportunities & Deals Module
    Route::match(['post', 'patch'], '/deals/{deal}/won', [DealController::class, 'markWon'])->name('deals.won');
    Route::match(['post', 'patch'], '/deals/{deal}/lost', [DealController::class, 'markLost'])->name('deals.lost');
    Route::match(['post', 'patch'], '/deals/{deal}/reopen', [DealController::class, 'reopen'])->name('deals.reopen');
    Route::resource('deals', DealController::class);
    Route::get('/opportunities', fn () => redirect()->route('deals.index'))->name('opportunities.index');

    // Follow-Up Sequences Subsystem
    Route::post('/sequences/{sequence}/toggle', [SequenceController::class, 'toggleStatus'])->name('sequences.toggle');
    Route::post('/sequences/{sequence}/enroll', [SequenceController::class, 'enrollContact'])->name('sequences.enroll');
    Route::post('/sequences/enrollments/{enrollment}/unenroll', [SequenceController::class, 'unenrollContact'])->name('sequences.enrollments.unenroll');
    Route::post('/contacts/{contact}/opt-out', [SequenceController::class, 'optOutContact'])->name('contacts.opt-out');
    Route::post('/contacts/{contact}/opt-in', [SequenceController::class, 'optInContact'])->name('contacts.opt-in');
    Route::resource('sequences', SequenceController::class);

    // Dynamic Smart Lists Subsystem
    Route::post('/smart-lists/preview', [SmartListController::class, 'preview'])->name('smart-lists.preview');
    Route::post('/smart-lists/{smartList}/toggle-favorite', [SmartListController::class, 'toggleFavorite'])->name('smart-lists.toggle-favorite');
    Route::resource('smart-lists', SmartListController::class);

    // WhatsApp Marketing Campaigns & Audiences Subsystem
    Route::post('/audiences/preview', [AudienceController::class, 'preview'])->name('audiences.preview');
    Route::resource('audiences', AudienceController::class)->except(['create', 'edit']);
    Route::post('/campaigns/{campaign}/launch', [CampaignController::class, 'launch'])->name('campaigns.launch');
    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume');
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
    Route::resource('campaigns', CampaignController::class);

    // Executive Analytics & Performance Reports Subsystem
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    // AI Sales Intelligence Subsystem
    Route::get('/sales-intelligence', [SalesIntelligenceController::class, 'index'])->name('sales-intelligence.index');
    Route::post('/sales-intelligence/generate', [SalesIntelligenceController::class, 'generate'])->name('sales-intelligence.generate');
    Route::get('/sales-intelligence/{uuid}', [SalesIntelligenceController::class, 'show'])->name('sales-intelligence.show');

    // WhatsApp Business Platform Settings
    Route::get('/settings/whatsapp', [WhatsAppSettingsController::class, 'index'])->name('whatsapp.settings.index');
    Route::post('/settings/whatsapp/test-connection', [WhatsAppSettingsController::class, 'testConnection'])->name('whatsapp.settings.test-connection');
    Route::post('/settings/whatsapp/sync-templates', [WhatsAppSettingsController::class, 'syncTemplates'])->name('whatsapp.settings.sync-templates');

    // Realtime Notifications & Alerts Subsystem
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

    // WhatsApp Team Inbox & Real-time Messaging
    Route::get('/inbox', [ConversationInboxController::class, 'index'])->name('conversations.inbox');
    Route::get('/conversations', [ConversationInboxController::class, 'index'])->name('conversations.index');
    Route::get('/inbox/sync', [ConversationInboxController::class, 'sync'])->name('inbox.sync');
    Route::post('/inbox/{conversation}/messages', [ConversationInboxController::class, 'sendMessage'])->name('inbox.messages.store');
    Route::patch('/inbox/{conversation}/mode', [ConversationInboxController::class, 'updateMode'])->name('inbox.mode');
    Route::patch('/inbox/{conversation}/status', [ConversationInboxController::class, 'updateStatus'])->name('inbox.status');
    Route::patch('/inbox/{conversation}/assign', [ConversationInboxController::class, 'assign'])->name('inbox.assign');
    Route::post('/inbox/{conversation}/read', [ConversationInboxController::class, 'markRead'])->name('inbox.read');
    Route::post('/inbox/{conversation}/inspections', [ConversationInboxController::class, 'scheduleInspection'])->name('inbox.inspections.store');
    Route::post('/inbox/{conversation}/resume-ai', [ConversationInboxController::class, 'resumeAi'])->name('inbox.resume-ai');
    Route::post('/inbox/{conversation}/handover', [ConversationInboxController::class, 'triggerHandover'])->name('inbox.handover');

    // AI Knowledge Base Subsystem
    Route::patch('/knowledge/{knowledge}/toggle-status', [KnowledgeRecordController::class, 'toggleStatus'])->name('knowledge.toggle-status');
    Route::resource('knowledge', KnowledgeRecordController::class);

    // Configurable Lead Scoring Subsystem
    Route::patch('/settings/lead-scoring/{leadScoringRule}/toggle', [LeadScoringRuleController::class, 'toggleActive'])->name('lead-scoring.toggle');
    Route::post('/settings/lead-scoring/reset-defaults', [LeadScoringRuleController::class, 'resetDefaults'])->name('lead-scoring.reset-defaults');
    Route::resource('/settings/lead-scoring', LeadScoringRuleController::class)->parameters(['lead-scoring' => 'leadScoringRule'])->names('lead-scoring');

    // Email Infrastructure & Amazon SES
    Route::get('/emails', [EmailController::class, 'index'])->name('emails.index');
    Route::post('/emails', [EmailController::class, 'store'])->name('emails.store');
    Route::post('/emails/test', [EmailController::class, 'sendTest'])->name('emails.test');
    Route::get('/emails/stats', [EmailController::class, 'stats'])->name('emails.stats');
    Route::get('/emails/{email}', [EmailController::class, 'show'])->name('emails.show');
    Route::post('/email-templates/upload-image', [EmailTemplateController::class, 'uploadImage'])->name('email-templates.upload-image');
    Route::resource('email-templates', EmailTemplateController::class);
    Route::post('/email-templates/{emailTemplate}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');
    Route::post('/email-templates/{emailTemplate}/test', [EmailTemplateController::class, 'sendTest'])->name('email-templates.test');
    Route::resource('email-suppressions', EmailSuppressionController::class)->only(['index', 'store', 'destroy']);

    // Email Marketing Campaigns Subsystem
    Route::get('/email-campaigns/reports/attribution', [EmailCampaignController::class, 'attributionReport'])->name('email-campaigns.reports.attribution');
    Route::post('/email-campaigns/preview-audience', [EmailCampaignController::class, 'previewAudience'])->name('email-campaigns.preview-audience');
    Route::post('/email-campaigns/{emailCampaign}/send-test', [EmailCampaignController::class, 'sendTest'])->name('email-campaigns.send-test');
    Route::post('/email-campaigns/{emailCampaign}/send-now', [EmailCampaignController::class, 'sendNow'])->name('email-campaigns.send-now');
    Route::post('/email-campaigns/{emailCampaign}/schedule', [EmailCampaignController::class, 'schedule'])->name('email-campaigns.schedule');
    Route::post('/email-campaigns/{emailCampaign}/pause', [EmailCampaignController::class, 'pause'])->name('email-campaigns.pause');
    Route::post('/email-campaigns/{emailCampaign}/resume', [EmailCampaignController::class, 'resume'])->name('email-campaigns.resume');
    Route::post('/email-campaigns/{emailCampaign}/cancel', [EmailCampaignController::class, 'cancel'])->name('email-campaigns.cancel');
    Route::get('/email-campaigns/{emailCampaign}/progress', [EmailCampaignController::class, 'progress'])->name('email-campaigns.progress');
    Route::get('/email-campaigns/{emailCampaign}/attribution', [EmailCampaignController::class, 'attribution'])->name('email-campaigns.attribution');
    Route::resource('email-campaigns', EmailCampaignController::class);
});

require __DIR__.'/auth.php';
