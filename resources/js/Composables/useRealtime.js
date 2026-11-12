import { onMounted, onUnmounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { initAlonaRealtime } from '@/realtime/echo';

/**
 * Shared composable for real-time WebSocket / Reverb subscriptions,
 * automatic cleanup on unmount, debounced Inertia partial reloads,
 * and server-authoritative countdown clocks.
 */
export function useRealtime() {
    const echo = initAlonaRealtime();
    const subscriptions = [];
    const domHandlers = [];
    let reloadTimeout = null;
    const isReloading = ref(false);

    try {
        const page = usePage();
        const serverTimeMs = page?.props?.realtimeConfig?.server_time_ms;
        if (echo && serverTimeMs) {
            echo.updateServerClock(Number(serverTimeMs));
        }
    } catch (_) {
        // Component may be outside Inertia context (e.g., standalone Logistics Vue root)
    }

    function listen(channelName, eventName, callback, isPrivate = true) {
        if (!echo || !channelName) return () => {};
        const ch = isPrivate && channelName !== 'catalog.global'
            ? echo.private(channelName)
            : echo.channel(channelName);

        ch.listen(eventName, callback);
        const unsub = () => ch.stopListening(eventName, callback);
        subscriptions.push(unsub);
        return unsub;
    }

    function listenPublic(channelName, eventName, callback) {
        return listen(channelName, eventName, callback, false);
    }

    function onGlobalEvent(callback) {
        if (typeof window === 'undefined' || typeof callback !== 'function') return () => {};
        const handler = (e) => callback(e.detail);
        window.addEventListener('alona:realtime-event', handler);
        const remove = () => window.removeEventListener('alona:realtime-event', handler);
        domHandlers.push(remove);
        return remove;
    }

    function onLogisticsEvent(callback) {
        if (typeof window === 'undefined' || typeof callback !== 'function') return () => {};
        const handler = (e) => callback(e.detail);
        window.addEventListener('alona:logistics-realtime', handler);
        const remove = () => window.removeEventListener('alona:logistics-realtime', handler);
        domHandlers.push(remove);
        return remove;
    }

    function onStateUpdate(callback) {
        if (!echo) return () => {};
        const unsub = echo.onStateUpdate(callback);
        subscriptions.push(unsub);
        return unsub;
    }

    function onReconnect(callback) {
        if (!echo) return () => {};
        const unsub = echo.onReconnect(callback);
        subscriptions.push(unsub);
        return unsub;
    }

    /**
     * Debounced Inertia partial reload so burst events coalesce into a single seamless prop refresh
     * without resetting scroll position or form inputs.
     */
    function reloadProps(only = [], delayMs = 350) {
        if (typeof window === 'undefined') return;
        clearTimeout(reloadTimeout);
        reloadTimeout = setTimeout(() => {
            if (isReloading.value) return;
            isReloading.value = true;
            const options = {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    isReloading.value = false;
                },
            };
            if (Array.isArray(only) && only.length > 0) {
                options.only = only;
            }
            try {
                router.reload(options);
            } catch (_) {
                isReloading.value = false;
            }
        }, delayMs);
    }

    function getAuthoritativeNowMs() {
        return echo ? echo.getAuthoritativeNowMs() : Date.now();
    }

    async function fetchCatalogState(productIds = []) {
        if (!echo) return null;
        return echo.fetchCatalogState(productIds);
    }

    onUnmounted(() => {
        clearTimeout(reloadTimeout);
        while (subscriptions.length > 0) {
            const unsub = subscriptions.pop();
            try {
                unsub();
            } catch (_) {
                // Ignore cleanup errors
            }
        }
        while (domHandlers.length > 0) {
            const remove = domHandlers.pop();
            try {
                remove();
            } catch (_) {
                // Ignore cleanup errors
            }
        }
    });

    return {
        echo,
        state: echo?.state || null,
        isReloading,
        listen,
        listenPublic,
        onGlobalEvent,
        onLogisticsEvent,
        onStateUpdate,
        onReconnect,
        reloadProps,
        getAuthoritativeNowMs,
        fetchCatalogState,
    };
}

/**
 * Composable for live server-authoritative countdown timers (Flash Sales & Bundle Deals)
 * without sending WebSocket messages every second.
 */
export function useServerCountdown(endsAtGetter) {
    const { getAuthoritativeNowMs } = useRealtime();
    const remainingMs = ref(0);
    const isExpired = ref(false);
    const formatted = ref('00:00:00');
    let timerId = null;

    function tick() {
        const rawTarget = typeof endsAtGetter === 'function' ? endsAtGetter() : endsAtGetter;
        if (!rawTarget) {
            remainingMs.value = 0;
            isExpired.value = false;
            formatted.value = '';
            return;
        }

        const targetMs = new Date(rawTarget).getTime();
        if (Number.isNaN(targetMs)) {
            remainingMs.value = 0;
            return;
        }

        const diff = Math.max(0, targetMs - getAuthoritativeNowMs());
        remainingMs.value = diff;
        isExpired.value = diff <= 0;

        const totalSeconds = Math.floor(diff / 1000);
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;

        const pad = (n) => String(n).padStart(2, '0');
        formatted.value = days > 0
            ? `${days}d ${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
            : `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    }

    onMounted(() => {
        tick();
        timerId = setInterval(tick, 1000);
    });

    onUnmounted(() => {
        if (timerId) clearInterval(timerId);
    });

    return {
        remainingMs,
        isExpired,
        formatted,
        tick,
    };
}
