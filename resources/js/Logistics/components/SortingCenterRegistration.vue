<template>
    <div class="app-auth-shell">
        <div class="w-full max-w-lg overflow-hidden app-panel app-auth-card">
            <!-- Header -->
            <div class="app-auth-header">
                <img :src="logoUrl" alt="Alona" class="mx-auto mb-3 h-[60px] w-[60px] object-contain" />
                <h1 class="app-auth-title">Sorting Center Registration</h1>
                <p class="app-description text-center">Register your sorting center to start processing shipments</p>
            </div>

            <!-- Form -->
            <form v-if="!submitted" @submit.prevent="handleSubmit" class="p-6 space-y-4">
                <!-- Business Name -->
                <div>
                    <label for="business_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Business Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="business_name"
                        v-model="form.business_name"
                        type="text"
                        class="w-full placeholder-gray-400 app-input"
                        placeholder="Your business/legal entity name"
                        :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': errors.business_name }"
                        @blur="touchField('business_name')"
                    />
                    <p v-if="errors.business_name" class="mt-1 text-sm text-red-600">{{ errors.business_name[0] }}</p>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="w-full placeholder-gray-400 app-input"
                        placeholder="you@sortingcenter.com"
                        :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': errors.email }"
                        @blur="touchField('email')"
                    />
                    <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email[0] }}</p>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="w-full placeholder-gray-400 app-input"
                        placeholder="Minimum 8 characters"
                        :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': errors.password }"
                        @blur="touchField('password')"
                    />
                    <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password[0] }}</p>
                </div>

                <!-- Password Confirmation -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                        Confirm Password <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="w-full placeholder-gray-400 app-input"
                        placeholder="Re-enter your password"
                        :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': errors.password_confirmation }"
                        @blur="touchField('password_confirmation')"
                    />
                </div>

                <!-- ID Document Upload -->
                <div>
                    <label for="id_doc" class="block text-sm font-medium text-gray-700 mb-1">
                        ID Upload (Image or PDF) <span class="text-red-500">*</span>
                    </label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-orange-400 border-dashed rounded-lg cursor-pointer hover:border-orange-500 transition" :class="{ 'border-red-500 bg-red-50': errors.id_doc }">
                        <div class="space-y-1 text-center">
                            <div class="flex text-sm text-gray-600">
                                <label for="id_doc" class="relative cursor-pointer rounded-md font-medium text-[var(--app-primary)] hover:text-[var(--app-primary-dark)] focus-within:outline-none">
                                    <span>Upload a file</span>
                                    <input id="id_doc" ref="idDocInput" type="file" class="sr-only app-input" accept="image/*,.pdf" @change="onFileSelect('id_doc', $event)" />
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-[var(--app-text-muted)]">JPG, PNG, or PDF — Max 5MB</p>
                            <p v-if="form.id_doc" class="text-xs text-[var(--app-primary)] font-medium mt-1">{{ form.id_doc.name }}</p>
                        </div>
                    </div>
                    <p v-if="errors.id_doc" class="mt-1 text-sm text-red-600">{{ errors.id_doc[0] }}</p>
                </div>

                <!-- Permit Document Upload -->
                <div>
                    <label for="permit_doc" class="block text-sm font-medium text-gray-700 mb-1">
                        Business / DTI Permit Upload (Image or PDF) <span class="text-red-500">*</span>
                    </label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-orange-400 border-dashed rounded-lg cursor-pointer hover:border-orange-500 transition" :class="{ 'border-red-500 bg-red-50': errors.permit_doc }">
                        <div class="space-y-1 text-center">
                            <div class="flex text-sm text-gray-600">
                                <label for="permit_doc" class="relative cursor-pointer rounded-md font-medium text-[var(--app-primary)] hover:text-[var(--app-primary-dark)] focus-within:outline-none">
                                    <span>Upload a file</span>
                                    <input id="permit_doc" ref="permitDocInput" type="file" class="sr-only app-input" accept="image/*,.pdf" @change="onFileSelect('permit_doc', $event)" />
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-[var(--app-text-muted)]">JPG, PNG, or PDF — Max 5MB</p>
                            <p v-if="form.permit_doc" class="text-xs text-[var(--app-primary)] font-medium mt-1">{{ form.permit_doc.name }}</p>
                        </div>
                    </div>
                    <p v-if="errors.permit_doc" class="mt-1 text-sm text-red-600">{{ errors.permit_doc[0] }}</p>
                </div>

                <!-- Submit Error -->
                <div v-if="submitError" class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                    {{ submitError }}
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full flex justify-center border border-transparent text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-300 disabled:opacity-50 disabled:cursor-not-allowed app-button-primary"
                >
                    <span v-if="loading" class="flex items-center">
                        Submitting...
                    </span>
                    <span v-else>Submit Registration</span>
                </button>

                <!-- Navigation link to Login -->
                <div class="pt-2 text-center text-sm text-gray-600">
                    Already have an account?
                    <a href="/sorting-center/login" class="font-medium text-[var(--app-primary)] hover:text-[var(--app-primary-dark)] ml-1 transition">
                        Log in here
                    </a>
                </div>
            </form>

            <!-- Success State -->
            <div v-else class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <span class="text-sm font-semibold text-green-600">Submitted</span>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Registration Submitted</h2>
                <p class="text-gray-600 mb-6">
                    Your sorting center registration has been received and is now <strong>awaiting admin approval</strong>.
                    You'll be notified once your account is activated.
                </p>
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <p class="text-sm text-gray-500">Business Name</p>
                    <p class="text-lg font-semibold text-gray-900">{{ submittedData?.business_name }}</p>
                </div>
                <div class="space-y-3">
                    <a
                        href="/sorting-center/login"
                        class="block w-full border border-transparent text-sm font-semibold text-center app-button-primary"
                    >
                        Go to Login
                    </a>
                    <button
                        @click="resetForm"
                        class="text-sm font-medium text-[var(--app-primary)] hover:text-[var(--app-primary-dark)] transition"
                    >
                        Register another sorting center
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';

