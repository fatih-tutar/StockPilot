<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    product: { type: Object, required: true },
    actions: { type: Array, default: () => [] },
    factories: { type: Array, default: () => [] },
    vehicles: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
    buttons: { type: Boolean, default: true },
    active: { type: String, default: null },
    modal: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const open = ref(null);

watch(
    () => props.active,
    (value) => {
        open.value = value;
    },
    { immediate: true },
);

const quoteForm = useForm({
    client_name: '',
    quantity_piece: 1,
    unit_price: props.product.sale_price ?? '',
});

const clientOptions = ref([]);
const clientOpen = ref(false);
const chosenClient = ref('');
let clientTimer = null;
let clientRequest = 0;

watch(
    () => quoteForm.client_name,
    (value) => {
        if (value !== chosenClient.value) {
            chosenClient.value = '';
        }
    },
);

const scheduleClientSearch = (event) => {
    window.clearTimeout(clientTimer);

    const term = String(event?.target?.value ?? quoteForm.client_name).trim();

    if (term === '') {
        clientOptions.value = [];
        clientOpen.value = false;

        return;
    }

    clientTimer = window.setTimeout(async () => {
        const requestId = ++clientRequest;
        const { data } = await axios.get(route('clients.search'), { params: { term } });

        if (requestId !== clientRequest) {
            return;
        }

        clientOptions.value = data;
        clientOpen.value = true;
    }, 200);
};

const pickClient = (client) => {
    chosenClient.value = client.name;
    quoteForm.client_name = client.name;
    clientOpen.value = false;
};

const hideClientResults = () => {
    window.setTimeout(() => {
        clientOpen.value = false;
    }, 150);
};

const shipTypes = [
    'Müşteri Çağlayan',
    'Müşteri Alkop',
    'Tarafımızca sevk',
    'Ambara tarafımızca sevk',
    'Kargo Teslim',
];

const shipForm = useForm({
    client_name: '',
    quantity_piece: '',
    ship_type: '',
    unit_price: '',
    vehicle_id: '',
    notes: '',
});

const shipClientOptions = ref([]);
const shipClientOpen = ref(false);
const chosenShipClient = ref('');
let shipClientTimer = null;
let shipClientRequest = 0;

watch(
    () => shipForm.client_name,
    (value) => {
        if (value !== chosenShipClient.value) {
            chosenShipClient.value = '';
        }
    },
);

const scheduleShipClientSearch = (event) => {
    window.clearTimeout(shipClientTimer);

    const term = String(event?.target?.value ?? shipForm.client_name).trim();

    if (term === '') {
        shipClientOptions.value = [];
        shipClientOpen.value = false;

        return;
    }

    shipClientTimer = window.setTimeout(async () => {
        const requestId = ++shipClientRequest;
        const { data } = await axios.get(route('clients.search'), { params: { term } });

        if (requestId !== shipClientRequest) {
            return;
        }

        shipClientOptions.value = data;
        shipClientOpen.value = true;
    }, 200);
};

const pickShipClient = (client) => {
    chosenShipClient.value = client.name;
    shipForm.client_name = client.name;
    shipClientOpen.value = false;
};

const hideShipClientResults = () => {
    window.setTimeout(() => {
        shipClientOpen.value = false;
    }, 150);
};

onUnmounted(() => {
    window.clearTimeout(clientTimer);
    window.clearTimeout(shipClientTimer);
});

const orderForm = useForm({
    factory_id: props.product.factory_id || '',
    quantity: props.product.default_order_quantity || 1,
    length: '6 metre',
    pallet_count: '',
    due_on: '',
    contact_name: '',
    prepared_by_user_id: '',
});

const toggle = (name) => {
    open.value = open.value === name ? null : name;
};

const dismiss = () => {
    open.value = null;
    emit('close');
};

const formClass = (name) => {
    if (props.modal) {
        return 'max-h-[80vh] overflow-y-auto p-6';
    }

    return {
        offer: 'grid gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 md:grid-cols-4',
        order: 'grid gap-3 rounded-md border border-sky-200 bg-sky-50 p-3 md:grid-cols-3',
        ship: 'grid gap-3 rounded-md border border-gray-200 bg-gray-50 p-3 md:grid-cols-2',
    }[name];
};

const send = (form, routeName) => {
    form.post(route(routeName, props.product.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = null;
            emit('close');
        },
    });
};
</script>

