<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    reviews: { type: Object, default: () => ({ data: [] }) },
    averageRating: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
})

const replyForms = {}

const getReplyForm = (review) => {
    if (!replyForms[review.id]) {
        replyForms[review.id] = useForm({ seller_reply: review.seller_reply || '' })
    }
    return replyForms[review.id]
}

const submitReply = (review) => {
    getReplyForm(review).post(route('seller.reviews.reply', review.id), {
        preserveScroll: true,
        onSuccess: () => {
            review.seller_reply = getReplyForm(review).seller_reply
        },
    })
}

const stars = (rating) => {
    const rounded = Math.round(Number(rating || 0))
    return '★'.repeat(rounded).padEnd(5, '☆')
}
</script>

<template>
    <Head title="Seller Reviews" />
    <SellerLayout>
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <div>
                <p class="text-sm font-medium text-indigo-600">Seller Center</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">Customer feedback</h1>
            </div>

            <div class="mt-8 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Average rating</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ averageRating.toFixed(1) }} / 5</p>
                <p class="mt-2 text-sm text-amber-500">{{ stars(averageRating) }}</p>
            </div>

            <div class="mt-8 space-y-4">
                <div v-for="review in props.reviews.data" :key="review.id" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ review.user?.name || 'Customer' }}</p>
                            <p class="text-xs text-gray-500">{{ review.product?.name || 'Product' }}</p>
                        </div>
                        <span class="text-sm font-semibold text-amber-500">{{ stars(review.rating) }}</span>
                    </div>

                    <p class="mt-4 text-sm leading-6 text-gray-700">{{ review.comment || 'No comment provided.' }}</p>

                    <form v-if="!review.seller_reply" class="mt-4" @submit.prevent="submitReply(review)">
                        <textarea v-model="getReplyForm(review).seller_reply" rows="3" placeholder="Reply to customer" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none focus:border-indigo-400 focus:bg-white focus:ring-2 focus:ring-indigo-100"></textarea>
                        <div class="mt-3 flex justify-end">
                            <button type="submit" :disabled="getReplyForm(review).processing" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">{{ getReplyForm(review).processing ? 'Sending...' : 'Reply' }}</button>
                        </div>
                    </form>

                    <div v-else class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">
                        <strong>Your reply:</strong> {{ review.seller_reply }}
                    </div>
                </div>
                <div v-if="!props.reviews.data.length" class="rounded-2xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">No customer feedback yet.</div>
            </div>
        </div>
    </SellerLayout>
</template>
