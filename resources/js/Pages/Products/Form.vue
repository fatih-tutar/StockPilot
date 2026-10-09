<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Editor from '@/Pages/Products/Editor.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    product: { type: Object, default: null },
    categories: { type: Array, required: true },
    fields: { type: Array, default: () => [] },
    factories: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const columns = computed(() => page.props.auth?.user?.columns ?? {});
const seesStockNumbers = computed(() => columns.value.piece || columns.value.pallet || columns.value.alkop);
const flashSuccess = computed(() => page.props.flash?.success);
const canOrder = computed(() => {
    const permissions = page.props.auth?.user?.permissions || [];

    return permissions.includes('factory_orders.manage');
});
const flashError = computed(() => page.props.flash?.error);
const isEdit = computed(() => !!props.product?.id);

const adjustForm = useForm({
    quantity_piece_delta: 0,
    quantity_pallet_delta: 0,
    note: '',
});

const submitAdjust = () => {
    adjustForm
        .transform((data) => ({
            ...data,
            quantity_piece_delta: Number(data.quantity_piece_delta || 0),
            quantity_pallet_delta: Number(data.quantity_pallet_delta || 0),
        }))
        .post(route('products.adjust-stock', props.product.id), {
            preserveScroll: true,
            onSuccess: () => adjustForm.reset(),
        });
};
</script>

<template>
    <Head :title="isEdit ? 'Ürünü düzenle' : 'Yeni ürün'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? product.name : 'Yeni ürün' }}
                </h2>
                <div class="flex items-center gap-4">
                    <Link
                        v-if="isEdit && canOrder"
                        :href="route('factory-orders.create', { product_id: product.id })"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        Fabrika siparişi
                    </Link>
                    <Link
                        :href="route('products.index')"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        Ürünlere dön
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
                <div
                    v-if="flashError"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {{ flashError }}
                </div>

                <Editor
                    :product="product"
                    :categories="categories"
                    :fields="fields"
                    :factories="factories"
                    :can-manage="canManage"
                />

                <div
                    v-if="isEdit && (seesStockNumbers || canManage)"
                    class="bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <h3 class="mb-2 text-lg font-medium text-gray-900">
                        Güncel stok
                    </h3>
                    <p v-if="seesStockNumbers" class="text-sm text-gray-600">
                        <span v-if="columns.piece">
                            Adet:
                            <span class="font-semibold text-gray-900">
                                {{ product.quantity_piece }}
                            </span>
                        </span>
                        <span v-if="columns.pallet">
                            <span v-if="columns.piece"> · </span>
                            Palet:
                            <span class="font-semibold text-gray-900">
                                {{ product.quantity_pallet }}
                            </span>
                        </span>
                        <span
                            v-if="product.is_low_stock"
                            class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                        >
                            Düşük stok
                        </span>
                    </p>

                    <form
                        v-if="canManage"
                        class="mt-4 grid gap-3"
                        @submit.prevent="submitAdjust"
                    >
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel v-if="columns.piece" value="Adet farkı (+/-)" />
                                <TextInput
                                    v-if="columns.piece"
                                    v-model="adjustForm.quantity_piece_delta"
                                    type="number"
                                    class="mt-1 block w-full"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="adjustForm.errors.quantity_piece_delta"
                                />
                            </div>
                            <div>
                                <InputLabel v-if="columns.pallet" value="Palet farkı (+/-)" />
                                <TextInput
                                    v-if="columns.pallet"
                                    v-model="adjustForm.quantity_pallet_delta"
                                    type="number"
                                    class="mt-1 block w-full"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="adjustForm.errors.quantity_pallet_delta"
                                />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Not" />
                            <TextInput
                                v-model="adjustForm.note"
                                class="mt-1 block w-full"
                                placeholder="örn. sayım düzeltmesi"
                            />
                        </div>
                        <PrimaryButton :disabled="adjustForm.processing">
                            Stok düzelt
                        </PrimaryButton>
                    </form>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
