import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/client';
import Modal from '../../components/Modal';
import { money } from '../procurement/common';

/**
 * hh:mm:ss running clock. Prefers `elapsed` (seconds, measured by the SERVER at
 * fetch time) and ticks on from there — the shop-floor tablet's own clock may be
 * wrong or in another timezone. Falls back to comparing "HH:MM:SS" locally.
 */
export function Timer({ start, end, elapsed, className = 'font-mono text-lg text-slate-700' }) {
    const [sec, setSec] = useState(0);
    useEffect(() => setSec(0), [elapsed, start, end]);
    useEffect(() => {
        if (end) return undefined;
        const t = setInterval(() => setSec((n) => n + 1), 1000);
        return () => clearInterval(t);
    }, [end, elapsed]);

    let d = null;
    if (elapsed !== undefined && elapsed !== null && !Number.isNaN(Number(elapsed))) {
        d = Number(elapsed) + (end ? 0 : sec);
    } else if (start) {
        const toSec = (s) => { const [h, m, x] = String(s).split(':').map(Number); return h * 3600 + m * 60 + (x || 0); };
        const now = new Date();
        const nowSec = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();
        d = (end ? toSec(end) : nowSec) - toSec(start);
        if (d < 0) d += 86400;
    }
    if (d === null) return null;

    const p = (n) => String(Math.floor(n)).padStart(2, '0');
    return <span className={className}>{p(d / 3600)}:{p((d % 3600) / 60)}:{p(d % 60)}</span>;
}

/** −/+ stepper used for Qty Act columns. */
export function Stepper({ value, onCommit, min = 0, max, disabled }) {
    const [v, setV] = useState(value ?? 0);
    useEffect(() => setV(value ?? 0), [value]);
    const clamp = (n) => Math.max(min, max != null ? Math.min(max, n) : n);
    return (
        <div className="flex items-center">
            <button type="button" disabled={disabled} className="rounded-l bg-red-400 px-2 py-1 text-white hover:bg-red-500 disabled:bg-slate-300"
                onClick={() => { const n = clamp(Number(v) - 1); setV(n); onCommit(n); }}>−</button>
            <input className="w-16 border-y border-slate-300 px-2 py-1 text-center text-sm outline-none disabled:bg-slate-100"
                disabled={disabled} value={v} onChange={(e) => setV(e.target.value.replace(/\D/g, ''))}
                onBlur={() => onCommit(clamp(Number(v) || 0))}
                onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()} />
            <button type="button" disabled={disabled} className="rounded-r bg-emerald-500 px-2 py-1 text-white hover:bg-emerald-600 disabled:bg-slate-300"
                onClick={() => { const n = clamp(Number(v) + 1); setV(n); onCommit(n); }}>+</button>
        </div>
    );
}

const vcell = 'border border-slate-300 px-2 py-1.5';

