import axios from 'axios';
import { enqueue } from '../stores/offlineQueue';

const api = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

// Attach bearer token from localStorage on every request.
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('ab_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

/**
 * A write to a MES scan endpoint that never reached the server is buffered
 * rather than lost — the shop floor keeps working through a dropped link and
 * the queue is flushed on reconnect. Only MES writes qualify: office screens
 * must fail loudly, and a read has nothing to replay.
 */
function isQueueable(config, error) {
    if (error.response) return false;             // server answered; a real error
    if (error.code === 'ERR_CANCELED') return false;
    const url = String(config?.url || '').replace(/^\/+/, '');
    return url.startsWith('mes/')
        && !url.startsWith('mes/sync')
        && ['post', 'put', 'patch', 'delete'].includes(String(config?.method).toLowerCase());
}

// On 401 clear the session; on a lost connection buffer MES scans.
api.interceptors.response.use(
    (res) => res,
    async (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('ab_token');
            if (!window.location.pathname.startsWith('/login')) {
                window.location.href = '/login';
            }
            return Promise.reject(error);
        }

        if (isQueueable(error.config, error)) {
            const { config } = error;
            const body = typeof config.data === 'string' ? JSON.parse(config.data || '{}') : (config.data || {});
            const op = await enqueue({
                method: config.method,
                url: `api/v1/${String(config.url).replace(/^\/+/, '')}`,
                data: body,
            });

            // Resolve so the screen can acknowledge the scan. `queued` tells it
            // the row does not exist server-side yet, so there is no id to show.
            return { data: { data: null, queued: true, client_uuid: op.clientUuid }, status: 202, config };
        }

        return Promise.reject(error);
    },
);

/** Extract the first error message from the API error envelope. */
export function apiError(error, fallback = 'Terjadi kesalahan.') {
    const errs = error?.response?.data?.errors;
    if (Array.isArray(errs) && errs.length) {
        return errs.map((e) => e.message).join(' ');
    }
    return error?.message || fallback;
}

export default api;
