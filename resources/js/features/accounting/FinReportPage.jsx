import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import Icon from '../../components/Icon';
import { money } from '../procurement/common';

import MonthPicker, { currentPeriod } from '../../components/MonthPicker';
const TABS = [
    ['balance-sheet', 'Neraca'],
    ['income-statement', 'Laba Rugi'],
    ['ap-aging', 'Aging Hutang'],
    ['ar-aging', 'Aging Piutang'],
];

/** Financial statements from the posted ledger. */
export default function FinReportPage() {
    const [tab, setTab] = useState('balance-sheet');
    const [period, setPeriod] = useState(currentPeriod());
    const [asOf, setAsOf] = useState(new Date().toISOString().slice(0, 10));

    const isAging = tab.endsWith('aging');
    const q = useQuery({
        queryKey: ['fin-reports', tab, period, asOf],
        queryFn: async () => (await api.get(`/reports/${tab}`, {
            params: isAging ? { as_of: asOf } : { period },
        })).data.data,
    });

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Laporan Keuangan</h1>
                <p className="text-xs text-slate-400">Disusun dari jurnal berstatus POSTED. Neraca bersifat kumulatif, laba rugi per periode.</p>
            </div>

            <div className="mb-4 flex gap-1 border-b border-slate-200">
                {TABS.map(([k, label]) => (
                    <button key={k} onClick={() => setTab(k)}
                        className={`-mb-px border-b-2 px-4 py-2 text-sm ${tab === k ? 'border-blue-600 font-medium text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>
                        {label}
                    </button>
                ))}
            </div>

            <div className="card mb-4 flex flex-wrap items-end gap-3 p-4">
                {isAging ? (
                    <div>
                        <label className="field-label">Per tanggal</label>
                        <input type="date" className="field-input w-44" value={asOf} onChange={(e) => setAsOf(e.target.value)} />
                    </div>
                ) : (
                    <div>
                        <label className="field-label">Periode</label>
                        <MonthPicker value={period} onChange={setPeriod} className="w-44" />
                    </div>
                )}
                <button className="btn btn-ghost" onClick={() => q.refetch()}><Icon name="search" /> Tampilkan</button>
            </div>

            {q.isLoading && <div className="py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></div>}
            {q.isError && <div className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{apiError(q.error)}</div>}

            {q.data && tab === 'balance-sheet' && <BalanceSheet d={q.data} />}
            {q.data && tab === 'income-statement' && <IncomeStatement d={q.data} />}
            {q.data && isAging && <Aging d={q.data} />}
        </div>
    );
}

function Section({ title, rows, total }) {
    return (
        <div className="card p-4">
            <h3 className="mb-2 font-semibold text-slate-800">{title}</h3>
            <table className="w-full text-sm">
                <tbody>
                    {rows.length === 0 && <tr><td className="py-2 text-slate-400">Tidak ada saldo.</td></tr>}
                    {rows.map((r, i) => (
                        <tr key={i} className="border-b border-dashed border-slate-100">
                            <td className="py-1.5 text-slate-600">{r.code !== '—' && <span className="text-slate-400">{r.code} </span>}{r.name}</td>
                            <td className="py-1.5 text-right font-medium">{money(r.amount)}</td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t-2 border-slate-300">
                        <td className="py-2 font-semibold">Total</td>
                        <td className="py-2 text-right font-semibold">{money(total)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

function BalanceSheet({ d }) {
    return (
        <div className="space-y-4">
            {!d.balanced && (
                <div className="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    Neraca tidak seimbang. Selisih {money(d.difference)} — periksa jurnal yang belum lengkap.
                </div>
            )}
            <div className="grid gap-4 lg:grid-cols-2">
                <Section title="Aset" rows={d.assets} total={d.total_assets} />
                <div className="space-y-4">
                    <Section title="Kewajiban" rows={d.liabilities} total={d.total_liabilities} />
                    <Section title="Ekuitas" rows={d.equity} total={d.total_equity} />
                </div>
            </div>
        </div>
    );
}

function IncomeStatement({ d }) {
    return (
        <div className="max-w-3xl space-y-4">
            <Section title="Pendapatan" rows={d.revenue} total={d.total_revenue} />
            <Section title="Harga Pokok Penjualan" rows={d.cogs} total={d.total_cogs} />
            <div className="card flex items-center justify-between p-4">
                <span className="font-semibold text-slate-700">Laba Kotor</span>
                <span className="text-lg font-semibold">{money(d.gross_profit)}</span>
            </div>
            <Section title="Beban Usaha" rows={d.expense} total={d.total_expense} />
            <div className={`card flex items-center justify-between p-4 ${d.net_income < 0 ? 'bg-red-50' : 'bg-emerald-50'}`}>
                <span className="font-semibold text-slate-700">Laba (Rugi) Bersih</span>
                <span className={`text-xl font-semibold ${d.net_income < 0 ? 'text-red-700' : 'text-emerald-700'}`}>{money(d.net_income)}</span>
            </div>
        </div>
    );
}

const BUCKETS = [
    ['current', 'Belum jatuh tempo'],
    ['d1_30', '1–30 hari'],
    ['d31_60', '31–60 hari'],
    ['d61_90', '61–90 hari'],
    ['over_90', '> 90 hari'],
];

function Aging({ d }) {
    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-5">
                {BUCKETS.map(([k, label]) => (
                    <div key={k} className={`card p-3 ${k === 'over_90' && d.buckets[k] > 0 ? 'border-red-300' : ''}`}>
                        <div className="text-xs text-slate-400">{label}</div>
                        <div className="text-lg font-semibold text-slate-800">{money(d.buckets[k])}</div>
                    </div>
                ))}
            </div>

            <div className="card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th className="px-4 py-3">Dokumen</th>
                            <th className="px-4 py-3">Pihak</th>
                            <th className="px-4 py-3">Jatuh Tempo</th>
                            <th className="px-4 py-3">Total</th>
                            <th className="px-4 py-3">Dibayar</th>
                            <th className="px-4 py-3">Sisa</th>
                            <th className="px-4 py-3">Terlambat</th>
                        </tr>
                    </thead>
                    <tbody>
                        {d.items.length === 0 && <tr><td colSpan={7} className="px-4 py-8 text-center text-slate-400">Tidak ada saldo terbuka.</td></tr>}
                        {d.items.map((r) => (
                            <tr key={r.id} className="border-b border-slate-100">
                                <td className="px-4 py-2">{r.code}</td>
                                <td className="px-4 py-2">{r.party || '—'}</td>
                                <td className="px-4 py-2">{(r.due_date || r.date || '').slice(0, 10)}</td>
                                <td className="px-4 py-2">{money(r.total)}</td>
                                <td className="px-4 py-2">{money(r.paid)}</td>
                                <td className="px-4 py-2 font-medium">{money(r.outstanding)}</td>
                                <td className={`px-4 py-2 ${r.days_late > 90 ? 'font-semibold text-red-600' : r.days_late > 0 ? 'text-amber-700' : 'text-slate-400'}`}>
                                    {r.days_late > 0 ? `${r.days_late} hari` : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
