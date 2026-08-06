import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import ApprovalBadge from '../../components/ApprovalBadge';
import { StatusBadge } from '../procurement/common';

/**
 * Colour is per document family and cosmetic only. The readable name comes from
 * the server (`doc_type_label`), so a newly approvable document shows up here
 * correctly without this file needing to know it exists.
 */
const DOC_COLOR = {
    prc_pr_main: 'text-sky-700',
    prc_po_main: 'text-indigo-700',
    sub_po_main: 'text-indigo-700',
    sls_so_main: 'text-amber-700',
    m_pricelist_main: 'text-amber-700',
    prd_mpp: 'text-violet-700',
    prd_mps: 'text-violet-700',
    prd_mps_resched: 'text-violet-700',
    prd_wo_main: 'text-cyan-700',
    m_item: 'text-emerald-700',
    m_bom: 'text-emerald-700',
    m_process_main: 'text-emerald-700',
};

export default function ApprovalPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [rejectId, setRejectId] = useState(null);
    const [rejectNote, setRejectNote] = useState('');
    const [flash, setFlash] = useState(null);

    const list = useQuery({
        queryKey: ['approvals', 'pending'],
        queryFn: async () => (await api.get('/approvals/pending')).data.data,
        refetchInterval: 15_000,
    });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['approvals'] }); };

    // Approving goes through the approval row itself, so every registered
    // document type works — including the ones this file has never heard of.
    const approve = useMutation({
        mutationFn: async (id) => (await api.post(`/approvals/${id}/approve`)).data.data,
        onSuccess: (d) => { invalidate(); if (d?.message) setFlash(d.message); },
        onError: (e) => alert(apiError(e)),
    });

    const reject = useMutation({
        mutationFn: async ({ id, note }) => api.post(`/approvals/${id}/reject`, { note }),
        onSuccess: () => { invalidate(); setRejectId(null); setRejectNote(''); },
        onError: (e) => alert(apiError(e)),
    });

    const rows = list.data || [];

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Approval Persetujuan</h1>
                <p className="mt-1 text-xs text-slate-400">Dokumen yang menunggu persetujuan Anda. Data diperbarui otomatis setiap 15 detik.</p>
            </div>

            {flash && (
                <div className="mb-3 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{flash}</div>
            )}

            <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th className="px-3 py-2.5">Tipe</th>
                            <th className="px-3 py-2.5">Dokumen</th>
                            <th className="px-3 py-2.5">Status Dokumen</th>
                            <th className="px-3 py-2.5">Level Approval</th>
                            <th className="px-3 py-2.5">Pengaju</th>
                            <th className="px-3 py-2.5">Tanggal</th>
                            <th className="px-3 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {list.isLoading && <tr><td colSpan={7} className="px-4 py-6 text-center text-slate-400">Memuat…</td></tr>}
                        {!list.isLoading && rows.length === 0 && <tr><td colSpan={7} className="px-4 py-6 text-center text-slate-400">Tidak ada dokumen yang menunggu persetujuan Anda.</td></tr>}
                        {rows.map((r) => {
                            const color = DOC_COLOR[r.approval.doc_type] || 'text-slate-700';
                            return (
                                <tr key={r.approval.id} className="border-t border-slate-100">
                                    <td className="px-3 py-2">
                                        <span className={`font-medium ${color}`}>{r.doc_type_label || r.approval.doc_type}</span>
                                    </td>
                                    <td className="px-3 py-2 font-medium text-slate-800">{r.doc_label}</td>
                                    <td className="px-3 py-2"><StatusBadge status={r.doc_status} /></td>
                                    <td className="px-3 py-2"><ApprovalBadge approvals={r.approvals} /></td>
                                    <td className="px-3 py-2 text-slate-600">{r.approval.actor?.name || <span className="text-slate-300">—</span>}</td>
                                    <td className="px-3 py-2 text-slate-500 text-xs">{r.approval.created_at?.slice(0, 10)}</td>
                                    <td className="px-3 py-2">
                                        <div className="flex justify-end gap-1">
                                            <button
                                                className="btn btn-ghost px-2 py-1 text-xs text-emerald-700"
                                                disabled={approve.isPending}
                                                onClick={() => approve.mutate(r.approval.id)}
                                            >
                                                <Icon name="check" className="h-3.5 w-3.5" /> Setujui
                                            </button>
                                            <button
                                                className="btn btn-ghost px-2 py-1 text-xs text-red-600"
                                                onClick={() => setRejectId(r.approval.id)}
                                            >
                                                <Icon name="ban" className="h-3.5 w-3.5" /> Tolak
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {rejectId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={() => setRejectId(null)}>
                    <div className="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-3 text-sm font-semibold text-slate-700">Alasan Penolakan</h3>
                        <textarea
                            className="field-input mb-3 min-h-[80px] w-full"
                            placeholder="Wajib diisi …"
                            value={rejectNote}
                            onChange={(e) => setRejectNote(e.target.value)}
                            autoFocus
                        />
                        <div className="flex justify-end gap-2">
                            <button className="btn btn-ghost px-3 py-1.5 text-xs" onClick={() => setRejectId(null)}>Batal</button>
                            <button
                                className="btn bg-red-600 px-3 py-1.5 text-xs text-white disabled:opacity-50"
                                disabled={!rejectNote.trim() || reject.isPending}
                                onClick={() => reject.mutate({ id: rejectId, note: rejectNote })}
                            >
                                {reject.isPending ? '…' : 'Tolak'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
