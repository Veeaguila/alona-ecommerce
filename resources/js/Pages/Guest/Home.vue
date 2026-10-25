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
    flashSales: {
        type: Array,
        default: () => [],
    },
    flashSaleEndTime: {
        type: String,
        default: null,
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
    marketplaceStats: {
        type: Object,
        default: () => ({
            products: 0,
            sellers: 0,
            categories: 0,
            orders_delivered: 0,
        }),
    },
    whyAlona: {
        type: Object,
        default: null,
    },
})
</script>

<template>
    <BuyerDashboard
        :categories="categories"
        :products="products"
        :flash-sales="flashSales"
        :flash-sale-end-time="flashSaleEndTime"
        :featured-products="featuredProducts"
        :recommended-products="recommendedProducts"
        :top-selling-products="topSellingProducts"
        :promotions="promotions"
        :recently-viewed-products="recentlyViewedProducts"
        :marketplace-stats="marketplaceStats"
        :why-alona="whyAlona"
        is-guest
    />
</template>
