<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    shipment: { type: Object, default: null },
    vehicles: { type: Array, required: true },
    shipTypeOptions: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
    modal: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const isEdit = computed(() => !!props.shipment?.id);
const readOnly = computed(() => isEdit.value && !props.canManage);

const emptyItem = () => ({
    id: null,
    product_id: '',
    product_name: '',
    quantity_piece: '',
    unit_price: '',
});

const initialItems =
    props.shipment?.items?.length > 0
        ? props.shipment.items.map((item) => ({
              id: item.id,
              product_id: item.product_id || '',
              product_name: item.product_name || '',
              quantity_piece: item.quantity_piece,
              unit_price: item.unit_price ?? '',
          }))
        : [emptyItem()];

const form = useForm({
    client_id: props.shipment?.client_id || '',
    ship_type: props.shipment?.ship_type || '',
    vehicle_id: props.shipment?.vehicle_id || '',
    notes: props.shipment?.notes || '',
    items: initialItems,
});

const deleteForm = useForm({});

const clientName = ref(props.shipment?.client_name || '');
const chosenClient = ref(props.shipment?.client_name || '');
const clientOptions = ref([]);
const clientOpen = ref(false);
let clientTimer = null;
let clientRequest = 0;

const chosenProductNames = ref(initialItems.map((item) => item.product_name || ''));
const productOptions = ref([]);
const productOpenIndex = ref(null);
let productTimer = null;
let productRequest = 0;

watch(clientName, (value) => {
    if (value !== chosenClient.value) {
        chosenClient.value = '';
        form.client_id = '';
    }
});

const scheduleClientSearch = (event) => {
    window.clearTimeout(clientTimer);

    const term = String(event?.target?.value ?? clientName.value).trim();

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
    clientName.value = client.name;
    form.client_id = client.id;
    clientOpen.value = false;
};

const hideClientResults = () => {
    window.setTimeout(() => {
        clientOpen.value = false;
    }, 150);
};

const scheduleProductSearch = (index, event) => {
    window.clearTimeout(productTimer);

    const item = form.items[index];

    if (item.product_name !== chosenProductNames.value[index]) {
        chosenProductNames.value[index] = '';
        item.product_id = '';
    }

    const term = String(event?.target?.value ?? item.product_name).trim();

    if (term === '') {
        productOptions.value = [];
        productOpenIndex.value = null;

        return;
    }

    productTimer = window.setTimeout(async () => {
        const requestId = ++productRequest;
        const { data } = await axios.get(route('products.search'), { params: { term } });

        if (requestId !== productRequest) {
            return;
        }

        productOptions.value = data;
        productOpenIndex.value = index;
    }, 200);
};

const pickProduct = (index, product) => {
    const item = form.items[index];
    chosenProductNames.value[index] = product.name;
    item.product_name = product.name;
    item.product_id = product.id;
    productOpenIndex.value = null;
};

const hideProductResults = () => {
    window.setTimeout(() => {
        productOpenIndex.value = null;
    }, 150);
};

const addItem = () => {
    form.items.push(emptyItem());
    chosenProductNames.value.push('');
};

const removeItem = (index) => {
    if (form.items.length === 1) {
        form.items[0] = emptyItem();
        chosenProductNames.value[0] = '';
        productOpenIndex.value = null;

        return;
    }

    form.items.splice(index, 1);
    chosenProductNames.value.splice(index, 1);

    if (productOpenIndex.value === index) {
        productOpenIndex.value = null;
    } else if (productOpenIndex.value !== null && productOpenIndex.value > index) {
        productOpenIndex.value -= 1;
    }
};

const submit = () => {
    const payload = {
        client_id: Number(form.client_id),
        ship_type: form.ship_type,
        vehicle_id: form.vehicle_id ? Number(form.vehicle_id) : null,
        notes: form.notes || null,
        items: form.items.map((item) => ({
            id: item.id || undefined,
            product_id: Number(item.product_id),
            quantity_piece: Number(item.quantity_piece || 0),
            unit_price: Number(item.unit_price || 0),
        })),
    };

    if (isEdit.value) {
        form.transform(() => payload).put(route('shipments.update', props.shipment.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (props.modal) {
                    emit('close');
                }
            },
        });
    } else {
        form.transform(() => payload).post(route('shipments.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (props.modal) {
                    emit('close');
                }
            },
        });
    }
};

