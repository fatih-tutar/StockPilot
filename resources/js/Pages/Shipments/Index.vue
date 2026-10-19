<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Modal from '@/Components/Modal.vue';
import ShipmentOrderCard from '@/Components/ShipmentOrderCard.vue';
import TextInput from '@/Components/TextInput.vue';
import ShipmentForm from '@/Pages/Shipments/Form.vue';
import { useShipmentOrders } from '@/composables/useShipmentOrders';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    columns: { type: Array, required: true },
    filters: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const search = ref(props.filters.search || '');

const columnTone = {
    received: 'bg-sky-600 text-white',
    preparing: 'bg-amber-500 text-white',
    invoiced: 'bg-emerald-600 text-white',
};

const {
    createOpen,
    createOptions,
    createError,
    editingShipment,
    deletingShipment,
    deleteProcessing,
    openCreate,
    closeCreate,
    closeDelete,
    confirmDelete,
    runAction,
} = useShipmentOrders();

watch(search, () => {
    router.get(
        route('shipments.index'),
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
});
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
            <div class="mx-auto max-w-[90rem] space-y-6 sm:px-6 lg:px-8">
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

                <div class="grid gap-4 lg:grid-cols-3">
                    <section
                        v-for="column in columns"
                        :key="column.status"
                        class="min-w-0 rounded-lg bg-gray-50 shadow-sm"
                    >
                        <header
                            class="rounded-t-lg px-4 py-3 text-center text-base font-semibold"
                            :class="columnTone[column.status] || 'bg-gray-700 text-white'"
                        >
                            {{ column.label }}
                        </header>

                        <div class="space-y-3 p-3">
                            <ShipmentOrderCard
                                v-for="shipment in column.shipments"
                                :key="shipment.id"
                                :shipment="shipment"
                                :can-manage="canManage"
                                @action="(key) => runAction(shipment, key)"
                            />

                            <div
                                v-if="column.shipments.length === 0"
                                class="rounded-md border border-dashed border-gray-300 bg-white px-3 py-8 text-center text-sm text-gray-500"
                            >
                                Sipariş yok.
                            </div>
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
                :can-manage="canManage"
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
