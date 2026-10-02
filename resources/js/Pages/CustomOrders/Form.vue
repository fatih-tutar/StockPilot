<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    order: { type: Object, default: null },
    clients: { type: Array, required: true },
    factories: { type: Array, required: true },
    deliveryOptions: { type: Array, required: true },
    statusOptions: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const isEdit = computed(() => !!props.order?.id);
const locked = computed(() => isEdit.value && !props.canManage);

const nowStamp = () => new Date().toISOString().slice(0, 16);

const emptyItem = () => ({
    id: null,
    product_name: '',
    length: '',
    factory_id: '',
    quantity: 1,
    unit_price: 0,
    due_on: '',
});

const form = useForm({
    client_id: props.order?.client_id || '',
    delivery_method: props.order?.delivery_method || 'we_ship',
    status: props.order?.status || 'open',
    notes: props.order?.notes || '',
    ordered_at: props.order?.ordered_at || nowStamp(),
    items:
        props.order?.items?.length > 0
            ? props.order.items.map((item) => ({
                  id: item.id,
                  product_name: item.product_name,
                  length: item.length || '',
                  factory_id: item.factory_id || '',
                  quantity: item.quantity,
                  unit_price: item.unit_price,
                  due_on: item.due_on || '',
              }))
            : [emptyItem()],
});

const deleteForm = useForm({});

const fieldClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

const addItem = () => {
    form.items.push(emptyItem());
};

const removeItem = (index) => {
    if (form.items.length === 1) {
        form.items[0] = emptyItem();
        return;
    }

    form.items.splice(index, 1);
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('custom-orders.update', props.order.id));
        return;
    }

    form.post(route('custom-orders.store'));
};

const destroyOrder = () => {
    if (!confirm('Bu özel sipariş silinsin mi?')) {
        return;
    }

    deleteForm.delete(route('custom-orders.destroy', props.order.id));
};
</script>

<template>
    <Head :title="isEdit ? 'Özel siparişi düzenle' : 'Yeni özel sipariş'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? 'Özel sipariş' : 'Yeni özel sipariş' }}
                </h2>
                <Link
                    :href="route('custom-orders.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Siparişlere dön
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
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

                <form class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="client_id" value="Müşteri" />
                            <select
                                id="client_id"
                                v-model="form.client_id"
                                :class="fieldClass"
                                :disabled="locked"
                                required
                            >
                                <option value="">Müşteri seçin</option>
                                <option v-for="client in clients" :key="client.id" :value="client.id">
                                    {{ client.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.client_id" />
                        </div>
                        <div>
                            <InputLabel for="delivery_method" value="Teslim" />
                            <select
                                id="delivery_method"
                                v-model="form.delivery_method"
                                :class="fieldClass"
                                :disabled="locked"
                            >
                                <option
                                    v-for="option in deliveryOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.delivery_method" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="ordered_at" value="Sipariş zamanı" />
                            <TextInput
                                id="ordered_at"
                                v-model="form.ordered_at"
                                type="datetime-local"
                                class="mt-1 block w-full"
                                :disabled="locked"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.ordered_at" />
                        </div>
                        <div>
                            <InputLabel for="status" value="Durum" />
                            <select
                                id="status"
                                v-model="form.status"
                                :class="fieldClass"
                                :disabled="locked"
                            >
                                <option
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.status" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="notes" value="Not" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            :class="fieldClass"
                            :disabled="locked"
                        />
                        <InputError class="mt-2" :message="form.errors.notes" />
                    </div>

                    <div class="space-y-4 border-t border-gray-100 pt-4">
                        <div class="flex items-center justify-between gap-4">
                            <h3 class="text-sm font-medium text-gray-800">Kalemler</h3>
                            <button
                                v-if="!locked"
                                type="button"
                                class="text-sm text-indigo-600 hover:text-indigo-800"
                                @click="addItem"
                            >
                                Kalem ekle
                            </button>
                        </div>

                        <div
                            v-for="(item, index) in form.items"
                            :key="item.id || index"
                            class="grid gap-3 rounded-md border border-gray-100 p-4 sm:grid-cols-6"
                        >
                            <div class="sm:col-span-2">
                                <InputLabel :for="`product-${index}`" value="Ürün" />
                                <TextInput
                                    :id="`product-${index}`"
                                    v-model="item.product_name"
                                    class="mt-1 block w-full"
                                    :disabled="locked"
                                />
                                <InputError class="mt-2" :message="form.errors[`items.${index}.product_name`]" />
                            </div>
                            <div>
                                <InputLabel :for="`length-${index}`" value="Ölçü" />
                                <TextInput
                                    :id="`length-${index}`"
                                    v-model="item.length"
                                    class="mt-1 block w-full"
                                    :disabled="locked"
                                />
                            </div>
                            <div>
                                <InputLabel :for="`factory-${index}`" value="Fabrika" />
                                <select
                                    :id="`factory-${index}`"
                                    v-model="item.factory_id"
                                    :class="fieldClass"
                                    :disabled="locked"
                                >
                                    <option value="">Seçilmedi</option>
                                    <option
                                        v-for="factory in factories"
                                        :key="factory.id"
                                        :value="factory.id"
                                    >
                                        {{ factory.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel :for="`quantity-${index}`" value="Adet" />
                                <TextInput
                                    :id="`quantity-${index}`"
                                    v-model="item.quantity"
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    class="mt-1 block w-full"
                                    :disabled="locked"
                                />
                            </div>
                            <div>
                                <InputLabel :for="`price-${index}`" value="Fiyat" />
                                <TextInput
                                    :id="`price-${index}`"
                                    v-model="item.unit_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                    :disabled="locked"
                                />
                            </div>
                            <div>
                                <InputLabel :for="`due-${index}`" value="Termin" />
                                <TextInput
                                    :id="`due-${index}`"
                                    v-model="item.due_on"
                                    type="date"
                                    class="mt-1 block w-full"
                                    :disabled="locked"
                                />
                            </div>
                            <div v-if="!locked" class="flex items-end">
                                <button
                                    type="button"
                                    class="text-sm text-red-600 hover:text-red-800"
                                    @click="removeItem(index)"
                                >
                                    Kaldır
                                </button>
                            </div>
                        </div>
                        <InputError :message="form.errors.items" />
                    </div>

                    <div v-if="!isEdit || canManage" class="flex gap-3">
                        <PrimaryButton :disabled="form.processing">
                            {{ isEdit ? 'Siparişi kaydet' : 'Sipariş oluştur' }}
                        </PrimaryButton>
                        <DangerButton
                            v-if="isEdit && canManage"
                            type="button"
                            :disabled="deleteForm.processing"
                            @click="destroyOrder"
                        >
                            Sil
                        </DangerButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
