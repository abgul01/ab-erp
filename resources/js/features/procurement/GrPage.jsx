import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { StatusBadge, CellInput, money, useOptions } from './common';
import PickerModal from '../../components/PickerModal';
import SerialModal from './SerialModal';

const today = () => new Date().toISOString().slice(0, 10);
const newLine = () => ({ key: '', po_id: '', item_id: '', quota_id: '', hs_code: '', length: '', weight: '', qty: '', serials: [] });

export default function GrPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(null);
    const [view, setView] = useState(null);
    const [serialFor, setSerialFor] = useState(null);
    const [itemPickerFor, setItemPickerFor] = useState(null);
    const [error, setError] = useState('');

    const quotas = useOptions('quotas');
    const items = useOptions('items');
    const itemSpec = (id) => (items.data || []).find((it) => it.id === Number(id)) || {};
    const receivablePos = useQuery({
        queryKey: ['po', 'receivable'],
        queryFn: async () => {
            const d = (await api.get('/po', { params: { per_page: 300 } })).data.data;
            return d.filter((p) => ['OPEN', 'INPROGRESS'].includes(p.status));
        },
        staleTime: 30_000,
    });

    const list = useQuery({
        queryKey: ['grn', { page }],
        queryFn: async () => (await api.get('/grn', { params: { page, per_page: 15 } })).data,
    });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['grn'] }); qc.invalidateQueries({ queryKey: ['po'] }); qc.invalidateQueries({ queryKey: ['quotas'] }); };

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/grn/${form.id}`, payload) : api.post('/grn', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/grn/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    /** Load PO detail lines → po_items entries keyed per (po,item). */
    const loadPoItems = async (poIds) => {
        const entries = [];
        for (const poId of poIds) {
            const { data } = await api.get(`/po/${poId}`);
            const d = data.data;
            for (const l of d.detail || []) {
                entries.push({
                    key: `${d.id}-${l.item_id}`,
                    po_id: d.id,
                    po_code: d.code,
                    is_import: d.source === 'IMPORT' && d.po_type === 'RM',
                    item_id: l.item_id,
                    label: `${d.code} · ${l.item?.code || ''} — ${l.item?.part_name || ''}`,
                    qty: l.qty,
                    qty_received: l.qty_received || 0,
                });
            }
        }
        return entries;
    };

    const openCreate = () => { setForm({ po_ids: [], date: today(), import_doc_no: '', po_items: [], lines: [] }); setError(''); setModal({ mode: 'create' }); };

    const togglePo = async (poId, checked) => {
        const po_ids = checked ? [...form.po_ids, poId] : form.po_ids.filter((x) => x !== poId);
        const po_items = await loadPoItems(po_ids);
        setForm((f) => ({ ...f, po_ids, po_items, lines: f.lines.filter((l) => po_ids.includes(l.po_id)) }));
    };
    const selectAllPos = async (vendorGroup) => {
        const po_ids = vendorGroup.map((p) => p.id);
        const po_items = await loadPoItems(po_ids);
        setForm((f) => ({ ...f, po_ids, po_items, lines: f.lines.filter((l) => po_ids.includes(l.po_id)) }));
    };

    const openView = async (row) => { const { data } = await api.get(`/grn/${row.id}`); setView(data.data); };
    const openEdit = async (row) => {
        setError('');
        const g = (await api.get(`/grn/${row.id}`)).data.data;
        const po_ids = [...new Set((g.detail || []).map((d) => d.po_id).filter(Boolean))];
        const po_items = await loadPoItems(po_ids);
        setForm({
            id: g.id, po_ids, po_locked: true, po_no: g.po_no,
            date: g.date?.slice(0, 10), import_doc_no: g.import_doc_no || '', po_items,
            lines: (g.detail || []).map((d) => ({
                key: `${d.po_id}-${d.item_id}`, po_id: d.po_id, item_id: d.item_id,
                quota_id: d.quota_id || '', hs_code: d.hs_code || '',
                length: d.length ?? d.serials?.[0]?.length ?? '',
                weight: d.weight ?? d.serials?.[0]?.weight ?? '', qty: d.qty,
                serials: (d.serials || []).map((s) => ({ serial_id: s.serial_id, millsheet: s.millsheet, qty: s.qty, length: s.length, weight: s.weight, status: s.status })),
            })),
        });
        setModal({ mode: 'edit' });
    };

    const quotasForItem = (itemId) => (quotas.data || []).filter((q) => (q.items || []).some((it) => it.item_id === Number(itemId)) && q.hs_code);
    const outstandingOf = (key) => {
        const rows = (form?.po_items || []).filter((p) => p.key === key);
        const ordered = rows.reduce((a, r) => a + Number(r.qty || 0), 0);
        const recv = rows.reduce((a, r) => a + Number(r.qty_received || 0), 0);
        return { ordered, recv, outstanding: ordered - recv };
    };

    const addLine = () => setForm((f) => ({ ...f, lines: [...f.lines, newLine()] }));
    const setLine = (i, patch) => setForm((f) => ({ ...f, lines: f.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)) }));
    const delLine = (i) => setForm((f) => ({ ...f, lines: f.lines.filter((_, j) => j !== i) }));

    const onSelectEntry = (i, key) => {
        const entry = (form.po_items || []).find((p) => p.key === key);
        if (!entry) { setLine(i, { key, po_id: '', item_id: '' }); return; }
        const opts = quotasForItem(entry.item_id);
        const auto = opts.length === 1 ? { quota_id: opts[0].id, hs_code: opts[0].hs_code } : { quota_id: '', hs_code: '' };
        setLine(i, { key, po_id: entry.po_id, item_id: entry.item_id, ...auto });
    };
    const onSelectHs = (i, quotaId) => {
        const q = (quotas.data || []).find((x) => x.id === Number(quotaId));
        setLine(i, { quota_id: quotaId ? Number(quotaId) : '', hs_code: q?.hs_code || '' });
    };

    const submit = () => {
        setError('');
        save.mutate({
            date: form.date, import_doc_no: form.import_doc_no,
            lines: form.lines.map((l) => ({ po_id: l.po_id, item_id: l.item_id, quota_id: l.quota_id || null, hs_code: l.hs_code || null, length: l.length || null, serials: l.serials })),
        });
    };

    const columns = [
        { key: 'code', label: 'No. GR' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'po_no', label: 'PO', render: (v) => <span className="whitespace-pre-wrap text-xs">{(v || '').split(',').join('\n')}</span> },
        { key: 'ven', label: 'Vendor', render: (v) => v?.company_n || '—' },
        { key: 'import_doc_no', label: 'Dok. Impor' },
        { key: 'detail_count', label: '# Item' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    // Group receivable POs by vendor so multi-select stays same-vendor.
    const vendorOf = (id) => (receivablePos.data || []).find((p) => p.id === id)?.ven_id;
    const activeVendor = form?.po_ids?.length ? vendorOf(form.po_ids[0]) : null;
    const selectablePos = (receivablePos.data || []).filter((p) => !activeVendor || p.ven_id === activeVendor);

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Goods Receipt (GRN)</h1>
                {can('grn', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Terima Barang</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {can('grn', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {can('grn', 'delete') && <button title="Batalkan" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Batalkan GR ini? Efek stok & kuota akan dibatalkan.') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            {/* Create / Edit modal */}
            <Modal open={!!modal} onClose={() => setModal(null)} size="max-w-[95rem]" title={modal?.mode === 'edit' ? 'Edit GR' : 'Terima Barang (GRN)'}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit} disabled={save.isPending || !form?.po_ids?.length || form?.lines.length === 0}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {form && (<>
                    <div className="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div>
                            <div className="mb-1 flex items-center justify-between">
                                <label className="field-label mb-0">PO (Open / In Progress) <span className="text-red-500">*</span></label>
                                {!form.po_locked && selectablePos.length > 0 && (
                                    <button type="button" className="text-xs text-blue-600 hover:underline" onClick={() => selectAllPos(selectablePos)}>Pilih semua</button>
                                )}
                            </div>
                            {form.po_locked ? (
                                <div className="rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-600 whitespace-pre-wrap">{(form.po_no || '').split(',').join('\n')}</div>
                            ) : (
                                <div className="max-h-40 overflow-y-auto rounded-md border border-slate-200 p-2">
                                    {selectablePos.length === 0 && <p className="p-1 text-xs text-slate-400">Tidak ada PO Open/In Progress{activeVendor ? ' untuk vendor ini' : ''}.</p>}
                                    {selectablePos.map((p) => (
                                        <label key={p.id} className="flex items-center gap-2 rounded px-1.5 py-1 text-sm hover:bg-slate-50">
                                            <input type="checkbox" className="h-4 w-4" checked={form.po_ids.includes(p.id)} onChange={(e) => togglePo(p.id, e.target.checked)} />
                                            <span className="truncate">{p.code} — {p.ven?.company_n}</span>
                                        </label>
                                    ))}
                                </div>
                            )}
                            {activeVendor && !form.po_locked && <p className="mt-1 text-xs text-slate-400">Satu GR = satu vendor; daftar difilter ke vendor PO pertama.</p>}
                        </div>
                        <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))} /></div>
                        <div><label className="field-label">No. Dokumen Impor</label><input className="field-input" value={form.import_doc_no} onChange={(e) => setForm((f) => ({ ...f, import_doc_no: e.target.value }))} placeholder="BL / PIB (opsional)" /></div>
                    </div>

                    {form.po_ids.length > 0 && (
                        <div className="mb-2 flex items-center justify-between">
                            <h4 className="text-sm font-semibold text-slate-700">Item yang datang (aktual)</h4>
                            <button type="button" className="btn btn-ghost px-2 py-1 text-xs" onClick={addLine}><Icon name="plus" className="h-3.5 w-3.5" /> Item</button>
                        </div>
                    )}

                    {form.po_ids.length > 0 && (
                        <div className="overflow-x-auto rounded-md border border-slate-200">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                        {['PO · Item', 'Dipesan / Diterima / Sisa', 'HS Code', 'Length (mm)', 'Weight/bar (kg)', 'Qty', 'Serial', 'Terisi', ''].map((h, k) => <th key={k} className="whitespace-nowrap px-3 py-2">{h}</th>)}
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.lines.length === 0 && (
                                        <tr><td colSpan={9} className="px-3 py-5 text-center text-slate-400">Belum ada item. Klik "+ Item" untuk menambah item yang datang.</td></tr>
                                    )}
                                    {form.lines.map((l, i) => {
                                        const o = l.key ? outstandingOf(l.key) : null;
                                        const hsOpts = l.item_id ? quotasForItem(l.item_id) : [];
                                        const recvQty = l.serials.reduce((a, s) => a + (Number(s.qty) || 0), 0);
                                        const recvW = l.serials.reduce((a, s) => a + (Number(s.weight) || 0), 0);
                                        return (
                                            <tr key={i} className="border-t border-slate-100 align-middle">
                                                <td className="min-w-[300px] px-3 py-2">
                                                    <button type="button" className="field-input flex w-full items-center justify-between gap-2 text-left" onClick={() => setItemPickerFor(i)}>
                                                        <span className="truncate">{l.key ? ((form.po_items || []).find((p) => p.key === l.key)?.label || '—') : <span className="text-slate-400">— pilih PO · item —</span>}</span>
                                                        <Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" />
                                                    </button>
                                                    {l.serials.length > 0 && (
                                                        <div className="mt-1 flex flex-wrap gap-1">
                                                            {l.serials.slice(0, 6).map((s, k) => (
                                                                <span key={k} className={`rounded border px-1.5 py-0.5 text-[11px] ${s.status === 'NG' ? 'border-red-200 bg-red-50 text-red-600' : 'border-slate-200 bg-slate-50 text-slate-500'}`}>{s.serial_id}</span>
                                                            ))}
                                                            {l.serials.length > 6 && <span className="px-1 text-[11px] text-slate-400">+{l.serials.length - 6} lagi</span>}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-2 text-xs text-slate-500">
                                                    {o ? (<><b>{o.ordered}</b> / <b>{o.recv}</b> / <b className={o.outstanding <= 0 ? 'text-emerald-600' : 'text-amber-600'}>{o.outstanding}</b></>) : '—'}
                                                </td>
                                                <td className="min-w-[190px] px-3 py-2">
                                                    <select className="field-input" value={l.quota_id} onChange={(e) => onSelectHs(i, e.target.value)} disabled={!l.item_id}>
                                                        <option value="">{hsOpts.length ? '— pilih HS / kuota —' : '(tidak ada kuota)'}</option>
                                                        {hsOpts.map((q) => <option key={q.id} value={q.id}>{q.hs_code} — {q.code}</option>)}
                                                    </select>
                                                </td>
                                                <td className="px-3 py-2"><div className="w-24"><CellInput type="number" value={l.length} onChange={(v) => setLine(i, { length: v })} /></div></td>
                                                <td className="px-3 py-2"><div className="w-24"><CellInput type="number" step="0.01" value={l.weight} onChange={(v) => setLine(i, { weight: v })} /></div></td>
                                                <td className="px-3 py-2"><div className="w-20"><CellInput type="number" value={l.qty} onChange={(v) => setLine(i, { qty: v })} /></div></td>
                                                <td className="whitespace-nowrap px-3 py-2">
                                                    <button type="button" className="btn btn-ghost px-2 py-1 text-xs" onClick={() => setSerialFor(i)} disabled={!l.qty}><Icon name="plus" className="h-3.5 w-3.5" /> Serial ({l.serials.length})</button>
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-2 text-xs text-slate-500"><b>{recvQty}</b> pcs / <b>{money(recvW)}</b> kg</td>
                                                <td className="px-3 py-2"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </>)}
            </Modal>

            {/* PO · item picker */}
            <PickerModal
                open={itemPickerFor != null}
                onClose={() => setItemPickerFor(null)}
                title="Pilih PO · Item"
                size="max-w-6xl"
                rows={(form?.po_items || []).map((p) => { const s = itemSpec(p.item_id); return { ...p, _key: p.key, code: s.code, part_name: s.part_name, o_d: s.o_d, i_d: s.i_d, thick: s.thick, width: s.width, height: s.height, outstanding: outstandingOf(p.key).outstanding }; })}
                searchKeys={['po_code', 'code', 'part_name']}
                columns={[
                    { key: 'po_code', label: 'PO' },
                    { key: 'code', label: 'Code' },
                    { key: 'part_name', label: 'Part Name' },
                    { key: 'o_d', label: 'OD', className: 'text-right' },
                    { key: 'i_d', label: 'ID', className: 'text-right' },
                    { key: 'thick', label: 'Thick', className: 'text-right' },
                    { key: 'width', label: 'Width', className: 'text-right' },
                    { key: 'height', label: 'Height', className: 'text-right' },
                    { key: 'outstanding', label: 'Sisa', className: 'text-right' },
                ]}
                onSelect={(row) => onSelectEntry(itemPickerFor, row.key)}
            />

            {/* Serial generator */}
            {serialFor != null && form?.lines[serialFor] && (
                <SerialModal
                    open
                    onClose={() => setSerialFor(null)}
                    itemLabel={(form.po_items.find((p) => p.key === form.lines[serialFor].key)?.label) || ''}
                    qty={form.lines[serialFor].qty}
                    defaultLength={form.lines[serialFor].length}
                    defaultWeight={form.lines[serialFor].weight}
                    initial={form.lines[serialFor].serials}
                    onSave={(serials) => setLine(serialFor, { serials })}
                />
            )}

            {/* View modal */}
            <Modal open={!!view} onClose={() => setView(null)} wide title={`GR ${view?.code || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setView(null)}>Tutup</button>}>
                {view && (<>
                    <div className="mb-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                        <div><span className="text-slate-400">PO</span><div className="whitespace-pre-wrap text-xs font-medium">{(view.po_no || '').split(',').join('\n')}</div></div>
                        <div><span className="text-slate-400">Vendor</span><div className="font-medium">{view.ven?.company_n}</div></div>
                        <div><span className="text-slate-400">Tanggal</span><div className="font-medium">{view.date?.slice(0, 10)}</div></div>
                        <div><span className="text-slate-400">Dok. Impor</span><div className="font-medium">{view.import_doc_no || '—'}</div></div>
                    </div>
                    {(view.detail || []).map((d) => (
                        <div key={d.id} className="mb-3 rounded-md border border-slate-200 p-3">
                            <div className="mb-1 flex justify-between text-sm font-medium text-slate-700">
                                <span>{d.po?.code ? `${d.po.code} · ` : ''}{d.item?.code} — {d.item?.part_name}</span>
                                <span className="text-slate-500">{d.qty} pcs / {money(d.w_total)} kg {d.hs_code ? `· HS ${d.hs_code}` : ''}</span>
                            </div>
                            <div className="flex flex-wrap gap-1 text-xs">
                                {(d.serials || []).map((s) => (
                                    <span key={s.id} className={`rounded border px-2 py-0.5 ${s.status === 'NG' ? 'border-red-200 bg-red-50 text-red-600' : 'border-slate-200 bg-slate-50 text-slate-600'}`}>
                                        {s.serial_id} · {s.qty}pcs · {money(s.weight)}kg {s.millsheet ? `· ${s.millsheet}` : ''}
                                    </span>
                                ))}
                            </div>
                        </div>
                    ))}
                </>)}
            </Modal>
        </div>
    );
}
