<?php

namespace App\Support;

use App\Enums\UserAccessLevel;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessRoles
{
    public static function label(string $name): string
    {
        return match ($name) {
            'admin' => 'Yönetici',
            'supervisor' => 'Yetkili',
            'staff' => 'Personel',
            default => $name,
        };
    }

    public static function roleFor(?UserAccessLevel $level): ?string
    {
        return match ($level) {
            UserAccessLevel::Manager => 'admin',
            UserAccessLevel::Supervisor => 'supervisor',
            UserAccessLevel::Staff => 'staff',
            default => null,
        };
    }

    public static function accessLevelFor(string $role): ?UserAccessLevel
    {
        return match ($role) {
            'admin' => UserAccessLevel::Manager,
            'supervisor' => UserAccessLevel::Supervisor,
            'staff' => UserAccessLevel::Staff,
            default => null,
        };
    }

    /**
     * Old screen flags that open a page, and the permissions that page uses.
     *
     * @return array<string, list<string>>
     */
    public static function flagPermissions(): array
    {
        return [
            'factory' => ['factories.view', 'factories.manage'],
            'quotes' => ['quotes.view', 'quotes.manage'],
            'orders' => ['custom_orders.view', 'custom_orders.manage', 'factory_orders.view', 'factory_orders.manage'],
            'movements' => ['stock.view', 'stock.manage'],
            'cashflow' => ['goods_flows.view', 'goods_flows.manage'],
            'sale_price' => ['catalog.view', 'catalog.manage'],
            'visits' => ['visits.view', 'visits.manage'],
            'shipments' => ['shipments.view', 'shipments.manage'],
            'vehicles' => ['vehicles.view', 'vehicles.manage'],
        ];
    }

    /**
     * @param  array<string, mixed>  $flags
     * @return list<string>
     */
    public static function permissionsForFlags(array $flags): array
    {
        $names = [];

        foreach (self::flagPermissions() as $flag => $permissions) {
            if (! filter_var($flags[$flag] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            array_push($names, ...$permissions);
        }

        return array_values(array_unique($names));
    }

    /**
     * Column gates from the old screen flags. Managers see every column.
     *
     * @return array{piece: bool, pallet: bool, alkop: bool, purchase: bool, sale: bool}
     */
    public static function visibleColumns(User $user): array
    {
        $flags = $user->access_flags ?? [];
        $seesAll = $user->hasRole('admin') || $user->access_level === UserAccessLevel::Manager;
        $enabled = function (string $key) use ($flags, $seesAll): bool {
            if ($seesAll) {
                return true;
            }

            return filter_var($flags[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        };

        return [
            'piece' => $enabled('piece_quantity'),
            'pallet' => $enabled('pallet_quantity'),
            'alkop' => $enabled('alkop'),
            'purchase' => $enabled('purchase'),
            'sale' => $enabled('sale_price'),
        ];
    }

    /**
     * @return list<array{label: string, permissions: list<array{name: string, label: string}>}>
     */
    public static function permissionGroups(): array
    {
        $labels = [
            'stock' => 'Stok',
            'clients' => 'Müşteriler',
            'quotes' => 'Teklifler',
            'custom_orders' => 'Özel siparişler',
            'visits' => 'Ziyaretler',
            'factories' => 'Fabrikalar',
            'factory_orders' => 'Fabrika siparişleri',
            'molds' => 'Kalıplar',
            'catalog' => 'Fiyat listesi',
            'shipments' => 'Sevkiyatlar',
            'vehicles' => 'Araçlar',
            'goods_flows' => 'Gelen giden',
            'work_tasks' => 'İşler',
            'leaves' => 'İzinler',
            'organizations' => 'Organizasyon',
            'users' => 'Personel ve roller',
        ];

        $actionLabels = [
            'view' => 'Görüntüleme',
            'manage' => 'Düzenleme',
        ];

        $groups = [];

        foreach (Permission::query()->orderBy('name')->get() as $permission) {
            [$prefix, $action] = array_pad(explode('.', $permission->name, 2), 2, $permission->name);
            $groups[$prefix][] = [
                'name' => $permission->name,
                'label' => $actionLabels[$action] ?? $permission->name,
            ];
        }

        $ordered = [];

        foreach ($labels as $prefix => $label) {
            if (! isset($groups[$prefix])) {
                continue;
            }

            $ordered[] = [
                'label' => $label,
                'permissions' => $groups[$prefix],
            ];
            unset($groups[$prefix]);
        }

        foreach ($groups as $prefix => $permissions) {
            $ordered[] = [
                'label' => $prefix,
                'permissions' => $permissions,
            ];
        }

        return $ordered;
    }

    public static function grantStoredFlags(): void
    {
        foreach (array_merge(...array_values(self::flagPermissions())) as $name) {
            Permission::findOrCreate($name);
        }

        Role::findOrCreate('admin');
        Role::findOrCreate('staff');
        Role::findOrCreate('supervisor');

        User::query()->orderBy('id')->each(function (User $user): void {
            $role = self::roleFor($user->access_level);

            if ($role !== null) {
                $user->syncRoles([$role]);
            }

            $permissions = self::permissionsForFlags($user->access_flags ?? []);

            if ($permissions !== []) {
                $user->givePermissionTo($permissions);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
