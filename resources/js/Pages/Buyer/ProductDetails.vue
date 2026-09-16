<!-- Legacy buyer product detail view retained for compatibility. -->
<script setup>
import { Head, Link } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const product = {
    name: 'Wireless Headphones',
    category: 'Electronics',
    price: 1499,
    oldPrice: 1899,
    rating: 4.8,
    reviews: 214,
    description:
        'Experience clear and immersive sound with these comfortable wireless headphones. Perfect for music, work, gaming, and everyday use.',
    colors: ['Black', 'White', 'Blue'],
    sizes: ['Standard'],
}

const selectedColor = 'Black'
const selectedSize = 'Standard'
</script>

<template>
    <Head :title="product.name" />

    <GuestLayout>
        <div class="min-h-screen bg-gray-50">

            <!-- Breadcrumb -->
            <div class="border-b bg-white">
                <div class="mx-auto max-w-7xl px-6 py-4">
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <Link href="/" class="hover:text-indigo-600">
                            Home
                        </Link>

                        <span>/</span>

                        <span>{{ product.category }}</span>

                        <span>/</span>

                        <span class="font-medium text-gray-900">
                            {{ product.name }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Product -->
            <main class="mx-auto max-w-7xl px-6 py-12">

                <div class="grid gap-10 lg:grid-cols-2">

                    <!-- Product Image -->
                    <div>
                        <div
                            class="flex aspect-square items-center justify-center rounded-3xl bg-gradient-to-br from-indigo-50 to-indigo-100"
                        >
                            <svg
                                class="h-40 w-40 text-indigo-600"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.3"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 14v-2a8 8 0 1 1 16 0v2M4 14a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h1v-6H4Zm16 0a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v-6h1Z"
                                />
                            </svg>
                        </div>

                        <!-- Thumbnails -->
                        <div class="mt-4 grid grid-cols-4 gap-3">
                            <button
                                v-for="n in 4"
                                :key="n"
                                class="flex aspect-square items-center justify-center rounded-xl border border-indigo-200 bg-white"
                            >
                                <svg
                                    class="h-8 w-8 text-gray-400"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 14v-2a8 8 0 1 1 16 0v2"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Product Information -->
                    <div class="flex flex-col justify-center">

                        <p
                            class="text-sm font-semibold uppercase tracking-wide text-indigo-600"
                        >
                            {{ product.category }}
                        </p>

                        <h1
                            class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl"
                        >
                            {{ product.name }}
                        </h1>

                        <!-- Rating -->
                        <div class="mt-4 flex items-center gap-3">
                            <div class="flex text-amber-400">
                                <svg
                                    v-for="n in 5"
                                    :key="n"
                                    class="h-5 w-5 fill-current"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        d="M10 1.5 12.6 7l6 .9-4.3 4.2 1 6-5.3-2.8-5.3 2.8-5.3 2.8 1-6L1.4 7.9l6-.9L10 1.5Z"
                                    />
                                </svg>
                            </div>

                            <span class="font-semibold text-gray-900">
                                {{ product.rating }}
                            </span>

                            <span class="text-sm text-gray-500">
                                ({{ product.reviews }} reviews)
                            </span>
                        </div>

                        <!-- Price -->
                        <div class="mt-6 flex items-baseline gap-3">
                            <span class="text-3xl font-bold text-gray-900">
                                ₱{{ product.price.toLocaleString() }}
                            </span>

                            <span
                                class="text-lg text-gray-400 line-through"
                            >
                                ₱{{ product.oldPrice.toLocaleString() }}
                            </span>

                            <span
                                class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-600"
                            >
                                SALE
                            </span>
                        </div>

                        <div class="my-7 border-t border-gray-200"></div>

                        <!-- Description -->
                        <div>
                            <h2 class="font-semibold text-gray-900">
                                Description
                            </h2>

                            <p class="mt-2 leading-7 text-gray-500">
                                {{ product.description }}
                            </p>
                        </div>

                        <!-- Color -->
                        <div class="mt-7">
                            <div class="flex items-center justify-between">
                                <h2 class="font-semibold text-gray-900">
                                    Color
                                </h2>

                                <span class="text-sm text-gray-500">
                                    {{ selectedColor }}
                                </span>
                            </div>

                            <div class="mt-3 flex gap-2">
                                <button
                                    v-for="color in product.colors"
                                    :key="color"
                                    class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium transition hover:border-indigo-500 hover:text-indigo-600"
                                >
                                    {{ color }}
                                </button>
                            </div>
                        </div>

                        <!-- Size -->
                        <div class="mt-6">
                            <h2 class="font-semibold text-gray-900">
                                Size
                            </h2>

                            <div class="mt-3 flex gap-2">
                                <button
                                    v-for="size in product.sizes"
                                    :key="size"
                                    class="rounded-lg border border-indigo-600 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-600"
                                >
                                    {{ size }}
                                </button>
                            </div>
                        </div>

                        <!-- Quantity -->
                        <div class="mt-6">
                            <h2 class="font-semibold text-gray-900">
                                Quantity
                            </h2>

                            <div
                                class="mt-3 flex h-11 w-32 items-center overflow-hidden rounded-lg border border-gray-200 bg-white"
                            >
                                <button
                                    class="flex h-full w-10 items-center justify-center text-gray-500 hover:bg-gray-50"
                                >
                                    −
                                </button>

                                <span
                                    class="flex flex-1 justify-center text-sm font-semibold"
                                >
                                    1
                                </span>

                                <button
                                    class="flex h-full w-10 items-center justify-center text-gray-500 hover:bg-gray-50"
                                >
                                    +
                                </button>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">

                            <button
                                class="flex-1 rounded-xl border border-indigo-600 px-6 py-3.5 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50"
                            >
                                Add to Cart
                            </button>

                            <button
                                class="flex-1 rounded-xl bg-indigo-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                            >
                                Buy Now
                            </button>

                        </div>

                        <!-- Delivery info -->
                        <div class="mt-8 rounded-2xl border border-gray-100 bg-white p-5">

                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-indigo-50 text-indigo-600"
                                >
                                    🚚
                                </div>

                                <div>
                                    <p class="font-semibold text-gray-900">
                                        Fast Delivery
                                    </p>

                                    <p class="mt-1 text-sm text-gray-500">
                                        Estimated delivery within 2–4
                                        business days.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 border-t border-gray-100 pt-5">

                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-emerald-50 text-emerald-600"
                                    >
                                        ✓
                                    </div>

                                    <div>
                                        <p class="font-semibold text-gray-900">
                                            Buyer Protection
                                        </p>

                                        <p class="mt-1 text-sm text-gray-500">
                                            Secure payment and protected
                                            purchases.
                                        </p>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

                <!-- Reviews -->
                <section class="mt-16 border-t border-gray-200 pt-12">

                    <h2 class="text-2xl font-bold text-gray-900">
                        Customer Reviews
                    </h2>

                    <div class="mt-6 grid gap-6 md:grid-cols-3">

                        <div
                            v-for="review in [
                                {
                                    name: 'Maria S.',
                                    rating: 5,
                                    comment: 'Great quality and fast delivery!'
                                },
                                {
                                    name: 'John D.',
                                    rating: 5,
                                    comment: 'The sound quality is excellent.'
                                },
                                {
                                    name: 'Kevin R.',
                                    rating: 4,
                                    comment: 'Good product for the price.'
                                }
                            ]"
                            :key="review.name"
                            class="rounded-2xl border border-gray-100 bg-white p-5"
                        >
                            <div class="flex text-amber-400">
                                <span
                                    v-for="n in review.rating"
                                    :key="n"
                                >
                                    ★
                                </span>
                            </div>

                            <p class="mt-3 text-sm leading-6 text-gray-600">
                                "{{ review.comment }}"
                            </p>

                            <p class="mt-4 text-sm font-semibold text-gray-900">
                                {{ review.name }}
                            </p>
                        </div>

                    </div>
                </section>

            </main>
        </div>
    </GuestLayout>
</template>