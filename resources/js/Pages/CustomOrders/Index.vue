<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
    statusOptions: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'open');

watch([search, status], () => {
    router.get(
        route('custom-orders.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
});

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-');
    return `${day}.${month}.${year}`;
};

const isPast = (value) => {
    if (!value) {
        return false;
    }

    return value < new Date().toISOString().slice(0, 10);
};

const formatQuantity = (value) =>
    new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 3 }).format(value || 0);

const formatPrice = (value) =>
    new Intl.NumberFormat('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);

const closeOrder = (order) => {
    router.post(route('custom-orders.close', order.id), {}, { preserveScroll: true });
};

const reopenOrder = (order) => {
    router.post(route('custom-orders.reopen', order.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Özel siparişler" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Özel siparişler
                </h2>
                <Link
                    v-if="canManage"
                    :href="route('custom-orders.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                >
                    Yeni sipariş
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

                <div class="flex flex-col gap-4 bg-white p-4 shadow-sm sm:flex-row sm:rounded-lg">
                    <div class="flex-1">
                        <InputLabel for="search" value="Ara" />
                        <TextInput
                            id="search"
                            v-model="search"
                            class="mt-1 block w-full"
                            placeholder="Müşteri, ürün veya not"
                        />
                    </div>
                    <div>
                        <InputLabel for="status" value="Durum" />
                        <select
                            id="status"
                            v-model="status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div v-if="orders.data.length === 0" class="bg-white px-4 py-8 text-center text-sm text-gray-500 shadow-sm sm:rounded-lg">
                    Sipariş bulunamadı.
                </div>

                <article
                    v-for="order in orders.data"
                    :key="order.id"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="font-medium text-gray-900">
                                {{ order.client?.name || 'Müşteri yok' }}
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                {{ formatDate(order.ordered_on) }}
                                · {{ order.delivery_label }}
                                · {{ order.status_label }}
                            </div>
                            <p v-if="order.notes" class="mt-2 text-sm text-gray-700">
                                {{ order.notes }}
                            </p>
                        </div>
                        <div class="flex gap-3 text-sm">
                            <Link
                                :href="route('custom-orders.edit', order.id)"
                                class="text-indigo-600 hover:text-indigo-800"
                            >
                                Düzenle
                            </Link>
                            <button
                                v-if="canManage && order.status === 'open'"
                                type="button"
                                class="text-gray-700 hover:text-gray-900"
                                @click="closeOrder(order)"
                            >
                                Kapat
                            </button>
                            <button
                                v-if="canManage && order.status === 'closed'"
                                type="button"
                                class="text-gray-700 hover:text-gray-900"
                                @click="reopenOrder(order)"
                            >
                                Yeniden aç
                            </button>
                        </div>
                    </div>

                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Ürün</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Fabrika</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Adet</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Fiyat</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Termin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="item in order.items" :key="item.id">
                                <td class="px-4 py-2">
                                    <div class="text-gray-900">{{ item.product_name }}</div>
                                    <div v-if="item.length" class="text-xs text-gray-500">
                                        {{ item.length }}
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-gray-700">
                                    {{ item.factory_name || '—' }}
                                </td>
                                <td class="px-4 py-2 text-right text-gray-700">
                                    {{ formatQuantity(item.quantity) }}
                                </td>
                                <td class="px-4 py-2 text-right text-gray-700">
                                    {{ formatPrice(item.unit_price) }}
                                </td>
                                <td
                                    class="px-4 py-2"
                                    :class="isPast(item.due_on) && order.status === 'open' ? 'text-red-700' : 'text-gray-700'"
                                >
                                    {{ formatDate(item.due_on) }}
                                </td>
                            </tr>
                            <tr v-if="order.items.length === 0">
                                <td colspan="5" class="px-4 py-3 text-gray-500">
                                    Kalem yok.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </article>

                <div
                    v-if="orders.links?.length > 3"
                    class="flex flex-wrap gap-2"
                >
                    <Link
                        v-for="link in orders.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded border px-3 py-1 text-xs"
                        :class="
                            link.active
                                ? 'border-gray-800 bg-gray-800 text-white'
                                : 'border-gray-200 bg-white text-gray-700'
                        "
                        v-html="link.label"
                        preserve-scroll
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
