<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    productId: { type: Number, required: true },
    actions: { type: Array, default: () => [] },
});

const emit = defineEmits(['select']);

const open = ref(false);
const button = ref(null);
const position = ref({ top: 0, left: 0 });

const menuItems = [
    { name: 'offer', label: 'Teklif', action: 'offer_button' },
    { name: 'order', label: 'Sipariş', action: 'order_button' },
    { name: 'ship', label: 'Sevkiyat', action: 'shipment_button' },
];

const placeMenu = () => {
    const rect = button.value?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    position.value = {
        top: rect.bottom + 4,
        left: rect.left,
    };
};

const toggle = () => {
    if (!open.value) {
        placeMenu();
    }

    open.value = !open.value;
};

const close = () => {
    open.value = false;
};

const pick = (name) => {
    close();
    emit('select', name);
};

const onDocumentClick = (event) => {
    if (button.value?.contains(event.target)) {
        return;
    }

    close();
};

const onEscape = (event) => {
    if (event.key === 'Escape') {
        close();
    }
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onEscape);
    window.removeEventListener('scroll', close, true);
    window.removeEventListener('resize', close);
});
</script>

<template>
    <div>
        <button
            ref="button"
            type="button"
            class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            aria-label="İşlemler"
            :aria-expanded="open"
            @click="toggle"
        >
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10 4.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM10 11.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM10 18.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z" />
            </svg>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                class="fixed z-50 w-40 rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5"
                :style="{ top: `${position.top}px`, left: `${position.left}px` }"
                @click.stop
            >
                <button
                    v-for="item in menuItems.filter((entry) => actions.includes(entry.action))"
                    :key="item.name"
                    type="button"
                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                    @click="pick(item.name)"
                >
                    {{ item.label }}
                </button>
                <Link
                    :href="route('products.edit', props.productId)"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Düzenle
                </Link>
            </div>
        </Teleport>
    </div>
</template>
