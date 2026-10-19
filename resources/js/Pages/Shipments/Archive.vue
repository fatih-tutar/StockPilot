<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    shipments: { type: Object, required: true },
    filters: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const search = ref(props.filters.search || '');

watch(search, () => {
    router.get(
        route('shipments.archive'),
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
});

const restore = (shipment) => {
    router.patch(
        route('shipments.status', shipment.id),
        { status: 'invoiced' },
        { preserveScroll: true },
    );
};

const deletingShipment = ref(null);
const deleteProcessing = ref(false);

const askDelete = (shipment) => {
    deletingShipment.value = shipment;
};

const closeDelete = () => {
    if (deleteProcessing.value) {
        return;
    }

    deletingShipment.value = null;
};

const confirmDelete = () => {
    if (!deletingShipment.value) {
        return;
    }

    deleteProcessing.value = true;

    router.delete(route('shipments.destroy', deletingShipment.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            deletingShipment.value = null;
        },
    });
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
    <Head title="Sipariş arşivi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Sipariş arşivi
                </h2>
                <Link
                    :href="route('shipments.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Müşteri siparişlerine dön
                </Link>
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
                                    <a
                                        :href="route('shipments.print', shipment.id)"
                                        target="_blank"
                                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                    >
                                        Siparişi yazdır
                                    </a>
                                    <button
                                        type="button"
                                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                        @click="restore(shipment)"
                                    >
                                        Arşivden çıkar
                                    </button>
                                    <button
                                        type="button"
                                        class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                                        @click="askDelete(shipment)"
                                    >
                                        Sil
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
                            </div>
                        </div>
                    </article>

                    <div
                        v-if="shipments.data.length === 0"
                        class="col-span-full rounded-lg border border-dashed border-gray-300 bg-white px-4 py-12 text-center text-sm text-gray-500"
                    >
                        Arşivde sipariş yok.
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
