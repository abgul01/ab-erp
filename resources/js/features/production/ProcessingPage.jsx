import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import { money } from '../procurement/common';
import Modal from '../../components/Modal';
import { Timer, Stepper, KplModal, ViewProcessingModal, ViewPalletModal } from './mesShared';

const today = () => new Date().toISOString().slice(0, 10);
const MAX_MACHINE = 2;   // processing: 2 machines per transaction

/**
 * Processing Transaction — every routing step after cutting. Entry point is the
 * PALLET scan: the pallet says which lot it belongs to and what still needs
 * work. Half = one side finished, Full = both sides. A pallet left HALF may
 * only continue on the same process (its second side); "Continue Process"
 * signals that and opens two machine sections at once.
 */
export default function ProcessingPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const user = useAuth((s) => s.user);

    const [palletCode, setPalletCode] = useState('');
    const [ctx, setCtx] = useState(null);            // from the pallet scan
    const [head, setHead] = useState({ code: '', date: today(), shift_id: '', process_id: '', sq_process: '', subcont: false, subcon_code: '', repair: false, cont_pro: false });
    const [trxId, setTrxId] = useState(null);
    const [locked, setLocked] = useState(false);
    const [kpl, setKpl] = useState(false);
    const [kplChecked, setKplChecked] = useState(false);
    const [scan, setScan] = useState({});
    const [inputType, setInputType] = useState({});   // rowId → half | full | both
    const [pending, setPending] = useState(0);        // machine sections still to add
    const [mode, setMode] = useState('list');         // list ⇄ form
    const [dt, setDt] = useState(null);               // Input Downtime modal
    const [ab, setAb] = useState(null);               // Input Abnormality modal
    const [viewId, setViewId] = useState(null);       // View Processing Data
    const [palletId, setPalletId] = useState(null);   // Print Pallet → pallet list

    const dtCats = useQuery({ queryKey: ['dt-cats', 'pro'], queryFn: async () => (await api.get('/mes/processing/dt-categories')).data.data });

    const list = useQuery({
        queryKey: ['pro', 'list'],
        queryFn: async () => (await api.get('/mes/processing', { params: { per_page: 100 } })).data.data,
        enabled: mode === 'list',
    });

    const openForm = (id = null) => {
        setTrxId(id);
        if (!id) {
            setCtx(null); setPalletCode(''); setLocked(false); setKplChecked(false); setPending(0);
            setHead({ code: '', date: today(), shift_id: '', process_id: '', sq_process: '', subcont: false, subcon_code: '', repair: false, cont_pro: false });
        }
        setMode('form');
    };

    const shifts = useQuery({ queryKey: ['shifts'], queryFn: async () => (await api.get('/shifts')).data.data });
    const flow = useQuery({
        queryKey: ['kpl', ctx?.wip_code],
        queryFn: async () => (await api.get(`/mes/kpl/${encodeURIComponent(ctx.wip_code)}`)).data.data,
        enabled: !!ctx?.wip_code,
    });
    const trx = useQuery({
        queryKey: ['pro', 'trx', trxId],
        queryFn: async () => (await api.get(`/mes/processing/${trxId}`)).data.data,
        enabled: !!trxId,
    });
    const reload = () => qc.invalidateQueries({ queryKey: ['pro'] });

    const scanPallet = useMutation({
        mutationFn: async (pallet_code) => (await api.post('/mes/processing/check', { pallet_code })).data.data,
        onSuccess: (d) => { setCtx(d); setHead((h) => ({ ...h, code: d.suggest_code })); setKplChecked(false); },
        onError: (e) => { setCtx(null); alert(apiError(e)); },
    });
    const start = useMutation({
        mutationFn: async () => (await api.post('/mes/processing/start', {
            wip_id: ctx.wip_id, no_dp: ctx.no_dp, code: head.code, date: head.date,
            pallet_code: ctx.pallet_code, cut_id: ctx.cut_id, shift_id: head.shift_id || null,
            process_id: head.process_id, sq_process: head.sq_process,
            cont_pro: head.cont_pro, subcont: head.subcont, subcon_code: head.subcon_code, repair: head.repair,
        })).data.data,
        onSuccess: (d) => { setTrxId(d.id); setLocked(true); reload(); },
        onError: (e) => alert(apiError(e)),
    });
    const call = useMutation({
        mutationFn: async ({ method, url, body }) => (await api({ method, url, data: body })).data.data,
        onSuccess: () => reload(),
        onError: (e) => alert(apiError(e)),
    });

    const t = trx.data;
    const cell = 'border border-slate-300 px-2 py-1.5';

    /** Continuing an existing transaction: rebuild the header from what was saved. */
    useEffect(() => {
        if (!trx.data || ctx) return;
        const d = trx.data;
        const tot = (d.available_pallets || []).reduce((a, p) => ({ h: a.h + p.qty_half_need, f: a.f + p.qty_full_need }), { h: 0, f: 0 });
        setPalletCode(d.pallet_code || '');
        setCtx({
            pallet_code: d.pallet_code, wip_id: d.wip?.id, wip_code: d.wip?.code, no_dp: d.no_dp,
            item_code: d.item?.code, item_id: d.item?.id, size: d.item?.length,
            qty_half_needs: tot.h, qty_full_needs: tot.f, old_status: 'cut', old_process_id: null, cut_id: null,
        });
        setHead((h) => ({
            ...h, code: d.code, date: (d.date || h.date).slice(0, 10),
            process_id: d.process?.id || '', sq_process: d.sq_process || '',
            cont_pro: !!d.cont_pro, subcont: !!d.subcont, subcon_code: d.subcon_code || '',
        }));
        setKplChecked(true); setLocked(true);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [trx.data?.id]);

    /* Process choices: skip cutting; a HALF pallet may only stay on its process. */
    const procOptions = (flow.data?.flow || [])
        .filter((f) => f.sequence !== 1)
        .filter((f) => (ctx?.old_status === 'half' ? f.process_id === ctx.old_process_id : true));

    /* Input Qty Half/Full = totals of every row across every machine. */
    const totHalf = (t?.machines || []).reduce((a, m) => a + m.qty_half, 0);
    const totFull = (t?.machines || []).reduce((a, m) => a + m.qty_full, 0);
    const machineCount = (t?.machines || []).length;

    const addMachine = () => {
        const room = MAX_MACHINE - machineCount;
        if (room <= 0) return alert(`Maksimal ${MAX_MACHINE} mesin per transaksi processing.`);
        setPending(Math.min(head.cont_pro ? 2 : 1, room));
    };

    return (
        <div className="space-y-4 p-6">
            {mode === 'list' && (
                <div className="rounded-md border border-slate-300 bg-white">
                    <div className="flex items-center justify-between border-b border-slate-300 bg-slate-50 px-4 py-2">
                        <span className="text-lg font-semibold text-slate-800">Processing Transactions</span>
                        {can('mes-processing', 'create') && (
                            <button className="rounded bg-cyan-500 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-600" onClick={() => openForm(null)}>Create</button>
                        )}
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                <th className={cell}>No</th><th className={cell}>User</th><th className={cell}>Processing Code</th>
                                <th className={cell}>WIP</th><th className={cell}>Proses</th><th className={cell}>Date</th>
                                <th className={cell}>Status</th><th className={cell}>Action</th>
                            </tr></thead>
                            <tbody>
                                {list.isLoading && <tr><td className={cell} colSpan={8}>Memuat…</td></tr>}
                                {!list.isLoading && (list.data || []).length === 0 && <tr><td className={cell} colSpan={8}>Belum ada transaksi processing.</td></tr>}
                                {(list.data || []).map((r) => (
                                    <tr key={r.id} className="hover:bg-slate-50">
                                        <td className={cell}>{r.id}</td>
                                        <td className={cell}>{r.user_id}</td>
                                        <td className={`${cell} font-medium text-slate-700`}>{r.code}</td>
                                        <td className={cell}>{r.wip_code}</td>
                                        <td className={cell}>{r.process_code}</td>
                                        <td className={cell}>{(r.date || '').slice(0, 10)}</td>
                                        <td className={cell}>
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${r.status === 'FINISHED' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                                                {r.status === 'FINISHED' ? 'Selesai' : 'Berjalan'}
                                            </span>
                                            {r.status === 'FINISHED' && <div className="text-[11px] text-slate-400">half {r.qty_half ?? 0} / full {r.qty_full ?? 0}</div>}
                                        </td>
                                        <td className={cell}>
                                            <div className="flex gap-1">
                                                <button className="rounded bg-blue-600 px-2 py-1 text-xs font-medium text-white" onClick={() => setViewId(r.id)}>View</button>
                                                <button className="rounded bg-emerald-700 px-2 py-1 text-xs font-medium text-white" onClick={() => setPalletId(r.id)}>Print Pallet</button>
                                                <button className="rounded px-2 py-1 text-xs font-medium text-white disabled:bg-slate-300 disabled:text-slate-500"
                                                    style={r.status === 'FINISHED' ? undefined : { backgroundColor: '#f59e0b' }}
                                                    disabled={r.status === 'FINISHED'} onClick={() => openForm(r.id)}>Continue</button>
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
            {/* pallets judged repairable must pass this process again */}
            {((t?.repair_outstanding || []).length > 0) && (
                <div className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm">
                    <b className="text-amber-800">Wajib dikerjakan ulang (hasil keputusan Repair):</b>
                    <div className="mt-1 flex flex-wrap gap-2">
                        {t.repair_outstanding.map((r) => (
                            <span key={r.pallet_code} className="rounded bg-white px-2 py-1 font-mono text-xs text-amber-900 ring-1 ring-amber-300">
                                {r.pallet_code} · {money(r.qty)} pcs
                            </span>
                        ))}
                    </div>
                    <div className="mt-1 text-xs text-amber-700">Centang <b>Repair</b> di header, lalu scan pallet tersebut dan isi qty sesuai jumlah repair.</div>
                </div>
            )}

            {/* ---------- Header ---------- */}
            <div className="rounded-md border border-slate-300 bg-white">
                <div className="flex items-center justify-between border-b border-slate-300 bg-slate-50 px-4 py-2">
                    <span className="text-lg font-semibold text-slate-800">Processing Transaction Create</span>
                    <button className="btn btn-ghost" onClick={() => { setMode('list'); qc.invalidateQueries({ queryKey: ['pro', 'list'] }); }}>← Daftar</button>
                </div>
                <div className="grid grid-cols-1 gap-x-4 gap-y-3 p-4 md:grid-cols-4">
                    <div>
                        <label className="field-label">WIP Code</label>
                        <input className="field-input bg-slate-100" value={ctx?.wip_code || ''} placeholder="WIP-OP-xxxx-xxxx" readOnly />
                    </div>
                    <div>
                        <label className="field-label">Transaction Code</label>
                        <input className="field-input bg-slate-100" value={head.code} readOnly />
                    </div>
                    <div className="flex items-end">
                        <button className="btn btn-primary" disabled={!ctx}
                            onClick={() => { setKpl(true); setKplChecked(true); }}>Check KPL</button>
                    </div>
                    <div>
                        <label className="field-label">Scan Pallet Code Here</label>
                        <input className="field-input" value={palletCode} autoFocus
                            onChange={(e) => setPalletCode(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter' && palletCode) scanPallet.mutate(palletCode); }}
                            onBlur={() => { if (palletCode && !ctx) scanPallet.mutate(palletCode); }}
                            placeholder="Scan Pallet Code" disabled={locked || !!ctx} />
                    </div>

                    <div><label className="field-label">Date</label>
                        <input type="date" className="field-input" value={head.date} onChange={(e) => setHead({ ...head, date: e.target.value })} disabled={locked} /></div>
                    <div><label className="field-label">User</label>
                        <input className="field-input bg-slate-100" value={t?.user_id || (user ? `U${String(user.id).padStart(4, '0')}` : '')} disabled /></div>
                    <div><label className="field-label">Shift</label>
                        <select className="field-input" value={head.shift_id} onChange={(e) => setHead({ ...head, shift_id: e.target.value })} disabled={locked}>
                            <option value="">Select Shift</option>
                            {(shifts.data || []).map((s) => <option key={s.id} value={s.id}>{s.name || s.code}</option>)}
                        </select></div>
                    <div><label className="field-label">No Denpyou</label>
                        <input className="field-input bg-slate-100" value={ctx?.no_dp || ''} readOnly /></div>
                </div>

                <div className="grid grid-cols-1 gap-x-4 gap-y-3 border-t border-slate-200 p-4 md:grid-cols-6">
                    <div><label className="field-label">Item Code</label>
                        <input className="field-input bg-slate-100" value={ctx?.item_code || ''} readOnly /></div>
                    <div><label className="field-label">Size Finish Good</label>
                        <input className="field-input bg-slate-100" value={ctx?.size ?? ''} readOnly /></div>
                    <div><label className="field-label">Process <span className="text-red-500">*</span></label>
                        <select className="field-input" value={head.process_id} disabled={!kplChecked || locked}
                            onChange={(e) => {
                                const opt = procOptions.find((o) => String(o.process_id) === e.target.value);
                                setHead({ ...head, process_id: e.target.value, sq_process: opt?.sequence || '' });
                            }}>
                            <option value="">Select Process</option>
                            {procOptions.map((o) => <option key={o.process_id} value={o.process_id}>{o.process}</option>)}
                        </select>
                        {ctx?.old_status === 'half' && <div className="text-[11px] text-amber-600">pallet HALF — wajib proses yang sama</div>}
                    </div>
                    <div><label className="field-label">Subcontract</label>
                        <input type="checkbox" className="mt-2 h-5 w-9 accent-slate-700" checked={head.subcont} onChange={(e) => setHead({ ...head, subcont: e.target.checked })} disabled={!kplChecked || locked} /></div>
                    <div><label className="field-label">Delivery Code</label>
                        <input className="field-input" value={head.subcon_code} onChange={(e) => setHead({ ...head, subcon_code: e.target.value })} placeholder="DO code" disabled={!head.subcont || locked} /></div>
                    <div><label className="field-label">Repair</label>
                        <input type="checkbox" className="mt-2 h-5 w-9 accent-slate-700" checked={head.repair} onChange={(e) => setHead({ ...head, repair: e.target.checked })} disabled={!kplChecked || locked} /></div>

                    <div><label className="field-label">Qty Half Needs</label>
                        <input className="field-input bg-slate-100" value={ctx?.qty_half_needs ?? ''} readOnly /></div>
                    <div><label className="field-label">Qty Full Needs</label>
                        <input className="field-input bg-slate-100" value={ctx?.qty_full_needs ?? ''} readOnly /></div>
                    <div><label className="field-label">Input Qty Half</label>
                        <input className={`field-input bg-slate-100 ${ctx && totHalf > ctx.qty_half_needs ? 'text-red-600 font-semibold' : ''}`} value={t ? totHalf : ''} readOnly /></div>
                    <div><label className="field-label">Input Qty Full</label>
                        <input className={`field-input bg-slate-100 ${ctx && totFull > ctx.qty_full_needs ? 'text-red-600 font-semibold' : ''}`} value={t ? totFull : ''} readOnly /></div>
                    <div><label className="field-label">Continue Process</label>
                        <input type="checkbox" className="mt-2 h-5 w-9 accent-slate-700" checked={head.cont_pro} onChange={(e) => setHead({ ...head, cont_pro: e.target.checked })} disabled={!kplChecked || locked} />
                        <div className="text-[11px] text-slate-400">buka 2 mesin sekaligus</div></div>
                    <div className="flex items-end gap-2">
                        {can('mes-processing', 'create') && (
                            <button className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:bg-slate-300"
                                onClick={() => { if (!head.process_id) return alert('Pilih process dulu.'); start.mutate(); }}
                                disabled={!ctx || !!trxId || !kplChecked || start.isPending}>Start</button>
                        )}
                        <button className={`rounded px-4 py-2 text-sm font-medium text-white ${locked ? 'bg-slate-400' : 'bg-yellow-500 hover:bg-yellow-600'}`}
                            onClick={() => setLocked((l) => !l)} disabled={!ctx}>Lock</button>
                    </div>
                </div>
            </div>

            {/* ---------- Detail ---------- */}
            {trxId && (
                <div className="rounded-md border border-slate-300 bg-white">
                    <div className="border-b border-slate-300 bg-slate-50 px-4 py-2 text-lg font-semibold text-slate-800">Processing Transaction Create</div>
                    <div className="space-y-4 p-4">
                        {trx.isLoading && <div className="py-6 text-center text-slate-400">Memuat…</div>}

                        {(t?.machines || []).map((m) => (
                            <div key={m.id} className="rounded-md border border-slate-300">
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-2">
                                    <span className="font-semibold text-slate-700">Machine Details</span>
                                    <Timer start={m.start_time} end={m.end_time} elapsed={m.elapsed_sec} />
                                </div>
                                <div className="flex flex-wrap items-center gap-2 px-4 py-3">
                                    <input className="field-input w-44 bg-slate-100" value={m.user_id || ''} disabled />
                                    {m.downtime ? (
                                        <button className="rounded bg-slate-500 px-3 py-2 text-sm font-medium text-white"
                                            onClick={() => setDt({ det_pro_id: m.id, machine: m.machine?.code, pro_code: t.code, no_dp: t.no_dp, cat_id: '', note: '', dtId: m.downtime.id, start_time: m.downtime.start_time, elapsed: m.downtime.elapsed_sec, tools: [], toolInput: '' })}>
                                            Downtime Running
                                            <span className="ml-2 rounded bg-red-600 px-1.5 py-0.5"><Timer elapsed={m.downtime.elapsed_sec} start={m.downtime.start_time} className="font-mono text-xs text-white" /></span>
                                        </button>
                                    ) : (
                                        <button className="rounded bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:bg-slate-300"
                                            disabled={!!m.finish}
                                            onClick={() => setDt({ det_pro_id: m.id, machine: m.machine?.code, pro_code: t.code, no_dp: t.no_dp, cat_id: '', note: '', dtId: null, start_time: null, tools: [], toolInput: '' })}>Input Downtime</button>
                                    )}
                                    <button className="rounded bg-sky-400 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:bg-slate-300"
                                        disabled={!m.downtime}
                                        title={!m.downtime ? 'Start downtime dulu — mesin harus berhenti sebelum mencatat NG' : undefined}
                                        onClick={() => setAb({
                                            det_pro_id: m.id, machine: m.machine?.code, wip_code: t.wip?.code,
                                            pro_code: t.code, process: t.process?.code, note: '', rows: [], pick: '',
                                            // any pallet of this lot may turn out NG, not only the ones already scanned in
                                            pallets: (t.available_pallets || []).map((p) => ({ code: p.code, qty: p.qty })),
                                        })}>Input Abnormality</button>
                                    <input className="field-input w-56 bg-slate-100" value={m.machine ? `${m.machine.code} — ${m.machine.name}` : '—'} disabled />
                                </div>

                                <div className="border-t border-slate-200 px-4 py-3">
                                    <input className="field-input w-72" placeholder="Scan Pallet Code" list={`pl-${m.id}`}
                                        value={scan[m.id] || ''} onChange={(e) => setScan({ ...scan, [m.id]: e.target.value })}
                                        onKeyDown={(e) => { if (e.key === 'Enter' && scan[m.id]) { call.mutate({ method: 'post', url: `/mes/processing/machine/${m.id}/pallet`, body: { pallet_code: scan[m.id] } }); setScan({ ...scan, [m.id]: '' }); } }}
                                        disabled={!!m.finish} />
                                    <datalist id={`pl-${m.id}`}>{(t.available_pallets || []).map((p) => <option key={p.code} value={p.code}>{`half ${p.qty_half_need} / full ${p.qty_full_need}`}</option>)}</datalist>
                                </div>

                                <div className="overflow-x-auto px-4 pb-3">
                                    <table className="w-full text-sm">
                                        <thead><tr className="text-left text-xs font-semibold text-slate-600">
                                            <th className={cell}>Pallet Code</th><th className={cell}>Qty Half Need</th><th className={cell}>Qty Full Need</th>
                                            <th className={cell}>Qty Half Act</th><th className={cell}>Qty Full Act</th>
                                            <th className={cell}>Choose Input Type</th><th className={cell}>Finish</th><th className={cell}>Action</th>
                                        </tr></thead>
                                        <tbody>
                                            {m.pallets.length === 0 && <tr><td className={cell} colSpan={8}>Belum ada pallet discan pada mesin ini.</td></tr>}
                                            {m.pallets.map((p) => {
                                                const type = inputType[p.id] || (p.qty_half_need > 0 ? 'half' : 'full');
                                                return (
                                                    <tr key={p.id}>
                                                        <td className={cell}>{p.pallet_code}</td>
                                                        <td className={cell}>{money(p.qty_half_need)}</td>
                                                        <td className={cell}>{money(p.qty_full_need)}</td>
                                                        <td className={cell}>
                                                            <Stepper value={p.qty_half} max={p.qty_half_need} disabled={!(type === 'half' || type === 'both') || !!m.finish}
                                                                onCommit={(n) => call.mutate({ method: 'put', url: `/mes/processing/pallet/${p.id}`, body: { qty_half: n } })} />
                                                        </td>
                                                        <td className={cell}>
                                                            <Stepper value={p.qty_full} max={p.qty_full_need} disabled={!(type === 'full' || type === 'both') || !!m.finish}
                                                                onCommit={(n) => call.mutate({ method: 'put', url: `/mes/processing/pallet/${p.id}`, body: { qty_full: n } })} />
                                                        </td>
                                                        <td className={cell}>
                                                            <select className="field-input" value={type} onChange={(e) => setInputType({ ...inputType, [p.id]: e.target.value })} disabled={!!m.finish}>
                                                                <option value="half">Half (1 sisi)</option>
                                                                <option value="full">Full (2 sisi)</option>
                                                                <option value="both">Both</option>
                                                            </select>
                                                        </td>
                                                        <td className={`${cell} text-center`}>
                                                            <input type="checkbox" className="h-4 w-4" checked={!!p.finish}
                                                                onChange={(e) => call.mutate({ method: 'put', url: `/mes/processing/pallet/${p.id}`, body: { finish: e.target.checked ? 1 : 0 } })} />
                                                        </td>
                                                        <td className={cell}>
                                                            <div className="flex gap-1">
                                                                <button className="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                                                                    onClick={() => call.mutate({ method: 'put', url: `/mes/processing/pallet/${p.id}`, body: { finish: 1 } })}>Done</button>
                                                                <button className="rounded bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700"
                                                                    onClick={() => call.mutate({ method: 'delete', url: `/mes/processing/pallet/${p.id}` })}>Remove</button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="flex justify-center gap-2 border-t border-slate-200 py-3">
                                    <button className="rounded bg-yellow-500 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-600 disabled:bg-slate-300"
                                        disabled={!!m.finish || !!m.downtime || !m.pallets.length || m.pallets.some((p) => !p.finish)}
                                        title={m.downtime ? 'Selesaikan downtime dulu' : undefined}
                                        onClick={() => window.confirm('Selesaikan proses? Hasil akan dibuatkan pallet.') && call.mutate({ method: 'post', url: `/mes/processing/${trxId}/finish` })}>Finish Processing</button>
                                    <button className="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                                        onClick={() => window.confirm('Hapus mesin ini?') && call.mutate({ method: 'delete', url: `/mes/processing/machine/${m.id}` })}>Remove Machine</button>
                                </div>
                            </div>
                        ))}

                        {/* pending machine slots: scan the machine code to open them */}
                        {Array.from({ length: pending }).map((_, i) => (
                            <div key={`p${i}`} className="rounded-md border border-dashed border-emerald-400 p-4">
                                <div className="mb-2 text-sm font-semibold text-slate-700">Machine Details (baru)</div>
                                <input className="field-input w-64" placeholder="Scan Machine Code"
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && e.currentTarget.value) {
                                            call.mutate({ method: 'post', url: `/mes/processing/${trxId}/machine`, body: { mach_code: e.currentTarget.value } });
                                            setPending((n) => Math.max(0, n - 1));
                                        }
                                    }} />
                            </div>
                        ))}

                        <div className="flex items-center justify-center">
                            <button className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:bg-slate-300"
                                disabled={machineCount + pending >= MAX_MACHINE} onClick={addMachine}>Add Machine</button>
                        </div>

                        {(t?.out_pallets || []).length > 0 && (
                            <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">
                                <b>Pallet hasil:</b> {t.out_pallets.map((p) => `${p.code} — ${money(p.qty)} pcs (${p.status}, dari ${p.pal_code_bf})`).join(' · ')}
                            </div>
                        )}

                        <div className="flex justify-center gap-2 border-t border-slate-200 pt-3">
                            <button className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:bg-slate-300"
                                disabled={!(t?.out_pallets || []).length}
                                onClick={() => alert(`Pallet: ${(t.out_pallets || []).map((p) => p.code).join(', ')}\n(cetak label belum tersedia)`)}>print pallet</button>
                            <button className="rounded bg-blue-500 px-4 py-2 text-sm font-medium text-white disabled:bg-slate-300"
                                disabled={!machineCount || (t?.machines || []).some((m) => !m.finish)}
                                onClick={() => alert('Semua mesin selesai — transaksi processing tertutup.')}>FINISH</button>
                            <button className="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white"
                                onClick={() => { if (window.confirm('Batalkan transaksi di layar?')) { setTrxId(null); setCtx(null); setPalletCode(''); setLocked(false); setKplChecked(false); setPending(0); setHead({ code: '', date: today(), shift_id: '', process_id: '', sq_process: '', subcont: false, subcon_code: '', repair: false, cont_pro: false }); } }}>CANCEL</button>
                        </div>
                    </div>
                </div>
            )}

            </>)}

            {/* ---------- Input Downtime ---------- */}
            <Modal open={!!dt} onClose={() => setDt(null)} wide title="Input Downtime"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setDt(null)}>Tutup</button>
                    <button className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:bg-slate-300"
                        disabled={!dt?.dtId}
                        onClick={async () => {
                            try {
                                await api.post(`/mes/processing/downtime/${dt.dtId}/save`, { tools: dt.tools });
                                alert('Downtime disimpan (mesin jalan lagi).'); setDt(null); reload();
                            } catch (e) { alert(apiError(e)); }
                        }}>Save</button>
                </>}>
                {dt && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div><label className="field-label">User ID</label><input className="field-input bg-slate-50" value={t?.user_id || ''} disabled /></div>
                            <div><label className="field-label">Machine code</label><input className="field-input bg-slate-50" value={dt.machine || ''} disabled /></div>
                            <div><label className="field-label">Process Code</label><input className="field-input bg-slate-50" value={dt.pro_code || ''} disabled /></div>
                            <div><label className="field-label">No Denpyou</label><input className="field-input bg-slate-50" value={dt.no_dp || ''} disabled /></div>
                            <div><label className="field-label">Category</label>
                                <select className="field-input" value={dt.cat_id} onChange={(e) => setDt({ ...dt, cat_id: e.target.value })} disabled={!!dt.dtId}>
                                    <option value="">Select Category</option>
                                    {(dtCats.data || []).map((c) => <option key={c.id} value={c.id}>{c.name_c_dt}</option>)}
                                </select></div>
                            <div><label className="field-label">Description</label>
                                <input className="field-input" value={dt.note} onChange={(e) => setDt({ ...dt, note: e.target.value })} disabled={!!dt.dtId} maxLength={50} /></div>
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
                                        const r = (await api.post('/mes/processing/downtime/start', { det_pro_id: dt.det_pro_id, cat_id: dt.cat_id, note: dt.note })).data.data;
                                        setDt({ ...dt, dtId: r.id, start_time: r.start_time }); reload();
                                    } catch (e) { alert(apiError(e)); }
                                }}>Start</button>
                        </div>
                        <div>
                            <input className="field-input w-64" placeholder="Scan Tools" disabled={!dt.dtId}
                                value={dt.toolInput} onChange={(e) => setDt({ ...dt, toolInput: e.target.value })}
                                onKeyDown={(e) => { if (e.key === 'Enter' && dt.toolInput) setDt({ ...dt, tools: [...dt.tools, { serial_tool: dt.toolInput, tools_id: 0 }], toolInput: '' }); }} />
                        </div>
                        <div>
                            <div className="mb-1 text-sm font-semibold text-slate-700">Flow Process</div>
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600"><th className={cell}>Serial Part</th><th className={cell}>Tools</th></tr></thead>
                                <tbody>
                                    {dt.tools.length === 0 && <tr><td className={cell} colSpan={2}>Belum ada tools discan.</td></tr>}
                                    {dt.tools.map((x, i) => <tr key={i}><td className={cell}>{x.serial_tool}</td><td className={cell}>{x.tools_id || '-'}</td></tr>)}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ---------- Input Abnormality (pallet-based) ---------- */}
            <Modal open={!!ab} onClose={() => setAb(null)} wide title="Input Abnormality"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setAb(null)}>Close</button>
                    <button className="rounded bg-yellow-500 px-3 py-2 text-sm font-medium text-white disabled:bg-slate-300"
                        disabled={!ab?.rows.length}
                        onClick={() => alert(`Cetak label NG:\n${ab.rows.map((r) => `${r.pallet_code} — ${r.qty} pcs${r.repair ? ' (repair)' : ''}`).join('\n')}\n\n(cetak fisik belum tersedia)`)}>Print</button>
                    <button className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:bg-slate-300"
                        disabled={!ab?.rows.length}
                        onClick={async () => {
                            try {
                                await api.post('/mes/processing/abnormal', { det_pro_id: ab.det_pro_id, note: ab.note, rows: ab.rows });
                                alert('Abnormality (NG) tersimpan.'); setAb(null); reload();
                            } catch (e) { alert(apiError(e)); }
                        }}>Save</button>
                </>}>
                {ab && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div><label className="field-label">User ID</label><input className="field-input bg-slate-50" value={t?.user_id || ''} disabled /></div>
                            <div><label className="field-label">Machine code</label><input className="field-input bg-slate-50" value={ab.machine || ''} disabled /></div>
                            <div><label className="field-label">WIP Code</label><input className="field-input bg-slate-50" value={ab.wip_code || ''} disabled /></div>
                            <div><label className="field-label">Process Code</label><input className="field-input bg-slate-50" value={ab.process || ''} disabled /></div>
                            <div className="sm:col-span-2"><label className="field-label">Description</label>
                                <input className="field-input" maxLength={50} value={ab.note} onChange={(e) => setAb({ ...ab, note: e.target.value })} /></div>
                        </div>
                        <div>
                            <select className="field-input w-72" value={ab.pick}
                                onChange={(e) => {
                                    const code = e.target.value;
                                    if (!code) return setAb({ ...ab, pick: '' });
                                    if (ab.rows.some((r) => r.pallet_code === code)) { alert('Pallet ini sudah ada di daftar.'); return setAb({ ...ab, pick: '' }); }
                                    const p = ab.pallets.find((x) => x.code === code);
                                    setAb({ ...ab, pick: '', rows: [...ab.rows, { pallet_code: code, qty_pallet: p?.qty ?? 0, qty: 1 }] });
                                }}>
                                <option value="">‒ pilih pallet ‒</option>
                                {ab.pallets.map((p) => <option key={p.code} value={p.code}>{p.code} ({money(p.qty)} pcs)</option>)}
                            </select>
                        </div>
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                <th className={cell}>Pallet Code</th><th className={cell}>Qty Pallet</th><th className={cell}>Qty Input</th><th className={cell}>Action</th>
                            </tr></thead>
                            <tbody>
                                {ab.rows.length === 0 && <tr><td className={cell} colSpan={4}>Pilih pallet yang abnormal.</td></tr>}
                                {ab.rows.map((r, i) => (
                                    <tr key={i}>
                                        <td className={cell}>{r.pallet_code}</td>
                                        <td className={cell}>{money(r.qty_pallet)}</td>
                                        <td className={cell}>
                                            <input type="number" min="1" max={r.qty_pallet || undefined} className="field-input w-24" value={r.qty}
                                                onChange={(e) => setAb({ ...ab, rows: ab.rows.map((x, j) => (j === i ? { ...x, qty: Number(e.target.value || 1) } : x)) })} /></td>
                                        <td className={cell}>
                                            <button className="rounded bg-red-600 px-3 py-1 text-xs font-medium text-white"
                                                onClick={() => setAb({ ...ab, rows: ab.rows.filter((_, j) => j !== i) })}>Remove</button></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Modal>

            <KplModal open={kpl} wipCode={ctx?.wip_code} onClose={() => setKpl(false)} />
            <ViewProcessingModal open={!!viewId} id={viewId} onClose={() => setViewId(null)} />
            <ViewPalletModal open={!!palletId} id={palletId} kind="pro" onClose={() => setPalletId(null)} />
        </div>
    );
}
