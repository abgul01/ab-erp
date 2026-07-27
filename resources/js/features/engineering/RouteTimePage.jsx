import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import PickerModal from '../../components/PickerModal';
import { ITEM_COLUMNS } from '../procurement/PoPage';
import { Select, useOptions, CellInput } from '../procurement/common';

/**
 * Cycle-time master: item × process × machine → seconds/piece + priority.
 * The slowest process (bottleneck) sets an item's daily throughput, which the
 * MPS generator uses to spread the monthly plan across working days.
 */
export default function RouteTimePage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(null);
    const [itemPicker, setItemPicker] = useState(false);
    const [error, setError] = useState('');

    const processes = useOptions('processes');
    const machines = useOptions('machines');
    const items = useOptions('items');
    const itemById = Object.fromEntries((items.data || []).map((it) => [it.id, it]));

    // processes allowed for the selected item = the steps of its routing templates
    const itemProcs = useQuery({
        queryKey: ['route-times', 'item-procs', form?.item_id],
        queryFn: async () => (await api.get(`/route-times/item-procs/${form.item_id}`)).data.data,
        enabled: !!form?.item_id,
    });
    const procOptions = (form?.item_id && itemProcs.data?.length) ? itemProcs.data : processes.data;

    const list = useQuery({ queryKey: ['route-times', { page }], queryFn: async () => (await api.get('/route-times', { params: { page, per_page: 20 } })).data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['route-times'] });

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/route-times/${modal.id}`, p) : api.post('/route-times', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/route-times/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setForm({ item_id: '', proc_id: '', machine_id: '', cycle_sec: 0, setup_min: 0, priority: 1, active: 1 }); setError(''); setModal({ mode: 'create' }); };
    const openEdit = (r) => { setForm({ item_id: r.item_id, proc_id: r.proc_id, machine_id: r.machine_id || '', cycle_sec: r.cycle_sec, setup_min: r.setup_min, priority: r.priority, active: r.active }); setError(''); setModal({ mode: 'edit', id: r.id }); };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const columns = [
        { key: 'item', label: 'Item', render: (v) => (v ? `${v.code} — ${v.part_name}` : '—') },
        { key: 'process', label: 'Proses', render: (v) => (v ? `${v.code} — ${v.name_p}` : '—') },
        { key: 'machine', label: 'Mesin', render: (v) => (v ? `${v.code} — ${v.name}` : '— (semua)') },
        { key: 'cycle_sec', label: 'Cycle (dtk/pc)', render: (v) => Number(v).toLocaleString('id-ID') },
        { key: 'setup_min', label: 'Setup (min)', render: (v) => Number(v).toLocaleString('id-ID') },
        { key: 'priority', label: 'Prioritas' },
        { key: 'active', label: 'Aktif', render: (v) => (v ? '✓' : '—') },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Cycle Time (Routing per Mesin)</h1>
                {can('route-times', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Cycle Time</button>}
            </div>
            <p className="mb-3 text-xs text-slate-400">Waktu proses per pcs untuk tiap item di mesin tertentu. Prioritas 1 = mesin utama. Proses paling lambat menentukan kapasitas harian (dipakai Generate MPS).</p>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('route-times', 'edit') && <button className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {can('route-times', 'delete') && <button className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus cycle time?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Cycle Time`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {modal && form && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2"><label className="field-label">Item <span className="text-red-500">*</span></label>
                            <button type="button" className="field-input flex w-full items-center justify-between gap-2 text-left" onClick={() => setItemPicker(true)}>
                                <span className="truncate">{form.item_id ? (itemById[form.item_id] ? `${itemById[form.item_id].code} — ${itemById[form.item_id].part_name}` : `#${form.item_id}`) : <span className="text-slate-400">— pilih item —</span>}</span>
                                <Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" />
                            </button></div>
                        <div><label className="field-label">Proses <span className="text-red-500">*</span></label>
                            <Select value={form.proc_id} onChange={(v) => set('proc_id', v)} options={procOptions} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name_p}`} placeholder={form.item_id ? '— pilih proses —' : '— pilih item dulu —'} />
                            {form.item_id && itemProcs.data && itemProcs.data.length === 0 && <p className="mt-1 text-xs text-amber-600">Item ini belum punya routing — atur di Item Master tab Proses.</p>}
                            {form.item_id && (itemProcs.data?.length ?? 0) > 0 && <p className="mt-1 text-xs text-slate-400">Hanya proses dari routing item ini.</p>}
                        </div>
                        <div><label className="field-label">Mesin (opsional)</label>
                            <Select value={form.machine_id} onChange={(v) => set('machine_id', v)} options={machines.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— semua mesin —" /></div>
                        <div><label className="field-label">Cycle time (detik/pcs) <span className="text-red-500">*</span></label><CellInput type="number" step="0.1" value={form.cycle_sec} onChange={(v) => set('cycle_sec', v)} /></div>
                        <div><label className="field-label">Setup (menit)</label><CellInput type="number" step="0.1" value={form.setup_min} onChange={(v) => set('setup_min', v)} /></div>
                        <div><label className="field-label">Prioritas mesin <span className="text-red-500">*</span></label><CellInput type="number" value={form.priority} onChange={(v) => set('priority', v)} /></div>
                        <div className="self-end"><label className="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" className="h-4 w-4" checked={!!Number(form.active)} onChange={(e) => set('active', e.target.checked ? 1 : 0)} /> Aktif</label></div>
                    </div>
                )}
            </Modal>

            <PickerModal open={itemPicker} onClose={() => setItemPicker(false)} title="Pilih Item" rows={(items.data || []).map((it) => ({ ...it, _key: it.id }))} searchKeys={['code', 'part_name']} columns={ITEM_COLUMNS} onSelect={(row) => setForm((f) => ({ ...f, item_id: row.id, proc_id: '' }))} />
        </div>
    );
}
