<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
    items: {
        type: Array,
        required: true,
    },
});

const open = ref(false);
const root = ref(null);

const buttonClass = computed(() =>
    props.active
        ? 'inline-flex h-full items-center gap-1 whitespace-nowrap border-b-2 border-indigo-400 px-1 pt-1 text-sm font-medium text-gray-900 focus:outline-none'
        : 'inline-flex h-full items-center gap-1 whitespace-nowrap border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 focus:outline-none',
);

const close = () => {
    open.value = false;
};

const closeOnEscape = (event) => {
    if (open.value && event.key === 'Escape') {
        close();
    }
};

const closeOnOutsideClick = (event) => {
    if (open.value && root.value && !root.value.contains(event.target)) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
    document.addEventListener('click', closeOnOutsideClick);
});

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.removeEventListener('click', closeOnOutsideClick);
});
</script>

<template>
    <div ref="root" class="relative flex h-full items-stretch">
        <button type="button" :class="buttonClass" @click="open = !open">
            {{ label }}
            <svg
                class="h-4 w-4"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
            >
                <path
                    fill-rule="evenodd"
                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <div
            v-show="open"
            class="absolute start-0 top-full z-50 w-56 rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5"
        >
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                class="block px-4 py-2 text-sm leading-5 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                :class="item.active ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700'"
                @click="close"
            >
                {{ item.label }}
            </Link>
        </div>
    </div>
</template>
