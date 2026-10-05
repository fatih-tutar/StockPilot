<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    client: { type: Object, default: null },
    canViewMolds: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const isEdit = computed(() => !!props.client?.id);

const form = useForm({
    name: props.client?.name || '',
    phone: props.client?.phone || '',
    email: props.client?.email || '',
    address: props.client?.address || '',
    notes: props.client?.notes || '',
    is_active: props.client?.is_active ?? true,
});

const deleteForm = useForm({});

const submit = () => {
    if (isEdit.value) {
        form.put(route('clients.update', props.client.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route('clients.store'));
    }
};

const destroyClient = () => {
    if (!confirm(`"${props.client.name}" müşterisi silinsin mi?`)) {
        return;
    }

    deleteForm.delete(route('clients.destroy', props.client.id));
};
</script>

<template>
    <Head :title="isEdit ? 'Müşteriyi düzenle' : 'Yeni müşteri'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? client.name : 'Yeni müşteri' }}
                </h2>
                <Link
                    :href="route('clients.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Müşterilere dön
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
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

                <form
                    class="space-y-4 bg-white p-6 shadow-sm sm:rounded-lg"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel for="name" value="Firma adı" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            class="mt-1 block w-full"
                            required
                            :disabled="isEdit && !canManage"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="phone" value="Telefon" />
                            <TextInput
                                id="phone"
                                v-model="form.phone"
                                class="mt-1 block w-full"
                                :disabled="isEdit && !canManage"
                            />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>
                        <div>
                            <InputLabel for="email" value="E-posta" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="text"
                                class="mt-1 block w-full"
                                :disabled="isEdit && !canManage"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="address" value="Adres" />
                        <textarea
                            id="address"
                            v-model="form.address"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :disabled="isEdit && !canManage"
                        />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <div>
                        <InputLabel for="notes" value="Notlar" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :disabled="isEdit && !canManage"
                        />
                        <InputError class="mt-2" :message="form.errors.notes" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            v-model:checked="form.is_active"
                            :disabled="isEdit && !canManage"
                        />
                        <InputLabel for="is_active" value="Aktif" />
                    </div>

                    <div v-if="!isEdit || canManage" class="flex gap-3">
                        <PrimaryButton :disabled="form.processing">
                            {{ isEdit ? 'Müşteriyi kaydet' : 'Müşteri oluştur' }}
                        </PrimaryButton>
                        <DangerButton
                            v-if="isEdit && canManage"
                            type="button"
                            :disabled="deleteForm.processing"
                            @click="destroyClient"
                        >
                            Sil
                        </DangerButton>
                    </div>
                </form>

                <section
                    v-if="isEdit && canViewMolds"
                    class="bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <h3 class="text-sm font-medium text-gray-800">Kalıplar</h3>
                    <p v-if="!client.molds?.length" class="mt-3 text-sm text-gray-500">
                        Bu firmaya bağlı kalıp yok.
                    </p>
                    <ul v-else class="mt-4 space-y-3">
                        <li
                            v-for="mold in client.molds"
                            :key="mold.id"
                            class="flex items-start justify-between gap-3 rounded-md border border-gray-100 px-4 py-3"
                        >
                            <div class="text-sm text-gray-800">
                                {{ mold.number || '—' }}
                                · {{ mold.factory_name || '—' }}
                                · {{ mold.due_on || 'Termin yok' }}
                                <span v-if="mold.archived" class="text-gray-500">· Arşiv</span>
                            </div>
                            <Link
                                :href="route('molds.edit', mold.id)"
                                class="text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                Aç
                            </Link>
                        </li>
                    </ul>
                </section>

                <section
                    v-if="isEdit"
                    class="bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <h3 class="text-sm font-medium text-gray-800">Özel siparişler</h3>
                    <p v-if="!client.custom_orders?.length" class="mt-3 text-sm text-gray-500">
                        Bu firmaya bağlı özel sipariş yok.
                    </p>
                    <ul v-else class="mt-4 space-y-3">
                        <li
                            v-for="order in client.custom_orders"
                            :key="order.id"
                            class="rounded-md border border-gray-100 px-4 py-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm text-gray-800">
                                        {{ order.ordered_on ? order.ordered_on.split('-').reverse().join('.') : '—' }}
                                        · {{ order.delivery_label }}
                                        · {{ order.status_label }}
                                    </div>
                                    <p v-if="order.notes" class="mt-1 text-sm text-gray-600">
                                        {{ order.notes }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-700">
                                        {{ order.items.map((item) => item.product_name).join(', ') || 'Kalem yok' }}
                                    </p>
                                </div>
                                <Link
                                    :href="route('custom-orders.edit', order.id)"
                                    class="text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    Aç
                                </Link>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
