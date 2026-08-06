import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import PickerModal from '../../components/PickerModal';
import { ITEM_COLUMNS } from './PoPage';
import { Select, VendorSelect, useOptions, money } from './common';

const EMPTY = { ven_id: '', item_id: '', process_id: '', price: 0, active: true };

/**
 * Master Subcont — barang apa saja (item + proses + harga) yang dikerjakan tiap
 * vendor subcont. Dipakai sebagai daftar item saat membuat PO Subcont.
 */
export default function SubcontItemPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    const [itemPicker, setItemPicker] = useState(false);

    const processes = useOptions('processes');
    const items = useOptions('items');
    const itemById = Object.fromEntries((items.data || []).map((it) => [it.id, it]));
    const list = useQuery({ queryKey: ['subcont-items', { page }], queryFn: async () => (await api.get('/subcont-items', { params: { page, per_page: 20 } })).data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['subcont-items'] });

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/subcont-items/${modal.id}`, p) : api.post('/subcont-items', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/subcont-items/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = (r) => { setForm({ ven_id: r.ven_id, item_id: r.item_id, process_id: r.process_id || '', price: r.price, active: !!r.active }); setError(''); setModal({ mode: 'edit', id: r.id }); };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const submit = () => { setError(''); save.mutate({ ...form, process_id: form.process_id || null }); };

    const columns = [
        { key: 'ven', label: 'Vendor', render: (v) => v?.company_n || '—' },
        { key: 'item', label: 'Barang (Item)', render: (v) => (v ? `${v.code} — ${v.part_name}` : '—') },
        { key: 'process', label: 'Proses', render: (v) => (v ? `${v.code} — ${v.name_p}` : '— (umum)') },
        { key: 'price', label: 'Harga/pc', className: 'text-right', render: (v) => money(v) },
        { key: 'active', label: 'Aktif', render: (v) => (v ? '✓' : '—') },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Master Subcont</h1>
                    <p className="text-xs text-slate-400">Barang & proses yang dikerjakan tiap vendor subcont, beserta harganya. Dipakai saat membuat PO Subcont.</p>
                </div>
                {can('subcont-items', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('subcont-items', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {can('subcont-items', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus dari master?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Master Subcont`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit} disabled={save.isPending || !form.ven_id || !form.item_id}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2"><label className="field-label">Vendor Subcont <span className="text-red-500">*</span></label><VendorSelect value={form.ven_id} onChange={(v) => set('ven_id', v)} /></div>
                    <div className="sm:col-span-2">
                        <label className="field-label">Barang (Item) <span className="text-red-500">*</span></label>
                        <button type="button" className="field-input flex w-full items-center justify-between gap-2 text-left" onClick={() => setItemPicker(true)}>
                            <span className="truncate">{form.item_id ? (itemById[form.item_id] ? `${itemById[form.item_id].code} — ${itemById[form.item_id].part_name}` : `#${form.item_id}`) : <span className="text-slate-400">— pilih item —</span>}</span>
                            <Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" />
                        </button>
                    </div>
                    <div><label className="field-label">Proses (opsional)</label><Select value={form.process_id} onChange={(v) => set('process_id', v)} options={processes.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name_p}`} placeholder="— umum —" /></div>
                    <div><label className="field-label">Harga per pc (Rp)</label><input type="number" step="0.01" className="field-input" value={form.price} onChange={(e) => set('price', e.target.value)} /></div>
                    <div className="sm:col-span-2"><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.active} onChange={(e) => set('active', e.target.checked)} /> Aktif</label></div>
                </div>
            </Modal>

            <PickerModal open={itemPicker} onClose={() => setItemPicker(false)} title="Pilih Barang" rows={(items.data || []).map((it) => ({ ...it, _key: it.id }))} searchKeys={['code', 'part_name']} columns={ITEM_COLUMNS} onSelect={(row) => set('item_id', row.id)} />
        </div>
    );
}
