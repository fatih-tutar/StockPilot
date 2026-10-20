<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Modal from '@/Components/Modal.vue';
import ShipmentOrderCard from '@/Components/ShipmentOrderCard.vue';
import ShipmentForm from '@/Pages/Shipments/Form.vue';
import { useShipmentOrders } from '@/composables/useShipmentOrders';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    lowStockProducts: { type: Array, default: () => [] },
    openQuotes: { type: Array, default: () => [] },
    shipmentTabs: { type: Array, default: () => [] },
    recentMovements: { type: Array, default: () => [] },
    upcomingWorkTasks: { type: Array, default: () => [] },
    panelPageSize: { type: Number, default: 4 },
    can: { type: Object, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const columns = computed(() => page.props.auth?.user?.columns ?? {});

const columnTone = {
    received: 'bg-sky-600 text-white',
    preparing: 'bg-amber-500 text-white',
    invoiced: 'bg-emerald-600 text-white',
};

const pageSize = computed(() => Math.max(1, Number(props.panelPageSize) || 4));

const visibleCounts = reactive({
    lowStock: pageSize.value,
    quotes: pageSize.value,
    tasks: pageSize.value,
    movements: pageSize.value,
    shipments: {},
});

const syncShipmentCounts = () => {
    for (const tab of props.shipmentTabs) {
        if (visibleCounts.shipments[tab.status] === undefined) {
            visibleCounts.shipments[tab.status] = pageSize.value;
        }
    }
};

syncShipmentCounts();

watch(
    () => props.shipmentTabs.map((tab) => tab.status).join(','),
    () => syncShipmentCounts(),
);

watch(pageSize, (size) => {
    visibleCounts.lowStock = Math.max(visibleCounts.lowStock, size);
    visibleCounts.quotes = Math.max(visibleCounts.quotes, size);
    visibleCounts.tasks = Math.max(visibleCounts.tasks, size);
    visibleCounts.movements = Math.max(visibleCounts.movements, size);
    for (const status of Object.keys(visibleCounts.shipments)) {
        visibleCounts.shipments[status] = Math.max(visibleCounts.shipments[status], size);
    }
});

const shownShipments = (tab) => (tab.shipments || []).slice(0, visibleCounts.shipments[tab.status] || pageSize.value);
const canShowMoreShipments = (tab) => (visibleCounts.shipments[tab.status] || 0) < (tab.shipments?.length || 0);
const showMoreShipments = (status) => {
    visibleCounts.shipments[status] = (visibleCounts.shipments[status] || pageSize.value) + pageSize.value;
};

const shownLowStock = computed(() => props.lowStockProducts.slice(0, visibleCounts.lowStock));
const shownQuotes = computed(() => props.openQuotes.slice(0, visibleCounts.quotes));
const shownTasks = computed(() => props.upcomingWorkTasks.slice(0, visibleCounts.tasks));
const shownMovements = computed(() => props.recentMovements.slice(0, visibleCounts.movements));

const {
    createOpen,
    createOptions,
    createError,
    editingShipment,
    deletingShipment,
    deleteProcessing,
    closeCreate,
    closeDelete,
    confirmDelete,
    runAction,
} = useShipmentOrders();

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
            <div class="mx-auto max-w-[90rem] space-y-6 sm:px-6 lg:px-8">
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
                        <p class="text-sm text-gray-500">Aktif müşteri siparişi</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ stats.active_shipments_count }}
                        </p>
                        <Link
                            :href="route('shipments.index')"
                            class="mt-3 inline-block text-sm text-indigo-600 hover:text-indigo-800"
                        >
                            Müşteri siparişlerine git
                        </Link>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <section
                        v-if="can.shipments"
                        class="overflow-hidden bg-white shadow-sm sm:rounded-lg lg:col-span-2"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-2.5">
                            <h3 class="font-medium text-gray-900">Müşteri siparişleri</h3>
                            <div class="flex items-center gap-3 text-sm">
                                <Link
                                    :href="route('shipments.archive')"
                                    class="text-indigo-600 hover:text-indigo-800"
                                >
                                    Arşiv
                                </Link>
                                <Link
                                    :href="route('shipments.index')"
                                    class="text-indigo-600 hover:text-indigo-800"
                                >
                                    Tümü
                                </Link>
                            </div>
                        </div>

                        <div class="grid gap-3 p-3 lg:grid-cols-3">
                            <div
                                v-for="tab in shipmentTabs"
                                :key="tab.status"
                                class="min-w-0 rounded-lg bg-gray-50 shadow-sm"
                            >
                                <header
                                    class="rounded-t-lg px-3 py-2.5 text-center text-sm font-semibold"
                                    :class="columnTone[tab.status] || 'bg-gray-700 text-white'"
                                >
                                    {{ tab.label }}
                                    <span class="ms-1 opacity-80">({{ tab.shipments.length }})</span>
                                </header>

                                <div class="space-y-2 p-2.5">
                                    <ShipmentOrderCard
                                        v-for="shipment in shownShipments(tab)"
                                        :key="shipment.id"
                                        :shipment="shipment"
                                        :can-manage="can.shipments_manage"
                                        @action="(key) => runAction(shipment, key)"
                                    />

                                    <div
                                        v-if="tab.shipments.length === 0"
                                        class="rounded-md border border-dashed border-gray-300 bg-white px-3 py-6 text-center text-sm text-gray-500"
                                    >
                                        Sipariş yok.
                                    </div>

                                    <button
                                        v-if="canShowMoreShipments(tab)"
                                        type="button"
                                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50"
                                        @click="showMoreShipments(tab.status)"
                                    >
                                        Devamını göster
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

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
                                v-for="product in shownLowStock"
                                :key="product.id"
                                class="flex items-center justify-between gap-3 px-4 py-2"
                            >
                                <div>
                                    <Link
                                        :href="route('products.edit', product.id)"
                                        class="font-medium text-gray-900 hover:text-indigo-700"
                                    >
                                        {{ product.name }}
                                    </Link>
                                    <p class="text-xs text-gray-500">
                                        {{ product.sku || 'Kod yok' }}
                                        · eşik {{ product.low_stock_threshold }}
                                    </p>
                                </div>
                                <div v-if="columns.piece || columns.pallet" class="text-right">
                                    <p v-if="columns.piece" class="font-medium text-amber-800">
                                        {{ product.quantity_piece }} adet
                                    </p>
                                    <p v-if="columns.pallet" class="text-xs text-gray-500">
                                        {{ product.quantity_pallet }} palet
                                    </p>
                                </div>
                            </li>
                            <li
                                v-if="lowStockProducts.length === 0"
                                class="px-4 py-4 text-center text-gray-500"
                            >
                                Düşük stoklu ürün yok.
                            </li>
                        </ul>
                        <div
                            v-if="visibleCounts.lowStock < lowStockProducts.length"
                            class="border-t border-gray-100 p-2"
                        >
                            <button
                                type="button"
                                class="w-full rounded-md px-3 py-2 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50"
                                @click="visibleCounts.lowStock += pageSize"
                            >
                                Devamını göster
                            </button>
                        </div>
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
                                v-for="quote in shownQuotes"
                                :key="quote.id"
                                class="flex items-center justify-between gap-3 px-4 py-2"
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
                                class="px-4 py-4 text-center text-gray-500"
                            >
                                Açık teklif yok.
                            </li>
                        </ul>
                        <div
                            v-if="visibleCounts.quotes < openQuotes.length"
                            class="border-t border-gray-100 p-2"
                        >
                            <button
                                type="button"
                                class="w-full rounded-md px-3 py-2 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50"
                                @click="visibleCounts.quotes += pageSize"
                            >
                                Devamını göster
                            </button>
                        </div>
                    </section>

                    <section
                        v-if="can.work_tasks"
                        class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                            <h3 class="font-medium text-gray-900">Önümüzdeki işler</h3>
                            <Link
                                :href="route('work-tasks.index')"
                                class="text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                Tümü
                            </Link>
                        </div>
                        <ul class="divide-y divide-gray-100 text-sm">
                            <li
                                v-for="task in shownTasks"
                                :key="task.id"
                                class="flex items-center justify-between gap-3 px-4 py-2"
                            >
                                <Link
                                    :href="route('work-tasks.index')"
                                    class="font-medium text-gray-900 hover:text-indigo-700"
                                >
                                    {{ task.title }}
                                </Link>
                                <p
                                    class="shrink-0 text-xs"
                                    :class="task.is_overdue ? 'font-medium text-red-700' : 'text-gray-500'"
                                >
                                    {{ task.due_on }}
                                </p>
                            </li>
                            <li
                                v-if="upcomingWorkTasks.length === 0"
                                class="px-4 py-4 text-center text-gray-500"
                            >
                                Önümüzdeki 30 günde açık iş yok.
                            </li>
                        </ul>
                        <div
                            v-if="visibleCounts.tasks < upcomingWorkTasks.length"
                            class="border-t border-gray-100 p-2"
                        >
                            <button
                                type="button"
                                class="w-full rounded-md px-3 py-2 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50"
                                @click="visibleCounts.tasks += pageSize"
                            >
                                Devamını göster
                            </button>
                        </div>
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
                                v-for="movement in shownMovements"
                                :key="movement.id"
                                class="px-4 py-2"
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
                                    <span v-if="columns.piece">adet {{ movement.quantity_piece_delta }}</span>
                                    <span v-if="columns.piece && columns.pallet"> · </span>
                                    <span v-if="columns.pallet">palet {{ movement.quantity_pallet_delta }}</span>
                                    <span v-if="movement.user_name">
                                        · {{ movement.user_name }}
                                    </span>
                                </p>
                            </li>
                            <li
                                v-if="recentMovements.length === 0"
                                class="px-4 py-4 text-center text-gray-500"
                            >
                                Henüz stok hareketi yok.
                            </li>
                        </ul>
                        <div
                            v-if="visibleCounts.movements < recentMovements.length"
                            class="border-t border-gray-100 p-2"
                        >
                            <button
                                type="button"
                                class="w-full rounded-md px-3 py-2 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50"
                                @click="visibleCounts.movements += pageSize"
                            >
                                Devamını göster
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <Modal :show="createOpen" max-width="3xl" @close="closeCreate">
            <ShipmentForm
                v-if="createOptions"
                :key="editingShipment?.id || 'new'"
                modal
                :shipment="editingShipment"
                :vehicles="createOptions.vehicles"
                :ship-type-options="createOptions.shipTypeOptions"
                :can-manage="can.shipments_manage"
                @close="closeCreate"
            />
            <div v-else class="p-6 text-sm text-gray-500">
                {{ createError || 'Yükleniyor…' }}
            </div>
        </Modal>

        <ConfirmModal
            :show="deletingShipment !== null"
            title="Siparişi silmek istediğinize emin misiniz?"
            :message="deletingShipment
                ? `${deletingShipment.client?.name || 'Bu sipariş'} silinecek. Bu işlem geri alınamaz.`
                : ''"
            :processing="deleteProcessing"
            @close="closeDelete"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
