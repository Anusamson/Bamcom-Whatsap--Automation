<?php

namespace App\Http\Controllers;

use App\Enums\PermissionEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display a comprehensive directory of all granular permissions.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('permissions.view');

        $search = $request->query('search');

        $permissions = Permission::with('roles:id,name')
            ->when($search, function ($query, $term): void {
                $query->where('name', 'like', "%{$term}%");
            })
            ->get()
            ->map(function (Permission $permission) {
                $enumCase = PermissionEnum::tryFrom($permission->name);

                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'label' => $enumCase?->label() ?? $permission->name,
                    'group' => $enumCase?->group() ?? 'General',
                    'roles' => $permission->roles->pluck('name'),
                ];
            });

        return Inertia::render('Permissions/Index', [
            'permissions' => $permissions,
            'groupedCategories' => PermissionEnum::grouped(),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
