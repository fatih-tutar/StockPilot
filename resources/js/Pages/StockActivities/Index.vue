<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    activities: { type: Object, required: true },
    product: { type: Object, default: null },
});

const differenceClass = (difference) => {
    if (difference > 0) {
        return 'text-green-700';
    }

    if (difference < 0) {
        return 'text-red-700';
    }

    return 'text-gray-700';
};

const differenceLabel = (difference) => (difference > 0 ? `+${difference}` : String(difference));
</script>

<template>
    <Head title="İşlemler" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">İşlemler</h2>
                <Link
                    v-if="product"
                    :href="route('stock-activities.index')"
                    class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                >
                    Tüm kayıtlar
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <p class="text-sm text-gray-600">
                    <template v-if="product">{{ product.name }} · </template>
                    {{ new Intl.NumberFormat('tr-TR').format(activities.total) }} kayıt
                </p>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Personel</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Ürün</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Eski adet</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Yeni adet</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Fark</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Yer</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Tarih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="activity in activities.data" :key="activity.id">
                                <td class="px-4 py-3 text-gray-800">{{ activity.user?.name || '—' }}</td>
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="activity.product"
                                        :href="route('stock-activities.index', { product_id: activity.product.id })"
                                        class="font-medium text-indigo-700 hover:text-indigo-900"
                                    >
                                        {{ activity.product.name }}
                                    </Link>
                                    <span v-else class="text-gray-500">Silinmiş ürün</span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-800">{{ activity.previous_quantity }}</td>
                                <td class="px-4 py-3 text-right text-gray-800">{{ activity.new_quantity }}</td>
                                <td class="px-4 py-3 text-right font-medium" :class="differenceClass(activity.difference)">
                                    {{ differenceLabel(activity.difference) }}
                                </td>
                                <td class="px-4 py-3 text-gray-800">{{ activity.place }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ activity.recorded_at }}</td>
                            </tr>
                            <tr v-if="activities.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    Kayıt yok.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="activities.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3"
                    >
                        <Link
                            v-for="link in activities.links"
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
