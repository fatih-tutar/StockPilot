<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    lowStockProducts: { type: Array, default: () => [] },
    openQuotes: { type: Array, default: () => [] },
    activeShipments: { type: Array, default: () => [] },
    recentMovements: { type: Array, default: () => [] },
    can: { type: Object, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const formatMoney = (amount, currency = 'TRY') =>
    new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(amount ?? 0);

const movementTypeLabel = (type) => {
    const labels = {
        adjustment: 'Düzeltme',
        in: 'Giriş',
        out: 'Çıkış',
    };
    return labels[type] || type;
};
</script>

<template>
    <Head title="Panel" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Panel
                </h2>
                <p class="text-sm text-gray-600">
                    {{ user?.name }} · {{ user?.email }}
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-if="can.stock"
                        class="bg-white p-5 shadow-sm sm:rounded-lg"
                    >
                        <p class="text-sm text-gray-500">Düşük stok</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ stats.low_stock_count }}
                        </p>
                        <Link
                            :href="route('products.index')"
                            class="mt-3 inline-block text-sm text-indigo-600 hover:text-indigo-800"
                        >
                            Ürünlere git
                        </Link>
                    </div>
                    <div
                        v-if="can.clients"
                        class="bg-white p-5 shadow-sm sm:rounded-lg"
                    >
                        <p class="text-sm text-gray-500">Aktif müşteri</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ stats.active_clients_count }}
                        </p>
                        <Link
                            :href="route('clients.index')"
                            class="mt-3 inline-block text-sm text-indigo-600 hover:text-indigo-800"
                        >
                            Müşterilere git
                        </Link>
                    </div>
                    <div
                        v-if="can.quotes"
                        class="bg-white p-5 shadow-sm sm:rounded-lg"
                    >
                        <p class="text-sm text-gray-500">Açık teklif</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ stats.open_quotes_count }}
                        </p>
                        <Link
                            :href="route('quotes.index')"
                            class="mt-3 inline-block text-sm text-indigo-600 hover:text-indigo-800"
                        >
                            Tekliflere git
                        </Link>
                    </div>
                    <div
                        v-if="can.shipments"
                        class="bg-white p-5 shadow-sm sm:rounded-lg"
                    >
                        <p class="text-sm text-gray-500">Aktif sevkiyat</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ stats.active_shipments_count }}
                        </p>
                        <Link
                            :href="route('shipments.index')"
                            class="mt-3 inline-block text-sm text-indigo-600 hover:text-indigo-800"
                        >
                            Sevkiyatlara git
                        </Link>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <section
                        v-if="can.stock"
                        class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                            <h3 class="font-medium text-gray-900">Düşük stok ürünler</h3>
                            <Link
                                :href="route('products.index')"
                                class="text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                Tümü
                            </Link>
                        </div>
                        <ul class="divide-y divide-gray-100 text-sm">
                            <li
                                v-for="product in lowStockProducts"
                                :key="product.id"
                                class="flex items-center justify-between gap-3 px-4 py-3"
                            >
                                <div>
                                    <Link
                                        :href="route('products.edit', product.id)"
                                        class="font-medium text-gray-900 hover:text-indigo-700"
                                    >
                                        {{ product.name }}
                                    </Link>
                                    <p class="text-xs text-gray-500">
                                        {{ product.sku || 'SKU yok' }}
                                        · eşik {{ product.low_stock_threshold }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-amber-800">
                                        {{ product.quantity_piece }} adet
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ product.quantity_pallet }} palet
                                    </p>
                                </div>
                            </li>
                            <li
                                v-if="lowStockProducts.length === 0"
                                class="px-4 py-8 text-center text-gray-500"
                            >
                                Düşük stoklu ürün yok.
                            </li>
                        </ul>
                    </section>

                    <section
                        v-if="can.quotes"
                        class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                            <h3 class="font-medium text-gray-900">Açık teklifler</h3>
                            <Link
                                :href="route('quotes.index')"
                                class="text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                Tümü
                            </Link>
                        </div>
                        <ul class="divide-y divide-gray-100 text-sm">
                            <li
                                v-for="quote in openQuotes"
                                :key="quote.id"
                                class="flex items-center justify-between gap-3 px-4 py-3"
                            >
                                <div>
                                    <Link
                                        :href="route('quotes.edit', quote.id)"
                                        class="font-medium text-gray-900 hover:text-indigo-700"
                                    >
                                        {{ quote.number }}
                                    </Link>
                                    <p class="text-xs text-gray-500">
                                        {{ quote.client_name || '—' }}
                                        · {{ quote.status_label }}
                                        · {{ quote.quote_date }}
                                    </p>
                                </div>
                                <p class="font-medium text-gray-900">
                                    {{ formatMoney(quote.total, quote.currency) }}
                                </p>
                            </li>
                            <li
                                v-if="openQuotes.length === 0"
                                class="px-4 py-8 text-center text-gray-500"
                            >
                                Açık teklif yok.
                            </li>
                        </ul>
                    </section>

                    <section
                        v-if="can.shipments"
                        class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                            <h3 class="font-medium text-gray-900">Aktif sevkiyatlar</h3>
                            <Link
                                :href="route('shipments.index')"
                                class="text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                Tümü
                            </Link>
                        </div>
                        <ul class="divide-y divide-gray-100 text-sm">
                            <li
                                v-for="shipment in activeShipments"
                                :key="shipment.id"
                                class="flex items-center justify-between gap-3 px-4 py-3"
                            >
                                <div>
                                    <Link
                                        :href="route('shipments.edit', shipment.id)"
                                        class="font-medium text-gray-900 hover:text-indigo-700"
                                    >
                                        {{ shipment.number }}
                                    </Link>
                                    <p class="text-xs text-gray-500">
                                        {{ shipment.client_name || '—' }}
                                        · {{ shipment.status_label }}
                                        · {{ shipment.ship_date }}
                                    </p>
                                </div>
                                <p class="text-sm text-gray-700">
                                    {{ shipment.vehicle_plate || '—' }}
                                </p>
                            </li>
                            <li
                                v-if="activeShipments.length === 0"
                                class="px-4 py-8 text-center text-gray-500"
                            >
                                Aktif sevkiyat yok.
                            </li>
                        </ul>
                    </section>

                    <section
                        v-if="can.stock"
                        class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                            <h3 class="font-medium text-gray-900">Son stok hareketleri</h3>
                            <Link
                                :href="route('products.index')"
                                class="text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                Ürünler
                            </Link>
                        </div>
                        <ul class="divide-y divide-gray-100 text-sm">
                            <li
                                v-for="movement in recentMovements"
                                :key="movement.id"
                                class="px-4 py-3"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-medium text-gray-900">
                                        {{ movement.product_name || 'Ürün' }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ movementTypeLabel(movement.type) }}
                                    </p>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    adet {{ movement.quantity_piece_delta }}
                                    · palet {{ movement.quantity_pallet_delta }}
                                    <span v-if="movement.user_name">
                                        · {{ movement.user_name }}
                                    </span>
                                </p>
                            </li>
                            <li
                                v-if="recentMovements.length === 0"
                                class="px-4 py-8 text-center text-gray-500"
                            >
                                Henüz stok hareketi yok.
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
