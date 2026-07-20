import { useEffect, useRef, useState } from 'react';
import Icon from './Icon';

/**
 * Generic searchable single-select table picker (stacks above other modals).
 *
 * props:
 *  - rows: array of objects (optionally with `_key`, `_disabled`)
 *  - columns: [{ key, label, render?(value,row), className? }]
 *  - searchKeys: keys to match against the search box
 *  - onSelect(row): fired on row click, then the modal closes
 */
export default function PickerModal({ open, onClose, title = 'Pilih', rows = [], columns = [], searchKeys = [], onSelect, empty = 'Tidak ada data.', size = 'max-w-5xl' }) {
    const [q, setQ] = useState('');
    const inputRef = useRef(null);

    useEffect(() => { if (open) { setQ(''); setTimeout(() => inputRef.current?.focus(), 50); } }, [open]);
    if (!open) return null;

    const term = q.trim().toLowerCase();
    const filtered = !term ? rows : rows.filter((r) => searchKeys.some((k) => String(r[k] ?? '').toLowerCase().includes(term)));

    return (
        <div className="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8">
            <div className={`card my-4 w-full ${size}`}>
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h3 className="text-base font-semibold text-slate-800">{title}</h3>
                    <button onClick={onClose} className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><Icon name="x" /></button>
                </div>
                <div className="px-5 py-3">
                    <div className="relative mb-3">
                        <Icon name="search" className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input ref={inputRef} className="field-input pl-9" placeholder="Cari…" value={q} onChange={(e) => setQ(e.target.value)} />
                    </div>
                    <div className="max-h-[60vh] overflow-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0"><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                {columns.map((c, i) => <th key={i} className={`whitespace-nowrap px-3 py-2 ${c.className || ''}`}>{c.label}</th>)}
                            </tr></thead>
                            <tbody>
                                {filtered.length === 0 && <tr><td colSpan={columns.length} className="px-3 py-6 text-center text-slate-400">{empty}</td></tr>}
                                {filtered.map((r, ri) => (
                                    <tr
                                        key={r._key ?? ri}
                                        className={`border-t border-slate-100 ${r._disabled ? 'cursor-not-allowed opacity-40' : 'cursor-pointer hover:bg-blue-50'}`}
                                        onClick={() => { if (!r._disabled) { onSelect(r); onClose(); } }}
                                    >
                                        {columns.map((c, ci) => <td key={ci} className={`whitespace-nowrap px-3 py-2 ${c.className || ''}`}>{c.render ? c.render(r[c.key], r) : (r[c.key] ?? '—')}</td>)}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
}
