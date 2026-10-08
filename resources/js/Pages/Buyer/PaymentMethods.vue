<!-- Buyer saved payment methods. Only the last 4 digits are ever stored. -->
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

defineProps({
    payment_methods: {
        type: Array,
        default: () => [],
    },
})

const form = useForm({
    type: 'card',
    holder_name: '',
    number: '',
    exp_month: '',
    exp_year: '',
    is_default: false,
})

const typeLabel = type =>
    ({ card: 'Card', gcash: 'GCash', maya: 'Maya' })[type] || type

const submit = () => {
    form.post(route('buyer.payment-methods.store'), {
        preserveScroll: true,
        // Never keep card data in the form after submit.
        onFinish: () => form.reset('number', 'exp_month', 'exp_year'),
        onSuccess: () => form.reset(),
    })
}

const remove = method => {
    if (!confirm('Remove this payment method?')) return

    useForm({}).delete(route('buyer.payment-methods.destroy', method.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Payment Methods" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] bg-[#F8FAF9]">
            <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-extrabold text-[#1F2937]">Payment Methods</h1>
                        <p class="mt-1 text-sm text-[#64748B]">
                            Save a payment method for faster checkout. We only keep the last 4 digits.
                        </p>
                    </div>

                    <Link :href="route('buyer.cart')" class="text-xs font-bold text-[#087F8C] hover:underline">
                        ← Back to Cart
                    </Link>
                </div>

                <div class="mt-6 grid gap-5 md:grid-cols-2">
                    <!-- LIST -->
                    <section class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-extrabold text-[#1F2937]">Saved methods</h2>

                        <p v-if="!payment_methods.length" class="mt-4 text-xs text-[#64748B]">
                            You have no saved payment methods yet.
                        </p>

                        <ul v-else class="mt-4 space-y-3">
                            <li
                                v-for="method in payment_methods"
                                :key="method.id"
                                class="flex items-center justify-between gap-3 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3"
                            >
                                <div class="min-w-0">
                                    <p class="text-xs font-extrabold text-[#1F2937]">
                                        {{ method.brand || typeLabel(method.type) }} •••• {{ method.last_four }}
                                        <span
                                            v-if="method.is_default"
                                            class="ml-1 rounded-full bg-[#EAF8F1] px-2 py-0.5 text-[9px] font-bold text-[#22A06B]"
                                        >
                                            Default
                                        </span>
                                    </p>
                                    <p class="mt-0.5 truncate text-[11px] text-[#64748B]">
                                        {{ method.holder_name }}
                                        <span v-if="method.exp_month">
                                            · Exp {{ String(method.exp_month).padStart(2, '0') }}/{{ method.exp_year }}
                                        </span>
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-[#C24141] hover:bg-[#FFF1F1]"
                                    @click="remove(method)"
                                >
                                    Remove
                                </button>
                            </li>
                        </ul>
                    </section>

                    <!-- ADD -->
                    <form
                        class="space-y-3 rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm"
                        autocomplete="off"
                        @submit.prevent="submit"
                    >
                        <h2 class="text-sm font-extrabold text-[#1F2937]">Add a payment method</h2>

                        <div>
                            <label class="text-[11px] font-bold text-[#64748B]">Type</label>
                            <select
                                v-model="form.type"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs"
                            >
                                <option value="card">Credit / Debit Card</option>
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-[11px] font-bold text-[#64748B]">
                                {{ form.type === 'card' ? 'Name on card' : 'Account name' }}
                            </label>
                            <input
                                v-model="form.holder_name"
                                type="text"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs"
                            />
                            <p v-if="form.errors.holder_name" class="mt-1 text-[11px] text-[#C24141]">{{ form.errors.holder_name }}</p>
                        </div>

                        <div>
                            <label class="text-[11px] font-bold text-[#64748B]">
                                {{ form.type === 'card' ? 'Card number' : 'Mobile number' }}
                            </label>
                            <input
                                v-model="form.number"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs"
                            />
                            <p v-if="form.errors.number" class="mt-1 text-[11px] text-[#C24141]">{{ form.errors.number }}</p>
                        </div>

                        <div v-if="form.type === 'card'" class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] font-bold text-[#64748B]">Exp. month</label>
                                <input
                                    v-model="form.exp_month"
                                    type="number"
                                    min="1"
                                    max="12"
                                    placeholder="MM"
                                    class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs"
                                />
                                <p v-if="form.errors.exp_month" class="mt-1 text-[11px] text-[#C24141]">{{ form.errors.exp_month }}</p>
                            </div>

                            <div>
                                <label class="text-[11px] font-bold text-[#64748B]">Exp. year</label>
                                <input
                                    v-model="form.exp_year"
                                    type="number"
                                    placeholder="YYYY"
                                    class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs"
                                />
                                <p v-if="form.errors.exp_year" class="mt-1 text-[11px] text-[#C24141]">{{ form.errors.exp_year }}</p>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-xs text-[#64748B]">
                            <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-[#087F8C]" />
                            Set as default
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#066C76] disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : 'Save payment method' }}
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </BuyerLayout>
</template>

