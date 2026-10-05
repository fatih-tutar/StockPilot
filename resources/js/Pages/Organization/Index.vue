<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import OrganizationCard from '@/Components/OrganizationCard.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    members: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const form = useForm({
    people: props.members.map((member) => ({
        id: member.id,
        position: member.position,
        name: member.name ?? '',
        title: member.title ?? '',
        user_name: member.user_name ?? null,
        photo: null,
        photo_available: member.photo_available,
    })),
});

const at = (position) => form.people.find((person) => person.position === position);

const save = () => {
    form.post(route('organization.update'), {
        forceFormData: true,
        preserveScroll: true,
    });
};

const printChart = () => {
    window.print();
};
</script>

<template>
    <Head title="Organizasyon" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Organizasyon şeması
                </h2>
                <button
                    type="button"
                    class="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 print:hidden"
                    @click="printChart"
                >
                    Yazdır
                </button>
            </div>
        </template>

        <div class="py-8">
            <form class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" @submit.prevent="save">
                <p
                    v-if="form.people.length === 0"
                    class="bg-white px-4 py-8 text-center text-sm text-gray-500 shadow-sm sm:rounded-lg"
                >
                    Organizasyon kaydı yok.
                </p>

                <div v-else class="overflow-x-auto pb-8">
                    <div class="mx-auto flex min-w-[1040px] flex-col items-center">
                        <OrganizationCard :person="at(1)" :can-manage="canManage" />
                        <div class="h-8 w-px bg-slate-400" />

                        <div class="grid w-full max-w-3xl grid-cols-2 justify-items-center gap-y-8 border-x border-t border-slate-400 px-6 pt-8">
                            <OrganizationCard :person="at(2)" :can-manage="canManage" />
                            <OrganizationCard :person="at(3)" :can-manage="canManage" />
                            <OrganizationCard :person="at(4)" :can-manage="canManage" />
                            <OrganizationCard :person="at(5)" :can-manage="canManage" />
                        </div>

                        <div class="grid w-full max-w-5xl grid-cols-2 gap-8 pt-8">
                            <div class="flex flex-col items-center gap-8 border border-slate-200 bg-slate-50 px-4 py-8">
                                <div class="flex w-full justify-between">
                                    <OrganizationCard :person="at(6)" :can-manage="canManage" />
                                    <OrganizationCard :person="at(7)" :can-manage="canManage" />
                                </div>
                                <div class="flex w-full justify-between">
                                    <OrganizationCard :person="at(8)" :can-manage="canManage" />
                                    <OrganizationCard :person="at(9)" :can-manage="canManage" />
                                </div>
                                <div class="flex w-full justify-between">
                                    <OrganizationCard :person="at(10)" :can-manage="canManage" />
                                    <OrganizationCard :person="at(11)" :can-manage="canManage" />
                                </div>
                            </div>
                            <div class="flex flex-col items-center gap-8 border border-slate-200 bg-slate-50 px-4 py-8">
                                <div class="flex w-full justify-between">
                                    <OrganizationCard :person="at(12)" :can-manage="canManage" />
                                    <OrganizationCard :person="at(13)" :can-manage="canManage" />
                                </div>
                                <div class="flex w-full justify-between">
                                    <OrganizationCard :person="at(14)" :can-manage="canManage" />
                                    <OrganizationCard :person="at(15)" :can-manage="canManage" />
                                </div>
                                <div class="flex w-full justify-between">
                                    <OrganizationCard :person="at(16)" :can-manage="canManage" />
                                    <OrganizationCard :person="at(17)" :can-manage="canManage" />
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex flex-col items-center gap-6 border border-slate-400 px-8 py-6">
                            <OrganizationCard :person="at(18)" :can-manage="canManage" />
                            <OrganizationCard :person="at(19)" :can-manage="canManage" />
                        </div>
                    </div>
                </div>

                <div v-if="canManage && form.people.length > 0" class="mt-6 flex justify-center print:hidden">
                    <p v-if="form.errors['people.0.photo']" class="mr-4 text-sm text-red-600">
                        {{ form.errors['people.0.photo'] }}
                    </p>
                    <PrimaryButton :disabled="form.processing">
                        Kaydet
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
