<template>
    <div class="app-auth-shell">
        <div class="w-full max-w-md overflow-hidden app-panel app-auth-card">
            <!-- Header -->
            <div class="app-auth-header">
                <img :src="logoUrl" alt="Alona" class="mx-auto mb-3 h-10 w-10 object-contain" />
                <h1 class="app-auth-title">Sorting Center Login</h1>
                <p class="app-description text-center">Access your sorting center portal and shipments</p>
            </div>

            <!-- Login Form (Visible when not logged in) -->
            <form v-if="!authData" @submit.prevent="handleLogin" class="p-6 space-y-4">
                <!-- General Error Banner -->
                <div v-if="errorMessage" class="app-feedback app-feedback--error">
                    <span>{{ errorMessage }}</span>
                </div>

                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full placeholder-gray-400 app-input"
                        placeholder="you@sortingcenter.com"
                        :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': fieldErrors.email }"
                        @input="clearFieldError('email')"
                    />
                    <p v-if="fieldErrors.email" class="mt-1 text-sm text-red-600">{{ fieldErrors.email[0] }}</p>
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full placeholder-gray-400 app-input"
                        placeholder="Enter your password"
                        :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': fieldErrors.password }"
                        @input="clearFieldError('password')"
                    />
                    <p v-if="fieldErrors.password" class="mt-1 text-sm text-red-600">{{ fieldErrors.password[0] }}</p>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full flex justify-center border border-transparent text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-300 disabled:opacity-50 disabled:cursor-not-allowed app-button-primary"
                >
                    <span v-if="loading" class="flex items-center">
                        Signing in...
                    </span>
                    <span v-else>Sign In</span>
                </button>

                <!-- Navigation link to Register -->
                <div class="pt-2 text-center text-sm text-gray-600">
                    Don't have an account yet?
                    <a href="/sorting-center/register" class="font-medium text-[var(--app-primary)] hover:text-[var(--app-primary-dark)] ml-1 transition">
                        Register your sorting center
                    </a>
                </div>
            </form>

            <!-- Status State 1: PENDING -->
            <div v-else-if="authData.approval_status === 'pending'" class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-amber-100 mb-4">
                    <span class="text-sm font-semibold text-amber-600">Pending</span>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Awaiting Approval</h2>
                <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 mb-4">
                    Status: Pending Review
                </div>
                <p class="text-gray-600 mb-6">
                    Your registration is still awaiting administrator approval. You will receive full access to the sorting center dashboard once your documents and business credentials are verified.
                </p>

                <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Business:</span>
                        <span class="font-medium text-gray-900">{{ authData.sorting_center?.business_name || authData.user?.name }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Email:</span>
                        <span class="font-medium text-gray-900">{{ authData.user?.email }}</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <button
                        @click="handleLogout"
                        class="w-full py-2.5 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition"
                    >
                        Sign Out
                    </button>
                </div>
            </div>

            <!-- Status State 3: REJECTED -->
            <div v-else-if="authData.approval_status === 'rejected'" class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                    <span class="text-sm font-semibold text-red-600">Rejected</span>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Application Not Approved</h2>
                <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 mb-4">
                    Status: Rejected
                </div>
                <p class="text-gray-600 mb-6">
                    We regret to inform you that your sorting center application was not approved during the administrative verification process.
                </p>

                <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm text-gray-600 text-left">
                    <p class="font-medium text-gray-900 mb-1">Need assistance?</p>
                    <p>Please contact our support team at <strong class="text-[var(--app-primary)]">support@logistics.com</strong> with your business registration details.</p>
                </div>

                <button
                    @click="handleLogout"
                    class="w-full py-2.5 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition"
                >
                    Sign Out
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';

const logoUrl = '/images/alona.png';
const form = reactive({
    email: '',
    password: '',
});

const fieldErrors = reactive({});
const errorMessage = ref('');
const loading = ref(false);
const authData = ref(null);

onMounted(() => {
    localStorage.removeItem('logistics_auth_token');
    localStorage.removeItem('logistics_auth_session');
});

function clearFieldError(field) {
    if (fieldErrors[field]) {
        delete fieldErrors[field];
    }
    errorMessage.value = '';
}

async function handleLogin() {
    loading.value = true;
    errorMessage.value = '';
    Object.keys(fieldErrors).forEach(k => delete fieldErrors[k]);

    try {
        const response = await fetch('/api/sorting-center/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                email: form.email,
                password: form.password,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 422) {
                // Validation errors
                Object.assign(fieldErrors, data.errors || {});
                errorMessage.value = data.message || 'Please correct the validation errors.';
            } else if (response.status === 401) {
                // Invalid credentials
                errorMessage.value = data.message || 'Invalid email or password.';
            } else {
                errorMessage.value = data.message || 'An unexpected error occurred. Please try again.';
            }
            return;
        }

        // Store token and session data in localStorage
        if (data.token) {
            localStorage.setItem('logistics_auth_token', data.token);
            localStorage.setItem('logistics_auth_session', JSON.stringify(data));
        }

        if (data.approval_status === 'approved') {
            window.location.assign('/sorting-center/dashboard');
            return;
        }

        authData.value = data;
    } catch (err) {
        errorMessage.value = 'Network error. Please check your connection and try again.';
    } finally {
        loading.value = false;
    }
}

async function handleLogout() {
    const token = localStorage.getItem('logistics_auth_token');
    if (token) {
        try {
            const response = await fetch('/api/logistics/logout', {
                method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer ' + token },
            });
            if (!response.ok) throw new Error('Unable to sign out.');
        } catch (error) {
            errorMessage.value = 'Unable to sign out. Please try again.';
            return;
        }
    }
    localStorage.removeItem('logistics_auth_token');
    localStorage.removeItem('logistics_auth_session');
    authData.value = null;
    form.password = '';
    errorMessage.value = '';
}
</script>
