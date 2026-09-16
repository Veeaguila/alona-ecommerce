```vue
<!-- Buyer delivery address book with add, edit, default, and remove support. -->
<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    addresses: {
        type: Array,
        default: () => [],
    },
})

const isEditing = ref(false)
const editingAddressId = ref(null)

const form = useForm({
    label: 'Home',
    address: '',
    is_default: false,
})

const addressList = computed(() => {
    return Array.isArray(props.addresses)
        ? props.addresses
        : []
})

const resetForm = () => {
    form.reset()
    form.label = 'Home'
    form.address = ''
    form.is_default = false

    isEditing.value = false
    editingAddressId.value = null
    form.clearErrors()
}

const submit = () => {
    if (isEditing.value && editingAddressId.value) {
        form.patch(
            route(
                'buyer.addresses.update',
                editingAddressId.value
            ),
            {
                preserveScroll: true,

                onSuccess: () => {
                    resetForm()
                },
            }
        )

        return
    }

    form.post(
        route('buyer.addresses.store'),
        {
            preserveScroll: true,

            onSuccess: () => {
                resetForm()
            },
        }
    )
}

const editAddress = address => {
    if (!address?.id) return

    isEditing.value = true
    editingAddressId.value = address.id

    form.clearErrors()

    form.label = address.label || 'Home'
    form.address = address.address || ''
    form.is_default = Boolean(address.is_default)
}

const remove = address => {
    if (!address?.id) return

    if (
        !window.confirm(
            `Remove the "${address.label || 'address'}" address?`
        )
    ) {
        return
    }

    useForm({}).delete(
        route(
            'buyer.addresses.destroy',
            address.id
        ),
        {
            preserveScroll: true,
        }
    )
}
</script>

<template>
    <Head title="My Addresses" />

    <BuyerLayout>
        <main
            class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8"
        >
            <!-- PAGE HEADER -->
            <div class="mb-8">
                <p
                    class="text-sm font-semibold text-indigo-600"
                >
                    Delivery details
                </p>

                <h1
                    class="mt-1 text-3xl font-bold text-gray-900"
                >
                    My Addresses
                </h1>

                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-gray-500"
                >
                    Manage the delivery addresses you use for
                    your Zellora orders.
                </p>
            </div>

            <div
                class="grid gap-6 lg:grid-cols-[1fr_360px]"
            >
                <!-- SAVED ADDRESSES -->
                <section class="space-y-4">
                    <div
                        v-if="addressList.length"
                        class="space-y-4"
                    >
                        <article
                            v-for="address in addressList"
                            :key="address.id"
                            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                        >
                            <div
                                class="flex items-start justify-between gap-4"
                            >
                                <div>
                                    <h2
                                        class="font-bold text-gray-900"
                                    >
                                        {{ address.label }}
                                    </h2>

                                    <span
                                        v-if="address.is_default"
                                        class="mt-2 inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-600"
                                    >
                                        Default
                                    </span>
                                </div>

                                <div
                                    class="flex shrink-0 items-center gap-3"
                                >
                                    <button
                                        type="button"
                                        class="text-sm font-semibold text-indigo-600 hover:text-indigo-700"
                                        @click="editAddress(address)"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        type="button"
                                        class="text-sm font-semibold text-red-500 hover:text-red-600"
                                        @click="remove(address)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>

                            <p
                                class="mt-4 text-sm leading-6 text-gray-500"
                            >
                                {{ address.address }}
                            </p>
                        </article>
                    </div>

                    <!-- EMPTY STATE -->
                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center"
                    >
                        <h2
                            class="text-lg font-semibold text-gray-900"
                        >
                            No saved addresses yet
                        </h2>

                        <p
                            class="mt-2 text-sm text-gray-500"
                        >
                            Add your first delivery address
                            using the form.
                        </p>
                    </div>
                </section>

                <!-- ADD / EDIT FORM -->
                <form
                    class="h-fit rounded-2xl border border-gray-200 bg-white p-6 shadow-sm"
                    @submit.prevent="submit"
                >
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-indigo-600"
                        >
                            {{ isEditing ? 'Edit address' : 'New address' }}
                        </p>

                        <h2
                            class="mt-1 text-lg font-bold text-gray-900"
                        >
                            {{
                                isEditing
                                    ? 'Update address'
                                    : 'Add address'
                            }}
                        </h2>
                    </div>

                    <!-- LABEL -->
                    <label class="mt-5 block">
                        <span
                            class="text-sm font-medium text-gray-700"
                        >
                            Label
                        </span>

                        <input
                            v-model="form.label"
                            required
                            maxlength="50"
                            class="field"
                            placeholder="Home"
                        />

                        <p
                            v-if="form.errors.label"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.label }}
                        </p>
                    </label>

                    <!-- ADDRESS -->
                    <label class="mt-4 block">
                        <span
                            class="text-sm font-medium text-gray-700"
                        >
                            Complete address
                        </span>

                        <textarea
                            v-model="form.address"
                            required
                            maxlength="1000"
                            rows="5"
                            class="field"
                            placeholder="House number, street, barangay, municipality, province"
                        ></textarea>

                        <p
                            v-if="form.errors.address"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.address }}
                        </p>
                    </label>

                    <!-- DEFAULT -->
                    <label
                        class="mt-4 flex items-center gap-2 text-sm text-gray-600"
                    >
                        <input
                            v-model="form.is_default"
                            type="checkbox"
                            class="rounded text-indigo-600"
                        />

                        <span>
                            Set as default address
                        </span>
                    </label>

                    <!-- ACTIONS -->
                    <div class="mt-5 flex gap-3">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex-1 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : isEditing
                                        ? 'Update address'
                                        : 'Save address'
                            }}
                        </button>

                        <button
                            v-if="isEditing"
                            type="button"
                            :disabled="form.processing"
                            class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 disabled:opacity-50"
                            @click="resetForm"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- CHECKOUT LINK -->
            <Link
                :href="route('buyer.checkout')"
                class="mt-6 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-700"
            >
                Continue to checkout →
            </Link>
        </main>
    </BuyerLayout>
</template>

<style scoped>
.field {
    margin-top: 0.5rem;
    display: block;
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgb(229 231 235);
    background: rgb(249 250 251);
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.15s ease, background-color 0.15s ease;
}

.field:focus {
    border-color: rgb(129 140 248);
    background: white;
}
</style>