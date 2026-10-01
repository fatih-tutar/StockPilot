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
    vehicle: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const isEdit = computed(() => !!props.vehicle?.id);
const locked = computed(() => isEdit.value && !props.canManage);

const form = useForm({
    name: props.vehicle?.name || '',
    license_plate: props.vehicle?.license_plate || '',
    driver_name: props.vehicle?.driver_name || '',
    description: props.vehicle?.description || '',
    is_delivery_vehicle: props.vehicle?.is_delivery_vehicle ?? false,
    casco_expires_on: props.vehicle?.casco_expires_on || '',
    insurance_expires_on: props.vehicle?.insurance_expires_on || '',
    inspection_due_on: props.vehicle?.inspection_due_on || '',
    casco: null,
    traffic_insurance: null,
    registration: null,
});

const deleteForm = useForm({});

const documents = [
    { key: 'casco', label: 'Kasko poliçesi' },
    { key: 'traffic_insurance', label: 'Trafik sigortası' },
    { key: 'registration', label: 'Ruhsat' },
];

const onFile = (key, event) => {
    form[key] = event.target.files[0] || null;
};

const submit = () => {
    if (isEdit.value) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(route('vehicles.update', props.vehicle.id), {
            forceFormData: true,
            preserveScroll: true,
        });
        return;
    }

    form.post(route('vehicles.store'), { forceFormData: true });
};

const destroyVehicle = () => {
    if (!confirm(`"${props.vehicle.name}" aracı silinsin mi?`)) {
        return;
    }

    deleteForm.delete(route('vehicles.destroy', props.vehicle.id));
};
</script>

<template>
    <Head :title="isEdit ? 'Aracı düzenle' : 'Yeni araç'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? vehicle.name : 'Yeni araç' }}
                </h2>
                <Link
                    :href="route('vehicles.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Araçlara dön
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
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
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="name" value="Araç" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                class="mt-1 block w-full"
                                required
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="license_plate" value="Plaka" />
                            <TextInput
                                id="license_plate"
                                v-model="form.license_plate"
                                class="mt-1 block w-full"
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.license_plate" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="driver_name" value="Sürücü" />
                        <TextInput
                            id="driver_name"
                            v-model="form.driver_name"
                            class="mt-1 block w-full"
                            :disabled="locked"
                        />
                        <InputError class="mt-2" :message="form.errors.driver_name" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel for="casco_expires_on" value="Kasko bitiş" />
                            <TextInput
                                id="casco_expires_on"
                                v-model="form.casco_expires_on"
                                type="date"
                                class="mt-1 block w-full"
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.casco_expires_on" />
                        </div>
                        <div>
                            <InputLabel for="insurance_expires_on" value="Trafik bitiş" />
                            <TextInput
                                id="insurance_expires_on"
                                v-model="form.insurance_expires_on"
                                type="date"
                                class="mt-1 block w-full"
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.insurance_expires_on" />
                        </div>
                        <div>
                            <InputLabel for="inspection_due_on" value="Muayene" />
                            <TextInput
                                id="inspection_due_on"
                                v-model="form.inspection_due_on"
                                type="date"
                                class="mt-1 block w-full"
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.inspection_due_on" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="description" value="Not" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :disabled="locked"
                        />
                        <InputError class="mt-2" :message="form.errors.description" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="is_delivery_vehicle"
                            v-model:checked="form.is_delivery_vehicle"
                            :disabled="locked"
                        />
                        <InputLabel for="is_delivery_vehicle" value="Sevkiyat aracı" />
                    </div>

                    <div class="space-y-4 border-t border-gray-100 pt-4">
                        <h3 class="text-sm font-medium text-gray-800">Belgeler</h3>
                        <div v-for="document in documents" :key="document.key">
                            <InputLabel :for="document.key" :value="document.label" />
                            <input
                                :id="document.key"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="mt-1 block w-full text-sm text-gray-700"
                                :disabled="locked"
                                @change="onFile(document.key, $event)"
                            />
                            <p
                                v-if="vehicle?.documents?.[document.key]"
                                class="mt-1 text-xs text-gray-500"
                            >
                                <a
                                    v-if="vehicle.documents[document.key].available"
                                    :href="route('vehicles.documents.download', [vehicle.id, vehicle.documents[document.key].id])"
                                    class="text-indigo-600 hover:text-indigo-800"
                                >
                                    {{ vehicle.documents[document.key].file_name }}
                                </a>
                                <span v-else>
                                    {{ vehicle.documents[document.key].file_name }} — dosya depoda yok
                                </span>
                            </p>
                            <InputError class="mt-2" :message="form.errors[document.key]" />
                        </div>
                    </div>

                    <div v-if="!isEdit || canManage" class="flex gap-3">
                        <PrimaryButton :disabled="form.processing">
                            {{ isEdit ? 'Aracı kaydet' : 'Araç oluştur' }}
                        </PrimaryButton>
                        <DangerButton
                            v-if="isEdit && canManage"
                            type="button"
                            :disabled="deleteForm.processing"
                            @click="destroyVehicle"
                        >
                            Sil
                        </DangerButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
