<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserRoleAssignmentController;
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
});

require __DIR__.'/auth.php';
