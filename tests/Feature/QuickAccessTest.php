<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\QuickAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QuickAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_lists_quick_access_options(): void
    {
        Permission::findOrCreate('shipments.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('shipments.manage');

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Edit')
                ->has('quickAccessOptions')
                ->where('quickAccessMax', QuickAccess::MAX_ITEMS)
                ->where('quickAccessSelected', []));
    }

    public function test_user_can_save_quick_access_pages(): void
    {
        Permission::findOrCreate('shipments.manage');
        Permission::findOrCreate('clients.view');
        $user = User::factory()->create();
        $user->givePermissionTo(['shipments.manage', 'clients.view']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.quick-access'), [
                'quick_access' => ['products', 'shipments', 'clients'],
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Hızlı erişim güncellendi.');

        $user->refresh();
        $this->assertSame(['products', 'shipments', 'clients'], $user->quick_access);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('quickAccess', 3)
                ->where('quickAccess.0.key', 'products')
                ->where('quickAccess.1.key', 'shipments')
                ->where('quickAccess.2.key', 'clients'));
    }

    public function test_quick_access_rejects_more_than_the_limit(): void
    {
        $user = User::factory()->create();
        $keys = array_slice(QuickAccess::keys(), 0, QuickAccess::MAX_ITEMS + 1);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.quick-access'), [
                'quick_access' => $keys,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('quick_access');
    }

    public function test_quick_access_drops_pages_the_user_cannot_open(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.quick-access'), [
                'quick_access' => ['products', 'shipments'],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame(['products'], $user->refresh()->quick_access);
    }
}
