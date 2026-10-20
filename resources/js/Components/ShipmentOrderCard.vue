<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import { ref } from 'vue';

const props = defineProps({
    shipment: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['action']);

const expanded = ref(false);

const toggleDetails = () => {
    expanded.value = !expanded.value;
};

const menuActions = () => {
    if (props.shipment.status === 'received') {
        return [
            { key: 'edit', label: 'Düzenle' },
            { key: 'print', label: 'Siparişi yazdır' },
            { key: 'prepare', label: 'Sevkiyat hazır' },
            { key: 'delete', label: 'Sil', danger: true },
        ];
    }

    if (props.shipment.status === 'preparing') {
        return [
            { key: 'edit', label: 'Düzenle' },
            { key: 'revert', label: 'Geri al' },
            { key: 'invoice', label: 'Fatura hazır' },
            { key: 'delete', label: 'Sil', danger: true },
        ];
    }

    return [
        { key: 'edit', label: 'Düzenle' },
        { key: 'revert', label: 'Geri al' },
        { key: 'archive', label: 'Arşive gönder' },
        { key: 'delete', label: 'Sil', danger: true },
    ];
};

const money = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${Number(value).toLocaleString('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} TL`;
};
</script>

<template>
    <article class="flex flex-col rounded-md border border-gray-200 bg-white shadow-sm">
        <div
            class="flex items-start justify-between gap-3 px-3"
            :class="[
                compact ? 'py-1.5' : 'py-2.5',
                expanded ? 'border-b border-gray-100' : '',
            ]"
        >
            <button
                type="button"
                class="min-w-0 flex-1 rounded text-left hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1"
                :aria-expanded="expanded"
                :aria-label="expanded ? 'Sipariş detayını gizle' : 'Sipariş detayını göster'"
                @click="toggleDetails"
            >
                <span class="flex items-start gap-1.5">
                    <svg
                        class="mt-0.5 h-4 w-4 shrink-0 text-gray-400 transition-transform"
                        :class="expanded ? 'rotate-90' : ''"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <span class="min-w-0">
                        <span
                            class="block truncate font-semibold text-gray-900"
                            :class="compact ? 'text-xs' : 'text-sm'"
                        >
                            {{ shipment.client?.name || 'Müşteri yok' }}
                        </span>
                        <span class="mt-0.5 block text-xs text-gray-500">{{ shipment.ship_date }}</span>
                    </span>
                </span>
            </button>
            <Dropdown v-if="canManage" align="right" width="48">
                <template #trigger>
                    <button
                        type="button"
                        class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                        aria-label="İşlemler"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 4.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM10 11.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM10 18.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z" />
                        </svg>
                    </button>
                </template>
                <template #content>
                    <button
                        v-for="action in menuActions()"
                        :key="action.key"
                        type="button"
                        class="block w-full px-4 py-2 text-left text-sm"
                        :class="action.danger ? 'text-red-600 hover:bg-red-50' : 'text-gray-700 hover:bg-gray-100'"
                        @click="emit('action', action.key)"
                    >
                        {{ action.label }}
                    </button>
                </template>
            </Dropdown>
        </div>

        <div
            v-if="expanded"
            class="flex-1 text-gray-700"
            :class="compact ? 'space-y-1.5 px-3 py-1.5 text-xs' : 'space-y-2 px-3 py-2.5 text-sm'"
        >
            <div class="space-y-1">
                <div
                    v-for="item in (compact ? shipment.items.slice(0, 2) : shipment.items)"
                    :key="item.id"
                    class="flex items-start justify-between gap-2"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium text-gray-900">
                            {{ item.product_name || 'Ürün yok' }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ item.kind || '—' }} · {{ item.quantity_piece }} adet
                        </p>
                    </div>
                    <p class="shrink-0 text-xs text-gray-600">{{ money(item.unit_price) }}</p>
                </div>
                <p
                    v-if="compact && shipment.items.length > 2"
                    class="text-xs text-gray-400"
                >
                    +{{ shipment.items.length - 2 }} ürün daha
                </p>
                <p v-if="shipment.items.length === 0" class="text-xs text-gray-500">
                    Ürün yok.
                </p>
            </div>

            <div
                v-if="!compact"
                class="space-y-0.5 border-t border-gray-100 pt-2 text-xs text-gray-500"
            >
                <p v-if="shipment.creator_name">Oluşturan: {{ shipment.creator_name }}</p>
                <p v-if="shipment.ship_type">Sevk tipi: {{ shipment.ship_type }}</p>
                <p v-if="shipment.vehicle">Araç: {{ shipment.vehicle }}</p>
                <p v-if="shipment.notes">Açıklama: {{ shipment.notes }}</p>
            </div>
            <div
                v-else-if="shipment.vehicle || shipment.ship_type"
                class="truncate text-xs text-gray-500"
            >
                <span v-if="shipment.ship_type">{{ shipment.ship_type }}</span>
                <span v-if="shipment.ship_type && shipment.vehicle"> · </span>
                <span v-if="shipment.vehicle">{{ shipment.vehicle }}</span>
            </div>
        </div>
    </article>
</template>
