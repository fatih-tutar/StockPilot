<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    categories: { type: Array, required: true },
    parentOptions: { type: Array, required: true },
    columnGroups: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const form = useForm({
    name: '',
    parent_id: '',
    sort_order: 0,
    column_ids: [],
});

const showForm = ref(false);
const editingCategory = ref(null);
const deletingCategory = ref(null);
const deleteForm = useForm({});

const groups = computed(() => {
    const byId = new Map(props.categories.map((category) => [category.id, category]));

    const rootOf = (category) => {
        let current = category;
        const seen = new Set();

        while (current.parent && !seen.has(current.id)) {
            seen.add(current.id);
            const parent = byId.get(current.parent.id);

            if (!parent) {
                break;
            }

            current = parent;
        }

        return current;
    };

    return props.categories
        .filter((category) => rootOf(category).id === category.id)
        .map((root) => ({
            root,
            children: props.categories.filter(
                (category) => category.id !== root.id && rootOf(category).id === root.id,
            ),
        }));
});

const columns = computed(() => {
    const stacks = [[], []];

    groups.value.forEach((group, index) => {
        stacks[index % 2].push({ ...group, order: index });
    });

    return stacks;
});

const openCreate = () => {
    editingCategory.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (category) => {
    editingCategory.value = category;
    form.name = category.name;
    form.parent_id = category.parent?.id || '';
    form.sort_order = category.sort_order ?? 0;
    form.column_ids = [...(category.column_ids || [])];
    form.clearErrors();
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
};

const showsColumns = computed(() => form.parent_id === '' || form.parent_id === null);

const toggleColumn = (id) => {
    form.column_ids = form.column_ids.includes(id)
        ? form.column_ids.filter((columnId) => columnId !== id)
        : [...form.column_ids, id];
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeForm(),
    };
    const transformed = form.transform((data) => ({
        ...data,
        parent_id: data.parent_id === '' ? null : Number(data.parent_id),
        sort_order: Number(data.sort_order || 0),
        column_ids: data.parent_id === '' || data.parent_id === null ? data.column_ids : [],
    }));

    if (editingCategory.value) {
        transformed.put(route('categories.update', editingCategory.value.id), options);

        return;
    }

    transformed.post(route('categories.store'), options);
};

const confirmDelete = () => {
    deleteForm.delete(route('categories.destroy', deletingCategory.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deletingCategory.value = null;
        },
    });
};
</script>

<template>
    <Head title="Kategoriler" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Kategoriler
                </h2>
                <PrimaryButton v-if="canManage" type="button" @click="openCreate">
                    Yeni kategori
                </PrimaryButton>
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

                <div v-if="groups.length === 0" class="bg-white px-4 py-8 text-center text-sm text-gray-500 shadow-sm sm:rounded-lg">
                    Henüz kategori yok.
                </div>
                <div v-else class="flex flex-col gap-4 lg:grid lg:grid-cols-2 lg:items-start">
                    <div
                        v-for="(stack, stackIndex) in columns"
                        :key="stackIndex"
                        class="contents lg:block lg:space-y-4"
                    >
                    <section
                        v-for="group in stack"
                        :key="group.root.id"
                        class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                        :style="{ order: group.order }"
                    >
                        <div class="flex items-center justify-between gap-3 bg-gray-50 px-4 py-3">
                            <div class="min-w-0 font-semibold text-gray-900">{{ group.root.name }}</div>
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="text-sm text-gray-600">{{ group.root.products_count }}</span>
                                <div v-if="canManage" class="flex gap-2">
                                    <SecondaryButton class="!px-3 !py-1" @click="openEdit(group.root)">
                                        Düzenle
                                    </SecondaryButton>
                                    <DangerButton class="!px-3 !py-1" @click="deletingCategory = group.root">
                                        Sil
                                    </DangerButton>
                                </div>
                            </div>
                        </div>
                        <ul v-if="group.children.length" class="divide-y divide-gray-100 text-sm">
                            <li
                                v-for="category in group.children"
                                :key="category.id"
                                class="flex items-center justify-between gap-3 px-4 py-2"
                            >
                                <div
                                    class="min-w-0 font-medium text-gray-900"
                                    :class="category.parent?.id === group.root.id ? 'pl-4' : 'pl-8'"
                                >
                                    {{ category.name }}
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <span class="text-gray-600">{{ category.products_count }}</span>
                                    <div v-if="canManage" class="flex gap-2">
                                        <SecondaryButton class="!px-3 !py-1" @click="openEdit(category)">
                                            Düzenle
                                        </SecondaryButton>
                                        <DangerButton class="!px-3 !py-1" @click="deletingCategory = category">
                                            Sil
                                        </DangerButton>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </section>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showForm" max-width="2xl" @close="closeForm">
            <form class="max-h-[80vh] overflow-y-auto p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editingCategory ? 'Kategoriyi düzenle' : 'Yeni kategori' }}
                </h2>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="name" value="Ad" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="parent_id" value="Üst kategori (opsiyonel)" />
                        <select
                            id="parent_id"
                            v-model="form.parent_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Yok</option>
                            <option
                                v-for="option in parentOptions"
                                :key="option.id"
                                :value="option.id"
                                :disabled="option.id === editingCategory?.id"
                            >
                                {{ option.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.parent_id" />
                    </div>
                </div>

                <div v-if="showsColumns" class="mt-6 space-y-4">
                    <p class="text-sm font-medium text-gray-800">Bu kategoride görünecek sütunlar</p>
                    <div v-for="group in columnGroups" :key="group.label" class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ group.label }}</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-4">
                            <label
                                v-for="column in group.columns"
                                :key="column.id"
                                class="flex items-center gap-2 text-sm text-gray-700"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300"
                                    :checked="form.column_ids.includes(column.id)"
                                    @change="toggleColumn(column.id)"
                                />
                                {{ column.label }}
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeForm">
                        İptal
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ editingCategory ? 'Kaydet' : 'Kategori oluştur' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="deletingCategory !== null" @close="deletingCategory = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Bu kategoriyi silmek istediğinize emin misiniz?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ deletingCategory?.name }} silinecek. Ürünü olan bir kategori silinemez.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="deletingCategory = null">
                        İptal
                    </SecondaryButton>
                    <DangerButton :disabled="deleteForm.processing" @click="confirmDelete">
                        Sil
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
