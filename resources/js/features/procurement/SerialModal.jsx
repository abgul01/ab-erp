import { useEffect, useState } from 'react';
import Icon from '../../components/Icon';
import { CellInput, money } from './common';

const blank = (length, weight) => ({ serial_id: '', millsheet: '', qty: 1, length: length ?? '', weight: weight ?? '', status: 'OK' });

/**
 * Serial generator modal (dual-UoM RM bars). Left: editable serial rows.
 * Right: bulk generators — serial number (prefix + running number, zero-padded)
 * and mill sheet (prefix applied to a row range). Stacks above the GR modal.
 */
export default function SerialModal({ open, onClose, itemLabel, qty, defaultLength, defaultWeight, initial, onSave }) {
    const [rows, setRows] = useState([]);
    const [prefix, setPrefix] = useState('');
    const [orderFrom, setOrderFrom] = useState(1);
    const [orderTo, setOrderTo] = useState(1);
    const [firstSerial, setFirstSerial] = useState(1);
    const [digits, setDigits] = useState(3);
    const [msPrefix, setMsPrefix] = useState('MS-');
    const [msFrom, setMsFrom] = useState(1);
    const [msTo, setMsTo] = useState(1);

    useEffect(() => {
        if (!open) return;
        const n = Number(qty) || 0;
        // Rows appear only when generated (or when editing an existing GR) —
        // do NOT pre-populate blank rows.
        setRows((initial && initial.length) ? initial.map((s) => ({ ...s })) : []);
        setOrderTo(n || 1);
        setMsTo(n || 1);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    if (!open) return null;

    const total = rows.reduce((a, r) => a + (Number(r.qty) || 0), 0);
    const wtot = rows.reduce((a, r) => a + (Number(r.weight) || 0), 0);

    const grow = (arr, n) => { const r = [...arr]; while (r.length < n) r.push(blank(defaultLength, defaultWeight)); return r; };
    const setRow = (i, k, v) => setRows((prev) => prev.map((r, j) => (j === i ? { ...r, [k]: v } : r)));
    const delRow = (i) => setRows((prev) => prev.filter((_, j) => j !== i));

    const genSerial = () => {
        const from = Number(orderFrom), to = Number(orderTo), first = Number(firstSerial), d = Number(digits);
        if (!from || !to || to < from) return;
        setRows((prev) => {
            const r = grow(prev, to);
            for (let k = from; k <= to; k++) {
                const num = first + (k - from);
                r[k - 1] = { ...r[k - 1], serial_id: `${prefix}${String(num).padStart(d, '0')}`, qty: r[k - 1].qty || 1, length: r[k - 1].length || defaultLength || '', weight: r[k - 1].weight || defaultWeight || '', status: r[k - 1].status || 'OK' };
            }
            return r;
        });
    };
    const genMillsheet = () => {
        const from = Number(msFrom), to = Number(msTo);
        if (!from || !to || to < from) return;
        // Only fills existing (already-generated) serial rows — never creates blanks.
        setRows((prev) => prev.map((r, j) => ((j + 1) >= from && (j + 1) <= to ? { ...r, millsheet: msPrefix } : r)));
    };

    const save = () => { onSave(rows.filter((r) => (r.serial_id || '').trim() !== '')); onClose(); };

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8">
            <div className="card my-4 w-full max-w-[90rem]">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h3 className="text-base font-semibold text-slate-800">Serial Item {itemLabel} — Qty: {qty || 0} <span className="ml-2 text-xs font-normal text-slate-400">(terisi {total} pcs / {money(wtot)} kg)</span></h3>
                    <button onClick={onClose} className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><Icon name="x" /></button>
                </div>

                <div className="grid grid-cols-1 gap-4 px-5 py-4 lg:grid-cols-[1fr_340px]">
                    {/* Serial rows */}
                    <div className="max-h-[60vh] overflow-y-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0"><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">#</th>
                                <th className="px-2 py-2">Serial Number</th>
                                <th className="px-2 py-2">Mill Sheet</th>
                                <th className="px-2 py-2">Qty</th>
                                <th className="px-2 py-2">Length</th>
                                <th className="px-2 py-2">Weight</th>
                                <th className="px-2 py-2"></th>
                            </tr></thead>
                            <tbody>
                                {rows.length === 0 && <tr><td colSpan={7} className="px-2 py-6 text-center text-slate-400">Belum ada serial. Gunakan generator di kanan.</td></tr>}
                                {rows.map((r, i) => (
                                    <tr key={i} className="border-t border-slate-100">
                                        <td className="px-2 py-1 text-xs text-slate-400">{i + 1}</td>
                                        <td className="px-2 py-1 min-w-[220px]"><CellInput value={r.serial_id} onChange={(v) => setRow(i, 'serial_id', v)} /></td>
                                        <td className="px-2 py-1 min-w-[200px]"><CellInput value={r.millsheet} onChange={(v) => setRow(i, 'millsheet', v)} /></td>
                                        <td className="px-2 py-1"><div className="w-20"><CellInput type="number" value={r.qty} onChange={(v) => setRow(i, 'qty', v)} /></div></td>
                                        <td className="px-2 py-1"><div className="w-28"><CellInput type="number" value={r.length} onChange={(v) => setRow(i, 'length', v)} /></div></td>
                                        <td className="px-2 py-1"><div className="w-28"><CellInput type="number" step="0.01" value={r.weight} onChange={(v) => setRow(i, 'weight', v)} /></div></td>
                                        <td className="px-2 py-1"><button type="button" className="rounded bg-red-500 px-2 py-1 text-xs text-white hover:bg-red-600" onClick={() => delRow(i)}>✕</button></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Generators */}
                    <div className="space-y-3">
                        <div className="rounded-md border border-slate-200 p-3">
                            <label className="field-label">Prefix</label>
                            <input className="field-input" value={prefix} onChange={(e) => setPrefix(e.target.value)} placeholder="PO2509077-1A235-" />
                            <div className="mt-2 grid grid-cols-2 gap-2">
                                <div><label className="field-label">Order From</label><input type="number" className="field-input" value={orderFrom} onChange={(e) => setOrderFrom(e.target.value)} /></div>
                                <div><label className="field-label">Order To <span className="text-xs text-slate-400">Max {qty || 0}</span></label><input type="number" className="field-input" value={orderTo} onChange={(e) => setOrderTo(e.target.value)} /></div>
                            </div>
                            <div className="mt-2"><label className="field-label">First Serial</label><input type="number" className="field-input" value={firstSerial} onChange={(e) => setFirstSerial(e.target.value)} /></div>
                            <div className="mt-2"><label className="field-label">Digit Number</label>
                                <select className="field-input" value={digits} onChange={(e) => setDigits(e.target.value)}>
                                    <option value={2}>2 digit (01-99)</option>
                                    <option value={3}>3 digit (001-999)</option>
                                    <option value={4}>4 digit (0001-9999)</option>
                                </select>
                            </div>
                            <button type="button" className="btn mt-3 w-full bg-emerald-600 text-white hover:bg-emerald-700" onClick={genSerial}>Generate Serial</button>
                        </div>

                        <div className="rounded-md border border-slate-200 p-3">
                            <div className="mb-1 text-sm font-semibold text-slate-700">Mill Sheet</div>
                            <label className="field-label">Prefix</label>
                            <input className="field-input" value={msPrefix} onChange={(e) => setMsPrefix(e.target.value)} placeholder="MS-" />
                            <div className="mt-2 grid grid-cols-2 gap-2">
                                <div><label className="field-label">From</label><input type="number" className="field-input" value={msFrom} onChange={(e) => setMsFrom(e.target.value)} /></div>
                                <div><label className="field-label">To</label><input type="number" className="field-input" value={msTo} onChange={(e) => setMsTo(e.target.value)} /></div>
                            </div>
                            <button type="button" className="btn mt-3 w-full bg-indigo-600 text-white hover:bg-indigo-700" onClick={genMillsheet}>Generate</button>
                            <p className="mt-2 text-xs text-slate-400">Mill sheet = Prefix. From–To menentukan baris mana yang diisi dengan prefix yang sama.</p>
                        </div>
                    </div>
                </div>

                <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button className="btn btn-ghost" onClick={onClose}>Close</button>
                    <button className="btn btn-primary" onClick={save}><Icon name="save" /> Save Serial</button>
                </div>
            </div>
        </div>
    );
}
