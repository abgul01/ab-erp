import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, useOptions, money } from '../procurement/common';
import ChooseGrModal from '../../components/ChooseGrModal';

const today = () => new Date().toISOString().slice(0, 10);

/* ---------------- Select serials by material code ---------------- */
function ChooseSerialModal({ open, onClose, grIds, itemsById, existing, onAdd }) {
    const [q, setQ] = useState('');
    const [expanded, setExpanded] = useState({});
    const [checked, setChecked] = useState({});

    const avail = useQuery({
        queryKey: ['incoming-rm', 'available', grIds],
        queryFn: async () => (await api.get('/incoming-rm/available', { params: { gr_ids: grIds.join(',') } })).data.data,
        enabled: open && grIds.length > 0,
    });
    useEffect(() => { if (open) { setQ(''); setChecked({}); setExpanded({}); } }, [open, grIds.join(',')]);
    if (!open) return null;

    const term = q.trim().toLowerCase();
    const rows = (avail.data || []).filter((s) => existing.indexOf(s.serial_id) === -1);
    const groups = {};
    for (const s of rows) {
        const sp = itemsById[s.item_id] || {};
        const code = sp.code || (s.item_label || '').split(' — ')[0];
        (groups[s.item_id] ||= { item_id: s.item_id, code, part_name: sp.part_name, o_d: sp.o_d, i_d: sp.i_d, thick: sp.thick, serials: [] }).serials.push(s);
    }
    const visibleGroups = Object.values(groups).filter((g) => !term
        || (g.code || '').toLowerCase().includes(term)
        || (g.part_name || '').toLowerCase().includes(term)
        || g.serials.some((s) => (s.serial_id || '').toLowerCase().includes(term)));

    const selectedCount = Object.values(checked).filter(Boolean).length;
    const toggle = (sid, on) => setChecked((c) => ({ ...c, [sid]: on }));
    const toggleGroup = (g, on) => setChecked((c) => { const n = { ...c }; g.serials.forEach((s) => { n[s.serial_id] = on; }); return n; });
    const submit = () => { onAdd(rows.filter((s) => checked[s.serial_id])); onClose(); };

    return (
        <div className="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8">
            <div className="card my-4 w-full max-w-4xl">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <div><h3 className="text-base font-semibold text-slate-800">Select Serials by Material Code</h3>
                        <p className="text-xs text-blue-600">{selectedCount} serial(s) selected</p></div>
                    <button onClick={onClose} className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><Icon name="x" /></button>
                </div>
                <div className="px-5 py-3">
                    <div className="relative mb-3">
                        <Icon name="search" className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input autoFocus className="field-input pl-9" placeholder="Search Material Code / Part Name / Serial Number…" value={q} onChange={(e) => setQ(e.target.value)} />
                    </div>
                    <div className="max-h-[58vh] space-y-2 overflow-auto">
                        {avail.isLoading && <p className="py-6 text-center text-sm text-slate-400">Memuat…</p>}
                        {!avail.isLoading && visibleGroups.length === 0 && <p className="py-6 text-center text-sm text-slate-400">Tidak ada serial tersedia.</p>}
                        {visibleGroups.map((g) => {
                            const isOpen = expanded[g.item_id] ?? true;
                            const allOn = g.serials.every((s) => checked[s.serial_id]);
                            return (
                                <div key={g.item_id} className="rounded-md border border-slate-200">
                                    <div className="flex items-center gap-3 bg-sky-50 px-3 py-2">
                                        <input type="checkbox" className="h-4 w-4" checked={allOn} onChange={(e) => toggleGroup(g, e.target.checked)} />
                                        <button className="rounded p-0.5 text-slate-400" onClick={() => setExpanded((e) => ({ ...e, [g.item_id]: !isOpen }))}>
                                            <Icon name="chevron" className={`h-4 w-4 transition ${isOpen ? '' : '-rotate-90'}`} />
                                        </button>
                                        <div className="flex-1 text-sm">
                                            <div className="font-semibold text-slate-800">{g.code}</div>
                                            <div className="text-xs text-slate-500">{g.part_name} {g.o_d != null && `| OD:${g.o_d} ID:${g.i_d ?? '-'} Thick:${g.thick ?? '-'}`}</div>
                                        </div>
                                        <span className="text-xs text-slate-500">{g.serials.length} serials</span>
                                    </div>
                                    {isOpen && (
                                        <div>
                                            {g.serials.map((s) => (
                                                <label key={s.serial_id} className="flex cursor-pointer items-center gap-3 border-t border-slate-100 px-4 py-1.5 text-sm hover:bg-slate-50">
                                                    <input type="checkbox" className="h-4 w-4" checked={!!checked[s.serial_id]} onChange={(e) => toggle(s.serial_id, e.target.checked)} />
                                                    <span className="w-64">Serial: <b>{s.serial_id}</b></span>
                                                    <span className="w-36 text-slate-500">Length: <b>{money(s.length)}</b></span>
                                                    <span className="w-24 text-slate-500">Qty: <b>{s.qty}</b></span>
                                                    <span className="text-slate-500">Status: <b className="text-emerald-600">Available</b></span>
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
                <div className="flex items-center justify-between border-t border-slate-200 px-5 py-3">
                    <button className="text-sm text-slate-500 hover:underline" onClick={() => setChecked({})}>Clear Selection</button>
                    <div className="flex gap-2">
                        <button className="btn btn-ghost" onClick={onClose}>Cancel</button>
                        <button className="btn btn-primary" onClick={submit} disabled={selectedCount === 0}>Select Serial(s)</button>
                    </div>
                </div>
            </div>
        </div>
    );
}

/* ---------------- Page ---------------- */
export default function IncomingPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const user = useAuth((s) => s.user);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(null);
    const [view, setView] = useState(null);
    const [grModal, setGrModal] = useState(false);
    const [serialModal, setSerialModal] = useState(false);
    const [error, setError] = useState('');

    const racks = useOptions('racks');
    const shifts = useOptions('shifts');
    const items = useOptions('items');
    const itemsById = Object.fromEntries((items.data || []).map((it) => [it.id, it]));

    const grs = useQuery({
        queryKey: ['grn', 'for-incoming-detail'],
        // Hanya GR yang masih punya serial belum di-incoming.
        queryFn: async () => (await api.get('/grn', { params: { with_detail: 1, incoming_pending: 1, per_page: 300 } })).data.data,
        staleTime: 10_000,
    });

    const list = useQuery({
        queryKey: ['incoming-rm', { page }],
        queryFn: async () => (await api.get('/incoming-rm', { params: { page, per_page: 15 } })).data,
    });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['incoming-rm'] }); qc.invalidateQueries({ queryKey: ['stock-rm'] }); qc.invalidateQueries({ queryKey: ['grn'] }); };

    const save = useMutation({
        mutationFn: async (payload) => api.post('/incoming-rm', payload),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/incoming-rm/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setForm({ date: today(), shift_id: '', default_rack_id: '', gr_ids: [], gr_codes: {}, rows: [] }); setError(''); setModal({ mode: 'create' }); };
    const openView = async (row) => { const { data } = await api.get(`/incoming-rm/${row.id}`); setView(data.data); };

    const toggleGr = (g) => setForm((f) => {
        const on = f.gr_ids.includes(g.id);
        return {
            ...f,
            gr_ids: on ? f.gr_ids.filter((x) => x !== g.id) : [...f.gr_ids, g.id],
            gr_codes: { ...f.gr_codes, [g.id]: g.code },
        };
    });
    const addSerials = (serials) => setForm((f) => ({
        ...f,
        rows: [...f.rows, ...serials.map((s) => {
            const sp = itemsById[s.item_id] || {};
            return {
                serial_id: s.serial_id, millsheet: s.millsheet || '-', gr_id: s.gr_id,
                item_id: s.item_id, item_code: sp.code || (s.item_label || '').split(' — ')[0],
                o_d: sp.o_d, i_d: sp.i_d, thick: sp.thick,
                length: s.length, qty: s.qty, rack_id: f.default_rack_id || '',
            };
        })],
    }));
    const setRow = (i, patch) => setForm((f) => ({ ...f, rows: f.rows.map((r, j) => (j === i ? { ...r, ...patch } : r)) }));
    const delRow = (i) => setForm((f) => ({ ...f, rows: f.rows.filter((_, j) => j !== i) }));

    const submit = () => {
        setError('');
        save.mutate({
            date: form.date, shift_id: form.shift_id || null,
            lines: form.rows.map((r) => ({ gr_id: r.gr_id, serial_id: r.serial_id, item_id: r.item_id, qty: r.qty, length: r.length, rack_id: r.rack_id })),
        });
    };

    const columns = [
        { key: 'code', label: 'No. Incoming' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'gr', label: 'GR', render: (v) => v?.code || '—' },
        { key: 'detail_count', label: '# Serial' },
        { key: 'user', label: 'Oleh', render: (v) => v?.name || '—' },
    ];

    const existingSerials = form?.rows.map((r) => r.serial_id) || [];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Incoming RM</h1>
                {can('incoming-rm', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Create Incoming</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {can('incoming-rm', 'delete') && <button title="Batalkan" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Batalkan incoming ini?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            {/* Create */}
            <Modal open={modal?.mode === 'create'} onClose={() => setModal(null)} size="max-w-[95rem]" title="Create Incoming Data"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit} disabled={save.isPending || form?.rows.length === 0 || form?.rows.some((r) => !r.rack_id)}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Save ({form?.rows.length || 0})
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {form && (<>
                    <div className="mb-4 rounded-md border border-slate-200 p-4">
                        <h4 className="mb-3 text-sm font-semibold text-slate-700">Header Information</h4>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                            <div><label className="field-label">User ID</label><input className="field-input bg-slate-50" value={user?.username || ''} disabled /></div>
                            <div><label className="field-label">Transactions Code</label><input className="field-input bg-slate-50" value="(otomatis saat simpan)" disabled /></div>
                            <div><label className="field-label">Date <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))} /></div>
                            <div>
                                <label className="field-label">GR Code</label>
                                <div className="flex gap-1">
                                    <input className="field-input bg-slate-50" value={form.gr_ids.map((id) => form.gr_codes[id]).join(', ')} placeholder="— pilih GR —" disabled />
                                    <button type="button" className="btn btn-ghost whitespace-nowrap px-2 text-xs" onClick={() => setGrModal(true)}><Icon name="search" className="h-4 w-4" /> Search</button>
                                </div>
                            </div>
                            <div><label className="field-label">Shift</label>
                                <Select value={form.shift_id} onChange={(v) => setForm((f) => ({ ...f, shift_id: v }))} options={shifts.data} getValue={(o) => o.id} getLabel={(o) => o.name} placeholder="Select Shift" />
                            </div>
                            <div><label className="field-label">Default Rack</label>
                                <Select value={form.default_rack_id} onChange={(v) => setForm((f) => ({ ...f, default_rack_id: v, rows: f.rows.map((r) => (r.rack_id ? r : { ...r, rack_id: v })) }))} options={(racks.data || []).filter((r) => r.active)} getValue={(o) => o.id} getLabel={(o) => o.location} placeholder="Select Rack" />
                            </div>
                            <div className="flex items-end sm:col-span-2">
                                <button type="button" className="btn btn-primary" onClick={() => setSerialModal(true)} disabled={form.gr_ids.length === 0}><Icon name="plus" /> Choose Serial</button>
                            </div>
                        </div>
                    </div>

                    <h4 className="mb-2 text-sm font-semibold text-slate-700">Incoming Part Details</h4>
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                {['No', 'Serial ID', 'Millsheet', 'Material Code', 'OD', 'ID', 'Thickness', 'Length', 'Rack', 'Qty', 'Action'].map((h, k) => <th key={k} className="whitespace-nowrap px-3 py-2">{h}</th>)}
                            </tr></thead>
                            <tbody>
                                {form.rows.length === 0 && <tr><td colSpan={11} className="px-3 py-5 text-center text-slate-400">Pilih GR lalu "Choose Serial".</td></tr>}
                                {form.rows.map((r, i) => (
                                    <tr key={r.serial_id} className="border-t border-slate-100">
                                        <td className="px-3 py-2 text-slate-400">{i + 1}</td>
                                        <td className="whitespace-nowrap px-3 py-2 font-medium text-slate-700">{r.serial_id}</td>
                                        <td className="whitespace-nowrap px-3 py-2 text-slate-500">{r.millsheet}</td>
                                        <td className="whitespace-nowrap px-3 py-2">{r.item_code}</td>
                                        <td className="px-3 py-2 text-right">{r.o_d ?? '—'}</td>
                                        <td className="px-3 py-2 text-right">{r.i_d ?? '—'}</td>
                                        <td className="px-3 py-2 text-right">{r.thick ?? '—'}</td>
                                        <td className="px-3 py-2"><input type="number" className="field-input w-24" value={r.length ?? ''} onChange={(e) => setRow(i, { length: e.target.value === '' ? '' : Number(e.target.value) })} /></td>
                                        <td className="min-w-[150px] px-3 py-2"><Select value={r.rack_id} onChange={(v) => setRow(i, { rack_id: v })} options={(racks.data || []).filter((x) => x.active)} getValue={(o) => o.id} getLabel={(o) => o.location} placeholder="— rak —" /></td>
                                        <td className="px-3 py-2"><input type="number" className="field-input w-16" value={r.qty ?? ''} onChange={(e) => setRow(i, { qty: e.target.value === '' ? '' : Number(e.target.value) })} /></td>
                                        <td className="px-3 py-2"><button type="button" className="rounded bg-red-500 px-2 py-1 text-xs text-white hover:bg-red-600" onClick={() => delRow(i)}>delete</button></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>)}
            </Modal>

            <ChooseGrModal open={grModal} onClose={() => setGrModal(false)} grs={grs.data} itemsById={itemsById} chosenIds={form?.gr_ids || []} onToggle={toggleGr} />
            <ChooseSerialModal open={serialModal} onClose={() => setSerialModal(false)} grIds={form?.gr_ids || []} itemsById={itemsById} existing={existingSerials} onAdd={addSerials} />

            {/* View */}
            <Modal open={!!view} onClose={() => setView(null)} wide title={`Incoming ${view?.code || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setView(null)}>Tutup</button>}>
                {view && (<>
                    <div className="mb-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                        <div><span className="text-slate-400">GR</span><div className="font-medium">{view.gr?.code}</div></div>
                        <div><span className="text-slate-400">Vendor</span><div className="font-medium">{view.gr?.ven?.company_n || '—'}</div></div>
                        <div><span className="text-slate-400">Tanggal</span><div className="font-medium">{view.date?.slice(0, 10)}</div></div>
                        <div><span className="text-slate-400">Oleh</span><div className="font-medium">{view.user?.name}</div></div>
                    </div>
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                {['Serial', 'Item', 'Qty', 'Length', 'Rak'].map((h, k) => <th key={k} className="px-3 py-2">{h}</th>)}
                            </tr></thead>
                            <tbody>
                                {(view.detail || []).map((d) => (
                                    <tr key={d.id} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5 font-medium text-slate-700">{d.serial_id}</td>
                                        <td className="px-3 py-1.5">{d.item?.code} — {d.item?.part_name}</td>
                                        <td className="px-3 py-1.5 text-right">{d.qty}</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.length)}</td>
                                        <td className="px-3 py-1.5">{d.rack?.location || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>)}
            </Modal>
        </div>
    );
}
