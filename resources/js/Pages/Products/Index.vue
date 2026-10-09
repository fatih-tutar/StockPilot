<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Actions from '@/Pages/Products/Actions.vue';
import Editor from '@/Pages/Products/Editor.vue';
import RowMenu from '@/Pages/Products/RowMenu.vue';
import StockForm from '@/Pages/Products/StockForm.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, required: true },
    categories: { type: Array, required: true },
    sheet: { type: Object, default: null },
    factories: { type: Array, default: () => [] },
    vehicles: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const columns = computed(() => page.props.auth?.user?.columns ?? {});
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const search = ref(props.filters.search || '');
const showForm = ref(false);
const parentCategoryId = ref('');

const categoriesById = computed(() => new Map(props.categories.map((category) => [category.id, category])));

const rootOf = (category) => {
    let current = category;
    const seen = new Set();

    while (current?.parent_id && !seen.has(current.id)) {
        seen.add(current.id);
        const parent = categoriesById.value.get(current.parent_id);

        if (!parent) {
            break;
        }

        current = parent;
    }

    return current;
};

const descendantsOf = (rootValue) => {
    if (rootValue === '' || rootValue === null || rootValue === undefined) {
        return [];
    }

    const rootId = Number(rootValue);

    return props.categories.filter(
        (category) => category.parent_id !== null && rootOf(category)?.id === rootId,
    );
};

const incomingCategory = props.categories.find(
    (category) => String(category.id) === String(props.filters.category_id || ''),
);
const incomingIsSubcategory = incomingCategory?.parent_id != null;

const categoryId = ref(incomingIsSubcategory ? props.filters.category_id : '');
const filterParentId = ref(incomingCategory ? String(rootOf(incomingCategory).id) : '');
const form = useForm({
    category_id: '',
    sku: '',
    name: '',
    description: '',
    quantity_piece: '',
    quantity_pallet: '',
    warehouse_quantity: '',
    shelf: '',
    unit_weight_kg: '',
    length_measure: '',
    purchase_price: '',
    sale_price: '',
    factory_id: '',
    customer_name: '',
    due_on: '',
    default_order_quantity: '',
    warehouse_low_stock_threshold: '',
    low_stock_threshold: '',
    mold_number: '',
    is_active: true,
});

const mainCategories = computed(() => props.categories.filter((category) => category.parent_id === null));

const subcategories = computed(() => descendantsOf(parentCategoryId.value));

const filterSubcategories = computed(() => descendantsOf(filterParentId.value));

const selectMainCategory = (id) => {
    filterParentId.value = id;

    const stillVisible = filterSubcategories.value.some(
        (category) => String(category.id) === String(categoryId.value),
    );

    if (!stillVisible) {
        categoryId.value = '';
    }
};

const selectSubcategory = (id) => {
    categoryId.value = id;
};

const actionNames = ['offer_button', 'order_button', 'shipment_button', 'edit_button'];
const openForm = ref(null);
const editor = ref(null);
const activities = ref(null);
const detailLoading = ref(false);
let detailRequest = 0;

const openProduct = computed(() => {
    if (!openForm.value) {
        return null;
    }

    return props.products.data.find((item) => item.id === openForm.value.id) ?? null;
});

const actionsFor = (product) => {
    if (props.sheet) {
        return props.sheet.actions;
    }

    const category = props.categories.find(
        (item) => String(item.id) === String(product.category?.id),
    );

    return (category?.fields ?? []).filter((name) => actionNames.includes(name));
};

const closeAction = () => {
    openForm.value = null;
    editor.value = null;
    activities.value = null;
};

const selectAction = async (product, name) => {
    if (openForm.value?.id === product.id && openForm.value?.name === name) {
        closeAction();

        return;
    }

    openForm.value = { id: product.id, name };
    editor.value = null;
    activities.value = null;

    if (name !== 'edit' && name !== 'activity') {
        return;
    }

    const requestId = ++detailRequest;
    detailLoading.value = true;

    try {
        const { data } = await axios.get(route(name === 'edit' ? 'products.editor' : 'products.activities', product.id));

        if (requestId !== detailRequest || openForm.value?.id !== product.id || openForm.value?.name !== name) {
            return;
        }

        if (name === 'edit') {
            editor.value = data;
        } else {
            activities.value = data;
        }
    } finally {
        if (requestId === detailRequest) {
            detailLoading.value = false;
        }
    }
};

