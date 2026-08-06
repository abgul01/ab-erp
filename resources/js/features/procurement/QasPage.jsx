import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';

/**
 * Incoming inspection (QAS).
 *
 * Serials are generated when the GR is created; this screen is where the
 * physical bar is measured. Length and weight are typed from the scale, not
 * copied from the order, because the whole point is to record what arrived.
 * Confirming the GR is what releases the serials to the warehouse.
 */
export default function QasPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [grId, setGrId] = useState(null);

    const grs = useQuery({
        queryKey: ['qas', 'pending'],
        queryFn: async () => (await api.get('/qas/pending')).data.data,
    });

    const serials = useQuery({
        queryKey: ['qas', 'serials', grId],
        queryFn: async () => (await api.get(`/qas/pending/${grId}/serials`)).data.data,
        enabled: !!grId,
    });

    const refresh = () => {
        qc.invalidateQueries({ queryKey: ['qas'] });
    };

    const confirm = useMutation({
        mutationFn: async () => api.post(`/qas/confirm/${grId}`),
        onSuccess: () => { setGrId(null); refresh(); },
        onError: (e) => alert(apiError(e)),
    });

    if (grId) {
        return (
            <InspectPanel
                grId={grId}
                rows={serials.data || []}
                loading={serials.isLoading}
                canEdit={can('qas', 'edit')}
                onBack={() => { setGrId(null); refresh(); }}
                onInspected={refresh}
                onConfirm={() => window.confirm('Selesaikan pengecekan GR ini? Serial OK akan diteruskan ke gudang.') && confirm.mutate()}
                confirming={confirm.isPending}
            />
        );
    }

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">QAS — Inspeksi Incoming</h1>
                <p className="text-xs text-slate-400">
                    Pilih GR, lalu ukur tiap serial: panjang dan berat aktual, judge OK atau NG.
                    GR baru bisa dikonfirmasi setelah seluruh serialnya diperiksa.
                </p>
            </div>

            <div className="card overflow-hidden">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th className="px-4 py-3">Kode GR</th>
                            <th className="px-4 py-3">Tanggal</th>
                            <th className="px-4 py-3">Vendor</th>
                            <th className="px-4 py-3">Serial Belum Diperiksa</th>
                            <th className="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {grs.isLoading && <tr><td colSpan={5} className="px-4 py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></td></tr>}
                        {!grs.isLoading && (grs.data || []).length === 0 && (
                            <tr><td colSpan={5} className="px-4 py-10 text-center text-slate-400">Tidak ada GR yang menunggu pengecekan.</td></tr>
                        )}
                        {(grs.data || []).map((g) => (
                            <tr key={g.id} className="border-b border-slate-100 hover:bg-blue-50/40">
                                <td className="px-4 py-2.5 font-medium text-slate-700">{g.code}</td>
                                <td className="px-4 py-2.5">{(g.date || '').slice(0, 10)}</td>
                                <td className="px-4 py-2.5">{g.ven?.company_n || '—'}</td>
                                <td className="px-4 py-2.5">{g.pending_serials ?? '—'}</td>
                                <td className="px-4 py-2.5 text-right">
                                    <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => setGrId(g.id)}>Periksa</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function InspectPanel({ grId, rows, loading, canEdit, onBack, onInspected, onConfirm, confirming }) {
    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <button className="btn btn-ghost mb-2 px-2 py-1 text-xs" onClick={onBack}><Icon name="left" /> Kembali</button>
                    <h1 className="text-xl font-semibold text-slate-800">Pengecekan Serial — GR #{grId}</h1>
                    <p className="text-xs text-slate-400">{rows.length} serial menunggu. Baris hilang dari daftar setelah diputuskan.</p>
                </div>
                {canEdit && (
                    <button className="btn btn-primary" disabled={confirming || rows.length > 0} onClick={onConfirm} title={rows.length > 0 ? 'Masih ada serial yang belum diperiksa' : ''}>
                        <Icon name="check" /> {confirming ? 'Memproses…' : 'Konfirmasi GR'}
                    </button>
                )}
            </div>

            <div className="card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th className="px-3 py-3">Serial</th>
                            <th className="px-3 py-3">Item</th>
                            <th className="px-3 py-3">Millsheet</th>
                            <th className="px-3 py-3">Panjang (mm)</th>
                            <th className="px-3 py-3">Berat (kg)</th>
                            <th className="px-3 py-3">Keputusan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading && <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></td></tr>}
                        {!loading && rows.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-10 text-center text-emerald-600">Seluruh serial sudah diperiksa. Silakan konfirmasi GR.</td></tr>
                        )}
                        {rows.map((s) => (
                            <SerialRow key={s.serial_db_id} s={s} canEdit={canEdit} onDone={onInspected} />
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function SerialRow({ s, canEdit, onDone }) {
    const [length, setLength] = useState(s.length_current ?? '');
    const [weight, setWeight] = useState(s.weight_current ?? '');
    const [reason, setReason] = useState('');

    const inspect = useMutation({
        mutationFn: async (status) => api.post('/qas/inspect', {
            serial_id: s.serial_db_id,
            length: Number(length) || 0,
            weight: Number(weight) || 0,
            status,
            ng_reason: status === 'NG' ? (reason || 'Tidak sesuai standar') : null,
        }),
        onSuccess: onDone,
        onError: (e) => alert(apiError(e)),
    });

    const filled = length !== '' && weight !== '';

    return (
        <tr className="border-b border-slate-100">
            <td className="px-3 py-2 font-mono text-slate-700">{s.serial_id}</td>
            <td className="px-3 py-2">{s.item_code}<div className="text-xs text-slate-400">{s.item_name}</div></td>
            <td className="px-3 py-2 text-slate-500">{s.millsheet || '—'}</td>
            <td className="px-3 py-2">
                <input type="number" step="0.01" className="field-input w-28" value={length} disabled={!canEdit}
                    onChange={(e) => setLength(e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <input type="number" step="0.01" className="field-input w-28" value={weight} disabled={!canEdit}
                    onChange={(e) => setWeight(e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <div className="flex items-center gap-2">
                    <button className="btn btn-ghost px-2 py-1 text-xs text-emerald-700" disabled={!canEdit || !filled || inspect.isPending}
                        onClick={() => inspect.mutate('OK')}>OK</button>
                    <button className="btn btn-ghost px-2 py-1 text-xs text-red-700" disabled={!canEdit || !filled || inspect.isPending}
                        onClick={() => inspect.mutate('NG')}>NG</button>
                    <input className="field-input w-40" placeholder="alasan NG" value={reason} disabled={!canEdit}
                        onChange={(e) => setReason(e.target.value)} />
                </div>
            </td>
        </tr>
    );
}
