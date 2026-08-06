import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import DataTable from '../../components/DataTable';

import { MonthRangePicker, currentPeriod, formatPeriod, periodRange } from '../../components/MonthPicker';

/**
 * Capacity requirements planning.
 *
 * A run explodes the approved MPS through each item's routing and totals the
 * hours landing on every process and machine, against that machine's available
 * hours. Load above capacity is the bottleneck PPIC decides overtime or
 * subcontracting on, so it is called out rather than left to be read off a number.
 */
export default function CrpPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    // Capacity is reviewed over a span, so the range defaults to this month
    // alone and widens without the planner running it once per month.
    const [range, setRange] = useState({ from: currentPeriod(), to: currentPeriod() });
    const periods = periodRange(range.from, range.to);

    const list = useQuery({
        queryKey: ['crp', { page }],
        queryFn: async () => (await api.get('/crp', { params: { page, per_page: 50 } })).data,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['crp'] });

    // The run is queued, so the table only fills once the worker finishes.
    const run = useMutation({
        mutationFn: async () => (await api.post('/crp/run', { periods })).data.data,
        onSuccess: (d) => { invalidate(); alert(d.message || 'CRP dijalankan.'); },
        onError: (e) => alert(apiError(e)),
    });

    const del = useMutation({
        mutationFn: async (id) => api.delete(`/crp/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const columns = [
        { key: 'period', label: 'Periode', render: formatPeriod },
        { key: 'basis', label: 'Basis' },
        { key: 'process', label: 'Proses', render: (v) => v?.name || v?.process || '—' },
        { key: 'machine', label: 'Mesin', render: (v) => (v ? `${v.code} — ${v.name}` : 'Semua mesin') },
        { key: 'load_hours', label: 'Beban (jam)', render: (v) => Number(v || 0).toFixed(1) },
        { key: 'capacity_hours', label: 'Kapasitas (jam)', render: (v) => Number(v || 0).toFixed(1) },
        {
            key: 'id',
            label: 'Utilisasi',
            render: (_v, row) => {
                const cap = Number(row.capacity_hours || 0);
                const load = Number(row.load_hours || 0);
                if (cap <= 0) return <span className="text-xs text-slate-400">kapasitas belum diisi</span>;
                const pct = (load / cap) * 100;
                const over = pct > 100;
                return (
                    <div className="min-w-40">
                        <div className="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                            <div className={`h-full ${over ? 'bg-red-500' : pct > 85 ? 'bg-amber-500' : 'bg-emerald-500'}`}
                                style={{ width: `${Math.min(pct, 100)}%` }} />
                        </div>
                        <span className={`text-xs ${over ? 'font-semibold text-red-600' : 'text-slate-500'}`}>
                            {pct.toFixed(0)}%{over ? ' — bottleneck' : ''}
                        </span>
                    </div>
                );
            },
        },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">CRP — Loading Capacity</h1>
                    <p className="text-xs text-slate-400">
                        Beban jam per proses dan mesin dari MPS yang sudah disetujui, dibandingkan kapasitas tersedia.
                    </p>
                </div>
                {can('crp', 'create') && (
                    <div className="flex flex-wrap items-end gap-2">
                        <MonthRangePicker from={range.from} to={range.to} onChange={setRange} disabled={run.isPending} />
                        <button className="btn btn-primary" disabled={run.isPending || periods.length === 0} onClick={() => run.mutate()}>
                            <Icon name="play" />
                            {run.isPending ? 'Menghitung…' : periods.length > 1 ? `Jalankan CRP (${periods.length} bulan)` : 'Jalankan CRP'}
                        </button>
                    </div>
                )}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={can('crp', 'delete') ? (row) => (
                    <button className="btn btn-ghost px-2 py-1 text-xs text-red-700"
                        onClick={() => window.confirm('Hapus baris CRP ini?') && del.mutate(row.id)}>Hapus</button>
                ) : undefined} />
        </div>
    );
}
