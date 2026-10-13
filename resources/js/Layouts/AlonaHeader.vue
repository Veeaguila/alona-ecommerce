<template>
    <header class="bg-[#008B8B] text-white shadow-md">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    <a href="/sorting-center/dashboard" class="shrink-0 rounded-lg bg-white p-1.5" aria-label="Alona dashboard">
                        <img :src="logoUrl" alt="Alona" class="h-10 w-10 object-contain" />
                    </a>
                    <div class="min-w-0">
                        <p v-if="screen" class="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">{{ screen }}</p>
                        <h1 class="mt-0.5 text-xl font-bold tracking-tight sm:text-2xl">{{ title }}</h1>
                        <p v-if="subtitle" class="mt-1 text-sm text-white/80">{{ subtitle }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <slot name="actions" />
                    <button
                        v-if="token"
                        type="button"
                        class="rounded-md border border-white/30 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
                        @click="handleLogout"
                    >
                        Sign out
                    </button>
                </div>
            </div>
            <nav aria-label="Sorting center navigation" class="mt-4 grid grid-cols-2 gap-1 border-t border-white/20 pt-3 text-sm sm:grid-cols-4 xl:grid-cols-8">
                <a
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    :class="title === link.title ? 'bg-white/20 text-white' : 'text-white/80 hover:bg-white/10 hover:text-white'"
                    class="rounded-md px-3 py-2 font-medium transition"
                >{{ link.label }}</a>
            </nav>
        </div>
    </header>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    screen: { type: String, default: '' },
});

const token = ref(localStorage.getItem('logistics_auth_token'));
const logoUrl = '/images/alona.png';
const links = [
    { href: '/sorting-center/dashboard', title: 'Dashboard', label: 'Dashboard' },
    { href: '/sorting-center/order-management', title: 'Order Management', label: 'Order Management' },
    { href: '/sorting-center/delivery-management', title: 'Delivery Management', label: 'Delivery Management' },
    { href: '/sorting-center/rider-assignment', title: 'Rider Assignment', label: 'Rider Assignment' },
    { href: '/sorting-center/delivery-monitoring', title: 'Delivery Monitoring', label: 'Delivery Monitoring' },
    { href: '/sorting-center/delivery-reports', title: 'Delivery Reports', label: 'Delivery Reports' },
    { href: '/sorting-center/messages', title: 'Messages', label: 'Messages' },
];

function handleLogout() {
    localStorage.removeItem('logistics_auth_token');
    localStorage.removeItem('logistics_auth_session');
    window.location.href = '/login';
}
</script>
