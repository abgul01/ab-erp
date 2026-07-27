import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';

const EMPTY = { code: '', name: '', useful_life: 48, depr_method: 'STRAIGHT' };

/** Asset categories: default useful life (months) + depreciation method. */
export default function AssetCategoryPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');

    const list = useQuery({ queryKey: ['asset-categs', { page }], queryFn: async () => (await api.get('/asset-categs', { params: { page, per_page: 50 } })).data });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['asset-categs'] }); qc.invalidateQueries({ queryKey: ['options', 'asset-categs'] }); };

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/asset-categs/${modal.id}`, p) : api.post('/asset-categs', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/asset-categs/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = (r) => { setForm({ code: r.code, name: r.name, useful_life: r.useful_life, depr_method: r.depr_method || 'STRAIGHT' }); setError(''); setModal({ mode: 'edit', id: r.id }); };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const columns = [
        { key: 'code', label: 'Kode' },
        { key: 'name', label: 'Nama Kategori' },
        { key: 'useful_life', label: 'Umur (bulan)', className: 'text-right' },
        { key: 'depr_method', label: 'Metode' },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Kategori Aset</h1>
                    <p className="text-xs text-slate-400">Umur manfaat default (bulan) dan metode depresiasi.</p>
                </div>
                {can('asset-categs', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Kategori</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('asset-categs', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {can('asset-categs', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus kategori?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Kategori Aset`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label className="field-label">Kode <span className="text-red-500">*</span></label><input className="field-input" maxLength={20} value={form.code} onChange={(e) => set('code', e.target.value)} placeholder="MSN" /></div>
                    <div><label className="field-label">Nama <span className="text-red-500">*</span></label><input className="field-input" maxLength={100} value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="Mesin Produksi" /></div>
                    <div><label className="field-label">Umur manfaat (bulan) <span className="text-red-500">*</span></label><input type="number" min="1" className="field-input" value={form.useful_life} onChange={(e) => set('useful_life', e.target.value)} /></div>
                    <div><label className="field-label">Metode</label><select className="field-input" value={form.depr_method} onChange={(e) => set('depr_method', e.target.value)}><option value="STRAIGHT">STRAIGHT (garis lurus)</option></select></div>
                </div>
            </Modal>
        </div>
    );
}
