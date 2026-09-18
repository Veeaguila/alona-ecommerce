<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },

    products: {
        type: Array,
        default: () => [],
    },

    recentlyViewedProducts: {
        type: Array,
        default: () => [],
    },
})

/*
|--------------------------------------------------------------------------
| Safe Routes
|--------------------------------------------------------------------------
*/

const safeRoute = (name, fallback = '#', params = undefined) => {
    try {
        return params === undefined
            ? route(name)
            : route(name, params)
    } catch (error) {
        return fallback
    }
}

/*
|--------------------------------------------------------------------------
| Image Helpers
|--------------------------------------------------------------------------
*/

const imageUrl = path => {
    if (!path) {
        return null
    }

    const value = String(path).trim()

    if (!value) {
        return null
    }

    if (
        value.startsWith('http://') ||
        value.startsWith('https://') ||
        value.startsWith('/storage/') ||
        value.startsWith('/images/') ||
        value.startsWith('data:')
    ) {
        return value
    }

    return `/storage/${value.replace(/^\/+/, '')}`
}

const slugify = value => {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/&/g, 'and')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
}

/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

const products = computed(() => {
    if (!Array.isArray(props.products)) {
        return []
    }

    return props.products.map(product => {
        const numericPrice = Number(product.price ?? 0)
        const numericOldPrice = Number(product.old_price ?? 0)

        const soldCount = Number(
            product.sold_count ??
            product.sales_count ??
            product.order_items_count ??
            product.total_sold ??
            0
        )

        return {
            ...product,

            name:
                product.name ??
                product.title ??
                'Product',

            category:
                product.category?.name ??
                product.category_name ??
                'Product',

            price: `₱${numericPrice.toLocaleString()}`,

            oldPrice:
                numericOldPrice > 0
                    ? `₱${numericOldPrice.toLocaleString()}`
                    : null,

            numericPrice,

            numericOldPrice,

            reviews: Number(
                product.reviews_count ??
                product.review_count ??
                product.reviews ??
                0
            ),

            rating: Number(
                product.rating ??
                product.average_rating ??
                product.reviews_avg_rating ??
                0
            ),

            image: imageUrl(
                product.image_path ??
                product.image ??
                product.image_url ??
                product.thumbnail ??
                product.photo
            ),

            description:
                product.description ?? '',

            soldCount,

            isSale:
                numericOldPrice > 0 &&
                numericOldPrice > numericPrice,
        }
    })
})

/*
|--------------------------------------------------------------------------
| Recently Viewed Products
|--------------------------------------------------------------------------
*/

const recentlyViewed = computed(() => {
    if (!Array.isArray(props.recentlyViewedProducts)) {
        return []
    }

    return props.recentlyViewedProducts
        .filter(product => product && product.id)
        .map(product => {
            const numericPrice = Number(product.price ?? 0)
            const numericOldPrice = Number(
                product.old_price ?? 0
            )

            return {
                ...product,

                name:
                    product.name ??
                    product.title ??
                    'Product',

                category:
                    product.category?.name ??
                    product.category_name ??
                    'Product',

                price:
                    `₱${numericPrice.toLocaleString()}`,

                oldPrice:
                    numericOldPrice > 0
                        ? `₱${numericOldPrice.toLocaleString()}`
                        : null,

                numericPrice,

                numericOldPrice,

                rating: Number(
                    product.rating ??
                    product.average_rating ??
                    product.reviews_avg_rating ??
                    0
                ),

                reviews: Number(
                    product.reviews_count ??
                    product.review_count ??
                    product.reviews ??
                    0
                ),

                image: imageUrl(
                    product.image_path ??
                    product.image ??
                    product.image_url ??
                    product.thumbnail ??
                    product.photo
                ),

                isSale:
                    numericOldPrice > 0 &&
                    numericOldPrice > numericPrice,
            }
        })
})

/*
|--------------------------------------------------------------------------
| Top Selling
|--------------------------------------------------------------------------
*/

const sortedBySales = computed(() => {
    return [...products.value].sort(
        (a, b) => b.soldCount - a.soldCount
    )
})

const featuredProduct = computed(() => {
    return sortedBySales.value[0] ?? null
})

/*
|--------------------------------------------------------------------------
| Trending Products
|--------------------------------------------------------------------------
*/

const trendingProducts = computed(() => {
    const featuredId = featuredProduct.value?.id

    return products.value
        .filter(product => product.id !== featuredId)
        .slice(0, 6)
})

/*
|--------------------------------------------------------------------------
| CATEGORY PHOTOS
|--------------------------------------------------------------------------
*/

const categoryPictures = {
    'mens-apparel':
        'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=900&q=85',

    'mobiles-gadgets':
        'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=85',

    'mobiles-accessories':
        'https://images.unsplash.com/photo-1609592424831-1e1f6c9c0f25?auto=format&fit=crop&w=900&q=85',

    'home-entertainment':
        'https://images.unsplash.com/photo-1593784991095-a205069470b6?auto=format&fit=crop&w=900&q=85',

    'babies-kids':
        'https://images.unsplash.com/photo-1516627145497-ae6968895b74?auto=format&fit=crop&w=900&q=85',

    'home-living':
        'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=85',

    groceries:
        'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=85',

    'toys-games-collectibles':
        'https://images.unsplash.com/photo-1594787318286-4d84bb1f0c2e?auto=format&fit=crop&w=900&q=85',

    'womens-bags':
        'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=900&q=85',

    'women-accessories':
        'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=900&q=85',

    'womens-apparel':
        'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=900&q=85',

    'health-personal-care':
        'https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?auto=format&fit=crop&w=900&q=85',

    'makeup-fragrances':
        'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=85',

    'home-appliances':
        'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&q=85',

    'laptops-computers':
        'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=85',

    cameras:
        'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=85',

    'sports-travel':
        'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=900&q=85',

    'mens-bags-accessories':
        'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=900&q=85',

    'mens-shoes':
        'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=85',

    motors:
        'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=900&q=85',

    'womens-shoes':
        'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=900&q=85',

    'pet-care':
        'https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&w=900&q=85',

    audio:
        'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85',

    'hobbies-stationery':
        'https://images.unsplash.com/photo-1499951360447-b19be8fe80f5?auto=format&fit=crop&w=900&q=85',

    gaming:
        'https://images.unsplash.com/photo-1605901309584-818e25960a8f?auto=format&fit=crop&w=900&q=85',
}

/*
|--------------------------------------------------------------------------
| EXACT CATEGORIES FROM YOUR SCREENSHOTS
|--------------------------------------------------------------------------
*/

