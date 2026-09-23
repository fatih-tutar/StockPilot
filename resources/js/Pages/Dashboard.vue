<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const roles = computed(() => user.value?.roles?.join(', ') || '—');
</script>

<template>
    <Head title="Panel" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Panel
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="space-y-2 p-6 text-gray-900">
                        <p>StockPilot oturumunuz açık.</p>
                        <p class="text-sm text-gray-600">
                            Giriş yapan
                            <span class="font-medium text-gray-900">{{ user?.email }}</span>
                            · rol(ler):
                            <span class="font-medium text-gray-900">{{ roles }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
