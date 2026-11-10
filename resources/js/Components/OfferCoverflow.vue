<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps({
    items: { type: Array, default: () => [] },
    label: { type: String, default: 'Special offers' },
})

const root = ref(null)
const activeIndex = ref(0)
const hovered = ref(false)
const focused = ref(false)
const dragging = ref(false)
const reducedMotion = ref(false)
const paused = ref(false)
let timer
let motionQuery
let pointerStart = null
let suppressClick = false
let lastInteraction = 0

const activeItem = computed(() => props.items[activeIndex.value])
const offsetFor = index => {
    const count = props.items.length
    if (!count) return 0
    let offset = (index - activeIndex.value + count) % count
    if (offset > count / 2) offset -= count
    return offset
}

const cardStyle = index => {
    const offset = offsetFor(index)
    const distance = Math.abs(offset)
    return {
        transform: `translateX(calc(-50% + ${offset * 68}%)) translateZ(${-distance * 75}px) rotateY(${offset === 0 ? 0 : -Math.sign(offset) * 48}deg) scale(${1 - Math.min(distance, 3) * 0.08})`,
        zIndex: 10 - Math.min(distance, 3),
        opacity: distance > 2 ? 0 : 1 - distance * 0.14,
        visibility: distance > 2 ? 'hidden' : 'visible',
        pointerEvents: distance > 2 ? 'none' : 'auto',
    }
}

const select = index => {
    if (!props.items.length) return
    activeIndex.value = (index + props.items.length) % props.items.length
    lastInteraction = Date.now()
}
const next = () => select(activeIndex.value + 1)
const previous = () => select(activeIndex.value - 1)

const onKeydown = event => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
    event.preventDefault()
    if (event.key === 'ArrowLeft') previous()
    if (event.key === 'ArrowRight') next()
    if (event.key === 'Home') select(0)
    if (event.key === 'End') select(props.items.length - 1)
    // Keep keyboard navigation on the carousel when the focused link moves aside.
    root.value?.focus({ preventScroll: true })
}

const onPointerDown = event => {
    if (!event.isPrimary || (event.pointerType === 'mouse' && event.button !== 0)) return
    pointerStart = { x: event.clientX, y: event.clientY }
    dragging.value = true
    suppressClick = false
}
const onPointerUp = event => {
    if (!pointerStart) return
    const dx = event.clientX - pointerStart.x
    const dy = event.clientY - pointerStart.y
    if (Math.abs(dx) > 36 && Math.abs(dx) > Math.abs(dy) * 1.2) {
        suppressClick = true
        dx < 0 ? next() : previous()
    }
    pointerStart = null
    dragging.value = false
}
const cancelDrag = () => { pointerStart = null; dragging.value = false }
const onClick = event => {
    if (!suppressClick) return
    event.preventDefault()
    event.stopPropagation()
    suppressClick = false
}
const onMotionChange = event => { reducedMotion.value = event.matches }

watch(() => props.items.length, count => { activeIndex.value = Math.min(activeIndex.value, Math.max(0, count - 1)) })

onMounted(() => {
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
    reducedMotion.value = motionQuery.matches
    motionQuery.addEventListener('change', onMotionChange)
    timer = window.setInterval(() => {
        if (props.items.length > 1 && !paused.value && !hovered.value && !focused.value && !dragging.value
            && !reducedMotion.value && !document.hidden && Date.now() - lastInteraction >= 4500) next()
    }, 4500)
    window.addEventListener('pointerup', onPointerUp)
    window.addEventListener('pointercancel', cancelDrag)
})
onUnmounted(() => {
    window.clearInterval(timer)
    motionQuery?.removeEventListener('change', onMotionChange)
    window.removeEventListener('pointerup', onPointerUp)
    window.removeEventListener('pointercancel', cancelDrag)
})

defineExpose({ next, previous })
</script>

