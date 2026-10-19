<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Dropdown from '@/Components/Dropdown.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import ShipmentForm from '@/Pages/Shipments/Form.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    shipments: { type: Object, required: true },
    filters: { type: Object, required: true },
    tabs: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const search = ref(props.filters.search || '');
const activeTab = computed(() => props.filters.tab);

const applyFilters = () => {
    router.get(
        route('shipments.index'),
        {
            tab: activeTab.value,
            search: search.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

watch(search, applyFilters);

const switchTab = (tab) => {
    router.get(
        route('shipments.index'),
        {
            tab,
            search: search.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const createOpen = ref(false);
const createOptions = ref(null);
const createError = ref('');
const editingShipment = ref(null);

const openCreate = async () => {
    editingShipment.value = null;
    createOpen.value = true;
    createError.value = '';

    if (createOptions.value?.vehicles && !createOptions.value.shipment) {
        return;
    }

    createOptions.value = null;

    try {
        const { data } = await axios.get(route('shipments.create-data'));
        createOptions.value = data;
    } catch {
        createError.value = 'Form yüklenemedi.';
    }
};

const openEdit = async (shipmentId) => {
    editingShipment.value = null;
    createOpen.value = true;
    createError.value = '';
    createOptions.value = null;

    try {
        const { data } = await axios.get(route('shipments.editor', shipmentId));
        createOptions.value = data;
        editingShipment.value = data.shipment;
    } catch {
        createError.value = 'Form yüklenemedi.';
    }
};

const closeCreate = () => {
    createOpen.value = false;
};

const setStatus = (shipment, status) => {
    router.patch(
        route('shipments.status', shipment.id),
        { status },
        { preserveScroll: true },
    );
};

const destroyShipment = (shipment) => {
    if (!confirm(`${shipment.client?.name || 'Bu'} siparişi silinsin mi?`)) {
        return;
    }

    router.delete(route('shipments.destroy', shipment.id), {
        preserveScroll: true,
    });
};

const menuActions = (shipment) => {
    if (shipment.status === 'received') {
        return [
            { key: 'edit', label: 'Düzenle' },
            { key: 'print', label: 'Siparişi yazdır' },
            { key: 'prepare', label: 'Sevkiyat hazır' },
            { key: 'delete', label: 'Sil', danger: true },
        ];
    }

    if (shipment.status === 'preparing') {
        return [
            { key: 'edit', label: 'Düzenle' },
            { key: 'revert', label: 'Geri al' },
            { key: 'invoice', label: 'Fatura hazır' },
            { key: 'delete', label: 'Sil', danger: true },
        ];
    }

    return [
        { key: 'edit', label: 'Düzenle' },
        { key: 'revert', label: 'Geri al' },
        { key: 'archive', label: 'Arşive gönder' },
        { key: 'delete', label: 'Sil', danger: true },
    ];
};

const runAction = (shipment, key) => {
    if (key === 'edit') {
        openEdit(shipment.id);
        return;
    }

    if (key === 'print') {
        window.open(route('shipments.print', shipment.id), '_blank');
        return;
    }

    if (key === 'prepare') {
        setStatus(shipment, 'preparing');
        return;
    }

    if (key === 'invoice') {
        setStatus(shipment, 'invoiced');
        return;
    }

    if (key === 'archive') {
        setStatus(shipment, 'archived');
        return;
    }

    if (key === 'revert') {
        setStatus(shipment, shipment.status === 'invoiced' ? 'preparing' : 'received');
        return;
    }

    if (key === 'delete') {
        destroyShipment(shipment);
    }
};

const money = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${Number(value).toLocaleString('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} TL`;
};
</script>

<template>
    <Head title="Müşteri siparişleri" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Müşteri siparişleri
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('shipments.archive')"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
                    >
                        Arşiv
                    </Link>
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                        @click="openCreate"
                    >
                        Yeni müşteri siparişi
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    v-if="flashSuccess"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                >
                    {{ flashSuccess }}
                </div>
                <div
                    v-if="flashError"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {{ flashError }}
                </div>

                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <TextInput
                        v-model="search"
                        class="block w-full"
                        placeholder="Müşteri, ürün veya araç ara"
                    />
                </div>

                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex flex-wrap gap-2" aria-label="Sipariş sekmeleri">
                        <button
                            v-for="tab in tabs"
                            :key="tab.value"
                            type="button"
                            class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition"
                            :class="
                                activeTab === tab.value
                                    ? 'border-indigo-600 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                            "
                            @click="switchTab(tab.value)"
                        >
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="shipment in shipments.data"
                        :key="shipment.id"
                        class="flex flex-col rounded-lg border border-gray-200 bg-white shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-semibold text-gray-900">
                                    {{ shipment.client?.name || 'Müşteri yok' }}
                                </h3>
                                <p class="mt-1 text-xs text-gray-500">{{ shipment.ship_date }}</p>
                            </div>
                            <Dropdown v-if="canManage" align="right" width="48">
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                        aria-label="İşlemler"
                                    >
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M10 4.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM10 11.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM10 18.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z" />
                                        </svg>
                                    </button>
                                </template>
                                <template #content>
                                    <button
                                        v-for="action in menuActions(shipment)"
                                        :key="action.key"
                                        type="button"
                                        class="block w-full px-4 py-2 text-left text-sm"
                                        :class="action.danger ? 'text-red-600 hover:bg-red-50' : 'text-gray-700 hover:bg-gray-100'"
                                        @click="runAction(shipment, action.key)"
                                    >
                                        {{ action.label }}
                                    </button>
                                </template>
                            </Dropdown>
                        </div>

                        <div class="flex-1 space-y-3 px-4 py-3 text-sm text-gray-700">
                            <div class="space-y-1">
                                <div
                                    v-for="item in shipment.items"
                                    :key="item.id"
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-gray-900">
                                            {{ item.product_name || 'Ürün yok' }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ item.kind || '—' }} · {{ item.quantity_piece }} adet
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-xs text-gray-600">{{ money(item.unit_price) }}</p>
                                </div>
                                <p v-if="shipment.items.length === 0" class="text-gray-500">Ürün yok.</p>
                            </div>

                            <div class="space-y-1 border-t border-gray-100 pt-3 text-xs text-gray-500">
                                <p v-if="shipment.creator_name">Oluşturan: {{ shipment.creator_name }}</p>
                                <p v-if="shipment.ship_type">Sevk tipi: {{ shipment.ship_type }}</p>
                                <p v-if="shipment.vehicle">Araç: {{ shipment.vehicle }}</p>
                                <p v-if="shipment.notes">Açıklama: {{ shipment.notes }}</p>
                            </div>
                        </div>
                    </article>

                    <div
                        v-if="shipments.data.length === 0"
                        class="col-span-full rounded-lg border border-dashed border-gray-300 bg-white px-4 py-12 text-center text-sm text-gray-500"
                    >
                        Bu sekmede sipariş yok.
                    </div>
                </div>

                <div
                    v-if="shipments.links?.length > 3"
                    class="flex flex-wrap gap-2"
                >
                    <Link
                        v-for="link in shipments.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded border px-3 py-1 text-xs"
                        :class="
                            link.active
                                ? 'border-gray-800 bg-gray-800 text-white'
                                : 'border-gray-200 text-gray-700'
                        "
                        v-html="link.label"
                        preserve-scroll
                    />
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
                :can-manage="canManage"
                @close="closeCreate"
            />
            <div v-else class="p-6 text-sm text-gray-500">
                {{ createError || 'Yükleniyor…' }}
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
