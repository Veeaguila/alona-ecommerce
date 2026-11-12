import { initAlonaRealtime } from './realtime/echo';

if (typeof window !== 'undefined') {
    initAlonaRealtime();
}
