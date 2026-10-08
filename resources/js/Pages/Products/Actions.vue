<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    product: { type: Object, required: true },
    actions: { type: Array, default: () => [] },
    factories: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
});

const open = ref(null);

const quoteForm = useForm({
    client_name: '',
    quantity_piece: 1,
    unit_price: props.product.sale_price ?? '',
});

const orderForm = useForm({
    factory_id: props.product.factory_id || '',
    quantity: props.product.default_order_quantity || 1,
    length: props.product.length_measure || '',
    pallet_count: '',
    due_on: '',
    contact_name: '',
    prepared_by_user_id: '',
});

const shipForm = useForm({
    client_name: '',
    quantity_piece: 1,
    quantity_pallet: 0,
});

const toggle = (name) => {
    open.value = open.value === name ? null : name;
};

const send = (form, routeName) => {
    form.post(route(routeName, props.product.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = null;
        },
    });
};
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
            <button
                v-if="actions.includes('offer_button')"
                type="button"
                class="rounded-md bg-amber-500 px-3 py-1 text-xs font-semibold text-white"
                @click="toggle('offer')"
            >
                Teklif
            </button>
            <button
                v-if="actions.includes('order_button')"
                type="button"
                class="rounded-md bg-sky-700 px-3 py-1 text-xs font-semibold text-white"
                @click="toggle('order')"
            >
                Sipariş
            </button>
            <button
                v-if="actions.includes('shipment_button')"
                type="button"
                class="rounded-md bg-gray-800 px-3 py-1 text-xs font-semibold text-white"
                @click="toggle('ship')"
            >
                Sevkiyat
            </button>
            <Link
                v-if="actions.includes('edit_button')"
                :href="route('products.edit', product.id)"
                class="rounded-md bg-green-700 px-3 py-1 text-xs font-semibold text-white"
            >
                Düzenle
            </Link>
        </div>

        <form
            v-if="open === 'offer'"
            class="grid gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 md:grid-cols-4"
            @submit.prevent="send(quoteForm, 'products.quote')"
        >
            <div>
                <InputLabel value="Müşteri" />
                <TextInput v-model="quoteForm.client_name" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="quoteForm.errors.client_name" />
            </div>
            <div>
                <InputLabel value="Adet" />
                <TextInput v-model="quoteForm.quantity_piece" type="number" min="1" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="quoteForm.errors.quantity_piece" />
            </div>
            <div>
                <InputLabel value="Kg satış fiyatı" />
                <TextInput v-model="quoteForm.unit_price" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="quoteForm.errors.unit_price" />
            </div>
            <div class="flex items-end">
                <PrimaryButton :disabled="quoteForm.processing">Teklife ekle</PrimaryButton>
            </div>
        </form>

        <form
            v-if="open === 'order'"
            class="grid gap-3 rounded-md border border-sky-200 bg-sky-50 p-3 md:grid-cols-3"
            @submit.prevent="send(orderForm, 'products.order')"
        >
            <div>
                <InputLabel value="Fabrika" />
                <select v-model="orderForm.factory_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Fabrika seçin</option>
                    <option v-for="factory in factories" :key="factory.id" :value="factory.id">{{ factory.name }}</option>
                </select>
                <InputError class="mt-1" :message="orderForm.errors.factory_id" />
            </div>
            <div>
                <InputLabel value="Hazırlayan" />
                <select v-model="orderForm.prepared_by_user_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Seçin</option>
                    <option v-for="person in staff" :key="person.id" :value="person.id">{{ person.name }}</option>
                </select>
            </div>
            <div>
                <InputLabel value="İlgili kişi" />
                <TextInput v-model="orderForm.contact_name" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel value="Miktar" />
                <TextInput v-model="orderForm.quantity" type="number" min="1" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="orderForm.errors.quantity" />
            </div>
            <div>
                <InputLabel value="Boy" />
                <TextInput v-model="orderForm.length" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel value="Termin" />
                <TextInput v-model="orderForm.due_on" type="date" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel value="Palet" />
                <TextInput v-model="orderForm.pallet_count" type="number" min="0" class="mt-1 block w-full" />
            </div>
            <div class="flex items-end">
                <PrimaryButton :disabled="orderForm.processing">Siparişe ekle</PrimaryButton>
            </div>
        </form>

        <form
            v-if="open === 'ship'"
            class="grid gap-3 rounded-md border border-gray-200 bg-gray-50 p-3 md:grid-cols-4"
            @submit.prevent="send(shipForm, 'products.ship')"
        >
            <div>
                <InputLabel value="Müşteri" />
                <TextInput v-model="shipForm.client_name" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="shipForm.errors.client_name" />
            </div>
            <div>
                <InputLabel value="Adet" />
                <TextInput v-model="shipForm.quantity_piece" type="number" min="1" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="shipForm.errors.quantity_piece" />
            </div>
            <div>
                <InputLabel value="Palet" />
                <TextInput v-model="shipForm.quantity_pallet" type="number" min="0" class="mt-1 block w-full" />
            </div>
            <div class="flex items-end">
                <PrimaryButton :disabled="shipForm.processing">Sevkiyata ekle</PrimaryButton>
            </div>
        </form>
    </div>
</template>
