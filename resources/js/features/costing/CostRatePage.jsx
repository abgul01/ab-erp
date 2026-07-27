import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, useOptions, money } from '../procurement/common';

const ym = () => new Date().toISOString().slice(0, 7).replace('-', '');
const EMPTY = { period: ym(), rate_type: 'LABOR', process_id: '', rate_per_hour: 0 };

/**
 * Cost rates: labor / factory-overhead per hour, by period and optionally per
 * process. COGM prefers the process-specific rate, then the "SEMUA" fallback.
 */
export default function CostRatePage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');

    const processes = useOptions('processes');
    const list = useQuery({ queryKey: ['cost-rates', { page }], queryFn: async () => (await api.get('/cost-rates', { params: { page, per_page: 50 } })).data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['cost-rates'] });

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/cost-rates/${modal.id}`, p) : api.post('/cost-rates', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/cost-rates/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = (r) => { setForm({ period: r.period, rate_type: r.rate_type, process_id: r.process_id || '', rate_per_hour: r.rate_per_hour }); setError(''); setModal({ mode: 'edit', id: r.id }); };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const submit = () => { setError(''); save.mutate({ ...form, process_id: form.process_id || null }); };

    const columns = [
        { key: 'period', label: 'Periode' },
        { key: 'rate_type', label: 'Jenis', render: (v) => <span className={`rounded px-1.5 py-0.5 text-xs ${v === 'LABOR' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'}`}>{v}</span> },
        { key: 'process_code', label: 'Proses' },
        { key: 'rate_per_hour', label: 'Tarif / Jam', className: 'text-right', render: (v) => `Rp ${money(v)}` },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Tarif Biaya (Labor / FOH)</h1>
                    <p className="text-xs text-slate-400">Tarif per jam untuk menghitung COGM. Proses kosong = berlaku untuk semua proses.</p>
                </div>
                {can('cost-rates', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Tarif</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('cost-rates', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {can('cost-rates', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus tarif?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Tarif`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit} disabled={save.isPending}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label className="field-label">Periode (YYYYMM) <span className="text-red-500">*</span></label><input className="field-input" maxLength={6} value={form.period} onChange={(e) => set('period', e.target.value)} placeholder="202607" /></div>
                    <div>
                        <label className="field-label">Jenis <span className="text-red-500">*</span></label>
                        <select className="field-input" value={form.rate_type} onChange={(e) => set('rate_type', e.target.value)}>
                            <option value="LABOR">LABOR (tenaga kerja)</option>
                            <option value="FOH">FOH (overhead pabrik)</option>
                        </select>
                    </div>
                    <div><label className="field-label">Proses (opsional)</label><Select value={form.process_id} onChange={(v) => set('process_id', v)} options={processes.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name_p}`} placeholder="— semua proses —" /></div>
                    <div><label className="field-label">Tarif per Jam (Rp) <span className="text-red-500">*</span></label><input type="number" step="0.01" className="field-input" value={form.rate_per_hour} onChange={(e) => set('rate_per_hour', e.target.value)} /></div>
                </div>
            </Modal>
        </div>
    );
}
