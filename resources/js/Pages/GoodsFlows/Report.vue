<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    rows: { type: Array, required: true },
    weights: { type: Object, required: true },
    companyName: { type: String, default: null },
    letterhead: { type: String, default: null },
});

const printPage = () => window.print();

const rowClass = (kind) => ({
    week: 'bg-sky-50',
    month: 'bg-amber-50',
    year: 'bg-emerald-50',
}[kind] ?? '');
</script>

<template>
    <Head title="Gelen giden raporu" />

    <div class="min-h-screen bg-white text-gray-900">
        <div class="mx-auto max-w-5xl space-y-6 px-6 py-8">
            <div class="flex items-center justify-between print:hidden">
                <a :href="route('goods-flows.index')" class="text-sm text-gray-600 hover:text-gray-900">Listeye dön</a>
                <button type="button" class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white" @click="printPage">
                    Yazdır
                </button>
            </div>

            <header class="space-y-2 text-center">
                <p v-if="companyName" class="text-lg font-semibold">{{ companyName }}</p>
                <p v-if="letterhead" class="whitespace-pre-line text-sm text-gray-700">{{ letterhead }}</p>
                <h1 class="text-2xl font-semibold">Gelen / giden ürün raporu</h1>
                <p class="text-sm text-gray-600">Haftalık, aylık ve yıllık toplamlar</p>
            </header>

            <section class="grid gap-3 text-sm sm:grid-cols-3">
                <p><span class="text-gray-500">Çağlayan</span> {{ weights.store }}</p>
                <p><span class="text-gray-500">Alkop</span> {{ weights.warehouse }}</p>
                <p><span class="text-gray-500">Toplam</span> {{ weights.total }}</p>
            </section>

            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-300 text-left">
                        <th class="py-2">Dönem</th>
                        <th class="py-2">Çağlayan giden</th>
                        <th class="py-2">Çağlayan gelen</th>
                        <th class="py-2">Alkop giden</th>
                        <th class="py-2">Alkop gelen</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in rows" :key="`${row.kind}-${row.label}-${index}`" class="border-b border-gray-100" :class="rowClass(row.kind)">
                        <td class="py-2">{{ row.label }}</td>
                        <td class="py-2">{{ row.store_outgoing }}</td>
                        <td class="py-2">{{ row.store_incoming }}</td>
                        <td class="py-2">{{ row.warehouse_outgoing }}</td>
                        <td class="py-2">{{ row.warehouse_incoming }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
