import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import PickerModal from '../../components/PickerModal';
import { ITEM_COLUMNS } from '../procurement/PoPage';
import { Select, VendorSelect, StatusBadge, useOptions, LineTable, CellInput, money } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), po_date: '', due_date: '', cus_id: '', cus_po_no: '', currency_id: '', note: '', lines: [], cus_items: [] };
const NEW_LINE = { item_id: '', po_detail_code: '', qty: 1, price: 0, tax_id: '', pph_tax_id: '', pricelist_det_id: null, manual: false, _pl: null, local_mat: false, ppn: false, pph: false, due_date: '', note: '' };
const r2 = (v) => Math.round((Number(v) || 0) * 100) / 100;

/**
 * Same arithmetic the API applies when saving (SoController::syncLines), so the
 * figures on screen are the ones stored on the line. PPN is charged on the DPP
 * ("nilai lain" factor of the tariff); PPh is withheld from the gross amount.
 */
function calcLine(l, taxById) {
    const subtotal = r2((Number(l.qty) || 0) * (Number(l.price) || 0));
    const ppnTax = l.ppn ? taxById(l.tax_id) : null;
    const pphTax = l.pph ? taxById(l.pph_tax_id) : null;
    const dpp = ppnTax ? r2(subtotal * Number(ppnTax.dpp_factor)) : subtotal;
    const ppn_value = ppnTax ? r2((dpp * Number(ppnTax.rate_pct)) / 100) : 0;
    const pph_value = pphTax ? r2((subtotal * Number(pphTax.rate_pct)) / 100) : 0;

    return { subtotal, dpp, ppn_value, pph_value, total: r2(subtotal + ppn_value - pph_value) };
}

