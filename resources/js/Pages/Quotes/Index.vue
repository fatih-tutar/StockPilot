<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    quotes: { type: Object, required: true },
    filters: { type: Object, required: true },
    statusOptions: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');

const applyFilters = () => {
    router.get(
        route('quotes.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

watch(search, applyFilters);
watch(status, applyFilters);

const formatMoney = (amount, currency = 'TRY') =>
    new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(amount ?? 0);
</script>

<template>
    <Head title="Teklifler" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Teklifler
                </h2>
                <Link
                    v-if="canManage"
                    :href="route('quotes.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                >
                    Yeni teklif
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

                <div class="grid gap-4 bg-white p-4 shadow-sm sm:grid-cols-2 sm:rounded-lg">
                    <div>
                        <InputLabel for="search" value="Ara" />
                        <TextInput
                            id="search"
                            v-model="search"
                            class="mt-1 block w-full"
                            placeholder="Teklif no veya müşteri"
                        />
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
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">No</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Müşteri</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Tarih</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Durum</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Toplam</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="quote in quotes.data" :key="quote.id">
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    {{ quote.number }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ quote.client?.name || '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ quote.quote_date }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700"
                                    >
                                        {{ quote.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900">
                                    {{ formatMoney(quote.total, quote.currency) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('quotes.edit', quote.id)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                    >
                                        Aç
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="quotes.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Teklif bulunamadı.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="quotes.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3"
                    >
                        <Link
                            v-for="link in quotes.links"
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
        </div>
    </AuthenticatedLayout>
</template>
