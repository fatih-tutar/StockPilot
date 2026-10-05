<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    factories: { type: Object, required: true },
    filters: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const canViewFactoryOrders = computed(() => {
    const permissions = page.props.auth?.user?.permissions || [];

    return (
        permissions.includes('factory_orders.view')
        || permissions.includes('factory_orders.manage')
    );
});
const flashError = computed(() => page.props.flash?.error);
const search = ref(props.filters.search || '');

watch(search, () => {
    router.get(
        route('factories.index'),
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
});

const labor = (value) => {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};
</script>

<template>
    <Head title="Fabrikalar" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Fabrikalar
                </h2>
                <Link
                    v-if="canManage"
                    :href="route('factories.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                >
                    Yeni fabrika
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
                        placeholder="Ad, telefon veya e-posta"
                    />
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Ad</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Telefon</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">İşçilik</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Durum</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="factory in factories.data" :key="factory.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">
                                        {{ factory.name }}
                                    </div>
                                    <div
                                        v-if="factory.email"
                                        class="text-xs text-gray-500"
                                    >
                                        {{ factory.email }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ factory.phone || '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">
                                    {{ labor(factory.labor_cost) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="factory.is_active"
                                        class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800"
                                    >
                                        Aktif
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                                    >
                                        Pasif
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        v-if="canViewFactoryOrders"
                                        :href="route('factory-orders.index', { factory_id: factory.id })"
                                        class="mr-3 text-indigo-600 hover:text-indigo-800"
                                    >
                                        Siparişler
                                    </Link>
                                    <Link
                                        :href="route('factories.edit', factory.id)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                    >
                                        Aç
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="factories.data.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    Fabrika bulunamadı.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="factories.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3"
                    >
                        <Link
                            v-for="link in factories.links"
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