<template>
    <div
        ref="root"
        class="offer-coverflow"
        role="region"
        aria-roledescription="carousel"
        :aria-label="label"
        tabindex="0"
        @keydown="onKeydown"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false"
        @focusin="focused = true"
        @focusout="focused = Boolean($event.relatedTarget && root?.contains($event.relatedTarget))"
        @pointerdown="onPointerDown"
        @click.capture="onClick"
        @dragstart.prevent
    >
        <div class="offer-coverflow__stage">
            <div
                v-for="(item, index) in items"
                :key="item.id"
                class="offer-coverflow__slide"
                :class="{ 'is-active': index === activeIndex }"
                :style="cardStyle(index)"
                role="group"
                aria-roledescription="slide"
                :aria-label="`${index + 1} of ${items.length}`"
                :aria-hidden="Math.abs(offsetFor(index)) > 2 || undefined"
            >
                <div class="offer-coverflow__card" :inert="index !== activeIndex">
                    <slot :item="item" :active="index === activeIndex" />
                </div>
                <button
                    v-if="index !== activeIndex && Math.abs(offsetFor(index)) <= 2"
                    type="button"
                    class="offer-coverflow__select"
                    :aria-label="`Show ${item.name || `offer ${index + 1}`}`"
                    @click="select(index)"
                ></button>
            </div>
        </div>
        <div v-if="items.length > 1" class="offer-coverflow__pagination" aria-label="Choose an offer">
            <button
                v-if="!reducedMotion"
                type="button"
                class="offer-coverflow__pause"
                :aria-label="paused ? 'Resume carousel animation' : 'Pause carousel animation'"
                :title="paused ? 'Resume animation' : 'Pause animation'"
                :aria-pressed="paused"
                @click="paused = !paused"
            >
                <svg v-if="paused" viewBox="0 0 16 16" width="12" height="12" fill="currentColor" aria-hidden="true"><path d="M4 2.5v11L13 8z" /></svg>
                <svg v-else viewBox="0 0 16 16" width="12" height="12" fill="currentColor" aria-hidden="true"><path d="M4 3h3v10H4zm5 0h3v10H9z" /></svg>
            </button>
            <button
                v-for="(item, index) in items"
                :key="item.id"
                type="button"
                :aria-label="`Show ${item.name || `offer ${index + 1}`}`"
                :aria-current="index === activeIndex ? 'true' : undefined"
                @click="select(index)"
            ><span :class="{ 'is-active': index === activeIndex }"></span></button>
        </div>
        <span class="sr-only" :aria-live="focused ? 'polite' : 'off'">{{ activeItem?.name }}</span>
    </div>
</template>

<style scoped>
.offer-coverflow {
    --offer-card-height: 242px;
    position: relative;
    min-width: 0;
    width: 100%;
    margin-top: 10px;
    overflow: hidden;
    border-radius: 16px;
    touch-action: pan-y pinch-zoom;
}
.offer-coverflow:focus-visible { outline: 2px solid #087f8c; outline-offset: 3px; }
.offer-coverflow__stage {
    position: relative;
    height: calc(var(--offer-card-height) + 62px);
    perspective: 950px;
    perspective-origin: 50% 43%;
    isolation: isolate;
}
.offer-coverflow__slide {
    position: absolute;
    top: 12px;
    left: 50%;
    width: min(55%, 236px);
    height: var(--offer-card-height);
    transform-origin: center;
    transition: transform 650ms cubic-bezier(.22,.68,.18,1), opacity 650ms ease, visibility 650ms;
    will-change: transform;
    -webkit-box-reflect: below 8px linear-gradient(transparent 80%, rgb(0 0 0 / 10%));
}
.offer-coverflow__card {
    height: 100%;
    overflow: hidden;
    border: 1px solid rgb(255 255 255 / 95%);
    border-radius: 16px;
    background: white;
    box-shadow: 0 10px 20px -12px rgb(15 23 42 / 40%), 0 0 0 1px rgb(8 127 140 / 13%);
    transition: box-shadow 650ms ease;
}
.is-active .offer-coverflow__card { box-shadow: 0 16px 26px -14px rgb(8 127 140 / 35%), 0 0 0 1px rgb(8 127 140 / 20%); }
.offer-coverflow__select { position: absolute; inset: 0; width: 100%; border-radius: 16px; cursor: pointer; background: transparent; }
.offer-coverflow__select:focus-visible { outline: 3px solid #087f8c; outline-offset: 2px; }
.offer-coverflow__pagination { display: flex; justify-content: center; flex-wrap: wrap; padding: 0 12px 2px; }
.offer-coverflow__pagination button { display: grid; width: 24px; height: 26px; place-items: center; }
.offer-coverflow__pause { color: #64748b; }
.offer-coverflow__pagination span { height: 5px; width: 5px; border-radius: 999px; background: #cbd5e1; transition: width 300ms, background 300ms; }
.offer-coverflow__pagination .is-active { width: 18px; background: #087f8c; }
.offer-coverflow__pagination button:focus-visible { outline: 2px solid #087f8c; border-radius: 6px; }
@media (max-width: 380px) {
    .offer-coverflow { --offer-card-height: 236px; }
    .offer-coverflow__slide { width: 60%; }
}
@media (prefers-reduced-motion: reduce) {
    .offer-coverflow__slide, .offer-coverflow__card, .offer-coverflow__pagination span { transition: none; }
}
</style>
