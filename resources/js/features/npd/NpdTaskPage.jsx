import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import { StatusBadge } from '../procurement/common';
import { PHASES } from './common';

/**
 * Task saya di seluruh proyek NPD.
 *
 * Anggota tim biasanya terlibat di beberapa proyek sekaligus; membuka satu per
 * satu untuk tahu apa yang harus dikerjakan hari ini adalah cara paling cepat
 * membuat orang berhenti memakai modulnya.
 */
export default function NpdTaskPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);

    const tasks = useQuery({
        queryKey: ['npd-my-tasks'],
        queryFn: async () => (await api.get('/npd/my-tasks')).data.data,
    });

    const save = useMutation({
        mutationFn: async ({ id, payload }) => api.put(`/npd-tasks/${id}`, payload),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['npd-my-tasks'] }),
        onError: (e) => alert(apiError(e)),
    });

    const rows = tasks.data || [];
    const late = rows.filter((t) => t.late);

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Task &amp; Milestone NPD</h1>
                <p className="text-sm text-slate-500">Task yang ditugaskan kepada Anda di seluruh proyek NPD yang sedang berjalan.</p>
            </div>

            {late.length > 0 && (
                <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    <Icon name="alert-triangle" className="inline h-4 w-4" /> {late.length} task sudah lewat tanggal rencana.
                </div>
            )}

            {tasks.isLoading ? (
                <p className="text-sm text-slate-400"><Icon name="spinner" className="inline h-4 w-4 animate-spin" /> Memuat…</p>
            ) : (
                <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                    <table className="w-full text-sm">
                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th className="px-3 py-2">Task</th><th className="px-3 py-2">Proyek</th>
                            <th className="px-3 py-2">Fase</th><th className="px-3 py-2">Rencana selesai</th>
                            <th className="px-3 py-2 text-right">Progres</th><th className="px-3 py-2 w-40">Status</th>
                        </tr></thead>
                        <tbody>
                            {rows.length === 0 && <tr><td colSpan={6} className="px-3 py-6 text-center text-slate-400">Tidak ada task terbuka untuk Anda.</td></tr>}
                            {rows.map((t) => (
                                <tr key={t.id} className={`border-t border-slate-100 ${t.late ? 'bg-amber-50' : ''}`}>
                                    <td className="px-3 py-1.5 font-medium">{t.name}</td>
                                    <td className="px-3 py-1.5 text-slate-600">{t.project_code} — {t.project_name}</td>
                                    <td className="px-3 py-1.5 text-xs text-slate-500">{t.phase_no} · {PHASES.find((p) => p.no === t.phase_no)?.short}</td>
                                    <td className={`px-3 py-1.5 ${t.late ? 'font-medium text-amber-700' : 'text-slate-500'}`}>{t.planned_end?.slice(0, 10) || '—'}</td>
                                    <td className="px-3 py-1.5 text-right">{t.progress_pct}%</td>
                                    <td className="px-3 py-1.5">
                                        {can('npd-tasks', 'edit') ? (
                                            <select className="field-input" value={t.status}
                                                onChange={(e) => save.mutate({ id: t.id, payload: { name: t.name, status: e.target.value } })}>
                                                <option value="OPEN">OPEN</option>
                                                <option value="RUNNING">RUNNING</option>
                                                <option value="DONE">DONE</option>
                                            </select>
                                        ) : <StatusBadge status={t.status} />}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
