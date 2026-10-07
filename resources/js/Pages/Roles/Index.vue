<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    roles: { type: Array, required: true },
    groups: { type: Array, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const drafts = ref([]);

const copyDrafts = () => {
    drafts.value = props.roles.map((role) => ({
        id: role.id,
        name: role.name,
        label: role.label,
        users: role.users,
        locked: role.locked,
        permissions: [...role.permissions],
    }));
};

copyDrafts();
watch(() => props.roles, copyDrafts);

const createForm = useForm({
    name: '',
});

const createRole = () => {
    createForm.post(route('roles.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const checked = (role, name) => role.permissions.includes(name);

const toggle = (role, name) => {
    if (checked(role, name)) {
        role.permissions = role.permissions.filter((permission) => permission !== name);
        return;
    }

    role.permissions = [...role.permissions, name];
};

const saveRole = (role) => {
    router.put(route('roles.update', role.id), {
        permissions: role.permissions,
    }, {
        preserveScroll: true,
    });
};

const deleteRole = (role) => {
    if (!window.confirm(`${role.label} rolü silinsin mi?`)) {
        return;
    }

    router.delete(route('roles.destroy', role.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Roller" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Roller
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
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
                    class="bg-white p-4 shadow-sm sm:rounded-lg"
                    @submit.prevent="createRole"
                >
                    <InputLabel for="role-name" value="Yeni rol" />
                    <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-start">
                        <div class="grow">
                            <TextInput
                                id="role-name"
                                v-model="createForm.name"
                                type="text"
                                class="block w-full"
                                placeholder="Depo sorumlusu"
                            />
                            <InputError class="mt-2" :message="createForm.errors.name" />
                        </div>
                        <PrimaryButton :disabled="createForm.processing">
                            Rol ekle
                        </PrimaryButton>
                    </div>
                </form>

                <section
                    v-for="role in drafts"
                    :key="role.id"
                    class="bg-white p-4 shadow-sm sm:rounded-lg"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ role.label }}
                            </h3>
                            <p class="text-sm text-gray-500">
                                {{ role.users }} kullanıcı
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
                                @click="saveRole(role)"
                            >
                                Kaydet
                            </button>
                            <button
                                v-if="!role.locked"
                                type="button"
                                class="rounded-md border border-red-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-700 hover:bg-red-50"
                                @click="deleteRole(role)"
                            >
                                Sil
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <fieldset
                            v-for="group in groups"
                            :key="`${role.id}-${group.label}`"
                            class="rounded-md border border-gray-200 p-3"
                        >
                            <legend class="px-1 text-sm font-medium text-gray-800">
                                {{ group.label }}
                            </legend>
                            <label
                                v-for="permission in group.permissions"
                                :key="permission.name"
                                class="mt-2 flex items-center gap-2 text-sm text-gray-700"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    :checked="checked(role, permission.name)"
                                    @change="toggle(role, permission.name)"
                                >
                                {{ permission.label }}
                            </label>
                        </fieldset>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
