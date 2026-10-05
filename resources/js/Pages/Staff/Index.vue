<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    staff: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const search = ref(props.filters.search || '');

watch(search, () => {
    router.get(
        route('staff.index'),
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
</script>

<template>
    <Head title="Personel" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Personel
                </h2>
                <Link
                    :href="route('staff.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                >
                    Yeni personel
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
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Ad, unvan, telefon veya e-posta"
                    />
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Ad</th>
                                <th class="px-4 py-3">Unvan</th>
                                <th class="px-4 py-3">Telefon</th>
                                <th class="px-4 py-3">Düzey</th>
                                <th class="px-4 py-3">İşe giriş</th>
                                <th class="px-4 py-3">Durum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="staff.data.length === 0">
                                <td colspan="6" class="px-4 py-6 text-gray-500">
                                    Kayıt yok.
                                </td>
                            </tr>
                            <tr
                                v-for="person in staff.data"
                                :key="person.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    <Link
                                        :href="route('staff.edit', person.id)"
                                        class="hover:underline"
                                    >
                                        {{ person.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ person.title || '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ person.phone || '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ person.access_level || '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ formatDate(person.hired_on) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="person.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ person.is_active ? 'Aktif' : 'Pasif' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
