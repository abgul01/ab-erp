/**
 * Service worker registration for the MES terminals.
 *
 * The worker is what lets a tablet keep the app shell available through a
 * dropped link; the scans themselves are buffered separately in IndexedDB
 * (see stores/offlineQueue). Dev builds are left alone so HMR keeps working.
 */
export function registerServiceWorker() {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // A terminal served over plain HTTP cannot register one. The app
            // still works online, so this is not worth interrupting anyone for.
        });
    });
}
