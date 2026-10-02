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
    visit: { type: Object, default: null },
    categories: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const isEdit = computed(() => !!props.visit?.id);
const locked = computed(() => isEdit.value && !props.canManage);

const form = useForm({
    city: props.visit?.city || '',
    district: props.visit?.district || '',
    customer_visit_category_id: props.visit?.customer_visit_category_id || '',
    customer_name: props.visit?.customer_name || '',
    contact_name: props.visit?.contact_name || '',
    phone: props.visit?.phone || '',
    visited_on: props.visit?.visited_on || '',
    planned_on: props.visit?.planned_on || '',
    address: props.visit?.address || '',
    notes: props.visit?.notes || '',
});

const deleteForm = useForm({});
const fieldClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

const submit = () => {
    if (isEdit.value) {
        form.put(route('customer-visits.update', props.visit.id));
        return;
    }

    form.post(route('customer-visits.store'));
};

const destroyVisit = () => {
    if (!confirm(`"${props.visit.customer_name}" ziyareti silinsin mi?`)) {
        return;
    }

    deleteForm.delete(route('customer-visits.destroy', props.visit.id));
};
</script>

<template>
    <Head :title="isEdit ? 'Ziyareti düzenle' : 'Yeni ziyaret'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? visit.customer_name : 'Yeni ziyaret' }}
                </h2>
                <Link :href="route('customer-visits.index')" class="text-sm text-gray-600 hover:text-gray-900">
                    Ziyaretlere dön
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

                <form class="space-y-4 bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="city" value="İl" />
                            <TextInput id="city" v-model="form.city" class="mt-1 block w-full" :disabled="locked" />
                        </div>
                        <div>
                            <InputLabel for="district" value="İlçe" />
                            <TextInput id="district" v-model="form.district" class="mt-1 block w-full" :disabled="locked" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="customer_visit_category_id" value="İş kolu" />
                        <select
                            id="customer_visit_category_id"
                            v-model="form.customer_visit_category_id"
                            :class="fieldClass"
                            :disabled="locked"
                        >
                            <option value="">Seçilmedi</option>
                            <option v-for="item in categories" :key="item.id" :value="item.id">
                                {{ item.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="customer_name" value="Müşteri" />
                            <TextInput id="customer_name" v-model="form.customer_name" class="mt-1 block w-full" required :disabled="locked" />
                            <InputError class="mt-2" :message="form.errors.customer_name" />
                        </div>
                        <div>
                            <InputLabel for="contact_name" value="Yetkili" />
                            <TextInput id="contact_name" v-model="form.contact_name" class="mt-1 block w-full" :disabled="locked" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="phone" value="Telefon" />
                        <TextInput id="phone" v-model="form.phone" class="mt-1 block w-full" :disabled="locked" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="visited_on" value="Ziyaret tarihi" />
                            <TextInput id="visited_on" v-model="form.visited_on" type="date" class="mt-1 block w-full" :disabled="locked" />
                        </div>
                        <div>
                            <InputLabel for="planned_on" value="Planlanan tarih" />
                            <TextInput id="planned_on" v-model="form.planned_on" type="date" class="mt-1 block w-full" :disabled="locked" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="address" value="Açık adres" />
                        <textarea id="address" v-model="form.address" rows="3" :class="fieldClass" :disabled="locked" />
                    </div>
                    <div>
                        <InputLabel for="notes" value="Ziyaret notu" />
                        <textarea id="notes" v-model="form.notes" rows="3" :class="fieldClass" :disabled="locked" />
                    </div>

                    <div v-if="!isEdit || canManage" class="flex gap-3">
                        <PrimaryButton :disabled="form.processing">
                            {{ isEdit ? 'Ziyareti kaydet' : 'Ziyaret oluştur' }}
                        </PrimaryButton>
                        <DangerButton v-if="isEdit && canManage" type="button" :disabled="deleteForm.processing" @click="destroyVisit">
                            Sil
                        </DangerButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
