<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    visits: { type: Object, required: true },
    categories: { type: Array, required: true },
    cities: { type: Array, required: true },
    districts: { type: Array, required: true },
    filters: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const search = ref(props.filters.search || '');
const city = ref(props.filters.city || '');
const district = ref(props.filters.district || '');
const category = ref(props.filters.category || '');
const showCategories = ref(false);

const categoryForm = useForm({ name: '' });
const categoryEdits = ref(
    Object.fromEntries(props.categories.map((item) => [item.id, item.name])),
);

watch([search, city, district, category], () => {
    router.get(
        route('customer-visits.index'),
        {
            search: search.value || undefined,
            city: city.value || undefined,
            district: district.value || undefined,
            category: category.value || undefined,
        },
        { preserveState: true, replace: true },
    );
});

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-');
    return `${day}.${month}.${year}`;
};

const addCategory = () => {
    categoryForm.post(route('customer-visits.categories.store'), {
        preserveScroll: true,
        onSuccess: () => categoryForm.reset(),
    });
};

const saveCategory = (id) => {
    router.put(
        route('customer-visits.categories.update', id),
        { name: categoryEdits.value[id] },
        { preserveScroll: true },
    );
};

const deleteCategory = (item) => {
    if (!confirm(`"${item.name}" iş kolu silinsin mi?`)) {
        return;
    }

    router.delete(route('customer-visits.categories.destroy', item.id), {
        preserveScroll: true,
    });
};

const selectClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <Head title="Müşteri ziyaretleri" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Müşteri ziyaretleri
                </h2>
                <div v-if="canManage" class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                        @click="showCategories = !showCategories"
                    >
                        İş kolları
                    </button>
                    <Link
                        :href="route('customer-visits.create')"
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
                    >
                        Yeni ziyaret
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    v-if="flashSuccess"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                >
                    {{ flashSuccess }}
                </div>
                <div
                    v-if="flashError"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {{ flashError }}
                </div>

                <section v-if="showCategories" class="space-y-4 bg-white p-4 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-medium text-gray-800">İş kolları</h3>
                    <form class="flex gap-2" @submit.prevent="addCategory">
                        <TextInput v-model="categoryForm.name" class="block w-full" placeholder="Yeni iş kolu" />
                        <PrimaryButton :disabled="categoryForm.processing">Ekle</PrimaryButton>
                    </form>
                    <p v-if="categoryForm.errors.name" class="text-sm text-red-600">
                        {{ categoryForm.errors.name }}
                    </p>
                    <ul class="space-y-2">
                        <li v-for="item in categories" :key="item.id" class="flex gap-2">
                            <TextInput v-model="categoryEdits[item.id]" class="block w-full" />
                            <button type="button" class="text-sm text-indigo-600" @click="saveCategory(item.id)">
                                Kaydet
                            </button>
                            <button type="button" class="text-sm text-red-600" @click="deleteCategory(item)">
                                Sil
                            </button>
                        </li>
                    </ul>
                </section>

                <div class="grid gap-4 bg-white p-4 shadow-sm sm:grid-cols-4 sm:rounded-lg">
                    <div class="sm:col-span-4">
                        <InputLabel for="search" value="Ara" />
                        <TextInput
                            id="search"
                            v-model="search"
                            class="mt-1 block w-full"
                            placeholder="Müşteri, yetkili veya telefon"
                        />
                    </div>
                    <div>
                        <InputLabel for="city" value="İl" />
                        <select id="city" v-model="city" :class="selectClass">
                            <option value="">Tümü</option>
                            <option v-for="item in cities" :key="item" :value="item">{{ item }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="district" value="İlçe" />
                        <select id="district" v-model="district" :class="selectClass">
                            <option value="">Tümü</option>
                            <option v-for="item in districts" :key="item" :value="item">{{ item }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="category" value="İş kolu" />
                        <select id="category" v-model="category" :class="selectClass">
                            <option value="">Tümü</option>
                            <option v-for="item in categories" :key="item.id" :value="String(item.id)">
                                {{ item.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Müşteri</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Yer</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">İş kolu</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Ziyaret</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Plan</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="visit in visits.data" :key="visit.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ visit.customer_name }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ visit.contact_name || 'Yetkili yok' }}
                                        <span v-if="visit.phone"> · {{ visit.phone }}</span>
                                    </div>
                                    <div v-if="visit.notes" class="mt-1 text-xs text-gray-600">{{ visit.notes }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    <button type="button" class="hover:underline" @click="city = visit.city || ''">
                                        {{ visit.city || '—' }}
                                    </button>
                                    <div>
                                        <button type="button" class="text-xs text-gray-500 hover:underline" @click="district = visit.district || ''">
                                            {{ visit.district || 'İlçe yok' }}
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="visit.category_name"
                                        type="button"
                                        class="text-indigo-600 hover:text-indigo-800"
                                        @click="category = String(visit.customer_visit_category_id)"
                                    >
                                        {{ visit.category_name }}
                                    </button>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ formatDate(visit.visited_on) }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ formatDate(visit.planned_on) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('customer-visits.edit', visit.id)" class="text-indigo-600 hover:text-indigo-800">
                                        Aç
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="visits.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">Ziyaret bulunamadı.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
