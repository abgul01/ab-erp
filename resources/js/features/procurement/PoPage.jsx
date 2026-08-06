import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import PickerModal from '../../components/PickerModal';
import { VendorSelect, Select, useOptions, StatusBadge, LineTable, CellInput, money } from './common';

// Shared item picker column set (code, part name, dims).
export const ITEM_COLUMNS = [
    { key: 'code', label: 'Code' },
    { key: 'part_name', label: 'Part Name' },
    { key: 'o_d', label: 'OD', className: 'text-right' },
    { key: 'i_d', label: 'ID', className: 'text-right' },
    { key: 'thick', label: 'Thick', className: 'text-right' },
    { key: 'width', label: 'Width', className: 'text-right' },
    { key: 'height', label: 'Height', className: 'text-right' },
];

// SUBCONT dipisah ke dokumen PO Subcont tersendiri (menu Procurement → PO Subcont)
const PO_TYPES = ['RM', 'GENERAL', 'NPD', 'SERVICE', 'ASSET'];
const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), po_type: 'RM', source: 'LOCAL', ven_id: '', quota_id: '', currency_id: '', rate: 1, top_days: 30, eta: '', lines: [] };

export default function PoPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    const [pullPr, setPullPr] = useState('');
    const [itemPickerFor, setItemPickerFor] = useState(null);

    const quotas = useOptions('quotas');
    const currencies = useOptions('currencies');
    const uoms = useOptions('uoms');
    const taxes = useOptions('taxes');
    const items = useOptions('items');
    const itemById = Object.fromEntries((items.data || []).map((it) => [it.id, it]));
    const itemLabel = (id) => { const it = itemById[id]; return it ? `${it.code} — ${it.part_name}` : ''; };
    const approvedPrs = useQuery({
        queryKey: ['pr', 'approved'],
        queryFn: async () => (await api.get('/pr', { params: { status: 'APPROVED', per_page: 200 } })).data.data,
        staleTime: 30_000,
    });

    const list = useQuery({
        queryKey: ['po', { page }],
        queryFn: async () => (await api.get('/po', { params: { page, per_page: 15 } })).data,
    });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['po'] });

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/po/${modal.id}`, payload) : api.post('/po', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({
        mutationFn: async ({ id, action }) => api.post(`/po/${id}/${action}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/po/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const isImportRm = form.source === 'IMPORT' && form.po_type === 'RM';

    // Per-line amount (DPP): qty × harga; fallback total berat × harga/kg when per-pcs price empty.
    const lineAmount = (l) => {
        const qty = Number(l.qty) || 0;
        const price = Number(l.price) || 0;
        if (price > 0) return qty * price;
        const totW = qty * (Number(l.est_weight_unit) || 0);
        return totW * (Number(l.price_kg) || 0);
    };
    const lineTax = (l) => {
        const t = (taxes.data || []).find((x) => x.id === Number(l.tax_id));
        if (!t) return 0;
        return lineAmount(l) * ((Number(t.rate_pct) || 0) / 100) * (Number(t.dpp_factor) || 1);
    };
    const totDpp = form.lines.reduce((a, l) => a + lineAmount(l), 0);
    const totTax = form.lines.reduce((a, l) => a + lineTax(l), 0);

    const openCreate = () => { setForm(EMPTY); setPullPr(''); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError(''); setPullPr('');
        const { data } = await api.get(`/po/${row.id}`);
        const d = data.data;
        setForm({
            amend: row.status !== 'DRAFT',
            date: d.date?.slice(0, 10), po_type: d.po_type, source: d.source, ven_id: d.ven_id,
            quota_id: d.quota_id || '', currency_id: d.currency_id || '', rate: d.rate, top_days: d.top_days,
            eta: d.eta?.slice(0, 10) || '',
            lines: (d.detail || []).map((l) => ({ id: l.id, pr_detail_id: l.pr_detail_id, item_id: l.item_id, qty: l.qty, uom_id: l.uom_id || '', price: l.price, price_kg: l.price_kg ?? '', tax_id: l.tax_id || '', est_weight_unit: l.est_weight_unit ?? '', est_length_unit: l.est_length_unit ?? '', qty_received: l.qty_received || 0 })),
        });
        setModal({ mode: 'edit', id: row.id });
    };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const addLine = () => set('lines', [...form.lines, { item_id: '', qty: 1, uom_id: '', price: 0, price_kg: '', tax_id: '', est_weight_unit: '', est_length_unit: '' }]);
    const setLine = (i, k, v) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const delLine = (i) => set('lines', form.lines.filter((_, j) => j !== i));

    const doPull = async () => {
        if (!pullPr) return;
        const { data } = await api.get(`/pr/${pullPr}`);
        let detail = data.data.detail || [];

        /*
         * A requisition raised by MRP already names a supplier per line, but a
         * purchase order goes to exactly one vendor. So: adopt the requisition's
         * vendor when the PO has none yet and the lines agree on one, and pull
         * only the lines that belong to this vendor — lines meant for another
         * mill stay behind for their own PO instead of being silently
         * re-addressed to whoever this order happens to be for.
         */
        const suggested = [...new Set(detail.map((l) => l.ven_id).filter(Boolean))];
        let venId = form.ven_id;
        if (!venId && suggested.length === 1) { venId = suggested[0]; set('ven_id', venId); }

        if (venId && suggested.length > 0) {
            const mine = detail.filter((l) => !l.ven_id || l.ven_id === venId);
            if (mine.length < detail.length) {
                setError(`${detail.length - mine.length} baris PR ditujukan ke vendor lain — tidak ikut ditarik. Buat PO terpisah untuk vendor tersebut.`);
            }
            detail = mine;
        }

        // Price starts from what the requisition expected to pay, so the buyer
        // negotiates from a number instead of from zero.
        const lines = detail.map((l) => ({ pr_detail_id: l.id, item_id: l.item_id, qty: l.qty, uom_id: l.uom_id || '', price: Number(l.est_price) || 0, price_kg: '', tax_id: '', est_weight_unit: '', est_length_unit: '' }));
        set('lines', [...form.lines, ...lines]);
        setPullPr('');
    };

    const columns = [
        { key: 'code', label: 'No. PO' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'po_type', label: 'Tipe' },
        { key: 'source', label: 'Sumber' },
        { key: 'ven', label: 'Vendor', render: (v) => v?.company_n || '—' },
        { key: 'eta', label: 'ETA', render: (v) => v?.slice(0, 10) || '—' },
        { key: 'detail_count', label: '# Item' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Purchase Order</h1>
                {can('po', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah PO</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {row.status === 'DRAFT' && can('po', 'edit') && <button title="Submit" className="rounded p-1.5 text-amber-600 hover:bg-amber-50" onClick={() => act.mutate({ id: row.id, action: 'submit' })}><Icon name="send" /></button>}
                        {row.status === 'SUBMITTED' && can('po', 'edit') && <button title="Approve" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate({ id: row.id, action: 'approve' })}><Icon name="check" /></button>}
                        {['OPEN', 'INPROGRESS'].includes(row.status) && can('po', 'edit') && <button title="Close" className="rounded p-1.5 text-blue-600 hover:bg-blue-50" onClick={() => window.confirm('Tutup PO ini secara manual?') && act.mutate({ id: row.id, action: 'close' })}><Icon name="lock" /></button>}
                        {['DRAFT', 'SUBMITTED', 'OPEN', 'INPROGRESS'].includes(row.status) && can('po', 'edit') && <button title="Cancel" className="rounded p-1.5 text-red-600 hover:bg-red-50" onClick={() => window.confirm('Batalkan PO ini?') && act.mutate({ id: row.id, action: 'cancel' })}><Icon name="ban" /></button>}
                        {row.status !== 'CANCELLED' && can('po', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {row.status === 'DRAFT' && can('po', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus PO?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} size="max-w-[95rem]" title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} PO`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {form.amend && <div className="mb-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700">Mode amend: Tipe/Sumber/Kuota terkunci. Qty tak boleh &lt; qty diterima; baris yang sudah diterima tak bisa dihapus. Reservasi kuota & status dihitung ulang.</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div><label className="field-label">Tipe PO <span className="text-red-500">*</span></label>
                        <select className="field-input" value={form.po_type} disabled={form.amend} onChange={(e) => set('po_type', e.target.value)}>{PO_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}</select>
                    </div>
                    <div><label className="field-label">Sumber <span className="text-red-500">*</span></label>
                        <select className="field-input" value={form.source} disabled={form.amend} onChange={(e) => set('source', e.target.value)}><option value="LOCAL">LOCAL</option><option value="IMPORT">IMPORT</option></select>
                    </div>
                    <div className="sm:col-span-2"><label className="field-label">Vendor <span className="text-red-500">*</span></label><VendorSelect value={form.ven_id} onChange={(v) => set('ven_id', v)} /></div>
                    <div><label className="field-label">TOP (hari)</label><input type="number" className="field-input" value={form.top_days} onChange={(e) => set('top_days', e.target.value)} /></div>
                    {isImportRm && (
                        <div className="sm:col-span-1"><label className="field-label">Kuota Impor <span className="text-red-500">*</span></label>
                            <Select value={form.quota_id} onChange={(v) => set('quota_id', v)} options={quotas.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} (sisa ${money(o.balance_ton)} t)`} placeholder="— pilih kuota —" disabled={form.amend} />
                        </div>
                    )}
                    <div><label className="field-label">Mata Uang</label><Select value={form.currency_id} onChange={(v) => set('currency_id', v)} options={currencies.data} getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="IDR (default)" /></div>
                    <div><label className="field-label">Kurs</label><input type="number" step="0.000001" className="field-input" value={form.rate} onChange={(e) => set('rate', e.target.value)} /></div>
                    <div><label className="field-label">ETA (estimasi tiba)</label><input type="date" className="field-input" value={form.eta} onChange={(e) => set('eta', e.target.value)} /></div>
                </div>

                <div className="mb-2 flex items-end gap-2">
                    <div className="flex-1">
                        <label className="field-label">Tarik dari PR (approved)</label>
                        <select className="field-input" value={pullPr} onChange={(e) => setPullPr(e.target.value)}>
                            <option value="">— pilih PR —</option>
                            {(approvedPrs.data || []).map((p) => <option key={p.id} value={p.id}>{p.code} ({p.pr_type})</option>)}
                        </select>
                    </div>
                    <button type="button" className="btn btn-ghost" onClick={doPull} disabled={!pullPr}><Icon name="plus" /> Tarik</button>
                </div>

                <LineTable title="Item PO" onAdd={addLine} lines={form.lines} empty="Belum ada item."
                    head={['Item', 'Qty', 'UoM', 'Harga', 'Harga/kg', 'Tax', 'Berat/pcs (kg)', 'Est. Berat (kg)', 'Length/pcs (mm)', 'Est. Length (mm)', 'Jumlah (DPP)', '']}
                    row={(l, i) => {
                        const totW = (Number(l.qty) || 0) * (Number(l.est_weight_unit) || 0);
                        const totL = (Number(l.qty) || 0) * (Number(l.est_length_unit) || 0);
                        return (<>
                            <td className="min-w-[240px] px-2 py-1.5">
                                <button type="button" className="field-input flex w-full items-center justify-between gap-2 text-left" onClick={() => setItemPickerFor(i)}>
                                    <span className="truncate">{l.item_id ? itemLabel(l.item_id) : <span className="text-slate-400">— pilih item —</span>}</span>
                                    <Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" />
                                </button>
                            </td>
                            <td className="px-2 py-1.5"><div className="w-20"><CellInput type="number" value={l.qty} onChange={(v) => setLine(i, 'qty', v)} /></div></td>
                            <td className="px-2 py-1.5"><div className="w-24"><Select value={l.uom_id} onChange={(v) => setLine(i, 'uom_id', v)} options={uoms.data} getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="—" /></div></td>
                            <td className="px-2 py-1.5"><div className="w-32"><CellInput type="number" step="0.01" value={l.price} onChange={(v) => setLine(i, 'price', v)} /></div></td>
                            <td className="px-2 py-1.5"><div className="w-32"><CellInput type="number" step="0.01" value={l.price_kg} onChange={(v) => setLine(i, 'price_kg', v)} /></div></td>
                            <td className="px-2 py-1.5"><div className="w-28"><Select value={l.tax_id} onChange={(v) => setLine(i, 'tax_id', v)} options={taxes.data} getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="—" /></div></td>
                            <td className="px-2 py-1.5"><div className="w-28"><CellInput type="number" step="0.01" value={l.est_weight_unit} onChange={(v) => setLine(i, 'est_weight_unit', v)} /></div></td>
                            <td className="px-2 py-1.5"><div className="w-28 rounded bg-slate-50 px-2 py-2 text-right text-sm text-slate-600">{money(totW)}</div></td>
                            <td className="px-2 py-1.5"><div className="w-28"><CellInput type="number" step="0.01" value={l.est_length_unit} onChange={(v) => setLine(i, 'est_length_unit', v)} /></div></td>
                            <td className="px-2 py-1.5"><div className="w-28 rounded bg-slate-50 px-2 py-2 text-right text-sm text-slate-600">{money(totL)}</div></td>
                            <td className="px-2 py-1.5"><div className="w-32 rounded bg-slate-50 px-2 py-2 text-right text-sm font-medium text-slate-700">{money(lineAmount(l))}</div></td>
                            <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                        </>);
                    }} />
                <div className="mt-3 flex flex-wrap items-center justify-end gap-6 rounded-md bg-slate-50 px-4 py-3 text-sm">
                    <div>Total DPP: <b>Rp {money(totDpp)}</b></div>
                    <div>Pajak: <b>Rp {money(Math.round(totTax))}</b></div>
                    <div className="text-base">Total: <b className="text-slate-800">Rp {money(Math.round(totDpp + totTax))}</b></div>
                </div>
                {isImportRm && <p className="mt-2 text-xs text-amber-600">PO impor RM: item wajib terdaftar di kuota & estimasi berat wajib diisi (untuk reservasi kuota).</p>}
            </Modal>

            <PickerModal
                open={itemPickerFor != null}
                onClose={() => setItemPickerFor(null)}
                title="Pilih Item"
                rows={(items.data || []).map((it) => ({ ...it, _key: it.id }))}
                searchKeys={['code', 'part_name']}
                columns={ITEM_COLUMNS}
                onSelect={(row) => setLine(itemPickerFor, 'item_id', row.id)}
            />
        </div>
    );
}
