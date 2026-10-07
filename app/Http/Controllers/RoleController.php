<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Support\AccessRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('users.manage') ?? false, 403);

        return Inertia::render('Roles/Index', [
            'roles' => Role::query()
                ->with('permissions:id,name')
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => AccessRoles::label($role->name),
                    'permissions' => $role->permissions->pluck('name')->values(),
                    'users' => $role->users_count,
                    'locked' => $role->name === 'admin',
                ]),
            'groups' => AccessRoles::permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        Role::findOrCreate($request->validated('name'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('roles.index')
            ->with('success', 'Rol oluşturuldu.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $permissions = $request->validated('permissions');

        if ($role->name === 'admin' && ! in_array('users.manage', $permissions, true)) {
            return back()->with('error', 'Yönetici rolünden personel yetkisi kaldırılamaz.');
        }

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('roles.index')
            ->with('success', 'Rol yetkileri kaydedildi.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()?->can('users.manage') ?? false, 403);

        if ($role->name === 'admin') {
            return back()->with('error', 'Yönetici rolü silinemez.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Bu role bağlı kullanıcılar varken rol silinemez.');
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('roles.index')
            ->with('success', 'Rol silindi.');
    }
}
