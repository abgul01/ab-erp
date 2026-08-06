import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/client';
import Icon from '../../components/Icon';
import { money } from '../procurement/common';

const period = () => {
    const d = new Date();
    return `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}`;
};

const BASIS_LABEL = {
    LANDED: 'landed cost',
    PO: 'harga PO',
    NONE: 'belum ada biaya',
};

function Card({ label, value, hint, tone = 'slate' }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</p>
            <p className={`mt-1 text-xl font-semibold text-${tone}-800`}>{money(value)}</p>
            {hint && <p className="mt-0.5 text-xs text-slate-400">{hint}</p>}
        </div>
    );
}

function Table({ head, rows, empty, render }) {
    return (
        <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
            <table className="w-full text-sm">
                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                    {head.map((h, i) => <th key={i} className={`px-3 py-2 ${i > 1 ? 'text-right' : ''}`}>{h}</th>)}
                </tr></thead>
                <tbody>
                    {rows.length === 0 && <tr><td colSpan={head.length} className="px-3 py-6 text-center text-slate-400">{empty}</td></tr>}
                    {rows.map((r, i) => <tr key={i} className="border-t border-slate-100">{render(r)}</tr>)}
                </tbody>
            </table>
        </div>
    );
}

export default function ValuationPage() {
    const [tab, setTab] = useState('valuation');
    const [per, setPer] = useState(period());

    const val = useQuery({
        queryKey: ['inventory-valuation'],
        queryFn: async () => (await api.get('/inventory-valuation')).data.data,
        enabled: tab === 'valuation',
    });
    const mar = useQuery({
        queryKey: ['margin', per],
        queryFn: async () => (await api.get('/inventory-valuation/margin', { params: { period: per } })).data.data,
        enabled: tab === 'margin',
    });

    const v = val.data;
    const m = mar.data;

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Valuasi Persediaan & Margin</h1>
                <p className="text-sm text-slate-500">
                    RM dinilai rata-rata landed cost per kg, WIP sebesar biaya material saja, FG memakai biaya aktual per lot.
                </p>
            </div>

            <div className="mb-4 flex gap-1.5 border-b border-slate-200 pb-1">
                {[['valuation', 'Valuasi Persediaan'], ['margin', 'Margin per Produk']].map(([k, label]) => (
                    <button key={k} onClick={() => setTab(k)}
                        className={`rounded-t px-3 py-1.5 text-sm ${tab === k ? 'border-b-2 border-[var(--ftpi-primary)] font-semibold text-slate-800' : 'text-slate-500 hover:text-slate-700'}`}>
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'valuation' && (
                val.isLoading ? <p className="text-sm text-slate-400"><Icon name="spinner" className="inline h-4 w-4 animate-spin" /> Menghitung…</p> : v && (
                    <div className="space-y-5">
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Card label="Bahan Baku (RM)" value={v.total.rm} hint={`${v.rm.length} item di rak`} />
                            <Card label="Barang Dalam Proses" value={v.total.wip} hint={`${v.wip.length} work order berjalan`} />
                            <Card label="Barang Jadi (FG)" value={v.total.fg} hint={`${v.fg.length} item di gudang`} />
                            <Card label="Total Persediaan" value={v.total.all} hint={`per ${v.as_of}`} />
                        </div>

                        <div>
                            <h2 className="mb-2 text-sm font-semibold text-slate-700">Bahan Baku</h2>
                            <Table head={['Kode', 'Nama', 'Qty', 'Biaya/pcs', 'Nilai', 'Dasar']} rows={v.rm} empty="Tidak ada stok RM."
                                render={(r) => (<>
                                    <td className="px-3 py-1.5 font-medium">{r.code}</td>
                                    <td className="px-3 py-1.5 text-slate-600">{r.part_name}</td>
                                    <td className="px-3 py-1.5 text-right">{money(r.qty)}</td>
                                    <td className="px-3 py-1.5 text-right">{money(r.unit_cost)}</td>
                                    <td className="px-3 py-1.5 text-right font-medium">{money(r.value)}</td>
                                    <td className={`px-3 py-1.5 text-right text-xs ${r.basis === 'NONE' ? 'text-amber-600' : 'text-slate-400'}`}>{BASIS_LABEL[r.basis]}</td>
                                </>)} />
                        </div>

                        <div>
                            <h2 className="mb-2 text-sm font-semibold text-slate-700">Barang Dalam Proses</h2>
                            <p className="mb-2 text-xs text-slate-400">Potongan yang sudah dipotong tapi belum masuk gudang FG. Upah &amp; overhead baru dibebankan saat lot jadi FG.</p>
                            <Table head={['WO', 'Produk', 'Qty', 'Material/pcs', 'Nilai']} rows={v.wip} empty="Tidak ada WIP."
                                render={(r) => (<>
                                    <td className="px-3 py-1.5 font-medium">{r.wo_code}</td>
                                    <td className="px-3 py-1.5 text-slate-600">{r.code} — {r.part_name}</td>
                                    <td className="px-3 py-1.5 text-right">{money(r.qty)}</td>
                                    <td className="px-3 py-1.5 text-right">{money(r.unit_cost)}</td>
                                    <td className="px-3 py-1.5 text-right font-medium">{money(r.value)}</td>
                                </>)} />
                        </div>

                        <div>
                            <h2 className="mb-2 text-sm font-semibold text-slate-700">Barang Jadi</h2>
                            <Table head={['Kode', 'Nama', 'Qty', 'Biaya/pcs', 'Nilai']} rows={v.fg} empty="Tidak ada stok FG."
                                render={(r) => (<>
                                    <td className="px-3 py-1.5 font-medium">{r.code}</td>
                                    <td className="px-3 py-1.5 text-slate-600">{r.part_name}</td>
                                    <td className="px-3 py-1.5 text-right">{money(r.qty)}</td>
                                    <td className="px-3 py-1.5 text-right">{money(r.unit_cost)}</td>
                                    <td className="px-3 py-1.5 text-right font-medium">{money(r.value)}</td>
                                </>)} />
                        </div>
                    </div>
                )
            )}

            {tab === 'margin' && (
                <div className="space-y-4">
                    <div className="flex items-end gap-2">
                        <div>
                            <label className="field-label">Periode (YYYYMM)</label>
                            <input className="field-input w-36" value={per} onChange={(e) => setPer(e.target.value.replace(/\D/g, '').slice(0, 6))} />
                        </div>
                    </div>

                    {mar.isLoading ? <p className="text-sm text-slate-400"><Icon name="spinner" className="inline h-4 w-4 animate-spin" /> Menghitung…</p> : m && (<>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Card label="Penjualan" value={m.total.revenue} />
                            <Card label="HPP (per lot terkirim)" value={m.total.cogs} />
                            <Card label="Margin" value={m.total.margin} hint={m.total.margin_pct != null ? `${m.total.margin_pct}% dari penjualan` : ''} />
                            <Card label="Qty tanpa biaya lot" value={m.total.qty_uncosted} hint="margin baris ini terlalu tinggi" />
                        </div>

                        <p className="text-xs text-slate-400">Diurutkan dari margin terendah — produk paling merugi muncul lebih dulu.</p>

                        <Table head={['Kode', 'Nama', 'Qty', 'Penjualan', 'HPP', 'Margin', '%']} rows={m.items} empty="Belum ada invoice pada periode ini."
                            render={(r) => (<>
                                <td className="px-3 py-1.5 font-medium">{r.code}</td>
                                <td className="px-3 py-1.5 text-slate-600">{r.part_name}</td>
                                <td className="px-3 py-1.5 text-right">{money(r.qty)}</td>
                                <td className="px-3 py-1.5 text-right">{money(r.revenue)}</td>
                                <td className="px-3 py-1.5 text-right">{money(r.cogs)}</td>
                                <td className={`px-3 py-1.5 text-right font-medium ${r.margin < 0 ? 'text-red-600' : 'text-slate-800'}`}>{money(r.margin)}</td>
                                <td className={`px-3 py-1.5 text-right ${r.margin_pct != null && r.margin_pct < 0 ? 'text-red-600' : 'text-slate-500'}`}>{r.margin_pct != null ? `${r.margin_pct}%` : '—'}</td>
                            </>)} />
                    </>)}
                </div>
            )}
        </div>
    );
}
