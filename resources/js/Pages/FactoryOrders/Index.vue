<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
    totals: { type: Object, required: true },
    factories: { type: Array, required: true },
    statuses: { type: Array, required: true },
    destinations: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const search = ref(props.filters.search || '');
const factoryId = ref(props.filters.factory_id || '');
const status = ref(props.filters.status || '');
const printed = ref(props.filters.printed || '');
const receiveState = ref({});

watch(
    [search, factoryId, status, printed],
    () => {
        router.get(
            route('factory-orders.index'),
            {
                search: search.value || undefined,
                factory_id: factoryId.value || undefined,
                status: status.value || undefined,
                printed: printed.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    },
    { deep: true },
);

const kilos = (value) => {
    if (value === null || value === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('tr-TR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    }).format(Number(value));
};

const receive = (order) => {
    const state = receiveState.value[order.id] || {};
    router.post(route('factory-orders.receive', order.id), {
        destination: state.destination || 'piece',
        quantity: Number(state.quantity || order.quantity),
    }, { preserveScroll: true });
};

const createForm = useForm({
    factory_id: props.filters.factory_id || '',
});

watch(factoryId, (value) => {
    createForm.factory_id = value || '';
});

const printForm = () => {
    createForm.factory_id = factoryId.value;
    createForm.post(route('factory-order-forms.store'));
};
</script>

<template>
    <Head title="Fabrika siparişleri" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Fabrika siparişleri
                </h2>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('factory-order-forms.index', { factory_id: factoryId || undefined })"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        Form arşivi
                    </Link>
                    <Link
                        v-if="canManage"
                        :href="route('factory-orders.create', { factory_id: factoryId || undefined })"
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                    >
                        Yeni sipariş
                    </Link>
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

                <div class="grid gap-4 bg-white p-4 shadow-sm sm:rounded-lg md:grid-cols-4">
                    <div>
                        <InputLabel for="factory" value="Fabrika" />
                        <select
                            id="factory"
                            v-model="factoryId"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Tümü</option>
                            <option
                                v-for="factory in factories"
                                :key="factory.id"
                                :value="factory.id"
                            >
                                {{ factory.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="status" value="Durum" />
                        <select
                            id="status"
                            v-model="status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Tümü</option>
                            <option
                                v-for="item in statuses"
                                :key="item.value"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="printed" value="Form" />
                        <select
                            id="printed"
                            v-model="printed"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Tümü</option>
                            <option value="no">Forma girmemiş</option>
                            <option value="yes">Formda</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="search" value="Ara" />
                        <TextInput
                            id="search"
                            v-model="search"
                            class="mt-1 block w-full"
                            placeholder="Ürün veya ilgili kişi"
                        />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-700">
                    <div>
                        Toplam {{ totals.pieces }} adet, {{ kilos(totals.kilos) }} kg
                    </div>
                    <button
                        v-if="canManage"
                        type="button"
                        class="rounded-md bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="!factoryId || createForm.processing"
                        @click="printForm"
                    >
                        Forma girmeyenleri yazdır
                    </button>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Ürün</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Fabrika</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Adet</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Boy</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Palet</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Kg</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Termin</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Hazırlayan</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Durum</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="order in orders.data" :key="order.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ order.product_name }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ order.contact_name || '—' }}
                                        <span v-if="order.factory_order_form_id"> · Form {{ order.factory_order_form_id }}</span>
                                        <span v-else> · Forma girmedi</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ order.factory?.name || '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ order.quantity }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ order.length || '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ order.pallet_count }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ kilos(order.estimated_kg) }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ order.due_on || '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ order.prepared_by?.name || '—' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="order.status === 'open' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'"
                                    >
                                        {{ order.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-col items-end gap-2">
                                        <Link
                                            v-if="canManage"
                                            :href="route('factory-orders.edit', order.id)"
                                            class="text-indigo-600 hover:text-indigo-800"
                                        >
                                            Düzenle
                                        </Link>
                                        <div
                                            v-if="canManage && order.status === 'open'"
                                            class="flex items-center gap-1"
                                        >
                                            <select
                                                class="rounded-md border-gray-300 text-xs shadow-sm"
                                                :value="receiveState[order.id]?.destination || 'piece'"
                                                @change="receiveState[order.id] = { ...(receiveState[order.id] || {}), destination: $event.target.value, quantity: receiveState[order.id]?.quantity || order.quantity }"
                                            >
                                                <option
                                                    v-for="destination in destinations"
                                                    :key="destination.value"
                                                    :value="destination.value"
                                                >
                                                    {{ destination.label }}
                                                </option>
                                            </select>
                                            <input
                                                type="number"
                                                min="1"
                                                class="w-16 rounded-md border-gray-300 text-xs shadow-sm"
                                                :value="receiveState[order.id]?.quantity || order.quantity"
                                                @input="receiveState[order.id] = { ...(receiveState[order.id] || {}), destination: receiveState[order.id]?.destination || 'piece', quantity: $event.target.value }"
                                            >
                                            <button
                                                type="button"
                                                class="text-xs font-medium text-gray-800 hover:text-gray-600"
                                                @click="receive(order)"
                                            >
                                                Teslim al
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="orders.data.length === 0">
                                <td colspan="10" class="px-4 py-8 text-center text-gray-500">
                                    Sipariş bulunamadı.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="orders.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3"
                    >
                        <Link
                            v-for="link in orders.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            class="rounded border px-3 py-1 text-xs"
                            :class="link.active ? 'border-gray-800 bg-gray-800 text-white' : 'border-gray-200 text-gray-700'"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