const logoUrl = '/images/alona.png';
const form = reactive({
    business_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    id_doc: null,
    permit_doc: null,
});

const errors = reactive({});
const loading = ref(false);
const submitted = ref(false);
const submittedData = ref(null);
const submitError = ref('');

const idDocInput = ref(null);
const permitDocInput = ref(null);

function touchField(field) {
    if (errors[field]) {
        delete errors[field];
    }
}

function onFileSelect(field, event) {
    form[field] = event.target.files[0] || null;
    if (errors[field]) {
        delete errors[field];
    }
}

function resetForm() {
    form.business_name = '';
    form.email = '';
    form.password = '';
    form.password_confirmation = '';
    form.id_doc = null;
    form.permit_doc = null;
    Object.keys(errors).forEach(k => delete errors[k]);
    submitted.value = false;
    submittedData.value = null;
    submitError.value = '';
    if (idDocInput.value) idDocInput.value.value = '';
    if (permitDocInput.value) permitDocInput.value.value = '';
}

async function handleSubmit() {
    loading.value = true;
    submitError.value = '';
    Object.keys(errors).forEach(k => delete errors[k]);

    const fd = new FormData();
    fd.append('business_name', form.business_name);
    fd.append('email', form.email);
    fd.append('password', form.password);
    fd.append('password_confirmation', form.password_confirmation);
    fd.append('id_doc', form.id_doc);
    fd.append('permit_doc', form.permit_doc);

    try {
        const response = await fetch('/api/sorting-center/register', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
            },
            body: fd,
        });

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 422) {
                Object.assign(errors, data.errors || {});
            } else {
                submitError.value = data.message || 'An unexpected error occurred. Please try again.';
            }
            return;
        }

        submittedData.value = data.data;
        submitted.value = true;
    } catch (e) {
        submitError.value = 'Network error. Please check your connection and try again.';
    } finally {
        loading.value = false;
    }
}
</script>
