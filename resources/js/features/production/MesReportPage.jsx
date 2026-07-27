import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/client';
import { money } from '../procurement/common';

const thisPeriod = () => { const d = new Date(); return `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}`; };

/**
 * Actual vs plan per machine per day: the MPS lot against what the floor really
 * cut and processed, plus the NG decided on that machine that day.
 */
export default function MesReportPage() {
    const [period, setPeriod] = useState(thisPeriod());
    const rows = useQuery({
        queryKey: ['mes-report', period],
        queryFn: async () => (await api.get('/mes/vs-mps', { params: { period } })).data.data,
    });
    const cell = 'border border-slate-300 px-2 py-1.5';
    const data = rows.data || [];
    const tot = data.reduce((a, r) => ({ plan: a.plan + r.plan, actual: a.actual + r.actual, ng: a.ng + r.ng }), { plan: 0, actual: 0, ng: 0 });

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold text-slate-800">Aktual vs Rencana (per mesin / hari)</h1>
                <div className="flex items-center gap-2">
                    <label className="text-sm text-slate-500">Periode (YYYYMM)</label>
                    <input className="field-input w-32" value={period} onChange={(e) => setPeriod(e.target.value.replace(/\D/g, '').slice(0, 6))} />
                </div>
            </div>
            <p className="mb-3 text-xs text-slate-400">Rencana = lot MPS terjadwal. Aktual = hasil nyata dari lantai: potongan (cutting) + hasil proses (processing). NG = yang sudah diputuskan scrap.</p>

            <div className="mb-3 flex flex-wrap gap-3 text-sm">
                <span className="rounded-md bg-slate-100 px-3 py-1">Total rencana <b>{money(tot.plan)}</b></span>
                <span className="rounded-md bg-emerald-50 px-3 py-1 text-emerald-800">Total aktual <b>{money(tot.actual)}</b></span>
                <span className="rounded-md bg-red-50 px-3 py-1 text-red-700">Total NG <b>{money(tot.ng)}</b></span>
                <span className={`rounded-md px-3 py-1 ${tot.actual - tot.plan < 0 ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800'}`}>
                    Selisih <b>{tot.actual - tot.plan > 0 ? '+' : ''}{money(tot.actual - tot.plan)}</b>
                </span>
            </div>

            <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                        <th className={cell}>Tanggal</th><th className={cell}>Mesin</th>
                        <th className={cell}>Rencana (MPS)</th><th className={cell}>Aktual Cutting</th><th className={cell}>Aktual Processing</th>
                        <th className={cell}>Total Aktual</th><th className={cell}>NG</th><th className={cell}>Selisih</th>
                    </tr></thead>
                    <tbody>
                        {rows.isLoading && <tr><td className={cell} colSpan={8}>Memuat…</td></tr>}
                        {!rows.isLoading && data.length === 0 && <tr><td className={cell} colSpan={8}>Tidak ada data pada periode ini.</td></tr>}
                        {data.map((r, i) => (
                            <tr key={i} className="hover:bg-slate-50">
                                <td className={cell}>{r.date}</td>
                                <td className={`${cell} font-medium text-slate-700`}>{r.machine || '—'}</td>
                                <td className={cell}>{money(r.plan)}</td>
                                <td className={cell}>{r.cut ? money(r.cut) : '—'}</td>
                                <td className={cell}>{r.process ? money(r.process) : '—'}</td>
                                <td className={`${cell} font-semibold text-emerald-700`}>{money(r.actual)}</td>
                                <td className={`${cell} text-red-600`}>{r.ng ? money(r.ng) : '—'}</td>
                                <td className={`${cell} font-medium ${r.diff < 0 ? 'text-red-600' : 'text-emerald-700'}`}>{r.diff > 0 ? '+' : ''}{money(r.diff)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