const destroyShipment = () => {
    if (!confirm('Bu müşteri siparişi silinsin mi?')) {
        return;
    }
    deleteForm.delete(route('shipments.destroy', props.shipment.id), {
        onSuccess: () => {
            if (props.modal) {
                emit('close');
            }
        },
    });
};

onUnmounted(() => {
    window.clearTimeout(clientTimer);
    window.clearTimeout(productTimer);
});
</script>

<template>
    <div>
        <Head v-if="!modal" :title="isEdit ? 'Müşteri siparişi düzenle' : 'Yeni müşteri siparişi'" />

        <component
            :is="modal ? 'div' : AuthenticatedLayout"
            :class="modal ? 'max-h-[80vh] overflow-y-auto overflow-x-visible' : null"
        >
            <template v-if="!modal" #header>
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ isEdit ? 'Müşteri siparişi düzenle' : 'Yeni müşteri siparişi' }}
                    </h2>
                    <Link
                        :href="route('shipments.index')"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        Müşteri siparişlerine dön
                    </Link>
                </div>
            </template>

            <div :class="modal ? 'space-y-6 p-6' : 'py-8'">
                <div :class="modal ? 'space-y-6' : 'mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8'">
                    <template v-if="modal">
                        <p class="text-sm font-medium text-gray-500">Müşteri siparişi</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900">
                            {{ isEdit ? 'Siparişi düzenle' : 'Yeni müşteri siparişi' }}
                        </h2>
                    </template>
                    <div
                        v-if="!modal && flashSuccess"
                        class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                    >
                        {{ flashSuccess }}
                    </div>
                    <div
                        v-if="!modal && flashError"
                        class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                    >
                        {{ flashError }}
                    </div>

                    <form
                        class="space-y-6"
                        :class="modal ? '' : 'bg-white p-6 shadow-sm sm:rounded-lg'"
                        @submit.prevent="submit"
                    >
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <InputLabel for="client_name" value="Firma" />
                                <div class="relative mt-1" :class="clientOpen ? 'z-20' : ''">
                                    <TextInput
                                        id="client_name"
                                        v-model="clientName"
                                        class="block w-full"
                                        placeholder="Firma Adı"
                                        autocomplete="off"
                                        required
                                        :disabled="readOnly"
                                        @input="scheduleClientSearch"
                                        @focus="scheduleClientSearch"
                                        @blur="hideClientResults"
                                    />
                                    <ul
                                        v-if="clientOpen && !readOnly"
                                        class="absolute left-0 right-0 top-full z-20 mt-1 max-h-48 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg"
                                    >
                                        <li
                                            v-if="clientOptions.length === 0"
                                            class="px-3 py-2 text-sm text-gray-500"
                                        >
                                            Eşleşen kayıt bulunamadı.
                                        </li>
                                        <li
                                            v-for="client in clientOptions"
                                            :key="client.id"
                                        >
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
                                <InputError class="mt-2" :message="form.errors.client_id" />
                            </div>

                            <div>
                                <InputLabel for="ship_type" value="Sevk Tipi" />
                                <select
                                    id="ship_type"
                                    v-model="form.ship_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                    :disabled="readOnly"
                                >
                                    <option value="" disabled>Sevk tipi seçiniz.</option>
                                    <option
                                        v-for="option in shipTypeOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.ship_type" />
                            </div>

                            <div>
                                <InputLabel for="vehicle_id" value="Araç" />
                                <select
                                    id="vehicle_id"
                                    v-model="form.vehicle_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :disabled="readOnly"
                                >
                                    <option value="">Araç seçiniz.</option>
                                    <option
                                        v-for="vehicle in vehicles"
                                        :key="vehicle.id"
                                        :value="vehicle.id"
                                    >
                                        {{ vehicle.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.vehicle_id" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="notes" value="Açıklama" />
                                <TextInput
                                    id="notes"
                                    v-model="form.notes"
                                    class="mt-1 block w-full"
                                    placeholder="Müşteri siparişi ile ilgili açıklama yazabilirsiniz."
                                    :disabled="readOnly"
                                />
                                <InputError class="mt-2" :message="form.errors.notes" />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900">Ürünler</h3>
                                <button
                                    v-if="!readOnly"
                                    type="button"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                    @click="addItem"
                                >
                                    Ürün ekle
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.items" />

                            <div class="overflow-visible">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-2 py-2 text-left font-medium text-gray-600">
                                                Ürün
                                            </th>
                                            <th
                                                class="w-28 px-2 py-2 text-right font-medium text-gray-600"
                                            >
                                                Adet
                                            </th>
                                            <th
                                                class="w-28 px-2 py-2 text-right font-medium text-gray-600"
                                            >
                                                Fiyat
                                            </th>
                                            <th v-if="!readOnly" class="px-2 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr
                                            v-for="(item, index) in form.items"
                                            :key="item.id || `new-${index}`"
                                        >
                                            <td
                                                class="px-2 py-2 align-top"
                                                :class="productOpenIndex === index ? 'relative z-20' : 'relative'"
                                            >
                                                <TextInput
                                                    v-model="item.product_name"
                                                    class="block w-full min-w-48"
                                                    placeholder="Ürün adı"
                                                    autocomplete="off"
                                                    :disabled="readOnly"
                                                    @input="scheduleProductSearch(index, $event)"
                                                    @focus="scheduleProductSearch(index, $event)"
                                                    @blur="hideProductResults"
                                                />
                                                <ul
                                                    v-if="productOpenIndex === index && !readOnly"
                                                    class="absolute left-0 right-0 top-full z-20 mt-1 max-h-48 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg"
                                                >
                                                    <li
                                                        v-if="productOptions.length === 0"
                                                        class="px-3 py-2 text-sm text-gray-500"
                                                    >
                                                        Eşleşen kayıt bulunamadı.
                                                    </li>
                                                    <li
                                                        v-for="product in productOptions"
                                                        :key="product.id"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="block w-full px-3 py-2 text-left text-sm text-gray-800 hover:bg-gray-100"
                                                            @mousedown.prevent="pickProduct(index, product)"
                                                        >
                                                            {{ product.name }}
                                                        </button>
                                                    </li>
                                                </ul>
                                                <InputError
                                                    class="mt-1"
                                                    :message="form.errors[`items.${index}.product_id`]"
                                                />
                                            </td>
                                            <td class="w-28 px-2 py-2 align-top text-right">
                                                <TextInput
                                                    v-model="item.quantity_piece"
                                                    type="number"
                                                    min="1"
                                                    class="inline-block w-24 text-right"
                                                    placeholder="(Boy)"
                                                    :disabled="readOnly"
                                                />
                                                <InputError
                                                    class="mt-1"
                                                    :message="
                                                        form.errors[`items.${index}.quantity_piece`]
                                                    "
                                                />
                                            </td>
                                            <td class="w-28 px-2 py-2 align-top text-right">
                                                <TextInput
                                                    v-model="item.unit_price"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="inline-block w-24 text-right"
                                                    placeholder="TL"
                                                    :disabled="readOnly"
                                                />
                                                <InputError
                                                    class="mt-1"
                                                    :message="
                                                        form.errors[`items.${index}.unit_price`]
                                                    "
                                                />
                                            </td>
                                            <td
                                                v-if="!readOnly"
                                                class="px-2 py-2 align-middle text-right"
                                            >
                                                <button
                                                    type="button"
                                                    class="text-sm text-red-600 hover:text-red-800"
                                                    @click="removeItem(index)"
                                                >
                                                    Sil
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-if="!readOnly" class="flex gap-3" :class="modal ? 'justify-end' : ''">
                            <SecondaryButton v-if="modal" type="button" @click="emit('close')">
                                İptal
                            </SecondaryButton>
                            <PrimaryButton :disabled="form.processing || chosenClient === ''">
                                {{ isEdit ? 'Kaydet' : 'Müşteri siparişi oluştur' }}
                            </PrimaryButton>
                            <DangerButton
                                v-if="isEdit && canManage"
                                type="button"
                                :disabled="deleteForm.processing"
                                @click="destroyShipment"
                            >
                                Sil
                            </DangerButton>
                        </div>
                    </form>
                </div>
            </div>
        </component>
    </div>
</template>
