<template>
  <section v-if="review?.claim || review?.refund" class="my-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm" aria-label="Logistics claim and refund review">
    <p v-if="review.claim">Loss claim {{ review.claim.reference }}: pending review.</p>
    <p v-if="review.refund">Refund review #{{ review.refund.id }}: {{ review.refund.status }}. Requested {{ review.refund.requested_amount }} PHP from the recorded paid order amount. No refund has been issued; execution awaits a verified authorized workflow.</p>
  </section>
</template>
<script setup>
defineProps({ review: { type: Object, default: null } });
</script>
