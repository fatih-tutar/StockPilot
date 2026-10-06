<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    rows: { type: Array, required: true },
    chart: { type: Array, required: true },
    weights: { type: Object, required: true },
    weekly: { type: Boolean, required: true },
    canManage: { type: Boolean, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const showChart = ref(false);
const chartPeriod = ref('month');
const chartPeriods = [
    { id: 'month', label: 'Aylık' },
    { id: 'quarter', label: '3 aylık' },
    { id: 'year', label: 'Yıllık' },
];
const editingId = ref(null);

const form = useForm({
    recorded_on: '',
    store_outgoing: '',
    store_incoming: '',
    warehouse_outgoing: '',
    warehouse_incoming: '',
});

const series = ref([
    { key: 'store_outgoing', label: 'Çağlayan giden', color: '#dc3545', on: false },
    { key: 'store_incoming', label: 'Çağlayan gelen', color: '#198754', on: false },
    { key: 'warehouse_outgoing', label: 'Alkop giden', color: '#0d6efd', on: false },
    { key: 'warehouse_incoming', label: 'Alkop gelen', color: '#f59e0b', on: false },
    { key: 'total_outgoing', label: 'Toplam giden', color: '#111827', on: true },
    { key: 'total_incoming', label: 'Toplam gelen', color: '#6d28d9', on: false },
]);

const months = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];

const niceCeiling = (value) => {
    if (value <= 0) {
        return 1;
    }

    const power = 10 ** Math.floor(Math.log10(value));
    const fraction = value / power;
    const nice = fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10;

    return nice * power;
};

const amountFields = [
    'store_incoming',
    'store_outgoing',
    'warehouse_incoming',
    'warehouse_outgoing',
    'total_incoming',
    'total_outgoing',
];

const chartPoints = computed(() => {
    if (chartPeriod.value === 'month') {
        return props.chart.map((point) => {
            const [year, month] = point.month.split('-').map(Number);

            return {
                ...point,
                label: `${months[month - 1]} ${String(year).slice(2)}`,
            };
        });
    }

    const groups = new Map();

    props.chart.forEach((point) => {
        const [year, month] = point.month.split('-').map(Number);
        const quarter = Math.ceil(month / 3);
        const key = chartPeriod.value === 'year' ? String(year) : `${year}-Q${quarter}`;
        const label = chartPeriod.value === 'year' ? String(year) : `${year} Ç${quarter}`;

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                label,
                ...Object.fromEntries(amountFields.map((field) => [field, 0])),
            });
        }

        const bucket = groups.get(key);
        amountFields.forEach((field) => {
            bucket[field] += Number(point[field]) || 0;
        });
    });

    return [...groups.values()];
});

const chartBox = ref(null);
const chartSize = ref({ width: 960, height: 288 });
let chartObserver;

const measureChart = () => {
    if (!chartBox.value) {
        return;
    }

    const { width, height } = chartBox.value.getBoundingClientRect();

    if (width < 1 || height < 1) {
        return;
    }

    chartSize.value = {
        width: Math.round(width),
        height: Math.round(height),
    };
};

watch(showChart, async (open) => {
    chartObserver?.disconnect();

    if (!open) {
        return;
    }

    await nextTick();
    measureChart();

    if (!chartBox.value) {
        return;
    }

    chartObserver = new ResizeObserver(() => measureChart());
    chartObserver.observe(chartBox.value);
});

onBeforeUnmount(() => chartObserver?.disconnect());

