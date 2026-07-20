import { useQueries } from '@tanstack/react-query';
import api from '../api/client';
import { useAuth } from '../stores/auth';
import Icon from '../components/Icon';

const CARDS = [
    { key: 'items', label: 'Master Item', icon: 'box' },
    { key: 'contacts', label: 'Kontak (Cust/Vendor)', icon: 'users' },
    { key: 'machines', label: 'Mesin', icon: 'cog' },
    { key: 'processes', label: 'Proses', icon: 'workflow' },
    { key: 'uoms', label: 'Unit of Measure', icon: 'ruler' },
];

export default function Dashboard() {
    const { user, can } = useAuth();
    const visible = CARDS.filter((c) => can(c.key, 'view'));

    const results = useQueries({
        queries: visible.map((c) => ({
            queryKey: [c.key, 'count'],
            queryFn: async () => (await api.get(`/${c.key}`, { params: { per_page: 1 } })).data.meta?.total ?? 0,
        })),
    });

    return (
        <div className="p-6">
            <div className="mb-6">
                <h1 className="text-xl font-semibold text-slate-800">Selamat datang, {user?.name}</h1>
                <p className="text-sm text-slate-500">Ringkasan data master &amp; engineering — AB-ERP Fase 1 (Fondasi).</p>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {visible.map((c, i) => (
                    <div key={c.key} className="card flex items-center gap-4 p-5">
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 text-[var(--ftpi-primary)]">
                            <Icon name={c.icon} className="h-6 w-6" />
                        </div>
                        <div>
                            <div className="text-2xl font-semibold text-slate-800">
                                {results[i]?.isLoading ? '…' : (results[i]?.data ?? 0)}
                            </div>
                            <div className="text-sm text-slate-500">{c.label}</div>
                        </div>
                    </div>
                ))}
            </div>

            {visible.length === 0 && (
                <div className="card p-8 text-center text-slate-400">
                    Anda belum memiliki hak akses ke modul manapun. Hubungi administrator.
                </div>
            )}
        </div>
    );
}
