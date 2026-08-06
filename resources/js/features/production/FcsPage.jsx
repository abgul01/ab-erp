import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import Modal from '../../components/Modal';
import { Select, StatusBadge, money } from '../procurement/common';

/**
 * Final Check Sheet — the gate a Work Order passes before its output becomes
 * finished goods. Approving one creates the FG lot and freezes the traceability
 * snapshot (which RM serials and pallets went into it), so it is deliberately a
 * one-way step rather than an editable document.
 */
export default function FcsPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [status, setStatus] = useState('');
    const [creating, setCreating] = useState(false);
    const [viewId, setViewId] = useState(null);

    const list = useQuery({
        queryKey: ['fcs', status],
        queryFn: async () => (await api.get('/fcs', { params: { status: status || undefined } })).data.data,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['fcs'] });

    const approve = useMutation({
        mutationFn: async (id) => (await api.post(`/fcs/${id}/approve`)).data.data,
        onSuccess: (d) => { alert(`FCS disetujui. Lot FG dibuat: ${d.lot_code}`); invalidate(); },
        onError: (e) => alert(apiError(e)),
    });

    const reject = useMutation({
        mutationFn: async ({ id, reason }) => api.post(`/fcs/${id}/reject`, { reason }),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Final Check Sheet</h1>
                    <p className="text-xs text-slate-400">
                        QC akhir per Work Order. Persetujuan di sini yang membuat lot FG dan mengunci jejak telusurnya.
                    </p>
                </div>
                <div className="flex items-end gap-2">
                    <div>
                        <label className="field-label">Status</label>
                        <select className="field-input w-40" value={status} onChange={(e) => setStatus(e.target.value)}>
                            <option value="">Semua</option>
                            <option value="PENDING">PENDING</option>
                            <option value="APPROVED">APPROVED</option>
                            <option value="REJECTED">REJECTED</option>
                        </select>
                    </div>
                    {can('work-orders', 'create') && (
                        <button className="btn btn-primary" onClick={() => setCreating(true)}><Icon name="plus" /> Buat FCS</button>
                    )}
                </div>
            </div>

            <div className="card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th className="px-4 py-3">#</th>
                            <th className="px-4 py-3">Work Order</th>
                            <th className="px-4 py-3">FG</th>
                            <th className="px-4 py-3">Rencana</th>
                            <th className="px-4 py-3">Lolos QC</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {list.isLoading && <tr><td colSpan={7} className="px-4 py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></td></tr>}
                        {!list.isLoading && (list.data || []).length === 0 && (
                            <tr><td colSpan={7} className="px-4 py-10 text-center text-slate-400">Belum ada Final Check Sheet.</td></tr>
                        )}
                        {(list.data || []).map((f) => (
                            <tr key={f.id} className="border-b border-slate-100 hover:bg-blue-50/40">
                                <td className="px-4 py-2.5 font-medium">FCS-{f.id}</td>
                                <td className="px-4 py-2.5">{f.wo_code}</td>
                                <td className="px-4 py-2.5">{f.fg_code}<div className="text-xs text-slate-400">{f.fg_name}</div></td>
                                <td className="px-4 py-2.5">{money(f.qty_planned)}</td>
                                <td className="px-4 py-2.5">{money(f.qty_good)}</td>
                                <td className="px-4 py-2.5"><StatusBadge status={f.status} /></td>
                                <td className="px-4 py-2.5 text-right">
                                    <div className="flex justify-end gap-1">
                                        <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => setViewId(f.id)}>Telusur</button>
                                        {f.status === 'PENDING' && can('work-orders', 'edit') && (
                                            <>
                                                <button className="btn btn-ghost px-2 py-1 text-xs text-emerald-700"
                                                    onClick={() => window.confirm('Setujui FCS ini? Lot FG akan dibuat.') && approve.mutate(f.id)}>Setujui</button>
                                                <button className="btn btn-ghost px-2 py-1 text-xs text-red-700"
                                                    onClick={() => {
                                                        const reason = window.prompt('Alasan penolakan:');
                                                        if (reason) reject.mutate({ id: f.id, reason });
                                                    }}>Tolak</button>
                                            </>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {creating && <CreateModal onClose={() => setCreating(false)} onSaved={() => { setCreating(false); invalidate(); }} />}
            {viewId && <TraceModal id={viewId} onClose={() => setViewId(null)} />}
        </div>
    );
}

function CreateModal({ onClose, onSaved }) {
    const [woId, setWoId] = useState('');
    const wos = useQuery({
        queryKey: ['fcs', 'eligible'],
        queryFn: async () => (await api.get('/fcs/eligible-wos')).data.data,
    });

    const save = useMutation({
        mutationFn: async () => api.post('/fcs', { wo_id: woId }),
        onSuccess: onSaved,
        onError: (e) => alert(apiError(e)),
    });

    return (
        <Modal open onClose={onClose} size="max-w-lg" title="Buat Final Check Sheet"
            footer={
                <>
                    <button className="btn btn-ghost" onClick={onClose}>Batal</button>
                    <button className="btn btn-primary" disabled={!woId || save.isPending} onClick={() => save.mutate()}>
                        {save.isPending ? 'Menyimpan…' : 'Buat'}
                    </button>
                </>
            }>
            <label className="field-label">Work Order</label>
            <Select value={woId} onChange={setWoId} options={wos.data}
                getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.fg_code} (qty ${o.qty}, ${o.status})`}
                placeholder={wos.data?.length ? '— pilih WO —' : 'tidak ada WO yang memenuhi syarat'} />
            <p className="mt-2 text-xs text-slate-400">
                Hanya WO yang seluruh transaksi cutting dan prosesnya sudah COMPLETED yang bisa dibuatkan FCS.
            </p>
        </Modal>
    );
}

function TraceModal({ id, onClose }) {
    const q = useQuery({
        queryKey: ['fcs', id],
        queryFn: async () => (await api.get(`/fcs/${id}`)).data.data,
    });
    const trace = q.data?.traceability_data;

    return (
        <Modal open onClose={onClose} size="max-w-3xl" title="Jejak Telusur FCS"
            footer={<button className="btn btn-ghost" onClick={onClose}>Tutup</button>}>
            {q.isLoading && <div className="py-6 text-center text-slate-400">Memuat…</div>}
            {q.data && (
                <div className="space-y-3 text-sm">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Field label="Work Order" value={q.data.wo_code} />
                        <Field label="FG" value={q.data.fg_code} />
                        <Field label="Rencana" value={money(q.data.qty_planned)} />
                        <Field label="Lolos QC" value={money(q.data.qty_good)} />
                    </div>
                    {q.data.notes && <div className="rounded bg-amber-50 px-3 py-2 text-amber-800">{q.data.notes}</div>}
                    {trace ? (
                        <pre className="max-h-96 overflow-auto rounded bg-slate-900 p-3 text-xs text-slate-100">
                            {JSON.stringify(trace, null, 2)}
                        </pre>
                    ) : (
                        <p className="text-slate-400">Jejak telusur baru dibekukan saat FCS disetujui.</p>
                    )}
                </div>
            )}
        </Modal>
    );
}

function Field({ label, value }) {
    return (
        <div>
            <div className="text-xs text-slate-400">{label}</div>
            <div className="font-medium text-slate-800">{value ?? '—'}</div>
        </div>
    );
}
