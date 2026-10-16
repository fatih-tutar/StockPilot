<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    options: { type: Array, required: true },
    selected: { type: Array, required: true },
    max: { type: Number, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const form = useForm({
    quick_access: [...props.selected],
});

const selectedCount = computed(() => form.quick_access.length);

const isChecked = (key) => form.quick_access.includes(key);

const toggle = (key) => {
    if (isChecked(key)) {
        form.quick_access = form.quick_access.filter((entry) => entry !== key);

        return;
    }

    if (form.quick_access.length >= props.max) {
        return;
    }

    form.quick_access = [...form.quick_access, key];
};

const submit = () => {
    form.patch(route('profile.quick-access'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Hızlı erişim</h2>
            <p class="mt-1 text-sm text-gray-600">
                Masaüstünde üst menüde, mobilde alt çubukta gösterilecek sayfaları seçin. En fazla
                {{ max }} sayfa ekleyebilirsiniz.
            </p>
        </header>

        <div
            v-if="flashSuccess"
            class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
        >
            {{ flashSuccess }}
        </div>

        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <p class="text-sm text-gray-500">
                Seçili: {{ selectedCount }} / {{ max }}
            </p>

            <div
                v-for="group in options"
                :key="group.group"
                class="space-y-3 rounded-md border border-gray-200 p-4"
            >
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                    {{ group.group }}
                </h3>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="item in group.items"
                        :key="item.key"
                        class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 hover:bg-gray-50"
                        :class="{
                            'opacity-50':
                                !isChecked(item.key) && selectedCount >= max,
                        }"
                    >
                        <input
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            :checked="isChecked(item.key)"
                            :disabled="!isChecked(item.key) && selectedCount >= max"
                            @change="toggle(item.key)"
                        />
                        <span class="text-sm text-gray-800">{{ item.label }}</span>
                    </label>
                </div>
            </div>

            <InputError :message="form.errors.quick_access" />

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Kaydet</PrimaryButton>
            </div>
        </form>
    </section>
</template>
