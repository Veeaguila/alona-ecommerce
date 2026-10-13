<script setup>
defineProps({ modelValue: [String, Number, Array, File], name: String, label: String, type: { type: String, default: 'text' }, options: Array, error: String, required: Boolean, disabled: Boolean })
defineEmits(['update:modelValue'])
</script>
<template>
    <div>
        <label :for="name" class="mb-1.5 block text-sm font-semibold text-[#1F2937]">{{ label }} <span v-if="required" class="text-[#087F8C]">*</span></label>
        <select v-if="options" :id="name" :value="modelValue" :required="required" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value)" class="partner-input">
            <option value="">Choose an option</option><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <input v-else :id="name" :type="type" :value="type === 'file' ? undefined : modelValue" :required="required" :disabled="disabled" :accept="type === 'file' ? '.jpg,.jpeg,.png,.pdf' : undefined" :autocomplete="type === 'password' ? 'new-password' : undefined" :aria-invalid="!!error" :aria-describedby="error ? name + '-error' : undefined" @input="$emit('update:modelValue', type === 'file' ? $event.target.files[0] : $event.target.value)" class="partner-input" />
        <p v-if="error" :id="name + '-error'" role="alert" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
<style scoped>
.partner-input { width:100%; border:1px solid #e5e7eb; border-radius:12px; padding:11px 12px; font-size:14px; background:#fff; }
.partner-input:focus { outline:2px solid #087f8c; outline-offset:2px; }
.partner-input:disabled { background:#f8faf9; color:#64748b; }
</style>
