import { useEffect, useMemo, useState } from 'react';
import { Outlet, NavLink, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../stores/auth';
import Icon from '../components/Icon';
import Modal from '../components/Modal';
import { apiError } from '../api/client';

function MenuGroup({ menu, isOpen, onToggle }) {
    const hasChildren = menu.children && menu.children.length > 0;

    if (!hasChildren) {
        if (!menu.link || menu.link === '0') return null;
        return (
            <NavLink
                to={`/${menu.link}`}
                className={({ isActive }) =>
                    `flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition ${
                        isActive ? 'bg-white/15 text-white' : 'text-blue-100 hover:bg-[var(--ftpi-sidebar-hover)]'
                    }`
                }
            >
                <Icon name={menu.icon} className="h-4 w-4" />
                {menu.name}
            </NavLink>
        );
    }

    return (
        <div className="mb-1">
            <button
                onClick={onToggle}
                className="flex w-full items-center justify-between rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide text-blue-200/80 hover:text-white"
            >
                <span className="flex items-center gap-2">
                    <Icon name={menu.icon} className="h-4 w-4" />
                    {menu.name}
                </span>
                <Icon name="chevron" className={`h-3.5 w-3.5 transition ${isOpen ? '' : '-rotate-90'}`} />
            </button>
            {isOpen && (
                <div className="mt-1 space-y-0.5 pl-2">
                    {menu.children.map((child) => (
                        <NavLink
                            key={child.id}
                            to={`/${child.link}`}
                            className={({ isActive }) =>
                                `flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition ${
                                    isActive ? 'bg-white/15 font-medium text-white' : 'text-blue-100/90 hover:bg-[var(--ftpi-sidebar-hover)]'
                                }`
                            }
                        >
                            <Icon name={child.icon} className="h-4 w-4 opacity-80" />
                            {child.name}
                        </NavLink>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function AppLayout() {
    const { user, menus, logout, changePassword } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [mobileOpen, setMobileOpen] = useState(false);
    const [openId, setOpenId] = useState(null);
    const [tabs, setTabs] = useState([{ path: '/', label: 'Dashboard' }]);
    const [pwOpen, setPwOpen] = useState(false);
    const [pwValues, setPwValues] = useState({ current: '', next: '', confirm: '' });
    const [pwError, setPwError] = useState('');
    const [pwSaving, setPwSaving] = useState(false);

    // path -> menu label
    const labelMap = useMemo(() => {
        const map = { '/': 'Dashboard' };
        menus.forEach((m) => {
            if (m.link && m.link !== '0') map[`/${m.link}`] = m.name;
            (m.children || []).forEach((c) => { map[`/${c.link}`] = c.name; });
        });
        return map;
    }, [menus]);

    // Accordion: auto-open the group that contains the active route.
    useEffect(() => {
        const active = menus.find((m) => (m.children || []).some((c) => `/${c.link}` === location.pathname));
        if (active) setOpenId(active.id);
    }, [location.pathname, menus]);

    // Tabs: register the current route as an open tab.
    useEffect(() => {
        const path = location.pathname;
        const label = labelMap[path] || path.replace('/', '') || 'Halaman';
        setTabs((prev) => (prev.some((t) => t.path === path) ? prev : [...prev, { path, label }]));
        setMobileOpen(false);
    }, [location.pathname, labelMap]);

    const closeTab = (e, path) => {
        e.stopPropagation();
        setTabs((prev) => {
            const next = prev.filter((t) => t.path !== path);
            if (path === location.pathname) navigate((next[next.length - 1] || { path: '/' }).path);
            return next.length ? next : [{ path: '/', label: 'Dashboard' }];
        });
    };

    // Close every tab back to a single Dashboard tab.
    const closeAllTabs = () => {
        setTabs([{ path: '/', label: 'Dashboard' }]);
        if (location.pathname !== '/') navigate('/');
    };

    const doLogout = async () => { await logout(); navigate('/login', { replace: true }); };

    const submitPassword = async (e) => {
        e.preventDefault();
        setPwError('');
        if (pwValues.next.length < 6) return setPwError('Password minimal 6 karakter.');
        if (pwValues.next !== pwValues.confirm) return setPwError('Konfirmasi password tidak sama.');
        setPwSaving(true);
        try {
            await changePassword(pwValues.current, pwValues.next);
            setPwOpen(false);
            setPwValues({ current: '', next: '', confirm: '' });
        } catch (err) {
            setPwError(apiError(err, 'Gagal mengganti password.'));
        } finally {
            setPwSaving(false);
        }
    };

    return (
        <div className="flex h-screen overflow-hidden">
            {/* Sidebar */}
            <aside
                className={`fixed inset-y-0 left-0 z-30 w-64 transform bg-[var(--ftpi-sidebar)] transition-transform lg:static lg:translate-x-0 ${
                    mobileOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex h-14 items-center gap-2 border-b border-white/10 px-5 text-white">
                    <div className="flex h-8 w-8 items-center justify-center rounded-md bg-white/15 text-sm font-bold">AB</div>
                    <span className="font-semibold tracking-wide">AB-ERP</span>
                </div>
                <nav className="h-[calc(100vh-3.5rem)] space-y-1 overflow-y-auto px-3 py-4">
                    <NavLink to="/" end
                        className={({ isActive }) => `flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition ${isActive ? 'bg-white/15 text-white' : 'text-blue-100 hover:bg-[var(--ftpi-sidebar-hover)]'}`}>
                        <Icon name="dashboard" className="h-4 w-4" /> Dashboard
                    </NavLink>
                    {menus.map((m) => (
                        <MenuGroup key={m.id} menu={m} isOpen={openId === m.id} onToggle={() => setOpenId((id) => (id === m.id ? null : m.id))} />
                    ))}
                </nav>
            </aside>

            {mobileOpen && <div className="fixed inset-0 z-20 bg-black/40 lg:hidden" onClick={() => setMobileOpen(false)} />}

            {/* Main */}
            <div className="flex flex-1 flex-col overflow-hidden">
                <header className="flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 shadow-sm">
                    <button className="rounded p-2 text-slate-500 hover:bg-slate-100 lg:hidden" onClick={() => setMobileOpen(true)}>
                        <Icon name="menu" className="h-5 w-5" />
                    </button>
                    <div className="hidden text-sm text-slate-400 lg:block">Manufaktur Pipa Besi — ERP &amp; MES</div>
                    <div className="flex items-center gap-3">
                        <div className="text-right">
                            <div className="text-sm font-medium text-slate-700">{user?.name}</div>
                            <div className="text-xs text-slate-400">{user?.is_super_admin ? 'Administrator' : user?.status}</div>
                        </div>
                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--ftpi-primary)] text-sm font-semibold text-white">
                            {user?.name?.charAt(0)?.toUpperCase()}
                        </div>
                        <button onClick={doLogout} className="rounded p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" title="Logout">
                            <Icon name="logout" className="h-5 w-5" />
                        </button>
                        <button onClick={() => { setPwError(''); setPwValues({ current: '', next: '', confirm: '' }); setPwOpen(true); }}
                            className="rounded p-2 text-slate-500 hover:bg-blue-50 hover:text-blue-600" title="Ganti Password">
                            <Icon name="lock" className="h-5 w-5" />
                        </button>
                    </div>
                </header>

                <Modal
                    open={pwOpen}
                    onClose={() => setPwOpen(false)}
                    title="Ganti Password"
                    footer={
                        <>
                            <button className="btn btn-ghost" onClick={() => setPwOpen(false)}>Batal</button>
                            <button className="btn btn-primary" onClick={submitPassword} disabled={pwSaving}>
                                {pwSaving ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />}
                                Simpan
                            </button>
                        </>
                    }
                >
                    {pwError && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{pwError}</div>}
                    <form onSubmit={submitPassword} className="grid grid-cols-1 gap-4">
                        <div>
                            <label className="field-label">Password Saat Ini <span className="text-red-500">*</span></label>
                            <input className="field-input" type="password" value={pwValues.current}
                                onChange={(e) => setPwValues((v) => ({ ...v, current: e.target.value }))} />
                        </div>
                        <div>
                            <label className="field-label">Password Baru <span className="text-red-500">*</span></label>
                            <input className="field-input" type="password" value={pwValues.next}
                                onChange={(e) => setPwValues((v) => ({ ...v, next: e.target.value }))} />
                        </div>
                        <div>
                            <label className="field-label">Konfirmasi Password Baru <span className="text-red-500">*</span></label>
                            <input className="field-input" type="password" value={pwValues.confirm}
                                onChange={(e) => setPwValues((v) => ({ ...v, confirm: e.target.value }))} />
                        </div>
                    </form>
                </Modal>

                {/* Tab bar */}
                <div className="flex items-center gap-1 overflow-x-auto border-b border-slate-200 bg-slate-50 px-2 py-1.5">
                    {tabs.map((t) => {
                        const active = t.path === location.pathname;
                        return (
                            <div key={t.path}
                                onClick={() => navigate(t.path)}
                                className={`group flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-md border px-3 py-1 text-xs transition ${
                                    active ? 'border-slate-300 bg-white font-medium text-slate-800 shadow-sm' : 'border-transparent text-slate-500 hover:bg-white/70'
                                }`}>
                                <span>{t.label}</span>
                                {t.path !== '/' && (
                                    <button onClick={(e) => closeTab(e, t.path)} className="rounded p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                                        <Icon name="x" className="h-3 w-3" />
                                    </button>
                                )}
                            </div>
                        );
                    })}
                    {tabs.length > 1 && (
                        <button onClick={closeAllTabs} title="Tutup semua tab"
                            className="ml-1 flex shrink-0 items-center gap-1 whitespace-nowrap rounded-md border border-transparent px-2.5 py-1 text-xs text-slate-500 transition hover:bg-white/70 hover:text-red-600">
                            <Icon name="x" className="h-3 w-3" /> Tutup Semua
                        </button>
                    )}
                </div>

                <main className="flex-1 overflow-y-auto">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
