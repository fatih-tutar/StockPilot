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
    staffMember: { type: Object, default: null },
    levels: { type: Array, required: true },
    flags: { type: Array, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const isEdit = computed(() => !!props.staffMember?.id);

const flagValues = Object.fromEntries(
    props.flags.map((flag) => [flag.key, props.staffMember?.access_flags?.[flag.key] ?? false]),
);

const form = useForm({
    name: props.staffMember?.name || '',
    email: props.staffMember?.email || '',
    phone: props.staffMember?.phone || '',
    phone_2: props.staffMember?.phone_2 || '',
    address: props.staffMember?.address || '',
    title: props.staffMember?.title || '',
    hired_on: props.staffMember?.hired_on || '',
    access_level: props.staffMember?.access_level || 'staff',
    access_flags: flagValues,
    is_active: props.staffMember?.is_active ?? true,
    password: '',
    password_confirmation: '',
    photo: null,
    identity_card: null,
    application_form: null,
    residence_certificate: null,
    health_report: null,
});

const deleteForm = useForm({});

const documents = [
    { key: 'photo', label: 'Fotoğraf' },
    { key: 'identity_card', label: 'Kimlik' },
    { key: 'application_form', label: 'İş başvuru formu' },
    { key: 'residence_certificate', label: 'İkametgah' },
    { key: 'health_report', label: 'Sağlık raporu' },
];

const onFile = (key, event) => {
    form[key] = event.target.files[0] || null;
};

const submit = () => {
    if (isEdit.value) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(route('staff.update', props.staffMember.id), {
            forceFormData: true,
            preserveScroll: true,
        });
        return;
    }

    form.post(route('staff.store'), { forceFormData: true });
};

const destroyStaff = () => {
    if (!confirm('Bu personel kaydı silinsin mi?')) {
        return;
    }

    deleteForm.delete(route('staff.destroy', props.staffMember.id));
};
</script>

<template>
    <Head :title="isEdit ? 'Personel düzenle' : 'Yeni personel'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? 'Personel düzenle' : 'Yeni personel' }}
                </h2>
                <Link
                    :href="route('staff.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Listeye dön
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
                    class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <InputLabel for="name" value="Ad" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="title" value="Unvan" />
                            <TextInput
                                id="title"
                                v-model="form.title"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.title" />
                        </div>
                        <div>
                            <InputLabel for="access_level" value="Yetki düzeyi" />
                            <select
                                id="access_level"
                                v-model="form.access_level"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option
                                    v-for="level in levels"
                                    :key="level.value"
                                    :value="level.value"
                                >
                                    {{ level.label }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.access_level" />
                        </div>
                        <div>
                            <InputLabel for="email" value="E-posta" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>
                        <div>
                            <InputLabel for="hired_on" value="İşe giriş" />
                            <TextInput
                                id="hired_on"
                                v-model="form.hired_on"
                                type="date"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.hired_on" />
                        </div>
                        <div>
                            <InputLabel for="phone" value="Telefon" />
                            <TextInput
                                id="phone"
                                v-model="form.phone"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>
                        <div>
                            <InputLabel for="phone_2" value="Telefon 2" />
                            <TextInput
                                id="phone_2"
                                v-model="form.phone_2"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.phone_2" />
                        </div>
                        <div class="sm:col-span-2">
                            <InputLabel for="address" value="Adres" />
                            <textarea
                                id="address"
                                v-model="form.address"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError class="mt-2" :message="form.errors.address" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <Checkbox v-model:checked="form.is_active" />
                        Aktif
                    </label>

                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">
                            Ekran yetkileri
                        </legend>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="flag in flags"
                                :key="flag.key"
                                class="flex items-center gap-2 text-sm text-gray-700"
                            >
                                <Checkbox v-model:checked="form.access_flags[flag.key]" />
                                {{ flag.label }}
                            </label>
                        </div>
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel
                                for="password"
                                :value="isEdit ? 'Yeni şifre' : 'Şifre'"
                            />
                            <TextInput
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="new-password"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>
                        <div>
                            <InputLabel for="password_confirmation" value="Şifre tekrar" />
                            <TextInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="new-password"
                            />
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="document in documents"
                            :key="document.key"
                        >
                            <InputLabel :for="document.key" :value="document.label" />
                            <input
                                :id="document.key"
                                type="file"
                                class="mt-1 block w-full text-sm text-gray-500"
                                @change="onFile(document.key, $event)"
                            >
                            <p
                                v-if="staffMember?.documents?.[document.key]?.file_name"
                                class="mt-1 text-xs text-gray-500"
                            >
                                <a
                                    v-if="staffMember.documents[document.key].download_url"
                                    :href="staffMember.documents[document.key].download_url"
                                    class="underline"
                                >
                                    {{ staffMember.documents[document.key].file_name }}
                                </a>
                                <span v-else>
                                    {{ staffMember.documents[document.key].file_name }} — dosya depoda yok
                                </span>
                            </p>
                            <InputError class="mt-2" :message="form.errors[document.key]" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <PrimaryButton :disabled="form.processing">
                            Kaydet
                        </PrimaryButton>
                        <DangerButton
                            v-if="isEdit"
                            type="button"
                            :disabled="deleteForm.processing"
                            @click="destroyStaff"
                        >
                            Sil
                        </DangerButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
