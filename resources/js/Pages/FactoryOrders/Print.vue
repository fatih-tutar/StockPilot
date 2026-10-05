<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    form: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
});

const kilos = (value) => {
    if (value === null || value === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('tr-TR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    }).format(Number(value));
};

const destroyLine = (line) => {
    if (confirm('Bu kalem silinsin mi?')) {
        router.delete(route('factory-orders.destroy', line.id));
    }
};

const printPage = () => {
    window.print();
};
</script>

<template>
    <Head title="Malzeme talep formu" />

    <AuthenticatedLayout>
        <div class="py-8 print:py-0">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8 print:max-w-none print:px-0">
                <div class="flex items-center justify-between print:hidden">
                    <Link
                        :href="route('factory-order-forms.index', { factory_id: form.factory?.id })"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        Arşive dön
                    </Link>
                    <button
                        type="button"
                        class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white"
                        @click="printPage"
                    >
                        Yazdır
                    </button>
                </div>

                <div class="bg-white p-8 shadow-sm sm:rounded-lg print:shadow-none">
                    <div v-if="form.letterhead" class="mb-6 whitespace-pre-line text-sm text-gray-700">
                        {{ form.letterhead }}
                    </div>
                    <h1 class="text-center text-lg font-bold text-gray-900">
                        MALZEME TALEP FORMU
                    </h1>
                    <div class="mt-6 grid gap-2 text-sm sm:grid-cols-2">
                        <div><span class="font-semibold">Hazırlayan kişi:</span> {{ form.prepared_by?.name || '—' }}</div>
                        <div><span class="font-semibold">Tarih:</span> {{ form.created_at }}</div>
                        <div><span class="font-semibold">Talep edilen fabrika:</span> {{ form.factory?.name || '—' }}</div>
                        <div><span class="font-semibold">İlgili kişi:</span> {{ form.contact_name || '—' }}</div>
                    </div>

                    <table class="mt-6 w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-black text-left">
                                <th class="py-2 pr-2">S.No</th>
                                <th class="py-2 pr-2">Kalıp</th>
                                <th class="py-2 pr-2">Malzemenin cinsi</th>
                                <th class="py-2 pr-2">Boy</th>
                                <th class="py-2 pr-2 text-right">Adet</th>
                                <th class="py-2 pr-2 text-right">Paket</th>
                                <th class="py-2 pr-2 text-right">Palet</th>
                                <th class="py-2 text-right">Tahmini kg</th>
                                <th class="py-2 print:hidden" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in form.lines" :key="line.id" class="border-b border-gray-200">
                                <td class="py-2 pr-2">{{ line.number }}</td>
                                <td class="py-2 pr-2">{{ line.mold_number || '—' }}</td>
                                <td class="py-2 pr-2">{{ line.material }}</td>
                                <td class="py-2 pr-2">{{ line.length || '—' }}</td>
                                <td class="py-2 pr-2 text-right">{{ line.quantity }}</td>
                                <td class="py-2 pr-2 text-right">{{ line.pack_quantity ?? '—' }}</td>
                                <td class="py-2 pr-2 text-right">{{ line.pallet_count }}</td>
                                <td class="py-2 text-right">{{ kilos(line.estimated_kg) }}</td>
                                <td class="py-2 text-right print:hidden">
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="text-xs text-red-600"
                                        @click="destroyLine(line)"
                                    >
                                        Sil
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="7" class="py-2 text-right font-semibold">Toplam</td>
                                <td class="py-2 text-right font-semibold">{{ kilos(form.total_kg) }}</td>
                                <td class="print:hidden" />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
