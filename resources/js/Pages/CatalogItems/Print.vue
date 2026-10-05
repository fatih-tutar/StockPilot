<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    groups: { type: Array, required: true },
    pricesVisible: { type: Boolean, required: true },
    letterhead: { type: String, default: null },
    companyName: { type: String, default: null },
});

const printPage = () => window.print();
</script>

<template>
    <Head title="Fiyat listesi" />

    <div class="min-h-screen bg-white text-gray-900">
        <div class="mx-auto max-w-5xl space-y-4 px-4 py-6">
            <div class="flex items-start justify-between gap-4 print:hidden">
                <a :href="route('catalog-items.index')" class="text-sm text-gray-600 hover:text-gray-900">Listeye dön</a>
                <button
                    type="button"
                    class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white"
                    @click="printPage"
                >
                    Yazdır
                </button>
            </div>

            <header class="space-y-1">
                <h1 class="text-xl font-semibold">{{ companyName || 'Fiyat listesi' }}</h1>
                <p v-if="letterhead" class="whitespace-pre-line text-sm text-gray-700">{{ letterhead }}</p>
            </header>

            <p class="text-sm font-semibold text-red-700">
                Fiyatlarımız kg x birim fiyat = metre maliyet olarak listelenmiştir.
            </p>
            <p v-if="!pricesVisible" class="text-sm font-semibold">
                Fiyat güncelleme sırasında fiyat almak için arayınız.
            </p>

            <section v-for="group in groups" :key="group.id" class="overflow-hidden border border-sky-800">
                <div class="bg-sky-600 px-3 py-2 text-sm font-semibold text-white">
                    Ürün no: {{ group.product_code }}
                </div>
                <div class="grid gap-3 p-3 sm:grid-cols-[140px_minmax(0,1fr)_140px]">
                    <img
                        v-if="group.images[0]?.url"
                        :src="group.images[0].url"
                        alt=""
                        class="w-full border border-sky-800"
                    />
                    <div v-else class="text-xs text-gray-400"></div>

                    <div>
                        <div
                            class="hidden grid-cols-[1fr_2fr_2fr_1fr] text-xs font-semibold uppercase sm:grid"
                        >
                            <span>Kod</span>
                            <span>Model</span>
                            <span>Adet / metre</span>
                            <span>Fiyat</span>
                        </div>
                        <div
                            v-for="line in group.lines"
                            :key="line.id"
                            class="grid grid-cols-2 gap-1 border-t border-sky-800 py-1 text-sm sm:grid-cols-[1fr_2fr_2fr_1fr]"
                        >
                            <span>{{ line.code }}</span>
                            <span>{{ line.model }}</span>
                            <span>{{ line.quantity }}</span>
                            <span>{{ pricesVisible ? line.price : 'Güncelleniyor...' }}</span>
                        </div>
                        <p v-if="group.description" class="mt-2 border-t border-sky-800 pt-2 text-sm">
                            <span class="font-semibold underline">Açıklama:</span> {{ group.description }}
                        </p>
                    </div>

                    <img
                        v-if="group.images[1]?.url"
                        :src="group.images[1].url"
                        alt=""
                        class="hidden w-full border border-sky-800 sm:block"
                    />
                </div>
            </section>
        </div>
    </div>
</template>
