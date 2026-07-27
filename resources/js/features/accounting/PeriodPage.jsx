import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Icon from '../../components/Icon';

const ym = () => new Date().toISOString().slice(0, 7).replace('-', '');
const STYLE = { OPEN: 'bg-emerald-100 text-emerald-700', CLOSED: 'bg-amber-100 text-amber-700', LOCKED: 'bg-red-100 text-red-700' };

/** Accounting periods: OPEN allows posting; CLOSED locks it (LOCKED = permanent). */
export default function PeriodPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [period, setPeriod] = useState(ym());

    const list = useQuery({ queryKey: ['acc-periods', { page }], queryFn: async () => (await api.get('/acc-periods', { params: { page, per_page: 60 } })).data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['acc-periods'] });

    const add = useMutation({ mutationFn: async () => api.post('/acc-periods', { period }), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const setStatus = useMutation({ mutationFn: async ({ id, status }) => api.post(`/acc-periods/${id}/status`, { status }), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const columns = [
        { key: 'period', label: 'Periode' },
        { key: 'status', label: 'Status', render: (v) => <span className={`rounded-full px-2 py-0.5 text-xs ${STYLE[v] || ''}`}>{v}</span> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Periode Akuntansi</h1>
                    <p className="text-xs text-slate-400">Jurnal hanya bisa diposting ke periode berstatus OPEN. Tutup periode untuk mengunci.</p>
                </div>
                {can('acc-periods', 'create') && (
                    <div className="flex items-end gap-2">
                        <div><label className="field-label">Periode baru</label><input className="field-input w-32" maxLength={6} value={period} onChange={(e) => setPeriod(e.target.value)} placeholder="202607" /></div>
                        <button className="btn btn-primary" onClick={() => add.mutate()} disabled={add.isPending}><Icon name="plus" /> Buka Periode</button>
                    </div>
                )}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {row.status === 'OPEN' && can('acc-periods', 'edit') && <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => window.confirm(`Tutup periode ${row.period}?`) && setStatus.mutate({ id: row.id, status: 'CLOSED' })}>Tutup</button>}
                        {row.status === 'CLOSED' && can('acc-periods', 'edit') && <button className="btn btn-ghost px-2 py-1 text-xs text-emerald-700" onClick={() => setStatus.mutate({ id: row.id, status: 'OPEN' })}>Buka Lagi</button>}
                    </div>
                )} />
        </div>
    );
}
