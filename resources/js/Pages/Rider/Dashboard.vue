<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
defineProps({ rider: Object, parcels: Array })
</script>
<template>
    <Head title="Rider dashboard" /><GuestLayout><main class="mx-auto max-w-5xl px-4 py-10"><div class="flex flex-wrap items-center justify-between gap-4"><div><p class="text-sm font-semibold text-[#087F8C]">Alona delivery network</p><h1 class="mt-2 text-3xl font-semibold">Welcome, {{ rider.name }}</h1><p class="mt-2 text-slate-500">{{ rider.vehicle_type }} · {{ rider.approval_status }}</p></div><Link :href="route('application.status')" class="rounded-xl border border-[#087F8C] px-4 py-3 text-[#087F8C]">Profile and application</Link></div>
        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-lg font-semibold">Assigned deliveries</h2><p v-if="!parcels.length" class="mt-4 text-slate-500">No deliveries assigned yet. Your Logistics Partner will assign deliveries here.</p><ul v-else class="mt-4 divide-y divide-slate-100"><li v-for="parcel in parcels" :key="parcel.id" class="py-4"><div class="flex items-center justify-between"><p class="font-semibold">{{ parcel.order_id }} · {{ parcel.status }}</p><span v-if="parcel.shipping_method === 'express' || parcel.is_express" class="rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-black text-white">⚡ EXPRESS</span></div><p class="mt-1 text-sm text-slate-500">{{ parcel.delivery_address }}</p></li></ul><button type="button" @click="router.reload()" class="mt-5 text-sm font-semibold text-[#087F8C]">Refresh deliveries</button></section>
    </main></GuestLayout>
</template>