const fixedCategories = [
    {
        name: "Men's Apparel",
        slug: 'mens-apparel',
    },

    {
        name: 'Mobiles & Gadgets',
        slug: 'mobiles-gadgets',
    },

    {
        name: 'Mobiles Accessories',
        slug: 'mobiles-accessories',
    },

    {
        name: 'Home Entertainment',
        slug: 'home-entertainment',
    },

    {
        name: "Babies & Kids",
        slug: 'babies-kids',
    },

    {
        name: 'Home & Living',
        slug: 'home-living',
    },

    {
        name: 'Groceries',
        slug: 'groceries',
    },

    {
        name: 'Toys, Games & Collectibles',
        slug: 'toys-games-collectibles',
    },

    {
        name: "Women's Bags",
        slug: 'womens-bags',
    },

    {
        name: 'Women Accessories',
        slug: 'women-accessories',
    },

    {
        name: "Women's Apparel",
        slug: 'womens-apparel',
    },

    {
        name: 'Health & Personal Care',
        slug: 'health-personal-care',
    },

    {
        name: 'Makeup & Fragrances',
        slug: 'makeup-fragrances',
    },

    {
        name: 'Home Appliances',
        slug: 'home-appliances',
    },

    {
        name: 'Laptops & Computers',
        slug: 'laptops-computers',
    },

    {
        name: 'Cameras',
        slug: 'cameras',
    },

    {
        name: 'Sports & Travel',
        slug: 'sports-travel',
    },

    {
        name: "Men's Bags & Accessories",
        slug: 'mens-bags-accessories',
    },

    {
        name: "Men's Shoes",
        slug: 'mens-shoes',
    },

    {
        name: 'Motors',
        slug: 'motors',
    },

    {
        name: "Women's Shoes",
        slug: 'womens-shoes',
    },

    {
        name: 'Pet Care',
        slug: 'pet-care',
    },

    {
        name: 'Audio',
        slug: 'audio',
    },

    {
        name: 'Hobbies & Stationery',
        slug: 'hobbies-stationery',
    },

    {
        name: 'Gaming',
        slug: 'gaming',
    },
]

/*
|--------------------------------------------------------------------------
| Category Colors
|--------------------------------------------------------------------------
*/

const categoryColors = {
    'mens-apparel': 'bg-blue-50',
    'mobiles-gadgets': 'bg-slate-50',
    'mobiles-accessories': 'bg-gray-50',
    'home-entertainment': 'bg-cyan-50',
    'babies-kids': 'bg-rose-50',
    'home-living': 'bg-amber-50',
    groceries: 'bg-lime-50',
    'toys-games-collectibles': 'bg-purple-50',
    'womens-bags': 'bg-emerald-50',
    'women-accessories': 'bg-red-50',
    'womens-apparel': 'bg-pink-50',
    'health-personal-care': 'bg-cyan-50',
    'makeup-fragrances': 'bg-pink-50',
    'home-appliances': 'bg-teal-50',
    'laptops-computers': 'bg-sky-50',
    cameras: 'bg-red-50',
    'sports-travel': 'bg-orange-50',
    'mens-bags-accessories': 'bg-blue-50',
    'mens-shoes': 'bg-sky-50',
    motors: 'bg-slate-100',
    'womens-shoes': 'bg-rose-50',
    'pet-care': 'bg-yellow-50',
    audio: 'bg-violet-50',
    'hobbies-stationery': 'bg-indigo-50',
    gaming: 'bg-violet-50',
}

/*
|--------------------------------------------------------------------------
| Category Icons
|--------------------------------------------------------------------------
*/

const categoryIcons = {
    'mens-apparel': '👕',
    'mobiles-gadgets': '📱',
    'mobiles-accessories': '🔌',
    'home-entertainment': '📺',
    'babies-kids': '🍼',
    'home-living': '🛋️',
    groceries: '🥬',
    'toys-games-collectibles': '🧸',
    'womens-bags': '👜',
    'women-accessories': '🕶️',
    'womens-apparel': '👗',
    'health-personal-care': '🧴',
    'makeup-fragrances': '💄',
    'home-appliances': '🧊',
    'laptops-computers': '💻',
    cameras: '📷',
    'sports-travel': '🏃',
    'mens-bags-accessories': '🎒',
    'mens-shoes': '👟',
    motors: '🏍️',
    'womens-shoes': '👠',
    'pet-care': '🐶',
    audio: '🎧',
    'hobbies-stationery': '✏️',
    gaming: '🎮',
}

/*
|--------------------------------------------------------------------------
| Backend Category Lookup
|--------------------------------------------------------------------------
*/

const backendCategoryMap = computed(() => {
    const map = {}

    if (!Array.isArray(props.categories)) {
        return map
    }

    props.categories.forEach(category => {
        const name =
            category?.name ??
            category?.title ??
            ''

        const slug =
            category?.slug ??
            slugify(name)

        if (!slug) {
            return
        }

        map[slug] = category
    })

    return map
})

/*
|--------------------------------------------------------------------------
| Category Product Counts
|--------------------------------------------------------------------------
*/

const categoryProductCounts = computed(() => {
    const counts = {}

    products.value.forEach(product => {
        const slug = slugify(product.category)

        if (!slug) {
            return
        }

        counts[slug] =
            (counts[slug] ?? 0) + 1
    })

    return counts
})

/*
|--------------------------------------------------------------------------
| Category Image Error Handling
|--------------------------------------------------------------------------
*/

const categoryImageError = ref({})

const categoryErrorKey = category => {
    return (
        category?.slug ||
        category?.id ||
        slugify(category?.name) ||
        'category'
    )
}

const handleCategoryImageError = category => {
    const key = categoryErrorKey(category)

    categoryImageError.value = {
        ...categoryImageError.value,
        [key]: true,
    }
}

/*
|--------------------------------------------------------------------------
| Category Image Resolver
|--------------------------------------------------------------------------
*/

const categoryImage = category => {
    const slug = category?.slug

    const backendCategory =
        backendCategoryMap.value[slug]

    const databaseImage =
        backendCategory?.image_url ??
        backendCategory?.image_path ??
        backendCategory?.image ??
        backendCategory?.thumbnail ??
        backendCategory?.photo ??
        null

    if (databaseImage) {
        return imageUrl(databaseImage)
    }

    return categoryPictures[slug] ?? null
}

/*
|--------------------------------------------------------------------------
| Category List
|--------------------------------------------------------------------------
*/

const categoriesList = computed(() => {
    return fixedCategories.map((category, index) => {
        const backendCategory =
            backendCategoryMap.value[category.slug]

        const backendCount =
            backendCategory?.products_count ??
            backendCategory?.product_count ??
            backendCategory?.productsCount

        const productsCount =
            backendCount !== undefined &&
            backendCount !== null
                ? Number(backendCount)
                : Number(
                    categoryProductCounts.value[
                        category.slug
                    ] ?? 0
                )

        return {
            ...category,

            productsCount,

            image:
                categoryImage(category),

            color:
                categoryColors[category.slug] ??
                'bg-teal-50',

            icon:
                categoryIcons[category.slug] ??
                '🛍️',

            key:
                category.slug ??
                `category-${index}`,
        }
    })
})

