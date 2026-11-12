import { reactive, readonly } from 'vue';

const MAX_SEEN_EVENTS = 500;

class AlonaEchoChannel {
    constructor(client, rawName, isPrivate = false) {
        this.client = client;
        this.rawName = rawName;
        this.normalizedName = rawName.replace(/^(private-|presence-)/, '');
        this.wireName = isPrivate && !rawName.startsWith('private-') && !rawName.startsWith('presence-')
            ? `private-${this.normalizedName}`
            : rawName;
        this.isPrivate = isPrivate;
        this.listeners = new Map();
        this.notificationListeners = new Set();
        this.authorized = !isPrivate;
        this.subscribing = false;
    }

    listen(eventName, callback) {
        if (typeof callback !== 'function') return this;
        const normalizedEvent = String(eventName || '').replace(/^\./, '');
        if (!this.listeners.has(normalizedEvent)) {
            this.listeners.set(normalizedEvent, new Set());
        }
        this.listeners.get(normalizedEvent).add(callback);
        this.client.ensureSubscribed(this);
        return this;
    }

    stopListening(eventName, callback = null) {
        const normalizedEvent = String(eventName || '').replace(/^\./, '');
        if (!this.listeners.has(normalizedEvent)) return this;
        if (!callback) {
            this.listeners.delete(normalizedEvent);
        } else {
            this.listeners.get(normalizedEvent).delete(callback);
            if (this.listeners.get(normalizedEvent).size === 0) {
                this.listeners.delete(normalizedEvent);
            }
        }
        return this;
    }

    notification(callback) {
        if (typeof callback === 'function') {
            this.notificationListeners.add(callback);
            this.listen('notification.created', callback);
            this.listen('Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', callback);
        }
        return this;
    }

    stopListeningForNotification(callback) {
        this.notificationListeners.delete(callback);
        this.stopListening('notification.created', callback);
        this.stopListening('Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', callback);
        return this;
    }

    dispatch(eventName, payload) {
        const cleanEvent = String(eventName || '').replace(/^\./, '');
        const callbacks = this.listeners.get(cleanEvent);
        if (callbacks) {
            callbacks.forEach((cb) => {
                try {
                    cb(payload);
                } catch (err) {
                    console.warn('[AlonaEcho] Listener error on', this.normalizedName, cleanEvent, err);
                }
            });
        }

        const wildcardCallbacks = this.listeners.get('*');
        if (wildcardCallbacks) {
            wildcardCallbacks.forEach((cb) => {
                try {
                    cb(cleanEvent, payload);
                } catch (err) {
                    console.warn('[AlonaEcho] Wildcard listener error on', this.normalizedName, err);
                }
            });
        }
    }

    hasActiveListeners() {
        for (const set of this.listeners.values()) {
            if (set.size > 0) return true;
        }
        return false;
    }
}

