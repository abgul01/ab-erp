import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { StatusBadge } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);

/**
 * Sales Return — take back goods shipped on a DO. Pick the DO + item, the qty
 * is capped at what was delivered (less prior returns); posting restocks FG.
 */
export default function SalesReturnPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(false);
    const [form, setForm] = useState({ date: today(), do_id: '', item_id: '', qty: 1, reason: '' });
    const [error, setError] = useState('');

    const list = useQuery({ queryKey: ['sales-returns', { page }], queryFn: async () => (await api.get('/sales-returns', { params: { page, per_page: 15 } })).data });
    const dos = useQuery({ queryKey: ['sales-returns', 'shipped-dos'], queryFn: async () => (await api.get('/sales-returns/shipped-dos')).data.data, enabled: modal });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['sales-returns'] }); qc.invalidateQueries({ queryKey: ['stock-fg'] }); };

    const save = useMutation({
        mutationFn: async () => api.post('/sales-returns', { ...form, qty: Number(form.qty) }),
        onSuccess: () => { invalidate(); setModal(false); }, onError: (e) => setError(apiError(e)),
    });
    const post = useMutation({ mutationFn: async (id) => api.post(`/sales-returns/${id}/post`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/sales-returns/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const open = () => { setForm({ date: today(), do_id: '', item_id: '', qty: 1, reason: '' }); setError(''); setModal(true); };
    const selectedDo = (dos.data || []).find((d) => d.id === Number(form.do_id));
    const selectedLine = selectedDo?.lines.find((l) => l.item_id === Number(form.item_id));
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const columns = [
        { key: 'code', label: 'No. Retur' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'do', label: 'DO', render: (v) => v?.code || '—' },
        { key: 'item', label: 'Item', render: (v) => v?.code || '—' },
        { key: 'qty', label: 'Qty', className: 'text-right' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Sales Return</h1>
                {can('sales-returns', 'create') && <button className="btn btn-primary" onClick={open}><Icon name="plus" /> Buat Retur</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {row.status === 'DRAFT' && can('sales-returns', 'edit') && <button title="Posting (restock FG)" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => post.mutate(row.id)}><Icon name="check" /></button>}
                        {row.status === 'DRAFT' && can('sales-returns', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus retur?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={modal} onClose={() => setModal(false)} size="max-w-xl" title="Buat Sales Return"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(false)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(); }} disabled={save.isPending || !form.do_id || !form.item_id || Number(form.qty) < 1}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label className="field-label">Tanggal</label><input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div>
                        <label className="field-label">Delivery Order <span className="text-red-500">*</span></label>
                        <select className="field-input" value={form.do_id} onChange={(e) => setForm((f) => ({ ...f, do_id: e.target.value ? Number(e.target.value) : '', item_id: '' }))}>
                            <option value="">— pilih DO —</option>
                            {(dos.data || []).map((d) => <option key={d.id} value={d.id}>{d.code} — {d.customer}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="field-label">Item <span className="text-red-500">*</span></label>
                        <select className="field-input" value={form.item_id} onChange={(e) => set('item_id', e.target.value ? Number(e.target.value) : '')} disabled={!selectedDo}>
                            <option value="">— pilih item —</option>
                            {(selectedDo?.lines || []).map((l) => <option key={l.item_id} value={l.item_id}>{l.item_code} (sisa {l.returnable})</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="field-label">Qty Retur <span className="text-red-500">*</span></label>
                        <input type="number" min="1" max={selectedLine?.returnable || undefined} className="field-input" value={form.qty} onChange={(e) => set('qty', e.target.value)} />
                        {selectedLine && <p className="mt-1 text-xs text-slate-400">Maks {selectedLine.returnable} (terkirim {selectedLine.delivered}).</p>}
                    </div>
                    <div className="sm:col-span-2"><label className="field-label">Alasan</label><input className="field-input" maxLength={300} value={form.reason} onChange={(e) => set('reason', e.target.value)} placeholder="alasan retur…" /></div>
                </div>
            </Modal>
        </div>
    );
}