/*
|--------------------------------------------------------------------------
| Category Slider
|--------------------------------------------------------------------------
*/

const categorySlider = ref(null)

const scrollCategories = direction => {
    const container =
        categorySlider.value

    if (!container) {
        return
    }

    const firstCard =
        container.querySelector('.category-card')

    const cardWidth =
        firstCard?.getBoundingClientRect().width ?? 220

    const gap = 12

    const amount =
        Math.max(
            (cardWidth + gap) * 4,
            container.clientWidth * 0.72
        )

    container.scrollBy({
        left: direction * amount,
        behavior: 'smooth',
    })
}

/*
|--------------------------------------------------------------------------
| Category Image State
|--------------------------------------------------------------------------
*/

const hasCategoryImage = category => {
    const key =
        categoryErrorKey(category)

    return Boolean(
        category?.image &&
        !categoryImageError.value[key]
    )
}

/*
|--------------------------------------------------------------------------
| Category Navigation
|--------------------------------------------------------------------------
*/

const goToCategory = category => {
    const destination =
        safeRoute('buyer.products')

    if (destination === '#') {
        return
    }

    router.get(destination, {
        category:
            category?.slug ??
            category?.name,
    })
}

/*
|--------------------------------------------------------------------------
| Benefits
|--------------------------------------------------------------------------
*/

const benefits = [
    {
        number: '01',

        title:
            'Curated for you',

        text:
            'Discover products selected from different categories in one simple marketplace.',
    },

    {
        number: '02',

        title:
            'Trusted sellers',

        text:
            'Shop from sellers who are part of the Alona marketplace community.',
    },

    {
        number: '03',

        title:
            'Simple shopping',

        text:
            'Find products, compare choices, and shop without unnecessary steps.',
    },
]

/*
|--------------------------------------------------------------------------
| Scroll Animation
|--------------------------------------------------------------------------
*/

let observer = null

onMounted(() => {
    if (
        typeof window === 'undefined' ||
        typeof IntersectionObserver ===
            'undefined'
    ) {
        document
            .querySelectorAll('.alona-reveal')
            .forEach(element => {
                element.classList.add(
                    'is-visible'
                )
            })

        return
    }

    observer =
        new IntersectionObserver(
            entries => {
                entries.forEach(
                    entry => {
                        if (
                            !entry.isIntersecting
                        ) {
                            return
                        }

                        entry.target.classList.add(
                            'is-visible'
                        )

                        observer?.unobserve(
                            entry.target
                        )
                    }
                )
            },
            {
                threshold: 0.08,
            }
        )

    document
        .querySelectorAll('.alona-reveal')
        .forEach(element => {
            observer.observe(element)
        })
})

onUnmounted(() => {
    observer?.disconnect()

    observer = null
})
</script>

