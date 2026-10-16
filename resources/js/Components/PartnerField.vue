<script setup>
import { ref, watch } from 'vue'
const revealed = ref(false)
const upload = ref(null)
const props = defineProps({ modelValue: [String, Number, Array, File], name: String, label: String, type: { type: String, default: 'text' }, options: Array, error: String, required: Boolean, disabled: Boolean })
defineEmits(['update:modelValue'])
watch(() => props.modelValue, value => {
    if (props.type === 'file' && value == null && upload.value) upload.value.value = ''
})
</script>
<template>
    <div>
        <label :for="name" class="mb-1.5 block text-sm font-semibold text-[#1F2937]">{{ label }} <span v-if="required" class="text-[#087F8C]">*</span></label>
        <select v-if="options" :id="name" :value="modelValue" :required="required" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value)" class="partner-input">
            <option value="">Choose an option</option><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <input v-else-if="type === 'file'" :id="name" ref="upload" type="file" :required="required" :disabled="disabled" accept=".jpg,.jpeg,.png,.pdf" :aria-invalid="!!error" :aria-describedby="error ? name + '-error' : undefined" @input="$emit('update:modelValue', $event.target.files[0])" class="partner-input" />
        <input v-else :id="name" :type="type === 'password' && revealed ? 'text' : type" :value="modelValue" :required="required" :disabled="disabled" :autocomplete="type === 'password' ? 'new-password' : undefined" :aria-invalid="!!error" :aria-describedby="error ? name + '-error' : undefined" @input="$emit('update:modelValue', $event.target.value)" class="partner-input" />
        <button v-if="type === 'password'" type="button" :aria-controls="name" :aria-pressed="revealed" @click="revealed = !revealed" class="mt-1 text-sm font-semibold text-[#087F8C] focus:outline-2 focus:outline-offset-2">{{ revealed ? 'Hide password' : 'Show password' }}</button>
        <p v-if="type === 'file' && modelValue" class="mt-1 break-all text-sm text-[#64748B]">{{ modelValue.name }}</p>
        <div v-if="error" :id="name + '-error'" role="alert" class="mt-2 rounded-2xl bg-[#FEF2F2] px-5 py-4 text-sm leading-relaxed text-[#DC2626]">{{ error }}</div>
    </div>
</template>
<style scoped>
.partner-input { width:100%; border:1px solid #e5e7eb; border-radius:12px; padding:11px 12px; font-size:14px; background:#fff; }
.partner-input:focus { outline:2px solid #087f8c; outline-offset:2px; }
.partner-input:disabled { background:#f8faf9; color:#64748b; }
</style>
