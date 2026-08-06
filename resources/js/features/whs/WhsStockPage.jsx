import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/client';
import Icon from '../../components/Icon';
import { money } from '../procurement/common';
import { WHS_TYPES, TypeBadge } from './common';

function Card({ label, value, hint, tone = 'text-slate-800' }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</p>
            <p className={`mt-1 text-xl font-semibold ${tone}`}>{value}</p>
            {hint && <p className="mt-0.5 text-xs text-slate-400">{hint}</p>}
        </div>
    );
}

export default function WhsStockPage() {
    const [type, setType] = useState('');
    const [belowMin, setBelowMin] = useState(false);
    const [tab, setTab] = useState('stock');

    const stock = useQuery({
        queryKey: ['whs-stock', { type, belowMin }],
        queryFn: async () => (await api.get('/whs-stock', {
            params: { whs_type: type || undefined, below_min: belowMin ? 1 : undefined },
        })).data.data,
    });
    const loans = useQuery({
        queryKey: ['whs-stock-loans'],
        queryFn: async () => (await api.get('/whs-stock/on-loan')).data.data,
        enabled: tab === 'loan',
    });
    // Serial batch: dari kiriman mana barang di rak berasal, dan berapa sisanya.
    const serials = useQuery({
        queryKey: ['whs-stock-serials'],
        queryFn: async () => (await api.get('/whs-stock/serials')).data.data,
        enabled: tab === 'serial',
    });

    const s = stock.data?.summary;
    const items = stock.data?.items || [];

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Stok WHS</h1>
                <p className="text-sm text-slate-500">
                    Dihitung dari dokumen yang sudah di-post — bukan dari kolom saldo, supaya laporan ini tidak bisa berbeda dengan transaksinya.
                </p>
            </div>

            {s && (
                <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
                    {WHS_TYPES.map((t) => (
                        <Card key={t.value}
                            label={t.label}
                            value={money(s.by_type[t.value]?.value || 0)}
                            hint={`${s.by_type[t.value]?.items || 0} jenis · ${money(s.by_type[t.value]?.qty || 0)} unit`} />
                    ))}
                    <Card label="Total Persediaan" value={money(s.total_value)} hint={`per ${s.as_of}`} />
                    <Card label="Di bawah minimum" value={s.below_min}
                        tone={s.below_min > 0 ? 'text-amber-700' : 'text-slate-800'}
                        hint={s.on_loan > 0 ? `${s.on_loan} alat sedang dipinjam` : 'semua alat di gudang'} />
                </div>
            )}

            <div className="mb-4 flex gap-1.5 border-b border-slate-200 pb-1">
                {[['stock', 'Isi Gudang'], ['serial', 'Serial Penerimaan'], ['loan', 'Alat Sedang Dipinjam']].map(([k, label]) => (
                    <button key={k} onClick={() => setTab(k)}
                        className={`rounded-t px-3 py-1.5 text-sm ${tab === k ? 'border-b-2 border-[var(--ftpi-primary)] font-semibold text-slate-800' : 'text-slate-500 hover:text-slate-700'}`}>
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'stock' && (<>
                <div className="mb-3 flex flex-wrap items-end gap-3">
                    <div>
                        <label className="field-label">Jenis</label>
                        <select className="field-input w-52" value={type} onChange={(e) => setType(e.target.value)}>
                            <option value="">Semua jenis</option>
                            {WHS_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label} — {t.hint}</option>)}
                        </select>
                    </div>
                    <label className="mb-2 inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" className="h-4 w-4" checked={belowMin} onChange={(e) => setBelowMin(e.target.checked)} />
                        Hanya yang di bawah stok minimum
                    </label>
                </div>

                {stock.isLoading ? (
                    <p className="text-sm text-slate-400"><Icon name="spinner" className="inline h-4 w-4 animate-spin" /> Menghitung…</p>
                ) : (
                    <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-3 py-2">Kode</th><th className="px-3 py-2">Nama</th>
                                <th className="px-3 py-2">Jenis</th><th className="px-3 py-2">Lokasi</th>
                                <th className="px-3 py-2 text-right">Stok</th><th className="px-3 py-2 text-right">Dipinjam</th>
                                <th className="px-3 py-2 text-right">Min.</th><th className="px-3 py-2 text-right">Harga rata²</th>
                                <th className="px-3 py-2 text-right">Nilai</th>
                            </tr></thead>
                            <tbody>
                                {items.length === 0 && <tr><td colSpan={9} className="px-3 py-6 text-center text-slate-400">Tidak ada barang.</td></tr>}
                                {items.map((r) => (
                                    <tr key={r.item_id} className={`border-t border-slate-100 ${r.below_min ? 'bg-amber-50' : ''}`}>
                                        <td className="px-3 py-1.5 font-medium">{r.code}</td>
                                        <td className="px-3 py-1.5 text-slate-600">{r.name}</td>
                                        <td className="px-3 py-1.5"><TypeBadge type={r.whs_type} /></td>
                                        <td className="px-3 py-1.5 text-slate-500">{r.rack_loc || '—'}</td>
                                        <td className={`px-3 py-1.5 text-right font-medium ${r.below_min ? 'text-amber-700' : ''}`}>{money(r.qty)}</td>
                                        <td className="px-3 py-1.5 text-right text-slate-500">{r.whs_type === 'TOOL' ? money(r.on_loan) : '—'}</td>
                                        <td className="px-3 py-1.5 text-right text-slate-400">{r.min_stock || '—'}</td>
                                        <td className="px-3 py-1.5 text-right">{money(r.unit_cost)}</td>
                                        <td className="px-3 py-1.5 text-right font-medium">{money(r.value)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </>)}

            {tab === 'serial' && (<>
                <p className="mb-3 text-xs text-slate-500">
                    Satu serial = satu batch penerimaan. Kode inilah yang dipilih saat barang keluar, dan yang dicatat di downtime mesin ketika sparepart diganti —
                    dari situ riwayat “bearing mesin ini sudah diganti berapa kali, dari kiriman mana” bisa dibaca.
                </p>
                <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                    <table className="w-full text-sm">
                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th className="px-3 py-2">Serial</th><th className="px-3 py-2">Barang</th>
                            <th className="px-3 py-2">Penerimaan</th><th className="px-3 py-2">Surat Jalan</th>
                            <th className="px-3 py-2 text-right">Diterima</th><th className="px-3 py-2 text-right">Keluar</th>
                            <th className="px-3 py-2 text-right">Sisa</th><th className="px-3 py-2 text-right">Harga</th>
                        </tr></thead>
                        <tbody>
                            {(serials.data || []).length === 0 && <tr><td colSpan={8} className="px-3 py-6 text-center text-slate-400">Belum ada serial. Serial terbit saat penerimaan di-post.</td></tr>}
                            {(serials.data || []).map((s) => (
                                <tr key={s.serial_code} className={`border-t border-slate-100 ${s.remaining === 0 ? 'text-slate-400' : ''}`}>
                                    <td className="px-3 py-1.5 font-mono text-xs font-medium">{s.serial_code}</td>
                                    <td className="px-3 py-1.5">{s.item_code} — {s.item_name}</td>
                                    <td className="px-3 py-1.5 text-slate-500">{s.receipt_code} · {String(s.receipt_date).slice(0, 10)}</td>
                                    <td className="px-3 py-1.5 text-slate-500">{s.do_no || '—'}</td>
                                    <td className="px-3 py-1.5 text-right">{money(s.received)}</td>
                                    <td className="px-3 py-1.5 text-right">{money(s.issued)}</td>
                                    <td className={`px-3 py-1.5 text-right font-medium ${s.remaining === 0 ? '' : 'text-slate-800'}`}>{money(s.remaining)}</td>
                                    <td className="px-3 py-1.5 text-right">{money(s.unit_cost)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </>)}

            {tab === 'loan' && (
                <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                    <table className="w-full text-sm">
                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th className="px-3 py-2">Unit</th><th className="px-3 py-2">Alat</th>
                            <th className="px-3 py-2">Pemegang</th><th className="px-3 py-2">Bagian</th>
                            <th className="px-3 py-2">Dokumen</th><th className="px-3 py-2 text-right">Lama di luar</th>
                        </tr></thead>
                        <tbody>
                            {(loans.data || []).length === 0 && <tr><td colSpan={6} className="px-3 py-6 text-center text-slate-400">Semua alat ada di gudang.</td></tr>}
                            {(loans.data || []).map((u) => (
                                <tr key={u.id} className="border-t border-slate-100">
                                    <td className="px-3 py-1.5 font-medium">{u.unit_code}</td>
                                    <td className="px-3 py-1.5 text-slate-600">{u.item_code} — {u.item_name}</td>
                                    <td className="px-3 py-1.5">{u.holder || '—'}</td>
                                    <td className="px-3 py-1.5 text-slate-500">{u.dept || '—'}</td>
                                    <td className="px-3 py-1.5 text-slate-500">{u.out_code || '—'}</td>
                                    <td className={`px-3 py-1.5 text-right ${u.days_out > 30 ? 'font-medium text-amber-700' : 'text-slate-500'}`}>
                                        {u.days_out != null ? `${u.days_out} hari` : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