<template>
    <Head title="Alona" />

    <BuyerLayout>

        <div
            class="alona-page min-h-screen overflow-hidden bg-[#F8FAF9] text-[#1F2937]"
        >

            <!-- =====================================================
                 HERO
            ====================================================== -->

            <section class="relative">

                <div
                    class="pointer-events-none absolute -left-32 top-20 h-72 w-72 rounded-full bg-[#16A6A0]/10 blur-3xl"
                ></div>

                <div
                    class="pointer-events-none absolute right-0 top-0 h-96 w-96 rounded-full bg-[#F4B942]/10 blur-3xl"
                ></div>

                <div
                    class="mx-auto max-w-[1440px] px-4 pb-7 pt-5 sm:px-6 lg:px-8 lg:pb-10"
                >

                    <div
                        class="alona-reveal hero-card relative overflow-hidden rounded-[30px] bg-[#087F8C]"
                    >

                        <div
                            class="absolute -right-24 -top-32 h-96 w-96 rounded-full border-[70px] border-white/[0.05]"
                        ></div>

                        <div
                            class="absolute -bottom-32 left-[40%] h-72 w-72 rounded-full bg-[#F4B942]/10 blur-2xl"
                        ></div>

                        <div
                            class="relative grid min-h-[390px] grid-cols-1 lg:grid-cols-[1.05fr_.95fr]"
                        >

                            <div
                                class="relative z-10 flex flex-col justify-center px-6 py-12 sm:px-10 lg:px-14 xl:px-20"
                            >

                                <div
                                    class="hero-item mb-5 flex items-center gap-3"
                                >

                                    <span
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-[#F4B942] text-[#087F8C]"
                                    >
                                        ✦
                                    </span>

                                    <span
                                        class="text-xs font-bold uppercase tracking-[0.2em] text-teal-50"
                                    >
                                        Discover • Shop • Enjoy
                                    </span>

                                </div>

                                <h1
                                    class="hero-item max-w-2xl text-4xl font-black leading-[1.02] tracking-[-0.04em] text-white sm:text-5xl lg:text-6xl"
                                >
                                    Welcome to
                                    <span
                                        class="text-[#F4B942]"
                                    >
                                        Alona.
                                    </span>
                                </h1>

                                <p
                                    class="hero-item mt-5 max-w-xl text-sm leading-6 text-teal-50 sm:text-base sm:leading-7"
                                >
                                    Everything you love,
                                    all in one place.
                                    Discover quality
                                    products, trusted
                                    sellers, and great
                                    deals made for you.
                                </p>

                                <div
                                    class="hero-item mt-7 flex flex-wrap gap-3"
                                >

                                    <Link
                                        :href="safeRoute('buyer.products')"
                                        class="group inline-flex items-center gap-3 rounded-2xl bg-white px-5 py-3.5 text-sm font-extrabold text-[#087F8C] shadow-xl shadow-black/10 transition duration-300 hover:-translate-y-1 hover:bg-[#F4FBFA]"
                                    >
                                        Shop Now

                                        <span
                                            class="flex h-6 w-6 items-center justify-center rounded-full bg-[#E8F7F6] transition group-hover:bg-[#087F8C] group-hover:text-white"
                                        >
                                            →
                                        </span>
                                    </Link>

                                    <a
                                        href="#categories"
                                        class="inline-flex items-center gap-3 rounded-2xl border border-white/50 px-5 py-3.5 text-sm font-extrabold text-white transition duration-300 hover:-translate-y-1 hover:bg-white hover:text-[#087F8C]"
                                    >
                                        Explore Categories
                                    </a>

                                </div>

                            </div>

                            <div
                                class="hero-visual relative flex min-h-[260px] items-center justify-center lg:min-h-0"
                            >

                                <div
                                    class="absolute h-[250px] w-[250px] rounded-full bg-white/[0.08] sm:h-[360px] sm:w-[360px] lg:h-[420px] lg:w-[420px]"
                                ></div>

                                <div
                                    class="absolute h-[210px] w-[210px] rounded-full border border-white/10 sm:h-[300px] sm:w-[300px]"
                                ></div>

                                <img
                                    src="/images/Alogo.png"
                                    alt="Alona"
                                    class="alona-floating relative z-10 w-[230px] max-w-[75%] object-contain drop-shadow-[0_25px_35px_rgba(0,0,0,0.18)] sm:w-[310px] lg:w-[390px]"
                                />

                                <div
                                    class="absolute right-[7%] top-[12%] z-20 hidden rounded-2xl border border-white/20 bg-white/95 p-3 shadow-xl backdrop-blur sm:block"
                                >

                                    <div
                                        class="flex items-center gap-3"
                                    >

                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                        >
                                            🛍️
                                        </div>

                                        <div>

                                            <p
                                                class="text-[9px] font-bold uppercase tracking-wider text-gray-400"
                                            >
                                                Shopping
                                            </p>

                                            <p
                                                class="text-xs font-extrabold text-gray-900"
                                            >
                                                Made simple
                                            </p>

                                        </div>

                                    </div>

                                </div>

                                <div
                                    class="absolute bottom-[12%] left-[7%] z-20 hidden rounded-2xl border border-white/20 bg-white/95 px-4 py-3 shadow-xl backdrop-blur sm:block"
                                >

                                    <div
                                        class="flex items-center gap-3"
                                    >

                                        <div
                                            class="flex h-9 w-9 items-center justify-center rounded-full bg-[#F4B942] text-white"
                                        >
                                            ★
                                        </div>

                                        <div>

                                            <p
                                                class="text-[9px] font-bold text-gray-400"
                                            >
                                                DISCOVER
                                            </p>

                                            <p
                                                class="text-xs font-extrabold text-gray-900"
                                            >
                                                Something new
                                            </p>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </section>


            <!-- =====================================================
                 SHOP BY CATEGORY
            ====================================================== -->

            <section
                id="categories"
                class="alona-reveal mx-auto max-w-[1440px] scroll-mt-20 px-4 pb-9 sm:px-6 lg:px-8 lg:pb-12"
            >

                <div
                    class="mb-5 flex items-end justify-between gap-4"
                >

                    <div>

                        <div
                            class="flex items-center gap-2"
                        >

                            <span
                                class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"
                            ></span>

                            <p
                                class="text-[10px] font-black uppercase tracking-[0.22em] text-[#087F8C]"
                            >
                                Explore Alona
                            </p>

                        </div>

                        <h2
                            class="mt-1.5 text-2xl font-black tracking-[-0.03em] text-[#1F2937] sm:text-3xl"
                        >
                            Shop by Category
                        </h2>

                        <p
                            class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm"
                        >
                            Browse popular categories and
                            find something you'll love.
                        </p>

                    </div>

                    <div
                        class="hidden items-center gap-2 sm:flex"
                    >

                        <button
                            type="button"
                            aria-label="Previous categories"
                            class="category-nav-button"
                            @click="scrollCategories(-1)"
                        >

                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    d="m15 18-6-6 6-6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>

                        </button>

                        <button
                            type="button"
                            aria-label="Next categories"
                            class="category-nav-button"
                            @click="scrollCategories(1)"
                        >

                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >

                                <path
                                    d="m9 18 6-6-6-6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />

                            </svg>

                        </button>

                    </div>

                </div>


                <div class="relative">

                    <div
                        class="pointer-events-none absolute left-0 top-0 z-20 hidden h-full w-10 bg-gradient-to-r from-[#F8FAF9] to-transparent sm:block"
                    ></div>

                    <div
                        ref="categorySlider"
                        class="category-slider flex gap-3 overflow-x-auto scroll-smooth pb-4 snap-x snap-mandatory"
                    >

                        <button
                            v-for="(category, index) in categoriesList"
                            :key="category.key"
                            type="button"
                            class="category-card group relative isolate flex-none snap-start overflow-hidden rounded-[20px] bg-white text-left shadow-sm ring-1 ring-black/[0.04] transition duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-[#087F8C]/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#087F8C]/40"
                            :style="{
                                animationDelay: `${index * 55}ms`,
                            }"
                            @click="goToCategory(category)"
                        >

                            <div
                                class="relative aspect-[1.05] overflow-hidden bg-[#E8F7F6]"
                            >

                                <img
                                    v-if="hasCategoryImage(category)"
                                    :src="category.image"
                                    :alt="category.name"
                                    class="category-image h-full w-full object-cover transition duration-700 ease-out group-hover:scale-110"
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                    @error="handleCategoryImageError(category)"
                                />

                                <div
                                    v-else
                                    :class="category.color"
                                    class="flex h-full w-full items-center justify-center transition duration-500 group-hover:scale-105"
                                >

                                    <div
                                        class="flex h-16 w-16 items-center justify-center rounded-full bg-white/80 text-3xl shadow-sm backdrop-blur"
                                    >
                                        {{ category.icon }}
                                    </div>

                                </div>

                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent"
                                ></div>

                                <div
                                    class="absolute inset-0 bg-[#087F8C]/0 transition duration-500 group-hover:bg-[#087F8C]/20"
                                ></div>

                                <div
                                    class="absolute right-3 top-3 flex h-8 w-8 translate-y-1 items-center justify-center rounded-full border border-white/30 bg-white/95 text-[#087F8C] opacity-0 shadow-lg backdrop-blur-sm transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100"
                                >

                                    <svg
                                        class="h-3.5 w-3.5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >

                                        <path
                                            d="M5 12h13"
                                            stroke-linecap="round"
                                        />

                                        <path
                                            d="m13 6 6 6-6 6"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />

                                    </svg>

                                </div>

                                <div
                                    class="absolute inset-x-0 bottom-0 z-10 p-4"
                                >

                                    <h3
                                        class="line-clamp-2 text-[13px] font-black leading-4 text-white drop-shadow-md sm:text-sm"
                                    >
                                        {{ category.name }}
                                    </h3>

                                    <div
                                        class="mt-1.5 flex items-center gap-1.5"
                                    >

                                        <span
                                            class="text-[10px] font-semibold text-white/80"
                                        >
                                            {{
                                                category.productsCount.toLocaleString()
                                            }}

                                            {{
                                                category.productsCount === 1
                                                    ? 'product'
                                                    : 'products'
                                            }}
                                        </span>

                                        <span
                                            class="h-1 w-1 rounded-full bg-white/50"
                                        ></span>

                                        <span
                                            class="text-[10px] font-semibold text-white/70"
                                        >
                                            Explore
                                        </span>

                                    </div>

                                </div>

                            </div>

                        </button>

                    </div>

                    <div
                        class="pointer-events-none absolute right-0 top-0 z-20 hidden h-full w-10 bg-gradient-to-l from-[#F8FAF9] to-transparent sm:block"
                    ></div>

                </div>

                <div
                    v-if="categoriesList.length > 6"
                    class="mt-1 flex items-center justify-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-[#64748B]"
                >

                    <span
                        class="h-1 w-1 rounded-full bg-[#F4B942]"
                    ></span>

                    <span>
                        Swipe or use the arrows to explore more categories
                    </span>

                    <span
                        class="h-1 w-1 rounded-full bg-[#F4B942]"
                    ></span>

                </div>

                <div class="mt-4 sm:hidden">

                    <Link
                        :href="safeRoute('buyer.products')"
                        class="group flex w-full items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 text-xs font-extrabold text-[#087F8C] transition hover:border-[#087F8C]/20 hover:bg-[#E8F7F6]"
                    >
                        View all categories

                        <span
                            class="transition-transform duration-300 group-hover:translate-x-1"
                        >
                            →
                        </span>

                    </Link>

                </div>

            </section>


            <!-- =====================================================
                 TOP SELLING
            ====================================================== -->

            <section
                v-if="featuredProduct"
                class="alona-reveal mx-auto max-w-[1440px] px-4 py-7 sm:px-6 lg:px-8 lg:py-9"
            >

                <div
                    class="mb-4 flex items-end justify-between gap-4"
                >

                    <div>

                        <p
                            class="text-[10px] font-black uppercase tracking-[0.22em] text-[#087F8C]"
                        >
                            Top Selling
                        </p>

                        <h2
                            class="mt-1 text-xl font-black tracking-tight text-gray-900 sm:text-2xl"
                        >
                            Customer favorites
                        </h2>

                    </div>

                    <Link
                        :href="safeRoute('buyer.products')"
                        class="hidden items-center gap-2 text-xs font-bold text-[#087F8C] sm:flex"
                    >
                        See all products →
                    </Link>

                </div>

                <div
                    class="grid overflow-hidden rounded-[24px] border border-gray-100 bg-white shadow-sm lg:grid-cols-[.85fr_1.15fr]"
                >

                    <Link
                        :href="safeRoute('buyer.product', '#', featuredProduct.id)"
                        class="group relative h-[230px] overflow-hidden bg-[#F3F7F6] sm:h-[280px] lg:h-[320px]"
                    >

                        <img
                            v-if="featuredProduct.image"
                            :src="featuredProduct.image"
                            :alt="featuredProduct.name"
                            class="h-full w-full object-cover transition duration-700 group-hover:scale-105"
                            loading="lazy"
                        />

                        <div
                            v-else
                            class="flex h-full items-center justify-center text-5xl"
                        >
                            🛍️
                        </div>

                        <div
                            class="absolute left-4 top-4 rounded-full bg-[#F4B942] px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#087F8C]"
                        >
                            #1 Top Selling
                        </div>

                        <div
                            v-if="featuredProduct.isSale"
                            class="absolute bottom-4 left-4 rounded-full bg-[#E85D5D] px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-white"
                        >
                            Sale
                        </div>

                    </Link>

                    <div
                        class="flex flex-col justify-center p-5 sm:p-7 lg:p-8"
                    >

                        <span
                            class="w-fit rounded-full bg-[#E8F7F6] px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#087F8C]"
                        >
                            {{ featuredProduct.category }}
                        </span>

                        <h3
                            class="mt-3 text-xl font-black leading-tight tracking-[-0.03em] text-gray-900 sm:text-2xl"
                        >
                            {{ featuredProduct.name }}
                        </h3>

                        <p
                            v-if="featuredProduct.description"
                            class="mt-3 line-clamp-3 max-w-xl text-xs leading-5 text-gray-500 sm:text-sm"
                        >
                            {{ featuredProduct.description }}
                        </p>

                        <p
                            v-else
                            class="mt-3 text-xs leading-5 text-gray-400"
                        >
                            Discover this popular product
                            from one of our Alona sellers.
                        </p>

                        <div
                            class="mt-4 flex items-end gap-3"
                        >

                            <span
                                class="text-xl font-black text-[#087F8C]"
                            >
                                {{ featuredProduct.price }}
                            </span>

                            <span
                                v-if="featuredProduct.oldPrice"
                                class="pb-0.5 text-xs text-gray-400 line-through"
                            >
                                {{ featuredProduct.oldPrice }}
                            </span>

                        </div>

                        <div
                            class="mt-2 flex flex-wrap items-center gap-2 text-[10px] text-gray-400"
                        >

                            <span class="text-[#F4B942]">
                                ★
                            </span>

                            <strong
                                class="text-gray-600"
                            >
                                {{
                                    featuredProduct.rating.toFixed(
                                        1
                                    )
                                }}
                            </strong>

                            <span>
                                ({{ featuredProduct.reviews }}
                                reviews)
                            </span>

                            <span
                                class="text-gray-300"
                            >
                                •
                            </span>

                            <span>
                                {{ featuredProduct.soldCount }}
                                sold
                            </span>

                        </div>

                        <Link
                            :href="safeRoute('buyer.product', '#', featuredProduct.id)"
                            class="mt-5 inline-flex w-fit items-center gap-3 rounded-xl bg-[#087F8C] px-4 py-3 text-xs font-extrabold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[#066974]"
                        >
                            View product
                            →
                        </Link>

                    </div>

                </div>

            </section>


            <!-- =====================================================
                 TRENDING PRODUCTS
            ====================================================== -->

            <section
                class="alona-reveal border-y border-gray-100 bg-white"
            >

                <div
                    class="mx-auto max-w-[1440px] px-4 py-10 sm:px-6 lg:px-8 lg:py-12"
                >

                    <div
                        class="flex items-end justify-between gap-4"
                    >

                        <div>

                            <div
                                class="flex items-center gap-2"
                            >

                                <span
                                    class="h-2 w-2 rounded-full bg-[#F4B942]"
                                ></span>

                                <p
                                    class="text-[10px] font-black uppercase tracking-[0.22em] text-[#087F8C]"
                                >
                                    Trending now
                                </p>

                            </div>

                            <h2
                                class="mt-2 text-2xl font-black tracking-tight text-gray-900 sm:text-3xl"
                            >
                                People are looking
                            </h2>

                        </div>

                        <Link
                            :href="safeRoute('buyer.products')"
                            class="hidden items-center gap-2 text-xs font-bold text-[#087F8C] sm:flex"
                        >
                            See everything →
                        </Link>

                    </div>

                    <div
                        v-if="trendingProducts.length"
                        class="mt-7 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
                    >

                        <Link
                            v-for="(product, index) in trendingProducts"
                            :key="product.id ?? `trending-${index}`"
                            :href="safeRoute('buyer.product', '#', product.id)"
                            class="product-card group"
                        >

                            <div
                                class="relative aspect-[.92] overflow-hidden rounded-[22px] bg-[#F4F7F6]"
                            >

                                <img
                                    v-if="product.image"
                                    :src="product.image"
                                    :alt="product.name"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                    loading="lazy"
                                />

                                <div
                                    v-else
                                    class="flex h-full items-center justify-center text-4xl"
                                >
                                    🛍️
                                </div>

                                <span
                                    class="absolute left-3 top-3 flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-[10px] font-black text-[#087F8C] shadow-sm backdrop-blur"
                                >
                                    {{
                                        String(
                                            index + 1
                                        ).padStart(2, '0')
                                    }}
                                </span>

                                <span
                                    v-if="product.isSale"
                                    class="absolute bottom-3 left-3 rounded-full bg-[#E85D5D] px-2.5 py-1 text-[9px] font-black text-white"
                                >
                                    SALE
                                </span>

                            </div>

                            <div
                                class="px-1 pt-4"
                            >

                                <p
                                    class="text-[10px] font-bold uppercase tracking-wider text-[#087F8C]"
                                >
                                    {{ product.category }}
                                </p>

                                <h3
                                    class="mt-1 line-clamp-2 text-sm font-extrabold leading-5 text-gray-900 transition group-hover:text-[#087F8C]"
                                >
                                    {{ product.name }}
                                </h3>

                                <div
                                    class="mt-2 flex items-center gap-2"
                                >

                                    <span
                                        class="text-sm font-black text-[#087F8C]"
                                    >
                                        {{ product.price }}
                                    </span>

                                    <span
                                        v-if="product.oldPrice"
                                        class="text-[10px] text-gray-400 line-through"
                                    >
                                        {{ product.oldPrice }}
                                    </span>

                                </div>

                                <div
                                    class="mt-2 flex items-center gap-1 text-[10px] text-gray-400"
                                >

                                    <span
                                        class="text-[#F4B942]"
                                    >
                                        ★
                                    </span>

                                    <span>
                                        {{
                                            product.rating.toFixed(
                                                1
                                            )
                                        }}
                                    </span>

                                    <span>
                                        ({{ product.reviews }})
                                    </span>

                                </div>

                            </div>

                        </Link>

                    </div>

                    <div
                        v-else
                        class="mt-7 rounded-[24px] border border-dashed border-gray-200 bg-[#F8FAF9] p-12 text-center"
                    >

                        <div
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#E8F7F6] text-2xl"
                        >
                            🛍️
                        </div>

                        <h3
                            class="mt-4 text-sm font-extrabold text-gray-900"
                        >
                            Products are coming soon
                        </h3>

                        <p
                            class="mx-auto mt-1 max-w-sm text-xs leading-5 text-gray-500"
                        >
                            Check back soon for new products
                            from Alona sellers.
                        </p>

                    </div>

                </div>

            </section>


            <!-- =====================================================
                 RECENTLY VIEWED
            ====================================================== -->

            <section
                v-if="recentlyViewed.length"
                class="alona-reveal border-b border-gray-100 bg-[#F8FAF9]"
            >

                <div
                    class="mx-auto max-w-[1440px] px-4 py-10 sm:px-6 lg:px-8 lg:py-12"
                >

                    <div
                        class="flex items-end justify-between gap-4"
                    >

                        <div>

                            <div
                                class="flex items-center gap-2"
                            >

                                <span
                                    class="h-2 w-2 rounded-full bg-[#F4B942]"
                                ></span>

                                <p
                                    class="text-[10px] font-black uppercase tracking-[0.22em] text-[#087F8C]"
                                >
                                    Pick up where you left off
                                </p>

                            </div>

                            <h2
                                class="mt-2 text-2xl font-black tracking-[-0.03em] text-gray-900 sm:text-3xl"
                            >
                                Recently Viewed
                            </h2>

                            <p
                                class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm"
                            >
                                Products you've looked at recently.
                            </p>

                        </div>

                        <Link
                            :href="safeRoute('buyer.products')"
                            class="hidden items-center gap-2 text-xs font-bold text-[#087F8C] transition hover:gap-3 sm:flex"
                        >
                            Continue shopping
                            →
                        </Link>

                    </div>


                    <div
                        class="mt-7 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
                    >

                        <Link
                            v-for="(product, index) in recentlyViewed"
                            :key="product.id ?? `recent-${index}`"
                            :href="safeRoute('buyer.product', '#', product.id)"
                            class="recent-product-card group"
                        >

                            <div
                                class="relative aspect-[.92] overflow-hidden rounded-[22px] bg-white ring-1 ring-black/[0.04]"
                            >

                                <img
                                    v-if="product.image"
                                    :src="product.image"
                                    :alt="product.name"
                                    class="h-full w-full object-cover transition duration-500 ease-out group-hover:scale-105"
                                    loading="lazy"
                                />

                                <div
                                    v-else
                                    class="flex h-full items-center justify-center text-4xl"
                                >
                                    🛍️
                                </div>


                                <!-- VIEWED BADGE -->

                                <span
                                    class="absolute left-3 top-3 rounded-full bg-white/95 px-2.5 py-1.5 text-[8px] font-black uppercase tracking-wider text-[#087F8C] shadow-sm backdrop-blur"
                                >
                                    Viewed
                                </span>


                                <!-- SALE -->

                                <span
                                    v-if="product.isSale"
                                    class="absolute bottom-3 left-3 rounded-full bg-[#E85D5D] px-2.5 py-1 text-[9px] font-black text-white"
                                >
                                    SALE
                                </span>

                            </div>


                            <div
                                class="px-1 pt-3.5"
                            >

                                <p
                                    class="truncate text-[9px] font-bold uppercase tracking-[0.12em] text-[#087F8C]"
                                >
                                    {{ product.category }}
                                </p>

                                <h3
                                    class="mt-1 line-clamp-2 text-sm font-extrabold leading-5 text-gray-900 transition duration-300 group-hover:text-[#087F8C]"
                                >
                                    {{ product.name }}
                                </h3>

                                <div
                                    class="mt-2 flex items-center gap-2"
                                >

                                    <span
                                        class="text-sm font-black text-[#087F8C]"
                                    >
                                        {{ product.price }}
                                    </span>

                                    <span
                                        v-if="product.oldPrice"
                                        class="text-[10px] text-gray-400 line-through"
                                    >
                                        {{ product.oldPrice }}
                                    </span>

                                </div>

                                <div
                                    class="mt-2 flex items-center gap-1 text-[10px] text-gray-400"
                                >

                                    <span
                                        class="text-[#F4B942]"
                                    >
                                        ★
                                    </span>

                                    <span
                                        class="font-semibold text-gray-600"
                                    >
                                        {{
                                            product.rating > 0
                                                ? product.rating.toFixed(1)
                                                : 'New'
                                        }}
                                    </span>

                                    <span
                                        v-if="product.reviews > 0"
                                    >
                                        ({{ product.reviews }})
                                    </span>

                                </div>

                            </div>

                        </Link>

                    </div>


                    <!-- MOBILE CONTINUE SHOPPING -->

                    <div class="mt-6 sm:hidden">

                        <Link
                            :href="safeRoute('buyer.products')"
                            class="group flex w-full items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 text-xs font-extrabold text-[#087F8C] transition hover:border-[#087F8C]/20 hover:bg-[#E8F7F6]"
                        >
                            Continue shopping

                            <span
                                class="transition-transform duration-300 group-hover:translate-x-1"
                            >
                                →
                            </span>

                        </Link>

                    </div>

                </div>

            </section>


            <!-- =====================================================
                 DEAL BANNER
            ====================================================== -->

            <section
                class="alona-reveal mx-auto max-w-[1440px] px-4 py-10 sm:px-6 lg:px-8 lg:py-12"
            >

                <div
                    class="relative overflow-hidden rounded-[30px] bg-[#F4B942]"
                >

                    <div
                        class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-white/20"
                    ></div>

                    <div
                        class="absolute -bottom-28 left-[45%] h-64 w-64 rounded-full bg-[#087F8C]/10"
                    ></div>

                    <div
                        class="relative grid items-center gap-8 px-7 py-9 sm:px-10 lg:grid-cols-[1fr_auto] lg:px-14 lg:py-11"
                    >

                        <div>

                            <span
                                class="inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#087F8C]"
                            >
                                Alona finds
                            </span>

                            <h2
                                class="mt-4 max-w-2xl text-3xl font-black leading-tight tracking-[-0.03em] text-[#087F8C] sm:text-4xl"
                            >
                                Good finds don't have
                                to be complicated.
                            </h2>

                            <p
                                class="mt-3 max-w-xl text-sm leading-6 text-[#087F8C]/75"
                            >
                                Browse products, discover
                                new sellers, and find
                                something that fits your
                                everyday life.
                            </p>

                        </div>

                        <Link
                            :href="safeRoute('buyer.products')"
                            class="inline-flex w-fit items-center gap-3 rounded-2xl bg-[#087F8C] px-5 py-3.5 text-xs font-extrabold text-white shadow-lg transition hover:-translate-y-1 hover:bg-[#066974]"
                        >
                            Explore deals →
                        </Link>

                    </div>

                </div>

            </section>


            <!-- =====================================================
                 WHY ALONA
            ====================================================== -->

            <section
                class="alona-reveal bg-[#087F8C]"
            >

                <div
                    class="mx-auto max-w-[1440px] px-4 py-12 sm:px-6 lg:px-8 lg:py-16"
                >

                    <div
                        class="grid gap-10 lg:grid-cols-[.8fr_1.2fr]"
                    >

                        <div>

                            <p
                                class="text-[10px] font-black uppercase tracking-[0.22em] text-[#F4B942]"
                            >
                                Why Alona
                            </p>

                            <h2
                                class="mt-3 max-w-md text-3xl font-black leading-tight tracking-[-0.03em] text-white sm:text-4xl"
                            >
                                Shopping should feel
                                effortless.
                            </h2>

                            <p
                                class="mt-4 max-w-md text-sm leading-6 text-teal-50"
                            >
                                Alona brings products and
                                sellers together through a
                                marketplace designed to keep
                                discovering simple.
                            </p>

                        </div>

                        <div
                            class="grid gap-4 sm:grid-cols-3"
                        >

                            <div
                                v-for="benefit in benefits"
                                :key="benefit.number"
                                class="rounded-[24px] border border-white/10 bg-white/[0.07] p-5 transition duration-300 hover:-translate-y-1 hover:bg-white/10"
                            >

                                <span
                                    class="text-xs font-black text-[#F4B942]"
                                >
                                    {{ benefit.number }}
                                </span>

                                <h3
                                    class="mt-8 text-sm font-extrabold text-white"
                                >
                                    {{ benefit.title }}
                                </h3>

                                <p
                                    class="mt-2 text-xs leading-5 text-teal-100"
                                >
                                    {{ benefit.text }}
                                </p>

                            </div>

                        </div>

                    </div>

                </div>

            </section>


            <!-- =====================================================
                 SELLER CTA
            ====================================================== -->

            <section
                class="alona-reveal mx-auto max-w-[1440px] px-4 py-10 sm:px-6 lg:px-8 lg:py-12"
            >

                <div
                    class="relative overflow-hidden rounded-[30px] border border-gray-100 bg-white shadow-sm"
                >

                    <div
                        class="absolute right-0 top-0 h-40 w-40 rounded-full bg-[#E8F7F6]"
                    ></div>

                    <div
                        class="relative grid gap-8 p-7 sm:p-10 lg:grid-cols-[1fr_auto] lg:items-center lg:p-12"
                    >

                        <div>

                            <span
                                class="inline-flex rounded-full bg-[#FFF6DF] px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#A87308]"
                            >
                                For sellers
                            </span>

                            <h2
                                class="mt-4 text-2xl font-black tracking-tight text-gray-900 sm:text-3xl"
                            >
                                Have something worth
                                selling?
                            </h2>

                            <p
                                class="mt-2 max-w-xl text-sm leading-6 text-gray-500"
                            >
                                Put your products in front of
                                new customers and grow your
                                business with Alona.
                            </p>

                        </div>

                        <Link
                            :href="safeRoute('register.seller')"
                            class="inline-flex w-fit items-center gap-3 rounded-2xl border border-[#087F8C] px-5 py-3.5 text-xs font-extrabold text-[#087F8C] transition hover:bg-[#087F8C] hover:text-white"
                        >
                            Start selling →
                        </Link>

                    </div>

                </div>

            </section>


            <!-- =====================================================
                 FINAL CTA
            ====================================================== -->

            <section
                class="alona-reveal border-t border-gray-100 bg-white"
            >

                <div
                    class="mx-auto max-w-[1440px] px-4 py-12 text-center sm:px-6 lg:px-8 lg:py-16"
                >

                    <p
                        class="text-[10px] font-black uppercase tracking-[0.25em] text-[#087F8C]"
                    >
                        Your next find is waiting
                    </p>

                    <h2
                        class="mx-auto mt-3 max-w-2xl text-3xl font-black tracking-[-0.04em] text-gray-900 sm:text-4xl"
                    >
                        Discover something you
                        didn't know you needed.
                    </h2>

                    <p
                        class="mx-auto mt-4 max-w-xl text-sm leading-6 text-gray-500"
                    >
                        Explore the Alona marketplace and
                        find products from different
                        categories in one place.
                    </p>

                    <Link
                        :href="safeRoute('buyer.products')"
                        class="mt-7 inline-flex items-center gap-3 rounded-2xl bg-[#087F8C] px-6 py-3.5 text-xs font-extrabold text-white shadow-lg shadow-[#087F8C]/10 transition hover:-translate-y-1 hover:bg-[#066974]"
                    >
                        Explore Alona →
                    </Link>

                </div>

            </section>

        </div>

    </BuyerLayout>
