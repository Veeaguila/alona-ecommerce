```vue
<!-- Buyer account profile page. -->
<script setup>
import { Head } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'
import DeleteUserForm from './Partials/DeleteUserForm.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'

defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },

    status: {
        type: String,
        default: null,
    },

    active: {
        type: String,
        default: 'account',
    },
})
</script>

<template>
    <Head title="My Account" />

    <BuyerLayout :active="active">
        <main class="min-h-screen bg-gray-50 px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl">

                <!-- PAGE HEADER -->
                <div class="mb-8">
                    <p class="text-sm font-semibold text-indigo-600">
                        Buyer Account
                    </p>

                    <h1 class="mt-1 text-3xl font-bold text-gray-900">
                        My Account
                    </h1>

                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500">
                        Manage your personal information, contact details,
                        delivery information, password, and account security.
                    </p>
                </div>

                <!-- PROFILE INFORMATION -->
                <section
                    class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                >
                    <div
                        class="border-b border-gray-100 bg-gray-50 px-5 py-4 sm:px-8"
                    >
                        <h2 class="text-base font-semibold text-gray-900">
                            Personal Information
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Update the information associated with your buyer account.
                        </p>
                    </div>

                    <div class="p-5 sm:p-8">
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                    </div>
                </section>

                <!-- PASSWORD -->
                <section
                    class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                >
                    <div
                        class="border-b border-gray-100 bg-gray-50 px-5 py-4 sm:px-8"
                    >
                        <h2 class="text-base font-semibold text-gray-900">
                            Password & Security
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Keep your buyer account secure by using a strong password.
                        </p>
                    </div>

                    <div class="p-5 sm:p-8">
                        <UpdatePasswordForm />
                    </div>
                </section>

                <!-- DELETE ACCOUNT -->
                <section
                    class="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-sm"
                >
                    <div
                        class="border-b border-red-100 bg-red-50 px-5 py-4 sm:px-8"
                    >
                        <h2 class="text-base font-semibold text-red-700">
                            Delete Account
                        </h2>

                        <p class="mt-1 text-sm text-red-600">
                            Permanently delete your buyer account and associated data.
                        </p>
                    </div>

                    <div class="p-5 sm:p-8">
                        <DeleteUserForm />
                    </div>
                </section>

            </div>
        </main>
    </BuyerLayout>
</template>
```
