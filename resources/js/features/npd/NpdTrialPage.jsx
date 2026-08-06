import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, StatusBadge, LineTable, CellInput, useOptions, money } from '../procurement/common';

const TRIAL_TYPES = [
    ['PROTOTYPE', 'Prototype — beberapa potong pertama'],
    ['PILOT', 'Pilot — satu lot kecil di mesin produksi'],
    ['MASS_TRIAL', 'Mass trial — kecepatan penuh, syarat PPAP'],
];

const today = () => new Date().toISOString().slice(0, 10);

export default function NpdTrialPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [projectId, setProjectId] = useState('');
    const [form, setForm] = useState(null);
    const [results, setResults] = useState(null);
    const [finish, setFinish] = useState(null);
    const [error, setError] = useState('');

    const machines = useOptions('machines');
    const projects = useQuery({
        queryKey: ['npd-projects', 'trials'],
        queryFn: async () => (await api.get('/npd-projects', { params: { per_page: 200 } })).data.data,
    });
    const data = useQuery({
        queryKey: ['npd-trials', projectId],
        queryFn: async () => (await api.get(`/npd-trials/project/${projectId}`)).data.data,
        enabled: !!projectId,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['npd-trials'] });
    const onErr = (e) => setError(apiError(e));

    const save = useMutation({
        mutationFn: async (payload) => (payload.id
            ? api.put(`/npd-trials/${payload.id}`, payload)
            : api.post(`/npd-trials/project/${projectId}`, payload)),
        onSuccess: () => { invalidate(); setForm(null); },
        onError: onErr,
    });
    const saveResults = useMutation({
        mutationFn: async ({ id, rows }) => api.post(`/npd-trials/${id}/results`, { results: rows }),
        onSuccess: () => { invalidate(); setResults(null); },
        onError: onErr,
    });
    const doFinish = useMutation({
        mutationFn: async ({ id, payload }) => api.post(`/npd-trials/${id}/finish`, payload),
        onSuccess: () => { invalidate(); setFinish(null); },
        onError: onErr,
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/npd-trials/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const project = data.data?.project;
    const trials = data.data?.trials || [];
    const params = data.data?.params || [];

    const openResults = (t) => {
        setError('');
        setResults({
            id: t.id,
            code: t.code,
            rows: (t.detail || []).length
                ? t.detail.map((d) => ({
                    param_id: d.param_id, sample_no: d.sample_no, nominal: d.nominal ?? '',
                    min_value: d.min_value ?? '', max_value: d.max_value ?? '',
                    measured: d.measured, instrument: d.instrument || '',
                }))
                : [],
        });
    };

    const addResultRow = () => setResults((r) => ({
        ...r,
        rows: [...r.rows, { param_id: '', sample_no: (r.rows.at(-1)?.sample_no || 1), nominal: '', min_value: '', max_value: '', measured: '', instrument: '' }],
    }));

    /** Memilih parameter langsung menarik spesifikasi yang berlaku untuk part ini. */
    const pickParam = (i, paramId) => {
        const p = params.find((x) => x.id === paramId);
        setResults((r) => ({
            ...r,
            rows: r.rows.map((row, j) => (j === i ? {
                ...row,
                param_id: paramId,
                nominal: p?.nominal ?? '',
                min_value: p?.min_value ?? '',
                max_value: p?.max_value ?? '',
                _source: p?.source,
                _uom: p?.uom,
            } : row)),
        }));
    };

    const setRow = (i, k, v) => setResults((r) => ({ ...r, rows: r.rows.map((row, j) => (j === i ? { ...row, [k]: v } : row)) }));

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Trial &amp; Hasil Ukur</h1>
                <p className="text-sm text-slate-500">
                    Setiap trial dijalankan lewat Work Order-nya sendiri, jadi pemakaian material dan jam mesinnya tercatat seperti produksi biasa —
                    tetapi keluarannya tidak dihitung MRP sebagai barang siap jual.
                </p>
            </div>

            <div className="mb-4 flex flex-wrap items-end gap-3">
                <div className="min-w-[22rem]">
                    <label className="field-label">Proyek NPD</label>
                    <Select value={projectId} onChange={(v) => { setProjectId(v); setError(''); }} options={projects.data}
                        getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— pilih proyek —" />
                </div>
                {project && can('npd-trials', 'create') && (
                    <button className="btn btn-primary" disabled={!project.item_id}
                        title={!project.item_id ? 'Daftarkan part ke master item dulu (layar BOM & Costing)' : undefined}
                        onClick={() => { setError(''); setForm({ date: today(), trial_type: 'PROTOTYPE', planned_qty: 10, machine_id: '' }); }}>
                        <Icon name="plus" /> Trial Baru
                    </button>
                )}
                {project && !project.item_id && (
                    <span className="mb-2 rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-700">
                        Part belum terdaftar di master item — Work Order trial butuh nomor part.
                    </span>
                )}
            </div>

            {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

            {!projectId && <p className="rounded-md border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-400">Pilih proyek untuk melihat trial-nya.</p>}

            {projectId && trials.length === 0 && (
                <p className="rounded-md border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-400">Belum ada trial pada proyek ini.</p>
            )}

            {trials.map((t) => (
                <div key={t.id} className="mb-4 rounded-lg border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
                        <div className="text-sm">
                            <span className="font-semibold text-slate-700">{t.code}</span>
                            <span className="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600">{t.trial_type}</span>
                            <span className="ml-2"><StatusBadge status={t.status} /></span>
                            <span className="ml-2 text-xs text-slate-500">{t.date?.slice(0, 10)}</span>
                            {t.wo && <span className="ml-2 text-xs text-violet-700">WO {t.wo.code}</span>}
                            {t.machine && <span className="ml-2 text-xs text-slate-500">mesin {t.machine.code}</span>}
                        </div>
                        <div className="flex gap-1">
                            {t.status === 'DRAFT' && can('npd-trials', 'edit') && (<>
                                <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => { setError(''); setForm({ id: t.id, date: t.date?.slice(0, 10), trial_type: t.trial_type, planned_qty: t.planned_qty, machine_id: t.machine_id || '' }); }}>
                                    <Icon name="pencil" className="h-3.5 w-3.5" /> Ubah
                                </button>
                                <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => openResults(t)}>
                                    <Icon name="ruler" className="h-3.5 w-3.5" /> Hasil Ukur
                                </button>
                                <button className="btn btn-primary px-2 py-1 text-xs"
                                    onClick={() => { setError(''); setFinish({ id: t.id, code: t.code, produced_qty: t.planned_qty, ok_qty: t.planned_qty, ng_qty: 0, conclusion: '' }); }}>
                                    <Icon name="check" className="h-3.5 w-3.5" /> Tutup Trial
                                </button>
                            </>)}
                            {t.status === 'DRAFT' && can('npd-trials', 'delete') && (
                                <button className="btn btn-ghost px-2 py-1 text-xs text-red-700"
                                    onClick={() => window.confirm(`Hapus trial ${t.code} beserta Work Order uji cobanya?`) && remove.mutate(t.id)}>
                                    <Icon name="trash" className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3 px-3 py-3 text-sm lg:grid-cols-5">
                        <div><p className="text-xs text-slate-400">Rencana</p><p>{money(t.planned_qty)} pcs</p></div>
                        <div><p className="text-xs text-slate-400">Diproduksi</p><p>{money(t.produced_qty)} pcs</p></div>
                        <div><p className="text-xs text-slate-400">OK</p><p className="text-emerald-700">{money(t.ok_qty)}</p></div>
                        <div><p className="text-xs text-slate-400">NG</p><p className={t.ng_qty > 0 ? 'text-red-700' : ''}>{money(t.ng_qty)}</p></div>
                        <div>
                            <p className="text-xs text-slate-400">Hasil ukur masuk spek</p>
                            <p className={t.pass_rate === null ? 'text-slate-400' : t.pass_rate === 100 ? 'text-emerald-700' : 'text-amber-700'}>
                                {t.pass_rate === null ? 'belum diukur' : `${t.pass_rate}%`}
                            </p>
                        </div>
                    </div>

                    {(t.detail || []).length > 0 && (
                        <div className="overflow-x-auto border-t border-slate-100">
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                    <th className="px-3 py-2">Parameter</th><th className="px-3 py-2 text-right">Benda uji</th>
                                    <th className="px-3 py-2 text-right">Nominal</th><th className="px-3 py-2 text-right">Batas</th>
                                    <th className="px-3 py-2 text-right">Hasil</th><th className="px-3 py-2">Putusan</th>
                                    <th className="px-3 py-2">Alat ukur</th>
                                </tr></thead>
                                <tbody>
                                    {t.detail.map((d) => (
                                        <tr key={d.id} className={`border-t border-slate-100 ${d.judgement === 'NG' ? 'bg-red-50' : ''}`}>
                                            <td className="px-3 py-1.5">{d.param?.code} — {d.param?.name}</td>
                                            <td className="px-3 py-1.5 text-right text-slate-500">#{d.sample_no}</td>
                                            <td className="px-3 py-1.5 text-right">{d.nominal ?? '—'}</td>
                                            <td className="px-3 py-1.5 text-right text-slate-500">
                                                {d.min_value ?? '—'} … {d.max_value ?? '—'}
                                            </td>
                                            <td className="px-3 py-1.5 text-right font-medium">{d.measured}</td>
                                            <td className="px-3 py-1.5">
                                                <span className={`rounded px-1.5 py-0.5 text-[11px] font-medium ${d.judgement === 'OK' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                                                    {d.judgement}
                                                </span>
                                            </td>
                                            <td className="px-3 py-1.5 text-xs text-slate-500">{d.instrument || '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {t.conclusion && (
                        <p className="border-t border-slate-100 px-3 py-2 text-xs text-slate-600"><b>Kesimpulan:</b> {t.conclusion}</p>
                    )}
                </div>
            ))}

            {/* ── Buat / ubah trial ── */}
            <Modal open={!!form} onClose={() => setForm(null)} title={form?.id ? 'Ubah Trial' : 'Trial Baru'}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setForm(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={save.isPending} onClick={() => { setError(''); save.mutate(form); }}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {form && (
                    <div className="space-y-3">
                        {!form.id && (
                            <p className="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                Work Order uji coba akan dibuat otomatis dan ditandai <b>NPD_TRIAL</b>, sehingga keluarannya tidak terhitung sebagai pasokan di MRP.
                            </p>
                        )}
                        <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label>
                            <input type="date" className="field-input" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} /></div>
                        <div><label className="field-label">Jenis trial</label>
                            <select className="field-input" value={form.trial_type} onChange={(e) => setForm({ ...form, trial_type: e.target.value })}>
                                {TRIAL_TYPES.map(([v, label]) => <option key={v} value={v}>{label}</option>)}
                            </select></div>
                        <div><label className="field-label">Qty rencana <span className="text-red-500">*</span></label>
                            <input type="number" className="field-input" value={form.planned_qty} onChange={(e) => setForm({ ...form, planned_qty: e.target.value })} /></div>
                        <div><label className="field-label">Mesin</label>
                            <Select value={form.machine_id} onChange={(v) => setForm({ ...form, machine_id: v })} options={machines.data}
                                getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— belum ditentukan —" /></div>
                    </div>
                )}
            </Modal>

            {/* ── Hasil ukur ── */}
            <Modal open={!!results} onClose={() => setResults(null)} wide title={`Hasil Ukur — ${results?.code || ''}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setResults(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={saveResults.isPending || !results?.rows?.length}
                        onClick={() => { setError(''); saveResults.mutate({ id: results.id, rows: results.rows }); }}>
                        {saveResults.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {results && (<>
                    <p className="mb-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        Putusan OK/NG dihitung server dari batas spesifikasi — tidak diketik. Parameter yang batasnya sudah terdaftar di master item
                        memakai angka master, dan angka itu dibekukan di hasil ini.
                    </p>
                    <LineTable title="Pengukuran" onAdd={addResultRow} lines={results.rows} empty="Belum ada pengukuran."
                        head={['Parameter', 'Benda uji', 'Nominal', 'Min', 'Maks', 'Hasil ukur', 'Alat ukur', '']}
                        row={(r, i) => {
                            const locked = r._source === 'MASTER';
                            const ng = r.measured !== '' && (
                                (r.min_value !== '' && Number(r.measured) < Number(r.min_value))
                                || (r.max_value !== '' && Number(r.measured) > Number(r.max_value))
                            );
                            return (<>
                                <td className="min-w-[220px] px-2 py-1.5">
                                    <Select value={r.param_id} onChange={(v) => pickParam(i, v)} options={params}
                                        getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}${o.uom ? ` (${o.uom})` : ''}`} placeholder="— pilih parameter —" />
                                    {locked && <p className="mt-0.5 text-[11px] text-slate-400">batas dari master item</p>}
                                </td>
                                <td className="w-20 px-2 py-1.5"><CellInput type="number" value={r.sample_no} onChange={(v) => setRow(i, 'sample_no', v)} /></td>
                                <td className="w-24 px-2 py-1.5"><CellInput type="number" step="0.0001" value={r.nominal} onChange={(v) => setRow(i, 'nominal', v)} className={locked ? 'bg-slate-100' : ''} /></td>
                                <td className="w-24 px-2 py-1.5"><CellInput type="number" step="0.0001" value={r.min_value} onChange={(v) => setRow(i, 'min_value', v)} className={locked ? 'bg-slate-100' : ''} /></td>
                                <td className="w-24 px-2 py-1.5"><CellInput type="number" step="0.0001" value={r.max_value} onChange={(v) => setRow(i, 'max_value', v)} className={locked ? 'bg-slate-100' : ''} /></td>
                                <td className="w-28 px-2 py-1.5">
                                    <CellInput type="number" step="0.0001" value={r.measured} onChange={(v) => setRow(i, 'measured', v)}
                                        className={ng ? 'border-red-400 text-red-700' : ''} />
                                </td>
                                <td className="w-32 px-2 py-1.5"><CellInput value={r.instrument} onChange={(v) => setRow(i, 'instrument', v)} /></td>
                                <td className="px-2 py-1.5">
                                    <button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50"
                                        onClick={() => setResults({ ...results, rows: results.rows.filter((_, j) => j !== i) })}><Icon name="trash" /></button>
                                </td>
                            </>);
                        }} />
                </>)}
            </Modal>

            {/* ── Tutup trial ── */}
            <Modal open={!!finish} onClose={() => setFinish(null)} title={`Tutup Trial — ${finish?.code || ''}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setFinish(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={doFinish.isPending}
                        onClick={() => { setError(''); doFinish.mutate({ id: finish.id, payload: finish }); }}>
                        {doFinish.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="check" />} Tutup
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {finish && (
                    <div className="space-y-3">
                        <p className="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Trial yang ditutup tidak bisa diubah lagi, dan Work Order uji cobanya ikut ditutup.
                        </p>
                        <div className="grid grid-cols-3 gap-3">
                            <div><label className="field-label">Diproduksi</label>
                                <input type="number" className="field-input" value={finish.produced_qty} onChange={(e) => setFinish({ ...finish, produced_qty: e.target.value })} /></div>
                            <div><label className="field-label">OK</label>
                                <input type="number" className="field-input" value={finish.ok_qty} onChange={(e) => setFinish({ ...finish, ok_qty: e.target.value })} /></div>
                            <div><label className="field-label">NG</label>
                                <input type="number" className="field-input" value={finish.ng_qty} onChange={(e) => setFinish({ ...finish, ng_qty: e.target.value })} /></div>
                        </div>
                        <div><label className="field-label">Kesimpulan</label>
                            <textarea className="field-input" rows={3} maxLength={400} value={finish.conclusion}
                                onChange={(e) => setFinish({ ...finish, conclusion: e.target.value })}
                                placeholder="Mis. dimensi masuk spek; perlu penyetelan ulang stopper pada proses bending." /></div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
