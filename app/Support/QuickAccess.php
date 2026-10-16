<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class QuickAccess
{
    public const MAX_ITEMS = 5;

    /**
     * @return list<array{key: string, label: string, group: string, route: string, permissions: list<string>, always: bool}>
     */
    public static function catalog(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'Panel',
                'group' => 'Genel',
                'route' => 'dashboard',
                'permissions' => [],
                'always' => true,
            ],
            [
                'key' => 'products',
                'label' => 'Ürünler',
                'group' => 'Stok',
                'route' => 'products.index',
                'permissions' => [],
                'always' => true,
            ],
            [
                'key' => 'stock-activities',
                'label' => 'İşlemler',
                'group' => 'Stok',
                'route' => 'stock-activities.index',
                'permissions' => ['stock.view', 'stock.manage'],
                'always' => false,
            ],
            [
                'key' => 'count-reports',
                'label' => 'Sayım Raporu',
                'group' => 'Stok',
                'route' => 'count-reports.index',
                'permissions' => ['count_reports.view'],
                'always' => false,
            ],
            [
                'key' => 'categories',
                'label' => 'Kategoriler',
                'group' => 'Stok',
                'route' => 'categories.index',
                'permissions' => ['stock.view', 'stock.manage'],
                'always' => false,
            ],
            [
                'key' => 'molds',
                'label' => 'Kalıplar',
                'group' => 'Stok',
                'route' => 'molds.index',
                'permissions' => ['molds.view', 'molds.manage'],
                'always' => false,
            ],
            [
                'key' => 'catalog-items',
                'label' => 'Fiyat Listesi',
                'group' => 'Stok',
                'route' => 'catalog-items.index',
                'permissions' => ['catalog.view', 'catalog.manage'],
                'always' => false,
            ],
            [
                'key' => 'quotes',
                'label' => 'Teklifler',
                'group' => 'Satış',
                'route' => 'quotes.index',
                'permissions' => ['quotes.view', 'quotes.manage'],
                'always' => false,
            ],
            [
                'key' => 'offer-lists',
                'label' => 'Teklif Listesi',
                'group' => 'Satış',
                'route' => 'offer-lists.index',
                'permissions' => ['quotes.view', 'quotes.manage'],
                'always' => false,
            ],
            [
                'key' => 'clients',
                'label' => 'Müşteriler',
                'group' => 'Satış',
                'route' => 'clients.index',
                'permissions' => ['clients.view', 'clients.manage'],
                'always' => false,
            ],
            [
                'key' => 'shipments',
                'label' => 'Müşteri Siparişleri',
                'group' => 'Satış',
                'route' => 'shipments.index',
                'permissions' => ['shipments.view', 'shipments.manage'],
                'always' => false,
            ],
            [
                'key' => 'custom-orders',
                'label' => 'Özel Siparişler',
                'group' => 'Satış',
                'route' => 'custom-orders.index',
                'permissions' => ['custom_orders.view', 'custom_orders.manage'],
                'always' => false,
            ],
            [
                'key' => 'customer-visits',
                'label' => 'Ziyaretler',
                'group' => 'Satış',
                'route' => 'customer-visits.index',
                'permissions' => ['visits.view', 'visits.manage'],
                'always' => false,
            ],
            [
                'key' => 'factories',
                'label' => 'Fabrikalar',
                'group' => 'Fabrika',
                'route' => 'factories.index',
                'permissions' => ['factories.view', 'factories.manage'],
                'always' => false,
            ],
            [
                'key' => 'factory-orders',
                'label' => 'Fabrika Siparişleri',
                'group' => 'Fabrika',
                'route' => 'factory-orders.index',
                'permissions' => ['factory_orders.view', 'factory_orders.manage'],
                'always' => false,
            ],
            [
                'key' => 'vehicles',
                'label' => 'Araçlar',
                'group' => 'Sevkiyat',
                'route' => 'vehicles.index',
                'permissions' => ['vehicles.view', 'vehicles.manage'],
                'always' => false,
            ],
            [
                'key' => 'goods-flows',
                'label' => 'Gelen Giden',
                'group' => 'Günlük',
                'route' => 'goods-flows.index',
                'permissions' => ['goods_flows.view', 'goods_flows.manage'],
                'always' => false,
            ],
            [
                'key' => 'staff',
                'label' => 'Personel',
                'group' => 'Ofis',
                'route' => 'staff.index',
                'permissions' => ['users.manage'],
                'always' => false,
            ],
            [
                'key' => 'leaves',
                'label' => 'İzinler',
                'group' => 'Ofis',
                'route' => 'leaves.index',
                'permissions' => ['leaves.view', 'leaves.manage'],
                'always' => false,
            ],
            [
                'key' => 'roles',
                'label' => 'Roller',
                'group' => 'Ofis',
                'route' => 'roles.index',
                'permissions' => ['users.manage'],
                'always' => false,
            ],
            [
                'key' => 'organization',
                'label' => 'Organizasyon',
                'group' => 'Ofis',
                'route' => 'organization.index',
                'permissions' => ['organizations.view', 'organizations.manage'],
                'always' => false,
            ],
            [
                'key' => 'work-tasks',
                'label' => 'İşler',
                'group' => 'Ofis',
                'route' => 'work-tasks.index',
                'permissions' => ['work_tasks.view', 'work_tasks.manage'],
                'always' => false,
            ],
            [
                'key' => 'profile',
                'label' => 'Profil',
                'group' => 'Genel',
                'route' => 'profile.edit',
                'permissions' => [],
                'always' => true,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::catalog(), 'key');
    }

    public static function userCanAccess(User $user, array $entry): bool
    {
        if ($entry['always'] || $entry['permissions'] === []) {
            return true;
        }

        foreach ($entry['permissions'] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pages the user may pick in profile settings, grouped.
     *
     * @return list<array{group: string, items: list<array{key: string, label: string}>}>
     */
    public static function optionsFor(User $user): array
    {
        $groups = [];

        foreach (self::catalog() as $entry) {
            if (! self::userCanAccess($user, $entry)) {
                continue;
            }

            $groups[$entry['group']][] = [
                'key' => $entry['key'],
                'label' => $entry['label'],
            ];
        }

        $result = [];
        foreach ($groups as $group => $items) {
            $result[] = [
                'group' => $group,
                'items' => $items,
            ];
        }

        return $result;
    }

    /**
     * Selected quick-access links for the navigation chrome.
     *
     * @return list<array{key: string, label: string, href: string, active: bool}>
     */
    public static function linksFor(User $user): array
    {
        $selected = collect($user->quick_access ?? [])
            ->filter(fn ($key) => is_string($key))
            ->values()
            ->all();

        if ($selected === []) {
            return [];
        }

        $byKey = collect(self::catalog())->keyBy('key');
        $links = [];

        foreach ($selected as $key) {
            $entry = $byKey->get($key);
            if ($entry === null || ! self::userCanAccess($user, $entry)) {
                continue;
            }

            if (! Route::has($entry['route'])) {
                continue;
            }

            $links[] = [
                'key' => $entry['key'],
                'label' => $entry['label'],
                'href' => route($entry['route']),
                'active' => self::isActive($entry),
            ];
        }

        return $links;
    }

    /**
     * @param  array{key: string, route: string}  $entry
     */
    private static function isActive(array $entry): bool
    {
        return match ($entry['key']) {
            'dashboard' => request()->routeIs('dashboard'),
            'profile' => request()->routeIs('profile.*'),
            'factory-orders' => request()->routeIs('factory-orders.*', 'factory-order-forms.*'),
            default => request()->routeIs(str_replace('.index', '.*', $entry['route'])),
        };
    }
}
