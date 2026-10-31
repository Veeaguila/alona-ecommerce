// Keep session recovery consistent across the portal's existing fetch-based pages.
export function logisticsSessionFetch(fetchRequest, { origin, getToken, onExpired }) {
    let handled = false;
    return async (input, options) => {
        const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url, origin);
        const token = getToken();
        const headers = new Headers(options?.headers ?? (typeof input === 'object' ? input.headers : undefined));
        const owned = url.origin === origin && url.pathname.startsWith('/api/') && token
            && headers.get('Authorization') === `Bearer ${token}`;
        const response = await fetchRequest(input, options);
        // A late response from an old login must not clear a newer session.
        if (owned && response.status === 401 && token === getToken() && !handled) {
            handled = true;
            onExpired();
        }
        return response;
    };
}
