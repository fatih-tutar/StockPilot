<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    leave: { type: Object, default: null },
    staff: { type: Array, required: true },
    statuses: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const form = useForm({
    start_on: props.leave?.start_on ?? '',
    return_on: props.leave?.return_on ?? '',
    ...(props.canManage && !props.leave ? { user_id: '' } : {}),
    ...(props.canManage && props.leave ? { status: props.leave.status } : {}),
});

const dayCount = computed(() => {
    if (!form.start_on || !form.return_on) {
        return '';
    }

    const [startYear, startMonth, startDay] = form.start_on.split('-').map(Number);
    const [returnYear, returnMonth, returnDay] = form.return_on.split('-').map(Number);
    const start = Date.UTC(startYear, startMonth - 1, startDay);
    const end = Date.UTC(returnYear, returnMonth - 1, returnDay);
    const days = Math.round((end - start) / 86400000);

    return days > 0 ? String(days) : '0';
});

const submit = () => {
    if (props.leave) {
        form.put(route('leaves.update', props.leave.id));

        return;
    }

    form.post(route('leaves.store'));
};
</script>

<template>
    <Head :title="leave ? 'İzin düzenle' : 'Yeni izin'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ leave ? 'İzin düzenle' : 'Yeni izin' }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <form class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div v-if="canManage && !leave">
                        <InputLabel for="user_id" value="Personel" />
                        <select
                            id="user_id"
                            v-model="form.user_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="" disabled>Seçin</option>
                            <option v-for="person in staff" :key="person.id" :value="person.id">
                                {{ person.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.user_id" />
                    </div>

                    <p v-else-if="leave" class="text-sm text-gray-700">
                        Personel: <span class="font-medium text-gray-900">{{ leave.user_name }}</span>
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="start_on" value="Başlangıç" />
                            <TextInput id="start_on" v-model="form.start_on" type="date" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.start_on" />
                        </div>
                        <div>
                            <InputLabel for="return_on" value="İşe dönüş" />
                            <TextInput id="return_on" v-model="form.return_on" type="date" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.return_on" />
                        </div>
                    </div>

                    <p class="text-sm text-gray-700">
                        İzin günü: <span class="font-medium text-gray-900">{{ dayCount || '—' }}</span>
                    </p>

                    <div v-if="canManage && leave">
                        <InputLabel for="status" value="Durum" />
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="status in statuses" :key="status.value" :value="status.value">
                                {{ status.label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.status" />
                    </div>

                    <div class="flex items-center gap-3">
                        <PrimaryButton :disabled="form.processing">Kaydet</PrimaryButton>
                        <Link :href="route('leaves.index')" class="text-sm text-gray-600 underline">Vazgeç</Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
