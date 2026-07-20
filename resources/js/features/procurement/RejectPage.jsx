import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { StatusBadge, CellInput } from './common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), gr_id: '', item_id: '', qty: 1, reason: '', gr_items: [] };

export default function RejectPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');

    const grs = useQuery({
        queryKey: ['grn', 'for-reject'],
        queryFn: async () => (await api.get('/grn', { params: { per_page: 300 } })).data.data,
        staleTime: 30_000,
    });

    const list = useQuery({
        queryKey: ['gr-rejects', { page }],
        queryFn: async () => (await api.get('/gr-rejects', { params: { page, per_page: 15 } })).data,
    });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['gr-rejects'] }); qc.invalidateQueries({ queryKey: ['po'] }); };

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/gr-rejects/${modal.id}`, payload) : api.post('/gr-rejects', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({
        mutationFn: async ({ id, action }) => api.post(`/gr-rejects/${id}/${action}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/gr-rejects/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const loadGrItems = async (grId) => {
        if (!grId) return [];
        const { data } = await api.get(`/grn/${grId}`);
        return (data.data.detail || []).map((d) => ({ item_id: d.item_id, label: `${d.item?.code || ''} — ${d.item?.part_name || ''}`, qty: d.qty }));
    };
    const onSelectGr = async (grId) => {
        const gr_items = await loadGrItems(grId);
        setForm((f) => ({ ...f, gr_id: grId, gr_items, item_id: gr_items.length === 1 ? gr_items[0].item_id : '' }));
    };

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/gr-rejects/${row.id}`);
        const d = data.data;
        const gr_items = await loadGrItems(d.gr_id);
        setForm({ date: d.date?.slice(0, 10), gr_id: d.gr_id, item_id: d.item_id, qty: d.qty, reason: d.reason || '', gr_items });
        setModal({ mode: 'edit', id: row.id });
    };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const columns = [
        { key: 'code', label: 'No. Reject' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'gr', label: 'GR', render: (v) => v?.code || '—' },
        { key: 'ven', label: 'Vendor', render: (v) => v?.company_n || '—' },
        { key: 'item', label: 'Item', render: (v) => (v ? `${v.code} — ${v.part_name}` : '—') },
        { key: 'qty', label: 'Qty' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">GR Reject / Retur Vendor</h1>
                {can('gr-rejects', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Reject</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {row.status === 'DRAFT' && can('gr-rejects', 'edit') && (<>
                            <button title="Tandai RETURNED (barang dikembalikan; PO dibuka lagi)" className="rounded p-1.5 text-amber-600 hover:bg-amber-50" onClick={() => window.confirm('Tandai barang sudah diretur ke vendor? qty_received PO akan dikurangi.') && act.mutate({ id: row.id, action: 'return' })}><Icon name="undo" /></button>
                            <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>
                        </>)}
                        {row.status === 'RETURNED' && can('gr-rejects', 'edit') && (
                            <button title="Tandai CLAIMED" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate({ id: row.id, action: 'claim' })}><Icon name="check" /></button>
                        )}
                        {row.status === 'DRAFT' && can('gr-rejects', 'delete') && (
                            <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus reject?') && remove.mutate(row.id)}><Icon name="trash" /></button>
                        )}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} GR Reject`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); const { gr_items, ...payload } = form; save.mutate(payload); }} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div><label className="field-label">GR <span className="text-red-500">*</span></label>
                        <select className="field-input" value={form.gr_id} onChange={(e) => onSelectGr(Number(e.target.value) || '')}>
                            <option value="">— pilih GR —</option>
                            {(grs.data || []).map((g) => <option key={g.id} value={g.id}>{g.code} — {g.po_no} · {g.ven?.company_n}</option>)}
                        </select>
                    </div>
                    <div><label className="field-label">Item <span className="text-red-500">*</span></label>
                        <select className="field-input" value={form.item_id} onChange={(e) => set('item_id', Number(e.target.value) || '')} disabled={!form.gr_id}>
                            <option value="">— pilih item —</option>
                            {(form.gr_items || []).map((it) => <option key={it.item_id} value={it.item_id}>{it.label} (diterima {it.qty})</option>)}
                        </select>
                    </div>
                    <div><label className="field-label">Qty Reject <span className="text-red-500">*</span></label><CellInput type="number" value={form.qty} onChange={(v) => set('qty', v)} /></div>
                    <div className="sm:col-span-2"><label className="field-label">Alasan</label><textarea className="field-input" rows={2} value={form.reason} onChange={(e) => set('reason', e.target.value)} /></div>
                </div>
            </Modal>
        </div>
    );
}
