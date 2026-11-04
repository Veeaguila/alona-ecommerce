<script setup>
import { ref, watch } from 'vue'
const revealed = ref(false)
const upload = ref(null)
const props = defineProps({
    modelValue: [String, Number, Array, File],
    name: String,
    label: String,
    type: { type: String, default: 'text' },
    options: Array,
    error: String,
    required: Boolean,
    disabled: Boolean,
    dark: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])
watch(() => props.modelValue, value => {
    if (props.type === 'file' && value == null && upload.value) upload.value.value = ''
})
</script>
<template>
    <div>
        <label
            :for="name"
            :class="[
                'mb-1.5 block text-sm font-semibold',
                dark ? 'text-white' : 'text-[#1F2937]'
            ]"
        >
            {{ label }} <span v-if="required" :class="dark ? 'text-[#F5A524]' : 'text-[#087F8C]'">*</span>
        </label>
        <select
            v-if="options"
            :id="name"
            :value="modelValue"
            :required="required"
            :disabled="disabled"
            @change="$emit('update:modelValue', $event.target.value)"
            :class="[
                dark
                    ? 'w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 shadow-inner outline-none transition focus:ring-2 focus:ring-[#F5A524]'
                    : 'partner-input'
            ]"
        >
            <option value="">Choose an option</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <input
            v-else-if="type === 'file'"
            :id="name"
            ref="upload"
            type="file"
            :required="required"
            :disabled="disabled"
            accept=".jpg,.jpeg,.png,.pdf"
            :aria-invalid="!!error"
            :aria-describedby="error ? name + '-error' : undefined"
            @input="$emit('update:modelValue', $event.target.files[0])"
            :class="[
                dark
                    ? 'w-full rounded-xl border-0 bg-white px-4 py-2.5 text-xs text-slate-900 file:mr-3 file:rounded-lg file:border-0 file:bg-[#F5A524] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-slate-900 shadow-inner outline-none transition focus:ring-2 focus:ring-[#F5A524]'
                    : 'partner-input'
            ]"
        />
        <div v-else class="relative">
            <input
                :id="name"
                :type="type === 'password' && revealed ? 'text' : type"
                :value="modelValue"
                :required="required"
                :disabled="disabled"
                :autocomplete="type === 'password' ? 'new-password' : undefined"
                :aria-invalid="!!error"
                :aria-describedby="error ? name + '-error' : undefined"
                @input="$emit('update:modelValue', $event.target.value)"
                :class="[
                    dark
                        ? 'w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 shadow-inner outline-none transition focus:ring-2 focus:ring-[#F5A524]'
                        : 'partner-input',
                    type === 'password' ? 'pr-11' : ''
                ]"
            />
            <button
                v-if="type === 'password'"
                type="button"
                :aria-controls="name"
                :aria-pressed="revealed"
                @click="revealed = !revealed"
                class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 focus:outline-none"
            >
                <svg v-if="revealed" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
            </button>
        </div>
        <p v-if="type === 'file' && modelValue" :class="['mt-1 break-all text-xs', dark ? 'text-teal-200' : 'text-[#64748B]']">{{ modelValue.name }}</p>
        <div v-if="error" :id="name + '-error'" role="alert" class="mt-2 rounded-xl bg-rose-50 border border-rose-200 px-4 py-2.5 text-xs font-semibold leading-relaxed text-rose-800 shadow-sm">{{ error }}</div>
    </div>
</template>
<style scoped>
.partner-input { width:100%; border:1px solid #e5e7eb; border-radius:12px; padding:11px 12px; font-size:14px; background:#fff; }
.partner-input:focus { outline:2px solid #087f8c; outline-offset:2px; }
.partner-input:disabled { background:#f8faf9; color:#64748b; }
</style>
