import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';

const STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'];
const STCLR = {
    PENDING: 'bg-amber-100 text-amber-700',
    APPROVED: 'bg-emerald-100 text-emerald-700',
    REJECTED: 'bg-red-100 text-red-700',
    CANCELLED: 'bg-slate-100 text-slate-500',
};

/**
 * Approver's desk for MPS reschedule requests (maker-checker). Users with edit
 * on this menu can approve/reject; the requester can cancel their own pending
 * request. Approving applies the new date/machine to the locked lot.
 */
export default function MpsApprovalsPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const me = useAuth((s) => s.user);
    const canApprove = can('mps-approvals', 'edit');
    const [status, setStatus] = useState('PENDING');

    const list = useQuery({ queryKey: ['mps-approvals', status], queryFn: async () => (await api.get('/mps-approvals', { params: { status, per_page: 200 } })).data.data });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['mps-approvals'] }); qc.invalidateQueries({ queryKey: ['mps'] }); };

    const act = useMutation({
        mutationFn: async ({ id, action, note }) => api.post(`/mps-approvals/${id}/${action}`, { note }),
        onSuccess: invalidate, onError: (e) => alert(apiError(e)),
    });

    const rows = list.data || [];
    const fmtMachine = (m) => (m ? `${m.code}` : '—');

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold text-slate-800">Persetujuan Jadwal MPS</h1>
                <div className="flex items-center gap-2">
                    <label className="text-sm text-slate-500">Status</label>
                    <select className="field-input w-40" value={status} onChange={(e) => setStatus(e.target.value)}>
                        {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                    </select>
                </div>
            </div>
            <p className="mb-3 text-xs text-slate-400">Permintaan pemindahan jadwal untuk lot terkunci (APPROVED). {canApprove ? 'Anda dapat menyetujui / menolak.' : 'Anda hanya dapat memantau & membatalkan permintaan Anda sendiri.'}</p>

            <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th className="px-3 py-2.5">Item</th>
                            <th className="px-3 py-2.5">Proses</th>
                            <th className="px-3 py-2.5">Dari</th>
                            <th className="px-3 py-2.5">Ke</th>
                            <th className="px-3 py-2.5">Alasan</th>
                            <th className="px-3 py-2.5">Pengaju</th>
                            <th className="px-3 py-2.5">Status</th>
                            <th className="px-3 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {list.isLoading && <tr><td colSpan={8} className="px-4 py-6 text-center text-slate-400">Memuat…</td></tr>}
                        {!list.isLoading && rows.length === 0 && <tr><td colSpan={8} className="px-4 py-6 text-center text-slate-400">Tidak ada permintaan berstatus {status}.</td></tr>}
                        {rows.map((r) => (
                            <tr key={r.id} className="border-t border-slate-100">
                                <td className="px-3 py-2">
                                    <div className="font-medium text-slate-700">{r.mps?.item?.code || `#${r.mps?.item_id}`}</div>
                                    <div className="text-xs text-slate-400">{r.mps?.item?.part_name}</div>
                                </td>
                                <td className="px-3 py-2 text-slate-600">{r.mps?.proc_id ? (r.mps?.process?.code || '—') : '—'}</td>
                                <td className="px-3 py-2 text-slate-600">{r.from_date}<div className="text-xs text-slate-400">{fmtMachine(r.from_machine)}</div></td>
                                <td className="px-3 py-2 font-medium text-slate-800">{r.to_date}<div className="text-xs text-slate-500">{fmtMachine(r.to_machine)}</div></td>
                                <td className="px-3 py-2 text-slate-600">{r.reason || <span className="text-slate-300">—</span>}</td>
                                <td className="px-3 py-2 text-slate-600">{r.requester?.name || `#${r.requested_by}`}</td>
                                <td className="px-3 py-2"><span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${STCLR[r.status]}`}>{r.status}</span>
                                    {r.status !== 'PENDING' && r.approver && <div className="text-[11px] text-slate-400">oleh {r.approver.name}</div>}
                                </td>
                                <td className="px-3 py-2">
                                    {r.status === 'PENDING' && (
                                        <div className="flex justify-end gap-1">
                                            {canApprove && <button className="btn btn-ghost px-2 py-1 text-xs text-emerald-700" onClick={() => act.mutate({ id: r.id, action: 'approve' })}><Icon name="check" className="h-3.5 w-3.5" /> Setujui</button>}
                                            {canApprove && <button className="btn btn-ghost px-2 py-1 text-xs text-red-600" onClick={() => { const note = window.prompt('Alasan penolakan (opsional):') ?? undefined; act.mutate({ id: r.id, action: 'reject', note }); }}><Icon name="ban" className="h-3.5 w-3.5" /> Tolak</button>}
                                            {(r.requested_by === me?.id) && <button className="btn btn-ghost px-2 py-1 text-xs text-slate-500" onClick={() => window.confirm('Batalkan permintaan ini?') && act.mutate({ id: r.id, action: 'cancel' })}><Icon name="undo" className="h-3.5 w-3.5" /> Batalkan</button>}
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
