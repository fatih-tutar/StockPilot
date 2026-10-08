<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    entry: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const isEdit = computed(() => !!props.entry?.id);
const readOnly = computed(() => isEdit.value && !props.canManage);

const form = useForm({
    customer_name: props.entry?.customer_name || '',
    contact_name: props.entry?.contact_name || '',
    product_quantity: props.entry?.product_quantity || '',
    price: props.entry?.price ?? '',
    factory_name: props.entry?.factory_name || '',
    factory_price: props.entry?.factory_price ?? '',
    notes: props.entry?.notes || '',
    offered_on: props.entry?.offered_on || new Date().toISOString().slice(0, 10),
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('offer-lists.update', props.entry.id));

        return;
    }

    form.post(route('offer-lists.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Teklif listesi kaydı' : 'Yeni teklif listesi kaydı'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? 'Teklif listesi kaydı' : 'Yeni teklif listesi kaydı' }}
                </h2>
                <Link
                    :href="route('offer-lists.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Listeye dön
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div
                    v-if="flashSuccess"
                    class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                >
                    {{ flashSuccess }}
                </div>

                <form class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <p v-if="isEdit && entry.offered_by" class="text-sm text-gray-600">
                        Teklif veren: {{ entry.offered_by }}
                        <span v-if="entry.status_label"> · {{ entry.status_label }}</span>
                    </p>

                    <div>
                        <InputLabel for="customer_name" value="Müşteri adı" />
                        <TextInput
                            id="customer_name"
                            v-model="form.customer_name"
                            class="mt-1 block w-full"
                            required
                            :disabled="readOnly"
                        />
                        <InputError class="mt-2" :message="form.errors.customer_name" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="contact_name" value="İlgili kişi" />
                            <TextInput
                                id="contact_name"
                                v-model="form.contact_name"
                                class="mt-1 block w-full"
                                :disabled="readOnly"
                            />
                            <InputError class="mt-2" :message="form.errors.contact_name" />
                        </div>
                        <div>
                            <InputLabel for="offered_on" value="Tarih" />
                            <TextInput
                                id="offered_on"
                                v-model="form.offered_on"
                                type="date"
                                class="mt-1 block w-full"
                                required
                                :disabled="readOnly"
                            />
                            <InputError class="mt-2" :message="form.errors.offered_on" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="product_quantity" value="Ürün / miktar" />
                        <textarea
                            id="product_quantity"
                            v-model="form.product_quantity"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :disabled="readOnly"
                        />
                        <InputError class="mt-2" :message="form.errors.product_quantity" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="price" value="Fiyat" />
                            <TextInput
                                id="price"
                                v-model="form.price"
                                class="mt-1 block w-full"
                                :disabled="readOnly"
                            />
                            <InputError class="mt-2" :message="form.errors.price" />
                        </div>
                        <div>
                            <InputLabel for="factory_price" value="Fabrika fiyatı" />
                            <TextInput
                                id="factory_price"
                                v-model="form.factory_price"
                                class="mt-1 block w-full"
                                :disabled="readOnly"
                            />
                            <InputError class="mt-2" :message="form.errors.factory_price" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="factory_name" value="Fabrika" />
                        <TextInput
                            id="factory_name"
                            v-model="form.factory_name"
                            class="mt-1 block w-full"
                            :disabled="readOnly"
                        />
                        <InputError class="mt-2" :message="form.errors.factory_name" />
                    </div>

                    <div>
                        <InputLabel for="notes" value="Açıklama" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :disabled="readOnly"
                        />
                        <InputError class="mt-2" :message="form.errors.notes" />
                    </div>

                    <div v-if="!readOnly" class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">
                            Kaydet
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
