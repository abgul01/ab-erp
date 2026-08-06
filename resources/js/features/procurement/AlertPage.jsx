import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import { money } from './common';

const SEVERITY = {
    CRITICAL: { cls: 'bg-red-100 text-red-700 border-red-300', label: 'Kritis' },
    WARNING: { cls: 'bg-amber-100 text-amber-800 border-amber-300', label: 'Peringatan' },
    INFO: { cls: 'bg-sky-100 text-sky-700 border-sky-300', label: 'Info' },
};

const TYPE_LABEL = { QUOTA: 'Kuota Impor', MIN_STOCK: 'Stok Minimum' };

/**
 * Operational alerts.
 *
 * Import quota is hard-blocked at the moment a PO exceeds it, and by then the
 * shipment is already a problem. These warnings exist to arrive earlier.
 *
 * An alert whose condition has cleared closes itself on the next check, so the
 * list stays short enough to be worth reading.
 */
export default function AlertPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);

    const list = useQuery({
        queryKey: ['alerts'],
        queryFn: async () => (await api.get('/alerts')).data.data,
        refetchInterval: 60_000,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['alerts'] });

    const refresh = useMutation({
        mutationFn: async () => (await api.post('/alerts/refresh')).data.data,
        onSuccess: (d) => { invalidate(); alert(d.message); },
        onError: (e) => alert(apiError(e)),
    });

    const resolve = useMutation({
        mutationFn: async (id) => api.post(`/alerts/${id}/resolve`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const items = list.data?.items || [];

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Peringatan Operasional</h1>
                    <p className="text-xs text-slate-400">
                        Kuota impor menipis dan stok di bawah minimum. Diperiksa otomatis tiap pagi;
                        peringatan yang kondisinya sudah teratasi menutup sendiri.
                    </p>
                </div>
                {can('alerts', 'create') && (
                    <button className="btn btn-primary" disabled={refresh.isPending} onClick={() => refresh.mutate()}>
                        <Icon name="search" /> {refresh.isPending ? 'Memeriksa…' : 'Periksa Sekarang'}
                    </button>
                )}
            </div>

            <div className="mb-4 grid gap-3 sm:grid-cols-3">
                <Stat label="Peringatan terbuka" value={list.data?.open ?? '—'} />
                <Stat label="Kritis" value={list.data?.critical ?? '—'} tone={list.data?.critical > 0 ? 'text-red-600' : ''} />
                <Stat label="Pemeriksaan otomatis" value="Tiap hari 05:30" />
            </div>

            {list.isLoading && <div className="py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></div>}

            {!list.isLoading && items.length === 0 && (
                <div className="card p-10 text-center text-slate-400">
                    Tidak ada peringatan terbuka — kuota impor dan stok minimum dalam batas aman.
                </div>
            )}

            <div className="space-y-2">
                {items.map((a) => {
                    const sev = SEVERITY[a.severity] || SEVERITY.INFO;
                    return (
                        <div key={a.id} className={`flex flex-wrap items-start gap-3 rounded-md border px-4 py-3 ${sev.cls}`}>
                            <Icon name={a.severity === 'CRITICAL' ? 'alert-octagon' : 'alert-triangle'} className="mt-0.5 h-5 w-5 shrink-0" />
                            <div className="min-w-0 grow">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-semibold">{a.title}</span>
                                    <span className="rounded bg-white/60 px-1.5 py-0.5 text-xs">{TYPE_LABEL[a.type] || a.type}</span>
                                    <span className="rounded bg-white/60 px-1.5 py-0.5 text-xs">{sev.label}</span>
                                </div>
                                <p className="mt-0.5 text-sm">{a.message}</p>
                                {a.value != null && a.threshold != null && (
                                    <p className="mt-0.5 text-xs opacity-75">
                                        Nilai {money(a.value)} terhadap batas {money(a.threshold)}
                                    </p>
                                )}
                            </div>
                            {can('alerts', 'edit') && (
                                <button className="btn btn-ghost shrink-0 px-2 py-1 text-xs"
                                    title="Tutup manual — kondisi yang benar-benar teratasi menutup sendiri"
                                    onClick={() => window.confirm('Tutup peringatan ini?') && resolve.mutate(a.id)}>
                                    Tutup
                                </button>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function Stat({ label, value, tone = '' }) {
    return (
        <div className="card p-3">
            <div className="text-xs text-slate-400">{label}</div>
            <div className={`text-lg font-semibold ${tone || 'text-slate-800'}`}>{value}</div>
        </div>
    );
}