export default function SoPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [code, setCode] = useState('');   // SO number, assigned by the server on save
    const [itemPickerFor, setItemPickerFor] = useState(null);
    const [error, setError] = useState('');

    const currencies = useOptions('currencies');
    const taxes = useOptions('taxes');
    const taxList = taxes.data || [];
    const taxById = (id) => taxList.find((t) => t.id === Number(id)) || null;
    const ppnTaxes = taxList.filter((t) => t.code?.startsWith('PPN'));
    const pphTaxes = taxList.filter((t) => t.code?.startsWith('PPH'));
    const defaultTax = (kind) => (kind === 'ppn' ? (ppnTaxes.find((t) => t.code === 'PPN-DN') || ppnTaxes[0]) : pphTaxes[0])?.id || '';

    const list = useQuery({ queryKey: ['sales-orders', { page }], queryFn: async () => (await api.get('/sales-orders', { params: { page, per_page: 15 } })).data });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['sales-orders'] }); };

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/sales-orders/${modal.id}`, p) : api.post('/sales-orders', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({ mutationFn: async ({ id, action }) => api.post(`/sales-orders/${id}/${action}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/sales-orders/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const loadCusItems = async (cusId) => { if (!cusId) return []; return (await api.get(`/sales-orders/customer-items/${cusId}`)).data.data; };
    const onSelectCus = async (cusId) => { const cus_items = await loadCusItems(cusId); setForm((f) => ({ ...f, cus_id: cusId, cus_items, lines: [] })); };

    const openCreate = () => { setForm(EMPTY); setCode(''); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/sales-orders/${row.id}`);
        const d = data.data;
        const cus_items = await loadCusItems(d.cus_id);
        setForm({
            date: d.date?.slice(0, 10), po_date: d.po_date?.slice(0, 10) || '', due_date: d.due_date?.slice(0, 10) || '',
            cus_id: d.cus_id, cus_po_no: d.cus_po_no || '', currency_id: d.currency_id || '', note: d.note || '', cus_items,
            lines: (d.detail || []).map((l) => ({
                item_id: l.item_id, po_detail_code: l.po_detail_code || '', qty: l.qty, price: l.price,
                tax_id: l.tax_id || '', pph_tax_id: l.pph_tax_id || '',
                pricelist_det_id: l.pricelist_det_id || null, manual: !l.pricelist_det_id,
                _pl: l.pricelist_det ? { found: true, pricelist_code: l.pricelist_det.main?.code, valid_from: l.pricelist_det.valid_from, valid_to: l.pricelist_det.valid_to } : null,
                local_mat: !!l.local_mat, ppn: !!l.ppn, pph: !!l.pph,
                due_date: l.due_date?.slice(0, 10), note: l.note || '',
            })),
        });
        setCode(d.code || '');
        setModal({ mode: 'edit', id: row.id });
    };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const itemLabel = (id) => { const it = (form.cus_items || []).find((x) => x.id === id); return it ? `${it.code} — ${it.part_name}` : (id ? `#${id}` : ''); };
    const addLine = () => set('lines', [...form.lines, { ...NEW_LINE }]);
    const setLine = (i, k, v) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    // functional update: applyPricelist patches after an await, so it must not
    // write back a `lines` array captured before the request went out
    const patchLine = (i, patch) => setForm((f) => ({ ...f, lines: f.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)) }));
    const delLine = (i) => set('lines', form.lines.filter((_, j) => j !== i));

    /**
     * Pull the price from the customer pricelist that is valid on the SO date for
     * this qty. Nothing valid found (or the operator typed a price) → the line
     * falls back to a manual price and stores no pricelist reference.
     */
    const applyPricelist = async (i, patch = {}) => {
        const l = { ...form.lines[i], ...patch };
        if (!form.cus_id || !l.item_id) { patchLine(i, patch); return; }
        try {
            const { data } = await api.get('/pricelists/lookup', { params: { cus_id: form.cus_id, item_id: l.item_id, date: form.date, qty: Number(l.qty) || 1 } });
            const r = data.data;
            patchLine(i, r.found
                ? { ...patch, price: r.price, pricelist_det_id: r.pricelist_det_id, manual: false, _pl: r }
                : { ...patch, pricelist_det_id: null, manual: true, _pl: { found: false } });
        } catch {
            patchLine(i, { ...patch, pricelist_det_id: null, manual: true, _pl: { found: false } });
        }
    };

    const onQty = (i, v) => { const l = form.lines[i]; if (l.manual) setLine(i, 'qty', v); else applyPricelist(i, { qty: v }); };
    const onPrice = (i, v) => patchLine(i, { price: v, manual: true, pricelist_det_id: null });
    const onTaxToggle = (i, kind, on) => patchLine(i, { [kind]: on, [kind === 'ppn' ? 'tax_id' : 'pph_tax_id']: on ? defaultTax(kind) : '' });

    const submit = () => {
        setError('');
        const { cus_items, lines, ...header } = form;
        save.mutate({
            ...header,
            lines: lines.map(({ manual, _pl, ...l }) => ({
                ...l,
                tax_id: l.tax_id || null,
                pph_tax_id: l.pph_tax_id || null,
                pricelist_det_id: manual ? null : l.pricelist_det_id || null,
            })),
        });
    };

    const totals = form.lines.reduce((a, l) => {
        const c = calcLine(l, taxById);

        return { subtotal: a.subtotal + c.subtotal, dpp: a.dpp + c.dpp, ppn: a.ppn + c.ppn_value, pph: a.pph + c.pph_value, total: a.total + c.total };
    }, { subtotal: 0, dpp: 0, ppn: 0, pph: 0, total: 0 });

    const columns = [
        { key: 'code', label: 'No. SO' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'cus', label: 'Customer', render: (v) => v?.company_n || '—' },
        { key: 'cus_po_no', label: 'PO Customer' },
        { key: 'detail_count', label: '# Item' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Sales Order</h1>
                {can('sales-orders', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah SO</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {row.status === 'DRAFT' && can('sales-orders', 'edit') && <button title="Approve" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate({ id: row.id, action: 'approve' })}><Icon name="check" /></button>}
                        {row.status === 'APPROVED' && can('sales-orders', 'edit') && <button title="Close" className="rounded p-1.5 text-blue-600 hover:bg-blue-50" onClick={() => act.mutate({ id: row.id, action: 'close' })}><Icon name="lock" /></button>}
                        {['DRAFT', 'APPROVED'].includes(row.status) && can('sales-orders', 'edit') && <button title="Cancel" className="rounded p-1.5 text-red-600 hover:bg-red-50" onClick={() => window.confirm('Batalkan SO?') && act.mutate({ id: row.id, action: 'cancel' })}><Icon name="ban" /></button>}
                        {row.status === 'DRAFT' && can('sales-orders', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {row.status === 'DRAFT' && can('sales-orders', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus SO?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} size="max-w-[90rem]" title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Sales Order`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit} disabled={save.isPending}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div><label className="field-label">No. SO</label><input className="field-input bg-slate-100" readOnly value={code} placeholder="— otomatis saat simpan —" /></div>
                    <div><label className="field-label">Tanggal SO <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div className="sm:col-span-2"><label className="field-label">Customer <span className="text-red-500">*</span></label><VendorSelect value={form.cus_id} onChange={onSelectCus} /></div>

                    <div><label className="field-label">No. PO Customer</label><input className="field-input" value={form.cus_po_no} onChange={(e) => set('cus_po_no', e.target.value)} /></div>
                    <div><label className="field-label">Tgl PO Diterima</label><input type="date" className="field-input" value={form.po_date} onChange={(e) => set('po_date', e.target.value)} /></div>
                    <div><label className="field-label">Due Date (order)</label><input type="date" className="field-input" value={form.due_date} onChange={(e) => set('due_date', e.target.value)} /></div>
                    <div><label className="field-label">Mata Uang</label><Select value={form.currency_id} onChange={(v) => set('currency_id', v)} options={currencies.data} getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="IDR" /></div>

                    <div className="sm:col-span-4"><label className="field-label">Keterangan</label><input className="field-input" maxLength={200} value={form.note} onChange={(e) => set('note', e.target.value)} placeholder="catatan order…" /></div>
                </div>

                <LineTable title="Item SO" subtitle="Hanya item yang terdaftar untuk customer ini (tab Customer di Item Master)." onAdd={form.cus_id ? addLine : null} lines={form.lines}
                    empty={form.cus_id ? 'Belum ada item.' : 'Pilih customer dulu.'}
                    head={['Item', 'PO Detail', 'Qty', 'Harga', 'Lokal?', 'PPN', 'PPh', 'Due Date', 'Keterangan', 'Subtotal', 'DPP', 'PPN (Rp)', 'PPh (Rp)', 'Total', '']}
                    row={(l, i) => {
                        const c = calcLine(l, taxById);

                        return (<>
                            <td className="min-w-[240px] px-2 py-1.5">
                                <button type="button" className="field-input flex w-full items-center justify-between gap-2 text-left" onClick={() => setItemPickerFor(i)}>
                                    <span className="truncate">{l.item_id ? itemLabel(l.item_id) : <span className="text-slate-400">— pilih item —</span>}</span>
                                    <Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" />
                                </button>
                            </td>
                            <td className="px-2 py-1.5"><div className="w-32"><CellInput value={l.po_detail_code} onChange={(v) => setLine(i, 'po_detail_code', v)} /></div></td>
                            <td className="px-2 py-1.5"><div className="w-20"><CellInput type="number" value={l.qty} onChange={(v) => onQty(i, v)} /></div></td>
                            <td className="px-2 py-1.5">
                                <div className="w-36">
                                    <CellInput type="number" step="0.01" value={l.price} onChange={(v) => onPrice(i, v)} />
                                    <PriceSource line={l} onReset={() => applyPricelist(i)} />
                                </div>
                            </td>
                            <td className="px-2 py-1.5 text-center align-middle"><input type="checkbox" className="h-4 w-4 align-middle" checked={!!l.local_mat} onChange={(e) => setLine(i, 'local_mat', e.target.checked)} /></td>
                            <td className="px-2 py-1.5 align-middle">
                                <TaxCell checked={!!l.ppn} onToggle={(on) => onTaxToggle(i, 'ppn', on)} value={l.tax_id} onChange={(v) => setLine(i, 'tax_id', v)} options={ppnTaxes} />
                            </td>
                            <td className="px-2 py-1.5 align-middle">
                                <TaxCell checked={!!l.pph} onToggle={(on) => onTaxToggle(i, 'pph', on)} value={l.pph_tax_id} onChange={(v) => setLine(i, 'pph_tax_id', v)} options={pphTaxes} />
                            </td>
                            <td className="px-2 py-1.5"><div className="w-36"><CellInput type="date" value={l.due_date} onChange={(v) => setLine(i, 'due_date', v)} /></div></td>
                            <td className="px-2 py-1.5"><div className="w-40"><CellInput value={l.note} onChange={(v) => setLine(i, 'note', v)} /></div></td>
                            <td className="px-2 py-1.5 text-right align-middle text-slate-600">{money(c.subtotal)}</td>
                            <td className="px-2 py-1.5 text-right align-middle text-slate-600">{money(c.dpp)}</td>
                            <td className="px-2 py-1.5 text-right align-middle text-slate-600">{money(c.ppn_value)}</td>
                            <td className="px-2 py-1.5 text-right align-middle text-amber-700">{c.pph_value ? `(${money(c.pph_value)})` : '—'}</td>
                            <td className="px-2 py-1.5 text-right align-middle font-medium text-slate-800">{money(c.total)}</td>
                            <td className="px-2 py-1.5 align-middle"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                        </>);
                    }} />

                <div className="mt-2 flex justify-end">
                    <dl className="w-72 space-y-1 text-sm">
                        <Row label="Subtotal (DPP bruto)" value={totals.subtotal} />
                        <Row label="DPP nilai lain" value={totals.dpp} />
                        <Row label="PPN" value={totals.ppn} />
                        <Row label="PPh dipotong" value={-totals.pph} />
                        <div className="flex justify-between border-t border-slate-200 pt-1 font-semibold text-slate-800">
                            <dt>Total Tagihan</dt><dd>Rp {money(totals.total)}</dd>
                        </div>
                    </dl>
                </div>
            </Modal>

            <PickerModal open={itemPickerFor != null} onClose={() => setItemPickerFor(null)} title="Pilih Item (terdaftar customer)"
                rows={(form.cus_items || []).map((it) => ({ ...it, _key: it.id }))} searchKeys={['code', 'part_name']} columns={ITEM_COLUMNS}
                empty="Customer ini belum punya item terdaftar (tab Customer di Item Master)."
                onSelect={(row) => applyPricelist(itemPickerFor, { item_id: row.id })} />
        </div>
    );
}

/** Where the price came from: a valid pricelist line, or typed by hand. */
function PriceSource({ line, onReset }) {
    if (!line.item_id) return null;
    if (!line.manual && line._pl?.found) {
        return (
            <p className="mt-0.5 truncate text-[11px] text-emerald-600" title={`Pricelist ${line._pl.pricelist_code} • ${line._pl.valid_from?.slice(0, 10)} s/d ${line._pl.valid_to?.slice(0, 10)}`}>
                <Icon name="check" className="mr-0.5 inline h-3 w-3" />{line._pl.pricelist_code} s/d {line._pl.valid_to?.slice(0, 10)}
            </p>
        );
    }

    return (
        <p className="mt-0.5 text-[11px] text-slate-400">
            {line._pl && !line._pl.found ? 'Manual — tidak ada pricelist berlaku' : 'Manual'}
            <button type="button" className="ml-1 text-blue-600 hover:underline" onClick={onReset}>pakai pricelist</button>
        </p>
    );
}

/** Tax on/off plus which tariff of m_tax applies to this line. */
function TaxCell({ checked, onToggle, value, onChange, options }) {
    return (
        <div className="w-28">
            <label className="flex items-center gap-1.5 text-xs text-slate-600">
                <input type="checkbox" className="h-4 w-4" checked={checked} onChange={(e) => onToggle(e.target.checked)} />
                {checked ? 'ya' : 'tidak'}
            </label>
            {checked && (
                <select className="mt-1 w-full rounded border border-slate-300 px-1 py-0.5 text-[11px]" value={value ?? ''} onChange={(e) => onChange(e.target.value === '' ? '' : Number(e.target.value))}>
                    <option value="">— tarif —</option>
                    {options.map((t) => <option key={t.id} value={t.id}>{t.code} ({Number(t.rate_pct)}%)</option>)}
                </select>
            )}
        </div>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex justify-between text-slate-600">
            <dt>{label}</dt><dd>{money(value)}</dd>
        </div>
    );
}
