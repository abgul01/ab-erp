import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, useOptions, money } from '../procurement/common';
import { MonthRangePicker, currentPeriod, formatPeriod, periodRange } from '../../components/MonthPicker';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { code: '', categ_id: '', name: '', acq_date: today(), acq_cost: 0, useful_life: '', machine_id: '', status: 'ACTIVE' };

/** Fixed assets + straight-line monthly depreciation. */
export default function AssetPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    // Depreciation is often caught up for several months at once after a late
    // close, so the control is a range that defaults to this month alone.
    const [range, setRange] = useState({ from: currentPeriod(), to: currentPeriod() });
    const [view, setView] = useState(null);
    const periods = periodRange(range.from, range.to);

    const categs = useOptions('asset-categs');
    const machines = useOptions('machines');
    const list = useQuery({ queryKey: ['assets', { page }], queryFn: async () => (await api.get('/assets', { params: { page, per_page: 20 } })).data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['assets'] });

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/assets/${modal.id}`, p) : api.post('/assets', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/assets/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const depre = useMutation({
        mutationFn: async () => (await api.post('/assets/depreciate', { periods })).data.data,
        onSuccess: (d) => {
            invalidate();
            const detail = Object.entries(d.by_period || {}).map(([p, n]) => `${formatPeriod(p)}: ${n}`).join('\n');
            alert(`Depresiasi diposting untuk ${d.periods.length} bulan — total ${d.posted} baris.\n${detail}`);
        },
        onError: (e) => alert(apiError(e)),
    });

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = (r) => {
        setForm({ code: r.code, categ_id: r.categ_id, name: r.name, acq_date: r.acq_date?.slice(0, 10), acq_cost: r.acq_cost, useful_life: r.useful_life, machine_id: r.machine_id || '', status: r.status });
        setError(''); setModal({ mode: 'edit', id: r.id });
    };
    const openView = async (r) => { const { data } = await api.get(`/assets/${r.id}`); setView(data.data); };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const submit = () => { setError(''); save.mutate({ ...form, machine_id: form.machine_id || null, useful_life: form.useful_life || null }); };

    const columns = [
        { key: 'code', label: 'Kode' },
        { key: 'name', label: 'Nama Aset' },
        { key: 'categ', label: 'Kategori', render: (v) => v?.name || '—' },
        { key: 'acq_date', label: 'Tgl Perolehan', render: (v) => v?.slice(0, 10) },
        { key: 'acq_cost', label: 'Harga Perolehan', className: 'text-right', render: (v) => money(v) },
        { key: 'monthly', label: 'Depre/bln', className: 'text-right', render: (v) => money(v) },
        { key: 'depre_total', label: 'Akum. Depre', className: 'text-right', render: (v) => money(v) },
        { key: 'book_value', label: 'Nilai Buku', className: 'text-right', render: (v) => <b>{money(v)}</b> },
        { key: 'status', label: 'Status', render: (v) => <span className={`rounded px-1.5 py-0.5 text-xs ${v === 'ACTIVE' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{v}</span> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Aset & Depresiasi</h1>
                    <p className="text-xs text-slate-400">Depresiasi garis lurus: harga perolehan ÷ umur manfaat, diposting per bulan.</p>
                </div>
                <div className="flex flex-wrap items-end gap-2">
                    <MonthRangePicker from={range.from} to={range.to} onChange={setRange} disabled={depre.isPending} />
                    {can('assets', 'create') && <button className="btn btn-ghost" disabled={depre.isPending || periods.length === 0} onClick={() => depre.mutate()}>{depre.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="calculator" />} {periods.length > 1 ? `Hitung Depresiasi (${periods.length} bln)` : 'Hitung Depresiasi'}</button>}
                    {can('assets', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Aset</button>}
                </div>
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Riwayat depresiasi" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {can('assets', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {can('assets', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus aset beserta riwayat depresiasinya?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} size="max-w-2xl" title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Aset`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit} disabled={save.isPending}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label className="field-label">Kode <span className="text-red-500">*</span></label><input className="field-input" maxLength={30} value={form.code} onChange={(e) => set('code', e.target.value)} placeholder="AST-001" /></div>
                    <div><label className="field-label">Kategori <span className="text-red-500">*</span></label><Select value={form.categ_id} onChange={(v) => set('categ_id', v)} options={categs.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— pilih kategori —" /></div>
                    <div className="sm:col-span-2"><label className="field-label">Nama Aset <span className="text-red-500">*</span></label><input className="field-input" maxLength={150} value={form.name} onChange={(e) => set('name', e.target.value)} /></div>
                    <div><label className="field-label">Tgl Perolehan <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.acq_date} onChange={(e) => set('acq_date', e.target.value)} /></div>
                    <div><label className="field-label">Harga Perolehan (Rp) <span className="text-red-500">*</span></label><input type="number" step="0.01" className="field-input" value={form.acq_cost} onChange={(e) => set('acq_cost', e.target.value)} /></div>
                    <div><label className="field-label">Umur manfaat (bulan)</label><input type="number" min="1" className="field-input" value={form.useful_life} onChange={(e) => set('useful_life', e.target.value)} placeholder="ikut kategori" /></div>
                    <div><label className="field-label">Mesin terkait</label><Select value={form.machine_id} onChange={(v) => set('machine_id', v)} options={machines.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— tidak ada —" /></div>
                    <div><label className="field-label">Status</label><select className="field-input" value={form.status} onChange={(e) => set('status', e.target.value)}><option value="ACTIVE">ACTIVE</option><option value="DISPOSED">DISPOSED</option><option value="TRANSFERRED">TRANSFERRED</option></select></div>
                </div>
            </Modal>

            <Modal open={!!view} onClose={() => setView(null)} size="max-w-xl" title={`Riwayat Depresiasi — ${view?.code || ''}`}>
                <div className="mb-2 text-sm text-slate-500">{view?.name} · perolehan Rp {money(view?.acq_cost)} · umur {view?.useful_life} bulan</div>
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Periode</th><th className="px-2 py-2 text-right">Jumlah</th></tr></thead>
                    <tbody>
                        {(view?.depre || []).length === 0 && <tr><td colSpan={2} className="px-2 py-4 text-center text-slate-400">Belum ada depresiasi diposting.</td></tr>}
                        {(view?.depre || []).map((d) => (
                            <tr key={d.id} className="border-t border-slate-100"><td className="px-2 py-1.5">{d.period}</td><td className="px-2 py-1.5 text-right">{money(d.amount)}</td></tr>
                        ))}
                    </tbody>
                </table>
            </Modal>
        </div>
    );
}
