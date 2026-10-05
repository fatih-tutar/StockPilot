<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    profile: {
        type: Object,
        required: true,
    },
});

const documents = [
    { key: 'photo', label: 'Fotoğraf' },
    { key: 'identity_card', label: 'Kimlik' },
    { key: 'application_form', label: 'İş başvuru formu' },
    { key: 'residence_certificate', label: 'İkametgah' },
    { key: 'health_report', label: 'Sağlık raporu' },
];

const form = useForm({
    name: props.profile.name || '',
    email: props.profile.email || '',
    title: props.profile.title || '',
    phone: props.profile.phone || '',
    phone_2: props.profile.phone_2 || '',
    address: props.profile.address || '',
    hired_on: props.profile.hired_on || '',
    photo: null,
    identity_card: null,
    application_form: null,
    residence_certificate: null,
    health_report: null,
});

const onFile = (key, event) => {
    form[key] = event.target.files[0] || null;
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post(route('profile.update'), {
        forceFormData: true,
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Profil bilgileri
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Ad, iletişim, adres ve belgelerini buradan güncelleyebilirsin.
            </p>
        </header>

        <form
            class="mt-6 space-y-6"
            @submit.prevent="submit"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="name" value="Ad" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="name"
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
                    <InputLabel for="email" value="E-posta" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autocomplete="username"
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

            <div v-if="mustVerifyEmail && profile.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    E-posta adresiniz doğrulanmamış.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Doğrulama e-postasını yeniden göndermek için tıklayın.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    E-posta adresinize yeni bir doğrulama bağlantısı gönderildi.
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
                        v-if="profile.documents?.[document.key]?.file_name"
                        class="mt-1 text-xs text-gray-500"
                    >
                        <a
                            v-if="profile.documents[document.key].download_url"
                            :href="profile.documents[document.key].download_url"
                            class="underline"
                        >
                            {{ profile.documents[document.key].file_name }}
                        </a>
                        <span v-else>
                            {{ profile.documents[document.key].file_name }} — dosya depoda yok
                        </span>
                    </p>
                    <InputError class="mt-2" :message="form.errors[document.key]" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Kaydet</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Kaydedildi.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
