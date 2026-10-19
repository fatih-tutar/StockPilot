<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    shipment: { type: Object, required: true },
});

const money = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${Number(value).toLocaleString('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} TL`;
};

const printPage = () => {
    window.print();
};
</script>

<template>
    <Head title="Sevkiyat formu" />

    <AuthenticatedLayout>
        <div class="py-8 print:py-0">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8 print:max-w-none print:px-0">
                <div class="flex items-center justify-between print:hidden">
                    <Link
                        :href="route('shipments.index')"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        Müşteri siparişlerine dön
                    </Link>
                    <button
                        type="button"
                        class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white"
                        @click="printPage"
                    >
                        Yazdır
                    </button>
                </div>

                <div class="bg-white p-8 text-sm shadow-sm sm:rounded-lg print:shadow-none">
                    <div class="grid gap-6 sm:grid-cols-3">
                        <div class="flex items-center justify-center sm:col-span-1">
                            <img
                                v-if="shipment.company?.logo"
                                :src="shipment.company.logo"
                                :alt="shipment.company?.name || 'Logo'"
                                class="max-h-24 w-auto"
                            />
                            <p v-else class="text-lg font-semibold text-gray-900">
                                {{ shipment.company?.name || 'StockPilot' }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <p
                                v-if="shipment.company?.letterhead"
                                class="whitespace-pre-line text-center text-[10px] leading-relaxed text-gray-700"
                            >
                                {{ shipment.company.letterhead }}
                            </p>
                        </div>
                    </div>

                    <h1 class="mt-8 text-center text-lg font-bold text-gray-900">
                        SEVKİYAT FORMU
                    </h1>

                    <div class="mt-6 space-y-3">
                        <p><span class="font-semibold">Firma :</span> {{ shipment.client?.name || '—' }}</p>
                        <p><span class="font-semibold">Adres :</span> {{ shipment.client?.address || '—' }}</p>
                        <div class="grid gap-2 sm:grid-cols-4">
                            <p><span class="font-semibold">Tel :</span> {{ shipment.client?.phone || '—' }}</p>
                            <p class="sm:col-span-2">
                                <span class="font-semibold">E-posta :</span> {{ shipment.client?.email || '—' }}
                            </p>
                            <p><span class="font-semibold">Tarih :</span> {{ shipment.ship_date || '—' }}</p>
                            <p><span class="font-semibold">Saat :</span> {{ shipment.created_time || '—' }}</p>
                        </div>
                    </div>

                    <div class="mt-8 hidden grid-cols-12 gap-2 border-b-2 border-black pb-2 font-semibold sm:grid">
                        <div class="col-span-4">Ürün</div>
                        <div class="col-span-2">Cinsi</div>
                        <div class="col-span-2">Raf</div>
                        <div class="col-span-2 text-right">Adet</div>
                        <div class="col-span-2 text-right">Fiyat</div>
                    </div>

                    <div
                        v-for="(item, index) in shipment.items"
                        :key="index"
                        class="grid gap-1 border-b-2 border-black py-4 sm:grid-cols-12 sm:gap-2"
                    >
                        <div class="sm:col-span-4">
                            <span class="sm:hidden font-semibold">Ürün: </span>
                            <span class="font-semibold">
                                {{ item.product_name || '—' }}
                                <span v-if="item.type"> {{ item.type }}</span>
                            </span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="sm:hidden font-semibold">Cinsi: </span>
                            {{ item.kind || '—' }}
                        </div>
                        <div class="sm:col-span-2">
                            <span class="sm:hidden font-semibold">Raf: </span>
                            {{ item.shelf || '—' }}
                        </div>
                        <div class="sm:col-span-2 sm:text-right">
                            <span class="sm:hidden font-semibold">Adet: </span>
                            {{ item.quantity_piece }}
                        </div>
                        <div class="sm:col-span-2 sm:text-right">
                            <span class="sm:hidden font-semibold">Fiyat: </span>
                            {{ money(item.unit_price) }}
                        </div>
                    </div>

                    <div class="mt-6 grid gap-2 sm:grid-cols-3">
                        <p>
                            <span class="font-semibold">Siparişi Oluşturan :</span>
                            {{ shipment.creator_name || '—' }}
                        </p>
                        <p>
                            <span class="font-semibold">Siparişi Hazırlayan :</span>
                            {{ shipment.preparer_name || '—' }}
                        </p>
                        <p>
                            <span class="font-semibold">Faturayı Kesen :</span>
                            {{ shipment.invoicer_name || '—' }}
                        </p>
                    </div>

                    <div class="mt-4 grid gap-2 sm:grid-cols-3">
                        <p class="sm:col-span-1">
                            <span class="font-semibold">Sevk Tipi:</span>
                            {{ shipment.ship_type || '—' }}
                        </p>
                        <p class="sm:col-span-2">
                            <span class="font-semibold">Açıklama:</span>
                            {{ shipment.notes || '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
