<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    group: { type: Object, default: null },
    canManage: { type: Boolean, default: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const isEdit = computed(() => !!props.group?.id);
const locked = computed(() => isEdit.value && !props.canManage);

const blankLine = () => ({ id: null, code: '', model: '', quantity: '', price: '' });

const form = useForm({
    product_code: props.group?.product_code || '',
    description: props.group?.description || '',
    image_1: null,
    image_2: null,
    lines: props.group?.lines?.length
        ? props.group.lines.map((line) => ({
            id: line.id,
            code: line.code || '',
            model: line.model || '',
            quantity: line.quantity || '',
            price: line.price || '',
        }))
        : [blankLine()],
});

const onFile = (key, event) => {
    form[key] = event.target.files[0] || null;
};

const addLine = () => {
    form.lines.push(blankLine());
};

const removeLine = (index) => {
    if (form.lines.length === 1) {
        return;
    }

    form.lines.splice(index, 1);
};

const existingImage = (key) => props.group?.images?.find((image) => image.key === key);

const submit = () => {
    if (isEdit.value) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(route('catalog-items.update', props.group.id), {
            forceFormData: true,
            preserveScroll: true,
        });

        return;
    }

    form.post(route('catalog-items.store'), { forceFormData: true });
};
</script>

<template>
    <Head :title="isEdit ? 'Fiyatı düzenle' : 'Yeni fiyat'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? 'Fiyatı düzenle' : 'Yeni fiyat' }}
                </h2>
                <Link :href="route('catalog-items.index')" class="text-sm text-gray-600 hover:text-gray-900">
                    Listeye dön
                </Link>
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

                <form class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="product_code" value="Ürün no" />
                            <TextInput
                                id="product_code"
                                v-model="form.product_code"
                                class="mt-1 block w-full"
                                :disabled="locked"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.product_code" />
                        </div>
                        <div>
                            <InputLabel for="description" value="Açıklama" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :disabled="locked"
                            />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-for="slot in ['image_1', 'image_2']" :key="slot">
                            <InputLabel :for="slot" :value="slot === 'image_1' ? 'Fotoğraf 1' : 'Fotoğraf 2'" />
                            <input
                                :id="slot"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="mt-1 block w-full text-sm"
                                :disabled="locked"
                                @change="onFile(slot, $event)"
                            />
                            <p v-if="existingImage(slot)?.file_name" class="mt-2 text-xs text-gray-500">
                                {{ existingImage(slot).file_name }}
                                <span v-if="!existingImage(slot).url"> (dosya yok)</span>
                            </p>
                            <img
                                v-if="existingImage(slot)?.url"
                                :src="existingImage(slot).url"
                                alt=""
                                class="mt-2 h-24 w-24 rounded border border-gray-200 object-cover"
                            />
                            <InputError class="mt-2" :message="form.errors[slot]" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-gray-800">Satırlar</h3>
                            <button
                                v-if="!locked"
                                type="button"
                                class="text-xs font-semibold uppercase tracking-widest text-gray-700 hover:text-gray-900"
                                @click="addLine"
                            >
                                Satır ekle
                            </button>
                        </div>
                        <InputError :message="form.errors.lines" />

                        <div
                            v-for="(line, index) in form.lines"
                            :key="line.id || `new-${index}`"
                            class="grid gap-3 border-t border-gray-100 pt-3 md:grid-cols-[1fr_2fr_1fr_1fr_auto]"
                        >
                            <div>
                                <InputLabel :for="`code-${index}`" value="Kod" />
                                <TextInput
                                    :id="`code-${index}`"
                                    v-model="line.code"
                                    class="mt-1 block w-full"
                                    :disabled="locked"
                                />
                                <InputError class="mt-2" :message="form.errors[`lines.${index}.code`]" />
                            </div>
                            <div>
                                <InputLabel :for="`model-${index}`" value="Model" />
                                <TextInput
                                    :id="`model-${index}`"
                                    v-model="line.model"
                                    class="mt-1 block w-full"
                                    :disabled="locked"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors[`lines.${index}.model`]" />
                            </div>
                            <div>
                                <InputLabel :for="`quantity-${index}`" value="Adet / metre" />
                                <TextInput
                                    :id="`quantity-${index}`"
                                    v-model="line.quantity"
                                    class="mt-1 block w-full"
                                    :disabled="locked"
                                />
                                <InputError class="mt-2" :message="form.errors[`lines.${index}.quantity`]" />
                            </div>
                            <div>
                                <InputLabel :for="`price-${index}`" value="Fiyat" />
                                <TextInput
                                    :id="`price-${index}`"
                                    v-model="line.price"
                                    class="mt-1 block w-full"
                                    :disabled="locked"
                                    placeholder="120 TL"
                                />
                                <InputError class="mt-2" :message="form.errors[`lines.${index}.price`]" />
                            </div>
                            <div class="flex items-end">
                                <button
                                    v-if="!locked && !line.id && form.lines.length > 1"
                                    type="button"
                                    class="mb-2 text-xs font-semibold uppercase tracking-widest text-red-600"
                                    @click="removeLine(index)"
                                >
                                    Çıkar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="!locked" class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">Kaydet</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
