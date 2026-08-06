import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import Icon from '../../components/Icon';
import { money } from '../procurement/common';

import { MonthRangePicker, addMonths, currentPeriod, formatPeriod } from '../../components/MonthPicker';

const monthsAgo = (n) => addMonths(currentPeriod(), -n);

/**
 * Forecast accuracy.
 *
 * MAPE says how far off the forecast was regardless of direction; BIAS keeps
 * the sign, so a small MAPE with a large positive BIAS means sales is
 * consistently over-forecasting rather than merely being imprecise. Both are
 * needed to act on the number, which is why neither is shown alone.
 */
export default function ForecastAnalysisPage() {
    const [from, setFrom] = useState(monthsAgo(6));
    const [to, setTo] = useState(currentPeriod());

    const q = useQuery({
        queryKey: ['forecast-analysis', from, to],
        queryFn: async () => (await api.get('/forecast-analysis', {
            params: { period_from: from, period_to: to },
        })).data.data,
    });

    const d = q.data;
    const items = d?.items || [];

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Analisis Akurasi Forecast</h1>
                <p className="text-xs text-slate-400">
                    Membandingkan forecast versi terakhir tiap item dengan Sales Order yang benar-benar masuk pada periode yang sama.
                </p>
            </div>

            <div className="card mb-5 flex flex-wrap items-end gap-3 p-4">
                <MonthRangePicker from={from} to={to} onChange={({ from: f, to: t }) => { setFrom(f); setTo(t); }} max={24} />
                <button className="btn btn-ghost" onClick={() => q.refetch()}><Icon name="search" /> Hitung</button>
            </div>

            {q.isError && <div className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{apiError(q.error)}</div>}

            <div className="mb-5 grid gap-4 sm:grid-cols-3">
                <Metric label="MAPE" value={d ? `${d.mape}%` : '—'} loading={q.isLoading}
                    note="Rata-rata simpangan absolut. Makin kecil makin akurat." />
                <Metric label="BIAS" value={d ? money(d.bias) : '—'} loading={q.isLoading}
                    note={d && d.bias > 0 ? 'Positif — cenderung over-forecast.' : d && d.bias < 0 ? 'Negatif — cenderung under-forecast.' : 'Netral.'} />
                <Metric label="Baris dianalisis" value={items.length} loading={q.isLoading}
                    note="Satu baris = satu item pada satu periode." />
            </div>

            <div className="card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th className="px-4 py-3">Periode</th>
                            <th className="px-4 py-3">Item</th>
                            <th className="px-4 py-3">Forecast</th>
                            <th className="px-4 py-3">Aktual SO</th>
                            <th className="px-4 py-3">Selisih</th>
                            <th className="px-4 py-3">APE</th>
                        </tr>
                    </thead>
                    <tbody>
                        {q.isLoading && <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></td></tr>}
                        {!q.isLoading && items.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-400">Tidak ada forecast pada rentang periode ini.</td></tr>
                        )}
                        {items.map((r, i) => (
                            <tr key={i} className="border-b border-slate-100">
                                <td className="px-4 py-2.5">{formatPeriod(r.period)}</td>
                                <td className="px-4 py-2.5">{r.item_code}<div className="text-xs text-slate-400">{r.item_name}</div></td>
                                <td className="px-4 py-2.5">{money(r.forecast_qty)}</td>
                                <td className="px-4 py-2.5">{money(r.actual_qty)}</td>
                                <td className={`px-4 py-2.5 ${r.diff > 0 ? 'text-amber-700' : r.diff < 0 ? 'text-sky-700' : ''}`}>
                                    {r.diff > 0 ? '+' : ''}{money(r.diff)}
                                </td>
                                <td className={`px-4 py-2.5 ${r.ape_pct > 50 ? 'font-semibold text-red-600' : ''}`}>{r.ape_pct}%</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function Metric({ label, value, note, loading }) {
    return (
        <div className="card p-4">
            <div className="text-xs uppercase tracking-wide text-slate-400">{label}</div>
            <div className="my-1 text-2xl font-semibold text-slate-800">{loading ? '…' : value}</div>
            <p className="text-xs text-slate-400">{note}</p>
        </div>
    );
}
