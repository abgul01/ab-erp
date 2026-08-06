import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import PickerModal from '../../components/PickerModal';
import { ITEM_COLUMNS } from '../procurement/PoPage';
import { useOptions, CellInput, money } from '../procurement/common';
import MonthPicker from '../../components/MonthPicker';

const now = new Date();
const thisMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
const toPeriod = (ym) => ym.replace('-', ''); // YYYY-MM -> YYYYMM
const addMonths = (period, n) => { let y = +period.slice(0, 4); let m = +period.slice(4, 6) - 1 + n; y += Math.floor(m / 12); m = ((m % 12) + 12) % 12; return `${y}${String(m + 1).padStart(2, '0')}`; };
const fmtPeriod = (p) => `${p.slice(0, 4)}-${p.slice(4, 6)}`;
const STCLR = { APPROVED: 'bg-emerald-100 text-emerald-700 border-emerald-200', DRAFT: 'bg-slate-100 text-slate-600 border-slate-200' };

export default function MppPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [startYm, setStartYm] = useState(thisMonth);
    const [modal, setModal] = useState(null); // {mode:'create'|'edit', id?}
    const [form, setForm] = useState(null);
    const [itemPicker, setItemPicker] = useState(false);
    const [genOpen, setGenOpen] = useState(false);
    const [gen, setGen] = useState({ period: toPeriod(thisMonth), source: 'MAX' });
    const [error, setError] = useState('');

    const items = useOptions('items');
    const itemById = Object.fromEntries((items.data || []).map((it) => [it.id, it]));

    const start = toPeriod(startYm);
    const periods = [start, addMonths(start, 1), addMonths(start, 2)];

    const matrix = useQuery({
        queryKey: ['mpp', 'matrix', start],
        queryFn: async () => {
            const res = await Promise.all(periods.map((p) => api.get('/mpp', { params: { period: p, per_page: 300 } })));
            return res.flatMap((r) => r.data.data);
        },
    });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['mpp'] });

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/mpp/${modal.id}`, p) : api.post('/mpp', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const approve = useMutation({ mutationFn: async (id) => api.post(`/mpp/${id}/approve`), onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/mpp/${id}`), onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => alert(apiError(e)) });
    const generate = useMutation({
        mutationFn: async (body) => api.post('/mpp/generate', body),
        onSuccess: (res) => { invalidate(); const d = res.data.data; const label = (d.periods || []).map(fmtPeriod).join(', '); alert(`Generate MPP ${label}: +${d.created} baru, ${d.updated} diperbarui, ${d.skipped_approved} dilewati (approved).`); },
        onError: (e) => alert(apiError(e)),
    });

    // Build item rows × period cells
    const rows = {};
    (matrix.data || []).forEach((m) => {
        rows[m.item_id] ||= { item: m.item, item_id: m.item_id, cells: {} };
        rows[m.item_id].cells[m.period] = m;
    });
    const rowList = Object.values(rows).sort((a, b) => (a.item?.code || '').localeCompare(b.item?.code || ''));

    const openCreate = (itemId = '', period = start) => { setForm({ period, item_id: itemId, plan_qty: 1 }); setError(''); setModal({ mode: 'create' }); };
    const openEdit = (m) => { setForm({ period: m.period, item_id: m.item_id, plan_qty: m.plan_qty, status: m.status }); setError(''); setModal({ mode: 'edit', id: m.id }); };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    return (
        <div className="p-6">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold text-slate-800">MPP — Rencana Produksi Bulanan</h1>
                <div className="flex flex-wrap items-center gap-2">
                    <label className="text-sm text-slate-500">Mulai bulan</label>
                    <input type="month" className="field-input w-40" value={startYm} onChange={(e) => setStartYm(e.target.value)} />
                    {can('mpp', 'create') && <button className="btn btn-ghost" onClick={() => generate.mutate({ periods, source: 'MAX' })} disabled={generate.isPending}>{generate.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="calculator" />} Generate 3 Bulan</button>}
                    {can('mpp', 'create') && <button className="btn btn-ghost" onClick={() => { setGen({ period: start, source: 'MAX' }); setGenOpen(true); }}><Icon name="calculator" /> Generate 1 Bulan…</button>}
                    {can('mpp', 'create') && <button className="btn btn-primary" onClick={() => openCreate()}><Icon name="plus" /> Tambah</button>}
                </div>
            </div>
            <p className="mb-3 text-xs text-slate-400">Kebutuhan produksi = max(Σ SO approved, Σ Forecast FINAL) − on-process (WO yang sudah masuk proses pertama). Hanya baris DRAFT yang ditimpa; yang APPROVED dipertahankan. Setelah approve, buka MPS lalu klik <b>Generate dari MPP</b>.</p>

            <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th className="px-4 py-2.5">Item FG</th>
                            {periods.map((p) => <th key={p} className="px-4 py-2.5 text-center">{fmtPeriod(p)}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {matrix.isLoading && <tr><td colSpan={4} className="px-4 py-6 text-center text-slate-400">Memuat…</td></tr>}
                        {!matrix.isLoading && rowList.length === 0 && <tr><td colSpan={4} className="px-4 py-6 text-center text-slate-400">Belum ada MPP di rentang ini. Klik Generate atau Tambah.</td></tr>}
                        {rowList.map((r) => (
                            <tr key={r.item_id} className="border-t border-slate-100">
                                <td className="px-4 py-2">
                                    <div className="font-medium text-slate-700">{r.item?.code}</div>
                                    <div className="text-xs text-slate-400">{r.item?.part_name}</div>
                                </td>
                                {periods.map((p) => {
                                    const cell = r.cells[p];
                                    return (
                                        <td key={p} className="px-4 py-2 text-center">
                                            {cell ? (
                                                <button className={`inline-flex min-w-[72px] flex-col items-center rounded-md border px-3 py-1.5 transition hover:brightness-95 ${STCLR[cell.status] || STCLR.DRAFT}`} onClick={() => openEdit(cell)}>
                                                    <span className="text-base font-semibold leading-none">{money(cell.plan_qty)}</span>
                                                    <span className="mt-0.5 text-[10px] uppercase">{cell.status}</span>
                                                </button>
                                            ) : (
                                                can('mpp', 'create') && <button className="rounded-md border border-dashed border-slate-200 px-3 py-1.5 text-xs text-slate-300 hover:border-slate-300 hover:text-slate-500" onClick={() => openCreate(r.item_id, p)}><Icon name="plus" className="h-3.5 w-3.5" /></button>
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Create / edit / view cell */}
            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'MPP' : 'Tambah MPP'}${form ? ' — ' + fmtPeriod(form.period) : ''}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Tutup</button>
                    {modal?.mode === 'edit' && form?.status === 'DRAFT' && can('mpp', 'delete') && <button className="btn btn-ghost text-red-600" onClick={() => window.confirm('Hapus MPP?') && remove.mutate(modal.id)}><Icon name="trash" /> Hapus</button>}
                    {modal?.mode === 'edit' && form?.status === 'DRAFT' && can('mpp', 'edit') && <button className="btn btn-ghost text-emerald-700" onClick={() => approve.mutate(modal.id)}><Icon name="check" /> Approve</button>}
                    {(modal?.mode === 'create' || form?.status === 'DRAFT') && can('mpp', 'edit') && (
                        <button className="btn btn-primary" onClick={() => { setError(''); save.mutate({ period: form.period, item_id: form.item_id, plan_qty: form.plan_qty }); }} disabled={save.isPending}>
                            {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                        </button>
                    )}
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {modal && form && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div><label className="field-label">Periode <span className="text-red-500">*</span></label><MonthPicker value={form.period} onChange={(v) => set('period', v)} disabled={modal.mode === 'edit'} className="w-full" /></div>
                        <div className="sm:col-span-2"><label className="field-label">Item FG <span className="text-red-500">*</span></label>
                            <button type="button" className="field-input flex w-full items-center justify-between gap-2 text-left disabled:bg-slate-50" disabled={modal.mode === 'edit'} onClick={() => setItemPicker(true)}>
                                <span className="truncate">{form.item_id ? (itemById[form.item_id] ? `${itemById[form.item_id].code} — ${itemById[form.item_id].part_name}` : `#${form.item_id}`) : <span className="text-slate-400">— pilih FG —</span>}</span>
                                <Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" />
                            </button></div>
                        <div><label className="field-label">Rencana Qty <span className="text-red-500">*</span></label><CellInput type="number" value={form.plan_qty} onChange={(v) => set('plan_qty', v)} /></div>
                        {form.status && <div className="sm:col-span-2 self-end"><span className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium ${STCLR[form.status]}`}>{form.status}</span></div>}
                    </div>
                )}
            </Modal>

            <PickerModal open={itemPicker} onClose={() => setItemPicker(false)} title="Pilih Item FG" rows={(items.data || []).map((it) => ({ ...it, _key: it.id }))} searchKeys={['code', 'part_name']} columns={ITEM_COLUMNS} onSelect={(row) => set('item_id', row.id)} />

            <Modal open={genOpen} onClose={() => setGenOpen(false)} title="Generate MPP dari Forecast / SO"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setGenOpen(false)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { generate.mutate(gen); setGenOpen(false); }} disabled={generate.isPending}><Icon name="calculator" /> Generate</button>
                </>}>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label className="field-label">Periode (YYYYMM) <span className="text-red-500">*</span></label><input className="field-input" value={gen.period} onChange={(e) => setGen((g) => ({ ...g, period: e.target.value.replace(/\D/g, '').slice(0, 6) }))} /></div>
                    <div><label className="field-label">Dasar demand</label>
                        <select className="field-input" value={gen.source} onChange={(e) => setGen((g) => ({ ...g, source: e.target.value }))}>
                            <option value="MAX">Maks(SO, Forecast)</option>
                            <option value="SO">Sales Order saja</option>
                            <option value="FORECAST">Forecast saja</option>
                        </select></div>
                </div>
                <p className="mt-3 text-xs text-slate-500">plan_qty = demand − stok FG (0 dulu). MPP APPROVED tidak ditimpa; hanya DRAFT diperbarui/dibuat.</p>
            </Modal>
        </div>
    );
}
