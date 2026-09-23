<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    categories: { type: Array, required: true },
    parentOptions: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const createForm = useForm({
    name: '',
    description: '',
    parent_id: '',
    sort_order: 0,
});

const editingId = ref(null);
const editForm = useForm({
    name: '',
    description: '',
    parent_id: '',
    sort_order: 0,
});

const deleteForm = useForm({});

const submitCreate = () => {
    createForm.transform((data) => ({
        ...data,
        parent_id: data.parent_id === '' ? null : Number(data.parent_id),
        sort_order: Number(data.sort_order || 0),
    })).post(route('categories.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const startEdit = (category) => {
    editingId.value = category.id;
    editForm.name = category.name;
    editForm.description = category.description || '';
    editForm.parent_id = category.parent?.id || '';
    editForm.sort_order = category.sort_order ?? 0;
    editForm.clearErrors();
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
};

const submitEdit = (category) => {
    editForm.transform((data) => ({
        ...data,
        parent_id: data.parent_id === '' ? null : Number(data.parent_id),
        sort_order: Number(data.sort_order || 0),
    })).put(route('categories.update', category.id), {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
};

const destroyCategory = (category) => {
    if (!confirm(`"${category.name}" kategorisi silinsin mi?`)) {
        return;
    }

    deleteForm.delete(route('categories.destroy', category.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Kategoriler" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Kategoriler
            </h2>
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

                <div
                    v-if="canManage"
                    class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <h3 class="mb-4 text-lg font-medium text-gray-900">
                        Yeni kategori
                    </h3>
                    <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitCreate">
                        <div>
                            <InputLabel for="name" value="Ad" />
                            <TextInput
                                id="name"
                                v-model="createForm.name"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="createForm.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="parent_id" value="Üst kategori (opsiyonel)" />
                            <select
                                id="parent_id"
                                v-model="createForm.parent_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Yok</option>
                                <option
                                    v-for="option in parentOptions"
                                    :key="option.id"
                                    :value="option.id"
                                >
                                    {{ option.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="createForm.errors.parent_id" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="description" value="Açıklama" />
                            <textarea
                                id="description"
                                v-model="createForm.description"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError class="mt-2" :message="createForm.errors.description" />
                        </div>
                        <div class="md:col-span-2">
                            <PrimaryButton :disabled="createForm.processing">
                                Kategori oluştur
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Ad</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Üst kategori</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Ürünler</th>
                                <th v-if="canManage" class="px-4 py-3 text-right font-medium text-gray-600">
                                    İşlemler
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="category in categories" :key="category.id">
                                <td class="px-4 py-3 align-top text-gray-900">
                                    <template v-if="editingId === category.id">
                                        <TextInput v-model="editForm.name" class="w-full" />
                                        <InputError class="mt-1" :message="editForm.errors.name" />
                                        <textarea
                                            v-model="editForm.description"
                                            rows="2"
                                            class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                                        />
                                    </template>
                                    <template v-else>
                                        <div class="font-medium">{{ category.name }}</div>
                                        <div
                                            v-if="category.description"
                                            class="text-xs text-gray-500"
                                        >
                                            {{ category.description }}
                                        </div>
                                    </template>
                                </td>
                                <td class="px-4 py-3 align-top text-gray-700">
                                    <template v-if="editingId === category.id">
                                        <select
                                            v-model="editForm.parent_id"
                                            class="w-full rounded-md border-gray-300 text-sm shadow-sm"
                                        >
                                            <option value="">Yok</option>
                                            <option
                                                v-for="option in parentOptions"
                                                :key="option.id"
                                                :value="option.id"
                                                :disabled="option.id === category.id"
                                            >
                                                {{ option.name }}
                                            </option>
                                        </select>
                                    </template>
                                    <template v-else>
                                        {{ category.parent?.name || '—' }}
                                    </template>
                                </td>
                                <td class="px-4 py-3 align-top text-gray-700">
                                    {{ category.products_count }}
                                </td>
                                <td
                                    v-if="canManage"
                                    class="space-x-2 px-4 py-3 text-right align-top"
                                >
                                    <template v-if="editingId === category.id">
                                        <PrimaryButton
                                            class="!px-3 !py-1"
                                            @click="submitEdit(category)"
                                        >
                                            Kaydet
                                        </PrimaryButton>
                                        <SecondaryButton
                                            class="!px-3 !py-1"
                                            @click="cancelEdit"
                                        >
                                            İptal
                                        </SecondaryButton>
                                    </template>
                                    <template v-else>
                                        <SecondaryButton
                                            class="!px-3 !py-1"
                                            @click="startEdit(category)"
                                        >
                                            Düzenle
                                        </SecondaryButton>
                                        <DangerButton
                                            class="!px-3 !py-1"
                                            @click="destroyCategory(category)"
                                        >
                                            Sil
                                        </DangerButton>
                                    </template>
                                </td>
                            </tr>
                            <tr v-if="categories.length === 0">
                                <td
                                    class="px-4 py-8 text-center text-gray-500"
                                    :colspan="canManage ? 4 : 3"
                                >
                                    Henüz kategori yok.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
