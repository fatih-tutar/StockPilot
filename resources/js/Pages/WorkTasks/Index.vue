<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    open_tasks: { type: Array, required: true },
    completed_tasks: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const form = useForm({
    title: '',
    due_on: '',
    repeats_monthly: false,
});

const openDrafts = ref([]);
const completedDrafts = ref([]);

const copyTasks = (tasks) => tasks.map((task) => ({
    ...task,
    repeats_monthly: Boolean(task.repeats_monthly),
}));

watch(() => props.open_tasks, (tasks) => {
    openDrafts.value = copyTasks(tasks);
}, { immediate: true });

watch(() => props.completed_tasks, (tasks) => {
    completedDrafts.value = copyTasks(tasks);
}, { immediate: true });

const submit = () => {
    form.post(route('work-tasks.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const save = (task) => {
    router.put(route('work-tasks.update', task.id), {
        title: task.title,
        due_on: task.due_on,
        repeats_monthly: task.repeats_monthly,
        status: task.status,
    }, { preserveScroll: true });
};

const remove = (task) => {
    if (!confirm('Bu görev silinsin mi?')) {
        return;
    }

    router.delete(route('work-tasks.destroy', task.id), {
        preserveScroll: true,
    });
};

const rowClass = (task) => {
    if (task.status === 'completed') {
        return 'bg-emerald-50';
    }

    if (task.is_overdue) {
        return 'bg-red-50';
    }

    return 'bg-amber-50';
};

const fieldClass = 'block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <Head title="İşler" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                İşler
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <form
                    v-if="canManage"
                    class="bg-white p-4 shadow-sm sm:rounded-lg"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-4 md:grid-cols-12 md:items-end">
                        <div class="md:col-span-6">
                            <InputLabel for="title" value="Görev tanımı" />
                            <TextInput
                                id="title"
                                v-model="form.title"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="İş planına eklenecek görev"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.title" />
                        </div>
                        <div class="md:col-span-3">
                            <InputLabel for="due_on" value="Termin" />
                            <TextInput
                                id="due_on"
                                v-model="form.due_on"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.due_on" />
                        </div>
                        <div class="md:col-span-3">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    v-model="form.repeats_monthly"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                >
                                Aylık hatırlat
                            </label>
                            <PrimaryButton class="mt-3 w-full justify-center" :disabled="form.processing">
                                Kaydet
                            </PrimaryButton>
                        </div>
                    </div>
                </form>

                <section class="space-y-3">
                    <h3 class="text-sm font-medium text-gray-700">Sırada</h3>
                    <p
                        v-if="openDrafts.length === 0"
                        class="bg-white px-4 py-8 text-center text-sm text-gray-500 shadow-sm sm:rounded-lg"
                    >
                        Sırada görev yok.
                    </p>
                    <form
                        v-for="task in openDrafts"
                        :key="task.id"
                        class="grid gap-3 rounded-lg p-3 shadow-sm md:grid-cols-12 md:items-center"
                        :class="rowClass(task)"
                        @submit.prevent="save(task)"
                    >
                        <div class="md:col-span-2">
                            <input v-model="task.due_on" type="date" :class="fieldClass" :disabled="!canManage">
                        </div>
                        <div class="md:col-span-4">
                            <input v-model="task.title" type="text" :class="fieldClass" :disabled="!canManage">
                        </div>
                        <div class="md:col-span-2">
                            <select v-model="task.repeats_monthly" :class="fieldClass" :disabled="!canManage">
                                <option :value="false">Tekrarsız</option>
                                <option :value="true">Aylık tekrarlı</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <select v-model="task.status" :class="fieldClass" :disabled="!canManage">
                                <option value="open">Sırada</option>
                                <option value="completed">Tamamlandı</option>
                            </select>
                        </div>
                        <div v-if="canManage" class="flex gap-2 md:col-span-2">
                            <PrimaryButton class="flex-1 justify-center">
                                Düzenle
                            </PrimaryButton>
                            <button
                                type="button"
                                class="flex-1 rounded-md bg-red-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-500"
                                @click="remove(task)"
                            >
                                Sil
                            </button>
                        </div>
                    </form>
                </section>

                <section class="space-y-3">
                    <h3 class="text-center text-lg font-semibold text-gray-800">Tamamlanan işler</h3>
                    <p
                        v-if="completedDrafts.length === 0"
                        class="bg-white px-4 py-8 text-center text-sm text-gray-500 shadow-sm sm:rounded-lg"
                    >
                        Tamamlanan görev yok.
                    </p>
                    <form
                        v-for="task in completedDrafts"
                        :key="task.id"
                        class="grid gap-3 rounded-lg p-3 shadow-sm md:grid-cols-12 md:items-center"
                        :class="rowClass(task)"
                        @submit.prevent="save(task)"
                    >
                        <div class="md:col-span-2">
                            <input v-model="task.due_on" type="date" :class="fieldClass" :disabled="!canManage">
                        </div>
                        <div class="md:col-span-4">
                            <input v-model="task.title" type="text" :class="fieldClass" :disabled="!canManage">
                        </div>
                        <div class="md:col-span-2">
                            <select v-model="task.repeats_monthly" :class="fieldClass" :disabled="!canManage">
                                <option :value="false">Tekrarsız</option>
                                <option :value="true">Aylık tekrarlı</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <select v-model="task.status" :class="fieldClass" :disabled="!canManage">
                                <option value="open">Sırada</option>
                                <option value="completed">Tamamlandı</option>
                            </select>
                        </div>
                        <div v-if="canManage" class="flex gap-2 md:col-span-2">
                            <PrimaryButton class="flex-1 justify-center">
                                Düzenle
                            </PrimaryButton>
                            <button
                                type="button"
                                class="flex-1 rounded-md bg-red-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-500"
                                @click="remove(task)"
                            >
                                Sil
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