const chartFrame = computed(() => {
    const points = chartPoints.value;
    const width = chartSize.value.width;
    const height = chartSize.value.height;
    const left = 88;
    const right = 12;
    const top = 16;
    const bottom = 40;
    const plotWidth = width - left - right;
    const plotHeight = height - top - bottom;
    const enabled = series.value.filter((item) => item.on);
    const peak = Math.max(0, ...points.flatMap((point) => enabled.map((item) => Number(point[item.key]) || 0)));
    const max = niceCeiling(peak);
    const xAt = (index) => left + (points.length <= 1 ? plotWidth / 2 : (index / (points.length - 1)) * plotWidth);
    const yAt = (value) => top + plotHeight - (value / max) * plotHeight;
    const yTicks = [0, 0.25, 0.5, 0.75, 1].map((step) => {
        const value = max * step;

        return {
            value,
            label: new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 0 }).format(value),
            y: yAt(value),
        };
    });
    const labelStep = Math.max(1, Math.ceil(points.length / 8));
    const xTicks = points.flatMap((point, index) => {
        const last = index === points.length - 1;
        if (index % labelStep !== 0 && !last) {
            return [];
        }

        return [{
            label: point.label,
            x: xAt(index),
            anchor: index === 0 ? 'start' : (last ? 'end' : 'middle'),
        }];
    });
    const lines = enabled.map((item) => ({
        ...item,
        points: points.map((point, index) => `${xAt(index)},${yAt(Number(point[item.key]) || 0)}`).join(' '),
    }));
    const slots = points.map((point, index) => ({
        x: xAt(index),
        dots: enabled.map((item) => ({
            key: item.key,
            color: item.color,
            y: yAt(Number(point[item.key]) || 0),
        })),
    }));

    return {
        width,
        height,
        left,
        right,
        top,
        bottom,
        axisBottom: top + plotHeight,
        yTicks,
        xTicks,
        lines,
        slots,
    };
});

const hoveredIndex = ref(null);

const formatKilos = (value) => `${new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 3 }).format(value)} kg`;

const chartHover = computed(() => {
    const point = chartPoints.value[hoveredIndex.value];
    const slot = chartFrame.value.slots[hoveredIndex.value];

    if (!point || !slot) {
        return null;
    }

    const frame = chartFrame.value;
    const cardWidth = 240;
    const towardLeft = slot.x + 16 + cardWidth > frame.width - frame.right;

    return {
        label: point.label,
        x: slot.x,
        dots: slot.dots,
        rows: frame.lines.map((line) => ({
            key: line.key,
            label: line.label,
            color: line.color,
            value: formatKilos(Number(point[line.key]) || 0),
        })),
        style: towardLeft
            ? { right: `${frame.width - slot.x + 12}px`, top: '8px' }
            : { left: `${slot.x + 12}px`, top: '8px' },
    };
});

const onChartMove = (event) => {
    const frame = chartFrame.value;
    const box = chartBox.value?.getBoundingClientRect();

    if (!box || frame.slots.length === 0) {
        return;
    }

    const localX = ((event.clientX - box.left) / box.width) * frame.width;

    if (localX < frame.left || localX > frame.width - frame.right) {
        hoveredIndex.value = null;

        return;
    }

    let nearest = 0;
    let best = Infinity;

    frame.slots.forEach((slot, index) => {
        const distance = Math.abs(slot.x - localX);

        if (distance < best) {
            best = distance;
            nearest = index;
        }
    });

    hoveredIndex.value = nearest;
};

const onChartLeave = () => {
    hoveredIndex.value = null;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            editingId.value = null;
        },
    };

    if (editingId.value) {
        form.put(route('goods-flows.update', editingId.value), options);

        return;
    }

    form.post(route('goods-flows.store'), options);
};

const edit = (row) => {
    editingId.value = row.id;
    form.recorded_on = row.recorded_on;
    form.store_outgoing = row.store_outgoing_value;
    form.store_incoming = row.store_incoming_value;
    form.warehouse_outgoing = row.warehouse_outgoing_value;
    form.warehouse_incoming = row.warehouse_incoming_value;
};

const cancelEdit = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const remove = (row) => {
    if (!confirm(`${row.label} gününe ait kayıt silinsin mi?`)) {
        return;
    }

    router.delete(route('goods-flows.destroy', row.id), { preserveScroll: true });
};

const rowClass = (kind) => ({
    week: 'bg-sky-50 text-sky-950',
    month: 'bg-amber-50 text-amber-950',
    year: 'bg-emerald-50 text-emerald-950',
    day: 'bg-white text-gray-800',
}[kind]);
</script>

