import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import { ItemSelect, money } from '../procurement/common';

/**
 * FG Transfer — move finished goods from one FG code to another.
 *
 * Only allowed inside the same `spec_group`: the bars are physically identical,
 * it is the customer code that differs. The server enforces that; this screen
 * just makes the constraint visible before the attempt.
 */
export default function FgTransferPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [from, setFrom] = useState(null);
    const [toItem, setToItem] = useState('');
    const [qty, setQty] = useState('');

    const lots = useQuery({
        queryKey: ['fg-transfer', 'lots'],
        queryFn: async () => (await api.get('/fg-transfer/lots')).data.data,
    });

    const transfer = useMutation({
        mutationFn: async () => (await api.post('/fg-transfer', {
            from_det_id: from.id, to_item_id: toItem, qty: Number(qty),
        })).data.data,
        onSuccess: (d) => {
            alert(`Transfer selesai. Lot baru: ${d.to_lot_code || d.to_code || '—'}`);
            setFrom(null); setToItem(''); setQty('');
            qc.invalidateQueries({ queryKey: ['fg-transfer'] });
        },
        onError: (e) => alert(apiError(e)),
    });

    const remaining = (l) => Number(l.qty || 0) - Number(l.used || 0);

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Transfer FG</h1>
                <p className="text-xs text-slate-400">
                    Memindahkan stok FG ke kode FG lain. Hanya bisa antar kode dengan <code>spec_group</code> yang sama.
                </p>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <div className="card overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th className="px-4 py-3">Lot</th>
                                    <th className="px-4 py-3">Item</th>
                                    <th className="px-4 py-3">Qty Masuk</th>
                                    <th className="px-4 py-3">Sisa</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {lots.isLoading && <tr><td colSpan={5} className="px-4 py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></td></tr>}
                                {!lots.isLoading && (lots.data || []).length === 0 && (
                                    <tr><td colSpan={5} className="px-4 py-10 text-center text-slate-400">Tidak ada lot FG aktif bersisa.</td></tr>
                                )}
                                {(lots.data || []).map((l) => (
                                    <tr key={l.id} className={`border-b border-slate-100 ${from?.id === l.id ? 'bg-blue-50' : 'hover:bg-blue-50/40'}`}>
                                        <td className="px-4 py-2.5 font-mono">{l.code}</td>
                                        <td className="px-4 py-2.5">{l.item_id}</td>
                                        <td className="px-4 py-2.5">{money(l.qty)}</td>
                                        <td className="px-4 py-2.5 font-medium">{money(remaining(l))}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => { setFrom(l); setQty(String(remaining(l))); }}>Pilih</button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="card p-4">
                    <h2 className="mb-3 font-semibold text-slate-800">Tujuan Transfer</h2>
                    {!from && <p className="text-sm text-slate-400">Pilih lot asal dari tabel di samping.</p>}
                    {from && (
                        <div className="space-y-3">
                            <div className="rounded bg-slate-50 px-3 py-2 text-sm">
                                <div className="text-xs text-slate-400">Lot asal</div>
                                <div className="font-mono">{from.code}</div>
                                <div className="text-xs text-slate-500">sisa {money(remaining(from))}</div>
                            </div>
                            <div>
                                <label className="field-label">Item FG tujuan</label>
                                <ItemSelect value={toItem} onChange={setToItem} placeholder="— pilih FG tujuan —" />
                            </div>
                            <div>
                                <label className="field-label">Qty</label>
                                <input type="number" min="1" max={remaining(from)} className="field-input" value={qty}
                                    onChange={(e) => setQty(e.target.value)} />
                            </div>
                            <button className="btn btn-primary w-full"
                                disabled={!can('stock-fg', 'create') || !toItem || !qty || Number(qty) < 1 || Number(qty) > remaining(from) || transfer.isPending}
                                onClick={() => transfer.mutate()}>
                                {transfer.isPending ? 'Memproses…' : 'Transfer'}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
