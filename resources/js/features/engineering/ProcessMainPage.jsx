import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, useOptions, LineTable, CellInput } from '../procurement/common';

const EMPTY = { code: '', name: '', active: true, steps: [] };

/**
 * Master Routing (Master Process Main) — build a reusable, ordered set of
 * processes once; items then point at it instead of re-entering the sequence.
 */
export default function ProcessMainPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');

    const processes = useOptions('processes');
    const list = useQuery({ queryKey: ['process-mains', { page }], queryFn: async () => (await api.get('/process-mains', { params: { page, per_page: 15 } })).data });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['process-mains'] }); qc.invalidateQueries({ queryKey: ['options', 'process-mains'] }); };

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/process-mains/${modal.id}`, p) : api.post('/process-mains', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/process-mains/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/process-mains/${row.id}`);
        const d = data.data;
        setForm({
            code: d.code, name: d.name, active: !!d.active,
            steps: [...(d.detail || [])].sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0)).map((s) => ({ proc_id: s.proc_id, sequence: s.sequence })),
        });
        setModal({ mode: 'edit', id: row.id });
    };

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const addStep = () => set('steps', [...form.steps, { proc_id: '', sequence: form.steps.length + 1 }]);
    const setStep = (i, k, v) => set('steps', form.steps.map((s, j) => (j === i ? { ...s, [k]: v } : s)));
    const delStep = (i) => set('steps', form.steps.filter((_, j) => j !== i).map((s, j) => ({ ...s, sequence: j + 1 })));

    const submit = () => {
        setError('');
        save.mutate({ ...form, steps: form.steps.map((s, i) => ({ proc_id: s.proc_id, sequence: s.sequence ?? i + 1 })) });
    };

    const columns = [
        { key: 'code', label: 'Kode' },
        { key: 'name', label: 'Nama Routing' },
        { key: 'detail_count', label: '# Langkah' },
        { key: 'active', label: 'Aktif', render: (v) => (v ? <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">Aktif</span> : <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Nonaktif</span>) },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Master Routing</h1>
                    <p className="text-xs text-slate-400">Template urutan proses yang dipakai bersama banyak item FG.</p>
                </div>
                {can('process-mains', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Routing</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('process-mains', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {can('process-mains', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus routing?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} size="max-w-3xl" title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Routing`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit} disabled={save.isPending || !form.code || form.steps.length === 0}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div><label className="field-label">Kode <span className="text-red-500">*</span></label><input className="field-input" maxLength={50} value={form.code} onChange={(e) => set('code', e.target.value)} placeholder="mis. RT-3STEP" /></div>
                    <div className="sm:col-span-2"><label className="field-label">Nama <span className="text-red-500">*</span></label><input className="field-input" maxLength={100} value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="Cut → Machining → Chamfer" /></div>
                    <div className="flex items-end"><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.active} onChange={(e) => set('active', e.target.checked)} /> Aktif</label></div>
                </div>

                <LineTable title="Langkah Proses" subtitle="Urutan proses produksi (langkah 1 = paling awal / cutting)." onAdd={addStep} lines={form.steps}
                    empty="Belum ada langkah." head={['Urutan', 'Proses', '']}
                    row={(l, i) => (<>
                        <td className="w-24 px-2 py-1.5"><CellInput type="number" value={l.sequence ?? i + 1} onChange={(v) => setStep(i, 'sequence', v)} /></td>
                        <td className="min-w-[280px] px-2 py-1.5"><Select value={l.proc_id} onChange={(v) => setStep(i, 'proc_id', v)} options={processes.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name_p}`} placeholder="— pilih proses —" /></td>
                        <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delStep(i)}><Icon name="trash" /></button></td>
                    </>)} />
            </Modal>
        </div>
    );
}
