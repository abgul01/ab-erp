import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, StatusBadge, LineTable, CellInput, money } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);

const EMPTY_RISK = {
    proc_id: '', item_function: '', failure_mode: '', effect: '',
    severity: 5, cause: '', occurrence: 5, current_control: '', detection: 5,
    recommended_action: '', action_taken: '', status: 'OPEN',
};

const EMPTY_CONTROL = {
    proc_id: '', param_id: '', nominal: '', min_value: '', max_value: '',
    method: '', sample_size: 1, frequency: '', control_method: '', reaction_plan: '',
    to_item_inspection: true,
};

/** Warna RPN: yang di atas ambang harus terlihat tanpa perlu dicari. */
function rpnClass(rpn, threshold) {
    if (rpn >= threshold) return 'bg-red-100 text-red-700';
    if (rpn >= threshold * 0.6) return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-600';
}

/**
 * FMEA dan Control Plan dalam satu layar dua tab.
 *
 * Dipasang pada dua menu berbeda (npd-fmea dan npd-control-plan) karena
 * pemiliknya berbeda, tetapi isinya satu komponen — control plan disusun dari
 * FMEA, dan memisahkannya jadi dua halaman hanya membuat orang bolak-balik.
 */
export default function NpdQualityPage({ tab: initialTab = 'fmea' }) {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [projectId, setProjectId] = useState('');
    const [tab, setTab] = useState(initialTab);
    const [fmeaModal, setFmeaModal] = useState(null);
    const [riskModal, setRiskModal] = useState(null);
    const [cpModal, setCpModal] = useState(null);
    const [cpLines, setCpLines] = useState(null);
    const [genModal, setGenModal] = useState(null);
    const [error, setError] = useState('');

    const projects = useQuery({
        queryKey: ['npd-projects', 'quality'],
        queryFn: async () => (await api.get('/npd-projects', { params: { per_page: 200 } })).data.data,
    });
    const data = useQuery({
        queryKey: ['npd-quality', projectId],
        queryFn: async () => (await api.get(`/npd-quality/project/${projectId}`)).data.data,
        enabled: !!projectId,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['npd-quality'] });
    const onErr = (e) => setError(apiError(e));

    const createFmea = useMutation({
        mutationFn: async (p) => api.post(`/npd-quality/project/${projectId}/fmea`, p),
        onSuccess: () => { invalidate(); setFmeaModal(null); },
        onError: onErr,
    });
    const saveRisks = useMutation({
        mutationFn: async ({ id, lines }) => api.post(`/npd-quality/fmea/${id}/lines`, { lines }),
        onSuccess: () => { invalidate(); setRiskModal(null); },
        onError: onErr,
    });
    const fmeaAction = useMutation({
        mutationFn: async ({ id, action }) => (action === 'delete'
            ? api.delete(`/npd-quality/fmea/${id}`)
            : api.post(`/npd-quality/fmea/${id}/${action}`)),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const createCp = useMutation({
        mutationFn: async (p) => api.post(`/npd-quality/project/${projectId}/control-plan`, p),
        onSuccess: () => { invalidate(); setCpModal(null); },
        onError: onErr,
    });
    const saveControls = useMutation({
        mutationFn: async ({ id, lines }) => api.post(`/npd-quality/control-plan/${id}/lines`, { lines }),
        onSuccess: () => { invalidate(); setCpLines(null); },
        onError: onErr,
    });
    const cpAction = useMutation({
        mutationFn: async ({ id, action, body }) => (action === 'delete'
            ? api.delete(`/npd-quality/control-plan/${id}`)
            : api.post(`/npd-quality/control-plan/${id}/${action}`, body || {})),
        onSuccess: () => { invalidate(); setGenModal(null); },
        onError: (e) => alert(apiError(e)),
    });

    const fmeas = data.data?.fmeas || [];
    const cps = data.data?.control_plans || [];
    const processes = data.data?.processes || [];
    const params = data.data?.params || [];
    const pfmeas = fmeas.filter((f) => f.fmea_type === 'PROCESS');

    const openRisks = (f) => {
        setError('');
        setRiskModal({
            id: f.id, code: f.code, type: f.fmea_type, threshold: f.rpn_threshold,
            lines: (f.detail || []).map((d) => ({
                proc_id: d.proc_id || '', item_function: d.item_function, failure_mode: d.failure_mode,
                effect: d.effect, severity: d.severity, cause: d.cause, occurrence: d.occurrence,
                current_control: d.current_control || '', detection: d.detection,
                recommended_action: d.recommended_action || '', action_taken: d.action_taken || '',
                status: d.status,
            })),
        });
    };

    const openControls = (c) => {
        setError('');
        setCpLines({
            id: c.id, code: c.code,
            lines: (c.detail || []).map((d) => ({
                seq: d.seq, proc_id: d.proc_id || '', param_id: d.param_id || '',
                nominal: d.nominal ?? '', min_value: d.min_value ?? '', max_value: d.max_value ?? '',
                method: d.method || '', sample_size: d.sample_size, frequency: d.frequency || '',
                control_method: d.control_method || '', reaction_plan: d.reaction_plan || '',
                fmea_det_id: d.fmea_det_id || null, to_item_inspection: !!d.to_item_inspection,
            })),
        });
    };

    const setRisk = (i, k, v) => setRiskModal((m) => ({ ...m, lines: m.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)) }));
    const setCtl = (i, k, v) => setCpLines((m) => ({ ...m, lines: m.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)) }));

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">FMEA &amp; Control Plan</h1>
                <p className="text-sm text-slate-500">
                    FMEA menjawab apa yang bisa salah dan seberapa besar akibatnya; control plan menjawab apa yang karena itu diukur di lantai produksi.
                    RPN dihitung sistem (S × O × D) — risiko di atas ambang wajib punya tindakan perbaikan.
                </p>
            </div>

            <div className="mb-4 min-w-[22rem] max-w-xl">
                <label className="field-label">Proyek NPD</label>
                <Select value={projectId} onChange={(v) => { setProjectId(v); setError(''); }} options={projects.data}
                    getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— pilih proyek —" />
            </div>

            {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

            {!projectId && <p className="rounded-md border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-400">Pilih proyek untuk melihat FMEA dan control plan-nya.</p>}

            {projectId && (<>
                <div className="mb-4 flex gap-1.5 border-b border-slate-200 pb-1">
                    {[['fmea', `FMEA (${fmeas.length})`], ['cp', `Control Plan (${cps.length})`]].map(([k, label]) => (
                        <button key={k} onClick={() => setTab(k)}
                            className={`rounded-t px-3 py-1.5 text-sm ${tab === k ? 'border-b-2 border-[var(--ftpi-primary)] font-semibold text-slate-800' : 'text-slate-500 hover:text-slate-700'}`}>
                            {label}
                        </button>
                    ))}
                </div>

                {/* ── FMEA ── */}
                {tab === 'fmea' && (<>
                    <div className="mb-2 flex justify-end">
                        {can('npd-fmea', 'create') && (
                            <button className="btn btn-primary" onClick={() => { setError(''); setFmeaModal({ fmea_type: 'PROCESS', revision: 'rev A', team: '', date: today(), rpn_threshold: 100 }); }}>
                                <Icon name="plus" /> FMEA Baru
                            </button>
                        )}
                    </div>
                    {fmeas.length === 0 && <p className="rounded-md border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-400">Belum ada FMEA pada proyek ini.</p>}

                    {fmeas.map((f) => (
                        <div key={f.id} className="mb-4 rounded-lg border border-slate-200 bg-white">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
                                <div className="text-sm">
                                    <span className="font-semibold text-slate-700">{f.code}</span>
                                    <span className="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600">{f.fmea_type === 'DESIGN' ? 'DFMEA — risiko desain' : 'PFMEA — risiko proses'}</span>
                                    <span className="ml-2"><StatusBadge status={f.status} /></span>
                                    <span className="ml-2 text-xs text-slate-500">{f.revision} · ambang RPN {f.rpn_threshold}</span>
                                    {f.above_threshold > 0 && (
                                        <span className="ml-2 rounded bg-red-100 px-1.5 py-0.5 text-[11px] font-medium text-red-700">
                                            {f.above_threshold} risiko di atas ambang
                                        </span>
                                    )}
                                </div>
                                <div className="flex gap-1">
                                    {f.status === 'DRAFT' && can('npd-fmea', 'edit') && (<>
                                        <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => openRisks(f)}>
                                            <Icon name="pencil" className="h-3.5 w-3.5" /> Baris Risiko
                                        </button>
                                        <button className="btn btn-primary px-2 py-1 text-xs" onClick={() => fmeaAction.mutate({ id: f.id, action: 'finalize' })}>
                                            <Icon name="check" className="h-3.5 w-3.5" /> Finalkan
                                        </button>
                                    </>)}
                                    {f.status === 'DRAFT' && can('npd-fmea', 'delete') && (
                                        <button className="btn btn-ghost px-2 py-1 text-xs text-red-700"
                                            onClick={() => window.confirm(`Hapus ${f.code}?`) && fmeaAction.mutate({ id: f.id, action: 'delete' })}>
                                            <Icon name="trash" className="h-3.5 w-3.5" />
                                        </button>
                                    )}
                                </div>
                            </div>

                            {(f.detail || []).length === 0 ? (
                                <p className="px-3 py-4 text-center text-sm text-slate-400">Belum ada baris risiko.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                            <th className="px-3 py-2">Proses / Fungsi</th><th className="px-3 py-2">Mode kegagalan</th>
                                            <th className="px-3 py-2">Akibat</th>
                                            <th className="px-2 py-2 text-center">S</th><th className="px-2 py-2 text-center">O</th><th className="px-2 py-2 text-center">D</th>
                                            <th className="px-2 py-2 text-center">RPN</th>
                                            <th className="px-3 py-2">Tindakan</th><th className="px-3 py-2">Status</th>
                                        </tr></thead>
                                        <tbody>
                                            {f.detail.map((d) => (
                                                <tr key={d.id} className="border-t border-slate-100">
                                                    <td className="px-3 py-1.5">
                                                        {d.proc?.name_p && <span className="mr-1 text-xs text-slate-500">{d.proc.name_p} ·</span>}
                                                        {d.item_function}
                                                    </td>
                                                    <td className="px-3 py-1.5">{d.failure_mode}</td>
                                                    <td className="px-3 py-1.5 text-slate-600">{d.effect}</td>
                                                    <td className="px-2 py-1.5 text-center">{d.severity}</td>
                                                    <td className="px-2 py-1.5 text-center">{d.occurrence}</td>
                                                    <td className="px-2 py-1.5 text-center">{d.detection}</td>
                                                    <td className="px-2 py-1.5 text-center">
                                                        <span className={`rounded px-1.5 py-0.5 text-xs font-semibold ${rpnClass(d.rpn, f.rpn_threshold)}`}>{d.rpn}</span>
                                                    </td>
                                                    <td className="px-3 py-1.5 text-xs text-slate-600">{d.recommended_action || '—'}</td>
                                                    <td className="px-3 py-1.5"><StatusBadge status={d.status} /></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    ))}
                </>)}

                {/* ── Control Plan ── */}
                {tab === 'cp' && (<>
                    <div className="mb-2 flex justify-end gap-2">
                        {can('npd-control-plan', 'create') && (
                            <button className="btn btn-primary" onClick={() => { setError(''); setCpModal({ cp_type: 'PROTOTYPE', revision: 'rev A', date: today() }); }}>
                                <Icon name="plus" /> Control Plan Baru
                            </button>
                        )}
                    </div>
                    {cps.length === 0 && <p className="rounded-md border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-400">Belum ada control plan pada proyek ini.</p>}

                    {cps.map((c) => (
                        <div key={c.id} className="mb-4 rounded-lg border border-slate-200 bg-white">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
                                <div className="text-sm">
                                    <span className="font-semibold text-slate-700">{c.code}</span>
                                    <span className="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600">{c.cp_type}</span>
                                    <span className="ml-2"><StatusBadge status={c.status} /></span>
                                    <span className="ml-2 text-xs text-slate-500">{c.revision}</span>
                                </div>
                                <div className="flex gap-1">
                                    {c.status === 'DRAFT' && can('npd-control-plan', 'edit') && (<>
                                        <button className="btn btn-ghost px-2 py-1 text-xs" disabled={pfmeas.length === 0}
                                            title={pfmeas.length === 0 ? 'Belum ada PFMEA pada proyek ini' : undefined}
                                            onClick={() => { setError(''); setGenModal({ id: c.id, code: c.code, fmea_id: pfmeas[0]?.id || '', replace: false }); }}>
                                            <Icon name="workflow" className="h-3.5 w-3.5" /> Susun dari PFMEA
                                        </button>
                                        <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => openControls(c)}>
                                            <Icon name="pencil" className="h-3.5 w-3.5" /> Baris Kendali
                                        </button>
                                        <button className="btn btn-primary px-2 py-1 text-xs" onClick={() => cpAction.mutate({ id: c.id, action: 'finalize' })}>
                                            <Icon name="check" className="h-3.5 w-3.5" /> Finalkan
                                        </button>
                                    </>)}
                                    {c.status === 'DRAFT' && can('npd-control-plan', 'delete') && (
                                        <button className="btn btn-ghost px-2 py-1 text-xs text-red-700"
                                            onClick={() => window.confirm(`Hapus ${c.code}?`) && cpAction.mutate({ id: c.id, action: 'delete' })}>
                                            <Icon name="trash" className="h-3.5 w-3.5" />
                                        </button>
                                    )}
                                </div>
                            </div>

                            {(c.detail || []).length === 0 ? (
                                <p className="px-3 py-4 text-center text-sm text-slate-400">Belum ada baris kendali.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                            <th className="px-2 py-2">#</th><th className="px-3 py-2">Proses</th>
                                            <th className="px-3 py-2">Karakteristik</th><th className="px-3 py-2 text-right">Spesifikasi</th>
                                            <th className="px-3 py-2">Metode</th><th className="px-2 py-2 text-right">Sampel</th>
                                            <th className="px-3 py-2">Frekuensi</th><th className="px-3 py-2">Reaction plan</th>
                                            <th className="px-2 py-2">→ QC</th>
                                        </tr></thead>
                                        <tbody>
                                            {c.detail.map((d) => (
                                                <tr key={d.id} className="border-t border-slate-100">
                                                    <td className="px-2 py-1.5 text-slate-400">{d.seq}</td>
                                                    <td className="px-3 py-1.5">{d.proc?.name_p || '—'}</td>
                                                    <td className="px-3 py-1.5">
                                                        {d.param ? `${d.param.code} — ${d.param.name}` : <span className="text-amber-700">belum dipilih</span>}
                                                        {d.fmea_det_id && <span className="ml-1 text-[11px] text-violet-600">dari PFMEA</span>}
                                                    </td>
                                                    <td className="px-3 py-1.5 text-right text-slate-600">
                                                        {d.min_value === null && d.max_value === null ? '—' : `${d.min_value ?? '—'} … ${d.max_value ?? '—'}`}
                                                    </td>
                                                    <td className="px-3 py-1.5 text-xs text-slate-500">{d.method || '—'}</td>
                                                    <td className="px-2 py-1.5 text-right">{d.sample_size}</td>
                                                    <td className="px-3 py-1.5 text-xs text-slate-500">{d.frequency || '—'}</td>
                                                    <td className="px-3 py-1.5 text-xs text-slate-500">{d.reaction_plan || '—'}</td>
                                                    <td className="px-2 py-1.5">{d.to_item_inspection ? <Icon name="check" className="h-4 w-4 text-emerald-600" /> : <span className="text-slate-300">—</span>}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            {c.status === 'DRAFT' && (c.detail || []).some((d) => d.to_item_inspection && (!d.param_id || (d.min_value === null && d.max_value === null))) && (
                                <p className="border-t border-slate-100 px-3 py-2 text-xs text-amber-700">
                                    Baris bertanda “→ QC” harus punya parameter dan batas ukur sebelum control plan bisa difinalkan —
                                    baris itu yang nanti jadi parameter inspeksi produksi.
                                </p>
                            )}
                        </div>
                    ))}
                </>)}
            </>)}

            {/* ── FMEA baru ── */}
            <Modal open={!!fmeaModal} onClose={() => setFmeaModal(null)} title="FMEA Baru"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setFmeaModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={createFmea.isPending} onClick={() => { setError(''); createFmea.mutate(fmeaModal); }}>
                        {createFmea.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {fmeaModal && (
                    <div className="space-y-3">
                        <div><label className="field-label">Jenis</label>
                            <select className="field-input" value={fmeaModal.fmea_type} onChange={(e) => setFmeaModal({ ...fmeaModal, fmea_type: e.target.value })}>
                                <option value="DESIGN">DFMEA — risiko desain produk</option>
                                <option value="PROCESS">PFMEA — risiko proses produksi</option>
                            </select></div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="field-label">Revisi</label>
                                <input className="field-input" value={fmeaModal.revision} maxLength={10} onChange={(e) => setFmeaModal({ ...fmeaModal, revision: e.target.value })} /></div>
                            <div><label className="field-label">Tanggal</label>
                                <input type="date" className="field-input" value={fmeaModal.date} onChange={(e) => setFmeaModal({ ...fmeaModal, date: e.target.value })} /></div>
                        </div>
                        <div><label className="field-label">Tim</label>
                            <input className="field-input" value={fmeaModal.team} maxLength={200} placeholder="Nama anggota tim FMEA" onChange={(e) => setFmeaModal({ ...fmeaModal, team: e.target.value })} /></div>
                        <div><label className="field-label">Ambang RPN wajib tindakan</label>
                            <input type="number" className="field-input" value={fmeaModal.rpn_threshold} onChange={(e) => setFmeaModal({ ...fmeaModal, rpn_threshold: e.target.value })} />
                            <p className="mt-0.5 text-[11px] text-slate-400">100 adalah kelaziman industri otomotif. Di atas ambang, tindakan perbaikan wajib diisi.</p></div>
                    </div>
                )}
            </Modal>

            {/* ── Baris risiko ── */}
            <Modal open={!!riskModal} onClose={() => setRiskModal(null)} size="max-w-[95rem]" title={`Baris Risiko — ${riskModal?.code || ''}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setRiskModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={saveRisks.isPending || !riskModal?.lines?.length}
                        onClick={() => { setError(''); saveRisks.mutate({ id: riskModal.id, lines: riskModal.lines }); }}>
                        {saveRisks.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {riskModal && (<>
                    <p className="mb-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        RPN = Severity × Occurrence × Detection, dihitung server. Baris dengan RPN ≥ <b>{riskModal.threshold}</b> wajib punya tindakan perbaikan,
                        dan tindakan itu harus selesai sebelum FMEA bisa difinalkan.
                    </p>
                    <LineTable title="Risiko" onAdd={() => setRiskModal({ ...riskModal, lines: [...riskModal.lines, { ...EMPTY_RISK }] })}
                        lines={riskModal.lines} empty="Belum ada baris risiko."
                        head={riskModal.type === 'PROCESS'
                            ? ['Proses', 'Fungsi', 'Mode kegagalan', 'Akibat', 'S', 'Sebab', 'O', 'Kendali saat ini', 'D', 'RPN', 'Tindakan', 'Status', '']
                            : ['', 'Fungsi', 'Mode kegagalan', 'Akibat', 'S', 'Sebab', 'O', 'Kendali saat ini', 'D', 'RPN', 'Tindakan', 'Status', '']}
                        row={(l, i) => {
                            const rpn = (Number(l.severity) || 0) * (Number(l.occurrence) || 0) * (Number(l.detection) || 0);
                            const over = rpn >= riskModal.threshold;
                            return (<>
                                <td className="min-w-[140px] px-2 py-1.5">
                                    {riskModal.type === 'PROCESS' ? (
                                        <Select value={l.proc_id} onChange={(v) => setRisk(i, 'proc_id', v)} options={processes}
                                            getValue={(o) => o.id} getLabel={(o) => o.name_p} placeholder="—" />
                                    ) : <span className="text-xs text-slate-400">—</span>}
                                </td>
                                <td className="min-w-[150px] px-2 py-1.5"><CellInput value={l.item_function} onChange={(v) => setRisk(i, 'item_function', v)} /></td>
                                <td className="min-w-[150px] px-2 py-1.5"><CellInput value={l.failure_mode} onChange={(v) => setRisk(i, 'failure_mode', v)} /></td>
                                <td className="min-w-[150px] px-2 py-1.5"><CellInput value={l.effect} onChange={(v) => setRisk(i, 'effect', v)} /></td>
                                <td className="w-16 px-1 py-1.5"><CellInput type="number" value={l.severity} onChange={(v) => setRisk(i, 'severity', v)} /></td>
                                <td className="min-w-[150px] px-2 py-1.5"><CellInput value={l.cause} onChange={(v) => setRisk(i, 'cause', v)} /></td>
                                <td className="w-16 px-1 py-1.5"><CellInput type="number" value={l.occurrence} onChange={(v) => setRisk(i, 'occurrence', v)} /></td>
                                <td className="min-w-[140px] px-2 py-1.5"><CellInput value={l.current_control} onChange={(v) => setRisk(i, 'current_control', v)} /></td>
                                <td className="w-16 px-1 py-1.5"><CellInput type="number" value={l.detection} onChange={(v) => setRisk(i, 'detection', v)} /></td>
                                <td className="w-16 px-1 py-1.5 text-center">
                                    <span className={`rounded px-1.5 py-0.5 text-xs font-semibold ${rpnClass(rpn, riskModal.threshold)}`}>{rpn}</span>
                                </td>
                                <td className="min-w-[170px] px-2 py-1.5">
                                    <CellInput value={l.recommended_action} onChange={(v) => setRisk(i, 'recommended_action', v)}
                                        className={over && !l.recommended_action ? 'border-red-400' : ''} />
                                </td>
                                <td className="w-28 px-2 py-1.5">
                                    <select className="field-input" value={l.status} onChange={(e) => setRisk(i, 'status', e.target.value)}>
                                        <option value="OPEN">OPEN</option><option value="DONE">DONE</option>
                                    </select>
                                </td>
                                <td className="px-2 py-1.5">
                                    <button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50"
                                        onClick={() => setRiskModal({ ...riskModal, lines: riskModal.lines.filter((_, j) => j !== i) })}><Icon name="trash" /></button>
                                </td>
                            </>);
                        }} />
                </>)}
            </Modal>

            {/* ── Control plan baru ── */}
            <Modal open={!!cpModal} onClose={() => setCpModal(null)} title="Control Plan Baru"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setCpModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={createCp.isPending} onClick={() => { setError(''); createCp.mutate(cpModal); }}>
                        {createCp.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {cpModal && (
                    <div className="space-y-3">
                        <div><label className="field-label">Tahap</label>
                            <select className="field-input" value={cpModal.cp_type} onChange={(e) => setCpModal({ ...cpModal, cp_type: e.target.value })}>
                                <option value="PROTOTYPE">Prototype</option>
                                <option value="PRE_LAUNCH">Pre-launch</option>
                                <option value="PRODUCTION">Production</option>
                            </select></div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="field-label">Revisi</label>
                                <input className="field-input" value={cpModal.revision} maxLength={10} onChange={(e) => setCpModal({ ...cpModal, revision: e.target.value })} /></div>
                            <div><label className="field-label">Tanggal</label>
                                <input type="date" className="field-input" value={cpModal.date} onChange={(e) => setCpModal({ ...cpModal, date: e.target.value })} /></div>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ── Susun dari PFMEA ── */}
            <Modal open={!!genModal} onClose={() => setGenModal(null)} title={`Susun dari PFMEA — ${genModal?.code || ''}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setGenModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={cpAction.isPending}
                        onClick={() => cpAction.mutate({ id: genModal.id, action: 'from-fmea', body: { fmea_id: genModal.fmea_id, replace: genModal.replace } })}>
                        {cpAction.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="workflow" />} Susun
                    </button>
                </>}>
                {genModal && (
                    <div className="space-y-3">
                        <p className="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Satu baris kendali dibuat untuk setiap risiko PFMEA di atas ambang RPN. Frekuensi, ukuran sampel, dan batas ukur tetap
                            keputusan process engineer — yang dijamin hanyalah tidak ada risiko besar yang lolos tanpa satu pun pengukuran.
                        </p>
                        <div><label className="field-label">PFMEA sumber</label>
                            <Select value={genModal.fmea_id} onChange={(v) => setGenModal({ ...genModal, fmea_id: v })} options={pfmeas}
                                getValue={(o) => o.id} getLabel={(o) => `${o.code} (${o.above_threshold} risiko di atas ambang)`} placeholder="— pilih PFMEA —" /></div>
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" className="h-4 w-4" checked={genModal.replace} onChange={(e) => setGenModal({ ...genModal, replace: e.target.checked })} />
                            Ganti baris yang sebelumnya berasal dari FMEA
                        </label>
                    </div>
                )}
            </Modal>

            {/* ── Baris kendali ── */}
            <Modal open={!!cpLines} onClose={() => setCpLines(null)} size="max-w-[95rem]" title={`Baris Kendali — ${cpLines?.code || ''}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setCpLines(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={saveControls.isPending || !cpLines?.lines?.length}
                        onClick={() => { setError(''); saveControls.mutate({ id: cpLines.id, lines: cpLines.lines }); }}>
                        {saveControls.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {cpLines && (
                    <LineTable title="Karakteristik yang dikendalikan"
                        subtitle="Baris bertanda “→ QC” akan menjadi parameter inspeksi item saat serah terima — hilangkan tandanya bila baris ini hanya mengendalikan setelan mesin."
                        onAdd={() => setCpLines({ ...cpLines, lines: [...cpLines.lines, { ...EMPTY_CONTROL, seq: cpLines.lines.length + 1 }] })}
                        lines={cpLines.lines} empty="Belum ada baris kendali."
                        head={['#', 'Proses', 'Karakteristik', 'Nominal', 'Min', 'Maks', 'Metode', 'Sampel', 'Frekuensi', 'Reaction plan', '→ QC', '']}
                        row={(l, i) => (<>
                            <td className="w-14 px-2 py-1.5"><CellInput type="number" value={l.seq} onChange={(v) => setCtl(i, 'seq', v)} /></td>
                            <td className="min-w-[150px] px-2 py-1.5">
                                <Select value={l.proc_id} onChange={(v) => setCtl(i, 'proc_id', v)} options={processes}
                                    getValue={(o) => o.id} getLabel={(o) => o.name_p} placeholder="—" />
                            </td>
                            <td className="min-w-[190px] px-2 py-1.5">
                                <Select value={l.param_id} onChange={(v) => setCtl(i, 'param_id', v)} options={params}
                                    getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— pilih —" />
                            </td>
                            <td className="w-24 px-2 py-1.5"><CellInput type="number" step="0.0001" value={l.nominal} onChange={(v) => setCtl(i, 'nominal', v)} /></td>
                            <td className="w-24 px-2 py-1.5"><CellInput type="number" step="0.0001" value={l.min_value} onChange={(v) => setCtl(i, 'min_value', v)} /></td>
                            <td className="w-24 px-2 py-1.5"><CellInput type="number" step="0.0001" value={l.max_value} onChange={(v) => setCtl(i, 'max_value', v)} /></td>
                            <td className="min-w-[130px] px-2 py-1.5"><CellInput value={l.method} onChange={(v) => setCtl(i, 'method', v)} /></td>
                            <td className="w-20 px-2 py-1.5"><CellInput type="number" value={l.sample_size} onChange={(v) => setCtl(i, 'sample_size', v)} /></td>
                            <td className="min-w-[120px] px-2 py-1.5"><CellInput value={l.frequency} onChange={(v) => setCtl(i, 'frequency', v)} /></td>
                            <td className="min-w-[170px] px-2 py-1.5"><CellInput value={l.reaction_plan} onChange={(v) => setCtl(i, 'reaction_plan', v)} /></td>
                            <td className="w-16 px-2 py-1.5 text-center">
                                <input type="checkbox" className="h-4 w-4" checked={!!l.to_item_inspection} onChange={(e) => setCtl(i, 'to_item_inspection', e.target.checked)} />
                            </td>
                            <td className="px-2 py-1.5">
                                <button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50"
                                    onClick={() => setCpLines({ ...cpLines, lines: cpLines.lines.filter((_, j) => j !== i) })}><Icon name="trash" /></button>
                            </td>
                        </>)} />
                )}
            </Modal>
        </div>
    );
}
