<?php

namespace Tests\Feature;

use App\Enums\UserAccessLevel;
use App\Models\User;
use App\Support\AccessRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_screen_flags_become_direct_permissions_and_a_role(): void
    {
        $user = User::factory()->create([
            'access_level' => UserAccessLevel::Supervisor,
            'access_flags' => [
                'visits' => true,
                'vehicles' => false,
            ],
        ]);

        AccessRoles::grantStoredFlags();

        $user->refresh();
        $this->assertTrue($user->hasRole('supervisor'));
        $this->assertTrue($user->hasPermissionTo('visits.view'));
        $this->assertTrue($user->hasPermissionTo('visits.manage'));
        $this->assertFalse($user->hasPermissionTo('vehicles.view'));
    }

    public function test_manager_level_receives_the_admin_role(): void
    {
        $user = User::factory()->create([
            'access_level' => UserAccessLevel::Manager,
        ]);

        AccessRoles::grantStoredFlags();

        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_roles_page_saves_permissions_and_blocks_removing_admin(): void
    {
        Permission::findOrCreate('users.manage');
        Permission::findOrCreate('visits.view');
        $admin = Role::findOrCreate('admin');
        $admin->givePermissionTo('users.manage');
        $actor = User::factory()->create();
        $actor->givePermissionTo('users.manage');

        $this->actingAs($actor)
            ->post(route('roles.store'), ['name' => 'Depo'])
            ->assertRedirect(route('roles.index'));

        $role = Role::findByName('Depo');

        $this->actingAs($actor)
            ->put(route('roles.update', $role), ['permissions' => ['visits.view']])
            ->assertRedirect(route('roles.index'));

        $this->assertTrue($role->fresh()->hasPermissionTo('visits.view'));

        $this->actingAs($actor)
            ->put(route('roles.update', $admin), ['permissions' => ['visits.view']])
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->hasPermissionTo('users.manage'));

        $this->actingAs($actor)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertNull(Role::query()->where('name', 'Depo')->first());
    }

    public function test_user_without_staff_permission_cannot_open_roles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('roles.index'))
            ->assertForbidden();
    }
}
