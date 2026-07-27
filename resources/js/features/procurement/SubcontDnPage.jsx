import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { StatusBadge } from './common';

const today = () => new Date().toISOString().slice(0, 10);

/**
 * Subcont — Kirim (Delivery Note). Send goods to the subcontract vendor against
 * a SUBCONT purchase order; qty per line is capped at the PO's outstanding.
 */
export default function SubcontDnPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(false);
    const [poId, setPoId] = useState('');
    const [date, setDate] = useState(today());
    const [lines, setLines] = useState([]);   // {item_id,item_code,part_name,qty_order,sent,qty}
    const [error, setError] = useState('');
    const [view, setView] = useState(null);

    const list = useQuery({ queryKey: ['subcont-dn', { page }], queryFn: async () => (await api.get('/subcont/dn', { params: { page, per_page: 15 } })).data });
    const pos = useQuery({ queryKey: ['subcont-dn', 'pos'], queryFn: async () => (await api.get('/subcont/pos')).data.data, enabled: modal });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['subcont-dn'] });

    const save = useMutation({
        mutationFn: async () => api.post('/subcont/dn', { date, po_id: poId, lines: lines.filter((l) => Number(l.qty) > 0).map((l) => ({ item_id: l.item_id, qty: Number(l.qty) })) }),
        onSuccess: () => { invalidate(); setModal(false); }, onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({ mutationFn: async (id) => api.post(`/subcont/dn/${id}/send`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/subcont/dn/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const open = () => { setPoId(''); setDate(today()); setLines([]); setError(''); setModal(true); };
    const onSelectPo = (id) => {
        setPoId(id);
        const po = (pos.data || []).find((p) => p.id === Number(id));
        setLines((po?.lines || []).map((l) => ({ ...l, qty: Math.max(0, l.qty_order - l.sent) })));
    };
    const setQty = (i, v) => setLines((ls) => ls.map((l, j) => (j === i ? { ...l, qty: v } : l)));
    const openView = async (row) => { const { data } = await api.get(`/subcont/dn/${row.id}`); setView(data.data); };

    const columns = [
        { key: 'code', label: 'No. DN' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'po', label: 'PO Subcont', render: (v) => v?.code || '—' },
        { key: 'ven', label: 'Vendor', render: (v) => v?.company_n || '—' },
        { key: 'detail_count', label: '# Item' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Subcont — Kirim (Delivery Note)</h1>
                {can('subcont-dn', 'create') && <button className="btn btn-primary" onClick={open}><Icon name="plus" /> Buat DN</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {row.status === 'DRAFT' && can('subcont-dn', 'edit') && <button title="Kirim" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate(row.id)}><Icon name="send" /></button>}
                        {row.status === 'DRAFT' && can('subcont-dn', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus DN?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={modal} onClose={() => setModal(false)} size="max-w-3xl" title="Buat Delivery Note Subcont"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(false)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(); }} disabled={save.isPending || !poId || !lines.some((l) => Number(l.qty) > 0)}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="sm:col-span-2">
                        <label className="field-label">PO Subcont <span className="text-red-500">*</span></label>
                        <select className="field-input" value={poId} onChange={(e) => onSelectPo(e.target.value ? Number(e.target.value) : '')}>
                            <option value="">— pilih PO SUBCONT —</option>
                            {(pos.data || []).map((p) => <option key={p.id} value={p.id}>{p.code} — {p.vendor}</option>)}
                        </select>
                    </div>
                    <div><label className="field-label">Tanggal</label><input type="date" className="field-input" value={date} onChange={(e) => setDate(e.target.value)} /></div>
                </div>
                <div className="overflow-x-auto rounded-md border border-slate-200">
                    <table className="w-full text-sm">
                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Item</th><th className="px-2 py-2 text-right">Order</th><th className="px-2 py-2 text-right">Sudah Kirim</th><th className="px-2 py-2 text-right">Kirim</th></tr></thead>
                        <tbody>
                            {!poId && <tr><td colSpan={4} className="px-2 py-6 text-center text-slate-400">Pilih PO dulu.</td></tr>}
                            {lines.map((l, i) => {
                                const max = l.qty_order - l.sent;
                                return (
                                    <tr key={l.item_id} className="border-t border-slate-100">
                                        <td className="px-2 py-1.5"><span className="font-medium">{l.item_code}</span> <span className="text-slate-400">{l.part_name}</span></td>
                                        <td className="px-2 py-1.5 text-right">{l.qty_order}</td>
                                        <td className="px-2 py-1.5 text-right">{l.sent}</td>
                                        <td className="px-2 py-1.5 text-right"><input type="number" min="0" max={max} className={`field-input w-24 text-right ${Number(l.qty) > max ? 'border-red-400' : ''}`} value={l.qty} onChange={(e) => setQty(i, e.target.value)} /></td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </Modal>

            <Modal open={!!view} onClose={() => setView(null)} size="max-w-2xl" title={`DN ${view?.code || ''} — ${view?.status || ''}`}>
                <div className="mb-2 text-sm text-slate-500">PO {view?.po?.code} · {view?.ven?.company_n}</div>
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Item</th><th className="px-2 py-2 text-right">Qty</th></tr></thead>
                    <tbody>{(view?.detail || []).map((d) => <tr key={d.id} className="border-t border-slate-100"><td className="px-2 py-1.5">{d.item?.code} — {d.item?.part_name}</td><td className="px-2 py-1.5 text-right">{d.qty}</td></tr>)}</tbody>
                </table>
            </Modal>
        </div>
    );
}
