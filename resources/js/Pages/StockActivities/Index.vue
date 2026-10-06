<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    activities: { type: Array, required: true },
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
                    <template v-if="product">
                        {{ product.name }} için en yeni 300 kayıt.
                    </template>
                    <template v-else>
                        En yeni 300 kayıt.
                    </template>
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
                            <tr v-for="activity in activities" :key="activity.id">
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
                            <tr v-if="activities.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    Kayıt yok.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
