<?php

namespace App\Http\Controllers;

use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of roles with user counts and permission statistics.
     */
    public function index(): Response
    {
        Gate::authorize('roles.view');

        $roles = Role::withCount(['users', 'permissions'])
            ->with('permissions:id,name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'permissions' => $role->permissions->pluck('name'),
                'is_system' => in_array($role->name, [UserRole::SuperAdmin->value, UserRole::Admin->value], true),
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'groupedPermissions' => PermissionEnum::grouped(),
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): Response
    {
        Gate::authorize('roles.create');

        return Inertia::render('Roles/Create', [
            'groupedPermissions' => PermissionEnum::grouped(),
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('roles.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => trim($validated['name']),
            'guard_name' => 'web',
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->route('roles.index')
            ->with('success', "Role '{$role->name}' created successfully.");
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role): Response
    {
        Gate::authorize('roles.edit');

        return Inertia::render('Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name'),
                'is_system' => $role->name === UserRole::SuperAdmin->value,
            ],
            'groupedPermissions' => PermissionEnum::grouped(),
        ]);
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('roles.edit');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        // Protect Super Admin role name from modification
        if ($role->name === UserRole::SuperAdmin->value && $validated['name'] !== UserRole::SuperAdmin->value) {
            return back()->with('error', 'The Super Admin role name cannot be modified.');
        }

        $role->name = trim($validated['name']);
        $role->save();

        if ($role->name === UserRole::SuperAdmin->value) {
            // Super Admin always maintains all permissions
            $role->syncPermissions(Permission::all());
        } else {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        return redirect()->route('roles.index')
            ->with('success', "Role '{$role->name}' updated successfully.");
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('roles.delete');

        if (in_array($role->name, [UserRole::SuperAdmin->value, UserRole::Admin->value], true)) {
            return back()->with('error', "System core role '{$role->name}' cannot be deleted.");
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', "Cannot delete role '{$role->name}' because users are currently assigned to it.");
        }

        $roleName = $role->name;
        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', "Role '{$roleName}' deleted successfully.");
    }
}
