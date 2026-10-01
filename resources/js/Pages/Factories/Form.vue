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
    factory: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const isEdit = computed(() => !!props.factory?.id);

const form = useForm({
    name: props.factory?.name || '',
    phone: props.factory?.phone || '',
    email: props.factory?.email || '',
    address: props.factory?.address || '',
    labor_cost: props.factory?.labor_cost ?? '',
    fine_labor_cost: props.factory?.fine_labor_cost ?? '',
    is_active: props.factory?.is_active ?? true,
});

const deleteForm = useForm({});

const submit = () => {
    if (isEdit.value) {
        form.put(route('factories.update', props.factory.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route('factories.store'));
    }
};

const destroyFactory = () => {
    if (!confirm(`"${props.factory.name}" fabrikası silinsin mi?`)) {
        return;
    }

    deleteForm.delete(route('factories.destroy', props.factory.id));
};
</script>

<template>
    <Head :title="isEdit ? 'Fabrikayı düzenle' : 'Yeni fabrika'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? factory.name : 'Yeni fabrika' }}
                </h2>
                <Link
                    :href="route('factories.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Fabrikalara dön
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
                    <div>
                        <InputLabel for="name" value="Fabrika adı" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            class="mt-1 block w-full"
                            required
                            :disabled="isEdit && !canManage"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="phone" value="Telefon" />
                            <TextInput
                                id="phone"
                                v-model="form.phone"
                                class="mt-1 block w-full"
                                :disabled="isEdit && !canManage"
                            />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>
                        <div>
                            <InputLabel for="email" value="E-posta" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                class="mt-1 block w-full"
                                :disabled="isEdit && !canManage"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="address" value="Adres" />
                        <textarea
                            id="address"
                            v-model="form.address"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :disabled="isEdit && !canManage"
                        />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="labor_cost" value="İşçilik" />
                            <TextInput
                                id="labor_cost"
                                v-model="form.labor_cost"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1 block w-full"
                                :disabled="isEdit && !canManage"
                            />
                            <InputError class="mt-2" :message="form.errors.labor_cost" />
                        </div>
                        <div>
                            <InputLabel for="fine_labor_cost" value="İnce işçilik" />
                            <TextInput
                                id="fine_labor_cost"
                                v-model="form.fine_labor_cost"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1 block w-full"
                                :disabled="isEdit && !canManage"
                            />
                            <InputError class="mt-2" :message="form.errors.fine_labor_cost" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            v-model:checked="form.is_active"
                            :disabled="isEdit && !canManage"
                        />
                        <InputLabel for="is_active" value="Aktif" />
                    </div>

                    <div v-if="!isEdit || canManage" class="flex gap-3">
                        <PrimaryButton :disabled="form.processing">
                            {{ isEdit ? 'Fabrikayı kaydet' : 'Fabrika oluştur' }}
                        </PrimaryButton>
                        <DangerButton
                            v-if="isEdit && canManage"
                            type="button"
                            :disabled="deleteForm.processing"
                            @click="destroyFactory"
                        >
                            Sil
                        </DangerButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
