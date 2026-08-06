import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import PickerModal from '../../components/PickerModal';
import { ITEM_COLUMNS } from '../procurement/PoPage';
import { Select, useOptions, CellInput, money } from '../procurement/common';
import MonthPicker, { formatPeriod } from '../../components/MonthPicker';

const thisPeriod = () => { const d = new Date(); return `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}`; };
const VERSIONS = ['FINAL', 'N-1', 'N-2', 'N-3'];

export default function ForecastPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(null);
    const [itemPicker, setItemPicker] = useState(false);
    const [error, setError] = useState('');

    const contacts = useOptions('contacts');
    const items = useOptions('items');
    const itemById = Object.fromEntries((items.data || []).map((it) => [it.id, it]));

    const list = useQuery({ queryKey: ['forecasts', { page }], queryFn: async () => (await api.get('/forecasts', { params: { page, per_page: 15 } })).data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['forecasts'] });

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/forecasts/${modal.id}`, p) : api.post('/forecasts', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/forecasts/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setForm({ period: thisPeriod(), cus_id: '', item_id: '', version: 'FINAL', qty: 0 }); setError(''); setModal({ mode: 'create' }); };
    const openEdit = (r) => { setForm({ period: r.period, cus_id: r.cus_id, item_id: r.item_id, version: r.version, qty: r.qty }); setError(''); setModal({ mode: 'edit', id: r.id }); };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const columns = [
        { key: 'period', label: 'Periode', render: formatPeriod },
        { key: 'cus', label: 'Customer', render: (v) => v?.company_n || '—' },
        { key: 'item', label: 'Item', render: (v) => (v ? `${v.code} — ${v.part_name}` : '—') },
        { key: 'version', label: 'Versi' },
        { key: 'qty', label: 'Qty', render: (v) => money(v) },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Forecast Customer</h1>
                {can('forecasts', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Forecast</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('forecasts', 'edit') && <button className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {can('forecasts', 'delete') && <button className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus forecast?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Forecast`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {form && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><label className="field-label">Periode <span className="text-red-500">*</span></label><MonthPicker value={form.period} onChange={(v) => set('period', v)} className="w-full" /></div>
                        <div><label className="field-label">Versi <span className="text-red-500">*</span></label>
                            <select className="field-input" value={form.version} onChange={(e) => set('version', e.target.value)}>{VERSIONS.map((v) => <option key={v} value={v}>{v}</option>)}</select></div>
                        <div><label className="field-label">Customer <span className="text-red-500">*</span></label>
                            <Select value={form.cus_id} onChange={(v) => set('cus_id', v)} options={contacts.data} getValue={(o) => o.id} getLabel={(o) => o.company_n} placeholder="— pilih —" /></div>
                        <div><label className="field-label">Item <span className="text-red-500">*</span></label>
                            <button type="button" className="field-input flex w-full items-center justify-between gap-2 text-left" onClick={() => setItemPicker(true)}>
                                <span className="truncate">{form.item_id ? (itemById[form.item_id] ? `${itemById[form.item_id].code} — ${itemById[form.item_id].part_name}` : `#${form.item_id}`) : <span className="text-slate-400">— pilih item —</span>}</span>
                                <Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" />
                            </button></div>
                        <div><label className="field-label">Qty <span className="text-red-500">*</span></label><CellInput type="number" value={form.qty} onChange={(v) => set('qty', v)} /></div>
                    </div>
                )}
            </Modal>

            <PickerModal open={itemPicker} onClose={() => setItemPicker(false)} title="Pilih Item" rows={(items.data || []).map((it) => ({ ...it, _key: it.id }))} searchKeys={['code', 'part_name']} columns={ITEM_COLUMNS} onSelect={(row) => set('item_id', row.id)} />
        </div>
    );
}
