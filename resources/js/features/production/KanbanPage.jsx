import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import Modal from '../../components/Modal';
import DataTable from '../../components/DataTable';
import { Select, useOptions, StatusBadge, money } from '../procurement/common';

/**
 * Kanban material issue (PP-HACV).
 *
 * A kanban is raised per RM line of a released Work Order and then consumed on
 * the floor by scanning serials. Issuing takes the length actually used, not the
 * planned length, because the leftover decides whether the bar goes back to a
 * remnant rack or becomes scrap.
 */
export default function KanbanPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [status, setStatus] = useState('');
    const [createFor, setCreateFor] = useState(false);
    const [issueOn, setIssueOn] = useState(null);

    const list = useQuery({
        queryKey: ['kanbans', { page, status }],
        queryFn: async () => (await api.get('/kanbans', { params: { page, per_page: 20, status: status || undefined } })).data,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['kanbans'] });

    const close = useMutation({
        mutationFn: async (id) => api.post(`/kanbans/${id}/close`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const columns = [
        { key: 'code', label: 'Kode' },
        { key: 'wo', label: 'Work Order', render: (v) => v?.code || '—' },
        { key: 'item', label: 'Material', render: (v) => (v ? `${v.code} — ${v.part_name}` : '—') },
        { key: 'qty_planned', label: 'Rencana', render: money },
        { key: 'qty_issued', label: 'Terkeluar', render: money },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Kanban / Issue RM</h1>
                    <p className="text-xs text-slate-400">
                        Permintaan material per Work Order. Pengeluaran dilakukan dengan scan serial batang yang sudah dibooking.
                    </p>
                </div>
                <div className="flex items-end gap-2">
                    <div>
                        <label className="field-label">Status</label>
                        <select className="field-input w-36" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }}>
                            <option value="">Semua</option>
                            <option value="OPEN">OPEN</option>
                            <option value="CLOSED">CLOSED</option>
                        </select>
                    </div>
                    {can('kanbans', 'create') && (
                        <button className="btn btn-primary" onClick={() => setCreateFor(true)}><Icon name="plus" /> Buat dari WO</button>
                    )}
                </div>
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {row.status === 'OPEN' && can('kanbans', 'edit') && (
                            <>
                                <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => setIssueOn(row)}>Issue</button>
                                <button className="btn btn-ghost px-2 py-1 text-xs text-slate-600"
                                    onClick={() => window.confirm(`Tutup kanban ${row.code}?`) && close.mutate(row.id)}>Tutup</button>
                            </>
                        )}
                    </div>
                )} />

            {createFor && <CreateModal onClose={() => setCreateFor(false)} onSaved={() => { setCreateFor(false); invalidate(); }} />}
            {issueOn && <IssueModal kanban={issueOn} onClose={() => setIssueOn(null)} onSaved={invalidate} />}
        </div>
    );
}

