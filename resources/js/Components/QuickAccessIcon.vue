<script setup>
defineProps({
    name: { type: String, required: true },
});
</script>

<template>
    <svg
        class="h-5 w-5"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path v-if="name === 'dashboard'" d="M4 4h7v7H4V4Zm9 0h7v5h-7V4ZM4 13h7v7H4v-7Zm9 3h7v4h-7v-4Z" />
        <path
            v-else-if="name === 'products'"
            d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z M12 12l8-4.5M12 12v9M12 12 4 7.5"
        />
        <path
            v-else-if="name === 'clients' || name === 'staff' || name === 'customer-visits'"
            d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 20a8 8 0 0 1 16 0"
        />
        <path
            v-else-if="name === 'shipments' || name === 'vehicles' || name === 'goods-flows'"
            d="M3 7h11v10H3V7Zm11 3h4l3 3v4h-7v-7ZM7 20a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"
        />
        <path
            v-else-if="name === 'quotes' || name === 'offer-lists' || name === 'count-reports'"
            d="M7 3h8l4 4v14H7V3Zm8 0v4h4M9 12h8M9 16h6"
        />
        <path
            v-else-if="name === 'factories' || name === 'factory-orders'"
            d="M3 21V9l6 3V9l6 3V5l6 3v13H3Z"
        />
        <path
            v-else-if="name === 'profile'"
            d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 20a8 8 0 0 1 16 0"
        />
        <path
            v-else-if="name === 'leaves' || name === 'work-tasks'"
            d="M8 3h8a2 2 0 0 1 2 2v14l-6-3-6 3V5a2 2 0 0 1 2-2Z"
        />
        <path v-else-if="name === 'categories' || name === 'roles' || name === 'organization'" d="M4 6h16M4 12h16M4 18h10" />
        <path v-else-if="name === 'molds' || name === 'catalog-items'" d="M12 3 4 7v10l8 4 8-4V7l-8-4Z" />
        <path v-else-if="name === 'custom-orders'" d="M12 3v18M5 8l7-5 7 5M5 16l7 5 7-5" />
        <path v-else-if="name === 'stock-activities'" d="M4 19V9m5 10V5m5 14v-7m5 7V11" />
        <circle v-else cx="12" cy="12" r="8" />
    </svg>
</template>