export class AlonaEchoClient {
    constructor(options = {}) {
        this.options = {
            broadcaster: 'reverb',
            key: options.key || import.meta.env.VITE_REVERB_APP_KEY || 'alona-reverb-key',
            wsHost: options.wsHost || import.meta.env.VITE_REVERB_HOST || window.location.hostname || '127.0.0.1',
            wsPort: Number(options.wsPort || import.meta.env.VITE_REVERB_PORT || 8080),
            wssPort: Number(options.wssPort || import.meta.env.VITE_REVERB_PORT || 8080),
            forceTLS: (options.scheme || import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
            authEndpoint: options.authEndpoint || '/broadcasting/auth',
            syncEndpoint: options.syncEndpoint || '/realtime/sync',
            catalogEndpoint: options.catalogEndpoint || '/realtime/catalog-state',
        };

        this.channels = new Map();
        this.seenEventIds = [];
        this.seenEventSet = new Set();
        this.cursor = 0;
        this.ws = null;
        this.socketIdValue = null;
        this.wsReconnectAttempts = 0;
        this.wsReconnectTimer = null;
        this.syncTimer = null;
        this.syncInFlight = false;
        this.reconnectCallbacks = new Set();
        this.stateCallbacks = new Set();

        this.state = reactive({
            status: navigator.onLine ? 'connecting' : 'offline', // 'connected' | 'syncing' | 'reconnecting' | 'offline'
            transport: 'sync', // 'websocket' | 'sync'
            socketId: null,
            serverTimeOffsetMs: 0,
            lastSyncedAt: null,
            cursor: 0,
            roleState: {},
        });

        this.connector = {
            socketId: () => this.socketIdValue,
            channels: this.channels,
        };

        this.initLifecycle();
    }

    initLifecycle() {
        if (typeof window === 'undefined') return;

        // Subscribe to global catalog updates by default
        this.channel('catalog.global');

        window.addEventListener('online', () => {
            this.state.status = 'reconnecting';
            this.connectWebSocket();
            this.syncNow(true);
        });

        window.addEventListener('offline', () => {
            this.state.status = 'offline';
        });

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.syncNow(false);
            }
        });

        // Start immediate authoritative sync and start interval loop
        setTimeout(() => {
            this.syncNow(false);
            this.connectWebSocket();
        }, 120);

        this.syncTimer = setInterval(() => {
            if (!navigator.onLine) return;
            this.syncNow(false);
        }, 2000);
    }

    socketId() {
        return this.socketIdValue;
    }

    getAuthoritativeNowMs() {
        return Date.now() + (this.state.serverTimeOffsetMs || 0);
    }

    updateServerClock(serverTimeMs) {
        if (typeof serverTimeMs === 'number' && Number.isFinite(serverTimeMs) && serverTimeMs > 0) {
            this.state.serverTimeOffsetMs = serverTimeMs - Date.now();
        }
    }

    channel(name) {
        const normalized = String(name || '').replace(/^(private-|presence-)/, '');
        if (!this.channels.has(normalized)) {
            const ch = new AlonaEchoChannel(this, normalized, false);
            this.channels.set(normalized, ch);
        }
        return this.channels.get(normalized);
    }

    private(name) {
        const normalized = String(name || '').replace(/^(private-|presence-)/, '');
        if (!this.channels.has(normalized)) {
            const ch = new AlonaEchoChannel(this, normalized, true);
            this.channels.set(normalized, ch);
        }
        const ch = this.channels.get(normalized);
        ch.isPrivate = true;
        return ch;
    }

    join(name) {
        return this.private(name);
    }

    leave(name) {
        const normalized = String(name || '').replace(/^(private-|presence-)/, '');
        const ch = this.channels.get(normalized);
        if (ch && this.ws && this.ws.readyState === WebSocket.OPEN) {
            try {
                this.ws.send(JSON.stringify({
                    event: 'pusher:unsubscribe',
                    data: { channel: ch.wireName },
                }));
            } catch (_) {
                // Ignore WebSocket send errors during teardown
            }
        }
        this.channels.delete(normalized);
    }

    leaveChannel(name) {
        this.leave(name);
    }

    onReconnect(callback) {
        if (typeof callback === 'function') {
            this.reconnectCallbacks.add(callback);
        }
        return () => this.reconnectCallbacks.delete(callback);
    }

    onStateUpdate(callback) {
        if (typeof callback === 'function') {
            this.stateCallbacks.add(callback);
        }
        return () => this.stateCallbacks.delete(callback);
    }

    getAuthHeaders() {
        const headers = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrf) {
            headers['X-CSRF-TOKEN'] = csrf;
        }

        const logisticsToken = window.logisticsSession?.token || localStorage.getItem('logistics_auth_token');
        if (logisticsToken) {
            headers.Authorization = `Bearer ${logisticsToken}`;
            headers['X-Logistics-Token'] = logisticsToken;
        }

        return headers;
    }

    async authorizeChannel(channel) {
        if (!channel.isPrivate || channel.normalizedName === 'catalog.global') {
            channel.authorized = true;
            return { authorized: true, auth: '' };
        }

        try {
            const res = await fetch(this.options.authEndpoint, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({
                    socket_id: this.socketIdValue || '0.0',
                    channel_name: channel.wireName,
                }),
            });

            if (!res.ok) {
                channel.authorized = false;
                return { authorized: false };
            }

            const data = await res.json();
            channel.authorized = true;
            return { authorized: true, ...data };
        } catch (_) {
            return { authorized: false };
        }
    }

    async ensureSubscribed(channel) {
        if (channel.subscribing) return;
        channel.subscribing = true;
        try {
            if (this.ws && this.ws.readyState === WebSocket.OPEN && this.socketIdValue) {
                await this.subscribeOverWebSocket(channel);
            }
        } finally {
            channel.subscribing = false;
        }
    }

    async subscribeOverWebSocket(channel) {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) return;

        let authData = {};
        if (channel.isPrivate && channel.normalizedName !== 'catalog.global') {
            const authRes = await this.authorizeChannel(channel);
            if (!authRes.authorized) return;
            authData = {
                auth: authRes.auth,
                ...(authRes.channel_data ? { channel_data: authRes.channel_data } : {}),
            };
        }

        try {
            this.ws.send(JSON.stringify({
                event: 'pusher:subscribe',
                data: {
                    channel: channel.wireName,
                    ...authData,
                },
            }));
        } catch (_) {
            // Ignore WebSocket frame errors
        }
    }

    connectWebSocket() {
        if (typeof WebSocket === 'undefined' || !navigator.onLine) return;
        if (this.ws && (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING)) {
            return;
        }

        // Only open raw WebSocket if explicitly configured or first attempt in local dev
        if (this.wsReconnectAttempts >= 2 && !window.__ALONA_FORCE_WS__) {
            return;
        }

        const protocol = this.options.forceTLS ? 'wss' : 'ws';
        const port = this.options.forceTLS ? this.options.wssPort : this.options.wsPort;
        const wsUrl = `${protocol}://${this.options.wsHost}:${port}/app/${this.options.key}?protocol=7&client=js&version=8.4.0&flash=false`;

        try {
            const socket = new WebSocket(wsUrl);
            this.ws = socket;

            socket.onopen = () => {
                this.wsReconnectAttempts = 0;
                this.state.transport = 'websocket';
                this.state.status = 'connected';
            };

            socket.onmessage = (msgEvent) => {
                try {
                    const frame = JSON.parse(msgEvent.data);
                    const eventName = frame.event;
                    const data = typeof frame.data === 'string' ? JSON.parse(frame.data || '{}') : (frame.data || {});

                    if (eventName === 'pusher:connection_established') {
                        this.socketIdValue = data.socket_id || '1.1';
                        this.state.socketId = this.socketIdValue;
                        this.state.status = 'connected';
                        this.state.transport = 'websocket';
                        this.channels.forEach((ch) => this.subscribeOverWebSocket(ch));
                        return;
                    }

                    if (eventName === 'pusher:ping') {
                        socket.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
                        return;
                    }

                    if (frame.channel && eventName && !eventName.startsWith('pusher:')) {
                        this.processIncomingEvent({
                            channel: frame.channel.replace(/^(private-|presence-)/, ''),
                            event: eventName,
                            event_id: data.event_id || null,
                            seq: data.seq || null,
                            data,
                            server_time_ms: data.server_time_ms || null,
                        });
                    }
                } catch (_) {
                    // Ignore malformed frames
                }
            };

            socket.onerror = () => {
                // Handled gracefully by sync engine
            };

            socket.onclose = () => {
                this.ws = null;
                this.socketIdValue = null;
                this.state.socketId = null;
                this.state.transport = 'sync';
                this.wsReconnectAttempts += 1;

                if (this.wsReconnectAttempts < 2) {
                    const delay = Math.min(15000, 2000 * Math.pow(2, this.wsReconnectAttempts));
                    clearTimeout(this.wsReconnectTimer);
                    this.wsReconnectTimer = setTimeout(() => this.connectWebSocket(), delay);
                }
            };
        } catch (_) {
            this.state.transport = 'sync';
        }
    }

    hasSeenEvent(eventId) {
        if (!eventId) return false;
        return this.seenEventSet.has(String(eventId));
    }

    markEventSeen(eventId) {
        if (!eventId) return;
        const key = String(eventId);
        if (this.seenEventSet.has(key)) return;
        this.seenEventSet.add(key);
        this.seenEventIds.push(key);
        if (this.seenEventIds.length > MAX_SEEN_EVENTS) {
            const oldest = this.seenEventIds.shift();
            if (oldest) this.seenEventSet.delete(oldest);
        }
    }

    processIncomingEvent(envelope) {
        if (!envelope || typeof envelope !== 'object') return;
        const eventId = envelope.event_id || envelope.data?.event_id;
        if (eventId && this.hasSeenEvent(eventId)) {
            return; // Deduplicated!
        }
        if (eventId) {
            this.markEventSeen(eventId);
        }

        if (envelope.seq && Number(envelope.seq) > this.cursor) {
            this.cursor = Number(envelope.seq);
            this.state.cursor = this.cursor;
        }

        if (envelope.server_time_ms) {
            this.updateServerClock(Number(envelope.server_time_ms));
        }

        const channelName = String(envelope.channel || '').replace(/^(private-|presence-)/, '');
        const eventName = String(envelope.event || '');
        const payload = envelope.data || envelope;

        const channelObj = this.channels.get(channelName);
        if (channelObj) {
            channelObj.dispatch(eventName, payload);
        }

        // Dispatch global DOM events for decoupled listeners
        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('alona:realtime-event', {
                detail: {
                    channel: channelName,
                    event: eventName,
                    data: payload,
                },
            }));

            if (
                channelName.startsWith('logistics.') ||
                ['parcel', 'pickup', 'order', 'return', 'finance', 'logistics_chat'].includes(payload?.domain)
            ) {
                window.dispatchEvent(new CustomEvent('alona:logistics-realtime', {
                    detail: {
                        channel: channelName,
                        event: eventName,
                        data: payload,
                    },
                }));
            }
        }
    }

    async syncNow(isReconnect = false) {
        if (this.syncInFlight || !navigator.onLine) return null;
        this.syncInFlight = true;

        try {
            const subscribedChannels = Array.from(this.channels.keys());
            const params = new URLSearchParams();
            params.set('cursor', String(this.cursor || 0));
            if (subscribedChannels.length > 0) {
                params.set('channels', subscribedChannels.join(','));
            }

            const res = await fetch(`${this.options.syncEndpoint}?${params.toString()}`, {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'same-origin',
            });

            if (!res.ok) {
                return null;
            }

            const data = await res.json();
            const wasOfflineOrReconnecting = this.state.status === 'offline' || this.state.status === 'reconnecting' || isReconnect;

            if (data.server_time_ms) {
                this.updateServerClock(Number(data.server_time_ms));
            }

            const isFirstCursorInitialization = this.cursor === 0 && (!Array.isArray(data.events) || data.events.length === 0);
            if (typeof data.cursor === 'number' && data.cursor > this.cursor) {
                if (this.cursor === 0 && Array.isArray(data.events) && data.events.length > 0) {
                    // On very first boot, mark pre-existing historical buffer events as seen without re-triggering page reloads
                    data.events.forEach((ev) => {
                        if (ev?.event_id) this.markEventSeen(ev.event_id);
                    });
                    this.cursor = data.cursor;
                    this.state.cursor = data.cursor;
                } else {
                    if (Array.isArray(data.events)) {
                        data.events.forEach((ev) => this.processIncomingEvent(ev));
                    }
                    this.cursor = data.cursor;
                    this.state.cursor = data.cursor;
                }
            } else if (isFirstCursorInitialization && typeof data.cursor === 'number') {
                this.cursor = data.cursor;
                this.state.cursor = data.cursor;
            }

            if (data.state && typeof data.state === 'object') {
                this.state.roleState = data.state;
                this.stateCallbacks.forEach((cb) => {
                    try {
                        cb(data.state);
                    } catch (_) {
                        // Ignore callback error
                    }
                });
                window.dispatchEvent(new CustomEvent('alona:realtime-state', { detail: data.state }));
            }

            this.state.status = 'connected';
            this.state.lastSyncedAt = new Date().toISOString();

            if (wasOfflineOrReconnecting) {
                this.reconnectCallbacks.forEach((cb) => {
                    try {
                        cb(data);
                    } catch (_) {
                        // Ignore reconnect callback error
                    }
                });
                window.dispatchEvent(new CustomEvent('alona:realtime-reconnected', { detail: data }));
            }

            return data;
        } catch (_) {
            if (!navigator.onLine) {
                this.state.status = 'offline';
            }
            return null;
        } finally {
            this.syncInFlight = false;
        }
    }

    async fetchCatalogState(productIds = []) {
        try {
            const cleanIds = Array.from(new Set(
                (Array.isArray(productIds) ? productIds : [productIds])
                    .map((id) => Number(id))
                    .filter((id) => Number.isInteger(id) && id > 0)
            ));

            const params = new URLSearchParams();
            if (cleanIds.length > 0) {
                params.set('product_ids', cleanIds.join(','));
            }

            const res = await fetch(`${this.options.catalogEndpoint}?${params.toString()}`, {
                method: 'GET',
                headers: this.getAuthHeaders(),
                credentials: 'same-origin',
            });

            if (!res.ok) return null;
            const data = await res.json();
            if (data.server_time_ms) {
                this.updateServerClock(Number(data.server_time_ms));
            }
            return data;
        } catch (_) {
            return null;
        }
    }
}

export function initAlonaRealtime(options = {}) {
    if (typeof window === 'undefined') return null;
    if (!window.Echo) {
        const instance = new AlonaEchoClient(options);
        window.Echo = instance;
        window.AlonaRealtime = instance;
    }
    return window.Echo;
}

export function useEchoState() {
    const echo = typeof window !== 'undefined' ? (window.Echo || initAlonaRealtime()) : null;
    return echo ? readonly(echo.state) : null;
}
