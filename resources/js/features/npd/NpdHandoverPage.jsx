import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, StatusBadge, useOptions, money } from '../procurement/common';

/**
 * Layar SPV: proyek yang lolos gate terakhir, dan serah terimanya ke produksi.
 *
 * Tombolnya sengaja tidak berdiri sendiri — di sebelahnya ada pratinjau apa yang
 * akan dibuat dan daftar apa yang masih menahan. Keputusan diambil sambil
 * melihat isinya, bukan setelah menekan.
 */
export default function NpdHandoverPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [openId, setOpenId] = useState(null);
    const [cycles, setCycles] = useState([]);
    const [error, setError] = useState('');

    const machines = useOptions('machines');

    const list = useQuery({
        queryKey: ['npd-projects', 'handover'],
        queryFn: async () => (await api.get('/npd-projects', { params: { per_page: 100 } })).data.data,
    });
    const preview = useQuery({
        queryKey: ['npd-handover-preview', openId],
        queryFn: async () => {
            const { data } = await api.get(`/npd-projects/${openId}/handover-preview`);
            // Cycle time diisi per langkah routing yang akan dibuat.
            setCycles((data.data.will_create.routing?.steps || []).map((s) => ({
                proc_id: s.proc_id, name: s.name, cycle_sec: '', machine_id: '', setup_min: '',
            })));
            return data.data;
        },
        enabled: !!openId,
    });

    const handover = useMutation({
        mutationFn: async ({ id, payload }) => api.post(`/npd-projects/${id}/handover`, payload),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['npd-projects'] });
            qc.invalidateQueries({ queryKey: ['npd-dashboard'] });
            setOpenId(null);
        },
        onError: (e) => setError(apiError(e)),
    });

    const rows = list.data || [];
    const ready = rows.filter((p) => p.status === 'HANDOVER');
    const done = rows.filter((p) => p.status === 'CLOSED' && p.handover_date);
    const p = preview.data;

    const setCycle = (i, k, v) => setCycles((c) => c.map((x, j) => (j === i ? { ...x, [k]: v } : x)));

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Serah Terima Produksi</h1>
                <p className="text-sm text-slate-500">
                    Kewenangan SPV. Sekali tekan: BOM produksi, routing, cycle time, dan parameter inspeksi dibuat dari hasil proyek —
                    lalu part naik dari uji coba ke produksi massal.
                </p>
            </div>

            <h2 className="mb-2 text-sm font-semibold text-slate-700">Menunggu serah terima</h2>
            <div className="mb-6 overflow-x-auto rounded-md border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                        <th className="px-3 py-2">Kode</th><th className="px-3 py-2">Proyek</th>
                        <th className="px-3 py-2">Pelanggan</th><th className="px-3 py-2">Part</th>
                        <th className="px-3 py-2">Target SOP</th><th className="px-3 py-2 text-right"></th>
                    </tr></thead>
                    <tbody>
                        {list.isLoading && <tr><td colSpan={6} className="px-3 py-6 text-center text-slate-400"><Icon name="spinner" className="inline h-4 w-4 animate-spin" /> Memuat…</td></tr>}
                        {!list.isLoading && ready.length === 0 && (
                            <tr><td colSpan={6} className="px-3 py-6 text-center text-slate-400">Belum ada proyek yang lolos gate terakhir.</td></tr>
                        )}
                        {ready.map((r) => (
                            <tr key={r.id} className="border-t border-slate-100">
                                <td className="px-3 py-1.5 font-medium">{r.code}</td>
                                <td className="px-3 py-1.5">{r.name}</td>
                                <td className="px-3 py-1.5 text-slate-600">{r.cus?.company_n || '—'}</td>
                                <td className="px-3 py-1.5 text-slate-600">{r.part_name}</td>
                                <td className="px-3 py-1.5 text-slate-500">{r.target_sop?.slice(0, 10) || '—'}</td>
                                <td className="px-3 py-1.5 text-right">
                                    {can('npd-handover', 'view') && (
                                        <button className="btn btn-primary px-2 py-1 text-xs" onClick={() => { setError(''); setOpenId(r.id); }}>
                                            <Icon name="check-circle" className="h-3.5 w-3.5" /> Tinjau &amp; Serahkan
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {done.length > 0 && (<>
                <h2 className="mb-2 text-sm font-semibold text-slate-700">Sudah diserahterimakan</h2>
                <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                    <table className="w-full text-sm">
                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th className="px-3 py-2">Kode</th><th className="px-3 py-2">Proyek</th>
                            <th className="px-3 py-2">Part</th><th className="px-3 py-2">Tanggal</th><th className="px-3 py-2">Status</th>
                        </tr></thead>
                        <tbody>
                            {done.map((r) => (
                                <tr key={r.id} className="border-t border-slate-100">
                                    <td className="px-3 py-1.5 font-medium">{r.code}</td>
                                    <td className="px-3 py-1.5">{r.name}</td>
                                    <td className="px-3 py-1.5 text-slate-600">{r.part_name}</td>
                                    <td className="px-3 py-1.5 text-slate-500">{r.handover_date?.slice(0, 10)}</td>
                                    <td className="px-3 py-1.5"><StatusBadge status={r.status} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </>)}

            <p className="mt-4 rounded-md border border-slate-200 bg-white px-3 py-3 text-sm text-slate-600">
                <Icon name="check" className="mr-1 inline h-4 w-4 text-slate-400" />
                Gate yang menunggu keputusan Anda muncul di menu <b>Approval Persetujuan</b> bersama PO, MPS, dan ECN —
                satu inbox untuk semua dokumen.
            </p>

            {/* ── Tinjau & serahkan ── */}
            <Modal open={!!openId} onClose={() => setOpenId(null)} size="max-w-[80rem]"
                title={p ? `Serah Terima — ${p.project.code} · ${p.project.part_name}` : 'Memuat…'}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setOpenId(null)}>Tutup</button>
                    {p?.can_handover && can('npd-handover', 'edit') && (
                        <button className="btn btn-primary" disabled={handover.isPending}
                            onClick={() => window.confirm(
                                'Serahkan proyek ini ke produksi?\n\n'
                                + 'BOM, routing, cycle time, dan parameter inspeksi akan dibuat, lalu part naik ke produksi massal.',
                            ) && handover.mutate({ id: openId, payload: { cycle_times: cycles.filter((c) => c.cycle_sec) } })}>
                            {handover.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="check-circle" />} Serahkan ke Produksi
                        </button>
                    )}
                </>}>
                {preview.isLoading && <p className="text-sm text-slate-400"><Icon name="spinner" className="inline h-4 w-4 animate-spin" /> Memuat…</p>}
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                {p && (<>
                    {p.blockers.length > 0 ? (
                        <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-3">
                            <p className="mb-1 text-sm font-semibold text-red-800">Belum bisa diserahterimakan:</p>
                            <ul className="list-inside list-disc text-sm text-red-700">
                                {p.blockers.map((b, i) => <li key={i}>{b}</li>)}
                            </ul>
                        </div>
                    ) : (
                        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            Semua syarat terpenuhi. Yang akan dibuat ada di bawah — semuanya berstatus DRAFT dan tetap melewati approval engineering.
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div className="rounded-md border border-slate-200 p-3">
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">BOM Produksi</p>
                            {p.will_create.bom ? (<>
                                <p className="text-sm text-slate-700">dari preliminary BOM {p.will_create.bom.version}</p>
                                <p className="text-sm text-slate-500">{p.will_create.bom.rm_lines} baris RM · {p.will_create.bom.pm_lines} baris PM</p>
                                {p.will_create.bom.exists_already && <p className="mt-1 text-[11px] text-amber-700">BOM sudah pernah dibuat — tidak diulang.</p>}
                            </>) : <p className="text-sm text-slate-400">tidak ada</p>}
                        </div>

                        <div className="rounded-md border border-slate-200 p-3">
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Routing (WOS)</p>
                            {p.will_create.routing ? (<>
                                <p className="text-sm text-slate-700">{p.will_create.routing.steps.length} langkah</p>
                                <p className="text-xs text-slate-500">{p.will_create.routing.steps.map((s) => s.name).join(' → ')}</p>
                                <p className="mt-1 text-[11px] text-slate-400">urutan diambil dari {p.will_create.routing.source}</p>
                            </>) : <p className="text-sm text-slate-400">tidak ada control plan final</p>}
                        </div>

                        <div className="rounded-md border border-slate-200 p-3">
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Parameter QC</p>
                            <p className="text-sm text-slate-700">{p.will_create.inspection_params.length} parameter</p>
                            <ul className="mt-1 text-xs text-slate-500">
                                {p.will_create.inspection_params.slice(0, 4).map((x, i) => (
                                    <li key={i}>{x.param} ({x.min_value ?? '—'} … {x.max_value ?? '—'})</li>
                                ))}
                                {p.will_create.inspection_params.length > 4 && <li>…dan {p.will_create.inspection_params.length - 4} lagi</li>}
                            </ul>
                        </div>
                    </div>

                    {cycles.length > 0 && (
                        <div className="mt-5">
                            <h3 className="mb-1 text-sm font-semibold text-slate-700">Cycle time hasil trial</h3>
                            <p className="mb-2 text-xs text-slate-500">
                                Diisi process engineer, bukan dihitung sistem: angka yang dipakai perencanaan adalah cycle time yang sudah dinilai wajar,
                                bukan rata-rata mentah yang termasuk gangguan dan penyetelan. Langkah yang dikosongkan tidak dibuatkan baris.
                            </p>
                            <div className="overflow-x-auto rounded-md border border-slate-200">
                                <table className="w-full text-sm">
                                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                        <th className="px-3 py-2">Proses</th><th className="px-3 py-2 w-40">Cycle (detik)</th>
                                        <th className="px-3 py-2 w-52">Mesin</th><th className="px-3 py-2 w-40">Setup (menit)</th>
                                    </tr></thead>
                                    <tbody>
                                        {cycles.map((c, i) => (
                                            <tr key={c.proc_id} className="border-t border-slate-100">
                                                <td className="px-3 py-1.5">{c.name}</td>
                                                <td className="px-3 py-1.5">
                                                    <input type="number" step="0.01" className="field-input" value={c.cycle_sec}
                                                        onChange={(e) => setCycle(i, 'cycle_sec', e.target.value)} />
                                                </td>
                                                <td className="px-3 py-1.5">
                                                    <Select value={c.machine_id} onChange={(v) => setCycle(i, 'machine_id', v)} options={machines.data}
                                                        getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="— semua mesin —" />
                                                </td>
                                                <td className="px-3 py-1.5">
                                                    <input type="number" step="0.01" className="field-input" value={c.setup_min}
                                                        onChange={(e) => setCycle(i, 'setup_min', e.target.value)} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </>)}
            </Modal>
        </div>
    );
}