</template>


<style scoped>

/*
|--------------------------------------------------------------------------
| Alona Reveal
|--------------------------------------------------------------------------
*/

.alona-reveal {
    opacity: 0;
    transform: translateY(28px);

    transition:
        opacity 0.75s ease,
        transform 0.75s cubic-bezier(.22, 1, .36, 1);
}

.alona-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
}


/*
|--------------------------------------------------------------------------
| Hero
|--------------------------------------------------------------------------
*/

.hero-card {
    animation: heroEnter 0.9s ease both;
}

.hero-item {
    animation: heroText 0.8s ease both;
}

.hero-item:nth-child(2) {
    animation-delay: 0.12s;
}

.hero-item:nth-child(3) {
    animation-delay: 0.24s;
}

.hero-item:nth-child(4) {
    animation-delay: 0.36s;
}

.hero-visual {
    animation: visualEnter 1s ease both;
}

.alona-floating {
    animation: gentleFloat 5s ease-in-out infinite;
}


/*
|--------------------------------------------------------------------------
| CATEGORY SLIDER
|--------------------------------------------------------------------------
*/

.category-slider {
    scrollbar-width: none;
    -ms-overflow-style: none;

    overscroll-behavior-x: contain;

    scroll-behavior: smooth;

    -webkit-overflow-scrolling: touch;

    scroll-snap-type: x mandatory;
}