<template>
    <Head title="Gelen giden" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Gelen giden</h2>
                <div class="flex gap-2">
                    <Link
                        :href="route('goods-flows.index', weekly ? {} : { view: 'weekly' })"
                        class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                    >
                        {{ weekly ? 'Günlük' : 'Haftalık' }}
                    </Link>
                    <Link
                        :href="route('goods-flows.report')"
                        class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                    >
                        Rapor
                    </Link>
                    <button
                        type="button"
                        class="rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white"
                        @click="showChart = !showChart"
                    >
                        Grafik
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <p v-if="flashSuccess" class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ flashSuccess }}
                </p>

                <section class="grid gap-3 sm:grid-cols-3">
                    <article class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Çağlayan</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ weights.store }}</p>
                    </article>
                    <article class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Alkop</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ weights.warehouse }}</p>
                    </article>
                    <article class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Toplam tonaj</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ weights.total }}</p>
                    </article>
                </section>

                <section v-if="showChart" class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-medium text-gray-800">
                            {{ chartPeriods.find((period) => period.id === chartPeriod)?.label }} toplamlar
                        </h3>
                        <div class="flex gap-2">
                            <button
                                v-for="period in chartPeriods"
                                :key="period.id"
                                type="button"
                                class="rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-widest"
                                :class="chartPeriod === period.id ? 'bg-gray-800 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                                @click="chartPeriod = period.id"
                            >
                                {{ period.label }}
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-3 text-sm text-gray-700">
                        <label v-for="item in series" :key="item.key" class="inline-flex items-center gap-2">
                            <input v-model="item.on" type="checkbox" class="rounded border-gray-300 text-indigo-600">
                            <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: item.color }" />
                            {{ item.label }}
                        </label>
                    </div>
                    <p v-if="chartPoints.length === 0" class="mt-4 text-sm text-gray-500">Grafik için kayıt yok.</p>
                    <div
                        v-else
                        ref="chartBox"
                        class="relative mt-4 h-72 w-full cursor-crosshair"
                        @pointermove="onChartMove"
                        @pointerleave="onChartLeave"
                    >
                    <svg :viewBox="`0 0 ${chartFrame.width} ${chartFrame.height}`" class="block h-full w-full" role="img" aria-label="Gelen giden grafiği">
                        <line
                            v-for="tick in chartFrame.yTicks"
                            :key="`grid-${tick.label}`"
                            :x1="chartFrame.left"
                            :x2="chartFrame.width - chartFrame.right"
                            :y1="tick.y"
                            :y2="tick.y"
                            stroke="#e5e7eb"
                        />
                        <line
                            :x1="chartFrame.left"
                            :x2="chartFrame.left"
                            :y1="chartFrame.top"
                            :y2="chartFrame.axisBottom"
                            stroke="#9ca3af"
                        />
                        <line
                            :x1="chartFrame.left"
                            :x2="chartFrame.width - chartFrame.right"
                            :y1="chartFrame.axisBottom"
                            :y2="chartFrame.axisBottom"
                            stroke="#9ca3af"
                        />
                        <text
                            v-for="tick in chartFrame.yTicks"
                            :key="`y-${tick.label}`"
                            :x="chartFrame.left - 8"
                            :y="tick.y + 4"
                            text-anchor="end"
                            fill="#4b5563"
                            font-size="12"
                        >
                            {{ tick.label }}
                        </text>
                        <text
                            v-for="tick in chartFrame.xTicks"
                            :key="`x-${tick.label}-${tick.x}`"
                            :x="tick.x"
                            :y="chartFrame.axisBottom + 20"
                            :text-anchor="tick.anchor"
                            fill="#4b5563"
                            font-size="12"
                        >
                            {{ tick.label }}
                        </text>
                        <text :x="8" :y="14" fill="#6b7280" font-size="11">kg</text>
                        <polyline
                            v-for="line in chartFrame.lines"
                            :key="line.key"
                            fill="none"
                            :stroke="line.color"
                            stroke-width="2.5"
                            stroke-linejoin="round"
                            stroke-linecap="round"
                            :points="line.points"
                        />
                        <g v-if="chartHover">
                            <line
                                :x1="chartHover.x"
                                :x2="chartHover.x"
                                :y1="chartFrame.top"
                                :y2="chartFrame.axisBottom"
                                stroke="#9ca3af"
                                stroke-dasharray="4 4"
                            />
                            <circle
                                v-for="dot in chartHover.dots"
                                :key="dot.key"
                                :cx="chartHover.x"
                                :cy="dot.y"
                                r="4.5"
                                :fill="dot.color"
                                stroke="#ffffff"
                                stroke-width="1.5"
                            />
                        </g>
                    </svg>
                    <div
                        v-if="chartHover"
                        class="pointer-events-none absolute z-10 w-60 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs shadow-md"
                        :style="chartHover.style"
                    >
                        <p class="font-medium text-gray-900">{{ chartHover.label }}</p>
                        <ul class="mt-1.5 space-y-1">
                            <li v-for="row in chartHover.rows" :key="row.key" class="flex items-center justify-between gap-3">
                                <span class="inline-flex items-center gap-1.5 text-gray-600">
                                    <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: row.color }" />
                                    {{ row.label }}
                                </span>
                                <span class="font-medium tabular-nums text-gray-900">{{ row.value }}</span>
                            </li>
                        </ul>
                    </div>
                    </div>
                </section>

                <form v-if="canManage" class="rounded-lg bg-white p-4 shadow-sm" @submit.prevent="submit">
                    <h3 class="text-sm font-medium text-gray-800">{{ editingId ? 'Kaydı düzenle' : 'Yeni kayıt' }}</h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-5">
                        <div>
                            <InputLabel for="recorded_on" value="Tarih" />
                            <TextInput id="recorded_on" v-model="form.recorded_on" type="date" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.recorded_on" />
                        </div>
                        <div>
                            <InputLabel for="store_outgoing" value="Çağlayan giden" />
                            <TextInput id="store_outgoing" v-model="form.store_outgoing" type="text" class="mt-1 block w-full" inputmode="decimal" />
                            <InputError class="mt-2" :message="form.errors.store_outgoing" />
                        </div>
                        <div>
                            <InputLabel for="store_incoming" value="Çağlayan gelen" />
                            <TextInput id="store_incoming" v-model="form.store_incoming" type="text" class="mt-1 block w-full" inputmode="decimal" />
                            <InputError class="mt-2" :message="form.errors.store_incoming" />
                        </div>
                        <div>
                            <InputLabel for="warehouse_outgoing" value="Alkop giden" />
                            <TextInput id="warehouse_outgoing" v-model="form.warehouse_outgoing" type="text" class="mt-1 block w-full" inputmode="decimal" />
                            <InputError class="mt-2" :message="form.errors.warehouse_outgoing" />
                        </div>
                        <div>
                            <InputLabel for="warehouse_incoming" value="Alkop gelen" />
                            <TextInput id="warehouse_incoming" v-model="form.warehouse_incoming" type="text" class="mt-1 block w-full" inputmode="decimal" />
                            <InputError class="mt-2" :message="form.errors.warehouse_incoming" />
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-3">
                        <PrimaryButton :disabled="form.processing">{{ editingId ? 'Güncelle' : 'Kaydet' }}</PrimaryButton>
                        <button v-if="editingId" type="button" class="text-sm text-gray-600 underline" @click="cancelEdit">Vazgeç</button>
                    </div>
                </form>

                <section class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Tarih</th>
                                <th class="px-4 py-3">Çağlayan giden</th>
                                <th class="px-4 py-3">Çağlayan gelen</th>
                                <th class="px-4 py-3">Alkop giden</th>
                                <th class="px-4 py-3">Alkop gelen</th>
                                <th v-if="canManage" class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td class="px-4 py-8 text-gray-500" :colspan="canManage ? 6 : 5">Kayıt yok.</td>
                            </tr>
                            <tr v-for="(row, index) in rows" :key="`${row.kind}-${row.id ?? row.label}-${index}`" :class="rowClass(row.kind)">
                                <td class="px-4 py-3 font-medium">{{ row.label }}</td>
                                <td class="px-4 py-3">{{ row.store_outgoing }}</td>
                                <td class="px-4 py-3">{{ row.store_incoming }}</td>
                                <td class="px-4 py-3">{{ row.warehouse_outgoing }}</td>
                                <td class="px-4 py-3">{{ row.warehouse_incoming }}</td>
                                <td v-if="canManage" class="px-4 py-3 text-right">
                                    <template v-if="row.kind === 'day'">
                                        <button type="button" class="text-indigo-700 hover:text-indigo-900" @click="edit(row)">Düzenle</button>
                                        <button type="button" class="ml-3 text-red-700 hover:text-red-900" @click="remove(row)">Sil</button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
