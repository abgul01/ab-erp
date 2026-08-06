import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import Modal from '../../components/Modal';
import DataTable from '../../components/DataTable';
import { StatusBadge, money } from '../procurement/common';

const TYPES = [
    ['OPNAME', 'Stock Opname'],
    ['IN', 'Adjustment Masuk'],
    ['OUT', 'Adjustment Keluar'],
    ['BEGIN', 'Saldo Awal'],
];

/**
 * Stock opname and adjustment.
 *
 * The sheet is drafted with the system figure already filled in, so the counter
 * types only what was actually found and the variance is computed rather than
 * asserted. Posting writes the variance journal and is final.
 */
export default function StockAdjustmentPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [creating, setCreating] = useState(false);
    const [viewId, setViewId] = useState(null);

    const list = useQuery({
        queryKey: ['stock-adjustments', { page }],
        queryFn: async () => (await api.get('/stock-adjustments', { params: { page, per_page: 20 } })).data,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['stock-adjustments'] });

    const post = useMutation({
        mutationFn: async (id) => (await api.post(`/stock-adjustments/${id}/post`)).data.data,
        onSuccess: (d) => {
            invalidate();
            alert(`${d.code} diposting. Selisih nilai ${money(d.variance_value)}${d.journal_id ? ` (jurnal #${d.journal_id})` : ' — tidak ada jurnal karena nilainya nol'}.`);
        },
        onError: (e) => alert(apiError(e)),
    });

    const del = useMutation({
        mutationFn: async (id) => api.delete(`/stock-adjustments/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const columns = [
        { key: 'code', label: 'Kode' },
        { key: 'date', label: 'Tanggal', render: (v) => (v || '').slice(0, 10) },
        { key: 'adj_type', label: 'Jenis', render: (v) => TYPES.find(([k]) => k === v)?.[1] || v },
        { key: 'warehouse', label: 'Gudang' },
        { key: 'detail_count', label: 'Baris' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Opname & Adjustment Stok</h1>
                    <p className="text-xs text-slate-400">
                        Selisih hasil hitung fisik terhadap catatan sistem. Posting membuat jurnal selisih persediaan dan tidak bisa dibatalkan.
                    </p>
                </div>
                {can('stock-adjustments', 'create') && (
                    <button className="btn btn-primary" onClick={() => setCreating(true)}><Icon name="plus" /> Sheet Baru</button>
                )}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => setViewId(row.id)}>Detail</button>
                        {row.status === 'DRAFT' && can('stock-adjustments', 'edit') && (
                            <button className="btn btn-ghost px-2 py-1 text-xs text-emerald-700" disabled={post.isPending}
                                onClick={() => window.confirm(`Posting ${row.code}? Jurnal selisih akan dibuat dan dokumen terkunci.`) && post.mutate(row.id)}>
                                Posting
                            </button>
                        )}
                        {row.status === 'DRAFT' && can('stock-adjustments', 'delete') && (
                            <button className="btn btn-ghost px-2 py-1 text-xs text-red-700"
                                onClick={() => window.confirm(`Hapus ${row.code}?`) && del.mutate(row.id)}>Hapus</button>
                        )}
                    </div>
                )} />

            {creating && <SheetModal onClose={() => setCreating(false)} onSaved={() => { setCreating(false); invalidate(); }} />}
            {viewId && <DetailModal id={viewId} onClose={() => setViewId(null)} />}
        </div>
    );
}

function SheetModal({ onClose, onSaved }) {
    const [form, setForm] = useState({
        date: new Date().toISOString().slice(0, 10),
        adj_type: 'OPNAME',
        warehouse: 'RM',
        reason: '',
    });
    const [counts, setCounts] = useState({});
    const [costs, setCosts] = useState({});

    const stock = useQuery({
        queryKey: ['stock-adjustments', 'system-stock', form.warehouse],
        queryFn: async () => (await api.get('/stock-adjustments/system-stock', { params: { warehouse: form.warehouse } })).data.data,
    });

    const save = useMutation({
        mutationFn: async () => api.post('/stock-adjustments', {
            ...form,
            lines: (stock.data || [])
                .filter((r) => counts[r.item_id] !== undefined && counts[r.item_id] !== '')
                .map((r) => ({
                    item_id: r.item_id,
                    qty_system: Number(r.qty_system || 0),
                    qty_counted: Number(counts[r.item_id]),
                    unit_cost: Number(costs[r.item_id] || 0),
                })),
        }),
        onSuccess: onSaved,
        onError: (e) => alert(apiError(e)),
    });

    const rows = stock.data || [];
    const counted = rows.filter((r) => counts[r.item_id] !== undefined && counts[r.item_id] !== '');

    return (
        <Modal open onClose={onClose} size="max-w-5xl" title="Sheet Opname / Adjustment"
            footer={
                <>
                    <button className="btn btn-ghost" onClick={onClose}>Batal</button>
                    <button className="btn btn-primary" disabled={counted.length === 0 || save.isPending} onClick={() => save.mutate()}>
                        {save.isPending ? 'Menyimpan…' : `Simpan (${counted.length} baris)`}
                    </button>
                </>
            }>
            <div className="mb-4 grid gap-3 sm:grid-cols-4">
                <div>
                    <label className="field-label">Tanggal</label>
                    <input type="date" className="field-input" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} />
                </div>
                <div>
                    <label className="field-label">Jenis</label>
                    <select className="field-input" value={form.adj_type} onChange={(e) => setForm({ ...form, adj_type: e.target.value })}>
                        {TYPES.map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                    </select>
                </div>
                <div>
                    <label className="field-label">Gudang</label>
                    <select className="field-input" value={form.warehouse}
                        onChange={(e) => { setForm({ ...form, warehouse: e.target.value }); setCounts({}); }}>
                        <option value="RM">Raw Material</option>
                        <option value="FG">Finished Goods</option>
                        <option value="GENERAL">General Store</option>
                    </select>
                </div>
                <div>
                    <label className="field-label">Alasan</label>
                    <input className="field-input" value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} />
                </div>
            </div>

            <p className="mb-2 text-xs text-slate-400">
                Isi kolom Hasil Hitung hanya untuk item yang benar-benar dihitung. Baris yang dikosongkan tidak ikut tersimpan.
            </p>

            <div className="max-h-96 overflow-auto rounded-md border border-slate-200">
                <table className="w-full text-sm">
                    <thead className="sticky top-0 bg-slate-50">
                        <tr className="text-left text-xs font-semibold text-slate-500">
                            <th className="px-2 py-2">Item</th>
                            <th className="px-2 py-2 w-28">Sistem</th>
                            <th className="px-2 py-2 w-32">Hasil Hitung</th>
                            <th className="px-2 py-2 w-28">Selisih</th>
                            <th className="px-2 py-2 w-32">Harga Satuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {stock.isLoading && <tr><td colSpan={5} className="px-2 py-6 text-center text-slate-400">Memuat stok sistem…</td></tr>}
                        {!stock.isLoading && rows.length === 0 && (
                            <tr><td colSpan={5} className="px-2 py-6 text-center text-slate-400">Tidak ada stok tercatat di gudang ini.</td></tr>
                        )}
                        {rows.map((r) => {
                            const c = counts[r.item_id];
                            const diff = c === undefined || c === '' ? null : Number(c) - Number(r.qty_system || 0);
                            return (
                                <tr key={r.item_id} className="border-t border-slate-100">
                                    <td className="px-2 py-1.5">{r.item_code}<div className="text-xs text-slate-400">{r.part_name}</div></td>
                                    <td className="px-2 py-1.5">{money(r.qty_system)}</td>
                                    <td className="px-2 py-1.5">
                                        <input type="number" step="0.01" className="field-input" value={c ?? ''}
                                            onChange={(e) => setCounts({ ...counts, [r.item_id]: e.target.value })} />
                                    </td>
                                    <td className={`px-2 py-1.5 font-medium ${diff > 0 ? 'text-emerald-700' : diff < 0 ? 'text-red-700' : 'text-slate-400'}`}>
                                        {diff === null ? '—' : `${diff > 0 ? '+' : ''}${money(diff)}`}
                                    </td>
                                    <td className="px-2 py-1.5">
                                        <input type="number" step="0.01" className="field-input" value={costs[r.item_id] ?? ''}
                                            onChange={(e) => setCosts({ ...costs, [r.item_id]: e.target.value })} />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </Modal>
    );
}

function DetailModal({ id, onClose }) {
    const q = useQuery({
        queryKey: ['stock-adjustments', id],
        queryFn: async () => (await api.get(`/stock-adjustments/${id}`)).data.data,
    });
    const d = q.data;

    return (
        <Modal open onClose={onClose} size="max-w-3xl" title="Detail Sheet"
            footer={<button className="btn btn-ghost" onClick={onClose}>Tutup</button>}>
            {q.isLoading && <div className="py-6 text-center text-slate-400">Memuat…</div>}
            {d && (
                <div className="space-y-3 text-sm">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div><div className="text-xs text-slate-400">Kode</div><div className="font-medium">{d.code}</div></div>
                        <div><div className="text-xs text-slate-400">Tanggal</div><div>{(d.date || '').slice(0, 10)}</div></div>
                        <div><div className="text-xs text-slate-400">Jenis</div><div>{d.adj_type}</div></div>
                        <div><div className="text-xs text-slate-400">Status</div><StatusBadge status={d.status} /></div>
                    </div>
                    {d.reason && <div className="rounded bg-slate-50 px-3 py-2 text-slate-600">{d.reason}</div>}
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">Item</th><th className="px-2 py-2">Sistem</th>
                                <th className="px-2 py-2">Hitung</th><th className="px-2 py-2">Selisih</th><th className="px-2 py-2">Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(d.detail || []).map((l) => (
                                <tr key={l.id} className="border-t border-slate-100">
                                    <td className="px-2 py-2">{l.item?.code}<div className="text-xs text-slate-400">{l.item?.part_name}</div></td>
                                    <td className="px-2 py-2">{money(l.qty_system)}</td>
                                    <td className="px-2 py-2">{money(l.qty_counted)}</td>
                                    <td className={`px-2 py-2 font-medium ${l.qty_diff > 0 ? 'text-emerald-700' : l.qty_diff < 0 ? 'text-red-700' : ''}`}>
                                        {l.qty_diff > 0 ? '+' : ''}{money(l.qty_diff)}
                                    </td>
                                    <td className="px-2 py-2">{money(l.qty_diff * l.unit_cost)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Modal>
    );
}
