<script setup>
defineProps({
    appName: {
        type: String,
        required: true,
    },
    phase: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <main class="page">
        <section class="card">
            <h1>{{ appName }}</h1>
            <p>
                Wholesale warehouse &amp; distribution operations.
                Inertia + Vue is wired into this Laravel app.
            </p>
            <span class="tag">{{ phase }}</span>
        </section>
    </main>
</template>

<style scoped>
.page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 1.5rem;
    background:
        radial-gradient(ellipse at top, #1a2a3a 0%, #0f1419 55%),
        #0f1419;
    color: #e7ecf1;
    font-family: "Instrument Sans", "Segoe UI", system-ui, sans-serif;
}

.card {
    width: min(36rem, 100%);
    padding: 2rem 2.25rem;
    background: rgba(26, 34, 44, 0.92);
    border: 1px solid #2a3542;
    border-radius: 12px;
}

h1 {
    margin: 0 0 0.5rem;
    font-size: 1.75rem;
    letter-spacing: -0.02em;
}

p {
    margin: 0;
    color: #8b9aab;
    line-height: 1.55;
}

.tag {
    display: inline-block;
    margin-top: 1.25rem;
    padding: 0.35rem 0.7rem;
    border-radius: 999px;
    font-size: 0.75rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #3d9cf0;
    border: 1px solid rgba(61, 156, 240, 0.45);
    background: rgba(61, 156, 240, 0.12);
}
</style>
