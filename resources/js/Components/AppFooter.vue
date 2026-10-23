<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const defaultFooter = {
    theme: 'light',
    brand: {
        tagline: 'Discover products, connect with sellers, and enjoy simple shopping with Alona.',
        copyright: `© ${new Date().getFullYear()} Alona. All rights reserved.`,
        subtext: 'A marketplace made simple.',
    },
    sections: [
        {
            id: 'shop',
            title: 'Shop',
            links: [
                { label: 'All Products', url: '/products' },
                { label: 'Categories', url: '/categories' },
                { label: 'Cart', url: '/buyer/cart' },
                { label: 'Track My Order', url: '/buyer/orders' },
            ],
        },
        {
            id: 'customer-support',
            title: 'Customer Support',
            links: [
                { label: 'Help Center', url: '/help' },
                { label: 'Frequently Asked Questions', url: '/faq' },
                { label: 'Contact Us', url: '/help' },
                { label: 'Complaints & Ticket Status', url: '/buyer/complaints' },
                { label: 'Buyer Protection', url: '/policies' },
            ],
        },
        {
            id: 'partner-account',
            title: 'Partner & Account',
            links: [
                { label: 'Become a Seller', url: '/register/seller' },
                { label: 'Become a Rider', url: '/register' },
                { label: 'Logistics Partner', url: '/register' },
                { label: 'My Account', url: '/buyer/dashboard' },
            ],
        },
        {
            id: 'policies',
            title: 'Policies & Information',
            links: [
                { label: 'Privacy Policy', url: '/policies#privacy' },
                { label: 'Terms & Conditions', url: '/policies#terms' },
                { label: 'Shipping & Delivery Policy', url: '/policies#shipping' },
                { label: 'Returns & Refunds Policy', url: '/policies#returns' },
                { label: 'Payment Methods', url: '/policies?policy=payment' },
            ],
        },
    ],
}

const footerData = computed(() => {
    const fromProps = page.props.footerSettings
    if (fromProps && Array.isArray(fromProps.sections) && fromProps.sections.length > 0) {
        return fromProps
    }
    return defaultFooter
})

const handleLinkClick = (e, link) => {
    const url = link.url || ''
    const label = (link.label || '').toLowerCase()

    if (url === '#messages' || url === '#chat' || label.includes('seller messages')) {
        e.preventDefault()
        window.dispatchEvent(new CustomEvent('open-buyer-chat'))
    }
}

const isExternal = url => {
    return typeof url === 'string' && (url.startsWith('http://') || url.startsWith('https://'))
}

const isChatTrigger = link => {
    const url = link.url || ''
    const label = (link.label || '').toLowerCase()
    return url === '#messages' || url === '#chat' || label.includes('seller messages')
}
</script>

<template>
    <footer class="border-t border-[#E5E7EB] bg-white">
        <div
            class="mx-auto grid max-w-[1536px] w-full gap-10 px-4 py-12 sm:px-6 md:grid-cols-2 lg:grid-cols-5 lg:px-8 2xl:px-10"
        >
            <!-- BRAND -->
            <div class="md:col-span-2 lg:col-span-1">
                <Link href="/" class="inline-flex">
                    <img
                        src="/images/alona.png"
                        alt="Alona"
                        class="h-10 w-auto object-contain"
                    />
                </Link>

                <p class="mt-4 max-w-xs text-sm leading-6 text-[#64748B]">
                    {{ footerData.brand?.tagline || defaultFooter.brand.tagline }}
                </p>
            </div>

            <!-- DYNAMIC SECTIONS -->
            <div
                v-for="section in footerData.sections"
                :key="section.id || section.title"
            >
                <h3 class="text-xs font-black uppercase tracking-[0.16em] text-[#1F2937]">
                    {{ section.title }}
                </h3>

                <div class="mt-4 space-y-3">
                    <template
                        v-for="(link, lIdx) in section.links"
                        :key="lIdx"
                    >
                        <a
                            v-if="isExternal(link.url)"
                            :href="link.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            {{ link.label }}
                        </a>

                        <button
                            v-else-if="isChatTrigger(link)"
                            type="button"
                            @click="handleLinkClick($event, link)"
                            class="block text-left text-sm text-[#64748B] transition hover:text-[#087F8C] cursor-pointer"
                        >
                            {{ link.label }}
                        </button>

                        <Link
                            v-else
                            :href="link.url || '#'"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            {{ link.label }}
                        </Link>
                    </template>
                </div>
            </div>
        </div>

        <!-- FOOTER BOTTOM -->
        <div class="border-t border-[#E5E7EB]">
            <div
                class="mx-auto flex max-w-[1536px] w-full flex-col gap-2 px-4 py-5 text-xs text-[#64748B] sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8 2xl:px-10"
            >
                <p>
                    {{ footerData.brand?.copyright || defaultFooter.brand.copyright }}
                </p>

                <p>
                    {{ footerData.brand?.subtext || defaultFooter.brand.subtext }}
                </p>
            </div>
        </div>
    </footer>
</template>
