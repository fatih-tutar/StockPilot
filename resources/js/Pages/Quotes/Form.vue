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
    quote: { type: Object, default: null },
    clients: { type: Array, required: true },
    products: { type: Array, required: true },
    statusOptions: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const isEdit = computed(() => !!props.quote?.id);
const readOnly = computed(() => isEdit.value && !props.canManage);

const emptyItem = () => ({
    id: null,
    product_id: '',
    description: '',
    quantity_piece: 1,
    quantity_pallet: 0,
    unit_price: 0,
});

const form = useForm({
    client_id: props.quote?.client_id || '',
    status: props.quote?.status || 'draft',
    quote_date: props.quote?.quote_date || new Date().toISOString().slice(0, 10),
    valid_until: props.quote?.valid_until || '',
    currency: props.quote?.currency || 'TRY',
    tax_rate: props.quote?.tax_rate ?? 20,
    notes: props.quote?.notes || '',
    items:
        props.quote?.items?.length > 0
            ? props.quote.items.map((item) => ({
                  id: item.id,
                  product_id: item.product_id || '',
                  description: item.description,
                  quantity_piece: item.quantity_piece,
                  quantity_pallet: item.quantity_pallet,
                  unit_price: item.unit_price,
              }))
            : [emptyItem()],
});

const deleteForm = useForm({});

const lineTotal = (item) =>
    Number(item.quantity_piece || 0) * Number(item.unit_price || 0);

const subtotalPreview = computed(() =>
    form.items.reduce((sum, item) => sum + lineTotal(item), 0),
);

const taxPreview = computed(
    () => (subtotalPreview.value * Number(form.tax_rate || 0)) / 100,
);

const totalPreview = computed(() => subtotalPreview.value + taxPreview.value);

const formatMoney = (amount) =>
    new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: form.currency || 'TRY',
        maximumFractionDigits: 2,
    }).format(amount || 0);

const onProductChange = (index) => {
    const item = form.items[index];
    const product = props.products.find(
        (entry) => Number(entry.id) === Number(item.product_id),
    );
    if (product && !item.description) {
        item.description = product.name;
    }
};

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
    const payload = {
        ...form.data(),
        client_id: Number(form.client_id),
        tax_rate: Number(form.tax_rate || 0),
        valid_until: form.valid_until || null,
        items: form.items.map((item) => ({
            id: item.id || undefined,
            product_id: item.product_id ? Number(item.product_id) : null,
            description: item.description,
            quantity_piece: Number(item.quantity_piece || 0),
            quantity_pallet: Number(item.quantity_pallet || 0),
            unit_price: Number(item.unit_price || 0),
        })),
    };

    if (isEdit.value) {
        form.transform(() => payload).put(route('quotes.update', props.quote.id), {
            preserveScroll: true,
        });
    } else {
        form.transform(() => payload).post(route('quotes.store'));
    }
};

const destroyQuote = () => {
    if (!confirm(`"${props.quote.number}" teklifi silinsin mi?`)) {
        return;
    }
    deleteForm.delete(route('quotes.destroy', props.quote.id));
};
</script>

