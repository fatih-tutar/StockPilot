<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    order: { type: Object, default: null },
    factories: { type: Array, required: true },
    users: { type: Array, required: true },
    products: { type: Array, required: true },
    filters: { type: Object, required: true },
    selectedFactoryId: { type: Number, default: null },
});

const isEdit = computed(() => !!props.order?.id);
const productSearch = ref(props.filters.product || '');

const form = useForm({
    factory_id: props.order?.factory_id || props.selectedFactoryId || '',
    product_id: props.order?.product_id || props.filters.product_id || '',
    quantity: props.order?.quantity ?? '',
    length: props.order?.length || '',
    pallet_count: props.order?.pallet_count ?? 0,
    due_on: props.order?.due_on || '',
    contact_name: props.order?.contact_name || '',
    prepared_by_user_id: props.order?.prepared_by_user_id || '',
});

watch(productSearch, (value) => {
    router.get(
        isEdit.value ? route('factory-orders.edit', props.order.id) : route('factory-orders.create'),
        {
            product: value || undefined,
            product_id: form.product_id || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true, only: ['products', 'filters'] },
    );
});

const chooseProduct = (product) => {
    form.product_id = product.id;
    if (product.factory_id) {
        form.factory_id = product.factory_id;
    }
};

const submit = () => {
    const payload = {
        ...form.data(),
        factory_id: Number(form.factory_id),
        product_id: Number(form.product_id),
        quantity: Number(form.quantity),
        pallet_count: Number(form.pallet_count || 0),
        prepared_by_user_id: form.prepared_by_user_id ? Number(form.prepared_by_user_id) : null,
    };

    if (isEdit.value) {
        form.transform(() => payload).put(route('factory-orders.update', props.order.id));
    } else {
        form.transform(() => payload).post(route('factory-orders.store'));
    }
};

const destroyOrder = () => {
    if (confirm('Bu sipariş silinsin mi?')) {
        router.delete(route('factory-orders.destroy', props.order.id));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Siparişi düzenle' : 'Yeni fabrika siparişi'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? 'Siparişi düzenle' : 'Yeni fabrika siparişi' }}
                </h2>
                <Link
                    :href="route('factory-orders.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Siparişlere dön
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div>
                        <InputLabel for="product_search" value="Ürün ara" />
                        <TextInput
                            id="product_search"
                            v-model="productSearch"
                            class="mt-1 block w-full"
                            placeholder="Ad veya stok kodu"
                        />
                        <div v-if="products.length" class="mt-2 max-h-40 overflow-y-auto rounded-md border border-gray-200">
                            <button
                                v-for="product in products"
                                :key="product.id"
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50"
                                :class="Number(form.product_id) === product.id ? 'bg-gray-100 font-medium' : ''"
                                @click="chooseProduct(product)"
                            >
                                {{ product.name }}
                                <span v-if="product.sku" class="text-gray-500"> · {{ product.sku }}</span>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.product_id" />
                    </div>

                    <div>
                        <InputLabel for="factory_id" value="Fabrika" />
                        <select
                            id="factory_id"
                            v-model="form.factory_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Seçin</option>
                            <option v-for="factory in factories" :key="factory.id" :value="factory.id">
                                {{ factory.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.factory_id" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel for="quantity" value="Adet" />
                            <TextInput id="quantity" v-model="form.quantity" type="number" min="1" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.quantity" />
                        </div>
                        <div>
                            <InputLabel for="length" value="Boy" />
                            <TextInput id="length" v-model="form.length" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.length" />
                        </div>
                        <div>
                            <InputLabel for="pallet_count" value="Palet" />
                            <TextInput id="pallet_count" v-model="form.pallet_count" type="number" min="0" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.pallet_count" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="due_on" value="Termin" />
                            <TextInput id="due_on" v-model="form.due_on" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.due_on" />
                        </div>
                        <div>
                            <InputLabel for="contact_name" value="İlgili kişi" />
                            <TextInput id="contact_name" v-model="form.contact_name" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.contact_name" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="prepared_by_user_id" value="Hazırlayan" />
                        <select
                            id="prepared_by_user_id"
                            v-model="form.prepared_by_user_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Oturumdaki kullanıcı</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.prepared_by_user_id" />
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <button
                            v-if="isEdit"
                            type="button"
                            class="text-sm text-red-600 hover:text-red-800"
                            @click="destroyOrder"
                        >
                            Sil
                        </button>
                        <span v-else />
                        <PrimaryButton :disabled="form.processing">Kaydet</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
