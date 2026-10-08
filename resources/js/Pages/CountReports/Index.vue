<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    groups: { type: Array, required: true },
    place: { type: String, required: true },
    title: { type: String, required: true },
    reportDate: { type: String, required: true },
});

const showQuantities = ref(true);
const isAlkop = computed(() => props.place === 'alkop');
const columnCount = computed(() => {
    if (isAlkop.value) {
        return showQuantities.value ? 5 : 3;
    }

    return showQuantities.value ? 3 : 2;
});

const printPage = () => window.print();
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <div class="bg-white py-8 print:bg-white print:py-0">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8 print:max-w-none print:px-0">
                <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <div class="flex gap-2">
                        <Link
                            :href="route('count-reports.index', { place: 'caglayan' })"
                            class="rounded-md border px-3 py-2 text-sm font-semibold"
                            :class="place === 'caglayan' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-800'"
                        >
                            Çağlayan Sayım Raporu
                        </Link>
                        <Link
                            :href="route('count-reports.index', { place: 'alkop' })"
                            class="rounded-md border px-3 py-2 text-sm font-semibold"
                            :class="place === 'alkop' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-800'"
                        >
                            Alkop Sayım Raporu
                        </Link>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                            <input v-model="showQuantities" type="checkbox" class="rounded border-gray-300" />
                            Adetli
                        </label>
                        <button
                            type="button"
                            class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white"
                            @click="printPage"
                        >
                            Yazdır
                        </button>
                    </div>
                </div>

                <div v-if="groups.length === 0" class="py-16 text-center text-gray-500">
                    <h1 class="text-xl font-semibold uppercase tracking-wide text-gray-900">{{ title }}</h1>
                    <p class="mt-1 text-sm">{{ reportDate }}</p>
                    <p class="mt-8">Listelenecek ürün bulunamadı.</p>
                </div>

                <div v-else class="columns-1 gap-6 md:columns-2 print:columns-2 print:gap-4">
                    <header class="mb-4 break-inside-avoid border-b-2 border-gray-900 pb-2 text-center">
                        <h1 class="text-xl font-semibold uppercase tracking-wide">{{ title }}</h1>
                        <p class="mt-1 text-sm text-gray-600">{{ reportDate }}</p>
                    </header>

                    <section
                        v-for="(group, index) in groups"
                        :key="`${group.main}-${group.sub}-${index}`"
                        class="mb-4 break-inside-avoid"
                    >
                        <table class="w-full table-fixed border-collapse text-xs">
                            <thead>
                                <tr>
                                    <th :colspan="columnCount" class="bg-gray-900 px-2 py-1.5 text-left text-sm font-semibold uppercase text-white">
                                        {{ group.main }}
                                    </th>
                                </tr>
                                <tr>
                                    <th :colspan="columnCount" class="bg-gray-100 px-2 py-1.5 text-left text-sm font-semibold text-gray-900">
                                        {{ group.sub }}
                                    </th>
                                </tr>
                                <tr class="bg-gray-50 text-[10px] uppercase tracking-wide text-gray-600">
                                    <th class="border-b border-gray-300 px-2 py-1 text-left font-semibold">Ürün</th>
                                    <template v-if="isAlkop">
                                        <th v-show="showQuantities" class="w-12 border-b border-gray-300 px-1 py-1 text-center font-semibold">Palet</th>
                                        <th v-show="showQuantities" class="w-12 border-b border-gray-300 px-1 py-1 text-center font-semibold">Alkop</th>
                                        <th class="w-12 border-b border-gray-300 px-1 py-1 text-center font-semibold">Raf</th>
                                    </template>
                                    <th v-else v-show="showQuantities" class="w-12 border-b border-gray-300 px-1 py-1 text-center font-semibold">Adet</th>
                                    <th class="w-14 border-b border-gray-300 px-1 py-1 text-center font-semibold">Sayım</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in group.products" :key="product.name" class="even:bg-gray-50">
                                    <td class="truncate border-b border-gray-200 px-2 py-1 font-medium">{{ product.name }}</td>
                                    <template v-if="isAlkop">
                                        <td v-show="showQuantities" class="border-b border-gray-200 px-1 py-1 text-center">{{ product.pallet }}</td>
                                        <td v-show="showQuantities" class="border-b border-gray-200 px-1 py-1 text-center">{{ product.warehouse }}</td>
                                        <td class="border-b border-gray-200 px-1 py-1 text-center">{{ product.shelf || '—' }}</td>
                                    </template>
                                    <td v-else v-show="showQuantities" class="border-b border-gray-200 px-1 py-1 text-center">{{ product.piece }}</td>
                                    <td class="h-7 border border-gray-400 bg-white"></td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
