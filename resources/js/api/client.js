import axios from 'axios';

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

// On 401, clear the session and bounce to login.
api.interceptors.response.use(
    (res) => res,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('ab_token');
            if (!window.location.pathname.startsWith('/login')) {
                window.location.href = '/login';
            }
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
