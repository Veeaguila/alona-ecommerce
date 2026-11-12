<!-- Password update form for the profile settings page. -->
<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
        <!-- Section header -->
        <div class="border-b border-[#E5E7EB] bg-gradient-to-r from-[#E8F7F6] via-white to-white px-5 py-5 sm:px-6">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#087F8C] text-white shadow-sm">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <rect x="3" y="11" width="18" height="10" rx="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                </div>

                <div>
                    <div class="mb-1 flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"></span>
                        <span class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#087F8C]">
                            Account security
                        </span>
                    </div>

                    <h2 class="text-lg font-bold tracking-tight text-[#1F2937]">
                        Update Password
                    </h2>

                    <p class="mt-1 max-w-2xl text-sm leading-5 text-[#64748B]">
                        Keep your Alona account secure with a strong, unique password.
                    </p>
                </div>
            </div>
        </div>

        <form
            @submit.prevent="updatePassword"
            class="space-y-5 px-5 py-5 sm:px-6 sm:py-6"
        >
            <div class="grid gap-5 md:grid-cols-2">
                <!-- Current password -->
                <div class="md:col-span-2">
                    <InputLabel
                        for="current_password"
                        value="Current Password"
                        class="!text-sm !font-semibold !text-[#1F2937]"
                    />

                    <div class="relative mt-2">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[#94A3B8]">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <rect x="3" y="11" width="18" height="10" rx="2" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                        </div>

                        <TextInput
                            id="current_password"
                            ref="currentPasswordInput"
                            v-model="form.current_password"
                            type="password"
                            class="!mt-0 block w-full !rounded-xl !border-[#E5E7EB] !py-2.5 !pl-10 !text-sm !shadow-sm focus:!border-[#087F8C] focus:!ring-[#087F8C]"
                            autocomplete="current-password"
                        />
                    </div>

                    <InputError
                        :message="form.errors.current_password"
                        class="mt-2"
                    />
                </div>

                <!-- New password -->
                <div>
                    <InputLabel
                        for="password"
                        value="New Password"
                        class="!text-sm !font-semibold !text-[#1F2937]"
                    />

                    <div class="relative mt-2">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[#94A3B8]">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.778-7.778z" />
                                <path d="m15.5 6.5 2 2M18 4l2 2" />
                            </svg>
                        </div>

                        <TextInput
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="!mt-0 block w-full !rounded-xl !border-[#E5E7EB] !py-2.5 !pl-10 !text-sm !shadow-sm focus:!border-[#087F8C] focus:!ring-[#087F8C]"
                            autocomplete="new-password"
                        />
                    </div>

                    <InputError
                        :message="form.errors.password"
                        class="mt-2"
                    />
                </div>

                <!-- Confirm password -->
                <div>
                    <InputLabel
                        for="password_confirmation"
                        value="Confirm Password"
                        class="!text-sm !font-semibold !text-[#1F2937]"
                    />

                    <div class="relative mt-2">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[#94A3B8]">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="M9 12l2 2 4-4" />
                                <path d="M12 3a9 9 0 1 0 9 9" />
                            </svg>
                        </div>

                        <TextInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="!mt-0 block w-full !rounded-xl !border-[#E5E7EB] !py-2.5 !pl-10 !text-sm !shadow-sm focus:!border-[#087F8C] focus:!ring-[#087F8C]"
                            autocomplete="new-password"
                        />
                    </div>

                    <InputError
                        :message="form.errors.password_confirmation"
                        class="mt-2"
                    />
                </div>
            </div>

            <!-- Password guidance -->
            <div class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z" />
                            <path d="M9 12l2 2 4-4" />
                        </svg>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-[#1F2937]">
                            Use a strong password
                        </p>
                        <p class="mt-0.5 text-xs leading-5 text-[#64748B]">
                            Choose a long password that you don't reuse on other websites.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Save action -->
            <div class="flex flex-col gap-3 border-t border-[#E5E7EB] pt-5 sm:flex-row sm:items-center">
                <PrimaryButton
                    :disabled="form.processing"
                    class="!rounded-xl !bg-[#087F8C] !px-5 !py-2.5 !text-sm !font-semibold !shadow-sm hover:!bg-[#066D78] focus:!ring-[#087F8C] disabled:!cursor-not-allowed disabled:!opacity-60"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <svg
                            class="h-4 w-4 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                            />
                        </svg>
                        Saving...
                    </span>

                    <span v-else>Save Password</span>
                </PrimaryButton>

                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 translate-y-1"
                    leave-active-class="transition ease-in duration-150"
                    leave-to-class="opacity-0 translate-y-1"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="flex items-center gap-1.5 text-sm font-medium text-[#22A06B]"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path d="M5 12l4 4L19 6" />
                        </svg>
                        Password updated successfully.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
