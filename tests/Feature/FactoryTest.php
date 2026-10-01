<?php

namespace Tests\Feature;

use App\Models\Factory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function userWithFactoriesPermission(): User
    {
        foreach (['factories.view', 'factories.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['factories.view', 'factories.manage']);

        return $user;
    }

    public function test_factories_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('factories.index'))
            ->assertForbidden();
    }

    public function test_factories_index_lists_active_factories(): void
    {
        $user = $this->userWithFactoriesPermission();
        $factory = Factory::factory()->create(['name' => 'Bor Alüminyum']);
        Factory::factory()->create(['name' => 'Silinmiş', 'deleted_at' => now()]);

        $this->actingAs($user)
            ->get(route('factories.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Factories/Index')
                ->has('factories.data', 1)
                ->where('factories.data.0.name', $factory->name));
    }

    public function test_factory_can_be_created(): void
    {
        $user = $this->userWithFactoriesPermission();

        $this->actingAs($user)
            ->post(route('factories.store'), [
                'name' => 'Sistem Alüminyum',
                'phone' => '0212 000 00 00',
                'email' => 'info@example.com',
                'address' => 'Esenyurt',
                'labor_cost' => 1400,
                'fine_labor_cost' => 0,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('factories', [
            'name' => 'Sistem Alüminyum',
            'labor_cost' => 1400,
        ]);
    }
}