<template>
    <Head :title="isEdit ? `Teklif ${quote.number}` : 'Yeni teklif'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? quote.number : 'Yeni teklif' }}
                </h2>
                <Link
                    :href="route('quotes.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Tekliflere dön
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
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
                    class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="client_id" value="Müşteri" />
                            <select
                                id="client_id"
                                v-model="form.client_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                                :disabled="readOnly"
                            >
                                <option value="" disabled>Müşteri seçin</option>
                                <option
                                    v-for="client in clients"
                                    :key="client.id"
                                    :value="client.id"
                                >
                                    {{ client.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.client_id" />
                        </div>
                        <div>
                            <InputLabel for="status" value="Durum" />
                            <select
                                id="status"
                                v-model="form.status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :disabled="readOnly"
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
                        <div>
                            <InputLabel for="quote_date" value="Teklif tarihi" />
                            <TextInput
                                id="quote_date"
                                v-model="form.quote_date"
                                type="date"
                                class="mt-1 block w-full"
                                required
                                :disabled="readOnly"
                            />
                            <InputError class="mt-2" :message="form.errors.quote_date" />
                        </div>
                        <div>
                            <InputLabel for="valid_until" value="Geçerlilik" />
                            <TextInput
                                id="valid_until"
                                v-model="form.valid_until"
                                type="date"
                                class="mt-1 block w-full"
                                :disabled="readOnly"
                            />
                            <InputError class="mt-2" :message="form.errors.valid_until" />
                        </div>
                        <div>
                            <InputLabel for="currency" value="Para birimi" />
                            <TextInput
                                id="currency"
                                v-model="form.currency"
                                class="mt-1 block w-full uppercase"
                                maxlength="3"
                                required
                                :disabled="readOnly"
                            />
                            <InputError class="mt-2" :message="form.errors.currency" />
                        </div>
                        <div>
                            <InputLabel for="tax_rate" value="KDV (%)" />
                            <TextInput
                                id="tax_rate"
                                v-model="form.tax_rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                class="mt-1 block w-full"
                                :disabled="readOnly"
                            />
                            <InputError class="mt-2" :message="form.errors.tax_rate" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="notes" value="Notlar" />
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :disabled="readOnly"
                            />
                            <InputError class="mt-2" :message="form.errors.notes" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Kalemler</h3>
                            <button
                                v-if="!readOnly"
                                type="button"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                @click="addItem"
                            >
                                Kalem ekle
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.items" />

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-2 text-left font-medium text-gray-600">Ürün</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-600">Açıklama</th>
                                        <th class="px-2 py-2 text-right font-medium text-gray-600">Adet</th>
                                        <th class="px-2 py-2 text-right font-medium text-gray-600">Palet</th>
                                        <th class="px-2 py-2 text-right font-medium text-gray-600">Birim fiyat</th>
                                        <th class="px-2 py-2 text-right font-medium text-gray-600">Satır</th>
                                        <th v-if="!readOnly" class="px-2 py-2" />
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="item.id || `new-${index}`"
                                    >
                                        <td class="px-2 py-2 align-top">
                                            <select
                                                v-model="item.product_id"
                                                class="block w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                :disabled="readOnly"
                                                @change="onProductChange(index)"
                                            >
                                                <option value="">—</option>
                                                <option
                                                    v-for="product in products"
                                                    :key="product.id"
                                                    :value="product.id"
                                                >
                                                    {{ product.name }}
                                                </option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-2 align-top">
                                            <TextInput
                                                v-model="item.description"
                                                class="block w-full min-w-48"
                                                required
                                                :disabled="readOnly"
                                            />
                                            <InputError
                                                class="mt-1"
                                                :message="form.errors[`items.${index}.description`]"
                                            />
                                        </td>
                                        <td class="px-2 py-2 align-top">
                                            <TextInput
                                                v-model="item.quantity_piece"
                                                type="number"
                                                min="0"
                                                class="block w-24 text-right"
                                                :disabled="readOnly"
                                            />
                                        </td>
                                        <td class="px-2 py-2 align-top">
                                            <TextInput
                                                v-model="item.quantity_pallet"
                                                type="number"
                                                min="0"
                                                class="block w-24 text-right"
                                                :disabled="readOnly"
                                            />
                                        </td>
                                        <td class="px-2 py-2 align-top">
                                            <TextInput
                                                v-model="item.unit_price"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="block w-28 text-right"
                                                :disabled="readOnly"
                                            />
                                        </td>
                                        <td class="px-2 py-2 align-top text-right font-medium text-gray-900">
                                            {{ formatMoney(lineTotal(item)) }}
                                        </td>
                                        <td v-if="!readOnly" class="px-2 py-2 align-top text-right">
                                            <button
                                                type="button"
                                                class="text-xs text-red-600 hover:text-red-800"
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

                    <div class="flex flex-col items-end gap-1 text-sm text-gray-700">
                        <div>Ara toplam: <span class="font-semibold">{{ formatMoney(subtotalPreview) }}</span></div>
                        <div>KDV: <span class="font-semibold">{{ formatMoney(taxPreview) }}</span></div>
                        <div class="text-base text-gray-900">
                            Genel toplam:
                            <span class="font-semibold">{{ formatMoney(totalPreview) }}</span>
                        </div>
                    </div>

                    <div v-if="!readOnly" class="flex gap-3">
                        <PrimaryButton :disabled="form.processing">
                            {{ isEdit ? 'Teklifi kaydet' : 'Teklif oluştur' }}
                        </PrimaryButton>
                        <DangerButton
                            v-if="isEdit && canManage"
                            type="button"
                            :disabled="deleteForm.processing"
                            @click="destroyQuote"
                        >
                            Sil
                        </DangerButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
