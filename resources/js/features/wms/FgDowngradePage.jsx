import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import { money } from '../procurement/common';

/**
 * FG Downgrade — reject finished goods back into raw material.
 *
 * The target material comes from the mapping table, not from an operator's
 * choice, so a downgrade always lands on the code accounting expects. The
 * difference in value goes to scrap loss on the reclassification journal.
 */
export default function FgDowngradePage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [lot, setLot] = useState(null);
    const [qty, setQty] = useState('');

    const mappings = useQuery({
        queryKey: ['fg-downgrade', 'mappings'],
        queryFn: async () => (await api.get('/fg-downgrade/mappings')).data.data,
    });

    const lots = useQuery({
        queryKey: ['fg-transfer', 'lots'],
        queryFn: async () => (await api.get('/fg-transfer/lots')).data.data,
    });

    const downgrade = useMutation({
        mutationFn: async () => (await api.post('/fg-downgrade', { det_id: lot.id, qty: Number(qty) })).data.data,
        onSuccess: (d) => {
            alert(`Downgrade selesai. Material baru: item #${d.material_item_id}`);
            setLot(null); setQty('');
            qc.invalidateQueries({ queryKey: ['fg-transfer'] });
            qc.invalidateQueries({ queryKey: ['fg-downgrade'] });
        },
        onError: (e) => alert(apiError(e)),
    });

    const remaining = (l) => Number(l.qty || 0) - Number(l.used || 0);
    const mapped = new Set((mappings.data || []).map((m) => m.fg_item_id));

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Downgrade FG</h1>
                <p className="text-xs text-slate-400">
                    FG yang tidak lolos mutu dikembalikan menjadi material. Hanya item yang punya mapping di bawah yang bisa diproses.
                </p>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-4">
                    <div className="card overflow-x-auto">
                        <div className="border-b border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Lot FG tersedia</div>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th className="px-4 py-3">Lot</th>
                                    <th className="px-4 py-3">Item</th>
                                    <th className="px-4 py-3">Sisa</th>
                                    <th className="px-4 py-3">Mapping</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {lots.isLoading && <tr><td colSpan={5} className="px-4 py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></td></tr>}
                                {!lots.isLoading && (lots.data || []).length === 0 && (
                                    <tr><td colSpan={5} className="px-4 py-10 text-center text-slate-400">Tidak ada lot FG aktif.</td></tr>
                                )}
                                {(lots.data || []).map((l) => {
                                    const ok = mapped.has(l.item_id);
                                    return (
                                        <tr key={l.id} className={`border-b border-slate-100 ${lot?.id === l.id ? 'bg-blue-50' : ''}`}>
                                            <td className="px-4 py-2.5 font-mono">{l.code}</td>
                                            <td className="px-4 py-2.5">{l.item_id}</td>
                                            <td className="px-4 py-2.5">{money(remaining(l))}</td>
                                            <td className="px-4 py-2.5">
                                                {ok ? <span className="text-xs text-emerald-600">tersedia</span>
                                                    : <span className="text-xs text-slate-400">belum dipetakan</span>}
                                            </td>
                                            <td className="px-4 py-2.5 text-right">
                                                <button className="btn btn-ghost px-2 py-1 text-xs" disabled={!ok}
                                                    onClick={() => { setLot(l); setQty(String(remaining(l))); }}>Pilih</button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <div className="card overflow-x-auto">
                        <div className="border-b border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Mapping FG → Material</div>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th className="px-4 py-3">FG</th>
                                    <th className="px-4 py-3">Menjadi Material</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(mappings.data || []).length === 0 && (
                                    <tr><td colSpan={2} className="px-4 py-6 text-center text-slate-400">Belum ada mapping downgrade.</td></tr>
                                )}
                                {(mappings.data || []).map((m) => (
                                    <tr key={m.id} className="border-b border-slate-100">
                                        <td className="px-4 py-2">{m.fg_code}<div className="text-xs text-slate-400">{m.fg_name}</div></td>
                                        <td className="px-4 py-2">{m.mat_code}<div className="text-xs text-slate-400">{m.mat_name}</div></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="card h-fit p-4">
                    <h2 className="mb-3 font-semibold text-slate-800">Proses Downgrade</h2>
                    {!lot && <p className="text-sm text-slate-400">Pilih lot FG yang sudah punya mapping.</p>}
                    {lot && (
                        <div className="space-y-3">
                            <div className="rounded bg-slate-50 px-3 py-2 text-sm">
                                <div className="text-xs text-slate-400">Lot</div>
                                <div className="font-mono">{lot.code}</div>
                                <div className="text-xs text-slate-500">sisa {money(remaining(lot))}</div>
                            </div>
                            <div>
                                <label className="field-label">Qty didowngrade</label>
                                <input type="number" min="1" max={remaining(lot)} className="field-input" value={qty}
                                    onChange={(e) => setQty(e.target.value)} />
                            </div>
                            <button className="btn btn-primary w-full"
                                disabled={!can('stock-fg', 'create') || !qty || Number(qty) < 1 || Number(qty) > remaining(lot) || downgrade.isPending}
                                onClick={() => window.confirm('Downgrade lot ini menjadi material? Stok FG berkurang dan jurnal reklasifikasi dibuat.') && downgrade.mutate()}>
                                {downgrade.isPending ? 'Memproses…' : 'Downgrade'}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
