<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="StockPilot" />

    <div
        class="relative min-h-screen overflow-hidden text-slate-900 antialiased"
        style="
            --sp-ink: #0b1f3a;
            --sp-blue: #1d4ed8;
            --sp-foam: #dbeafe;
            --sp-mist: #eff6ff;
        "
    >
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,_var(--sp-foam)_0%,_var(--sp-mist)_42%,_#f8fafc_78%)]"
        />
        <div
            class="pointer-events-none absolute inset-0 opacity-[0.35]"
            style="
                background-image:
                    linear-gradient(rgba(29, 78, 216, 0.07) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(29, 78, 216, 0.07) 1px, transparent 1px);
                background-size: 48px 48px;
            "
        />

        <svg
            class="pointer-events-none absolute inset-y-0 right-0 h-full w-[58%] min-w-[320px] translate-x-[8%] opacity-90"
            viewBox="0 0 640 720"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <defs>
                <linearGradient id="spShelf" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#1d4ed8" stop-opacity="0.22" />
                    <stop offset="100%" stop-color="#0b1f3a" stop-opacity="0.08" />
                </linearGradient>
            </defs>
            <g class="origin-center animate-[sp-drift_14s_ease-in-out_infinite]">
                <rect x="80" y="90" width="420" height="520" rx="28" fill="url(#spShelf)" />
                <rect x="120" y="140" width="140" height="70" rx="10" fill="#1d4ed8" fill-opacity="0.35" />
                <rect x="280" y="140" width="180" height="70" rx="10" fill="#0b1f3a" fill-opacity="0.12" />
                <rect x="120" y="240" width="220" height="70" rx="10" fill="#0b1f3a" fill-opacity="0.1" />
                <rect x="360" y="240" width="100" height="70" rx="10" fill="#2563eb" fill-opacity="0.28" />
                <rect x="120" y="340" width="100" height="70" rx="10" fill="#1d4ed8" fill-opacity="0.2" />
                <rect x="240" y="340" width="220" height="70" rx="10" fill="#0b1f3a" fill-opacity="0.14" />
                <rect x="120" y="440" width="340" height="70" rx="10" fill="#1d4ed8" fill-opacity="0.16" />
                <path
                    d="M470 200h70l40 40v180l-40 40h-70"
                    stroke="#1d4ed8"
                    stroke-opacity="0.45"
                    stroke-width="10"
                    stroke-linejoin="round"
                />
            </g>
        </svg>

        <div class="relative z-10 flex min-h-screen flex-col">
            <header class="flex items-center justify-between px-6 py-5 sm:px-10">
                <Link
                    href="/"
                    class="group flex items-center gap-2.5 text-[var(--sp-ink)]"
                >
                    <ApplicationLogo
                        class="h-9 w-9 transition group-hover:scale-105"
                    />
                    <span class="text-lg font-semibold tracking-tight"
                        >StockPilot</span
                    >
                </Link>

                <nav v-if="canLogin" class="flex items-center gap-2 sm:gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-lg bg-[var(--sp-blue)] px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-800"
                    >
                        Panele git
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-white/70 hover:text-[var(--sp-ink)]"
                        >
                            Giriş yap
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-lg bg-[var(--sp-ink)] px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                        >
                            Kayıt ol
                        </Link>
                    </template>
                </nav>
            </header>

            <main
                class="flex flex-1 flex-col justify-center px-6 pb-20 pt-8 sm:px-10 lg:max-w-[54%]"
            >
                <h1
                    class="text-5xl font-semibold tracking-tight text-[var(--sp-ink)] sm:text-6xl lg:text-7xl animate-[sp-rise_0.85s_ease-out_both]"
                >
                    StockPilot
                </h1>

                <p
                    class="mt-5 max-w-md text-lg leading-relaxed text-slate-600 sm:text-xl animate-[sp-rise_0.9s_ease-out_0.1s_both]"
                >
                    Stok, müşteri, teklif ve sevkiyatı tek panelden yönetin —
                    sade, hızlı, portföy demosu için hazır.
                </p>
            </main>
        </div>
    </div>
</template>

<style>
@keyframes sp-rise {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes sp-drift {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}
</style>
