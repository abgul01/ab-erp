import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import PickerModal from '../../components/PickerModal';
import { ITEM_COLUMNS } from '../procurement/PoPage';
import { Select, useOptions, money } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);
let _uid = 0;
const uid = () => `r${++_uid}`;
const STATUS = { 1: 'bg-slate-100 text-slate-600', 2: 'bg-emerald-100 text-emerald-700', 3: 'bg-blue-100 text-blue-700', 9: 'bg-red-100 text-red-700' };
const WoStatus = ({ wo }) => <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${STATUS[wo.status] || 'bg-slate-100'}`}>{wo.status_label || wo.status}</span>;
const num = (v) => (v === '' || v == null ? '' : Number(v));

/* ---------- Choose BOM (RM or PM) ---------- */
function ChooseBomModal({ open, onClose, title, columns, rows, onPick }) {
    const [q, setQ] = useState('');
    const [sel, setSel] = useState(null);
    if (!open) return null;
    const term = q.trim().toLowerCase();
    const list = (rows || []).filter((r) => !term || (r.code || '').toLowerCase().includes(term) || (r.spec || '').toLowerCase().includes(term));
    return (
        <div className="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8">
            <div className="card my-4 w-full max-w-3xl">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h3 className="text-base font-semibold text-slate-800">{title}</h3>
                    <button onClick={onClose} className="rounded p-1 text-slate-400 hover:bg-slate-100"><Icon name="x" /></button>
                </div>
                <div className="px-5 py-3">
                    <div className="relative mb-3"><Icon name="search" className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input autoFocus className="field-input pl-9" placeholder="Cari material / spec…" value={q} onChange={(e) => setQ(e.target.value)} /></div>
                    <div className="max-h-[50vh] overflow-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-3 py-2"></th><th className="px-3 py-2">No</th>{columns.map((c, i) => <th key={i} className="px-3 py-2">{c.label}</th>)}
                            </tr></thead>
                            <tbody>
                                {list.length === 0 && <tr><td colSpan={columns.length + 2} className="px-3 py-5 text-center text-slate-400">Tidak ada.</td></tr>}
                                {list.map((r, i) => (
                                    <tr key={i} className="cursor-pointer border-t border-slate-100 hover:bg-blue-50" onClick={() => setSel(r)}>
                                        <td className="px-3 py-2"><input type="radio" checked={sel === r} readOnly /></td>
                                        <td className="px-3 py-2">{i + 1}</td>
                                        {columns.map((c, ci) => <td key={ci} className="px-3 py-2">{c.render ? c.render(r) : r[c.key]}</td>)}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button className="btn btn-primary" disabled={!sel} onClick={() => { onPick(sel); setSel(null); setQ(''); onClose(); }}>Add to Table</button>
                    <button className="btn btn-ghost" onClick={onClose}>Cancel</button>
                </div>
            </div>
        </div>
    );
}

/* ---------- Serial Raw Material ---------- */
function SerialRmModal({ open, onClose, itemId, excludeWo, existing, onAdd, needPcs = 0, bookedPcs = 0, lengthUse = 0, noCut = false }) {
    const [q, setQ] = useState('');
    const [checked, setChecked] = useState({});
    const avail = useQuery({
        queryKey: ['wo-serials', itemId, excludeWo],
        queryFn: async () => (await api.get('/work-orders/available-serials', { params: { item_id: itemId, exclude_wo: excludeWo || 0 } })).data.data,
        enabled: open && !!itemId,
    });
    if (!open) return null;
    const term = q.trim().toLowerCase();
    const rows = (avail.data || []).filter((s) => !existing.includes(s.serial_id) && (!term || s.serial_id.toLowerCase().includes(term) || (s.millsheet || '').toLowerCase().includes(term)));
    const sel = rows.filter((r) => checked[r.serial_id]);
    const lu = Number(lengthUse) || 0;
    const pcsOf = (r) => (noCut ? 1 : (lu > 0 ? Math.floor((Number(r.length) || 0) / lu) : 0));
    const shortage = Math.max(0, (Number(needPcs) || 0) - (Number(bookedPcs) || 0));
    const selPcs = sel.reduce((a, r) => a + pcsOf(r), 0);
    const autoPick = () => {
        let acc = 0; const next = {};
        for (const r of rows) {
            if (acc >= shortage) break;
            const p = pcsOf(r);
            if (p <= 0) continue;
            next[r.serial_id] = true;
            acc += p;
        }
        setChecked(next);
    };
    return (
        <div className="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8">
            <div className="card my-4 w-full max-w-2xl">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h3 className="text-base font-semibold text-slate-800">Serial Raw Material</h3>
                    <button onClick={onClose} className="rounded p-1 text-slate-400 hover:bg-slate-100"><Icon name="x" /></button>
                </div>
                <div className="px-5 py-3">
                    <div className="mb-3 flex items-center gap-2">
                        <div className="relative flex-1"><Icon name="search" className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input autoFocus className="field-input pl-9" placeholder="Cari serial…" value={q} onChange={(e) => setQ(e.target.value)} /></div>
                        <button type="button" className="btn btn-primary whitespace-nowrap" onClick={autoPick} title="Pilih otomatis sampai kebutuhan terpenuhi"><Icon name="check" className="h-4 w-4" /> Auto Pick</button>
                    </div>
                    <div className="mb-2 flex items-center justify-between rounded-md bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                        <span>Butuh <b>{needPcs}</b> pcs · sudah <b>{bookedPcs}</b> · <b className={shortage > 0 ? 'text-amber-600' : 'text-emerald-600'}>kurang {shortage}</b></span>
                        <span>Terpilih: <b>{sel.length}</b> serial = <b>{selPcs}</b> pcs</span>
                    </div>
                    <div className="max-h-[50vh] overflow-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-3 py-2"><input type="checkbox" checked={rows.length > 0 && sel.length === rows.length} onChange={(e) => setChecked(e.target.checked ? Object.fromEntries(rows.map((r) => [r.serial_id, true])) : {})} /></th>
                                <th className="px-3 py-2">No</th><th className="px-3 py-2">Serial</th><th className="px-3 py-2">Millsheet</th><th className="px-3 py-2">Length</th><th className="px-3 py-2">Pcs</th>
                            </tr></thead>
                            <tbody>
                                {avail.isLoading && <tr><td colSpan={6} className="px-3 py-5 text-center text-slate-400">Memuat…</td></tr>}
                                {!avail.isLoading && rows.length === 0 && <tr><td colSpan={6} className="px-3 py-5 text-center text-slate-400">Tidak ada serial tersedia.</td></tr>}
                                {rows.map((r, i) => (
                                    <tr key={r.serial_id} className={`border-t border-slate-100 ${checked[r.serial_id] ? 'bg-blue-50' : ''}`}>
                                        <td className="px-3 py-2"><input type="checkbox" checked={!!checked[r.serial_id]} onChange={(e) => setChecked((c) => ({ ...c, [r.serial_id]: e.target.checked }))} /></td>
                                        <td className="px-3 py-2">{i + 1}</td>
                                        <td className="px-3 py-2 font-medium text-slate-700">{r.serial_id}</td>
                                        <td className="px-3 py-2 text-slate-500">{r.millsheet || '—'}</td>
                                        <td className="px-3 py-2">{money(r.length)}</td>
                                        <td className="px-3 py-2 text-slate-500">{pcsOf(r)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button className="btn btn-primary" disabled={sel.length === 0} onClick={() => { onAdd(sel); setChecked({}); setQ(''); onClose(); }}>Add to Table ({sel.length})</button>
                    <button className="btn btn-ghost" onClick={onClose}>Close</button>
                </div>
            </div>
        </div>
    );
}

/* ---------- Serial Assy / PM (qty-based) ---------- */
function SerialPmModal({ open, onClose, itemId, existing, onAdd, needQty = 0, bookedQty = 0 }) {
    const [q, setQ] = useState('');
    const [checked, setChecked] = useState({});
    const avail = useQuery({
        queryKey: ['wo-pm-serials', itemId],
        queryFn: async () => (await api.get('/work-orders/available-serials', { params: { item_id: itemId } })).data.data,
        enabled: open && !!itemId,
    });
    if (!open) return null;
    const term = q.trim().toLowerCase();
    const rows = (avail.data || []).filter((s) => !existing.includes(s.serial_id) && (!term || s.serial_id.toLowerCase().includes(term)));
    const sel = rows.filter((r) => checked[r.serial_id]);
    const shortage = Math.max(0, (Number(needQty) || 0) - (Number(bookedQty) || 0));
    const selQty = sel.reduce((a, r) => a + (Number(r.qty) || 0), 0);
    const autoPick = () => { let acc = 0; const next = {}; for (const r of rows) { if (acc >= shortage) break; const qy = Number(r.qty) || 0; if (qy <= 0) continue; next[r.serial_id] = true; acc += qy; } setChecked(next); };
    return (
        <div className="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8">
            <div className="card my-4 w-full max-w-2xl">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h3 className="text-base font-semibold text-slate-800">Serial Assy / PM</h3>
                    <button onClick={onClose} className="rounded p-1 text-slate-400 hover:bg-slate-100"><Icon name="x" /></button>
                </div>
                <div className="px-5 py-3">
                    <div className="mb-3 flex items-center gap-2">
                        <div className="relative flex-1"><Icon name="search" className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input autoFocus className="field-input pl-9" placeholder="Cari serial…" value={q} onChange={(e) => setQ(e.target.value)} /></div>
                        <button type="button" className="btn btn-primary whitespace-nowrap" onClick={autoPick}><Icon name="check" className="h-4 w-4" /> Auto Pick</button>
                    </div>
                    <div className="mb-2 flex items-center justify-between rounded-md bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                        <span>Butuh <b>{needQty}</b> · sudah <b>{bookedQty}</b> · <b className={shortage > 0 ? 'text-amber-600' : 'text-emerald-600'}>kurang {shortage}</b></span>
                        <span>Terpilih: <b>{sel.length}</b> serial = <b>{selQty}</b> qty</span>
                    </div>
                    <div className="max-h-[50vh] overflow-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-3 py-2"><input type="checkbox" checked={rows.length > 0 && sel.length === rows.length} onChange={(e) => setChecked(e.target.checked ? Object.fromEntries(rows.map((r) => [r.serial_id, true])) : {})} /></th>
                                <th className="px-3 py-2">No</th><th className="px-3 py-2">Serial ID</th><th className="px-3 py-2">Millsheet</th><th className="px-3 py-2">Qty (box/lot)</th>
                            </tr></thead>
                            <tbody>
                                {avail.isLoading && <tr><td colSpan={5} className="px-3 py-5 text-center text-slate-400">Memuat…</td></tr>}
                                {!avail.isLoading && rows.length === 0 && <tr><td colSpan={5} className="px-3 py-5 text-center text-slate-400">Belum ada stok serial PM.</td></tr>}
                                {rows.map((r, i) => (
                                    <tr key={r.serial_id} className={`border-t border-slate-100 ${checked[r.serial_id] ? 'bg-blue-50' : ''}`}>
                                        <td className="px-3 py-2"><input type="checkbox" checked={!!checked[r.serial_id]} onChange={(e) => setChecked((c) => ({ ...c, [r.serial_id]: e.target.checked }))} /></td>
                                        <td className="px-3 py-2">{i + 1}</td>
                                        <td className="px-3 py-2 font-medium text-slate-700">{r.serial_id}</td>
                                        <td className="px-3 py-2 text-slate-500">{r.millsheet || '—'}</td>
                                        <td className="px-3 py-2">{r.qty}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button className="btn btn-primary" disabled={sel.length === 0} onClick={() => { onAdd(sel); setChecked({}); setQ(''); onClose(); }}>Add to Table ({sel.length})</button>
                    <button className="btn btn-ghost" onClick={onClose}>Close</button>
                </div>
            </div>
        </div>
    );
}

export default function WoPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null); // {mode:'create'|'edit', id?}
    const [form, setForm] = useState(null);
    const [fgInfo, setFgInfo] = useState(null);
    const [rmRows, setRmRows] = useState([]);
    const [pmRows, setPmRows] = useState([]);
    const [detail, setDetail] = useState(null);
    const [bomRm, setBomRm] = useState(false);
    const [bomPm, setBomPm] = useState(false);
    const [serialFor, setSerialFor] = useState(null); // row index (rm)
    const [serialPmFor, setSerialPmFor] = useState(null); // row index (pm)
    const [error, setError] = useState('');

    const contacts = useOptions('contacts');
    const items = useOptions('items');
    const itemById = Object.fromEntries((items.data || []).map((it) => [it.id, it]));
    const openMps = useQuery({
        queryKey: ['mps', 'open-for-wo'],
        queryFn: async () => (await api.get('/mps', { params: { status: 'APPROVED', per_page: 300 } })).data.data,
        staleTime: 15_000,
    });

    const list = useQuery({
        queryKey: ['work-orders', { page }],
        queryFn: async () => (await api.get('/work-orders', { params: { page, per_page: 15 } })).data,
    });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['work-orders'] });

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/work-orders/${modal.id}`, payload) : api.post('/work-orders', payload)),
        onSuccess: (res) => { invalidate(); setModal(null); setDetail(res.data.data); },
        onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({ mutationFn: async ({ id, action }) => api.post(`/work-orders/${id}/${action}`), onSuccess: (res) => { invalidate(); setDetail(res.data.data); }, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/work-orders/${id}`), onSuccess: () => { invalidate(); setDetail(null); }, onError: (e) => alert(apiError(e)) });

    const loadFgInfo = async (fgId, qty) => {
        if (!fgId) { setFgInfo(null); return null; }
        const { data } = await api.get(`/work-orders/fg-info/${fgId}`, { params: { qty: qty || 1 } });
        setFgInfo(data.data);
        // default to the highest-priority routing when none chosen yet
        setForm((f) => (f && !f.process_main_id && data.data.routings?.length ? { ...f, process_main_id: data.data.routings[0].process_main_id } : f));
        return data.data;
    };
    const openCreate = () => { setForm({ date: today(), customer_id: '', so_id: '', qty: 1, no_cut: false, for_pm: false, fg_id: '', mps_id: '', process_main_id: '' }); setFgInfo(null); setRmRows([]); setPmRows([]); setError(''); setModal({ mode: 'create' }); };
    const onSelectMps = (mpsId) => {
        const m = (openMps.data || []).find((x) => x.id === Number(mpsId));
        if (!m) { setForm((f) => ({ ...f, mps_id: '', fg_id: '' })); setFgInfo(null); return; }
        const qty = m.wo_remaining > 0 ? m.wo_remaining : m.qty;
        setForm((f) => ({ ...f, mps_id: mpsId, fg_id: m.item_id, qty, process_main_id: '' }));
        setRmRows([]); setPmRows([]); loadFgInfo(m.item_id, qty);
    };
    const openDetail = async (row) => { const { data } = await api.get(`/work-orders/${row.id}`); setDetail(data.data); };
    const openEdit = async (wo) => {
        setError('');
        setForm({ date: wo.date?.slice(0, 10), customer_id: wo.customer_id, so_id: wo.so_id === '-' ? '' : wo.so_id, qty: wo.qty, no_cut: !!wo.no_cut, for_pm: !!wo.for_pm, fg_id: wo.fg_id, mps_id: wo.mps_id, process_main_id: wo.process_main_id || '' });
        const info = await loadFgInfo(wo.fg_id, wo.qty);
        const minUseByMat = Object.fromEntries((info?.bom_rm || []).map((b) => [b.rm_id, b.min_use_all ?? b.length_use]));
        const mk = (d, s) => ({ key: uid(), rm_id: d.rm_id, code: d.rm?.code, spec: d.rm ? `OD:${d.rm.o_d ?? ''}` : '', length_use: d.length_use, min_use_all: minUseByMat[d.rm_id] ?? d.length_use, serial_id: s?.serial_id || '', length_serial: s?.length_asal ?? '', length_book: s?.length_book ?? '', qty: s?.qty ?? '', length_rem: s?.length_rem ?? '', scrap: !!s?.scrap, note: s?.note || '' });
        setRmRows((wo.detail_rm || []).flatMap((d) => (d.serials || []).length ? d.serials.map((s) => mk(d, s)) : [mk(d, null)]));
        const mkPm = (d, s) => ({ key: uid(), pm_id: d.pm_id, code: d.pm?.code, spec: '', per_fg: d.per_fg, serial_id: s?.serial_id || '', qty: s?.qty ?? '', note: s?.note || d.note || '' });
        setPmRows((wo.detail_pm || []).flatMap((d) => (d.serials || []).length ? d.serials.map((s) => mkPm(d, s)) : [mkPm(d, null)]));
        setModal({ mode: 'edit', id: wo.id });
    };

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    /* RM row ops */
    // Leftover is scrap when it's smaller than the smallest cut any BOM needs.
    const isScrap = (rem, minUseAll) => { const r = Number(rem) || 0; const m = Number(minUseAll) || 0; return r > 0 && m > 0 && r < m; };
    const addRmMaterial = (m) => setRmRows((r) => [...r, { key: uid(), rm_id: m.rm_id, code: m.code, spec: m.spec, length_use: m.length_use, length_cut: m.length_cut, min_use_all: m.min_use_all ?? m.length_use, serial_id: '', length_serial: '', length_book: '', qty: '', length_rem: '', scrap: false, note: '' }]);
    // Distribute the outstanding requirement across picked serials: fill each to
    // capacity, the LAST one only takes the remainder (no over-cut).
    const addRmSerials = (rowIndex, picked) => setRmRows((rows) => {
        const base = rows[rowIndex];
        const lu = Number(base.length_use) || 0;
        const need = Number(form.qty) || 0;
        const already = rows.reduce((a, r, idx) => ((idx !== rowIndex && r.rm_id === base.rm_id && r.serial_id) ? a + (Number(r.qty) || 0) : a), 0);
        let remaining = Math.max(0, need - already);
        const built = picked.map((s) => {
            const ls = Number(s.length) || 0;
            const cap = form.no_cut ? 1 : (lu > 0 ? Math.floor(ls / lu) : 0);
            const take = Math.max(0, Math.min(cap, remaining));
            remaining -= take;
            const book = form.no_cut ? ls : Math.round(take * lu * 100) / 100;
            const rem = Math.round((ls - book) * 100) / 100;
            return { ...base, key: uid(), serial_id: s.serial_id, millsheet: s.millsheet, length_serial: ls, qty: take, length_book: book, length_rem: rem, scrap: isScrap(rem, base.min_use_all) };
        });
        const next = [...rows];
        next.splice(rowIndex, 1, ...built);
        return next;
    });
    const setRmField = (i, k, v) => setRmRows((rows) => rows.map((r, j) => {
        if (j !== i) return r;
        const row = { ...r, [k]: v };
        const lu = Number(row.length_use) || 0;
        const ls = Number(row.length_serial) || 0;
        if (k === 'qty' && lu > 0) { const book = Math.round(Number(v || 0) * lu * 100) / 100; row.length_book = book; row.length_rem = Math.round((ls - book) * 100) / 100; }
        if (k === 'length_book') { const book = Number(v || 0); row.qty = lu > 0 ? Math.floor(book / lu) : 0; row.length_rem = Math.round((ls - book) * 100) / 100; }
        // Re-evaluate scrap whenever the leftover changes (manual toggle still wins after).
        if (k === 'qty' || k === 'length_book') row.scrap = isScrap(row.length_rem, row.min_use_all);
        return row;
    }));
    const delRm = (i) => setRmRows((rows) => rows.filter((_, j) => j !== i));

    /* PM row ops (per serial, qty-based) */
    const addPmMaterial = (m) => setPmRows((r) => [...r, { key: uid(), pm_id: m.pm_id, code: m.code, spec: m.spec, per_fg: m.per_fg ?? 1, serial_id: '', qty: '', note: '' }]);
    const addPmSerials = (rowIndex, picked) => setPmRows((rows) => {
        const base = rows[rowIndex];
        const need = (Number(form.qty) || 0) * (Number(base.per_fg) || 0);
        const already = rows.reduce((a, r, idx) => ((idx !== rowIndex && r.pm_id === base.pm_id && r.serial_id) ? a + (Number(r.qty) || 0) : a), 0);
        let remaining = Math.max(0, need - already);
        const built = picked.map((s) => { const cap = Number(s.qty) || 0; const take = Math.max(0, Math.min(cap, remaining)); remaining -= take; return { ...base, key: uid(), serial_id: s.serial_id, qty: take, note: base.note || '' }; });
        const next = [...rows];
        next.splice(rowIndex, 1, ...built);
        return next;
    });
    const setPmField = (i, k, v) => setPmRows((rows) => rows.map((r, j) => (j === i ? { ...r, [k]: v } : r)));
    const delPm = (i) => setPmRows((rows) => rows.filter((_, j) => j !== i));
    const usedPmSerials = pmRows.filter((r) => r.serial_id).map((r) => r.serial_id);

    const usedSerials = rmRows.filter((r) => r.serial_id).map((r) => r.serial_id);

    const submit = () => {
        setError('');
        const rmByMat = {};
        rmRows.forEach((r) => { (rmByMat[r.rm_id] ||= { rm_id: r.rm_id, serials: [] }); if (r.serial_id) rmByMat[r.rm_id].serials.push({ serial_id: r.serial_id, length_serial: num(r.length_serial), length_book: num(r.length_book), qty: num(r.qty) || 0, length_rem: num(r.length_rem), scrap: r.scrap ? 1 : 0, note: r.note || null }); });
        const pmByMat = {};
        pmRows.forEach((r) => { (pmByMat[r.pm_id] ||= { pm_id: r.pm_id, serials: [] }); if (r.serial_id) pmByMat[r.pm_id].serials.push({ serial_id: r.serial_id, qty: num(r.qty) || 0, note: r.note || null }); });
        save.mutate({ ...form, no_cut: form.no_cut ? 1 : 0, for_pm: form.for_pm ? 1 : 0, rm_lines: Object.values(rmByMat), pm_lines: Object.values(pmByMat) });
    };

    const columns = [
        { key: 'code', label: 'No. WO' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'fg', label: 'FG', render: (v) => (v ? `${v.code} — ${v.part_name}` : '—') },
        { key: 'qty', label: 'Qty', render: (v) => money(v) },
        { key: 'customer', label: 'Customer', render: (v) => v?.company_n || '—' },
        { key: 'status', label: 'Status', render: (_, row) => <WoStatus wo={row} /> },
    ];

    const SpecBox = ({ label, value }) => (
        <div className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-center">
            <div className="text-[11px] uppercase text-slate-400">{label}</div>
            <div className="text-sm font-semibold text-slate-700">{value ?? 0}</div>
        </div>
    );

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Work Order</h1>
                {can('work-orders', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Buat WO</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Detail" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openDetail(row)}><Icon name="search" /></button>
                        {row.status === 1 && can('work-orders', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                    </div>
                )} />

            {/* Create / Edit workspace */}
            <Modal open={!!modal} onClose={() => setModal(null)} size="max-w-[110rem]" title={`${modal?.mode === 'edit' ? 'Edit' : 'Create'} Work Order`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Cancel</button>
                    <button className="btn btn-primary" onClick={submit} disabled={save.isPending || !form?.fg_id}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Save</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {form && (<>
                    {/* Header */}
                    <div className="mb-3 rounded-md bg-indigo-50/60 p-3">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                            <div><label className="field-label">Lot Number</label><input className="field-input bg-white/70" value="(otomatis)" disabled /></div>
                            <div className="lg:col-span-2"><label className="field-label">Customer <span className="text-red-500">*</span></label>
                                <Select value={form.customer_id} onChange={(v) => set('customer_id', v)} options={contacts.data} getValue={(o) => o.id} getLabel={(o) => o.company_n} placeholder="— pilih —" /></div>
                            <div><label className="field-label">Date <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                            <div><label className="field-label">SO Number</label><input className="field-input" value={form.so_id} onChange={(e) => set('so_id', e.target.value)} /></div>
                            <div><label className="field-label">Qty <span className="text-red-500">*</span></label><input type="number" className="field-input" value={form.qty} onChange={(e) => { set('qty', e.target.value); if (fgInfo) loadFgInfo(form.fg_id, e.target.value); }} /></div>
                            <div className="flex items-end gap-3">
                                <label className="inline-flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" checked={form.no_cut} onChange={(e) => set('no_cut', e.target.checked)} /> Without Cut</label>
                                <label className="inline-flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" checked={form.for_pm} onChange={(e) => set('for_pm', e.target.checked)} /> For PM</label>
                            </div>
                            <div className="lg:col-span-2"><label className="field-label">MPS (jadwal) <span className="text-red-500">*</span></label>
                                {modal?.mode === 'edit' ? (
                                    <input className="field-input bg-slate-50" value={form.fg_id && itemById[form.fg_id] ? `${itemById[form.fg_id].code} — ${itemById[form.fg_id].part_name}` : ''} disabled />
                                ) : (
                                    <Select value={form.mps_id} onChange={onSelectMps} options={openMps.data} getValue={(o) => o.id} getLabel={(o) => `${o.plan_date?.slice(0, 10)} · ${o.item?.code} (sisa ${o.wo_remaining}/${o.qty})`} placeholder="— pilih MPS approved —" />
                                )}
                                {form.fg_id && <p className="mt-1 truncate text-xs text-slate-500">FG: {itemById[form.fg_id] ? `${itemById[form.fg_id].code} — ${itemById[form.fg_id].part_name}` : `#${form.fg_id}`}</p>}
                            </div>
                            <div className="lg:col-span-2">
                                <label className="field-label">Routing <span className="text-red-500">*</span></label>
                                <select className="field-input" value={form.process_main_id || ''} onChange={(e) => set('process_main_id', e.target.value ? Number(e.target.value) : '')} disabled={!fgInfo?.routings?.length}>
                                    <option value="">{fgInfo?.routings?.length ? '— pilih routing —' : '— pilih MPS dulu —'}</option>
                                    {(fgInfo?.routings || []).map((r) => <option key={r.process_main_id} value={r.process_main_id}>{`#${r.priority} · ${r.code} — ${r.name}`}</option>)}
                                </select>
                                {fgInfo && !fgInfo.routings?.length && <p className="mt-1 text-xs text-amber-600">FG ini belum punya routing — atur di Item Master tab Proses.</p>}
                            </div>
                        </div>
                        {fgInfo && (
                            <div className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-8">
                                <SpecBox label="OD" value={fgInfo.fg.o_d} /><SpecBox label="ID" value={fgInfo.fg.i_d} /><SpecBox label="Thick" value={fgInfo.fg.thick} /><SpecBox label="Width" value={fgInfo.fg.width} />
                                <SpecBox label="Height" value={fgInfo.fg.height} /><SpecBox label="Length" value={fgInfo.fg.length} /><SpecBox label="Length Cut" value={fgInfo.length_cut} /><SpecBox label="Length Req" value={fgInfo.length_req} />
                            </div>
                        )}
                        {fgInfo && !fgInfo.has_bom && <p className="mt-2 text-xs text-amber-600">FG ini belum punya BOM — lengkapi di Item Master.</p>}
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-[2fr_1fr]">
                        {/* Raw Material */}
                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <h4 className="text-sm font-semibold text-slate-700">Raw Material</h4>
                                <button type="button" className="btn btn-ghost px-2 py-1 text-xs" disabled={!fgInfo?.bom_rm?.length} onClick={() => setBomRm(true)}><Icon name="plus" className="h-3.5 w-3.5" /> Material</button>
                            </div>
                            <div className="overflow-x-auto rounded-md border border-slate-200">
                                <table className="w-full text-sm">
                                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                        {['Material', 'Spec', 'Serial', 'Len Serial', 'Len Book', 'Qty', 'Len Remaining', 'Notes', 'Scrap', ''].map((h, i) => <th key={i} className="whitespace-nowrap px-2 py-2">{h}</th>)}
                                    </tr></thead>
                                    <tbody>
                                        {rmRows.length === 0 && <tr><td colSpan={10} className="px-2 py-4 text-center text-slate-400">Klik "+ Material" lalu "Pick" serial.</td></tr>}
                                        {rmRows.map((r, i) => (
                                            <tr key={r.key} className="border-t border-slate-100">
                                                <td className="whitespace-nowrap px-2 py-1 font-medium text-slate-700">{r.code}</td>
                                                <td className="whitespace-nowrap px-2 py-1 text-slate-500">{r.spec}</td>
                                                <td className="px-2 py-1">
                                                    <div className="flex items-center gap-1">
                                                        <input className="field-input w-40" value={r.serial_id} readOnly placeholder="—" />
                                                        <button type="button" className="rounded bg-cyan-500 px-2 py-1 text-xs text-white hover:bg-cyan-600" onClick={() => setSerialFor(i)}>Pick</button>
                                                    </div>
                                                </td>
                                                <td className="px-2 py-1 text-right text-slate-600">{money(r.length_serial)}</td>
                                                <td className="px-2 py-1"><input type="number" className="field-input w-20" value={r.length_book} onChange={(e) => setRmField(i, 'length_book', e.target.value)} /></td>
                                                <td className="px-2 py-1"><input type="number" className="field-input w-16" value={r.qty} onChange={(e) => setRmField(i, 'qty', e.target.value)} /></td>
                                                <td className="px-2 py-1 text-right text-slate-600">{money(r.length_rem)}</td>
                                                <td className="px-2 py-1"><input className="field-input w-28" value={r.note} onChange={(e) => setRmField(i, 'note', e.target.value)} /></td>
                                                <td className="px-2 py-1 text-center"><input type="checkbox" checked={r.scrap} onChange={(e) => setRmField(i, 'scrap', e.target.checked)} /></td>
                                                <td className="px-2 py-1"><button type="button" className="rounded bg-red-500 px-2 py-1 text-xs text-white hover:bg-red-600" onClick={() => delRm(i)}>Delete</button></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Assy / PM */}
                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <h4 className="text-sm font-semibold text-slate-700">Assy Part Material</h4>
                                <button type="button" className="btn btn-ghost px-2 py-1 text-xs" disabled={!fgInfo?.bom_pm?.length} onClick={() => setBomPm(true)}><Icon name="plus" className="h-3.5 w-3.5" /> PM</button>
                            </div>
                            <div className="overflow-x-auto rounded-md border border-slate-200">
                                <table className="w-full text-sm">
                                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                        {['Material', 'Spec', 'Serial', 'Qty', 'Need', 'Notes', ''].map((h, i) => <th key={i} className="whitespace-nowrap px-2 py-2">{h}</th>)}
                                    </tr></thead>
                                    <tbody>
                                        {pmRows.length === 0 && <tr><td colSpan={7} className="px-2 py-4 text-center text-slate-400">Klik "+ PM".</td></tr>}
                                        {pmRows.map((r, i) => (
                                            <tr key={r.key} className="border-t border-slate-100">
                                                <td className="whitespace-nowrap px-2 py-1 font-medium text-slate-700">{r.code}</td>
                                                <td className="whitespace-nowrap px-2 py-1 text-slate-500">{r.spec}</td>
                                                <td className="px-2 py-1">
                                                    <div className="flex items-center gap-1">
                                                        <input className="field-input w-32" value={r.serial_id} readOnly placeholder="—" />
                                                        <button type="button" className="rounded bg-cyan-500 px-2 py-1 text-xs text-white hover:bg-cyan-600" onClick={() => setSerialPmFor(i)}>Pick</button>
                                                    </div>
                                                </td>
                                                <td className="px-2 py-1"><input type="number" className="field-input w-16" value={r.qty} onChange={(e) => setPmField(i, 'qty', e.target.value)} /></td>
                                                <td className="px-2 py-1 text-right text-slate-500">{money((Number(form.qty) || 0) * (Number(r.per_fg) || 0))}</td>
                                                <td className="px-2 py-1"><input className="field-input w-20" value={r.note} onChange={(e) => setPmField(i, 'note', e.target.value)} /></td>
                                                <td className="px-2 py-1"><button type="button" className="rounded bg-red-500 px-2 py-1 text-xs text-white hover:bg-red-600" onClick={() => delPm(i)}>Delete</button></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </>)}
            </Modal>

            {/* pickers */}
            <ChooseBomModal open={bomRm} onClose={() => setBomRm(false)} title="Choose Raw Material (BOM)" rows={fgInfo?.bom_rm} onPick={addRmMaterial}
                columns={[{ key: 'code', label: 'Material' }, { key: 'spec', label: 'Spec' }, { key: 'length_use', label: 'Length Use' }, { key: 'priority', label: 'Priority' }, { key: 'min_length', label: 'Min Length' }]} />
            <ChooseBomModal open={bomPm} onClose={() => setBomPm(false)} title="Choose Assy / PM (BOM)" rows={fgInfo?.bom_pm} onPick={addPmMaterial}
                columns={[{ key: 'code', label: 'Material' }, { key: 'spec', label: 'Spec' }, { key: 'need', label: 'Need' }]} />
            <SerialRmModal open={serialFor != null} onClose={() => setSerialFor(null)} itemId={serialFor != null ? rmRows[serialFor]?.rm_id : null} excludeWo={modal?.id} existing={usedSerials}
                needPcs={Number(form?.qty) || 0} noCut={!!form?.no_cut}
                lengthUse={serialFor != null ? (rmRows[serialFor]?.length_use || 0) : 0}
                bookedPcs={serialFor != null ? rmRows.filter((r, idx) => idx !== serialFor && r.rm_id === rmRows[serialFor]?.rm_id && r.serial_id).reduce((a, r) => a + (Number(r.qty) || 0), 0) : 0}
                onAdd={(picked) => addRmSerials(serialFor, picked)} />
            <SerialPmModal open={serialPmFor != null} onClose={() => setSerialPmFor(null)} itemId={serialPmFor != null ? pmRows[serialPmFor]?.pm_id : null} existing={usedPmSerials}
                needQty={serialPmFor != null ? (Number(form?.qty) || 0) * (Number(pmRows[serialPmFor]?.per_fg) || 0) : 0}
                bookedQty={serialPmFor != null ? pmRows.filter((r, idx) => idx !== serialPmFor && r.pm_id === pmRows[serialPmFor]?.pm_id && r.serial_id).reduce((a, r) => a + (Number(r.qty) || 0), 0) : 0}
                onAdd={(picked) => addPmSerials(serialPmFor, picked)} />

            {/* Detail (read-only) */}
            <Modal open={!!detail} onClose={() => setDetail(null)} size="max-w-[95rem]" title={`Work Order ${detail?.code || ''}`}
                footer={<>
                    {detail?.status === 1 && can('work-orders', 'edit') && <button className="btn btn-ghost" onClick={() => { setDetail(null); openEdit(detail); }}><Icon name="pencil" /> Edit</button>}
                    {detail?.status === 1 && can('work-orders', 'delete') && <button className="btn btn-ghost text-red-600" onClick={() => window.confirm('Hapus WO?') && remove.mutate(detail.id)}><Icon name="trash" /> Hapus</button>}
                    {detail?.status === 1 && can('work-orders', 'edit') && <button className="btn btn-primary" onClick={() => act.mutate({ id: detail.id, action: 'release' })}><Icon name="send" /> Release</button>}
                    {detail?.status === 2 && can('work-orders', 'edit') && <button className="btn btn-primary" onClick={() => act.mutate({ id: detail.id, action: 'close' })}><Icon name="lock" /> Close</button>}
                    {[1, 2].includes(detail?.status) && can('work-orders', 'edit') && <button className="btn btn-ghost text-red-600" onClick={() => window.confirm('Batalkan WO?') && act.mutate({ id: detail.id, action: 'cancel' })}><Icon name="ban" /> Cancel</button>}
                    <button className="btn btn-ghost" onClick={() => setDetail(null)}>Tutup</button>
                </>}>
                {detail && (<>
                    <div className="mb-4 grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                        <div><span className="text-slate-400">FG</span><div className="font-medium">{detail.fg?.code} — {detail.fg?.part_name}</div></div>
                        <div><span className="text-slate-400">Qty</span><div className="font-medium">{money(detail.qty)}</div></div>
                        <div><span className="text-slate-400">Customer</span><div className="font-medium">{detail.customer?.company_n || '—'}</div></div>
                        <div><span className="text-slate-400">SO</span><div className="font-medium">{detail.so_id}</div></div>
                        <div><span className="text-slate-400">Status</span><div><WoStatus wo={detail} /></div></div>
                    </div>
                    <h4 className="mb-2 text-sm font-semibold text-slate-700">Raw Material (booking)</h4>
                    <div className="mb-5 overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">{['RM', 'Serial', 'Len Serial', 'Len Book', 'Qty', 'Len Rem', 'Scrap'].map((h, i) => <th key={i} className="px-3 py-2">{h}</th>)}</tr></thead>
                            <tbody>
                                {(detail.detail_rm || []).flatMap((d) => (d.serials || []).length ? d.serials.map((s) => (
                                    <tr key={s.id} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5">{d.rm?.code}</td><td className="px-3 py-1.5 font-medium text-slate-700">{s.serial_id}</td>
                                        <td className="px-3 py-1.5 text-right">{money(s.length_asal)}</td><td className="px-3 py-1.5 text-right">{money(s.length_book)}</td>
                                        <td className="px-3 py-1.5 text-right">{s.qty}</td><td className="px-3 py-1.5 text-right">{money(s.length_rem)}</td><td className="px-3 py-1.5">{s.scrap ? '✓' : ''}</td>
                                    </tr>
                                )) : [<tr key={`e${d.id}`} className="border-t border-slate-100"><td className="px-3 py-1.5">{d.rm?.code}</td><td colSpan={6} className="px-3 py-1.5 text-slate-400">belum ada serial (butuh {d.req_qty} pcs)</td></tr>])}
                            </tbody>
                        </table>
                    </div>
                    <h4 className="mb-2 text-sm font-semibold text-slate-700">Assy / PM</h4>
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">{['PM', 'Per FG', 'Butuh Qty', 'Ter-booking', 'Serial'].map((h, i) => <th key={i} className="px-3 py-2">{h}</th>)}</tr></thead>
                            <tbody>
                                {(detail.detail_pm || []).map((d) => <tr key={d.id} className="border-t border-slate-100 align-top"><td className="px-3 py-1.5">{d.pm?.code}</td><td className="px-3 py-1.5 text-right">{d.per_fg}</td><td className="px-3 py-1.5 text-right">{money(d.req_qty)}</td><td className="px-3 py-1.5 text-right text-emerald-700">{money(d.booked_qty)}</td><td className="px-3 py-1.5"><div className="flex flex-wrap gap-1">{(d.serials || []).map((s) => <span key={s.id} className="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-xs text-slate-600">{s.serial_id} ({s.qty})</span>)}{(d.serials || []).length === 0 && <span className="text-xs text-slate-400">—</span>}</div></td></tr>)}
                                {(detail.detail_pm || []).length === 0 && <tr><td colSpan={5} className="px-3 py-3 text-center text-slate-400">—</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    {detail.status === 2 && <p className="mt-3 text-xs text-slate-500">Released — issue RM lewat <b>Outgoing RM</b> (pilih WO ini; hanya serial ter-booking yang boleh keluar).</p>}
                </>)}
            </Modal>
        </div>
    );
}
