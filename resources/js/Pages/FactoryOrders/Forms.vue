<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    forms: { type: Object, required: true },
    filters: { type: Object, required: true },
    factories: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const factoryId = ref(props.filters.factory_id || '');

watch(factoryId, () => {
    router.get(
        route('factory-order-forms.index'),
        { factory_id: factoryId.value || undefined },
        { preserveState: true, replace: true },
    );
});

const destroyForm = (form) => {
    if (confirm('Form ve içindeki sipariş kalemleri silinsin mi?')) {
        router.delete(route('factory-order-forms.destroy', form.id));
    }
};
</script>

<template>
    <Head title="Sipariş formları" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Sipariş formları
                </h2>
                <Link
                    :href="route('factory-orders.index', { factory_id: factoryId || undefined })"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Siparişlere dön
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

                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <InputLabel for="factory" value="Fabrika" />
                    <select
                        id="factory"
                        v-model="factoryId"
                        class="mt-1 block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Tümü</option>
                        <option v-for="factory in factories" :key="factory.id" :value="factory.id">
                            {{ factory.name }}
                        </option>
                    </select>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Tarih</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Fabrika</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Hazırlayan</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">İlgili</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Kalem</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="form in forms.data" :key="form.id">
                                <td class="px-4 py-3 text-gray-700">{{ form.created_at }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ form.factory?.name || '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ form.prepared_by?.name || '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ form.contact_name || '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ form.orders_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('factory-order-forms.show', form.id)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                    >
                                        Aç
                                    </Link>
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="ml-3 text-red-600 hover:text-red-800"
                                        @click="destroyForm(form)"
                                    >
                                        Sil
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="forms.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Form bulunamadı.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="forms.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3"
                    >
                        <Link
                            v-for="link in forms.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            class="rounded border px-3 py-1 text-xs"
                            :class="link.active ? 'border-gray-800 bg-gray-800 text-white' : 'border-gray-200 text-gray-700'"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
