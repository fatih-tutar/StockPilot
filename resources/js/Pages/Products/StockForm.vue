<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    product: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const columns = computed(() => usePage().props.auth?.user?.columns ?? {});

const form = useForm({
    quantity_piece: '',
    warehouse_quantity: '',
    quantity_pallet: '',
});

const fields = computed(() => [
    columns.value.piece
        ? { key: 'quantity_piece', label: 'Adet', newLabel: 'Yeni adet', current: props.product.quantity_piece ?? 0 }
        : null,
    columns.value.alkop
        ? { key: 'warehouse_quantity', label: 'Depo adet', newLabel: 'Yeni depo adet', current: props.product.warehouse_quantity ?? 0 }
        : null,
    columns.value.pallet
        ? { key: 'quantity_pallet', label: 'Palet', newLabel: 'Yeni palet', current: props.product.quantity_pallet ?? 0 }
        : null,
].filter((field) => field !== null));

const columnClass = computed(() => {
    if (fields.value.length >= 3) {
        return 'sm:grid-cols-3';
    }

    if (fields.value.length === 2) {
        return 'sm:grid-cols-2';
    }

    return '';
});

const nothingEntered = computed(() => {
    const piece = !columns.value.piece || form.quantity_piece === '';
    const warehouse = !columns.value.alkop || form.warehouse_quantity === '';
    const pallet = !columns.value.pallet || form.quantity_pallet === '';

    return piece && warehouse && pallet;
});

const submit = () => {
    form.transform((data) => ({
        quantity_piece: columns.value.piece && data.quantity_piece !== '' ? Number(data.quantity_piece) : null,
        warehouse_quantity: columns.value.alkop && data.warehouse_quantity !== '' ? Number(data.warehouse_quantity) : null,
        quantity_pallet: columns.value.pallet && data.quantity_pallet !== '' ? Number(data.quantity_pallet) : null,
    })).post(route('products.set-stock', props.product.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <form class="max-h-[80vh] overflow-y-auto p-6" @submit.prevent="submit">
        <p class="text-sm font-medium text-gray-500">Stok güncelle</p>
        <h2 class="mt-1 text-2xl font-semibold text-gray-900">{{ product.name }}</h2>

        <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
            <p class="text-sm font-medium text-gray-500">Mevcut</p>
            <div class="mt-3 grid grid-cols-1 gap-4" :class="columnClass">
                <div v-for="field in fields" :key="field.key">
                    <p class="text-sm text-gray-600">{{ field.label }}</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums leading-none text-gray-900">{{ field.current }}</p>
                </div>
            </div>
        </div>

        <div class="mt-5 px-4">
            <div class="grid grid-cols-1 gap-4" :class="columnClass">
                <div v-for="field in fields" :key="'new-' + field.key" class="flex flex-col gap-2">
                    <InputLabel :value="field.newLabel" />
                    <TextInput
                        v-model="form[field.key]"
                        type="number"
                        min="0"
                        class="block w-full"
                        placeholder="Değiştirme"
                    />
                    <InputError :message="form.errors[field.key]" />
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Boş bırakılan alan değişmez.
            </p>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <SecondaryButton type="button" @click="emit('close')">İptal</SecondaryButton>
            <PrimaryButton :disabled="form.processing || nothingEntered">Güncelle</PrimaryButton>
        </div>
    </form>
</template>
