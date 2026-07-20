import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { ItemSelect, Select, useOptions, StatusBadge, LineTable, CellInput } from './common';

const PR_TYPES = ['MANUAL', 'MRP', 'ADDITIONAL', 'NON_RM', 'NPD'];
const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), pr_type: 'MANUAL', lines: [] };

export default function PrPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    const uoms = useOptions('uoms');

    const list = useQuery({
        queryKey: ['pr', { page }],
        queryFn: async () => (await api.get('/pr', { params: { page, per_page: 15 } })).data,
    });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['pr'] });

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/pr/${modal.id}`, payload) : api.post('/pr', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({
        mutationFn: async ({ id, action }) => api.post(`/pr/${id}/${action}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/pr/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/pr/${row.id}`);
        const d = data.data;
        setForm({
            date: d.date?.slice(0, 10), pr_type: d.pr_type,
            lines: (d.detail || []).map((l) => ({ item_id: l.item_id, qty: l.qty, uom_id: l.uom_id, note: l.note })),
        });
        setModal({ mode: 'edit', id: row.id });
    };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const addLine = () => set('lines', [...form.lines, { item_id: '', qty: 1, uom_id: '', note: '' }]);
    const setLine = (i, k, v) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const delLine = (i) => set('lines', form.lines.filter((_, j) => j !== i));

    const columns = [
        { key: 'code', label: 'No. PR' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'pr_type', label: 'Tipe' },
        { key: 'detail_count', label: '# Item' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Purchase Requisition</h1>
                {can('pr', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah PR</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {row.status === 'DRAFT' && can('pr', 'edit') && (
                            <button title="Submit" className="rounded p-1.5 text-amber-600 hover:bg-amber-50" onClick={() => act.mutate({ id: row.id, action: 'submit' })}><Icon name="send" /></button>
                        )}
                        {row.status === 'SUBMITTED' && can('pr', 'edit') && (<>
                            <button title="Approve" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate({ id: row.id, action: 'approve' })}><Icon name="check" /></button>
                            <button title="Reject" className="rounded p-1.5 text-red-600 hover:bg-red-50" onClick={() => window.confirm('Tolak PR ini?') && act.mutate({ id: row.id, action: 'reject' })}><Icon name="ban" /></button>
                        </>)}
                        {row.status === 'DRAFT' && can('pr', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {row.status === 'DRAFT' && can('pr', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus PR?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} PR`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div><label className="field-label">Tipe PR <span className="text-red-500">*</span></label>
                        <select className="field-input" value={form.pr_type} onChange={(e) => set('pr_type', e.target.value)}>
                            {PR_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
                        </select>
                    </div>
                </div>
                <LineTable title="Item Requisition" onAdd={addLine} lines={form.lines} empty="Belum ada item."
                    head={['Item', 'Qty', 'UoM', 'Catatan', '']}
                    row={(l, i) => (<>
                        <td className="min-w-[220px] px-2 py-1.5"><ItemSelect value={l.item_id} onChange={(v) => setLine(i, 'item_id', v)} /></td>
                        <td className="w-24 px-2 py-1.5"><CellInput type="number" value={l.qty} onChange={(v) => setLine(i, 'qty', v)} /></td>
                        <td className="w-32 px-2 py-1.5"><Select value={l.uom_id} onChange={(v) => setLine(i, 'uom_id', v)} options={uoms.data} getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="—" /></td>
                        <td className="px-2 py-1.5"><CellInput value={l.note} onChange={(v) => setLine(i, 'note', v)} /></td>
                        <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                    </>)} />
            </Modal>
        </div>
    );
}
