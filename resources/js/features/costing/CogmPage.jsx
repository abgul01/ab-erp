import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import { money } from '../procurement/common';

import MonthPicker, { MonthRangePicker, currentPeriod, formatPeriod, periodRange } from '../../components/MonthPicker';

/**
 * COGM — standard cost of goods manufactured per Work Order for a period:
 * material + labor + FOH + subcontract − scrap recovery, and the unit cost.
 */
export default function CogmPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    // The table always shows one month; the recalculation may span several,
    // which is what you want after correcting a rate that affected a quarter.
    const [period, setPeriod] = useState(currentPeriod());
    const [range, setRange] = useState({ from: currentPeriod(), to: currentPeriod() });
    const [error, setError] = useState('');
    const runPeriods = periodRange(range.from, range.to);

    const data = useQuery({ queryKey: ['cogm', period], queryFn: async () => (await api.get('/cogm', { params: { period } })).data.data });
    const run = useMutation({
        mutationFn: async () => (await api.post('/cogm/run', { periods: runPeriods })).data.data,
        onSuccess: (d) => { qc.invalidateQueries({ queryKey: ['cogm'] }); setError(''); alert(d.message); },
        onError: (e) => setError(apiError(e)),
    });

    const rows = data.data?.rows || [];
    const tot = rows.reduce((a, r) => ({
        material: a.material + r.material_cost, labor: a.labor + r.labor_cost, foh: a.foh + r.foh_cost,
        subcont: a.subcont + r.subcont_cost, scrap: a.scrap + r.scrap_recovery, total: a.total + r.total,
    }), { material: 0, labor: 0, foh: 0, subcont: 0, scrap: 0, total: 0 });

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">COGM — Biaya Produksi per Work Order</h1>
                    <p className="text-xs text-slate-400">Biaya standar: material (BOM) + tenaga kerja + overhead + subcont − nilai scrap. Unit cost = total ÷ qty WO.</p>
                </div>
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="field-label">Tampilkan bulan</label>
                        <MonthPicker value={period} onChange={setPeriod} className="w-40" />
                    </div>
                    {can('cogm', 'create') && (
                        <>
                            <div className="border-l border-slate-200 pl-3">
                                <MonthRangePicker from={range.from} to={range.to} onChange={setRange} disabled={run.isPending} />
                            </div>
                            <button className="btn btn-primary" disabled={run.isPending || runPeriods.length === 0}
                                onClick={() => { setError(''); run.mutate(); }}>
                                {run.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="calculator" />}
                                {runPeriods.length > 1 ? `Hitung ${runPeriods.length} Bulan` : 'Hitung COGM'}
                            </button>
                        </>
                    )}
                </div>
            </div>
            {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

            <div className="card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-3 py-2">WO</th><th className="px-3 py-2">Item</th><th className="px-3 py-2 text-right">Qty</th>
                                <th className="px-3 py-2 text-right">Material</th><th className="px-3 py-2 text-right">Labor</th><th className="px-3 py-2 text-right">FOH</th>
                                <th className="px-3 py-2 text-right">Subcont</th><th className="px-3 py-2 text-right">Scrap</th>
                                <th className="px-3 py-2 text-right">Total</th><th className="px-3 py-2 text-right">Unit Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.isLoading && <tr><td colSpan={10} className="px-3 py-6 text-center text-slate-400">Memuat…</td></tr>}
                            {!data.isLoading && rows.length === 0 && <tr><td colSpan={10} className="px-3 py-6 text-center text-slate-400">Belum ada data. Klik <b>Hitung COGM</b> untuk periode ini.</td></tr>}
                            {rows.map((r) => (
                                <tr key={r.id} className="border-t border-slate-100">
                                    <td className="px-3 py-1.5 font-medium">{r.wo_code}</td>
                                    <td className="px-3 py-1.5">{r.item_code} <span className="text-slate-400">{r.part_name}</span></td>
                                    <td className="px-3 py-1.5 text-right">{r.qty}</td>
                                    <td className="px-3 py-1.5 text-right">{money(r.material_cost)}</td>
                                    <td className="px-3 py-1.5 text-right">{money(r.labor_cost)}</td>
                                    <td className="px-3 py-1.5 text-right">{money(r.foh_cost)}</td>
                                    <td className="px-3 py-1.5 text-right">{money(r.subcont_cost)}</td>
                                    <td className="px-3 py-1.5 text-right text-amber-700">{r.scrap_recovery ? `(${money(r.scrap_recovery)})` : '—'}</td>
                                    <td className="px-3 py-1.5 text-right font-semibold text-slate-800">{money(r.total)}</td>
                                    <td className="px-3 py-1.5 text-right font-medium">{money(r.unit_cost)}</td>
                                </tr>
                            ))}
                        </tbody>
                        {rows.length > 0 && (
                            <tfoot>
                                <tr className="border-t-2 border-slate-200 bg-slate-50 font-semibold text-slate-700">
                                    <td className="px-3 py-2" colSpan={3}>Total {rows.length} WO</td>
                                    <td className="px-3 py-2 text-right">{money(tot.material)}</td>
                                    <td className="px-3 py-2 text-right">{money(tot.labor)}</td>
                                    <td className="px-3 py-2 text-right">{money(tot.foh)}</td>
                                    <td className="px-3 py-2 text-right">{money(tot.subcont)}</td>
                                    <td className="px-3 py-2 text-right">{money(tot.scrap)}</td>
                                    <td className="px-3 py-2 text-right">Rp {money(tot.total)}</td>
                                    <td className="px-3 py-2"></td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>
        </div>
    );
}