.category-slider::-webkit-scrollbar {
    display: none;
}


/*
|--------------------------------------------------------------------------
| Category Cards
|--------------------------------------------------------------------------
*/

.category-card {
    width: 205px;

    animation:
        categoryEnter
        0.65s
        cubic-bezier(.22, 1, .36, 1)
        both;

    animation-play-state: paused;

    will-change:
        transform,
        opacity;

    touch-action: manipulation;
}

.alona-reveal.is-visible .category-card {
    animation-play-state: running;
}

.category-card img {
    will-change: transform;
}

.category-image {
    filter: saturate(.96);

    backface-visibility: hidden;

    transform: translateZ(0);
}

.category-card:hover .category-image {
    filter: saturate(1.08);
}

.category-card:active {
    transform:
        translateY(0)
        scale(.985);
}


/*
|--------------------------------------------------------------------------
| Category Navigation Buttons
|--------------------------------------------------------------------------
*/

.category-nav-button {
    display: flex;

    height: 40px;
    width: 40px;

    align-items: center;
    justify-content: center;

    border-radius: 9999px;

    border: 1px solid #E5E7EB;

    background: #FFFFFF;

    color: #087F8C;

    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.04);

    transition:
        transform .25s ease,
        background-color .25s ease,
        color .25s ease,
        border-color .25s ease,
        box-shadow .25s ease;
}

