import { useEffect, useState } from 'react';
import Icon from './Icon';
import { money } from '../features/procurement/common';

/**
 * Reusable "Choose GR (Group by Item Code)" modal — searchable list of GRs,
 * each expandable to its item-detail table; multi-select via onToggle.
 * Used by WMS Incoming and AP Invoice.
 */
export default function ChooseGrModal({ open, onClose, grs, itemsById = {}, chosenIds = [], onToggle }) {
    const [q, setQ] = useState('');
    const [expanded, setExpanded] = useState({});
    useEffect(() => { if (open) { setQ(''); setExpanded({}); } }, [open]);
    if (!open) return null;

    const term = q.trim().toLowerCase();
    const match = (g) => !term
        || (g.code || '').toLowerCase().includes(term)
        || (g.po_no || '').toLowerCase().includes(term)
        || (g.ven?.company_n || '').toLowerCase().includes(term)
        || (g.detail || []).some((d) => (d.item?.code || '').toLowerCase().includes(term));
    const list = (grs || []).filter(match);

    return (
        <div className="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8">
            <div className="card my-4 w-full max-w-4xl">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h3 className="text-base font-semibold text-slate-800">Choose GR (Group by Item Code)</h3>
                    <button onClick={onClose} className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><Icon name="x" /></button>
                </div>
                <div className="px-5 py-3">
                    <div className="relative mb-3">
                        <Icon name="search" className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input autoFocus className="field-input pl-9" placeholder="Cari GR Code / PO No / Vendor / Item Code" value={q} onChange={(e) => setQ(e.target.value)} />
                    </div>
                    <div className="max-h-[60vh] space-y-2 overflow-auto">
                        {list.length === 0 && <p className="py-6 text-center text-sm text-slate-400">Tidak ada GR.</p>}
                        {list.map((g) => {
                            const isOpen = expanded[g.id];
                            const chosen = chosenIds.includes(g.id);
                            return (
                                <div key={g.id} className="rounded-md border border-slate-200">
                                    <div className="flex items-center gap-3 px-3 py-2">
                                        <span className="flex h-6 w-6 items-center justify-center rounded-full bg-sky-100 text-xs font-semibold text-sky-700">{(g.detail || []).length}</span>
                                        <div className="min-w-0 flex-1 text-sm">
                                            <span className="font-semibold text-slate-800">{g.code}</span>
                                            <span className="text-slate-400"> · PO: </span><span className="text-sky-600">{g.po_no}</span>
                                            <span className="text-slate-400"> · {g.ven?.company_n || '—'} · {g.date?.slice(0, 10)}</span>
                                        </div>
                                        <button className={`btn ${chosen ? 'btn-ghost' : 'btn-primary'} px-3 py-1 text-xs`} onClick={() => onToggle(g)}>
                                            {chosen ? 'Terpilih ✓' : 'Choose'}
                                        </button>
                                        <button className="rounded p-1 text-slate-400 hover:bg-slate-100" onClick={() => setExpanded((e) => ({ ...e, [g.id]: !e[g.id] }))}>
                                            <Icon name="chevron" className={`h-4 w-4 transition ${isOpen ? 'rotate-180' : ''}`} />
                                        </button>
                                    </div>
                                    {isOpen && (
                                        <div className="overflow-x-auto border-t border-slate-100">
                                            <table className="w-full text-xs">
                                                <thead><tr className="bg-slate-50 text-left font-semibold text-slate-500">
                                                    {['Item Code', 'Part Name', 'OD', 'ID', 'Thick', 'Qty (item)', 'Total Length'].map((h, i) => <th key={i} className="px-3 py-1.5">{h}</th>)}
                                                </tr></thead>
                                                <tbody>
                                                    {(g.detail || []).map((d) => {
                                                        const sp = itemsById[d.item_id] || {};
                                                        return (
                                                            <tr key={d.id} className="border-t border-slate-50">
                                                                <td className="px-3 py-1">{d.item?.code}</td>
                                                                <td className="px-3 py-1">{d.item?.part_name}</td>
                                                                <td className="px-3 py-1">{sp.o_d ?? '—'}</td>
                                                                <td className="px-3 py-1">{sp.i_d ?? '—'}</td>
                                                                <td className="px-3 py-1">{sp.thick ?? '—'}</td>
                                                                <td className="px-3 py-1">{d.qty}</td>
                                                                <td className="px-3 py-1">{money((Number(d.qty) || 0) * (Number(d.length) || 0))}</td>
                                                            </tr>
                                                        );
                                                    })}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
                <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button className="btn btn-ghost" onClick={onClose}>Close</button>
                </div>
            </div>
        </div>
    );
}
