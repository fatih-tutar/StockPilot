<script setup>
import { computed, ref, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import NavMenu from '@/Components/NavMenu.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const page = usePage();

watch(
    () => page.url,
    () => {
        showingNavigationDropdown.value = false;
    },
);

const menus = computed(() => {
    const permissions = page.props.auth?.user?.permissions || [];
    const allowed = (...names) => names.some((name) => permissions.includes(name));

    return [
        {
            label: 'Stok',
            items: [
                {
                    label: 'Ürünler',
                    href: route('products.index'),
                    active: route().current('products.*'),
                    visible: allowed('stock.view', 'stock.manage'),
                },
                {
                    label: 'Kategoriler',
                    href: route('categories.index'),
                    active: route().current('categories.*'),
                    visible: allowed('stock.view', 'stock.manage'),
                },
                {
                    label: 'Kalıplar',
                    href: route('molds.index'),
                    active: route().current('molds.*'),
                    visible: allowed('molds.view', 'molds.manage'),
                },
                {
                    label: 'Fiyat listesi',
                    href: route('catalog-items.index'),
                    active: route().current('catalog-items.*'),
                    visible: allowed('catalog.view', 'catalog.manage'),
                },
            ],
        },
        {
            label: 'Satış',
            items: [
                {
                    label: 'Müşteriler',
                    href: route('clients.index'),
                    active: route().current('clients.*'),
                    visible: allowed('clients.view', 'clients.manage'),
                },
                {
                    label: 'Teklifler',
                    href: route('quotes.index'),
                    active: route().current('quotes.*'),
                    visible: allowed('quotes.view', 'quotes.manage'),
                },
                {
                    label: 'Özel siparişler',
                    href: route('custom-orders.index'),
                    active: route().current('custom-orders.*'),
                    visible: allowed('custom_orders.view', 'custom_orders.manage'),
                },
                {
                    label: 'Ziyaretler',
                    href: route('customer-visits.index'),
                    active: route().current('customer-visits.*'),
                    visible: allowed('visits.view', 'visits.manage'),
                },
            ],
        },
        {
            label: 'Fabrika',
            items: [
                {
                    label: 'Fabrikalar',
                    href: route('factories.index'),
                    active: route().current('factories.*'),
                    visible: allowed('factories.view', 'factories.manage'),
                },
                {
                    label: 'Fabrika siparişleri',
                    href: route('factory-orders.index'),
                    active: route().current('factory-orders.*') || route().current('factory-order-forms.*'),
                    visible: allowed('factory_orders.view', 'factory_orders.manage'),
                },
            ],
        },
        {
            label: 'Sevkiyat',
            items: [
                {
                    label: 'Sevkiyatlar',
                    href: route('shipments.index'),
                    active: route().current('shipments.*'),
                    visible: allowed('shipments.view', 'shipments.manage'),
                },
                {
                    label: 'Araçlar',
                    href: route('vehicles.index'),
                    active: route().current('vehicles.*'),
                    visible: allowed('vehicles.view', 'vehicles.manage'),
                },
            ],
        },
        {
            label: 'Günlük',
            items: [
                {
                    label: 'Gelen giden',
                    href: route('goods-flows.index'),
                    active: route().current('goods-flows.*'),
                    visible: allowed('goods_flows.view', 'goods_flows.manage'),
                },
            ],
        },
        {
            label: 'Ofis',
            items: [
                {
                    label: 'İşler',
                    href: route('work-tasks.index'),
                    active: route().current('work-tasks.*'),
                    visible: allowed('work_tasks.view', 'work_tasks.manage'),
                },
                {
                    label: 'Personel',
                    href: route('staff.index'),
                    active: route().current('staff.*'),
                    visible: allowed('users.manage'),
                },
                {
                    label: 'İzinler',
                    href: route('leaves.index'),
                    active: route().current('leaves.*'),
                    visible: allowed('leaves.view', 'leaves.manage'),
                },
                {
                    label: 'Organizasyon',
                    href: route('organization.index'),
                    active: route().current('organization.*'),
                    visible: allowed('organizations.view', 'organizations.manage'),
                },
            ],
        },
    ]
        .map((menu) => ({
            ...menu,
            items: menu.items.filter((item) => item.visible),
        }))
        .filter((menu) => menu.items.length > 0);
});
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white print:hidden">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 items-stretch justify-between gap-4">
                        <div class="flex min-w-0 flex-1 items-stretch gap-6">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo class="block h-9 w-9" />
                                </Link>
                            </div>

                            <div class="hidden min-w-0 flex-1 items-stretch gap-x-5 overflow-visible xl:flex">
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Panel
                                </NavLink>
                                <NavMenu
                                    v-for="menu in menus"
                                    :key="menu.label"
                                    :label="menu.label"
                                    :active="menu.items.some((item) => item.active)"
                                    :items="menu.items"
                                />
                            </div>
                        </div>

                        <div class="hidden shrink-0 xl:ms-2 xl:flex xl:items-center">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center whitespace-nowrap rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}
                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">
                                            Profil
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Çıkış
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="-me-2 flex items-center xl:hidden">
                            <button
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="xl:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Panel
                        </ResponsiveNavLink>
                        <template v-for="menu in menus" :key="menu.label">
                            <div class="px-4 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                {{ menu.label }}
                            </div>
                            <ResponsiveNavLink
                                v-for="item in menu.items"
                                :key="item.href"
                                :href="item.href"
                                :active="item.active"
                                indent
                            >
                                {{ item.label }}
                            </ResponsiveNavLink>
                        </template>
                    </div>

                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profil
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Çıkış
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header v-if="$slots.header" class="bg-white shadow">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