.category-nav-button:hover {
    transform: translateY(-2px);

    background: #087F8C;

    color: #FFFFFF;

    border-color: #087F8C;

    box-shadow:
        0 8px 20px rgba(8, 127, 140, 0.16);
}

.category-nav-button:active {
    transform: scale(.94);
}


/*
|--------------------------------------------------------------------------
| Product Cards
|--------------------------------------------------------------------------
*/

.product-card {
    transition:
        transform 0.3s ease,
        opacity 0.3s ease;
}

.product-card:hover {
    transform:
        translateY(-5px);
}


/*
|--------------------------------------------------------------------------
| Recently Viewed Cards
|--------------------------------------------------------------------------
*/

.recent-product-card {
    transition:
        transform 0.3s ease,
        opacity 0.3s ease;
}

.recent-product-card:hover {
    transform:
        translateY(-5px);
}

.recent-product-card:active {
    transform:
        translateY(-1px)
        scale(.99);
}

.recent-product-card img {
    will-change: transform;

    backface-visibility: hidden;

    transform: translateZ(0);
}


/*
|--------------------------------------------------------------------------
| Hero Animations
|--------------------------------------------------------------------------
*/

@keyframes heroEnter {
    from {
        opacity: 0;

        transform:
            translateY(22px);
    }

    to {
        opacity: 1;

        transform:
            translateY(0);
    }
}

