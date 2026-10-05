<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    molds: { type: Object, required: true },
    archived: { type: Boolean, default: false },
    filters: { type: Object, required: true },
    factories: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const search = ref(props.filters.search || '');
const factoryId = ref(props.filters.factory_id || '');

const visit = () => {
    router.get(
        route(props.archived ? 'molds.archived' : 'molds.index'),
        {
            search: search.value || undefined,
            factory_id: factoryId.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

watch(search, visit);
watch(factoryId, visit);

const archiveMold = (mold) => {
    if (!confirm(`${mold.number} kodlu kalıp arşivlensin mi?`)) {
        return;
    }

    router.post(route('molds.archive', mold.id));
};

const unarchiveMold = (mold) => {
    if (!confirm(`${mold.number} kodlu kalıp arşivden çıkarılsın mı?`)) {
        return;
    }

    router.post(route('molds.unarchive', mold.id));
};

const destroyMold = (mold) => {
    if (!confirm(`${mold.number} kodlu kalıp silinsin mi?`)) {
        return;
    }

    router.delete(route('molds.destroy', mold.id));
};
</script>

<template>
    <Head :title="archived ? 'Kalıp arşivi' : 'Kalıplar'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ archived ? 'Kalıp arşivi' : 'Kalıplar' }}
                </h2>
                <div class="flex items-center gap-4">
                    <Link
                        :href="archived ? route('molds.index') : route('molds.archived')"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
                    >
                        {{ archived ? 'Açık kalıplar' : 'Arşiv' }}
                    </Link>
                    <Link
                        v-if="canManage && !archived"
                        :href="route('molds.create')"
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                    >
                        Yeni kalıp
                    </Link>
                </div>
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

                <div class="grid gap-4 bg-white p-4 shadow-sm sm:rounded-lg md:grid-cols-2">
                    <div>
                        <InputLabel for="search" value="Ara" />
                        <TextInput
                            id="search"
                            v-model="search"
                            class="mt-1 block w-full"
                            placeholder="Firma, kalıp no veya ilgili kişi"
                        />
                    </div>
                    <div>
                        <InputLabel for="factory_id" value="Fabrika" />
                        <select
                            id="factory_id"
                            v-model="factoryId"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Tümü</option>
                            <option v-for="factory in factories" :key="factory.id" :value="factory.id">
                                {{ factory.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Firma</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Kalıp no</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Fabrika</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Firma teklifi</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Fabrika teklifi</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Termin</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">İlgili</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Belgeler</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="mold in molds.data" :key="mold.id">
                                <td class="px-4 py-3 text-gray-900">{{ mold.client_name || '—' }}</td>
                                <td class="px-4 py-3">{{ mold.number || '—' }}</td>
                                <td class="px-4 py-3">{{ mold.factory_name || '—' }}</td>
                                <td class="px-4 py-3">{{ mold.client_offer_price || '—' }}</td>
                                <td class="px-4 py-3">{{ mold.factory_offer_price || '—' }}</td>
                                <td class="px-4 py-3">{{ mold.due_on || '—' }}</td>
                                <td class="px-4 py-3">{{ mold.contact_name || '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-1">
                                        <template v-for="document in mold.documents" :key="document.key">
                                            <a
                                                v-if="document.url"
                                                :href="document.url"
                                                class="text-indigo-600 hover:text-indigo-800"
                                            >
                                                {{ document.label }}
                                            </a>
                                        </template>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-3">
                                        <Link
                                            :href="route('molds.edit', mold.id)"
                                            class="text-indigo-600 hover:text-indigo-800"
                                        >
                                            Düzenle
                                        </Link>
                                        <button
                                            v-if="canManage && !archived"
                                            type="button"
                                            class="text-gray-600 hover:text-gray-900"
                                            @click="archiveMold(mold)"
                                        >
                                            Arşivle
                                        </button>
                                        <button
                                            v-if="canManage && archived"
                                            type="button"
                                            class="text-gray-600 hover:text-gray-900"
                                            @click="unarchiveMold(mold)"
                                        >
                                            Çıkar
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            class="text-red-600 hover:text-red-800"
                                            @click="destroyMold(mold)"
                                        >
                                            Sil
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="molds.data.length === 0">
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                    {{ archived ? 'Arşivde kalıp yok.' : 'Açık kalıp yok.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="molds.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3"
                    >
                        <Link
                            v-for="link in molds.links"
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
