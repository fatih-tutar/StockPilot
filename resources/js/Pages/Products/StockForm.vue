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

const current = computed(() => {
    const parts = [];

    if (columns.value.piece) {
        parts.push(`Adet ${props.product.quantity_piece ?? 0}`);
    }

    if (columns.value.alkop) {
        parts.push(`Depo adet ${props.product.warehouse_quantity ?? 0}`);
    }

    if (columns.value.pallet) {
        parts.push(`Palet ${props.product.quantity_pallet ?? 0}`);
    }

    return parts.join(' · ');
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
        <h2 class="text-lg font-medium text-gray-900">Stok güncelle</h2>
        <p class="mt-1 text-sm text-gray-500">{{ product.name }}</p>

        <p class="mt-6 text-sm text-gray-700">
            Mevcut: <span class="font-medium text-gray-900">{{ current }}</span>
        </p>

        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div v-if="columns.piece">
                <InputLabel value="Yeni adet" />
                <TextInput
                    v-model="form.quantity_piece"
                    type="number"
                    min="0"
                    class="mt-1 block w-full"
                    placeholder="Değiştirme"
                />
                <InputError class="mt-1" :message="form.errors.quantity_piece" />
            </div>
            <div v-if="columns.alkop">
                <InputLabel value="Yeni depo adet" />
                <TextInput
                    v-model="form.warehouse_quantity"
                    type="number"
                    min="0"
                    class="mt-1 block w-full"
                    placeholder="Değiştirme"
                />
                <InputError class="mt-1" :message="form.errors.warehouse_quantity" />
            </div>
            <div v-if="columns.pallet">
                <InputLabel value="Yeni palet" />
                <TextInput
                    v-model="form.quantity_pallet"
                    type="number"
                    min="0"
                    class="mt-1 block w-full"
                    placeholder="Değiştirme"
                />
                <InputError class="mt-1" :message="form.errors.quantity_pallet" />
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-500">
            Boş bırakılan alan değişmez.
        </p>

        <div class="mt-6 flex justify-end gap-3">
            <SecondaryButton type="button" @click="emit('close')">İptal</SecondaryButton>
            <PrimaryButton :disabled="form.processing || nothingEntered">Güncelle</PrimaryButton>
        </div>
    </form>
</template>