/** Read-only view of a finished/running Cutting transaction. */
export function ViewCuttingModal({ open, id, onClose }) {
    const q = useQuery({
        queryKey: ['cut', 'view', id],
        queryFn: async () => (await api.get(`/mes/cutting/${id}/view`)).data.data,
        enabled: !!open && !!id,
    });
    const d = q.data;

    return (
        <Modal open={!!open} onClose={onClose} size="max-w-6xl" title="View Cutting Data"
            footer={<button className="btn btn-ghost" onClick={onClose}>Close</button>}>
            {q.isLoading && <div className="py-6 text-center text-slate-400">Memuat…</div>}
            {d && (
                <div className="space-y-4">
                    <div>
                        <div className="mb-1 text-sm font-semibold text-slate-700">Main Data</div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                    <th className={vcell}>USER</th><th className={vcell}>WIP CODE</th><th className={vcell}>CUSTOMER</th>
                                    <th className={vcell}>DATE</th><th className={vcell}>SHIFT</th><th className={vcell}>ITEM CODE</th><th className={vcell}>NO DP</th>
                                </tr></thead>
                                <tbody><tr>
                                    <td className={vcell}>{d.main.user}</td><td className={vcell}>{d.main.wip_code}</td>
                                    <td className={vcell}>{d.main.customer || '—'}</td><td className={vcell}>{(d.main.date || '').slice(0, 10)}</td>
                                    <td className={vcell}>{d.main.shift || '—'}</td><td className={vcell}>{d.main.item_code}</td><td className={vcell}>{d.main.no_dp}</td>
                                </tr></tbody>
                            </table>
                        </div>
                    </div>

                    <div className="text-sm font-semibold text-slate-700">Detail Data</div>
                    {d.machines.map((m, i) => (
                        <div key={i} className="rounded-md border border-slate-200 p-3">
                            <div className="mb-2 font-medium text-slate-700">⚙ Machine: {m.machine}</div>
                            <table className="mb-2 w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                    <th className={vcell}>START TIME</th><th className={vcell}>END TIME</th><th className={vcell}>DURATION</th><th className={vcell}>DOWNTIME</th>
                                </tr></thead>
                                <tbody><tr>
                                    <td className={vcell}>{m.start_time || '—'}</td><td className={vcell}>{m.end_time || '—'}</td>
                                    <td className={vcell}>{m.duration || '—'}</td>
                                    <td className={vcell}>{m.downtimes.length === 0 ? 'No downtime'
                                        : m.downtimes.map((x, j) => <div key={j}>{x.category} · {x.duration}{x.note ? ` (${x.note})` : ''}</div>)}</td>
                                </tr></tbody>
                            </table>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                        <th className={vcell}>SERIAL ID</th><th className={vcell}>QTY</th><th className={vcell}>QTY REQ</th>
                                        <th className={vcell}>LENGTH REQ DP</th><th className={vcell}>LENGTH WH</th><th className={vcell}>LENGTH REM</th>
                                        <th className={vcell}>FINISH</th><th className={vcell}>ABNORMAL</th>
                                    </tr></thead>
                                    <tbody>
                                        {m.serials.length === 0 && <tr><td className={vcell} colSpan={8}>Tidak ada serial.</td></tr>}
                                        {m.serials.map((s, j) => (
                                            <tr key={j} className={s.finish ? 'bg-emerald-50' : ''}>
                                                <td className={vcell}>{s.serial_id}</td><td className={vcell}>{money(s.qty)}</td><td className={vcell}>{money(s.qty_req)}</td>
                                                <td className={vcell}>{money(s.length_req_dp)}</td><td className={vcell}>{money(s.length_wh)}</td><td className={vcell}>{money(s.length_rem)}</td>
                                                <td className={vcell}>{s.finish
                                                    ? <span className="rounded bg-emerald-600 px-2 py-0.5 text-xs text-white">Yes</span>
                                                    : <span className="rounded bg-slate-300 px-2 py-0.5 text-xs text-slate-700">No</span>}</td>
                                                <td className={vcell}>{s.abnormal ? <span className="font-semibold text-red-600">{money(s.abnormal)} pcs</span> : '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </Modal>
    );
}

/** Read-only view of a Processing transaction. */
export function ViewProcessingModal({ open, id, onClose }) {
    const q = useQuery({
        queryKey: ['pro', 'view', id],
        queryFn: async () => (await api.get(`/mes/processing/${id}/view`)).data.data,
        enabled: !!open && !!id,
    });
    const d = q.data;

    return (
        <Modal open={!!open} onClose={onClose} size="max-w-6xl" title="View Processing Input Data"
            footer={<button className="btn btn-ghost" onClick={onClose}>Close</button>}>
            {q.isLoading && <div className="py-6 text-center text-slate-400">Memuat…</div>}
            {d && (
                <div className="space-y-4">
                    <div>
                        <div className="mb-1 text-sm font-semibold text-slate-700">Main Data</div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                    <th className={vcell}>WIP Code</th><th className={vcell}>Date</th><th className={vcell}>FG Code</th>
                                    <th className={vcell}>Customer</th><th className={vcell}>Qty WIP</th><th className={vcell}>Process</th>
                                    <th className={vcell}>Qty Half</th><th className={vcell}>Qty Full</th>
                                </tr></thead>
                                <tbody><tr>
                                    <td className={vcell}>{d.main.wip_code}</td><td className={vcell}>{(d.main.date || '').slice(0, 10)}</td>
                                    <td className={vcell}>{d.main.fg_code}</td><td className={vcell}>{d.main.customer || '—'}</td>
                                    <td className={vcell}>{money(d.main.qty_wip)}</td><td className={vcell}>{d.main.process}</td>
                                    <td className={vcell}>{money(d.main.qty_half)}</td><td className={vcell}>{money(d.main.qty_full)}</td>
                                </tr></tbody>
                            </table>
                        </div>
                    </div>

                    <div className="text-sm font-semibold text-slate-700">Detail Data</div>
                    {d.machines.map((m, i) => (
                        <div key={i} className="rounded-md border border-slate-200 p-3">
                            <table className="mb-2 w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                    <th className={vcell}>Machine Code</th><th className={vcell}>Operator</th>
                                    <th className={vcell}>Start Time</th><th className={vcell}>Finish Time</th><th className={vcell}>Duration</th><th className={vcell}>Downtime</th>
                                </tr></thead>
                                <tbody><tr>
                                    <td className={vcell}>{m.machine}</td><td className={vcell}>{m.operator || '—'}</td>
                                    <td className={vcell}>{m.start_time || '—'}</td><td className={vcell}>{m.end_time || '—'}</td><td className={vcell}>{m.duration || '—'}</td>
                                    <td className={vcell}>{m.downtimes.length === 0 ? 'No downtime' : m.downtimes.map((x, j) => <div key={j}>{x.category}</div>)}</td>
                                </tr></tbody>
                            </table>
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                    <th className={vcell}>From Pallet Code</th><th className={vcell}>Qty Half</th><th className={vcell}>Qty Finish</th>
                                </tr></thead>
                                <tbody>
                                    {m.pallets.length === 0 && <tr><td className={vcell} colSpan={3}>Tidak ada pallet.</td></tr>}
                                    {m.pallets.map((p, j) => (
                                        <tr key={j}><td className={vcell}>{p.pallet_code}</td><td className={vcell}>{money(p.qty_half)}</td><td className={vcell}>{money(p.qty_finish)}</td></tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ))}
                </div>
            )}
        </Modal>
    );
}

/** Pallets produced by a transaction, each printable. */
export function ViewPalletModal({ open, id, kind, onClose }) {
    const q = useQuery({
        queryKey: [kind, 'view', id],
        queryFn: async () => (await api.get(`/mes/${kind === 'cut' ? 'cutting' : 'processing'}/${id}/view`)).data.data,
        enabled: !!open && !!id,
    });
    const pallets = q.data?.pallets || [];

    return (
        <Modal open={!!open} onClose={onClose} title="View Pallet Data"
            footer={<button className="btn btn-ghost" onClick={onClose}>Close</button>}>
            {q.isLoading && <div className="py-6 text-center text-slate-400">Memuat…</div>}
            {!q.isLoading && (
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                        <th className={vcell}>NO</th><th className={vcell}>PALLET CODE</th><th className={vcell}>QTY</th><th className={vcell}>ACTION</th>
                    </tr></thead>
                    <tbody>
                        {pallets.length === 0 && <tr><td className={vcell} colSpan={4}>Belum ada pallet dibuat.</td></tr>}
                        {pallets.map((p, i) => (
                            <tr key={p.id}>
                                <td className={vcell}>{i + 1}</td>
                                <td className={vcell}>{p.code}</td>
                                <td className={vcell}>{money(p.qty)}</td>
                                <td className={vcell}>
                                    <button className="rounded bg-teal-500 px-3 py-1 text-xs font-medium text-white"
                                        onClick={() => alert(`Label pallet ${p.code} (${p.qty} pcs)\n\n(cetak fisik belum tersedia)`)}>Print</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </Modal>
    );
}

/**
 * Kartu Pengawasan Lot (KPL) — the lot's identity plus how far it has flowed
 * through the routing: planned (Denpyou) vs transacted vs NG per process.
 */
export function KplModal({ open, wipCode, onClose }) {
    const q = useQuery({
        queryKey: ['kpl', wipCode],
        queryFn: async () => (await api.get(`/mes/kpl/${encodeURIComponent(wipCode)}`)).data.data,
        enabled: !!open && !!wipCode,
    });
    const d = q.data;
    const cell = 'border border-slate-300 px-2 py-1.5';

    return (
        <Modal open={!!open} onClose={onClose} wide title="Kartu Pengawasan Lot"
            footer={<button className="btn btn-ghost" onClick={onClose}>Tutup</button>}>
            {q.isLoading && <div className="py-6 text-center text-slate-400">Memuat…</div>}
            {q.isError && <div className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">Lot tidak ditemukan.</div>}
            {d && (
                <div className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div><label className="field-label">Nomor Lot</label><input className="field-input bg-slate-50" value={d.nomor_lot || ''} disabled /></div>
                        <div><label className="field-label">QR Code</label><input className="field-input bg-slate-50" value={d.qr_code || ''} disabled /></div>
                        <div><label className="field-label">Qty</label><input className="field-input bg-slate-50" value={d.qty ?? ''} disabled /></div>
                        <div><label className="field-label">Nomor Barang</label><input className="field-input bg-slate-50" value={d.nomor_barang || ''} disabled /></div>
                        <div className="sm:col-span-2"><label className="field-label">Nama Barang</label><input className="field-input bg-slate-50" value={d.nama_barang || ''} disabled /></div>
                    </div>
                    <div>
                        <div className="mb-1 text-sm font-semibold text-slate-700">Flow Process</div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                    <th className={cell}>OP</th><th className={cell}>Flow Process</th><th className={cell}>Sequence</th>
                                    <th className={cell}>Qty (Denpyou)</th><th className={cell}>Qty (Transaksi)</th><th className={cell}>Qty (NG)</th>
                                </tr></thead>
                                <tbody>
                                    {(d.flow || []).map((f) => (
                                        <tr key={f.sequence}>
                                            <td className={`${cell} font-semibold`}>{f.op}</td>
                                            <td className={cell}>{f.process}</td>
                                            <td className={cell}>{f.sequence}</td>
                                            <td className={cell}>{money(f.qty_denpyou)}</td>
                                            <td className={cell}>{money(f.qty_transaksi)}</td>
                                            <td className={cell}>{money(f.qty_ng)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}
        </Modal>
    );
}
