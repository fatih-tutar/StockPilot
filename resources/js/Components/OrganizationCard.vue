<script setup>
import { ref } from 'vue';

const props = defineProps({
    person: { type: Object, default: null },
    canManage: { type: Boolean, required: true },
});

const preview = ref(null);

const onPhoto = (event) => {
    const file = event.target.files?.[0] ?? null;
    if (props.person) {
        props.person.photo = file;
    }
    preview.value = file ? URL.createObjectURL(file) : null;
};

const photoSrc = () => {
    if (preview.value) {
        return preview.value;
    }

    if (props.person?.photo_available) {
        return route('organization.photo', props.person.id);
    }

    return null;
};
</script>

<template>
    <div class="w-52 shrink-0">
        <div class="relative rounded-md bg-slate-700 text-white shadow-sm">
            <img
                v-if="photoSrc()"
                :src="photoSrc()"
                alt=""
                class="absolute left-1 top-3 h-12 w-12 rounded-full border-2 border-white object-cover"
            >
            <div
                v-else
                class="absolute left-1 top-3 h-12 w-12 rounded-full border-2 border-white bg-slate-500"
            />
            <div class="flex min-h-14 items-center py-2 pl-16 pr-2 text-center text-[11px] leading-tight">
                <textarea
                    v-if="canManage && person"
                    v-model="person.title"
                    rows="3"
                    class="w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-center text-[11px] leading-tight text-white placeholder:text-slate-300 focus:ring-0"
                    placeholder="Unvan"
                />
                <span v-else class="w-full whitespace-normal">{{ person?.title || '—' }}</span>
            </div>
            <div class="flex min-h-12 items-center rounded-b-md bg-slate-800 py-2 pl-16 pr-2 text-center text-[11px] font-semibold leading-tight">
                <textarea
                    v-if="canManage && person"
                    v-model="person.name"
                    rows="2"
                    class="w-full resize-none border-0 bg-transparent p-0 text-center text-[11px] font-semibold leading-tight text-white placeholder:text-slate-300 focus:ring-0"
                    placeholder="Ad"
                />
                <span v-else class="w-full whitespace-normal">{{ person?.name || '—' }}</span>
            </div>
        </div>
        <input
            v-if="canManage && person"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="mt-1 block w-full text-[10px] text-gray-500 file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 print:hidden"
            @change="onPhoto"
        >
    </div>
</template>
