<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    entries: { type: Object, required: true },
    filters: { type: Object, required: true },
    archived: { type: Boolean, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const search = ref(props.filters.search || '');

const applyFilters = () => {
    router.get(
        route(props.archived ? 'offer-lists.archive' : 'offer-lists.index'),
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
};

watch(search, applyFilters);

const showPrice = (amount) => {
    if (amount === null || amount === undefined || amount === '') {
        return '—';
    }

    return amount;
};

const postStatus = (name, id) => {
    router.post(route(name, id));
};

const removeEntry = (id) => {
    if (!window.confirm('Bu kayıt listeden çıkarılsın mı?')) {
        return;
    }

    router.delete(route('offer-lists.destroy', id));
};
</script>

<template>
    <Head :title="archived ? 'Teklif arşivi' : 'Teklif listesi'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ archived ? 'Teklif arşivi' : 'Teklif listesi' }}
                </h2>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="archived ? route('offer-lists.index') : route('offer-lists.archive')"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
                    >
                        {{ archived ? 'Listeye dön' : 'Arşiv' }}
                    </Link>
                    <Link
                        v-if="canManage"
                        :href="route('offer-lists.create')"
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                    >
                        Yeni kayıt
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

                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <InputLabel for="search" value="Ara" />
                    <TextInput
                        id="search"
                        v-model="search"
                        class="mt-1 block w-full"
                        placeholder="Müşteri, ilgili kişi, ürün veya fabrika"
                    />
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Müşteri</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">İlgili kişi</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Ürün / miktar</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Fiyat</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Fabrika</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Fabrika fiyatı</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Teklif veren</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Tarih</th>
                                <th v-if="archived" class="px-4 py-3 text-left font-medium text-gray-600">Durum</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="entry in entries.data" :key="entry.id">
                                <td class="max-w-[10rem] truncate px-4 py-3 font-medium text-gray-900" :title="entry.customer_name">
                                    {{ entry.customer_name }}
                                </td>
                                <td class="max-w-[8rem] truncate px-4 py-3 text-gray-700" :title="entry.contact_name || ''">
                                    {{ entry.contact_name || '—' }}
                                </td>
                                <td class="max-w-[14rem] truncate px-4 py-3 text-gray-700" :title="entry.product_quantity || ''">
                                    {{ entry.product_quantity || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-gray-900">
                                    {{ showPrice(entry.price) }}
                                </td>
                                <td class="max-w-[8rem] truncate px-4 py-3 text-gray-700" :title="entry.factory_name || ''">
                                    {{ entry.factory_name || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-gray-900">
                                    {{ showPrice(entry.factory_price) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                                    {{ entry.offered_by || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                                    {{ entry.offered_on || '—' }}
                                </td>
                                <td v-if="archived" class="whitespace-nowrap px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="entry.status === 'archived_positive' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                    >
                                        {{ entry.status_label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="flex justify-end gap-3">
                                        <Link
                                            :href="route('offer-lists.edit', entry.id)"
                                            class="text-indigo-600 hover:text-indigo-800"
                                        >
                                            Aç
                                        </Link>
                                        <template v-if="canManage && !archived">
                                            <button type="button" class="text-green-700 hover:text-green-900" @click="postStatus('offer-lists.archive-positive', entry.id)">
                                                Arşiv +
                                            </button>
                                            <button type="button" class="text-red-700 hover:text-red-900" @click="postStatus('offer-lists.archive-negative', entry.id)">
                                                Arşiv −
                                            </button>
                                        </template>
                                        <template v-if="canManage && archived">
                                            <button type="button" class="text-indigo-700 hover:text-indigo-900" @click="postStatus('offer-lists.restore', entry.id)">
                                                Listeye al
                                            </button>
                                            <button type="button" class="text-red-700 hover:text-red-900" @click="removeEntry(entry.id)">
                                                Çıkar
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="entries.data.length === 0">
                                <td :colspan="archived ? 10 : 9" class="px-4 py-8 text-center text-gray-500">
                                    {{ archived ? 'Arşivde kayıt yok.' : 'Kayıt yok.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="entries.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3"
                    >
                        <Link
                            v-for="link in entries.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            class="rounded border px-3 py-1 text-xs"
                            :class="link.active ? 'border-gray-800 bg-gray-800 text-white' : 'border-gray-200 text-gray-700'"
                            v-html="link.label"
                            preserve-scroll
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
