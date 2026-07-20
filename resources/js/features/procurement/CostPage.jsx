import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, useOptions, StatusBadge, LineTable, CellInput, money } from './common';

const COST_TYPES = ['FREIGHT', 'INSURANCE', 'DUTY', 'VAT_IMPORT', 'PPH22', 'PIB', 'EMKL', 'FORWARDER', 'UNLOADING', 'TRUCKING', 'OTHER'];
const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), inv_id: '', alloc_basis: 'WEIGHT', costs: [] };

export default function CostPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [view, setView] = useState(null);
    const [error, setError] = useState('');
    const currencies = useOptions('currencies');

    const invoices = useQuery({
        queryKey: ['ap-invoices', 'for-cost'],
        queryFn: async () => (await api.get('/ap-invoices', { params: { per_page: 300 } })).data.data,
        staleTime: 30_000,
    });

    const list = useQuery({
        queryKey: ['landed-costs', { page }],
        queryFn: async () => (await api.get('/landed-costs', { params: { page, per_page: 15 } })).data,
    });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['landed-costs'] });

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/landed-costs/${modal.id}`, payload) : api.post('/landed-costs', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const finalize = useMutation({
        mutationFn: async (id) => api.post(`/landed-costs/${id}/finalize`),
        onSuccess: async (res) => { invalidate(); setView(res.data.data); },
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/landed-costs/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/landed-costs/${row.id}`);
        const d = data.data;
        setForm({
            date: d.date?.slice(0, 10), inv_id: d.inv_id, alloc_basis: d.alloc_basis,
            costs: (d.detail || []).map((c) => ({ cost_type: c.cost_type, descrip: c.descrip, amount: c.amount, currency_id: c.currency_id, rate: c.rate })),
        });
        setModal({ mode: 'edit', id: row.id });
    };
    const openView = async (row) => { const { data } = await api.get(`/landed-costs/${row.id}`); setView(data.data); };

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const addCost = () => set('costs', [...form.costs, { cost_type: 'FREIGHT', descrip: '', amount: '', currency_id: '', rate: 1 }]);
    const setCost = (i, k, v) => set('costs', form.costs.map((c, j) => (j === i ? { ...c, [k]: v } : c)));
    const delCost = (i) => set('costs', form.costs.filter((_, j) => j !== i));
    const totalIdr = form.costs.reduce((a, c) => a + (Number(c.amount) || 0) * (Number(c.rate) || 1), 0);

    const columns = [
        { key: 'code', label: 'No. LC' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'inv', label: 'AP Invoice', render: (v) => (v ? `${v.code} · ${v.inv_no}` : '—') },
        { key: 'detail_count', label: '# Biaya' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Landed Cost</h1>
                {can('landed-costs', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Landed Cost</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat alokasi" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {row.status === 'DRAFT' && can('landed-costs', 'edit') && <button title="Finalize (alokasi)" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => finalize.mutate(row.id)}><Icon name="calculator" /></button>}
                        {row.status === 'DRAFT' && can('landed-costs', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {row.status === 'DRAFT' && can('landed-costs', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus landed cost?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            {/* Create/edit modal */}
            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Landed Cost`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div className="sm:col-span-2"><label className="field-label">AP Invoice <span className="text-red-500">*</span></label>
                        <Select value={form.inv_id} onChange={(v) => set('inv_id', v)} options={invoices.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} · ${o.inv_no} — ${o.ven?.company_n || ''} (Rp ${money(o.total)})`} placeholder="— pilih invoice —" />
                    </div>
                </div>
                <LineTable title="Komponen Biaya Tambahan" onAdd={addCost} lines={form.costs} empty="Belum ada biaya."
                    head={['Jenis', 'Deskripsi', 'Jumlah', 'Mata Uang', 'Kurs', 'IDR', '']}
                    row={(c, i) => (<>
                        <td className="w-40 px-2 py-1.5"><select className="field-input" value={c.cost_type} onChange={(e) => setCost(i, 'cost_type', e.target.value)}>{COST_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}</select></td>
                        <td className="px-2 py-1.5"><CellInput value={c.descrip} onChange={(v) => setCost(i, 'descrip', v)} /></td>
                        <td className="w-32 px-2 py-1.5"><CellInput type="number" step="0.01" value={c.amount} onChange={(v) => setCost(i, 'amount', v)} /></td>
                        <td className="w-28 px-2 py-1.5"><Select value={c.currency_id} onChange={(v) => setCost(i, 'currency_id', v)} options={currencies.data} getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="IDR" /></td>
                        <td className="w-24 px-2 py-1.5"><CellInput type="number" step="0.000001" value={c.rate} onChange={(v) => setCost(i, 'rate', v)} /></td>
                        <td className="w-32 px-2 py-1.5 text-right text-slate-600">{money((Number(c.amount) || 0) * (Number(c.rate) || 1))}</td>
                        <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delCost(i)}><Icon name="trash" /></button></td>
                    </>)} />
                <div className="text-right text-sm font-semibold text-slate-700">Total: Rp {money(totalIdr)}</div>
            </Modal>

            {/* Allocation view */}
            <Modal open={!!view} onClose={() => setView(null)} wide title={`Landed Cost ${view?.code || ''} — ${view?.status || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setView(null)}>Tutup</button>}>
                {view && (<>
                    <div className="mb-3 text-sm text-slate-600">Invoice: <b>{view.inv?.code} · {view.inv?.inv_no}</b> · PO: <b>{view.po?.code}</b> · Basis alokasi: <b>{view.alloc_basis}</b></div>
                    <h4 className="mb-1 text-sm font-semibold text-slate-700">Biaya</h4>
                    <div className="mb-4 overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm"><thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            {['Jenis', 'Deskripsi', 'IDR'].map((h, i) => <th key={i} className="px-2 py-1.5">{h}</th>)}</tr></thead>
                            <tbody>
                                {(view.detail || []).map((c) => <tr key={c.id} className="border-t border-slate-100"><td className="px-2 py-1.5">{c.cost_type}</td><td className="px-2 py-1.5">{c.descrip}</td><td className="px-2 py-1.5 text-right">{money(c.amount_idr)}</td></tr>)}
                            </tbody>
                        </table>
                    </div>
                    <h4 className="mb-1 text-sm font-semibold text-slate-700">Alokasi ke Penerimaan (per berat)</h4>
                    {(view.alloc || []).length === 0 ? (
                        <p className="rounded-md border border-dashed border-slate-200 p-4 text-center text-sm text-slate-400">Belum di-finalize. Klik <Icon name="calculator" className="inline h-4 w-4" /> untuk menghitung alokasi.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-md border border-slate-200">
                            <table className="w-full text-sm"><thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                {['Item', 'Alokasi (IDR)', 'Unit Cost /kg'].map((h, i) => <th key={i} className="px-2 py-1.5">{h}</th>)}</tr></thead>
                                <tbody>
                                    {(view.alloc || []).map((a) => (
                                        <tr key={a.id} className="border-t border-slate-100">
                                            <td className="px-2 py-1.5">{a.gr_detail?.item?.code} — {a.gr_detail?.item?.part_name}</td>
                                            <td className="px-2 py-1.5 text-right">{money(a.amount)}</td>
                                            <td className="px-2 py-1.5 text-right">{money(a.unit_cost_kg)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </>)}
            </Modal>
        </div>
    );
}
