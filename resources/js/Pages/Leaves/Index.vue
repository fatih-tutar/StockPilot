<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    upcoming: { type: Array, required: true },
    past: { type: Array, required: true },
    balances: { type: Array, required: true },
    year: { type: Number, required: true },
    years: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const rules = [
    'İzin girişleri yalnızca 1 Ocak ile 31 Mart arasında yapılabilir. Bu tarihlerin dışında yöneticinizle görüşün.',
    'Tek seferde en fazla 14 gün girilebilir.',
    'İki izin arasında en az 100 gün olmalıdır. Yönetici bu kuralın dışına çıkabilir.',
    'Yıllık hak: 1–5 yıl 14 gün, 5–15 yıl 20 gün, 15 yıl ve üzeri 26 gün. Bir yılı doldurmayan personelin hakkı yoktur.',
    'Aynı departmanda tarihleri çakışan izin girilemez.',
    'Onay bekleyen talepler kırmızı görünür.',
];

const changeYear = (event) => {
    router.get(route('leaves.index'), { year: event.target.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const remove = (leave) => {
    if (!confirm('Bu izin silinsin mi?')) {
        return;
    }

    router.delete(route('leaves.destroy', leave.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="İzinler" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    İzinler
                </h2>
                <Link
                    :href="route('leaves.create')"
                    class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
                >
                    Yeni izin
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <p
                    v-if="flashSuccess"
                    class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ flashSuccess }}
                </p>

                <div class="flex items-center gap-3">
                    <label for="year" class="text-sm text-gray-700">Yıl</label>
                    <select
                        id="year"
                        :value="year"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="changeYear"
                    >
                        <option v-for="option in years" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                </div>

                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Personel</th>
                                <th class="px-4 py-3">Başlangıç</th>
                                <th class="px-4 py-3">İşe dönüş</th>
                                <th class="px-4 py-3">Gün</th>
                                <th class="px-4 py-3">Durum</th>
                                <th v-if="canManage" class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="upcoming.length === 0">
                                <td class="px-4 py-6 text-gray-500" :colspan="canManage ? 6 : 5">
                                    Bu yıl için yaklaşan izin yok.
                                </td>
                            </tr>
                            <tr v-for="leave in upcoming" :key="leave.id">
                                <td class="px-4 py-3" :class="leave.is_pending ? 'font-medium text-red-700' : 'text-gray-800'">
                                    {{ leave.user_name }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ leave.start_label }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ leave.return_label }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ leave.leave_days }}</td>
                                <td class="px-4 py-3" :class="leave.is_pending ? 'font-medium text-red-700' : 'text-gray-700'">
                                    {{ leave.status_label }}
                                </td>
                                <td v-if="canManage" class="px-4 py-3 text-right">
                                    <Link :href="route('leaves.edit', leave.id)" class="text-indigo-600 hover:text-indigo-800">
                                        Düzenle
                                    </Link>
                                    <button type="button" class="ml-3 text-red-600 hover:text-red-800" @click="remove(leave)">
                                        Sil
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <h3 class="text-sm font-medium text-gray-700">Geçmiş izinler</h3>

                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Personel</th>
                                <th class="px-4 py-3">Başlangıç</th>
                                <th class="px-4 py-3">İşe dönüş</th>
                                <th class="px-4 py-3">Gün</th>
                                <th class="px-4 py-3">Durum</th>
                                <th v-if="canManage" class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="past.length === 0">
                                <td class="px-4 py-6 text-gray-500" :colspan="canManage ? 6 : 5">
                                    Geçmiş izin yok.
                                </td>
                            </tr>
                            <tr v-for="leave in past" :key="leave.id">
                                <td class="px-4 py-3 text-gray-800">{{ leave.user_name }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ leave.start_label }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ leave.return_label }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ leave.leave_days }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ leave.status_label }}</td>
                                <td v-if="canManage" class="px-4 py-3 text-right">
                                    <Link :href="route('leaves.edit', leave.id)" class="text-indigo-600 hover:text-indigo-800">
                                        Düzenle
                                    </Link>
                                    <button type="button" class="ml-3 text-red-600 hover:text-red-800" @click="remove(leave)">
                                        Sil
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section v-if="canManage" class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <h3 class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-gray-800">
                        Yıllık izin hakları
                    </h3>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Personel</th>
                                <th class="px-4 py-3">İşe giriş</th>
                                <th class="px-4 py-3">Hak</th>
                                <th class="px-4 py-3">Kullanılan</th>
                                <th class="px-4 py-3">Kalan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="balances.length === 0">
                                <td class="px-4 py-6 text-gray-500" colspan="5">
                                    Gösterilecek personel yok.
                                </td>
                            </tr>
                            <tr v-for="row in balances" :key="row.name">
                                <td class="px-4 py-3 text-gray-800">{{ row.name }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ row.hired_on ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ row.entitlement }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ row.used }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ row.remaining }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-medium text-gray-800">Kurallar</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                        <li v-for="rule in rules" :key="rule">{{ rule }}</li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
