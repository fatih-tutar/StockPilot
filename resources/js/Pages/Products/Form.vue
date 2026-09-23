<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    product: { type: Object, default: null },
    categories: { type: Array, required: true },
    movements: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const isEdit = computed(() => !!props.product?.id);

const form = useForm({
    category_id: props.product?.category_id || '',
    sku: props.product?.sku || '',
    name: props.product?.name || '',
    description: props.product?.description || '',
    quantity_piece: props.product?.quantity_piece ?? 0,
    quantity_pallet: props.product?.quantity_pallet ?? 0,
    low_stock_threshold: props.product?.low_stock_threshold ?? '',
    is_active: props.product?.is_active ?? true,
});

const adjustForm = useForm({
    quantity_piece_delta: 0,
    quantity_pallet_delta: 0,
    note: '',
});

const deleteForm = useForm({});

const submit = () => {
    const payload = {
        ...form.data(),
        category_id: Number(form.category_id),
        low_stock_threshold:
            form.low_stock_threshold === '' || form.low_stock_threshold === null
                ? null
                : Number(form.low_stock_threshold),
    };

    if (isEdit.value) {
        form.transform(() => payload).put(route('products.update', props.product.id), {
            preserveScroll: true,
        });
    } else {
        form.transform(() => ({
            ...payload,
            quantity_piece: Number(form.quantity_piece || 0),
            quantity_pallet: Number(form.quantity_pallet || 0),
        })).post(route('products.store'));
    }
};

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

const destroyProduct = () => {
    if (!confirm('Bu ürün silinsin mi?')) {
        return;
    }

    deleteForm.delete(route('products.destroy', props.product.id));
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
                <Link
                    :href="route('products.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Ürünlere dön
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

                <form
                    class="space-y-4 bg-white p-6 shadow-sm sm:rounded-lg"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="name" value="Ad" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                class="mt-1 block w-full"
                                required
                                :disabled="isEdit && !canManage"
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="sku" value="SKU" />
                            <TextInput
                                id="sku"
                                v-model="form.sku"
                                class="mt-1 block w-full"
                                :disabled="isEdit && !canManage"
                            />
                            <InputError class="mt-2" :message="form.errors.sku" />
                        </div>
                        <div>
                            <InputLabel for="category_id" value="Kategori" />
                            <select
                                id="category_id"
                                v-model="form.category_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                                :disabled="isEdit && !canManage"
                            >
                                <option value="" disabled>Kategori seçin</option>
                                <option
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.category_id" />
                        </div>
                        <div>
                            <InputLabel for="low_stock_threshold" value="Düşük stok eşiği (adet)" />
                            <TextInput
                                id="low_stock_threshold"
                                v-model="form.low_stock_threshold"
                                type="number"
                                min="0"
                                class="mt-1 block w-full"
                                :disabled="isEdit && !canManage"
                            />
                            <InputError class="mt-2" :message="form.errors.low_stock_threshold" />
                        </div>
                        <div v-if="!isEdit">
                            <InputLabel for="quantity_piece" value="Açılış adet" />
                            <TextInput
                                id="quantity_piece"
                                v-model="form.quantity_piece"
                                type="number"
                                min="0"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.quantity_piece" />
                        </div>
                        <div v-if="!isEdit">
                            <InputLabel for="quantity_pallet" value="Açılış palet" />
                            <TextInput
                                id="quantity_pallet"
                                v-model="form.quantity_pallet"
                                type="number"
                                min="0"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.quantity_pallet" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="description" value="Açıklama" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :disabled="isEdit && !canManage"
                            />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>
                        <div class="flex items-center gap-2 md:col-span-2">
                            <Checkbox
                                id="is_active"
                                v-model:checked="form.is_active"
                                :disabled="isEdit && !canManage"
                            />
                            <InputLabel for="is_active" value="Aktif" />
                        </div>
                    </div>

                    <div v-if="!isEdit || canManage" class="flex gap-3">
                        <PrimaryButton :disabled="form.processing">
                            {{ isEdit ? 'Ürünü kaydet' : 'Ürün oluştur' }}
                        </PrimaryButton>
                        <DangerButton
                            v-if="isEdit && canManage"
                            type="button"
                            :disabled="deleteForm.processing"
                            @click="destroyProduct"
                        >
                            Sil
                        </DangerButton>
                    </div>
                </form>

                <div
                    v-if="isEdit"
                    class="grid gap-6 lg:grid-cols-2"
                >
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="mb-2 text-lg font-medium text-gray-900">
                            Güncel stok
                        </h3>
                        <p class="text-sm text-gray-600">
                            Adet:
                            <span class="font-semibold text-gray-900">
                                {{ product.quantity_piece }}
                            </span>
                            · Palet:
                            <span class="font-semibold text-gray-900">
                                {{ product.quantity_pallet }}
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
                                    <InputLabel value="Adet farkı (+/-)" />
                                    <TextInput
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
                                    <InputLabel value="Palet farkı (+/-)" />
                                    <TextInput
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

                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="mb-4 text-lg font-medium text-gray-900">
                            Son hareketler
                        </h3>
                        <ul class="space-y-3 text-sm">
                            <li
                                v-for="movement in movements"
                                :key="movement.id"
                                class="rounded border border-gray-100 px-3 py-2"
                            >
                                <div class="font-medium text-gray-900">
                                    {{ movement.type }}
                                    · adet {{ movement.quantity_piece_delta }}
                                    · palet {{ movement.quantity_pallet_delta }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ movement.created_at }}
                                    <span v-if="movement.user">
                                        · {{ movement.user.name }}
                                    </span>
                                </div>
                                <div v-if="movement.note" class="text-xs text-gray-600">
                                    {{ movement.note }}
                                </div>
                            </li>
                            <li v-if="movements.length === 0" class="text-gray-500">
                                Henüz hareket yok.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
