<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Sil' },
    cancelLabel: { type: String, default: 'İptal' },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                {{ title }}
            </h2>
            <p v-if="message" class="mt-2 text-sm text-gray-600">
                {{ message }}
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" :disabled="processing" @click="emit('close')">
                    {{ cancelLabel }}
                </SecondaryButton>
                <DangerButton
                    type="button"
                    :disabled="processing"
                    :class="{ 'opacity-25': processing }"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>
