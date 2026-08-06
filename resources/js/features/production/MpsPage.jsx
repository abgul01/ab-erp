import { useState, useRef, useLayoutEffect, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, useOptions, CellInput, money } from '../procurement/common';

const now = new Date();
const thisMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
const DOW = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

/**
 * MPS = machine-loading board. Rows are MACHINES, columns are days, and each
 * cell holds the lots (item + qty) that machine will run that day. Generate
 * packs the approved MPP quantities into daily lots up to each machine's
 * capacity (8h × 2 shifts = 16h/day).
 */
export default function MpsPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [month, setMonth] = useState(thisMonth); // YYYY-MM
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(null);
    const [drag, setDrag] = useState(null);
    const [error, setError] = useState('');
    const [focus, setFocus] = useState(null); // item_id whose routing flow is highlighted
    const [searchQuery, setSearchQuery] = useState('');
    const [foundIds, setFoundIds] = useState(new Set());
    const wrapRef = useRef(null);
    const [line, setLine] = useState([]);

    const machines = useOptions('machines');
    const procs = useOptions('processes');
    // colour a lot by its OP number (position in the item's routing) — 12 distinct
    // hues (OP1..OP12), repeating afterwards. Avoids emerald (approved ring) & yellow (pending).
    const OP_COLORS = ['bg-red-500', 'bg-orange-500', 'bg-amber-500', 'bg-lime-600', 'bg-teal-500', 'bg-cyan-600', 'bg-sky-500', 'bg-blue-600', 'bg-indigo-500', 'bg-violet-500', 'bg-fuchsia-500', 'bg-pink-500'];
    const opColor = (seq) => (seq ? OP_COLORS[(seq - 1) % OP_COLORS.length] : 'bg-slate-400');
    const approvedMpp = useQuery({ queryKey: ['mpp', 'approved'], queryFn: async () => (await api.get('/mpp', { params: { status: 'APPROVED', per_page: 300 } })).data.data, staleTime: 20_000 });
    const list = useQuery({ queryKey: ['mps', month], queryFn: async () => (await api.get('/mps', { params: { month, per_page: 2000 } })).data.data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['mps'] });

    const [y, mo] = month.split('-').map(Number);
    const daysInMonth = new Date(y, mo, 0).getDate();
    const days = Array.from({ length: daysInMonth }, (_, i) => i + 1);
    const dateOf = (d) => `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

    const inMonth = (list.data || []).filter((m) => (m.plan_date || '').slice(0, 7) === month);
    // group by machine → day
    const rows = {};
    inMonth.forEach((m) => {
        const key = m.machine_id ?? 'none';
        rows[key] ||= { key, machine: m.machine, machine_id: m.machine_id ?? null, byDay: {} };
        (rows[key].byDay[+m.plan_date.slice(8, 10)] ||= []).push(m);
    });
    const rowList = Object.values(rows).sort((a, b) => (a.machine?.code || '~~~').localeCompare(b.machine?.code || '~~~'));

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/mps/${modal.id}`, p) : api.post('/mps', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({ mutationFn: async (id) => api.post(`/mps/${id}/approve`), onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/mps/${id}`), onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => alert(apiError(e)) });
    const reschedule = useMutation({
        mutationFn: async ({ m, date, machineId }) => api.put(`/mps/${m.id}`, { plan_date: date, item_id: m.item_id, qty: m.qty, machine_id: machineId ?? m.machine_id ?? null }),
        onSuccess: invalidate, onError: (e) => alert(apiError(e)),
    });
    const generate = useMutation({
        mutationFn: async () => (await api.post('/mps/generate', { period: month.replace('-', '') })).data.data,
        onSuccess: (r) => { invalidate(); alert(`Generate MPS ${month}: ${r.created} lot untuk ${r.items} item${r.short_capacity ? ` — ${r.short_capacity} item melebihi kapasitas mesin bulan ini` : ''}.`); },
        onError: (e) => alert(apiError(e)),
    });
    // Locked (APPROVED) lots can't be moved directly — a planner requests a
    // reschedule that an approver confirms on the "Persetujuan Jadwal" page.
    const [reqModal, setReqModal] = useState(null);
    const requestResched = useMutation({
        mutationFn: async (p) => api.post('/mps-approvals', p),
        onSuccess: () => { invalidate(); setReqModal(null); alert('Permintaan reschedule terkirim — menunggu persetujuan.'); },
        onError: (e) => { alert(apiError(e)); },
    });
    const openReq = (m, toDate, toMachine) => setReqModal({ mps_id: m.id, item: m.item, from_date: (m.plan_date || '').slice(0, 10), from_machine: m.machine?.code || '—', to_date: toDate, to_machine_id: toMachine || m.machine_id || '', reason: '' });

    const openCreate = (date = '', machineId = '') => { setForm({ mpp_id: '', plan_date: date, item_id: '', item_label: '', proc_id: '', qty: '', machine_id: machineId || '' }); setError(''); setModal({ mode: 'create' }); };
    const openEdit = (m) => { setForm({ mpp_id: '', plan_date: m.plan_date?.slice(0, 10), item_id: m.item_id, item_label: m.item ? `${m.item.code} — ${m.item.part_name}` : '', proc_id: m.proc_id || '', qty: m.qty, machine_id: m.machine_id || '', status: m.status }); setError(''); setModal({ mode: 'edit', id: m.id, status: m.status, lot: m }); };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const onSelectMpp = (mppId) => {
        const m = (approvedMpp.data || []).find((x) => x.id === Number(mppId));
        if (!m) { set('mpp_id', ''); return; }
        setForm((f) => ({ ...f, mpp_id: mppId, item_id: m.item_id, item_label: m.item ? `${m.item.code} — ${m.item.part_name}` : '', plan_date: f.plan_date || `${m.period.slice(0, 4)}-${m.period.slice(4, 6)}-01`, qty: f.qty || m.plan_qty }));
    };
    const submit = () => { setError(''); const { mpp_id, item_label, status, ...payload } = form; save.mutate(payload); };

    // Draw the routing "flow" line through all lots of the focused item, in
    // time order (CUT → MCH → CHM …) across machine rows and days.
    useLayoutEffect(() => {
        const wrap = wrapRef.current;
        if (!wrap || !focus) { setLine([]); return; }
        const els = Array.from(wrap.querySelectorAll(`[data-item="${focus}"]`));
        if (els.length < 1) { setLine([]); return; }
        const wr = wrap.getBoundingClientRect();
        const pts = els.map((el) => {
            const r = el.getBoundingClientRect();
            return { x: r.left - wr.left + r.width / 2, y: r.top - wr.top + r.height / 2, date: el.getAttribute('data-date'), proc: +el.getAttribute('data-proc') };
        });
        pts.sort((a, b) => (a.date === b.date ? a.proc - b.proc : (a.date < b.date ? -1 : 1)));
        setLine(pts);
    }, [focus, list.data, month, daysInMonth]);

    // Search lots by item code, part name, process code, or lot id
    useEffect(() => {
        const q = searchQuery.trim().toLowerCase();
        if (!q) { setFoundIds(new Set()); return; }
        const matches = inMonth.filter((m) =>
            (m.item?.code && m.item.code.toLowerCase().includes(q)) ||
            (m.item?.part_name && m.item.part_name.toLowerCase().includes(q)) ||
            (m.process?.code && m.process.code.toLowerCase().includes(q)) ||
            String(m.id).includes(q)
        ).map((m) => m.id);
        setFoundIds(new Set(matches));
    }, [searchQuery, list.data, month]);

    // The route as SVG geometry: `points` for the polylines, and the same path
    // in `d` form for the dot that travels along it.
    const pathPoints = line.map((p) => `${p.x},${p.y}`).join(' ');
    const motionPath = line.length > 1
        ? line.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ')
        : '';

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold text-slate-800">MPS — Muat Mesin (Machine Loading)</h1>
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <input type="text" className="field-input w-48 pl-8 text-xs" placeholder="Cari lot…" value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} />
                        <Icon name="search" className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                        {foundIds.size > 0 && <span className="absolute right-2 top-1/2 -translate-y-1/2 text-xs font-semibold text-blue-600">{foundIds.size}</span>}
                    </div>
                    <input type="month" className="field-input w-40" value={month} onChange={(e) => setMonth(e.target.value)} />
                    {can('mps', 'create') && <button className="btn btn-ghost" onClick={() => window.confirm(`Generate MPS ${month} dari MPP approved? Lot DRAFT bulan ini dibuat ulang mengisi kapasitas mesin 16 jam/hari.`) && generate.mutate()} disabled={generate.isPending}>{generate.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="workflow" />} Generate dari MPP</button>}
                    {can('mps', 'create') && <button className="btn btn-primary" onClick={() => openCreate()}><Icon name="plus" /> Tambah Lot</button>}
                </div>
            </div>
            <p className="mb-3 text-xs text-slate-400">Baris = mesin, sel = lot (proses · item · qty) per hari. Kapasitas 8j × 2 shift = 16 jam/hari. <b>Arahkan kursor</b> ke sebuah lot untuk melihat <span className="text-blue-600">garis alur</span> item itu melintasi mesin &amp; tanggal — <b>panah dan garis putusnya berjalan searah urutan proses</b> (1→2→3…). Klik lot untuk lihat/edit; lot DRAFT bisa di-<b>drag</b> bebas; lot <span className="text-emerald-600">APPROVED</span> yang di-drag akan <b>mengajukan reschedule</b> (perlu persetujuan). Lot yang <span className="rounded bg-yellow-300 px-1 text-yellow-900">berkedip kuning</span> sedang menunggu persetujuan.</p>

            <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
              <div ref={wrapRef} className="relative" style={{ width: 'max-content' }}>
                <svg className="pointer-events-none absolute inset-0 z-20 h-full w-full">
                    <defs>
                        <marker id="mps-arrow" viewBox="0 0 10 10" refX="9" refY="5"
                            markerWidth="5" markerHeight="5" orient="auto-start-reverse">
                            <path d="M 0 0 L 10 5 L 0 10 z" fill="#2563eb" />
                        </marker>
                    </defs>

                    {line.length > 1 && (
                        <>
                            {/*
                              * Three layers make the direction readable at a glance: a pale
                              * track showing the whole route, an arrowhead on every hop, and
                              * dashes marching from one operation to the next.
                              */}
                            <polyline points={pathPoints} fill="none" stroke="#2563eb" strokeWidth="2"
                                strokeLinejoin="round" strokeLinecap="round" opacity="0.28" />

                            {line.slice(1).map((p, i) => (
                                <line key={`a${i}`} x1={line[i].x} y1={line[i].y} x2={p.x} y2={p.y}
                                    stroke="#2563eb" strokeWidth="2" opacity="0.55" markerEnd="url(#mps-arrow)" />
                            ))}

                            <polyline className="mps-flow-march" points={pathPoints} fill="none"
                                stroke="#1d4ed8" strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" />
                        </>
                    )}

                    {/* The head of the flow, running the route on a loop. */}
                    {line.length > 1 && (
                        <circle className="mps-flow-runner" r="4" fill="#1d4ed8" stroke="#fff" strokeWidth="1.5">
                            <animateMotion dur={`${Math.max(2.5, line.length * 0.7)}s`} repeatCount="indefinite"
                                path={motionPath} rotate="auto" />
                        </circle>
                    )}

                    {line.map((p, i) => (
                        <g key={i}>
                            <circle cx={p.x} cy={p.y} r="8" fill="#fff" opacity="0.9" />
                            <circle cx={p.x} cy={p.y} r="7" fill="#2563eb" />
                            <text x={p.x} y={p.y + 3} textAnchor="middle" fontSize="9" fill="#fff" fontWeight="700">{i + 1}</text>
                        </g>
                    ))}
                </svg>
                <table className="border-collapse text-sm" style={{ minWidth: 220 + daysInMonth * 46 }}>
                    <thead>
                        <tr>
                            <th className="sticky left-0 z-10 w-56 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left text-xs font-semibold text-slate-500">Mesin</th>
                            {days.map((d) => {
                                const dow = new Date(y, mo - 1, d).getDay();
                                return <th key={d} className={`w-[46px] border-b border-slate-100 px-1 py-1 text-center text-[11px] ${dow === 0 || dow === 6 ? 'bg-slate-50 text-slate-400' : 'text-slate-500'}`}><div>{d}</div><div className="text-[9px] text-slate-400">{DOW[dow]}</div></th>;
                            })}
                        </tr>
                    </thead>
                    <tbody>
                        {list.isLoading && <tr><td colSpan={daysInMonth + 1} className="px-4 py-6 text-center text-slate-400">Memuat…</td></tr>}
                        {!list.isLoading && rowList.length === 0 && <tr><td colSpan={daysInMonth + 1} className="px-4 py-6 text-center text-slate-400">Belum ada MPS di bulan ini. Klik "Generate dari MPP".</td></tr>}
                        {rowList.map((r) => (
                            <tr key={r.key} className="border-t border-slate-100">
                                <td className="sticky left-0 z-10 border-r border-slate-200 bg-white px-3 py-2">
                                    <div className="font-medium text-slate-700">{r.machine ? r.machine.code : 'Tanpa mesin'}</div>
                                    <div className="truncate text-xs text-slate-400">{r.machine?.name || 'belum ada cycle time'}</div>
                                </td>
                                {days.map((d) => {
                                    const cell = r.byDay[d] || [];
                                    const dow = new Date(y, mo - 1, d).getDay();
                                    return (
                                        <td key={d}
                                            className={`h-12 border-l border-slate-50 p-0.5 align-top ${dow === 0 || dow === 6 ? 'bg-slate-50/50' : ''}`}
                                            onDragOver={(e) => { if (drag) e.preventDefault(); }}
                                            onDrop={() => {
                                                if (drag) {
                                                    if (drag.status === 'DRAFT') reschedule.mutate({ m: drag, date: dateOf(d), machineId: r.machine_id });
                                                    else if (drag.status === 'APPROVED' && can('mps', 'edit')) openReq(drag, dateOf(d), r.machine_id);
                                                }
                                                setDrag(null);
                                            }}
                                            onClick={() => cell.length === 0 && can('mps', 'create') && openCreate(dateOf(d), r.machine_id || '')}
                                        >
                                            {cell.map((m) => (
                                                <div key={m.id}
                                                    data-item={m.item_id} data-date={(m.plan_date || '').slice(0, 10)} data-proc={m.proc_id || 0}
                                                    draggable={m.status === 'DRAFT' || m.status === 'APPROVED'}
                                                    onDragStart={() => setDrag(m)}
                                                    onMouseEnter={() => setFocus(m.item_id)}
                                                    onMouseLeave={() => setFocus((f) => (f === m.item_id ? null : f))}
                                                    onClick={(e) => { e.stopPropagation(); openEdit(m); }}
                                                    title={`${m.op_seq ? 'OP' + m.op_seq + ' · ' : ''}${m.process?.code ? m.process.code + ' · ' : ''}${m.item?.code} · ${m.plan_date?.slice(0, 10)} · qty ${m.qty} · ${m.status}${m.pending ? ' · MENUNGGU PERSETUJUAN RESCHEDULE' : ''}`}
                                                    className={`mb-0.5 cursor-pointer rounded px-1 py-0.5 text-center leading-tight text-white shadow-sm transition-opacity ${m.pending ? 'mps-pending' : opColor(m.op_seq)} ${foundIds.has(m.id) ? 'mps-found' : ''} ${m.status === 'DRAFT' ? 'hover:brightness-110' : ''} ${m.status === 'APPROVED' && focus !== m.item_id ? 'ring-2 ring-emerald-700' : ''} ${focus && focus !== m.item_id ? 'opacity-25' : ''} ${focus === m.item_id ? 'relative z-30 ring-2 ring-blue-600' : ''}`}>
                                                    <div className="truncate text-[8px] font-semibold uppercase tracking-wide opacity-80">{m.op_seq ? `OP${m.op_seq} · ` : ''}{m.process?.code || '—'}</div>
                                                    <div className="truncate text-[9px] font-medium opacity-90">{m.item?.code}</div>
                                                    <div className="text-[11px] font-semibold">{money(m.qty)}</div>
                                                </div>
                                            ))}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
              </div>
            </div>
            <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                <span className="font-medium text-slate-600">Warna = OP (urutan proses):</span>
                {OP_COLORS.map((c, i) => (
                    <span key={i} className="flex items-center gap-1"><span className={`inline-block h-3 w-3 rounded ${c}`} /> OP{i + 1}</span>
                ))}
                <span className="text-slate-400">(OP berikutnya mengulang warna)</span>
                <span className="mx-1 h-4 w-px bg-slate-200" />
                <span className="flex items-center gap-1"><span className="inline-block h-3 w-3 rounded bg-slate-400 ring-2 ring-emerald-700" /> Approved (terkunci)</span>
                <span className="flex items-center gap-1"><span className="inline-block h-3 w-3 rounded bg-yellow-300" /> Menunggu persetujuan (kedip)</span>
            </div>

            {/* Create / edit */}
            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Lot MPS' : 'Tambah Lot MPS'}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Tutup</button>
                    {modal?.mode === 'edit' && modal.status === 'DRAFT' && can('mps', 'delete') && <button className="btn btn-ghost text-red-600" onClick={() => window.confirm('Hapus lot MPS?') && remove.mutate(modal.id)}><Icon name="trash" /> Hapus</button>}
                    {modal?.mode === 'edit' && modal.status === 'DRAFT' && can('mps', 'edit') && <button className="btn btn-ghost text-emerald-700" onClick={() => act.mutate(modal.id)}><Icon name="check" /> Approve</button>}
                    {modal?.mode === 'edit' && modal.status === 'APPROVED' && can('mps', 'edit') && <button className="btn btn-ghost text-amber-700" onClick={() => { openReq(modal.lot, (modal.lot.plan_date || '').slice(0, 10), modal.lot.machine_id); setModal(null); }}><Icon name="calendar" /> Ajukan Reschedule</button>}
                    {(modal?.mode === 'create' || modal?.status === 'DRAFT') && can('mps', 'edit') && (
                        <button className="btn btn-primary" onClick={submit} disabled={save.isPending}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                    )}
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {modal && form && (<>
                    {modal.mode === 'create' && (
                        <div className="mb-4">
                            <label className="field-label">Dari MPP (approved) <span className="text-red-500">*</span></label>
                            <Select value={form.mpp_id} onChange={onSelectMpp} options={approvedMpp.data} getValue={(o) => o.id} getLabel={(o) => `${o.period.slice(0, 4)}-${o.period.slice(4, 6)} · ${o.item?.code} (rencana ${o.plan_qty})`} placeholder="— pilih MPP —" />
                            <p className="mt-1 text-xs text-slate-400">Total MPS per (item, bulan) ≤ rencana MPP.</p>
                        </div>
                    )}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2"><label className="field-label">Item FG</label><input className="field-input bg-slate-50" value={form.item_label} disabled placeholder="— dari MPP —" /></div>
                        <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.plan_date} onChange={(e) => set('plan_date', e.target.value)} /></div>
                        <div><label className="field-label">Qty <span className="text-red-500">*</span></label><CellInput type="number" value={form.qty} onChange={(v) => set('qty', v)} /></div>
                        <div><label className="field-label">Proses</label>
                            <Select value={form.proc_id} onChange={(v) => set('proc_id', v)} options={procs.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name_p}`} placeholder="— pilih proses —" /></div>
                        <div><label className="field-label">Mesin</label>
                            <Select value={form.machine_id} onChange={(v) => set('machine_id', v)} options={machines.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— pilih mesin —" /></div>
                    </div>
                </>)}
            </Modal>

            {/* Request reschedule for a locked (APPROVED) lot */}
            <Modal open={!!reqModal} onClose={() => setReqModal(null)} title="Ajukan Reschedule (perlu persetujuan)"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setReqModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => requestResched.mutate({ mps_id: reqModal.mps_id, to_date: reqModal.to_date, to_machine_id: reqModal.to_machine_id || null, reason: reqModal.reason })} disabled={requestResched.isPending}>
                        {requestResched.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="send" />} Kirim Permintaan
                    </button>
                </>}>
                {reqModal && (
                    <div className="space-y-3">
                        <p className="text-sm text-slate-600">Lot <b>{reqModal.item?.code}</b> terkunci. Perubahan akan berlaku setelah disetujui di menu <b>Persetujuan Jadwal MPS</b>.</p>
                        <div className="grid grid-cols-2 gap-3 rounded-md bg-slate-50 p-3 text-sm">
                            <div><span className="text-slate-400">Dari tanggal</span><div className="font-medium">{reqModal.from_date}</div></div>
                            <div><span className="text-slate-400">Dari mesin</span><div className="font-medium">{reqModal.from_machine}</div></div>
                        </div>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div><label className="field-label">Tanggal baru <span className="text-red-500">*</span></label><input type="date" className="field-input" value={reqModal.to_date} onChange={(e) => setReqModal((s) => ({ ...s, to_date: e.target.value }))} /></div>
                            <div><label className="field-label">Mesin baru</label>
                                <Select value={reqModal.to_machine_id} onChange={(v) => setReqModal((s) => ({ ...s, to_machine_id: v }))} options={machines.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— pilih mesin —" /></div>
                        </div>
                        <div><label className="field-label">Alasan</label><textarea className="field-input" rows={2} value={reqModal.reason} onChange={(e) => setReqModal((s) => ({ ...s, reason: e.target.value }))} placeholder="Alasan pemindahan jadwal…" /></div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
