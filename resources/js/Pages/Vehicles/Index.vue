<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    vehicles: { type: Object, required: true },
    filters: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const search = ref(props.filters.search || '');

watch(search, () => {
    router.get(
        route('vehicles.index'),
        { search: search.value || undefined },
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
</script>

<template>
    <Head title="Araçlar" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Araçlar
                </h2>
                <Link
                    v-if="canManage"
                    :href="route('vehicles.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                >
                    Yeni araç
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

                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <InputLabel for="search" value="Ara" />
                    <TextInput
                        id="search"
                        v-model="search"
                        class="mt-1 block w-full max-w-md"
                        placeholder="Araç, plaka veya sürücü"
                    />
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Araç</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Sürücü</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Kasko</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Trafik</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Muayene</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="vehicle in vehicles.data" :key="vehicle.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">
                                        {{ vehicle.name }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ vehicle.license_plate || 'Plaka yok' }}
                                        <span v-if="vehicle.is_delivery_vehicle"> · Sevkiyat</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ vehicle.driver_name || '—' }}
                                </td>
                                <td
                                    class="px-4 py-3"
                                    :class="isPast(vehicle.casco_expires_on) ? 'text-red-700' : 'text-gray-700'"
                                >
                                    {{ formatDate(vehicle.casco_expires_on) }}
                                </td>
                                <td
                                    class="px-4 py-3"
                                    :class="isPast(vehicle.insurance_expires_on) ? 'text-red-700' : 'text-gray-700'"
                                >
                                    {{ formatDate(vehicle.insurance_expires_on) }}
                                </td>
                                <td
                                    class="px-4 py-3"
                                    :class="isPast(vehicle.inspection_due_on) ? 'text-red-700' : 'text-gray-700'"
                                >
                                    {{ formatDate(vehicle.inspection_due_on) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('vehicles.edit', vehicle.id)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                    >
                                        Aç
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="vehicles.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Araç bulunamadı.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="vehicles.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3"
                    >
                        <Link
                            v-for="link in vehicles.links"
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
