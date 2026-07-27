import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import Modal from '../../components/Modal';
import { Select, useOptions, money } from '../procurement/common';
import { Timer, KplModal, ViewCuttingModal, ViewPalletModal } from './mesShared';

const today = () => new Date().toISOString().slice(0, 10);
const MAX_MACHINE = 4;   // cutting: 4 machines per transaction


/** Small −/+ stepper used for Qty Act. */
function Stepper({ value, onCommit, min = 0, max }) {
    const [v, setV] = useState(value ?? 0);
    useEffect(() => setV(value ?? 0), [value]);
    const clamp = (n) => Math.max(min, max != null ? Math.min(max, n) : n);
    return (
        <div className="flex items-center">
            <button type="button" className="rounded-l bg-red-400 px-2 py-1 text-white hover:bg-red-500" onClick={() => { const n = clamp(Number(v) - 1); setV(n); onCommit(n); }}>−</button>
            <input className="w-16 border-y border-slate-300 px-2 py-1 text-center text-sm outline-none"
                value={v} onChange={(e) => setV(e.target.value.replace(/\D/g, ''))}
                onBlur={() => onCommit(clamp(Number(v) || 0))}
                onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()} />
            <button type="button" className="rounded-r bg-emerald-500 px-2 py-1 text-white hover:bg-emerald-600" onClick={() => { const n = clamp(Number(v) + 1); setV(n); onCommit(n); }}>+</button>
        </div>
    );
}

/**
 * Length Remaining. Follows the value the server computed from Qty Act, but the
 * operator can still type over it (the bar may not cut out exactly).
 */
function LenInput({ value, onCommit, disabled }) {
    const [v, setV] = useState(value ?? '');
    useEffect(() => setV(value ?? ''), [value]);
    return (
        <input className="field-input w-40" placeholder="Length Remaining" disabled={disabled}
            value={v} onChange={(e) => setV(e.target.value.replace(/[^\d.]/g, ''))}
            onBlur={() => onCommit(Number(v || 0))}
            onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()} />
    );
}

/**
 * Cutting Transaction — the shop-floor screen for the first routing step,
 * modelled on the original app: a WIP + Denpyou header, then one "Machine
 * Details" block per machine, each with the RM bars scanned into it.
 */