/** Raise kanbans from the RM lines a Work Order already booked. */
function CreateModal({ onClose, onSaved }) {
    const [woId, setWoId] = useState('');
    const [picked, setPicked] = useState({});

    const wos = useOptions('work-orders', { status: 2 });
    const wo = useQuery({
        queryKey: ['work-orders', woId],
        queryFn: async () => (await api.get(`/work-orders/${woId}`)).data.data,
        enabled: !!woId,
    });

    const save = useMutation({
        mutationFn: async () => api.post('/kanbans', {
            wo_id: woId,
            items: Object.entries(picked)
                .filter(([, qty]) => Number(qty) > 0)
                .map(([id, qty]) => {
                    const line = wo.data.detail_rm.find((l) => String(l.id) === id);
                    return { item_id: line.rm_id, rm_detail_id: line.id, qty: Number(qty) };
                }),
        }),
        onSuccess: onSaved,
        onError: (e) => alert(apiError(e)),
    });

    const lines = wo.data?.detail_rm || [];
    const anyPicked = Object.values(picked).some((q) => Number(q) > 0);

    return (
        <Modal open onClose={onClose} size="max-w-3xl" title="Buat Kanban dari Work Order"
            footer={
                <>
                    <button className="btn btn-ghost" onClick={onClose}>Batal</button>
                    <button className="btn btn-primary" disabled={!anyPicked || save.isPending} onClick={() => save.mutate()}>
                        {save.isPending ? 'Menyimpan…' : 'Buat Kanban'}
                    </button>
                </>
            }>
            <div className="mb-4">
                <label className="field-label">Work Order (status RELEASED)</label>
                <Select value={woId} onChange={(v) => { setWoId(v); setPicked({}); }} options={wos.data}
                    getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.fg?.code || ''} (qty ${o.qty})`} />
            </div>

            {wo.isLoading && <div className="py-6 text-center text-slate-400">Memuat baris material…</div>}
            {wo.data && lines.length === 0 && (
                <div className="rounded bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Work Order ini belum punya baris RM, jadi tidak ada yang bisa dibuatkan kanban.
                </div>
            )}
            {lines.length > 0 && (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th className="px-2 py-2">Material</th>
                            <th className="px-2 py-2">Butuh (pcs)</th>
                            <th className="px-2 py-2">Sudah dibooking</th>
                            <th className="px-2 py-2">Qty Kanban</th>
                        </tr>
                    </thead>
                    <tbody>
                        {lines.map((l) => (
                            <tr key={l.id} className="border-t border-slate-100">
                                <td className="px-2 py-2">{l.rm?.code}<div className="text-xs text-slate-400">{l.rm?.part_name}</div></td>
                                <td className="px-2 py-2">{money(l.req_qty)}</td>
                                <td className="px-2 py-2">{money(l.booked_pcs)}</td>
                                <td className="px-2 py-2">
                                    <input type="number" min="0" className="field-input w-28" value={picked[l.id] ?? ''}
                                        onChange={(e) => setPicked({ ...picked, [l.id]: e.target.value })} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </Modal>
    );
}

/** Scan one serial out against a kanban. */
function IssueModal({ kanban, onClose, onSaved }) {
    const [serial, setSerial] = useState('');
    const [used, setUsed] = useState('');
    const [rem, setRem] = useState(false);
    const [remRack, setRemRack] = useState('');
    const racks = useOptions('racks');

    const issue = useMutation({
        mutationFn: async () => api.post(`/kanbans/${kanban.id}/issue`, {
            serial_id: serial,
            length_used: used === '' ? undefined : Number(used),
            rem,
            rem_rack_id: rem ? remRack : undefined,
        }),
        onSuccess: () => { setSerial(''); setUsed(''); onSaved(); },
        onError: (e) => alert(apiError(e)),
    });

    const remnantRacks = (racks.data || []).filter((r) => r.rem_rack);

    return (
        <Modal open onClose={onClose} size="max-w-lg" title={`Issue RM — ${kanban.code}`}
            footer={
                <>
                    <button className="btn btn-ghost" onClick={onClose}>Tutup</button>
                    <button className="btn btn-primary" disabled={!serial || issue.isPending || (rem && !remRack)} onClick={() => issue.mutate()}>
                        {issue.isPending ? 'Memproses…' : 'Keluarkan'}
                    </button>
                </>
            }>
            <div className="space-y-3">
                <div>
                    <label className="field-label">Scan Serial</label>
                    <input className="field-input font-mono" autoFocus value={serial} placeholder="scan atau ketik nomor serial"
                        onChange={(e) => setSerial(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && serial && issue.mutate()} />
                </div>
                <div>
                    <label className="field-label">Panjang dipakai (mm)</label>
                    <input type="number" step="0.01" className="field-input" value={used} onChange={(e) => setUsed(e.target.value)} />
                    <p className="mt-1 text-xs text-slate-400">Kosongkan bila seluruh batang habis terpakai.</p>
                </div>
                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={rem} onChange={(e) => setRem(e.target.checked)} />
                    Kembalikan sisa potongan ke rak remnant
                </label>
                {rem && (
                    <div>
                        <label className="field-label">Rak remnant</label>
                        <Select value={remRack} onChange={setRemRack} options={remnantRacks}
                            getValue={(o) => o.id} getLabel={(o) => `${o.location} — ${o.descriptions || ''}`}
                            placeholder={remnantRacks.length ? '— pilih rak —' : 'belum ada rak bertanda remnant'} />
                    </div>
                )}
            </div>
        </Modal>
    );
}
