<template>
  <section
    v-if="review?.claim || review?.refund"
    :class="[review?.refund?.status === 'refunded' ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : 'border-amber-200 bg-amber-50 text-amber-900', 'my-3 rounded-lg border p-4 text-sm']"
    aria-label="Logistics claim and refund review"
  >
    <p v-if="review.claim">Loss claim {{ review.claim.reference }}: pending review.</p>
    <div v-if="review.refund">
      <div v-if="review.refund.status === 'refunded'" class="flex items-start gap-2.5">
        <svg class="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <p class="font-semibold text-emerald-900">Refund #{{ review.refund.id }} Completed & Issued</p>
          <p class="mt-0.5 text-xs text-emerald-800">
            A refund of ₱{{ Number(review.refund.refunded_amount || review.refund.requested_amount).toFixed(2) }} was authorized and issued to the buyer via {{ review.refund.refund_method || 'original payment' }}.
            <span v-if="review.refund.refund_reference" class="font-mono ml-1 font-semibold">({{ review.refund.refund_reference }})</span>
          </p>
        </div>
      </div>
      <p v-else>Refund review #{{ review.refund.id }}: {{ review.refund.status }}. Requested {{ review.refund.requested_amount }} PHP from the recorded paid order amount. No refund has been issued; execution awaits a verified authorized workflow.</p>
    </div>
  </section>
</template>
<script setup>
defineProps({ review: { type: Object, default: null } });
</script>
