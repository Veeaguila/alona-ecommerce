<script setup>
import BuyerShow from '@/Pages/Buyer/Show.vue'

defineProps({
    product: {
        type: Object,
        default: null,
    },
    relatedProducts: {
        type: Array,
        default: () => [],
    },
    sellerStats: {
        type: Object,
        default: () => ({
            total_products: 0,
            avg_rating: 0,
        }),
    },
})
</script>

<template>
    <BuyerShow
        :product="product"
        :related-products="relatedProducts"
        :seller-stats="sellerStats"
        is-guest
    />
</template>