<template>
    <div v-if="buttons || open" :class="modal ? '' : 'space-y-3'">
        <div v-if="buttons" class="flex flex-wrap gap-2">
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
            :class="formClass('offer')"
            @submit.prevent="send(quoteForm, 'products.quote')"
        >
            <div v-if="modal">
                <h2 class="text-lg font-medium text-gray-900">Teklif</h2>
                <p class="mt-1 text-sm text-gray-500">{{ product.name }}</p>
            </div>
            <div :class="modal ? 'mt-6 grid gap-4 md:grid-cols-2' : 'contents'">
            <div>
                <InputLabel value="Müşteri" />
                <div class="mt-1 flex flex-col gap-1">
                    <TextInput
                        v-model="quoteForm.client_name"
                        class="block w-full"
                        placeholder="Firma Adı"
                        autocomplete="off"
                        @input="scheduleClientSearch"
                        @focus="scheduleClientSearch"
                        @blur="hideClientResults"
                    />
                    <ul
                        v-if="clientOpen"
                        class="max-h-48 overflow-y-auto rounded-md border border-gray-200 bg-white"
                    >
                        <li v-if="clientOptions.length === 0" class="px-3 py-2 text-sm text-gray-500">
                            Eşleşen kayıt bulunamadı.
                        </li>
                        <li v-for="client in clientOptions" :key="client.id">
                            <button
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm text-gray-800 hover:bg-gray-100"
                                @mousedown.prevent="pickClient(client)"
                            >
                                {{ client.name }}
                            </button>
                        </li>
                    </ul>
                </div>
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
            </div>
            <div :class="modal ? 'mt-6 flex justify-end gap-3' : 'flex items-end'">
                <SecondaryButton v-if="modal" type="button" @click="dismiss">İptal</SecondaryButton>
                <PrimaryButton :disabled="quoteForm.processing || chosenClient === ''">Teklife ekle</PrimaryButton>
            </div>
        </form>

        <form
            v-if="open === 'order'"
            :class="formClass('order')"
            @submit.prevent="send(orderForm, 'products.order')"
        >
            <div v-if="modal">
                <h2 class="text-lg font-medium text-gray-900">Sipariş</h2>
                <p class="mt-1 text-sm text-gray-500">{{ product.name }}</p>
            </div>
            <div :class="modal ? 'mt-6 grid gap-4 md:grid-cols-2' : 'contents'">
            <div>
                <InputLabel value="Fabrika" />
                <select v-model="orderForm.factory_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Fabrika seçin</option>
                    <option v-for="factory in factories" :key="factory.id" :value="factory.id">{{ factory.name }}</option>
                </select>
                <InputError class="mt-1" :message="orderForm.errors.factory_id" />
            </div>
            <div>
                <InputLabel value="Hazırlayan" />
                <select v-model="orderForm.prepared_by_user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
            </div>
            <div :class="modal ? 'mt-6 flex justify-end gap-3' : 'flex items-end'">
                <SecondaryButton v-if="modal" type="button" @click="dismiss">İptal</SecondaryButton>
                <PrimaryButton :disabled="orderForm.processing">Siparişe ekle</PrimaryButton>
            </div>
        </form>

        <form
            v-if="open === 'ship'"
            :class="formClass('ship')"
            @submit.prevent="send(shipForm, 'products.ship')"
        >
            <div v-if="modal">
                <h2 class="text-lg font-medium text-gray-900">Sevkiyat</h2>
                <p class="mt-1 text-sm text-gray-500">{{ product.name }}</p>
            </div>
            <div :class="modal ? 'mt-6 grid gap-4 md:grid-cols-2' : 'contents'">
            <div>
                <InputLabel value="Firma" />
                <div class="mt-1 flex flex-col gap-1">
                    <TextInput
                        v-model="shipForm.client_name"
                        class="block w-full"
                        placeholder="Firma Adı"
                        autocomplete="off"
                        @input="scheduleShipClientSearch"
                        @focus="scheduleShipClientSearch"
                        @blur="hideShipClientResults"
                    />
                    <ul
                        v-if="shipClientOpen"
                        class="max-h-48 overflow-y-auto rounded-md border border-gray-200 bg-white"
                    >
                        <li v-if="shipClientOptions.length === 0" class="px-3 py-2 text-sm text-gray-500">
                            Eşleşen kayıt bulunamadı.
                        </li>
                        <li v-for="client in shipClientOptions" :key="client.id">
                            <button
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm text-gray-800 hover:bg-gray-100"
                                @mousedown.prevent="pickShipClient(client)"
                            >
                                {{ client.name }}
                            </button>
                        </li>
                    </ul>
                </div>
                <InputError class="mt-1" :message="shipForm.errors.client_name" />
            </div>
            <div>
                <InputLabel value="Adet" />
                <TextInput
                    v-model="shipForm.quantity_piece"
                    type="number"
                    min="1"
                    class="mt-1 block w-full"
                    placeholder="(Boy)"
                />
                <InputError class="mt-1" :message="shipForm.errors.quantity_piece" />
            </div>
            <div>
                <InputLabel value="Sevk Tipi" />
                <select v-model="shipForm.ship_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Sevk tipi seçiniz.</option>
                    <option v-for="type in shipTypes" :key="type" :value="type">{{ type }}</option>
                </select>
                <InputError class="mt-1" :message="shipForm.errors.ship_type" />
            </div>
            <div>
                <InputLabel value="Fiyat" />
                <TextInput
                    v-model="shipForm.unit_price"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1 block w-full"
                    placeholder="TL"
                />
                <InputError class="mt-1" :message="shipForm.errors.unit_price" />
            </div>
            <div>
                <InputLabel value="Araç" />
                <select v-model="shipForm.vehicle_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Araç seçiniz.</option>
                    <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.name }}</option>
                </select>
                <InputError class="mt-1" :message="shipForm.errors.vehicle_id" />
            </div>
            <div class="md:col-span-2">
                <InputLabel value="Açıklama" />
                <TextInput
                    v-model="shipForm.notes"
                    class="mt-1 block w-full"
                    placeholder="Sevkiyat ile ilgili açıklama yazabilirsiniz."
                />
                <InputError class="mt-1" :message="shipForm.errors.notes" />
            </div>
            </div>
            <div :class="modal ? 'mt-6 flex justify-end gap-3' : 'flex items-end'">
                <SecondaryButton v-if="modal" type="button" @click="dismiss">İptal</SecondaryButton>
                <PrimaryButton :disabled="shipForm.processing || chosenShipClient === ''">Sevkiyata ekle</PrimaryButton>
            </div>
        </form>
    </div>
</template>
