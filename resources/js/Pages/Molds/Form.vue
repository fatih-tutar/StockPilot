<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    mold: { type: Object, default: null },
    clients: { type: Array, required: true },
    factories: { type: Array, required: true },
    canManage: { type: Boolean, default: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const isEdit = computed(() => !!props.mold?.id);
const locked = computed(() => isEdit.value && !props.canManage);

const documents = [
    { key: 'factory_approval', label: 'Fabrika onay PDF' },
    { key: 'client_approval', label: 'Firma onay PDF' },
    { key: 'contract', label: 'Sözleşme PDF' },
];

const form = useForm({
    client_id: props.mold?.client_id || '',
    factory_id: props.mold?.factory_id || '',
    number: props.mold?.number || '',
    client_offer_price: props.mold?.client_offer_price || '',
    factory_offer_price: props.mold?.factory_offer_price || '',
    due_on: props.mold?.due_on || '',
    contact_name: props.mold?.contact_name || '',
    description: props.mold?.description || '',
    factory_approval: null,
    client_approval: null,
    contract: null,
});

const onFile = (key, event) => {
    form[key] = event.target.files[0] || null;
};

const existingDocument = (key) => props.mold?.documents?.find((document) => document.key === key);

const submit = () => {
    if (isEdit.value) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(route('molds.update', props.mold.id), {
            forceFormData: true,
            preserveScroll: true,
        });

        return;
    }

    form.post(route('molds.store'), { forceFormData: true });
};
</script>

<template>
    <Head :title="isEdit ? 'Kalıbı düzenle' : 'Yeni kalıp'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? 'Kalıbı düzenle' : 'Yeni kalıp' }}
                </h2>
                <Link :href="route('molds.index')" class="text-sm text-gray-600 hover:text-gray-900">
                    Kalıplara dön
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

                <form class="space-y-4 bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="client_id" value="Firma" />
                            <select
                                id="client_id"
                                v-model="form.client_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                                :disabled="locked"
                            >
                                <option value="" disabled>Firma seçin</option>
                                <option v-for="client in clients" :key="client.id" :value="client.id">
                                    {{ client.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.client_id" />
                        </div>
                        <div>
                            <InputLabel for="factory_id" value="Fabrika" />
                            <select
                                id="factory_id"
                                v-model="form.factory_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                                :disabled="locked"
                            >
                                <option value="" disabled>Fabrika seçin</option>
                                <option v-for="factory in factories" :key="factory.id" :value="factory.id">
                                    {{ factory.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.factory_id" />
                        </div>
                        <div>
                            <InputLabel for="number" value="Kalıp numarası" />
                            <TextInput
                                id="number"
                                v-model="form.number"
                                class="mt-1 block w-full"
                                required
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.number" />
                        </div>
                        <div>
                            <InputLabel for="contact_name" value="İlgili kişi" />
                            <TextInput
                                id="contact_name"
                                v-model="form.contact_name"
                                class="mt-1 block w-full"
                                required
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.contact_name" />
                        </div>
                        <div>
                            <InputLabel for="client_offer_price" value="Firmaya verilen teklif" />
                            <TextInput
                                id="client_offer_price"
                                v-model="form.client_offer_price"
                                class="mt-1 block w-full"
                                required
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.client_offer_price" />
                        </div>
                        <div>
                            <InputLabel for="factory_offer_price" value="Fabrikadan alınan teklif" />
                            <TextInput
                                id="factory_offer_price"
                                v-model="form.factory_offer_price"
                                class="mt-1 block w-full"
                                required
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.factory_offer_price" />
                        </div>
                        <div>
                            <InputLabel for="due_on" value="Termin tarihi" />
                            <TextInput
                                id="due_on"
                                v-model="form.due_on"
                                type="date"
                                class="mt-1 block w-full"
                                required
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.due_on" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="description" value="Açıklama" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div v-for="document in documents" :key="document.key">
                            <InputLabel :for="document.key" :value="document.label" />
                            <input
                                :id="document.key"
                                type="file"
                                accept="application/pdf"
                                class="mt-1 block w-full text-sm text-gray-600"
                                :disabled="locked"
                                @change="onFile(document.key, $event)"
                            />
                            <p v-if="existingDocument(document.key)?.file_name" class="mt-1 text-xs text-gray-500">
                                <a
                                    v-if="existingDocument(document.key).url"
                                    :href="existingDocument(document.key).url"
                                    class="text-indigo-600 hover:text-indigo-800"
                                >
                                    {{ existingDocument(document.key).file_name }}
                                </a>
                                <span v-else>{{ existingDocument(document.key).file_name }} (dosya yok)</span>
                            </p>
                            <InputError class="mt-2" :message="form.errors[document.key]" />
                        </div>
                    </div>

                    <PrimaryButton v-if="!locked" :disabled="form.processing">
                        {{ isEdit ? 'Kalıbı kaydet' : 'Kalıp oluştur' }}
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
