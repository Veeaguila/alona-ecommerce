<script setup>
import { computed } from 'vue'

/*
|--------------------------------------------------------------------------
| Simple line icons
|--------------------------------------------------------------------------
|
| Minimal outline icons (24x24, stroke based) used in place of emoji.
| Usage: <Icon name="bag" class="h-5 w-5" />
|
*/

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
})

const icons = {
    bag: ['M6 7h12l1 13H5L6 7Z', 'M9 7a3 3 0 0 1 6 0'],
    tag: ['M3 12V4h8l10 10-8 8L3 12Z', 'M7.5 8.5h.01'],
    ticket: ['M3 8a2 2 0 0 0 0 4v0a2 2 0 0 1 0 4v2h18v-2a2 2 0 0 1 0-4v0a2 2 0 0 0 0-4V6H3v2Z', 'M13 6v12'],
    check: ['m5 12 5 5L20 7'],
    clock: ['M12 7v5l3 2', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z'],
    flame: ['M12 3c1 3 5 5 5 10a5 5 0 0 1-10 0c0-2 1-3 2-4 0 2 1 3 2 3 0-3-1-6 1-9Z'],
    star: ['m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z'],
    sparkle: ['M12 3v4', 'M12 17v4', 'M3 12h4', 'M17 12h4'],
    store: ['M4 9l1-5h14l1 5', 'M4 9a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0', 'M5 12v8h14v-8'],
    grid: ['M4 4h6v6H4V4Z', 'M14 4h6v6h-6V4Z', 'M4 14h6v6H4v-6Z', 'M14 14h6v6h-6v-6Z'],
    package: ['M21 8 12 3 3 8v8l9 5 9-5V8Z', 'm3 8 9 5 9-5', 'M12 13v8'],
    'arrow-right': ['M5 12h14', 'm13 6 6 6-6 6'],
    'arrow-left': ['M19 12H5', 'm11 6-6 6 6 6'],
    truck: ['M3 6h11v10H3V6Z', 'M14 10h4l3 3v3h-7v-6Z', 'M7 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z', 'M17 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z'],
    shield: ['M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z', 'm9 12 2 2 4-4'],
    help: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5', 'M12 17h.01'],
    bell: ['M6 9a6 6 0 1 1 12 0c0 6 2 7 2 7H4s2-1 2-7Z', 'M10 20a2 2 0 0 0 4 0'],
    message: ['M4 5h16v11H9l-5 4V5Z'],
    cart: ['M3 4h2l2 12h11l2-8H6', 'M9 20h.01', 'M17 20h.01'],
    heart: ['M12 20s-8-5-8-11a4.5 4.5 0 0 1 8-2.5A4.5 4.5 0 0 1 20 9c0 6-8 11-8 11Z'],
    user: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M4 21a8 8 0 0 1 16 0'],
    card: ['M3 6h18v12H3V6Z', 'M3 10h18'],
    receipt: ['M6 3h12v18l-3-2-3 2-3-2-3 2V3Z', 'M9 8h6', 'M9 12h6'],
    undo: ['M9 14 4 9l5-5', 'M4 9h10a6 6 0 0 1 0 12h-3'],
    flag: ['M5 21V4', 'M5 4h12l-2 4 2 4H5'],
    warning: ['M12 3 2 20h20L12 3Z', 'M12 10v4', 'M12 17h.01'],
    gift: ['M4 11h16v9H4v-9Z', 'M3 7h18v4H3V7Z', 'M12 7v13', 'M12 7C9 7 8 3.5 10.5 3.5 12 3.5 12 7 12 7Zm0 0c3 0 4-3.5 1.5-3.5C12 3.5 12 7 12 7Z'],
    search: ['M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z', 'm21 21-4.5-4.5'],
    mail: ['M3 5h18v14H3V5Z', 'm3 6 9 7 9-7'],
    phone: ['M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z'],
    pin: ['M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11Z', 'M12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z'],
    lock: ['M5 11h14v10H5V11Z', 'M8 11V8a4 4 0 0 1 8 0v3'],
    book: ['M4 4h7a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H4V4Z', 'M20 4h-6v14h6V4Z'],
    camera: ['M3 8h4l2-3h6l2 3h4v12H3V8Z', 'M12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
    thumb: ['M7 11v9H3v-9h4Z', 'M7 11l4-8a2.5 2.5 0 0 1 2.5 3L13 9h6a2 2 0 0 1 2 2.3l-1.2 7A2 2 0 0 1 17.800 20H7'],
    x: ['M6 6l12 12', 'M18 6 6 18'],
    info: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 11v5', 'M12 8h.01'],
}

const paths = computed(() => icons[props.name] ?? icons.bag)
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path v-for="(d, i) in paths" :key="i" :d="d" />
    </svg>
</template>

