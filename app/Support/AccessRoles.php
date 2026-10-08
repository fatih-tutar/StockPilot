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
     * Screen flags stored as permission rows. Office is a person column, not a permission.
     *
     * @return array<string, list<string>>
     */
    public static function flagPermissions(): array
    {
        return [
            'purchase' => ['columns.purchase'],
            'factory' => ['factories.view', 'factories.manage'],
            'quotes' => ['quotes.view', 'quotes.manage'],
            'orders' => ['custom_orders.view', 'custom_orders.manage', 'factory_orders.view', 'factory_orders.manage'],
            'editing' => ['records.edit'],
            'movements' => ['stock.view', 'stock.manage'],
            'cashflow' => ['goods_flows.view', 'goods_flows.manage'],
            'sale_price' => ['catalog.view', 'catalog.manage'],
            'totals' => ['totals.view'],
            'visits' => ['visits.view', 'visits.manage'],
            'shipments' => ['shipments.view', 'shipments.manage'],
            'piece_quantity' => ['columns.piece'],
            'pallet_quantity' => ['columns.pallet'],
            'alkop' => ['columns.alkop'],
            'vehicles' => ['vehicles.view', 'vehicles.manage'],
            'count_report' => ['count_reports.view'],
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
     * Column gates stored as permissions. Managers see every column.
     *
     * @return array{piece: bool, pallet: bool, alkop: bool, purchase: bool, sale: bool}
     */
    public static function visibleColumns(User $user): array
    {
        $seesAll = $user->hasRole('admin') || $user->access_level === UserAccessLevel::Manager;
        $enabled = function (string $permission) use ($user, $seesAll): bool {
            if ($seesAll) {
                return true;
            }

            return $user->hasPermissionTo($permission);
        };

        return [
            'piece' => $enabled('columns.piece'),
            'pallet' => $enabled('columns.pallet'),
            'alkop' => $enabled('columns.alkop'),
            'purchase' => $enabled('columns.purchase'),
            'sale' => $enabled('catalog.view'),
        ];
    }

    /**
     * Checkbox values for the staff form, from direct permissions and the office column.
     *
     * @return array<string, bool>
     */
    public static function storedFlags(User $user): array
    {
        $direct = $user->getDirectPermissions()->pluck('name')->flip();
        $flags = [];

        foreach (StaffAccess::keys() as $key) {
            if ($key === 'office') {
                $flags[$key] = (bool) $user->in_office;

                continue;
            }

            $permissions = self::flagPermissions()[$key] ?? [];
            $flags[$key] = $permissions !== [] && collect($permissions)->every(
                fn (string $name): bool => $direct->has($name),
            );
        }

        return $flags;
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    public static function syncFlagPermissions(User $user, array $flags): void
    {
        foreach (array_merge(...array_values(self::flagPermissions())) as $name) {
            Permission::findOrCreate($name);
        }

        $user->syncPermissions(self::permissionsForFlags($flags));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
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
            'count_reports' => 'Sayım raporu',
            'columns' => 'Sütunlar',
            'totals' => 'Toplamlar',
            'records' => 'Düzenleme',
            'goods_flows' => 'Gelen giden',
            'work_tasks' => 'İşler',
            'leaves' => 'İzinler',
            'organizations' => 'Organizasyon',
            'users' => 'Personel ve roller',
        ];

        $actionLabels = [
            'view' => 'Görüntüleme',
            'manage' => 'Düzenleme',
            'piece' => 'Adet',
            'pallet' => 'Palet',
            'alkop' => 'Alkop',
            'purchase' => 'Alış',
            'edit' => 'Düzenleme',
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

    /**
     * Assign each person's role from the stored access level.
     */
    public static function grantStoredFlags(): void
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('staff');
        Role::findOrCreate('supervisor');

        User::query()->orderBy('id')->each(function (User $user): void {
            $role = self::roleFor($user->access_level);

            if ($role !== null) {
                $user->syncRoles([$role]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
