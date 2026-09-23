<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, required: true },
    categories: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const search = ref(props.filters.search || '');
const categoryId = ref(props.filters.category_id || '');

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
    <Head title="Products" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Products & stock
                </h2>
                <Link
                    v-if="canManage"
                    :href="route('products.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                >
                    New product
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

                <div class="grid gap-4 bg-white p-4 shadow-sm sm:grid-cols-2 sm:rounded-lg">
                    <div>
                        <InputLabel for="search" value="Search" />
                        <TextInput
                            id="search"
                            v-model="search"
                            class="mt-1 block w-full"
                            placeholder="Name or SKU"
                        />
                    </div>
                    <div>
                        <InputLabel for="category_id" value="Category" />
                        <select
                            id="category_id"
                            v-model="categoryId"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All categories</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Product</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Category</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Piece</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Pallet</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="product in products.data" :key="product.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">
                                        {{ product.name }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ product.sku || 'No SKU' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ product.category?.name || '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ product.quantity_piece }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ product.quantity_pallet }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="product.is_low_stock"
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                    >
                                        Low stock
                                    </span>
                                    <span
                                        v-else-if="!product.is_active"
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                                    >
                                        Inactive
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800"
                                    >
                                        OK
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('products.edit', product.id)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                    >
                                        Open
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="products.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    No products found.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="products.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3"
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
                            :preserve-scroll="true"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
