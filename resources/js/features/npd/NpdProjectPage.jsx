import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { StatusBadge, money } from '../procurement/common';
import { PHASES, PROJECT_STATUS, PhaseBadge, PhaseRail, pendingMandatory } from './common';

const DELIVERABLE_STATUS = [
    ['OPEN', 'Belum dikerjakan'],
    ['IN_PROGRESS', 'Sedang dikerjakan'],
    ['DONE', 'Selesai'],
    ['WAIVED', 'Dikecualikan (wajib beralasan)'],
];

export default function NpdProjectPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [status, setStatus] = useState('');
    const [openId, setOpenId] = useState(null);
    const [tab, setTab] = useState(null);        // nomor fase yang dibuka
    const [error, setError] = useState('');

    const list = useQuery({
        queryKey: ['npd-projects', { page, status }],
        queryFn: async () => (await api.get('/npd-projects', { params: { page, per_page: 15, status: status || undefined } })).data,
    });
    const detail = useQuery({
        queryKey: ['npd-project', openId],
        queryFn: async () => (await api.get(`/npd-projects/${openId}`)).data.data,
        enabled: !!openId,
    });
    const dash = useQuery({
        queryKey: ['npd-dashboard'],
        queryFn: async () => (await api.get('/npd/dashboard')).data.data,
    });

    const invalidate = () => {
        qc.invalidateQueries({ queryKey: ['npd-projects'] });
        qc.invalidateQueries({ queryKey: ['npd-project'] });
        qc.invalidateQueries({ queryKey: ['npd-dashboard'] });
    };

    const gate = useMutation({
        mutationFn: async ({ id, phaseNo, action, body }) => api.post(`/npd-projects/${id}/gate/${phaseNo}/${action}`, body || {}),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const saveDeliverable = useMutation({
        mutationFn: async ({ id, payload }) => api.put(`/npd-deliverables/${id}`, payload),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const saveTask = useMutation({
        mutationFn: async ({ id, payload }) => api.put(`/npd-tasks/${id}`, payload),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const addTask = useMutation({
        mutationFn: async ({ phaseId, payload }) => api.post(`/npd-tasks/phase/${phaseId}`, payload),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const hold = useMutation({
        mutationFn: async (id) => api.post(`/npd-projects/${id}/hold`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const project = detail.data;
    const phases = project?.phases || [];
    const activeNo = tab || project?.current_phase_no || 1;
    const phase = phases.find((p) => p.phase_no === activeNo);
    const blocking = pendingMandatory(phase);

    const openProject = (row) => { setOpenId(row.id); setTab(null); setError(''); };

    const columns = [
        { key: 'code', label: 'Kode' },
        { key: 'name', label: 'Proyek' },
        { key: 'cus', label: 'Pelanggan', render: (v) => v?.company_n || '—' },
        { key: 'project_type', label: 'Tipe' },
        {
            key: 'current_phase_no',
            label: 'Fase',
            render: (v) => <span className="text-xs">{v} · {PHASES.find((p) => p.no === v)?.short}</span>,
        },
        { key: 'target_sop', label: 'Target SOP', render: (v) => v?.slice(0, 10) || '—' },
        { key: 'priority', label: 'Prioritas' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    const s = dash.data?.summary;

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Proyek NPD</h1>
                <p className="text-sm text-slate-500">
                    Pengembangan part baru dalam 5 fase APQP. Fase berikutnya terbuka setelah gate disetujui dua level — dan gate hanya boleh diajukan
                    kalau deliverable wajib fase itu sudah beres.
                </p>
            </div>

            {s && (
                <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-6">
                    {PHASES.map((p) => (
                        <div key={p.no} className="rounded-lg border border-slate-200 bg-white p-3">
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Fase {p.no}</p>
                            <p className="mt-0.5 text-lg font-semibold text-slate-800">{s.funnel[p.no] || 0}</p>
                            <p className="truncate text-[11px] text-slate-400">{p.short}</p>
                        </div>
                    ))}
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-amber-600">Perlu perhatian</p>
                        <p className="mt-0.5 text-sm text-amber-800">{s.pending_gates} gate menunggu</p>
                        <p className="text-[11px] text-amber-700">{s.late_tasks} task &amp; {s.late_projects} proyek telat</p>
                    </div>
                </div>
            )}

            <div className="mb-3 flex items-end gap-3">
                <div>
                    <label className="field-label">Status</label>
                    <select className="field-input w-44" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }}>
                        <option value="">Semua status</option>
                        {PROJECT_STATUS.map((x) => <option key={x} value={x}>{x}</option>)}
                    </select>
                </div>
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Buka" className="rounded p-1.5 text-slate-500 hover:bg-slate-100" onClick={() => openProject(row)}><Icon name="eye" /></button>
                        {['RUNNING', 'ON_HOLD'].includes(row.status) && can('npd-projects', 'edit') && (
                            <button title={row.status === 'RUNNING' ? 'Tahan sementara' : 'Lanjutkan'} className="rounded p-1.5 text-amber-600 hover:bg-amber-50"
                                onClick={() => hold.mutate(row.id)}><Icon name={row.status === 'RUNNING' ? 'lock' : 'play'} /></button>
                        )}
                    </div>
                )} />

            {/* ── Detail proyek ── */}
            <Modal open={!!openId} onClose={() => setOpenId(null)} size="max-w-[95rem]" title={project ? `${project.code} — ${project.name}` : 'Memuat…'}
                footer={<button className="btn btn-ghost" onClick={() => setOpenId(null)}>Tutup</button>}>
                {detail.isLoading && <p className="text-sm text-slate-400"><Icon name="spinner" className="inline h-4 w-4 animate-spin" /> Memuat…</p>}

                {project && (<>
                    <div className="mb-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                        <div><p className="text-xs text-slate-400">Pelanggan</p><p>{project.cus?.company_n || '—'}</p></div>
                        <div><p className="text-xs text-slate-400">Part</p><p>{project.part_name}</p></div>
                        <div><p className="text-xs text-slate-400">Drawing</p><p>{project.drawing_no || '—'}</p></div>
                        <div><p className="text-xs text-slate-400">Target SOP</p><p>{project.target_sop?.slice(0, 10) || '—'}</p></div>
                        <div><p className="text-xs text-slate-400">PM</p><p>{project.pm?.name || '—'}</p></div>
                        <div><p className="text-xs text-slate-400">Tipe</p><p>{project.project_type}</p></div>
                        <div><p className="text-xs text-slate-400">Prioritas</p><p>{project.priority}</p></div>
                        <div><p className="text-xs text-slate-400">Status</p><StatusBadge status={project.status} /></div>
                    </div>

                    <div className="mb-4">
                        <PhaseRail phases={phases} current={activeNo} onPick={setTab} />
                    </div>

                    {phase && (<>
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <div className="text-sm">
                                <span className="font-semibold text-slate-700">Fase {phase.phase_no} — {phase.phase?.name}</span>
                                <span className="ml-2"><PhaseBadge status={phase.status} /></span>
                                {phase.actual_start && <span className="ml-2 text-xs text-slate-500">mulai {phase.actual_start.slice(0, 10)}</span>}
                            </div>
                            <div className="flex gap-2">
                                {phase.status === 'RUNNING' && can('npd-projects', 'edit') && (
                                    <button className="btn btn-primary px-3 py-1.5 text-xs" disabled={blocking.length > 0 || gate.isPending}
                                        title={blocking.length ? 'Masih ada deliverable wajib yang belum beres' : undefined}
                                        onClick={() => gate.mutate({ id: project.id, phaseNo: phase.phase_no, action: 'submit' })}>
                                        <Icon name="send" /> Ajukan Gate {phase.phase_no}
                                    </button>
                                )}
                                {phase.status === 'SUBMITTED' && can('npd-projects', 'edit') && (<>
                                    <button className="btn btn-ghost px-3 py-1.5 text-xs text-emerald-700"
                                        onClick={() => gate.mutate({ id: project.id, phaseNo: phase.phase_no, action: 'approve' })}>
                                        <Icon name="check" /> Setujui
                                    </button>
                                    <button className="btn btn-ghost px-3 py-1.5 text-xs text-red-700"
                                        onClick={() => {
                                            const note = window.prompt('Alasan penolakan gate:');
                                            if (note) gate.mutate({ id: project.id, phaseNo: phase.phase_no, action: 'reject', body: { note } });
                                        }}>
                                        <Icon name="ban" /> Tolak
                                    </button>
                                </>)}
                            </div>
                        </div>

                        {phase.status === 'SUBMITTED' && (
                            <p className="mb-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                Gate ini menunggu dua persetujuan: pengaju proyek, lalu SPV. Keputusannya juga muncul di inbox Approval bersama.
                            </p>
                        )}
                        {blocking.length > 0 && phase.status === 'RUNNING' && (
                            <p className="mb-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">
                                Gate belum bisa diajukan — deliverable wajib yang belum beres: {blocking.map((d) => d.title).join(', ')}.
                            </p>
                        )}

                        {/* Deliverable */}
                        <h3 className="mb-2 text-sm font-semibold text-slate-700">Deliverable fase ini</h3>
                        <div className="mb-5 overflow-x-auto rounded-md border border-slate-200">
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                    <th className="px-2 py-2">Deliverable</th><th className="px-2 py-2">Wajib</th>
                                    <th className="px-2 py-2 w-56">Status</th><th className="px-2 py-2">Catatan</th>
                                </tr></thead>
                                <tbody>
                                    {(phase.deliverables || []).length === 0 && <tr><td colSpan={4} className="px-2 py-4 text-center text-slate-400">Belum ada deliverable.</td></tr>}
                                    {(phase.deliverables || []).map((d) => (
                                        <tr key={d.id} className="border-t border-slate-100">
                                            <td className="px-2 py-1.5">{d.title}</td>
                                            <td className="px-2 py-1.5">{d.std?.mandatory ? <span className="text-xs font-medium text-red-600">wajib</span> : <span className="text-xs text-slate-400">opsional</span>}</td>
                                            <td className="px-2 py-1.5">
                                                <select className="field-input" value={d.status}
                                                    disabled={['SUBMITTED', 'APPROVED'].includes(phase.status) || !can('npd-tasks', 'edit')}
                                                    onChange={(e) => {
                                                        const next = e.target.value;
                                                        let note = d.note || '';
                                                        if (next === 'WAIVED') {
                                                            note = window.prompt('Alasan pengecualian deliverable ini:', note) || '';
                                                            if (!note) return;
                                                        }
                                                        saveDeliverable.mutate({ id: d.id, payload: { status: next, note, title: d.title } });
                                                    }}>
                                                    {DELIVERABLE_STATUS.map(([v, label]) => <option key={v} value={v}>{label}</option>)}
                                                </select>
                                            </td>
                                            <td className="px-2 py-1.5 text-xs text-slate-500">{d.note || '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Task */}
                        <div className="mb-2 flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-slate-700">Task fase ini</h3>
                            {!['SUBMITTED', 'APPROVED'].includes(phase.status) && can('npd-tasks', 'create') && (
                                <button className="btn btn-ghost px-2 py-1 text-xs"
                                    onClick={() => {
                                        const name = window.prompt('Nama task:');
                                        if (name) addTask.mutate({ phaseId: phase.id, payload: { name, status: 'OPEN', progress_pct: 0 } });
                                    }}><Icon name="plus" className="h-3.5 w-3.5" /> Task</button>
                            )}
                        </div>
                        <div className="overflow-x-auto rounded-md border border-slate-200">
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                    <th className="px-2 py-2">Task</th><th className="px-2 py-2">PIC</th>
                                    <th className="px-2 py-2">Rencana selesai</th><th className="px-2 py-2 text-right">Progres</th>
                                    <th className="px-2 py-2 w-40">Status</th>
                                </tr></thead>
                                <tbody>
                                    {(phase.tasks || []).length === 0 && <tr><td colSpan={5} className="px-2 py-4 text-center text-slate-400">Belum ada task.</td></tr>}
                                    {(phase.tasks || []).map((t) => {
                                        const late = t.status !== 'DONE' && t.planned_end && t.planned_end.slice(0, 10) < new Date().toISOString().slice(0, 10);
                                        return (
                                            <tr key={t.id} className={`border-t border-slate-100 ${late ? 'bg-amber-50' : ''}`}>
                                                <td className="px-2 py-1.5">{t.name}</td>
                                                <td className="px-2 py-1.5 text-slate-500">{t.assignee?.name || '—'}</td>
                                                <td className={`px-2 py-1.5 ${late ? 'font-medium text-amber-700' : 'text-slate-500'}`}>{t.planned_end?.slice(0, 10) || '—'}</td>
                                                <td className="px-2 py-1.5 text-right">{t.progress_pct}%</td>
                                                <td className="px-2 py-1.5">
                                                    <select className="field-input" value={t.status}
                                                        disabled={['SUBMITTED', 'APPROVED'].includes(phase.status) || !can('npd-tasks', 'edit')}
                                                        onChange={(e) => saveTask.mutate({ id: t.id, payload: { name: t.name, status: e.target.value } })}>
                                                        <option value="OPEN">OPEN</option>
                                                        <option value="RUNNING">RUNNING</option>
                                                        <option value="DONE">DONE</option>
                                                        <option value="CANCELLED">CANCELLED</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </>)}

                    {/* Milestone & tim */}
                    <div className="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <div>
                            <h3 className="mb-2 text-sm font-semibold text-slate-700">Milestone</h3>
                            <div className="overflow-hidden rounded-md border border-slate-200">
                                <table className="w-full text-sm">
                                    <tbody>
                                        {(project.milestones || []).length === 0 && <tr><td className="px-2 py-3 text-center text-slate-400">Belum ada milestone.</td></tr>}
                                        {(project.milestones || []).map((m) => (
                                            <tr key={m.id} className="border-t border-slate-100">
                                                <td className="px-2 py-1.5">{m.name}</td>
                                                <td className="px-2 py-1.5 text-slate-500">{m.planned_date?.slice(0, 10)}</td>
                                                <td className="px-2 py-1.5"><StatusBadge status={m.status} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h3 className="mb-2 text-sm font-semibold text-slate-700">Tim proyek</h3>
                            <div className="overflow-hidden rounded-md border border-slate-200">
                                <table className="w-full text-sm">
                                    <tbody>
                                        {(project.members || []).length === 0 && <tr><td className="px-2 py-3 text-center text-slate-400">Belum ada anggota.</td></tr>}
                                        {(project.members || []).map((m) => (
                                            <tr key={m.id} className="border-t border-slate-100">
                                                <td className="px-2 py-1.5">{m.user?.name || `#${m.user_id}`}</td>
                                                <td className="px-2 py-1.5 text-slate-500">{m.role}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <p className="mt-4 text-xs text-slate-400">
                        BOM, costing, trial, FMEA, control plan, dan PPAP menyusul di tahap berikutnya (lihat PRD §12). Dokumen sudah bisa diunggah lewat API.
                    </p>
                </>)}
            </Modal>
        </div>
    );
}
