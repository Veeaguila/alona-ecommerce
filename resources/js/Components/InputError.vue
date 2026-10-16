<!-- Validation message displayed below a form field. -->
<script setup>
import { computed } from 'vue'

const props = defineProps({
    message: {
        type: [String, Array, Object],
        default: '',
    },
});

const cleanMessage = (msg) => {
    if (!msg) return ''
    if (Array.isArray(msg)) {
        msg = msg[0] || ''
    }
    if (typeof msg === 'object') {
        const first = Object.values(msg)[0]
        msg = Array.isArray(first) ? first[0] : first
    }
    let str = String(msg || '').trim()
    if (str.startsWith('["') && str.endsWith('"]')) {
        str = str.slice(2, -2)
    } else if (str.startsWith('[') && str.endsWith(']')) {
        str = str.slice(1, -1).trim()
        if (str.startsWith('"') && str.endsWith('"')) {
            str = str.slice(1, -1)
        }
    }
    return str
}

const formattedMessage = computed(() => cleanMessage(props.message))
</script>

<template>
    <div
        v-show="formattedMessage"
        class="rounded-2xl bg-[#FEF2F2] px-5 py-4 text-sm leading-relaxed text-[#DC2626]"
        role="alert"
    >
        {{ formattedMessage }}
    </div>
</template>
