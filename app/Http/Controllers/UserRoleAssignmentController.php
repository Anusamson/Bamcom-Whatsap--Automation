<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserRoleAssignmentController extends Controller
{
    /**
     * Update roles assigned to a user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('users.edit');

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->syncRoles($validated['roles']);

        return back()->with('success', "Roles updated for user {$user->name}.");
    }
}
