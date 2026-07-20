import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { VendorSelect, useOptions, StatusBadge, LineTable, CellInput, money } from './common';
import PickerModal from '../../components/PickerModal';
import ChooseGrModal from '../../components/ChooseGrModal';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = {
    date: today(), ven_id: '', gr_ids: [], inv_no: '', vat: '', wht23: '',
    tax_inv_no: '', tax_inv_date: '', due_date: '', lines: [], gr_lines: [], grs: [],
};

export default function InvoicePage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');

    const taxes = useOptions('taxes');
    const items = useOptions('items');
    const itemsById = Object.fromEntries((items.data || []).map((it) => [it.id, it]));
    const [linePickerFor, setLinePickerFor] = useState(null);
    const [grModal, setGrModal] = useState(false);
    const itemSpec = (id) => itemsById[id] || {};

    const list = useQuery({
        queryKey: ['ap-invoices', { page }],
        queryFn: async () => (await api.get('/ap-invoices', { params: { page, per_page: 15 } })).data,
    });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['ap-invoices'] });

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/ap-invoices/${modal.id}`, payload) : api.post('/ap-invoices', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({
        mutationFn: async ({ id, action }) => api.post(`/ap-invoices/${id}/${action}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/ap-invoices/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    /** Vendor GRs that still have not-fully-invoiced lines (exclude current invoice when editing). */
    const loadVendorGrs = async (venId, excludeInvId = 0) => {
        if (!venId) return [];
        const { data } = await api.get('/grn', { params: { ven_id: venId, with_detail: 1, invoice_pending: 1, exclude_inv_id: excludeInvId, per_page: 300 } });
        return data.data || [];
    };

    /** Build invoice-able line metas from the SELECTED GRs (price/tax from each line's PO). */
    const buildGrLines = async (grs, grIds) => {
        const selected = grs.filter((g) => grIds.includes(g.id));
        const poIds = [...new Set(selected.flatMap((g) => (g.detail || []).map((d) => d.po_id)).filter(Boolean))];
        const taxById = Object.fromEntries((taxes.data || []).map((t) => [t.id, t]));
        const poDetailMap = {};
        for (const poId of poIds) {
            const po = (await api.get(`/po/${poId}`)).data.data;
            for (const l of po.detail || []) poDetailMap[`${poId}-${l.item_id}`] = l;
        }
        return selected.flatMap((g) => (g.detail || []).map((d) => {
            const pl = poDetailMap[`${d.po_id}-${d.item_id}`];
            const unitW = d.qty > 0 ? (Number(d.w_total) || 0) / d.qty : 0;
            const priceKg = pl ? (Number(pl.price_kg) || (unitW > 0 && Number(pl.price) > 0 ? Number(pl.price) / unitW : 0)) : 0;
            const price = pl ? (Number(pl.price) > 0 ? Number(pl.price) : priceKg * unitW) : 0;
            const tax = pl ? taxById[pl.tax_id] : null;
            const sp = itemSpec(d.item_id);
            return {
                gr_detail_id: d.id,
                gr_code: g.code,
                item_code: d.item?.code || sp.code || '',
                part_name: d.item?.part_name || sp.part_name || '',
                o_d: sp.o_d, i_d: sp.i_d, thick: sp.thick, width: sp.width, height: sp.height,
                item_label: `${d.item?.code || ''} — ${d.item?.part_name || ''}`,
                label: `${g.code} · ${d.item?.code || ''} (${d.qty} pcs)`,
                qty: d.qty,
                unit_w: Math.round(unitW * 100) / 100,
                price_default: Math.round(price * 100) / 100,
                price_kg_default: Math.round(priceKg * 10000) / 10000,
                tax_label: tax ? tax.code : '—',
                tax_eff: tax ? ((Number(tax.rate_pct) || 0) / 100) * (Number(tax.dpp_factor) || 1) : 0,
            };
        }));
    };
    const grMeta = (grDetailId) => (form.gr_lines || []).find((g) => g.gr_detail_id === Number(grDetailId));

    const onSelectVendor = async (venId) => {
        const grs = await loadVendorGrs(venId);
        setForm((f) => ({ ...f, ven_id: venId, grs, gr_ids: [], gr_lines: [], lines: [] }));
    };
    const applyGrSelection = async (gr_ids) => {
        const gr_lines = await buildGrLines(form.grs, gr_ids);
        setForm((f) => {
            const kept = f.lines.filter((l) => gr_lines.some((g) => g.gr_detail_id === l.gr_detail_id));
            const existing = new Set(kept.map((l) => l.gr_detail_id));
            const added = gr_lines.filter((g) => !existing.has(g.gr_detail_id))
                .map((g) => ({ gr_detail_id: g.gr_detail_id, qty: g.qty, price: g.price_default, price_kg: g.price_kg_default }));
            return { ...f, gr_ids, gr_lines, lines: [...kept, ...added] };
        });
    };
    const toggleGr = (grId, checked) => applyGrSelection(checked ? [...form.gr_ids, grId] : form.gr_ids.filter((x) => x !== grId));
    const selectAllGrs = () => applyGrSelection(form.grs.map((g) => g.id));

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/ap-invoices/${row.id}`);
        const d = data.data;
        const grs = await loadVendorGrs(d.ven_id, d.id);
        const lineGrDetailIds = (d.detail || []).map((l) => l.gr_detail_id);
        const gr_ids = grs.filter((g) => (g.detail || []).some((x) => lineGrDetailIds.includes(x.id))).map((g) => g.id);
        const gr_lines = await buildGrLines(grs, gr_ids);
        setForm({
            date: d.date?.slice(0, 10), ven_id: d.ven_id, gr_ids, grs, gr_lines, inv_no: d.inv_no,
            vat: d.vat ?? '', wht23: d.wht23 ?? '', tax_inv_no: d.tax_inv_no || '',
            tax_inv_date: d.tax_inv_date?.slice(0, 10) || '', due_date: d.due_date?.slice(0, 10) || '',
            lines: (d.detail || []).map((l) => {
                const meta = gr_lines.find((g) => g.gr_detail_id === l.gr_detail_id);
                const unitW = meta?.unit_w || 0;
                return { gr_detail_id: l.gr_detail_id, qty: l.qty, price: l.price, price_kg: unitW > 0 ? Math.round((Number(l.price) / unitW) * 10000) / 10000 : '' };
            }),
        });
        setModal({ mode: 'edit', id: row.id });
    };

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const addLine = () => set('lines', [...form.lines, { gr_detail_id: '', qty: 1, price: 0, price_kg: '' }]);
    const setLine = (i, patch) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)));
    const delLine = (i) => set('lines', form.lines.filter((_, j) => j !== i));

    const onSelectGrLine = (i, grDetailId) => {
        const meta = grMeta(grDetailId);
        setLine(i, meta
            ? { gr_detail_id: grDetailId, qty: meta.qty, price: meta.price_default, price_kg: meta.price_kg_default }
            : { gr_detail_id: grDetailId });
    };
    const onPriceKg = (i, v, meta) => {
        const unitW = meta?.unit_w || 0;
        setLine(i, { price_kg: v, ...(v !== '' && unitW > 0 ? { price: Math.round(Number(v) * unitW * 100) / 100 } : {}) });
    };
    const onPricePcs = (i, v, meta) => {
        const unitW = meta?.unit_w || 0;
        setLine(i, { price: v, ...(v !== '' && unitW > 0 ? { price_kg: Math.round((Number(v) / unitW) * 10000) / 10000 } : {}) });
    };

    const lineAmount = (l) => (Number(l.qty) || 0) * (Number(l.price) || 0);
    const dpp = form.lines.reduce((a, l) => a + lineAmount(l), 0);
    const taxSuggest = Math.round(form.lines.reduce((a, l) => a + lineAmount(l) * (grMeta(l.gr_detail_id)?.tax_eff || 0), 0));
    const total = dpp + (Number(form.vat) || 0) - (Number(form.wht23) || 0);

    const submit = () => {
        setError('');
        const { gr_lines, grs, gr_ids, ...payload } = form;
        payload.lines = payload.lines.map(({ price_kg, ...l }) => l);
        save.mutate(payload);
    };

    const columns = [
        { key: 'code', label: 'No. AP' },
        { key: 'inv_no', label: 'Inv. Vendor' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'ven', label: 'Vendor', render: (v) => v?.company_n || '—' },
        { key: 'total', label: 'Total', render: (v) => money(v) },
        { key: 'due_date', label: 'Jatuh Tempo', render: (v) => v?.slice(0, 10) || '—' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">AP Invoice (Vendor)</h1>
                {can('ap-invoices', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Invoice</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {row.status === 'DRAFT' && can('ap-invoices', 'edit') && (<>
                            <button title="3-way Match" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate({ id: row.id, action: 'match' })}><Icon name="check" /></button>
                            <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>
                        </>)}
                        {row.status === 'MATCHED' && can('ap-invoices', 'edit') && (
                            <button title="Post" className="rounded p-1.5 text-blue-600 hover:bg-blue-50" onClick={() => act.mutate({ id: row.id, action: 'post' })}><Icon name="lock" /></button>
                        )}
                        {row.status === 'DRAFT' && can('ap-invoices', 'delete') && (
                            <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus invoice?') && remove.mutate(row.id)}><Icon name="trash" /></button>
                        )}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} size="max-w-[95rem]" title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} AP Invoice`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div>
                        <label className="field-label">Vendor <span className="text-red-500">*</span></label>
                        <VendorSelect value={form.ven_id} onChange={onSelectVendor} />
                        <div className="mt-2">
                            <div className="mb-1 flex items-center justify-between">
                                <label className="field-label mb-0">Penerimaan (GR) — bisa lebih dari satu</label>
                                <button type="button" className="btn btn-ghost px-2 py-1 text-xs" onClick={() => setGrModal(true)} disabled={!form.ven_id}><Icon name="search" className="h-3.5 w-3.5" /> Choose GR</button>
                            </div>
                            <div className="max-h-36 overflow-y-auto rounded-md border border-slate-200 p-2">
                                {form.gr_ids.length === 0 && <p className="p-1 text-xs text-slate-400">{form.ven_id ? 'Klik "Choose GR" untuk memilih penerimaan.' : 'Pilih vendor dulu.'}</p>}
                                {form.gr_ids.map((id) => {
                                    const g = form.grs.find((x) => x.id === id);
                                    return (
                                        <div key={id} className="flex items-center justify-between rounded px-1.5 py-1 text-sm hover:bg-slate-50">
                                            <span className="truncate">{g ? `${g.code} — ${g.po_no}` : `GR #${id}`}</span>
                                            <button type="button" className="rounded p-0.5 text-red-500 hover:bg-red-50" onClick={() => toggleGr(id, false)}><Icon name="x" className="h-3.5 w-3.5" /></button>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:col-span-2">
                        <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                        <div><label className="field-label">No. Invoice Vendor <span className="text-red-500">*</span></label><input className="field-input" value={form.inv_no} onChange={(e) => set('inv_no', e.target.value)} /></div>
                        <div><label className="field-label">No. Faktur Pajak</label><input className="field-input" value={form.tax_inv_no} onChange={(e) => set('tax_inv_no', e.target.value)} /></div>
                        <div><label className="field-label">Tgl. Faktur Pajak</label><input type="date" className="field-input" value={form.tax_inv_date} onChange={(e) => set('tax_inv_date', e.target.value)} /></div>
                        <div>
                            <label className="field-label">PPN (VAT)</label>
                            <div className="flex gap-1">
                                <CellInput type="number" step="0.01" value={form.vat} onChange={(v) => set('vat', v)} />
                                <button type="button" className="btn btn-ghost whitespace-nowrap px-2 text-xs" title={`Hitung dari tax code PO: Rp ${money(taxSuggest)}`} onClick={() => set('vat', taxSuggest)}>Auto</button>
                            </div>
                        </div>
                        <div><label className="field-label">PPh 23</label><CellInput type="number" step="0.01" value={form.wht23} onChange={(v) => set('wht23', v)} /></div>
                        <div><label className="field-label">Jatuh Tempo</label><input type="date" className="field-input" value={form.due_date} onChange={(e) => set('due_date', e.target.value)} /></div>
                    </div>
                </div>

                <LineTable title="Baris Invoice (referensi penerimaan / GR)" subtitle="Baris otomatis ditarik dari GR terpilih — harga & tax mengikuti baris PO."
                    onAdd={form.gr_ids.length ? addLine : null} lines={form.lines}
                    empty={form.gr_ids.length ? 'Belum ada baris.' : 'Pilih vendor & GR dulu.'}
                    head={['Penerimaan (GR)', 'Item', 'Qty', 'Berat/pcs (kg)', 'Harga/kg', 'Harga/pcs', 'Tax', 'Jumlah (DPP)', '']}
                    row={(l, i) => {
                        const meta = grMeta(l.gr_detail_id);
                        return (<>
                            <td className="min-w-[220px] px-2 py-1.5">
                                <button type="button" className="field-input flex w-full items-center justify-between gap-2 text-left" onClick={() => setLinePickerFor(i)}>
                                    <span className="truncate">{l.gr_detail_id ? (meta?.label || '—') : <span className="text-slate-400">— pilih penerimaan —</span>}</span>
                                    <Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" />
                                </button>
                            </td>
                            <td className="min-w-[200px] whitespace-nowrap px-2 py-1.5 text-sm text-slate-600">{meta?.item_label || '—'}</td>
                            <td className="px-2 py-1.5"><div className="w-20"><CellInput type="number" value={l.qty} onChange={(v) => setLine(i, { qty: v })} /></div></td>
                            <td className="whitespace-nowrap px-2 py-1.5 text-right text-sm text-slate-600">{meta?.unit_w ? money(meta.unit_w) : '—'}</td>
                            <td className="px-2 py-1.5"><div className="w-32"><CellInput type="number" step="0.0001" value={l.price_kg} onChange={(v) => onPriceKg(i, v, meta)} /></div></td>
                            <td className="px-2 py-1.5"><div className="w-36"><CellInput type="number" step="0.01" value={l.price} onChange={(v) => onPricePcs(i, v, meta)} /></div></td>
                            <td className="whitespace-nowrap px-2 py-1.5 text-sm text-slate-600">{meta?.tax_label || '—'}</td>
                            <td className="px-2 py-1.5"><div className="w-36 rounded bg-slate-50 px-2 py-2 text-right text-sm font-medium text-slate-700">{money(lineAmount(l))}</div></td>
                            <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                        </>);
                    }} />

                <div className="mt-3 flex flex-wrap items-center justify-end gap-6 rounded-md bg-slate-50 px-4 py-3 text-sm">
                    <div>Total DPP: <b>Rp {money(dpp)}</b></div>
                    <div>PPN: <b>Rp {money(Number(form.vat) || 0)}</b> {taxSuggest > 0 && Number(form.vat) !== taxSuggest && <span className="text-xs text-slate-400">(saran: {money(taxSuggest)})</span>}</div>
                    <div>PPh23: <b>−Rp {money(Number(form.wht23) || 0)}</b></div>
                    <div className="text-base">Total: <b className="text-slate-800">Rp {money(total)}</b></div>
                </div>
            </Modal>

            <PickerModal
                open={linePickerFor != null}
                onClose={() => setLinePickerFor(null)}
                title="Pilih Penerimaan (GR)"
                size="max-w-6xl"
                rows={(form.gr_lines || []).map((g) => ({ ...g, _key: g.gr_detail_id }))}
                searchKeys={['gr_code', 'item_code', 'part_name']}
                columns={[
                    { key: 'gr_code', label: 'GR' },
                    { key: 'item_code', label: 'Code' },
                    { key: 'part_name', label: 'Part Name' },
                    { key: 'o_d', label: 'OD', className: 'text-right' },
                    { key: 'i_d', label: 'ID', className: 'text-right' },
                    { key: 'thick', label: 'Thick', className: 'text-right' },
                    { key: 'qty', label: 'Qty', className: 'text-right' },
                ]}
                onSelect={(row) => onSelectGrLine(linePickerFor, row.gr_detail_id)}
            />

            <ChooseGrModal
                open={grModal}
                onClose={() => setGrModal(false)}
                grs={form.grs}
                itemsById={itemsById}
                chosenIds={form.gr_ids}
                onToggle={(g) => toggleGr(g.id, !form.gr_ids.includes(g.id))}
            />
        </div>
    );
}