onMounted(() => {
    if (props.filters.category_id && !incomingIsSubcategory) {
        router.get(
            route('products.index'),
            {
                search: search.value || undefined,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }
});

const selectedCategory = computed(() =>
    props.categories.find((category) => String(category.id) === String(form.category_id)),
);

const selectedFields = computed(() => selectedCategory.value?.fields ?? []);

const show = (name) => selectedFields.value.includes(name);

const selectedFactoryName = computed(
    () => props.factories.find((factory) => String(factory.id) === String(form.factory_id))?.name,
);

const emptyToNull = (value) => (value === '' || value === null || value === undefined ? null : value);

const emptyToNumber = (value) => {
    const normalized = emptyToNull(value);

    return normalized === null ? null : Number(normalized);
};

const openCreate = () => {
    parentCategoryId.value = '';
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

watch(parentCategoryId, () => {
    form.category_id = '';
    form.clearErrors('category_id');
    form.clearErrors('parent_category_id');
});

const closeForm = () => {
    showForm.value = false;
};

const submit = () => {
    form.clearErrors('parent_category_id');
    form.clearErrors('category_id');

    if (parentCategoryId.value === '' || parentCategoryId.value === null) {
        form.setError('parent_category_id', 'Ana kategori seçin.');
    }

    if (form.category_id === '' || form.category_id === null) {
        form.setError('category_id', 'Alt kategori seçin.');
    }

    if (form.errors.parent_category_id || form.errors.category_id) {
        return;
    }

    form.transform((data) => ({
        ...data,
        category_id: Number(data.category_id),
        sku: emptyToNull(data.sku),
        quantity_piece: emptyToNumber(data.quantity_piece) ?? 0,
        quantity_pallet: emptyToNumber(data.quantity_pallet) ?? 0,
        warehouse_quantity: emptyToNumber(data.warehouse_quantity),
        unit_weight_kg: emptyToNumber(data.unit_weight_kg),
        purchase_price: emptyToNumber(data.purchase_price),
        sale_price: emptyToNumber(data.sale_price),
        factory_id: emptyToNumber(data.factory_id),
        default_order_quantity: emptyToNumber(data.default_order_quantity),
        warehouse_low_stock_threshold: emptyToNumber(data.warehouse_low_stock_threshold),
        low_stock_threshold: emptyToNumber(data.low_stock_threshold),
        shelf: emptyToNull(data.shelf),
        length_measure: emptyToNull(data.length_measure),
        customer_name: emptyToNull(data.customer_name),
        due_on: emptyToNull(data.due_on),
        mold_number: emptyToNull(data.mold_number),
        description: emptyToNull(data.description),
    })).post(route('products.store'), {
        preserveScroll: true,
        onSuccess: () => closeForm(),
    });
};

watch(
    [search, categoryId],
    () => {
        router.get(
            route('products.index'),
            {
                search: search.value || undefined,
                category_id: categoryId.value || undefined,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    },
    { deep: true },
);
</script>

<template>
    <Head title="Ürünler ve stok" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Ürünler ve stok
                </h2>
                <PrimaryButton v-if="canManage" type="button" @click="openCreate">
                    Yeni ürün
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

                <div class="flex flex-col gap-4 bg-white p-4 shadow-sm sm:rounded-lg">
                    <div class="flex flex-col gap-4 md:flex-row md:items-end">
                        <div class="flex w-full shrink-0 flex-col gap-2 md:w-64">
                            <InputLabel for="search" value="Ara" />
                            <TextInput
                                id="search"
                                v-model="search"
                                class="block w-full"
                                placeholder="Ad veya kod"
                            />
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <p class="text-sm font-medium text-gray-700">Ana kategori</p>
                            <div class="flex flex-wrap gap-2" role="tablist" aria-label="Ana kategori">
                                <button
                                    v-for="category in mainCategories"
                                    :key="category.id"
                                    type="button"
                                    role="tab"
                                    class="rounded-md px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    :class="
                                        String(filterParentId) === String(category.id)
                                            ? 'bg-gray-800 text-white'
                                            : 'bg-gray-100 text-gray-800 hover:bg-gray-200'
                                    "
                                    :aria-selected="String(filterParentId) === String(category.id)"
                                    @click="selectMainCategory(category.id)"
                                >
                                    {{ category.name }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="filterSubcategories.length" class="flex flex-col gap-2">
                        <p class="text-sm font-medium text-gray-700">Alt kategori</p>
                        <div class="flex flex-wrap gap-1" role="group" aria-label="Alt kategori">
                            <button
                                v-for="category in filterSubcategories"
                                :key="category.id"
                                type="button"
                                class="whitespace-nowrap rounded border px-1 py-0.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                :class="
                                    String(categoryId) === String(category.id)
                                        ? 'border-gray-800 bg-gray-800 text-white'
                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                "
                                :aria-pressed="String(categoryId) === String(category.id)"
                                @click="selectSubcategory(category.id)"
                            >
                                {{ category.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="sheet" class="overflow-visible bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-10 px-2 py-3">
                                    <span class="sr-only">İşlemler</span>
                                </th>
                                <th class="w-[28%] px-4 py-3 text-left font-medium text-gray-600">Ürün</th>
                                <th
                                    v-for="column in sheet.columns"
                                    :key="column.name"
                                    class="px-4 py-3 text-left font-medium text-gray-600"
                                    :class="column.name === 'factory' ? 'w-[18%] whitespace-nowrap' : 'whitespace-nowrap'"
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-for="product in products.data" :key="product.id">
                                <tr>
                                    <td class="px-2 py-2">
                                        <RowMenu
                                            :actions="actionsFor(product)"
                                            :can-stock="canManage"
                                            @select="selectAction(product, $event)"
                                        />
                                    </td>
                                    <td class="w-[28%] px-4 py-3 font-medium text-gray-900">{{ product.name }}</td>
                                    <td
                                        v-for="column in sheet.columns"
                                        :key="column.name"
                                        class="px-4 py-3 text-gray-700"
                                        :class="column.name === 'factory' ? 'w-[18%] whitespace-nowrap' : 'whitespace-nowrap'"
                                    >
                                        {{ product.cells?.[column.name] ?? '—' }}
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="products.data.length === 0">
                                <td :colspan="sheet.columns.length + 2" class="px-4 py-8 text-center text-gray-500">
                                    Ürün bulunamadı.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="overflow-visible bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-10 px-2 py-3">
                                    <span class="sr-only">İşlemler</span>
                                </th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Ürün</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Kategori</th>
                                <th v-if="columns.piece" class="px-4 py-3 text-left font-medium text-gray-600">Adet</th>
                                <th v-if="columns.pallet" class="px-4 py-3 text-left font-medium text-gray-600">Palet</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Durum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-for="product in products.data" :key="product.id">
                            <tr>
                                <td class="px-2 py-2">
                                    <RowMenu
                                        :actions="actionsFor(product)"
                                        :can-stock="canManage"
                                        @select="selectAction(product, $event)"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">
                                        {{ product.name }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ product.sku || 'Kod yok' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ product.category?.name || '—' }}
                                </td>
                                <td v-if="columns.piece" class="px-4 py-3 text-gray-700">
                                    {{ product.quantity_piece }}
                                </td>
                                <td v-if="columns.pallet" class="px-4 py-3 text-gray-700">
                                    {{ product.quantity_pallet }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="product.is_low_stock"
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                    >
                                        Düşük stok
                                    </span>
                                    <span
                                        v-else-if="!product.is_active"
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                                    >
                                        Pasif
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800"
                                    >
                                        Uygun
                                    </span>
                                </td>
                            </tr>
                            </template>
                            <tr v-if="products.data.length === 0">
                                <td :colspan="4 + (columns.piece ? 1 : 0) + (columns.pallet ? 1 : 0)" class="px-4 py-8 text-center text-gray-500">
                                    Ürün bulunamadı.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="products.links?.length > 3"
                    class="flex flex-wrap gap-2 rounded-lg border border-gray-100 bg-white px-4 py-3"
                >
                        <Link
                            v-for="link in products.links"
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

        <Modal :show="openForm !== null" max-width="2xl" @close="closeAction">
            <Actions
                v-if="openProduct && ['offer', 'order', 'ship'].includes(openForm.name)"
                :key="openProduct.id + openForm.name"
                :product="openProduct"
                :actions="actionsFor(openProduct)"
                :factories="factories"
                :vehicles="vehicles"
                :staff="staff"
                :buttons="false"
                :modal="true"
                :active="openForm.name"
                @close="closeAction"
            />
            <StockForm
                v-else-if="openForm.name === 'stock' && openProduct"
                :key="'stock-' + openProduct.id"
                :product="openProduct"
                @close="closeAction"
            />
            <Editor
                v-else-if="openForm.name === 'edit' && editor"
                :key="editor.product.id"
                :product="editor.product"
                :categories="editor.categories"
                :fields="editor.fields"
                :factories="editor.factories"
                :can-manage="editor.canManage"
                modal
                @close="closeAction"
            />
            <div
                v-else-if="openForm.name === 'activity' && activities"
                class="max-h-[80vh] overflow-y-auto p-6"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900">Son hareketler</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ openProduct?.name }}</p>
                    </div>
                    <Link
                        v-if="openProduct"
                        :href="route('stock-activities.index', { product_id: openProduct.id })"
                        class="text-sm text-indigo-700 hover:text-indigo-900"
                    >
                        Tüm işlemler
                    </Link>
                </div>
                <ul class="mt-6 space-y-3 text-sm">
                    <li
                        v-for="activity in activities"
                        :key="activity.id"
                        class="rounded border border-gray-100 px-3 py-2"
                    >
                        <div class="font-medium text-gray-900">
                            {{ activity.place }}
                            · {{ activity.previous_quantity }} → {{ activity.new_quantity }}
                            · fark {{ activity.difference > 0 ? `+${activity.difference}` : activity.difference }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ activity.recorded_at }}
                            <span v-if="activity.user">
                                · {{ activity.user.name }}
                            </span>
                        </div>
                        <div v-if="activity.note" class="text-xs text-gray-600">
                            {{ activity.note }}
                        </div>
                    </li>
                    <li v-if="activities.length === 0" class="text-gray-500">
                        Henüz hareket yok.
                    </li>
                </ul>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="closeAction">Kapat</SecondaryButton>
                </div>
            </div>
            <div v-else class="p-6 text-sm text-gray-500">
                {{ detailLoading ? 'Yükleniyor...' : 'Kayıt açılamadı.' }}
            </div>
        </Modal>

        <Modal :show="showForm" max-width="2xl" @close="closeForm">
            <form class="max-h-[80vh] overflow-y-auto p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    Yeni ürün
                </h2>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="product_name" value="Ad" />
                        <TextInput
                            id="product_name"
                            v-model="form.name"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="product_sku" value="Kod" />
                        <TextInput
                            id="product_sku"
                            v-model="form.sku"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.sku" />
                    </div>
                    <div>
                        <InputLabel for="product_parent_category_id" value="Ana kategori" />
                        <select
                            id="product_parent_category_id"
                            v-model="parentCategoryId"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Ana kategori seçin</option>
                            <option
                                v-for="category in mainCategories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.parent_category_id" />
                    </div>
                    <div>
                        <InputLabel for="product_category_id" value="Alt kategori" />
                        <select
                            id="product_category_id"
                            v-model="form.category_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :disabled="parentCategoryId === '' || parentCategoryId === null"
                        >
                            <option value="">
                                {{ parentCategoryId === '' || parentCategoryId === null ? 'Önce ana kategori seçin' : 'Alt kategori seçin' }}
                            </option>
                            <option
                                v-for="category in subcategories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.category_id" />
                    </div>
                    <div>
                        <InputLabel for="product_low_stock_threshold" value="Düşük stok eşiği (adet)" />
                        <TextInput
                            id="product_low_stock_threshold"
                            v-model="form.low_stock_threshold"
                            type="number"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.low_stock_threshold" />
                    </div>
                    <div v-if="columns.piece">
                        <InputLabel for="product_quantity_piece" value="Açılış adet" />
                        <TextInput
                            id="product_quantity_piece"
                            v-model="form.quantity_piece"
                            type="number"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.quantity_piece" />
                    </div>
                    <div v-if="columns.pallet">
                        <InputLabel for="product_quantity_pallet" value="Açılış palet" />
                        <TextInput
                            id="product_quantity_pallet"
                            v-model="form.quantity_pallet"
                            type="number"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.quantity_pallet" />
                    </div>
                    <div v-if="show('shelf')">
                        <InputLabel for="product_shelf" value="Raf" />
                        <TextInput id="product_shelf" v-model="form.shelf" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.shelf" />
                    </div>
                    <div v-if="show('unit_weight')">
                        <InputLabel for="product_unit_weight_kg" value="Birim kg" />
                        <TextInput
                            id="product_unit_weight_kg"
                            v-model="form.unit_weight_kg"
                            type="number"
                            min="0"
                            step="0.001"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.unit_weight_kg" />
                    </div>
                    <div v-if="show('size_measure')">
                        <InputLabel for="product_length_measure" value="Boy ölçüsü" />
                        <TextInput id="product_length_measure" v-model="form.length_measure" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.length_measure" />
                    </div>
                    <div v-if="show('purchase_price')">
                        <InputLabel for="product_purchase_price" value="Alış" />
                        <TextInput
                            id="product_purchase_price"
                            v-model="form.purchase_price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.purchase_price" />
                    </div>
                    <div v-if="show('sales_price') || show('manual_sales')">
                        <InputLabel for="product_sale_price" value="Satış" />
                        <TextInput
                            id="product_sale_price"
                            v-model="form.sale_price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.sale_price" />
                    </div>
                    <div v-if="show('warehouse_quantity')">
                        <InputLabel for="product_warehouse_quantity" value="Depo adet" />
                        <TextInput
                            id="product_warehouse_quantity"
                            v-model="form.warehouse_quantity"
                            type="number"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.warehouse_quantity" />
                    </div>
                    <div v-if="show('factory')">
                        <InputLabel for="product_factory_id" value="Fabrika" />
                        <select
                            id="product_factory_id"
                            v-model="form.factory_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Fabrika yok</option>
                            <option v-for="factory in factories" :key="factory.id" :value="factory.id">
                                {{ factory.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.factory_id" />
                    </div>
                    <div v-if="show('customer_name')">
                        <InputLabel for="product_customer_name" value="Müşteri ismi" />
                        <TextInput id="product_customer_name" v-model="form.customer_name" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.customer_name" />
                    </div>
                    <div v-if="show('due_date')">
                        <InputLabel for="product_due_on" value="Termin" />
                        <TextInput id="product_due_on" v-model="form.due_on" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.due_on" />
                    </div>
                    <div v-if="show('order_quantity')">
                        <InputLabel for="product_default_order_quantity" value="Sipariş adedi" />
                        <TextInput
                            id="product_default_order_quantity"
                            v-model="form.default_order_quantity"
                            type="number"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.default_order_quantity" />
                    </div>
                    <div v-if="show('warehouse_warning_count')">
                        <InputLabel for="product_warehouse_low_stock_threshold" value="Depo uyarı adedi" />
                        <TextInput
                            id="product_warehouse_low_stock_threshold"
                            v-model="form.warehouse_low_stock_threshold"
                            type="number"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.warehouse_low_stock_threshold" />
                    </div>
                    <div v-if="show('factory') && form.factory_id" class="md:col-span-2">
                        <InputLabel
                            for="product_mold_number"
                            :value="selectedFactoryName ? `Kalıp numarası (${selectedFactoryName})` : 'Kalıp numarası'"
                        />
                        <TextInput id="product_mold_number" v-model="form.mold_number" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.mold_number" />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel for="product_description" value="Açıklama" />
                        <textarea
                            id="product_description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError class="mt-2" :message="form.errors.description" />
                    </div>
                    <div class="flex items-center gap-2 md:col-span-2">
                        <Checkbox id="product_is_active" v-model:checked="form.is_active" />
                        <InputLabel for="product_is_active" value="Aktif" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeForm">
                        İptal
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        Ürün oluştur
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
