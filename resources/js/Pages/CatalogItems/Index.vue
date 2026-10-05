<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    groups: { type: Array, required: true },
    filters: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
    pricesVisible: { type: Boolean, required: true },
    canTogglePrices: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const search = ref(props.filters.search || '');

const visit = () => {
    router.get(
        route('catalog-items.index'),
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
};

watch(search, visit);

const move = (group, direction) => {
    router.post(route('catalog-items.move', group.id), { direction }, { preserveScroll: true });
};

const destroyLine = (line) => {
    const label = line.model || line.code || 'bu satır';
    if (!confirm(`${label} silinsin mi?`)) {
        return;
    }

    router.delete(route('catalog-items.destroy', line.id));
};

const destroyGroup = (group) => {
    if (!confirm(`${group.product_code} numaralı ürün ve satırları silinsin mi?`)) {
        return;
    }

    router.delete(route('catalog-items.group.destroy', group.id));
};

const togglePrices = () => {
    router.post(route('catalog-items.visibility'), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Fiyat listesi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Fiyat listesi</h2>
                <div class="flex items-center gap-4">
                    <a
                        :href="route('catalog-items.print')"
                        target="_blank"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
                    >
                        Baskı
                    </a>
                    <button
                        v-if="canTogglePrices"
                        type="button"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
                        @click="togglePrices"
                    >
                        {{ pricesVisible ? 'Baskıda gizle' : 'Baskıda göster' }}
                    </button>
                    <Link
                        v-if="canManage"
                        :href="route('catalog-items.create')"
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                    >
                        Yeni ürün
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

                <p v-if="!pricesVisible" class="text-sm text-gray-600">Baskı listesinde fiyatlar gizli.</p>

                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <InputLabel for="search" value="Ara" />
                    <TextInput
                        id="search"
                        v-model="search"
                        class="mt-1 block w-full"
                        placeholder="Ürün no, kod, model veya açıklama"
                    />
                </div>

                <p v-if="groups.length === 0" class="text-sm text-gray-500">Kayıt yok.</p>

                <article
                    v-for="(group, index) in groups"
                    :key="group.id"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-sky-600 px-4 py-3 text-white">
                        <p class="font-semibold">Ürün no: {{ group.product_code }}</p>
                        <div v-if="canManage" class="flex flex-wrap items-center gap-3 text-xs font-semibold uppercase tracking-widest">
                            <button v-if="index > 0" type="button" class="hover:underline" @click="move(group, 'up')">
                                Yukarı
                            </button>
                            <button
                                v-if="index < groups.length - 1"
                                type="button"
                                class="hover:underline"
                                @click="move(group, 'down')"
                            >
                                Aşağı
                            </button>
                            <Link :href="route('catalog-items.edit', group.id)" class="hover:underline">Düzenle</Link>
                            <button type="button" class="hover:underline" @click="destroyGroup(group)">Ürünü sil</button>
                        </div>
                    </div>

                    <div class="grid gap-4 p-4 lg:grid-cols-[160px_minmax(0,1fr)_160px]">
                        <div class="space-y-2">
                            <img
                                v-if="group.images[0]?.url"
                                :src="group.images[0].url"
                                :alt="group.images[0].label"
                                class="w-full rounded border border-gray-200"
                            />
                            <p v-else class="text-xs text-gray-500">
                                {{ group.images[0]?.file_name || 'Fotoğraf 1' }}
                                <span v-if="group.images[0]?.file_name"> (dosya yok)</span>
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <th class="px-2 py-2">Kod</th>
                                        <th class="px-2 py-2">Model</th>
                                        <th class="px-2 py-2">Adet / metre</th>
                                        <th class="px-2 py-2">Fiyat</th>
                                        <th v-if="canManage" class="px-2 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in group.lines" :key="line.id" class="border-t border-gray-200">
                                        <td class="px-2 py-2">{{ line.code }}</td>
                                        <td class="px-2 py-2">{{ line.model }}</td>
                                        <td class="px-2 py-2">{{ line.quantity }}</td>
                                        <td class="px-2 py-2">{{ line.price }}</td>
                                        <td v-if="canManage" class="px-2 py-2 text-right">
                                            <button
                                                type="button"
                                                class="text-xs font-semibold uppercase tracking-widest text-red-600 hover:text-red-800"
                                                @click="destroyLine(line)"
                                            >
                                                Sil
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-if="group.description" class="mt-3 text-sm text-gray-700">
                                <span class="font-semibold">Açıklama:</span> {{ group.description }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <img
                                v-if="group.images[1]?.url"
                                :src="group.images[1].url"
                                :alt="group.images[1].label"
                                class="w-full rounded border border-gray-200"
                            />
                            <p v-else class="text-xs text-gray-500">
                                {{ group.images[1]?.file_name || 'Fotoğraf 2' }}
                                <span v-if="group.images[1]?.file_name"> (dosya yok)</span>
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
