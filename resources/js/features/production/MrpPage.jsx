import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { money } from '../procurement/common';

// A firm order is the surest demand, a forecast the least — colour says which.
const DEMAND_STYLE = {
    SO: 'bg-emerald-100 text-emerald-700',
    MPP: 'bg-sky-100 text-sky-700',
    FORECAST: 'bg-amber-100 text-amber-700',
};

import { MonthRangePicker, addMonths, currentPeriod, formatPeriod, periodRange } from '../../components/MonthPicker';

/**
 * MRP — explode the approved MPP into net material requirements. Runs are
 * historical snapshots; open one to see per-item FG (→WO) and RM (→PR) shortfalls.
 */
export default function MrpPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [view, setView] = useState(null);
    const [error, setError] = useState('');
    // Was hardcoded to "the next three months"; planners routinely need a
    // different horizon, so the range is theirs to set.
    const [range, setRange] = useState({ from: currentPeriod(), to: addMonths(currentPeriod(), 2) });
    const periods = periodRange(range.from, range.to);

    const list = useQuery({ queryKey: ['mrp', { page }], queryFn: async () => (await api.get('/mrp', { params: { page, per_page: 15 } })).data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['mrp'] });

    const run = useMutation({
        mutationFn: async () => (await api.post('/mrp/run', { periods })).data.data,
        onSuccess: (d) => { invalidate(); setView(d); }, onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/mrp/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const openView = async (row) => { const { data } = await api.get(`/mrp/${row.id}`); setView(data.data); };

    // Turn the run's RM shortfalls into a draft Purchase Requisition. The
    // server nets and rounds; this only asks for it.
    const genPr = useMutation({
        mutationFn: async (id) => (await api.post(`/mrp/${id}/generate-pr`)).data.data,
        onSuccess: (d) => {
            setView(null);
            alert(`Purchase Requisition ${d.code} dibuat: ${d.items} material, total ${d.total_qty} pcs (${d.total_kg} kg).\nStatusnya DRAFT — periksa lalu ajukan approval di menu Purchase Requisition.`);
        },
        onError: (e) => alert(apiError(e)),
    });

    const columns = [
        { key: 'id', label: 'Run #' },
        { key: 'run_date', label: 'Tanggal Run', render: (v) => v?.slice(0, 16).replace('T', ' ') },
        { key: 'detail_count', label: '# Baris' },
        { key: 'status', label: 'Status', render: (v) => <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">{v}</span> },
    ];

    const shortfalls = (view?.detail || []).filter((d) => d.net_req > 0);
    // Only raw material becomes a requisition; finished goods become work orders.
    const rmShortfalls = shortfalls.filter((d) => d.suggestion === 'PR').length;

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">MRP — Kebutuhan Material</h1>
                    <p className="text-xs text-slate-400">
                        Permintaan diambil yang tertinggi dari MPP, forecast, dan SO belum terkirim; lalu diledakkan lewat BOM
                        dan dikurangi stok, WO berjalan, PO, serta PR terbuka. Hasilnya kebutuhan bersih FG (→WO) dan RM (→PR).
                    </p>
                </div>
                <div className="flex flex-wrap items-end gap-2">
                    <MonthRangePicker from={range.from} to={range.to} onChange={setRange} disabled={run.isPending} />
                    {can('mrp', 'create') && <button className="btn btn-primary" onClick={() => { setError(''); run.mutate(); }} disabled={run.isPending || periods.length === 0}>{run.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="calculator" />} Jalankan MRP{periods.length > 1 ? ` (${periods.length} bln)` : ''}</button>}
                </div>
            </div>
            {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {can('mrp', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus run MRP?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!view} onClose={() => setView(null)} size="max-w-5xl" title={`Hasil MRP Run #${view?.id || ''}`}
                footer={
                    <>
                        <button className="btn btn-ghost" onClick={() => setView(null)}>Tutup</button>
                        {can('mrp', 'create') && (
                            <button className="btn btn-primary" disabled={rmShortfalls === 0 || genPr.isPending}
                                title={rmShortfalls === 0 ? 'Tidak ada material yang perlu dibeli' : ''}
                                onClick={() => window.confirm(`Buat Purchase Requisition untuk ${rmShortfalls} material?`) && genPr.mutate(view.id)}>
                                <Icon name="clipboard-list" /> {genPr.isPending ? 'Membuat…' : `Buat PR (${rmShortfalls} material)`}
                            </button>
                        )}
                    </>
                }>
                <p className="mb-3 text-xs text-slate-500">
                    Hanya baris dengan kebutuhan bersih &gt; 0. Bruto = permintaan tertinggi antara MPP, forecast, dan SO
                    (bukan dijumlah — SO umumnya realisasi forecast). Bersih = bruto − stok − WO berjalan − PO − PR terbuka,
                    lalu dibulatkan ke MOQ dan kelipatan supplier. Arahkan kursor ke kolom Sumber untuk melihat ketiga angkanya.
                </p>
                <div className="overflow-x-auto rounded-md border border-slate-200">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">Level</th><th className="px-2 py-2">Item</th><th className="px-2 py-2">Periode</th>
                                <th className="px-2 py-2">Sumber</th>
                                <th className="px-2 py-2 text-right">Bruto</th><th className="px-2 py-2 text-right">On-hand</th>
                                <th className="px-2 py-2 text-right">Open PO/WO</th><th className="px-2 py-2 text-right">Net</th>
                                <th className="px-2 py-2 text-right">Net (kg)</th><th className="px-2 py-2">Saran</th>
                            </tr>
                        </thead>
                        <tbody>
                            {shortfalls.length === 0 && <tr><td colSpan={10} className="px-2 py-6 text-center text-slate-400">Tidak ada kekurangan — semua kebutuhan tercukupi stok / order berjalan.</td></tr>}
                            {shortfalls.map((d, i) => (
                                <tr key={i} className="border-t border-slate-100">
                                    <td className="px-2 py-1.5"><span className={`rounded px-1.5 py-0.5 text-xs ${d.level === 'FG' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'}`}>{d.level}</span></td>
                                    <td className="px-2 py-1.5"><span className="font-medium">{d.item_code}</span> <span className="text-slate-400">{d.part_name}</span></td>
                                    <td className="px-2 py-1.5">{formatPeriod(d.period)}</td>
                                    <td className="px-2 py-1.5">
                                        {d.demand_src ? (
                                            <span className={`rounded px-1.5 py-0.5 text-xs ${DEMAND_STYLE[d.demand_src] || 'bg-slate-100 text-slate-600'}`}
                                                title={`MPP ${d.demand_mpp} · Forecast ${d.demand_fc} · SO ${d.demand_so} — diambil yang tertinggi`}>
                                                {d.demand_src}
                                            </span>
                                        ) : <span className="text-slate-300">—</span>}
                                    </td>
                                    <td className="px-2 py-1.5 text-right">{d.gross_req}</td>
                                    <td className="px-2 py-1.5 text-right">{d.onhand}</td>
                                    <td className="px-2 py-1.5 text-right">{(d.open_po || 0) + (d.open_wo || 0)}</td>
                                    <td className="px-2 py-1.5 text-right font-semibold text-slate-800">{d.net_req}</td>
                                    <td className="px-2 py-1.5 text-right">{d.net_req_kg != null ? money(d.net_req_kg) : '—'}</td>
                                    <td className="px-2 py-1.5"><span className={`rounded px-1.5 py-0.5 text-xs ${d.suggestion === 'WO' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'}`}>{d.suggestion}</span></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Modal>
        </div>
    );
}
