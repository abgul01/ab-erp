import { create } from 'zustand';
import api from '../api/client';

/**
 * Auth + session store. Holds token, current user, dynamic menu tree,
 * and the permission map used by <Can> and the sidebar.
 */
export const useAuth = create((set, get) => ({
    token: localStorage.getItem('ab_token') || null,
    user: null,
    menus: [],
    permissions: {},
    loading: false,
    ready: false,

    async login(username, password) {
        const { data } = await api.post('/auth/login', { username, password });
        localStorage.setItem('ab_token', data.data.token);
        set({
            token: data.data.token,
            user: data.data.user,
            menus: data.data.menus,
            permissions: data.data.permissions,
            ready: true,
        });
    },

    async loadMe() {
        set({ loading: true });
        try {
            const { data } = await api.get('/auth/me');
            set({
                user: data.data.user,
                menus: data.data.menus,
                permissions: data.data.permissions,
                ready: true,
            });
        } catch {
            localStorage.removeItem('ab_token');
            set({ token: null, user: null, ready: true });
        } finally {
            set({ loading: false });
        }
    },

    async logout() {
        try {
            await api.post('/auth/logout');
        } catch {
            /* ignore */
        }
        localStorage.removeItem('ab_token');
        set({ token: null, user: null, menus: [], permissions: {} });
    },

    /** action ∈ view|create|edit|delete|download|import */
    can(menuLink, action = 'view') {
        const { user, permissions } = get();
        if (user?.is_super_admin) return true;
        return Boolean(permissions?.[menuLink]?.[action]);
    },

    /** Ganti password akun sendiri (token aktif dipertahankan). */
    async changePassword(currentPassword, nextPassword) {
        await api.post('/auth/change-password', {
            current_password: currentPassword,
            password: nextPassword,
            password_confirmation: nextPassword,
        });
    },

    /** Muat ulang user + pohon menu + peta permission dari server. */
    async refreshMenus() {
        const { data } = await api.get('/auth/me');
        set({
            user: data.data.user,
            menus: data.data.menus,
            permissions: data.data.permissions,
        });
    },
}));
