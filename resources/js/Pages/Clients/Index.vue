<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    clients: { type: Object, required: true },
    filters: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const search = ref(props.filters.search || '');

watch(search, () => {
    router.get(
        route('clients.index'),
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
});
</script>

<template>
    <Head title="Müşteriler" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Müşteriler
                </h2>
                <Link
                    v-if="canManage"
                    :href="route('clients.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                >
                    Yeni müşteri
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
                                <th class="px-4 py-3 text-left font-medium text-gray-600">E-posta</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Durum</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="client in clients.data" :key="client.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">
                                        {{ client.name }}
                                    </div>
                                    <div
                                        v-if="client.address"
                                        class="text-xs text-gray-500"
                                    >
                                        {{ client.address }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ client.phone || '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ client.email || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="client.is_active"
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
                                        :href="route('clients.edit', client.id)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                    >
                                        Aç
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="clients.data.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    Müşteri bulunamadı.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="clients.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3"
                    >
                        <Link
                            v-for="link in clients.links"
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
