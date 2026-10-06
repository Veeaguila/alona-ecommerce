<script setup>
import BuyerDashboard from '@/Pages/Buyer/Dashboard.vue'

defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Array,
        default: () => [],
    },
    featuredProducts: {
        type: Array,
        default: () => [],
    },
    recommendedProducts: {
        type: Array,
        default: () => [],
    },
    topSellingProducts: {
        type: Array,
        default: () => [],
    },
    promotions: {
        type: Array,
        default: () => [],
    },
    recentlyViewedProducts: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <BuyerDashboard
        :categories="categories"
        :products="products"
        :featured-products="featuredProducts"
        :recommended-products="recommendedProducts"
        :top-selling-products="topSellingProducts"
        :promotions="promotions"
        :recently-viewed-products="recentlyViewedProducts"
        is-guest
    />
</template>