export default function CuttingPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const user = useAuth((s) => s.user);
    const machines = useOptions('machines');

    const [wipCode, setWipCode] = useState('');    // filled by the Denpyou scan (readonly)
    const [ctx, setCtx] = useState(null);          // resolved WIP context
    const [head, setHead] = useState({ no_dp: '', date: today(), shift_id: '', subcont: false, sub_code: '', repair: false });
    const shifts = useQuery({ queryKey: ['shifts'], queryFn: async () => (await api.get('/shifts')).data.data });
    const [trxId, setTrxId] = useState(null);
    const [locked, setLocked] = useState(false);
    const [addMc, setAddMc] = useState('');
    const [scan, setScan] = useState({});          // detailId → serial being typed
    const [showSerials, setShowSerials] = useState(false);
    const [kpl, setKpl] = useState(false);
    const [sel, setSel] = useState([]);            // serials ticked in the Serial List
    const [selMachine, setSelMachine] = useState('');
    const [dt, setDt] = useState(null);            // Input Downtime modal
    const [ab, setAb] = useState(null);            // Input Abnormality modal
    const [mode, setMode] = useState('list');      // list ⇄ form
    const [viewId, setViewId] = useState(null);    // View Cutting Data
    const [palletId, setPalletId] = useState(null); // Print → pallet list

    const list = useQuery({
        queryKey: ['cut', 'list'],
        queryFn: async () => (await api.get('/mes/cutting', { params: { per_page: 100 } })).data.data,
        enabled: mode === 'list',
    });

    const openForm = (id = null) => {
        setTrxId(id);
        if (!id) { setCtx(null); setWipCode(''); setLocked(false); setHead({ no_dp: '', date: today(), shift_id: '', subcont: false, sub_code: '', repair: false }); }
        setMode('form');
    };

    const serialRows = useQuery({
        queryKey: ['cut', 'serial-list', head.no_dp],
        queryFn: async () => (await api.get(`/mes/cutting/serial-list/${encodeURIComponent(head.no_dp)}`)).data.data,
        enabled: showSerials && !!head.no_dp,
    });
    const dtCats = useQuery({ queryKey: ['dt-cats'], queryFn: async () => (await api.get('/mes/dt-categories')).data.data });

    const trx = useQuery({
        queryKey: ['cut', 'trx', trxId],
        queryFn: async () => (await api.get(`/mes/cutting/${trxId}`)).data.data,
        enabled: !!trxId,
    });
    const reload = () => qc.invalidateQueries({ queryKey: ['cut'] });

    /** Continuing an existing transaction: fill the header from what was saved. */
    useEffect(() => {
        if (!trx.data || ctx) return;
        setWipCode(trx.data.wip?.code || '');
        setHead((h) => ({
            ...h,
            no_dp: trx.data.no_dp || h.no_dp,
            date: (trx.data.date || h.date).slice(0, 10),
            subcont: !!trx.data.subcont, sub_code: trx.data.sub_code || '', repair: !!trx.data.repair,
        }));
        setLocked(true);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [trx.data?.id]);

    /** Scanning the Denpyou is the entry point: it creates the WIP on first scan. */
    const scanDp = useMutation({
        mutationFn: async (no_dp) => (await api.post('/mes/cutting/check', { no_dp })).data.data,
        onSuccess: (d) => {
            setCtx(d);
            setWipCode(d.wip.code);
            if (d.open_transaction_id) setTrxId(d.open_transaction_id);
        },
        onError: (e) => { setCtx(null); setWipCode(''); alert(apiError(e)); },
    });
    const start = useMutation({
        mutationFn: async () => (await api.post('/mes/cutting/start', { wip_id: ctx.wip.id, ...head })).data.data,
        onSuccess: (d) => { setTrxId(d.id); setLocked(true); reload(); },
        onError: (e) => alert(apiError(e)),
    });
    const call = useMutation({
        mutationFn: async ({ method, url, body }) => (await api({ method, url, data: body })).data.data,
        onSuccess: () => reload(),
        onError: (e) => alert(apiError(e)),
    });

    const t = trx.data;
    const rowStyle = 'border border-slate-300 px-2 py-1.5';

    /** Cut length of one piece — from the row itself, falling back to the BOM. */
    const perPiece = (s) => (s.qty_dp > 0 && s.length_request > 0 ? s.length_request / s.qty_dp : (t?.size_length || 0));
    /** What is left of the bar after cutting `qty` pieces from it. */
    const remFor = (s, qty) => Math.max(0, Math.round((s.length_serial - qty * perPiece(s)) * 100) / 100);

    return (
        <div className="space-y-4 p-6">
            {mode === 'list' && (
                <div className="rounded-md border border-slate-300 bg-white">
                    <div className="flex items-center justify-between border-b border-slate-300 bg-slate-50 px-4 py-2">
                        <span className="text-lg font-semibold text-slate-800">Cutting Transactions</span>
                        {can('mes-cutting', 'create') && (
                            <button className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700" onClick={() => openForm(null)}>+ Create New</button>
                        )}
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                <th className={rowStyle}>NO</th><th className={rowStyle}>USER</th><th className={rowStyle}>WIP CODE</th>
                                <th className={rowStyle}>CUTTING CODE</th><th className={rowStyle}>ITEM CODE</th><th className={rowStyle}>DATE</th>
                                <th className={rowStyle}>NO DP</th><th className={rowStyle}>STATUS</th><th className={rowStyle}>ACTIONS</th>
                            </tr></thead>
                            <tbody>
                                {list.isLoading && <tr><td className={rowStyle} colSpan={9}>Memuat…</td></tr>}
                                {!list.isLoading && (list.data || []).length === 0 && <tr><td className={rowStyle} colSpan={9}>Belum ada transaksi cutting.</td></tr>}
                                {(list.data || []).map((r) => (
                                    <tr key={r.id} className="hover:bg-slate-50">
                                        <td className={rowStyle}>{r.id}</td>
                                        <td className={rowStyle}>{r.user_id}</td>
                                        <td className={rowStyle}>{r.wip_code}</td>
                                        <td className={rowStyle}>{r.code}</td>
                                        <td className={rowStyle}>{r.item_code}</td>
                                        <td className={rowStyle}>{(r.date || '').slice(0, 10)}</td>
                                        <td className={rowStyle}>{r.no_dp}</td>
                                        <td className={rowStyle}>
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${r.status === 'FINISHED' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                                                {r.status === 'FINISHED' ? 'Selesai' : 'Berjalan'}
                                            </span>
                                            <div className="text-[11px] text-slate-400">{r.machines} mesin</div>
                                        </td>
                                        <td className={rowStyle}>
                                            <div className="flex gap-1">
                                                <button className="rounded bg-blue-600 px-2 py-1 text-xs font-medium text-white" onClick={() => setViewId(r.id)}>View</button>
                                                <button className="rounded bg-teal-500 px-2 py-1 text-xs font-medium text-white" onClick={() => setPalletId(r.id)}>Print</button>
                                                <button className="rounded px-2 py-1 text-xs font-medium text-white disabled:bg-slate-300 disabled:text-slate-500"
                                                    style={r.status === 'FINISHED' ? undefined : { backgroundColor: '#f59e0b' }}
                                                    disabled={r.status === 'FINISHED'} onClick={() => openForm(r.id)}>→ Continue</button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {mode === 'form' && (<>
            {/* pieces judged repairable must be cut again on this lot */}
            {((ctx?.repair_outstanding || t?.repair_outstanding || []).length > 0) && (
                <div className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm">
                    <b className="text-amber-800">Wajib dikerjakan ulang (hasil keputusan Repair):</b>
                    <div className="mt-1 flex flex-wrap gap-2">
                        {(ctx?.repair_outstanding || t?.repair_outstanding || []).map((r) => (
                            <span key={r.serial_id} className="rounded bg-white px-2 py-1 font-mono text-xs text-amber-900 ring-1 ring-amber-300">
                                {r.serial_id} · {money(r.qty)} pcs
                            </span>
                        ))}
                    </div>
                    <div className="mt-1 text-xs text-amber-700">Centang <b>Repair</b> di header, lalu scan serial tersebut dan isi Qty Act sesuai jumlah repair.</div>
                </div>
            )}

            {/* ---------- Header ---------- */}
            <div className="rounded-md border border-slate-300 bg-white">
                <div className="flex items-center justify-between border-b border-slate-300 bg-slate-50 px-4 py-2">
                    <span className="text-lg font-semibold text-slate-800">Cutting Transaction</span>
                    <button className="btn btn-ghost" onClick={() => { setMode('list'); qc.invalidateQueries({ queryKey: ['cut', 'list'] }); }}>← Daftar</button>
                </div>
                <div className="grid grid-cols-1 gap-x-4 gap-y-3 p-4 md:grid-cols-4">
                    <div>
                        <label className="field-label">WIP Code</label>
                        <div className="flex gap-2">
                            <input className="field-input bg-slate-100" value={wipCode} placeholder="WIP-OP-xxxx-xxxx" readOnly />
                            <button className="btn btn-primary shrink-0" onClick={() => setKpl(true)} disabled={!ctx}>Check KPL</button>
                        </div>
                    </div>
                    <div>
                        <label className="field-label">Scan Denpyou Here</label>
                        <input className="field-input" value={head.no_dp} autoFocus
                            onChange={(e) => setHead({ ...head, no_dp: e.target.value })}
                            onKeyDown={(e) => { if (e.key === 'Enter' && head.no_dp) scanDp.mutate(head.no_dp); }}
                            onBlur={() => { if (head.no_dp && !ctx) scanDp.mutate(head.no_dp); }}
                            placeholder="Scan denpyou lalu Enter" disabled={locked || !!ctx} />
                        <div className="text-[11px] text-slate-400">WIP dibuat otomatis saat scan pertama</div>
                    </div>
                    <div>
                        <label className="field-label">Date</label>
                        <input type="date" className="field-input" value={head.date} onChange={(e) => setHead({ ...head, date: e.target.value })} disabled={locked} />
                    </div>
                    <div>
                        <label className="field-label">User</label>
                        <input className="field-input bg-slate-100" value={t?.user_id || (user ? `U${String(user.id).padStart(4, '0')}` : '')} disabled />
                    </div>

                    <div>
                        <label className="field-label">Item Code</label>
                        <input className="field-input bg-slate-100" value={ctx?.item?.code || t?.item?.code || ''} disabled />
                    </div>
                    <div>
                        <label className="field-label">Size Length for Cutting</label>
                        <input className="field-input bg-slate-100" value={ctx?.size_length ?? t?.size_length ?? ''} disabled />
                    </div>
                    <div>
                        <label className="field-label">Subcontract</label>
                        <input type="checkbox" className="mt-2 h-5 w-9 accent-slate-700" checked={head.subcont} onChange={(e) => setHead({ ...head, subcont: e.target.checked })} disabled={locked} />
                    </div>
                    <div>
                        <label className="field-label">Delivery Code</label>
                        <input className="field-input bg-slate-100" value={head.sub_code} onChange={(e) => setHead({ ...head, sub_code: e.target.value })} placeholder="DO code" disabled={locked || !head.subcont} />
                    </div>

                    <div>
                        <label className="field-label">Repair</label>
                        <input type="checkbox" className="mt-2 h-5 w-9 accent-slate-700" checked={head.repair} onChange={(e) => setHead({ ...head, repair: e.target.checked })} disabled={locked} />
                        <div className="text-[11px] text-slate-400">kerjakan ulang hasil keputusan repair</div>
                    </div>
                    <div>
                        <label className="field-label">Shift <span className="text-red-500">*</span></label>
                        <select className="field-input" value={head.shift_id} onChange={(e) => setHead({ ...head, shift_id: e.target.value })} disabled={locked}>
                            <option value="">Select Shift</option>
                            {(shifts.data || []).map((s) => <option key={s.id} value={s.id}>{s.name || s.code}</option>)}
                        </select>
                    </div>
                    <div className="flex items-end">
                        <button className={`rounded px-4 py-2 text-sm font-medium text-white ${locked ? 'bg-slate-400' : 'bg-yellow-500 hover:bg-yellow-600'}`} onClick={() => setLocked((l) => !l)} disabled={!ctx}>Lock</button>
                    </div>
                    <div className="flex items-end gap-2">
                        {can('mes-cutting', 'create') && (
                            <button className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:bg-slate-300"
                                onClick={() => { if (!head.shift_id) return alert('Pilih shift dulu.'); start.mutate(); }}
                                disabled={!ctx || !!trxId || !head.no_dp || start.isPending}>Start</button>
                        )}
                        <button className="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:bg-slate-300"
                            onClick={() => { setSel([]); setShowSerials(true); }} disabled={!ctx}>View Serials</button>
                    </div>
                </div>

            </div>

            {/* ---------- Cutting Detail ---------- */}
            {trxId && (
                <div className="rounded-md border border-slate-300 bg-white">
                    <div className="border-b border-slate-300 bg-slate-50 px-4 py-2 text-lg font-semibold text-slate-800">Cutting Detail</div>
                    <div className="space-y-4 p-4">
                        {trx.isLoading && <div className="py-6 text-center text-slate-400">Memuat…</div>}

                        {(t?.machines || []).map((m) => (
                            <div key={m.id} className="rounded-md border border-slate-300">
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-2">
                                    <span className="font-semibold text-slate-700">Machine Details</span>
                                    <Timer start={m.start_time} end={m.end_time} elapsed={m.elapsed_sec} />
                                </div>
                                <div className="flex flex-wrap items-center gap-2 px-4 py-3">
                                    <input className="field-input w-56 bg-slate-100" value={m.machine ? `${m.machine.code} — ${m.machine.name}` : '—'} disabled />
                                    {m.downtime ? (
                                        <button className="rounded bg-slate-500 px-3 py-2 text-sm font-medium text-white"
                                            onClick={() => setDt({ det_cut_id: m.id, machine: m.machine?.code, cut_code: t.code, no_dp: t.no_dp, cat_id: m.downtime.cat_id || '', desc: m.downtime.descriptions || '', dtId: m.downtime.id, start_time: m.downtime.start_time, elapsed: m.downtime.elapsed_sec, tools: [], toolInput: '' })}>
                                            Downtime Running
                                            <span className="ml-2 rounded bg-red-600 px-1.5 py-0.5"><Timer elapsed={m.downtime.elapsed_sec} start={m.downtime.start_time} className="font-mono text-xs text-white" /></span>
                                        </button>
                                    ) : (
                                        <button className="rounded bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:bg-slate-300"
                                            disabled={!!m.finish}
                                            onClick={() => setDt({ det_cut_id: m.id, machine: m.machine?.code, cut_code: t.code, no_dp: t.no_dp, cat_id: '', desc: '', dtId: null, start_time: null, tools: [], toolInput: '' })}>Input Downtime</button>
                                    )}
                                    <button className="rounded bg-sky-400 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:bg-slate-300"
                                        disabled={!m.downtime}
                                        title={!m.downtime ? 'Start downtime dulu — mesin harus berhenti sebelum mencatat NG' : undefined}
                                        onClick={() => setAb({
                                            det_cut_id: m.id, machine: m.machine?.code, cut_code: t.code, no_dp: t.no_dp,
                                            note: '', rows: [], serialInput: '',
                                            // any bar booked to this WO may turn out NG, not only the ones already scanned in
                                            serials: (t.available_serials || []).map((s) => ({ serial_id: s.serial_id, qty_dp: s.qty_dp })),
                                        })}>Input Abnormality</button>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 border-t border-slate-200 px-4 py-3">
                                    <input className="field-input w-64" placeholder="Scan Serial Number" list={`sn-${m.id}`}
                                        value={scan[m.id] || ''} onChange={(e) => setScan({ ...scan, [m.id]: e.target.value })}
                                        onKeyDown={(e) => { if (e.key === 'Enter' && scan[m.id]) { call.mutate({ method: 'post', url: `/mes/cutting/machine/${m.id}/serial`, body: { serial_id: scan[m.id] } }); setScan({ ...scan, [m.id]: '' }); } }}
                                        disabled={!!m.finish} />
                                    <datalist id={`sn-${m.id}`}>{(t.available_serials || []).map((s) => <option key={s.serial_id} value={s.serial_id} />)}</datalist>
                                    <button className="rounded bg-cyan-400 px-3 py-2 text-sm font-medium text-white hover:bg-cyan-500 disabled:bg-slate-300"
                                        disabled={!!m.finish}
                                        onClick={() => m.serials.forEach((s) => call.mutate({ method: 'put', url: `/mes/cutting/serial/${s.id}`, body: { qty: s.qty_dp, length_rem: remFor(s, s.qty_dp) } }))}>Auto Max</button>
                                    <button className="rounded bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:bg-slate-300"
                                        disabled={!!m.finish}
                                        onClick={() => m.serials.forEach((s) => call.mutate({ method: 'put', url: `/mes/cutting/serial/${s.id}`, body: { finish: 1 } }))}>Auto Done</button>
                                </div>

                                <div className="overflow-x-auto px-4 pb-3">
                                    <table className="w-full text-sm">
                                        <thead><tr className="text-left text-xs font-semibold text-slate-600">
                                            <th className={rowStyle}>Serials</th><th className={rowStyle}>Qty DP</th><th className={rowStyle}>Qty Act</th>
                                            <th className={rowStyle}>Length Serial</th><th className={rowStyle}>Length Request</th><th className={rowStyle}>Length Remaining</th>
                                            <th className={rowStyle}>Finish</th><th className={rowStyle}>Action</th>
                                        </tr></thead>
                                        <tbody>
                                            {m.serials.length === 0 && <tr><td className={rowStyle} colSpan={8}>Belum ada serial discan pada mesin ini.</td></tr>}
                                            {m.serials.map((s) => (
                                                <tr key={s.id}>
                                                    <td className={rowStyle}>{s.serial_id}</td>
                                                    <td className={rowStyle}>{s.qty_dp}</td>
                                                    <td className={rowStyle}>
                                                        <Stepper value={s.qty} max={s.qty_dp || undefined}
                                                            onCommit={(n) => call.mutate({ method: 'put', url: `/mes/cutting/serial/${s.id}`, body: { qty: n, length_rem: remFor(s, n) } })} />
                                                    </td>
                                                    <td className={rowStyle}>{money(s.length_serial)}</td>
                                                    <td className={rowStyle}>{money(s.length_request)}</td>
                                                    <td className={rowStyle}>
                                                        <LenInput value={s.length_rem} disabled={!!m.finish}
                                                            onCommit={(val) => call.mutate({ method: 'put', url: `/mes/cutting/serial/${s.id}`, body: { length_rem: val } })} />
                                                    </td>
                                                    <td className={`${rowStyle} text-center`}>
                                                        <input type="checkbox" className="h-4 w-4" checked={!!s.finish}
                                                            onChange={(e) => call.mutate({ method: 'put', url: `/mes/cutting/serial/${s.id}`, body: { finish: e.target.checked ? 1 : 0 } })} />
                                                    </td>
                                                    <td className={rowStyle}>
                                                        <div className="flex gap-1">
                                                            <button className="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                                                                onClick={() => call.mutate({ method: 'put', url: `/mes/cutting/serial/${s.id}`, body: { finish: 1 } })}>Done</button>
                                                            <button className="rounded bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700"
                                                                onClick={() => call.mutate({ method: 'delete', url: `/mes/cutting/serial/${s.id}` })}>Remove</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="flex justify-center gap-2 border-t border-slate-200 py-3">
                                    <button className="rounded bg-yellow-500 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-600 disabled:bg-slate-300"
                                        disabled={!!m.finish || !!m.downtime}
                                        title={m.downtime ? 'Selesaikan downtime dulu' : undefined}
                                        onClick={() => window.confirm('Selesaikan cutting? Hasil akan dibuatkan pallet.') && call.mutate({ method: 'post', url: `/mes/cutting/${trxId}/finish` })}>Finish Cutting</button>
                                    <button className="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                                        onClick={() => window.confirm('Hapus mesin ini dari transaksi?') && call.mutate({ method: 'delete', url: `/mes/cutting/machine/${m.id}` })}>Remove Machine</button>
                                </div>
                            </div>
                        ))}

                        <div className="flex items-center justify-center gap-2">
                            <input className="field-input w-64" placeholder="Scan Machine Code" list="mclist" value={addMc}
                                onChange={(e) => setAddMc(e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter' && addMc) { call.mutate({ method: 'post', url: `/mes/cutting/${trxId}/machine`, body: { mach_code: addMc } }); setAddMc(''); } }} />
                            <datalist id="mclist">{(machines.data || []).map((o) => <option key={o.id} value={o.code}>{o.name}</option>)}</datalist>
                            <button className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:bg-slate-300"
                                disabled={!addMc || (t?.machines || []).length >= MAX_MACHINE}
                                onClick={() => { call.mutate({ method: 'post', url: `/mes/cutting/${trxId}/machine`, body: { mach_code: addMc } }); setAddMc(''); }}>Add Machine</button>
                        </div>
                        {(t?.machines || []).length >= MAX_MACHINE && (
                            <div className="text-center text-xs text-amber-600">Sudah mencapai batas {MAX_MACHINE} mesin untuk transaksi cutting.</div>
                        )}

                        {/* footer actions, like the reference screen */}
                        <div className="flex justify-center gap-2 border-t border-slate-200 pt-3">
                            <button className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:bg-slate-300"
                                disabled={!(t?.pallets || []).length}
                                onClick={() => alert(`Pallet: ${(t.pallets || []).map((p) => p.code).join(', ')}\n(cetak label belum tersedia)`)}>print pallet</button>
                            <button className="rounded bg-blue-500 px-4 py-2 text-sm font-medium text-white disabled:bg-slate-300"
                                disabled={!(t?.machines || []).length || (t?.machines || []).some((m) => !m.finish)}
                                onClick={() => alert('Semua mesin sudah selesai — transaksi cutting tertutup.')}>FINISH</button>
                            <button className="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white"
                                onClick={() => { if (window.confirm('Batalkan transaksi ini di layar?')) { setTrxId(null); setCtx(null); setWipCode(''); setLocked(false); setHead({ no_dp: '', date: today(), shift_id: '', subcont: false, sub_code: '', repair: false }); } }}>CANCEL</button>
                        </div>

                        {(t?.pallets || []).length > 0 && (
                            <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">
                                <b>Pallet hasil cutting:</b> {t.pallets.map((p) => `${p.code} (${money(p.qty)} pcs)`).join(', ')}
                                <div className="text-xs text-emerald-700">Pallet ini yang discan di layar Processing.</div>
                            </div>
                        )}
                    </div>
                </div>
            )}

            </>)}

            <KplModal open={kpl} wipCode={wipCode} onClose={() => setKpl(false)} />
            <ViewCuttingModal open={!!viewId} id={viewId} onClose={() => setViewId(null)} />
            <ViewPalletModal open={!!palletId} id={palletId} kind="cut" onClose={() => setPalletId(null)} />

            {/* ---------- Serial List ---------- */}
            <Modal open={showSerials} onClose={() => setShowSerials(false)} size="max-w-6xl" title="Serial List"
                footer={<>
                    <span className="mr-auto text-sm text-slate-500"><b>{sel.length}</b> selected</span>
                    {trxId && (t?.machines || []).length > 0 && (
                        <>
                            <select className="field-input w-56" value={selMachine} onChange={(e) => setSelMachine(e.target.value)}>
                                <option value="">— pilih mesin tujuan —</option>
                                {(t.machines || []).filter((m) => !m.finish).map((m) => <option key={m.id} value={m.id}>{m.machine?.code}</option>)}
                            </select>
                            <button className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:bg-slate-300"
                                disabled={!sel.length || !selMachine}
                                onClick={() => { call.mutate({ method: 'post', url: `/mes/cutting/machine/${selMachine}/serials`, body: { serials: sel } }); setSel([]); setShowSerials(false); }}>
                                Add Selected to Machine</button>
                        </>
                    )}
                    <button className="btn btn-ghost" onClick={() => setShowSerials(false)}>Tutup</button>
                </>}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                            <th className={rowStyle}><input type="checkbox" className="h-4 w-4"
                                checked={!!(serialRows.data || []).length && sel.length === (serialRows.data || []).filter((r) => !r.fulfilled).length}
                                onChange={(e) => setSel(e.target.checked ? (serialRows.data || []).filter((r) => !r.fulfilled).map((r) => r.serial_id) : [])} /></th>
                            <th className={rowStyle}>Serial</th><th className={rowStyle}>OD</th><th className={rowStyle}>ID</th>
                            <th className={rowStyle}>Thickness</th><th className={rowStyle}>Length</th><th className={rowStyle}>Length Use</th>
                            <th className={rowStyle}>Request</th><th className={rowStyle}>Was Cut</th>
                        </tr></thead>
                        <tbody>
                            {serialRows.isLoading && <tr><td className={rowStyle} colSpan={9}>Memuat…</td></tr>}
                            {(serialRows.data || []).map((r) => (
                                <tr key={r.serial_id} className={r.fulfilled ? 'bg-emerald-50' : ''}>
                                    <td className={`${rowStyle} text-center`}>
                                        <input type="checkbox" className="h-4 w-4" disabled={r.fulfilled}
                                            checked={sel.includes(r.serial_id)}
                                            onChange={(e) => setSel((s) => (e.target.checked ? [...s, r.serial_id] : s.filter((x) => x !== r.serial_id)))} /></td>
                                    <td className={rowStyle}>{r.serial_id}</td>
                                    <td className={rowStyle}>{r.od ?? '-'}</td><td className={rowStyle}>{r.id_dim ?? '-'}</td>
                                    <td className={rowStyle}>{r.thickness ?? '-'}</td><td className={rowStyle}>{money(r.length)}</td>
                                    <td className={rowStyle}>{money(r.length_use)}</td>
                                    <td className={rowStyle}>{money(r.request)} pcs</td><td className={rowStyle}>{money(r.was_cut)} pcs</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Modal>

            {/* ---------- Input Downtime ---------- */}
            <Modal open={!!dt} onClose={() => setDt(null)} wide title="Input Downtime"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setDt(null)}>Tutup</button>
                    <button className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:bg-slate-300"
                        disabled={!dt?.dtId}
                        onClick={async () => {
                            try {
                                await api.post(`/mes/cutting/downtime/${dt.dtId}/save`, { tools: dt.tools });
                                alert('Downtime disimpan.'); setDt(null); reload();
                            } catch (e) { alert(apiError(e)); }
                        }}>Save</button>
                </>}>
                {dt && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div><label className="field-label">User ID</label><input className="field-input bg-slate-50" value={t?.user_id || ''} disabled /></div>
                            <div><label className="field-label">Machine code</label><input className="field-input bg-slate-50" value={dt.machine || ''} disabled /></div>
                            <div><label className="field-label">Cutting Code</label><input className="field-input bg-slate-50" value={dt.cut_code || ''} disabled /></div>
                            <div><label className="field-label">No Denpyou</label><input className="field-input bg-slate-50" value={dt.no_dp || ''} disabled /></div>
                            <div><label className="field-label">Category</label>
                                <select className="field-input" value={dt.cat_id} onChange={(e) => setDt({ ...dt, cat_id: e.target.value })} disabled={!!dt.dtId}>
                                    <option value="">Select Category</option>
                                    {(dtCats.data || []).map((c) => <option key={c.id} value={c.id}>{c.name_c_dt}</option>)}
                                </select></div>
                            <div><label className="field-label">Description</label>
                                <input className="field-input" value={dt.desc} onChange={(e) => setDt({ ...dt, desc: e.target.value })} disabled={!!dt.dtId} /></div>
                        </div>
                        <div className="flex items-center justify-end gap-3">
                            {dt.start_time && (
                                <span className="rounded bg-red-100 px-2 py-1 text-sm text-red-700">
                                    berhenti sejak {dt.start_time} · <Timer elapsed={dt.elapsed} start={dt.start_time} className="font-mono text-sm text-red-700" />
                                </span>
                            )}
                            <button className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:bg-slate-300"
                                disabled={!dt.cat_id || !!dt.dtId}
                                onClick={async () => {
                                    try {
                                        const r = (await api.post('/mes/cutting/downtime/start', { det_cut_id: dt.det_cut_id, cat_id: dt.cat_id, descriptions: dt.desc })).data.data;
                                        setDt({ ...dt, dtId: r.id, start_time: r.start_time }); reload();
                                    } catch (e) { alert(apiError(e)); }
                                }}>Start</button>
                        </div>
                        <div>
                            <input className="field-input w-64" placeholder="Scan Tools" disabled={!dt.dtId}
                                value={dt.toolInput} onChange={(e) => setDt({ ...dt, toolInput: e.target.value })}
                                onKeyDown={(e) => { if (e.key === 'Enter' && dt.toolInput) setDt({ ...dt, tools: [...dt.tools, { serial_item: dt.toolInput, tools_id: 0 }], toolInput: '' }); }} />
                        </div>
                        <div>
                            <div className="mb-1 text-sm font-semibold text-slate-700">Flow Process</div>
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600"><th className={rowStyle}>Serial Part</th><th className={rowStyle}>Tools</th></tr></thead>
                                <tbody>
                                    {dt.tools.length === 0 && <tr><td className={rowStyle} colSpan={2}>Belum ada tools discan.</td></tr>}
                                    {dt.tools.map((x, i) => <tr key={i}><td className={rowStyle}>{x.serial_item}</td><td className={rowStyle}>{x.tools_id || '-'}</td></tr>)}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ---------- Input Abnormality ---------- */}
            <Modal open={!!ab} onClose={() => setAb(null)} wide title="Input Abnormality"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setAb(null)}>Close</button>
                    <button className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:bg-slate-300"
                        disabled={!ab?.rows.length}
                        onClick={async () => {
                            try {
                                await api.post('/mes/cutting/abnormal', { det_cut_id: ab.det_cut_id, note: ab.note, rows: ab.rows });
                                alert('Abnormality (NG) tersimpan.'); setAb(null); reload();
                            } catch (e) { alert(apiError(e)); }
                        }}>Save</button>
                </>}>
                {ab && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div><label className="field-label">User ID</label><input className="field-input bg-slate-50" value={t?.user_id || ''} disabled /></div>
                            <div><label className="field-label">Machine code</label><input className="field-input bg-slate-50" value={ab.machine || ''} disabled /></div>
                            <div><label className="field-label">Cutting Code</label><input className="field-input bg-slate-50" value={ab.cut_code || ''} disabled /></div>
                            <div><label className="field-label">No Denpyou</label><input className="field-input bg-slate-50" value={ab.no_dp || ''} disabled /></div>
                            <div className="sm:col-span-2"><label className="field-label">Description</label>
                                <input className="field-input" value={ab.note} onChange={(e) => setAb({ ...ab, note: e.target.value })} /></div>
                        </div>
                        <div>
                            <input className="field-input w-64" placeholder="Scan Serial Number" list="ab-sn"
                                value={ab.serialInput} onChange={(e) => setAb({ ...ab, serialInput: e.target.value })}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && ab.serialInput) {
                                        const s = ab.serials.find((x) => x.serial_id === ab.serialInput);
                                        if (!s) return alert('Serial tidak dibooking untuk WO ini.');
                                        setAb({ ...ab, rows: [...ab.rows, { serial_id: s.serial_id, qty_per_serial: s.qty_dp, qty: 1 }], serialInput: '' });
                                    }
                                }} />
                            <datalist id="ab-sn">{ab.serials.map((s) => <option key={s.serial_id} value={s.serial_id} />)}</datalist>
                        </div>
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                <th className={rowStyle}>Serial Material</th><th className={rowStyle}>Qty per Serial</th><th className={rowStyle}>Qty</th><th className={rowStyle}>Action</th>
                            </tr></thead>
                            <tbody>
                                {ab.rows.length === 0 && <tr><td className={rowStyle} colSpan={4}>Scan serial yang abnormal.</td></tr>}
                                {ab.rows.map((r, i) => (
                                    <tr key={i}>
                                        <td className={rowStyle}>{r.serial_id}</td>
                                        <td className={rowStyle}>{r.qty_per_serial}</td>
                                        <td className={rowStyle}>
                                            <input type="number" min="1" className="field-input w-24" value={r.qty}
                                                onChange={(e) => setAb({ ...ab, rows: ab.rows.map((x, j) => (j === i ? { ...x, qty: Number(e.target.value || 1) } : x)) })} /></td>
                                        <td className={rowStyle}>
                                            <button className="rounded bg-red-600 px-3 py-1 text-xs font-medium text-white"
                                                onClick={() => setAb({ ...ab, rows: ab.rows.filter((_, j) => j !== i) })}>Remove</button></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Modal>
        </div>
    );
}