@keyframes heroText {
    from {
        opacity: 0;

        transform:
            translateY(18px);
    }

    to {
        opacity: 1;

        transform:
            translateY(0);
    }
}

@keyframes visualEnter {
    from {
        opacity: 0;

        transform:
            translateX(30px)
            scale(.96);
    }

    to {
        opacity: 1;

        transform:
            translateX(0)
            scale(1);
    }
}

@keyframes gentleFloat {
    0%,
    100% {
        transform:
            translateY(0);
    }

    50% {
        transform:
            translateY(-9px);
    }
}


/*
|--------------------------------------------------------------------------
| Category Animation
|--------------------------------------------------------------------------
*/

@keyframes categoryEnter {
    from {
        opacity: 0;

        transform:
            translateY(20px)
            scale(.97);
    }

    to {
        opacity: 1;

        transform:
            translateY(0)
            scale(1);
    }
}


/*
|--------------------------------------------------------------------------
| Reduced Motion
|--------------------------------------------------------------------------
*/

@media (prefers-reduced-motion: reduce) {

    .alona-page *,
    .alona-page *::before,
    .alona-page *::after {
        animation-duration:
            0.01ms !important;

        animation-iteration-count:
            1 !important;

        transition-duration:
            0.01ms !important;

        scroll-behavior:
            auto !important;
    }

    .alona-reveal {
        opacity: 1;

        transform: none;
    }

    .category-card {
        animation-play-state:
            running;
    }
}


/*
|--------------------------------------------------------------------------
| Tablet
|--------------------------------------------------------------------------
*/

@media (min-width: 640px) and (max-width: 1023px) {

    .category-card {
        width: 220px;
    }
}


/*
|--------------------------------------------------------------------------
| Desktop
|--------------------------------------------------------------------------
*/

@media (min-width: 1024px) {

    .category-card {
        width: 220px;
    }
}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 639px) {

    .alona-floating {
        width: 220px;
    }

    .category-card {
        width: 170px;

        border-radius: 18px;
    }

    .category-card .category-image {
        transform: scale(1.01);
    }

    .category-slider {
        margin-right: -16px;

        padding-right: 16px;

        gap: 10px;

        scroll-padding-left: 0;
    }

    .category-nav-button {
        height: 38px;
        width: 38px;
    }
}

</style>