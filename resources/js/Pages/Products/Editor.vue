<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    product: { type: Object, default: null },
    categories: { type: Array, required: true },
    fields: { type: Array, default: () => [] },
    factories: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    modal: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const page = usePage();
const columns = computed(() => page.props.auth?.user?.columns ?? {});
const isEdit = computed(() => !!props.product?.id);
const show = (name) => props.fields.includes(name);

const form = useForm({
    category_id: props.product?.category_id || '',
    sku: props.product?.sku || '',
    name: props.product?.name || '',
    description: props.product?.description || '',
    quantity_piece: props.product?.quantity_piece ?? 0,
    quantity_pallet: props.product?.quantity_pallet ?? 0,
    warehouse_quantity: props.product?.warehouse_quantity ?? 0,
    shelf: props.product?.shelf || '',
    unit_weight_kg: props.product?.unit_weight_kg ?? '',
    length_measure: props.product?.length_measure || '',
    purchase_price: props.product?.purchase_price ?? '',
    sale_price: props.product?.sale_price ?? '',
    factory_id: props.product?.factory_id || '',
    customer_name: props.product?.customer_name || '',
    due_on: props.product?.due_on || '',
    default_order_quantity: props.product?.default_order_quantity ?? '',
    warehouse_low_stock_threshold: props.product?.warehouse_low_stock_threshold ?? '',
    low_stock_threshold: props.product?.low_stock_threshold ?? '',
    is_active: props.product?.is_active ?? true,
    mold_number: props.product?.mold_number || '',
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

    const optional = {
        shelf: 'shelf',
        unit_weight: 'unit_weight_kg',
        size_measure: 'length_measure',
        purchase_price: 'purchase_price',
        sales_price: 'sale_price',
        manual_sales: 'sale_price',
        factory: 'factory_id',
        customer_name: 'customer_name',
        due_date: 'due_on',
        order_quantity: 'default_order_quantity',
        warehouse_warning_count: 'warehouse_low_stock_threshold',
        quantity: 'quantity_piece',
        pallet: 'quantity_pallet',
        warehouse_quantity: 'warehouse_quantity',
    };

    Object.entries(optional).forEach(([column, key]) => {
        if (show(column)) {
            payload[key] = form[key] === '' ? null : form[key];
        }
    });

    if (isEdit.value) {
        form.transform(() => payload).put(route('products.update', props.product.id), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    } else {
        form.transform(() => ({
            ...payload,
            quantity_piece: Number(form.quantity_piece || 0),
            quantity_pallet: Number(form.quantity_pallet || 0),
        })).post(route('products.store'));
    }
};

const destroyProduct = () => {
    if (!confirm('Bu ürün silinsin mi?')) {
        return;
    }

    deleteForm.delete(route('products.destroy', props.product.id));
};
</script>

<template>
    <form
        :class="modal ? 'max-h-[80vh] overflow-y-auto p-6' : 'space-y-4 bg-white p-6 shadow-sm sm:rounded-lg'"
        @submit.prevent="submit"
    >
        <div v-if="modal">
            <h2 class="text-lg font-medium text-gray-900">Düzenle</h2>
            <p class="mt-1 text-sm text-gray-500">{{ product?.name }}</p>
        </div>

        <div :class="modal ? 'mt-6 grid gap-4 md:grid-cols-2' : 'grid gap-4 md:grid-cols-2'">
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
                <InputLabel for="sku" value="Kod" />
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
            <div v-if="!isEdit || show('warning_count')">
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
            <div v-if="!isEdit && columns.piece">
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
            <div v-if="!isEdit && columns.pallet">
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
            <div v-if="show('shelf')">
                <InputLabel for="shelf" value="Raf" />
                <TextInput id="shelf" v-model="form.shelf" class="mt-1 block w-full" :disabled="isEdit && !canManage" />
            </div>
            <div v-if="show('unit_weight')">
                <InputLabel for="unit_weight_kg" value="Birim kg" />
                <TextInput id="unit_weight_kg" v-model="form.unit_weight_kg" type="number" min="0" step="0.001" class="mt-1 block w-full" :disabled="isEdit && !canManage" />
            </div>
            <div v-if="show('size_measure')">
                <InputLabel for="length_measure" value="Boy ölçüsü" />
                <TextInput id="length_measure" v-model="form.length_measure" class="mt-1 block w-full" :disabled="isEdit && !canManage" />
            </div>
            <div v-if="show('purchase_price')">
                <InputLabel for="purchase_price" value="Alış" />
                <TextInput id="purchase_price" v-model="form.purchase_price" type="number" min="0" step="0.01" class="mt-1 block w-full" :disabled="isEdit && !canManage" />
            </div>
            <div v-if="show('sales_price') || show('manual_sales')">
                <InputLabel for="sale_price" value="Satış" />
                <TextInput id="sale_price" v-model="form.sale_price" type="number" min="0" step="0.01" class="mt-1 block w-full" :disabled="isEdit && !canManage" />
            </div>
            <div v-if="show('warehouse_quantity')">
                <InputLabel for="warehouse_quantity" value="Depo adet" />
                <TextInput id="warehouse_quantity" v-model="form.warehouse_quantity" type="number" min="0" class="mt-1 block w-full" :disabled="isEdit && !canManage" />
            </div>
            <div v-if="show('quantity') && isEdit">
                <InputLabel for="quantity_piece_edit" value="Adet" />
                <TextInput id="quantity_piece_edit" v-model="form.quantity_piece" type="number" min="0" class="mt-1 block w-full" :disabled="!canManage" />
            </div>
            <div v-if="show('pallet') && isEdit">
                <InputLabel for="quantity_pallet_edit" value="Palet" />
                <TextInput id="quantity_pallet_edit" v-model="form.quantity_pallet" type="number" min="0" class="mt-1 block w-full" :disabled="!canManage" />
            </div>
            <div v-if="show('factory')">
                <InputLabel for="factory_id" value="Fabrika" />
                <select id="factory_id" v-model="form.factory_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" :disabled="isEdit && !canManage">
                    <option value="">Fabrika yok</option>
                    <option v-for="factory in factories" :key="factory.id" :value="factory.id">{{ factory.name }}</option>
                </select>
            </div>
            <div v-if="show('customer_name')">
                <InputLabel for="customer_name" value="Müşteri ismi" />
                <TextInput id="customer_name" v-model="form.customer_name" class="mt-1 block w-full" :disabled="isEdit && !canManage" />
            </div>
            <div v-if="show('due_date')">
                <InputLabel for="due_on" value="Termin" />
                <TextInput id="due_on" v-model="form.due_on" type="date" class="mt-1 block w-full" :disabled="isEdit && !canManage" />
            </div>
            <div v-if="show('order_quantity')">
                <InputLabel for="default_order_quantity" value="Sipariş adedi" />
                <TextInput id="default_order_quantity" v-model="form.default_order_quantity" type="number" min="0" class="mt-1 block w-full" :disabled="isEdit && !canManage" />
            </div>
            <div v-if="show('warehouse_warning_count')">
                <InputLabel for="warehouse_low_stock_threshold" value="Depo uyarı adedi" />
                <TextInput id="warehouse_low_stock_threshold" v-model="form.warehouse_low_stock_threshold" type="number" min="0" class="mt-1 block w-full" :disabled="isEdit && !canManage" />
            </div>
            <div v-if="isEdit && product.factory_id" class="md:col-span-2">
                <InputLabel for="mold_number" :value="`Kalıp numarası (${product.factory_name || 'fabrika'})`" />
                <TextInput
                    id="mold_number"
                    v-model="form.mold_number"
                    class="mt-1 block w-full"
                    :disabled="!canManage"
                />
                <InputError class="mt-2" :message="form.errors.mold_number" />
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

        <div
            v-if="!isEdit || canManage"
            :class="modal ? 'mt-6 flex justify-end gap-3' : 'flex gap-3'"
        >
            <SecondaryButton v-if="modal" type="button" @click="emit('close')">İptal</SecondaryButton>
            <DangerButton
                v-if="isEdit && canManage"
                type="button"
                :disabled="deleteForm.processing"
                @click="destroyProduct"
            >
                Sil
            </DangerButton>
            <PrimaryButton :disabled="form.processing">
                {{ isEdit ? 'Ürünü kaydet' : 'Ürün oluştur' }}
            </PrimaryButton>
        </div>
    </form>
</template>
