import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { useOptions, money } from './common';

const EMPTY = { code: '', descrip: '', hs_code: '', total_ton: '', valid_from: '', valid_to: '', active: true, item_ids: [] };

export default function QuotaPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    const items = useOptions('items');

    const list = useQuery({
        queryKey: ['quotas', { page }],
        queryFn: async () => (await api.get('/quotas', { params: { page, per_page: 15 } })).data,
    });

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/quotas/${modal.id}`, payload) : api.post('/quotas', payload)),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['quotas'] }); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/quotas/${id}`),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['quotas'] }),
        onError: (e) => alert(apiError(e)),
    });

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/quotas/${row.id}`);
        const q = data.data;
        setForm({
            code: q.code, descrip: q.descrip || '', hs_code: q.hs_code || '', total_ton: q.total_ton,
            valid_from: q.valid_from?.slice(0, 10) || '', valid_to: q.valid_to?.slice(0, 10) || '',
            active: !!q.active, item_ids: (q.items || []).map((i) => i.item_id),
        });
        setModal({ mode: 'edit', id: row.id });
    };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const toggleItem = (id) => set('item_ids', form.item_ids.includes(id) ? form.item_ids.filter((x) => x !== id) : [...form.item_ids, id]);

    const columns = [
        { key: 'code', label: 'Kode / PI' },
        { key: 'descrip', label: 'Deskripsi' },
        { key: 'hs_code', label: 'HS Code' },
        { key: 'total_ton', label: 'Total (ton)', render: (v) => money(v) },
        { key: 'balance_ton', label: 'Sisa (ton)', render: (v) => <span className="font-semibold text-emerald-700">{money(v)}</span> },
        { key: 'items_count', label: '# Item' },
        { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Import Quota</h1>
                {can('quotas', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Kuota</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('quotas', 'edit') && <button className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {can('quotas', 'delete') && <button className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus kuota?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Kuota Impor`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label className="field-label">Kode / No. PI <span className="text-red-500">*</span></label><input className="field-input" value={form.code} onChange={(e) => set('code', e.target.value)} /></div>
                    <div><label className="field-label">HS Code</label><input className="field-input" value={form.hs_code} onChange={(e) => set('hs_code', e.target.value)} /></div>
                    <div className="sm:col-span-2"><label className="field-label">Deskripsi</label><input className="field-input" value={form.descrip} onChange={(e) => set('descrip', e.target.value)} /></div>
                    <div><label className="field-label">Total Kuota (ton) <span className="text-red-500">*</span></label><input type="number" step="0.001" className="field-input" value={form.total_ton} onChange={(e) => set('total_ton', e.target.value)} /></div>
                    <label className="mt-6 inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" className="h-4 w-4" checked={form.active} onChange={(e) => set('active', e.target.checked)} /> Aktif</label>
                    <div><label className="field-label">Berlaku Dari <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.valid_from} onChange={(e) => set('valid_from', e.target.value)} /></div>
                    <div><label className="field-label">Berlaku Sampai <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.valid_to} onChange={(e) => set('valid_to', e.target.value)} /></div>
                </div>
                <div className="mt-5">
                    <h4 className="mb-2 text-sm font-semibold text-slate-700">Item yang tercakup kuota</h4>
                    <div className="max-h-52 overflow-y-auto rounded-md border border-slate-200 p-2">
                        {(items.data || []).map((it) => (
                            <label key={it.id} className="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-slate-50">
                                <input type="checkbox" className="h-4 w-4" checked={form.item_ids.includes(it.id)} onChange={() => toggleItem(it.id)} />
                                {it.code} — {it.part_name}
                            </label>
                        ))}
                        {(items.data || []).length === 0 && <p className="p-2 text-sm text-slate-400">Tidak ada item.</p>}
                    </div>
                </div>
            </Modal>
        </div>
    );
}
