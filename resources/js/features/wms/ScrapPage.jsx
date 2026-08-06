import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import Modal from '../../components/Modal';
import { money } from '../procurement/common';

/**
 * Scrap RM.
 *
 * The system flags a leftover bar once it is shorter than the smallest cut any
 * active BOM needs — but that is a proposal, not the decision. A supervisor
 * confirms it or rules it usable, and either way must say why, because the
 * reason is what the next stock-take argument gets settled with.
 */
export default function ScrapPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [deciding, setDeciding] = useState(null);
    const [historyFor, setHistoryFor] = useState(null);

    const list = useQuery({
        queryKey: ['scrap-rm'],
        queryFn: async () => (await api.get('/scrap-rm')).data.data,
    });

    const rows = list.data || [];
    const pending = rows.filter((r) => !r.last_decision);

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Scrap RM</h1>
                <p className="text-xs text-slate-400">
                    Sisa batang yang lebih pendek dari potongan terkecil di BOM aktif. Keputusan akhir tetap di tangan Anda — dan alasannya dicatat.
                </p>
            </div>

            {pending.length > 0 && (
                <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    {pending.length} sisa batang menunggu keputusan.
                </div>
            )}

            <div className="card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th className="px-4 py-3">Serial</th>
                            <th className="px-4 py-3">Material</th>
                            <th className="px-4 py-3">Work Order</th>
                            <th className="px-4 py-3">Sisa (mm)</th>
                            <th className="px-4 py-3">Min. BOM (mm)</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {list.isLoading && <tr><td colSpan={7} className="px-4 py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></td></tr>}
                        {!list.isLoading && rows.length === 0 && (
                            <tr><td colSpan={7} className="px-4 py-10 text-center text-slate-400">Tidak ada kandidat scrap. Semua sisa batang masih bisa dipakai.</td></tr>
                        )}
                        {rows.map((r) => (
                            <tr key={r.id} className="border-b border-slate-100 hover:bg-blue-50/40">
                                <td className="px-4 py-2.5 font-mono">{r.serial_id}</td>
                                <td className="px-4 py-2.5">{r.item_code}<div className="text-xs text-slate-400">{r.item_name}</div></td>
                                <td className="px-4 py-2.5">{r.wo_code}</td>
                                <td className="px-4 py-2.5 font-medium">{money(r.length_rem)}</td>
                                <td className="px-4 py-2.5 text-slate-500">{r.min_bom_length ? money(r.min_bom_length) : '—'}</td>
                                <td className="px-4 py-2.5">
                                    {r.last_decision
                                        ? <span className={`rounded-full px-2 py-0.5 text-xs ${r.last_decision === 'SCRAP' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'}`}>
                                            {r.last_decision === 'SCRAP' ? 'Diputuskan scrap' : 'Dinyatakan usable'}
                                        </span>
                                        : <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">Menunggu keputusan</span>}
                                    {r.last_reason && <div className="mt-0.5 text-xs text-slate-400">{r.last_reason}</div>}
                                </td>
                                <td className="px-4 py-2.5 text-right">
                                    <div className="flex justify-end gap-1">
                                        <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => setHistoryFor(r)}>Riwayat</button>
                                        {can('scrap-rm', 'edit') && (
                                            <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => setDeciding(r)}>Putuskan</button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {deciding && (
                <DecideModal row={deciding} onClose={() => setDeciding(null)}
                    onSaved={() => { setDeciding(null); qc.invalidateQueries({ queryKey: ['scrap-rm'] }); }} />
            )}
            {historyFor && <HistoryModal row={historyFor} onClose={() => setHistoryFor(null)} />}
        </div>
    );
}

function DecideModal({ row, onClose, onSaved }) {
    const [decision, setDecision] = useState(row.auto_candidate ? 'SCRAP' : 'USABLE');
    const [reason, setReason] = useState('');

    const save = useMutation({
        mutationFn: async () => api.post(`/scrap-rm/${row.id}/decide`, { decision, reason }),
        onSuccess: onSaved,
        onError: (e) => alert(apiError(e)),
    });

    const overriding = row.auto_candidate && decision === 'USABLE';

    return (
        <Modal open onClose={onClose} size="max-w-lg" title={`Keputusan Scrap — ${row.serial_id}`}
            footer={
                <>
                    <button className="btn btn-ghost" onClick={onClose}>Batal</button>
                    <button className="btn btn-primary" disabled={!reason.trim() || save.isPending} onClick={() => save.mutate()}>
                        {save.isPending ? 'Menyimpan…' : 'Simpan Keputusan'}
                    </button>
                </>
            }>
            <div className="mb-4 rounded bg-slate-50 px-3 py-2 text-sm">
                <div>Sisa <strong>{money(row.length_rem)} mm</strong>, potongan terkecil di BOM {row.min_bom_length ? `${money(row.min_bom_length)} mm` : 'tidak diketahui'}.</div>
                <div className="mt-1 text-xs text-slate-500">
                    {row.auto_candidate
                        ? 'Sistem mengusulkan batang ini discrap karena tidak cukup untuk produk mana pun.'
                        : 'Sistem menilai batang ini masih bisa dipakai.'}
                </div>
            </div>

            <div className="mb-3 flex gap-2">
                {['SCRAP', 'USABLE'].map((d) => (
                    <button key={d} onClick={() => setDecision(d)}
                        className={`grow rounded-md border px-3 py-2 text-sm ${decision === d
                            ? (d === 'SCRAP' ? 'border-red-400 bg-red-50 font-medium text-red-700' : 'border-emerald-400 bg-emerald-50 font-medium text-emerald-700')
                            : 'border-slate-200 text-slate-600'}`}>
                        {d === 'SCRAP' ? 'Scrap' : 'Masih Usable'}
                    </button>
                ))}
            </div>

            {overriding && (
                <div className="mb-3 rounded bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    Anda menyatakan usable padahal panjangnya di bawah kebutuhan BOM. Jelaskan alasannya — batang ini akan tetap muncul sebagai stok.
                </div>
            )}

            <label className="field-label">Alasan (wajib)</label>
            <textarea className="field-input h-24" value={reason} onChange={(e) => setReason(e.target.value)}
                placeholder="mis. akan dipakai untuk sampel uji tarik" />
        </Modal>
    );
}

function HistoryModal({ row, onClose }) {
    const q = useQuery({
        queryKey: ['scrap-rm', row.id, 'history'],
        queryFn: async () => (await api.get(`/scrap-rm/${row.id}/history`)).data.data,
    });

    return (
        <Modal open onClose={onClose} size="max-w-2xl" title={`Riwayat Keputusan — ${row.serial_id}`}
            footer={<button className="btn btn-ghost" onClick={onClose}>Tutup</button>}>
            {q.isLoading && <div className="py-6 text-center text-slate-400">Memuat…</div>}
            {q.data && q.data.length === 0 && <div className="py-6 text-center text-slate-400">Belum pernah diputuskan.</div>}
            {q.data && q.data.length > 0 && (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th className="px-2 py-2">Waktu</th><th className="px-2 py-2">Keputusan</th>
                            <th className="px-2 py-2">Sisa</th><th className="px-2 py-2">Usulan sistem</th><th className="px-2 py-2">Alasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {q.data.map((h) => (
                            <tr key={h.id} className="border-t border-slate-100">
                                <td className="px-2 py-2 whitespace-nowrap">{(h.created_at || '').replace('T', ' ').slice(0, 16)}</td>
                                <td className="px-2 py-2 font-medium">{h.decision}</td>
                                <td className="px-2 py-2">{money(h.length_rem)}</td>
                                <td className="px-2 py-2 text-xs text-slate-500">{h.auto_flag ? 'scrap' : 'usable'}</td>
                                <td className="px-2 py-2">{h.reason}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </Modal>
    );
}
