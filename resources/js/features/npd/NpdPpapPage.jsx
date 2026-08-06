import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, StatusBadge } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);

const LEVELS = [
    [1, 'Level 1 — hanya PSW'],
    [2, 'Level 2 — PSW + sampel + data pendukung terbatas'],
    [3, 'Level 3 — PSW + sampel + data pendukung lengkap (paling umum)'],
    [4, 'Level 4 — sesuai permintaan pelanggan'],
    [5, 'Level 5 — lengkap, ditinjau di lokasi pemasok'],
];

const ELEMENT_STATUS = [
    ['OPEN', 'Belum ada'],
    ['DONE', 'Sudah ada'],
    ['NA', 'Tidak berlaku (wajib beralasan)'],
];

export default function NpdPpapPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [projectId, setProjectId] = useState('');
    const [createModal, setCreateModal] = useState(null);
    const [submitModal, setSubmitModal] = useState(null);
    const [decisionModal, setDecisionModal] = useState(null);
    const [error, setError] = useState('');

    const projects = useQuery({
        queryKey: ['npd-projects', 'ppap'],
        queryFn: async () => (await api.get('/npd-projects', { params: { per_page: 200 } })).data.data,
    });
    const data = useQuery({
        queryKey: ['npd-ppap', projectId],
        queryFn: async () => (await api.get(`/npd-ppap/project/${projectId}`)).data.data,
        enabled: !!projectId,
    });

    const invalidate = () => {
        qc.invalidateQueries({ queryKey: ['npd-ppap'] });
        qc.invalidateQueries({ queryKey: ['npd-projects'] });
    };
    const onErr = (e) => setError(apiError(e));

    const create = useMutation({
        mutationFn: async (p) => api.post(`/npd-ppap/project/${projectId}`, p),
        onSuccess: () => { invalidate(); setCreateModal(null); },
        onError: onErr,
    });
    const setElement = useMutation({
        mutationFn: async ({ id, detailId, payload }) => api.put(`/npd-ppap/${id}/element/${detailId}`, payload),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const action = useMutation({
        mutationFn: async ({ id, act, body }) => (act === 'delete'
            ? api.delete(`/npd-ppap/${id}`)
            : api.post(`/npd-ppap/${id}/${act}`, body || {})),
        onSuccess: () => { invalidate(); setSubmitModal(null); setDecisionModal(null); },
        onError: (e) => { setError(apiError(e)); if (!submitModal && !decisionModal) alert(apiError(e)); },
    });

    const subs = data.data?.submissions || [];
    const docs = data.data?.docs || [];

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">PPAP Submission</h1>
                <p className="text-sm text-slate-500">
                    Paket bukti yang membuat pelanggan mengizinkan produksi massal. Enam dari 18 elemen dijawab sistem sendiri dari
                    DFMEA, PFMEA, control plan, hasil ukur trial, sampel produksi, dan ECN proyek — sisanya dilampirkan manual.
                </p>
            </div>

            <div className="mb-4 flex flex-wrap items-end gap-3">
                <div className="min-w-[22rem]">
                    <label className="field-label">Proyek NPD</label>
                    <Select value={projectId} onChange={(v) => { setProjectId(v); setError(''); }} options={projects.data}
                        getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— pilih proyek —" />
                </div>
                {projectId && can('npd-ppap', 'create') && (
                    <button className="btn btn-primary" onClick={() => { setError(''); setCreateModal({ ppap_level: 3, psw_no: '', customer_pic: '', note: '' }); }}>
                        <Icon name="plus" /> Submission Baru
                    </button>
                )}
            </div>

            {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

            {!projectId && <p className="rounded-md border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-400">Pilih proyek untuk melihat PPAP-nya.</p>}
            {projectId && subs.length === 0 && <p className="rounded-md border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-400">Belum ada submission PPAP pada proyek ini.</p>}

            {subs.map((p) => {
                const draft = p.status === 'DRAFT';
                const done = (p.detail || []).filter((d) => d.status === 'DONE').length;
                const na = (p.detail || []).filter((d) => d.status === 'NA').length;

                return (
                    <div key={p.id} className="mb-5 rounded-lg border border-slate-200 bg-white">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
                            <div className="text-sm">
                                <span className="font-semibold text-slate-700">{p.code}</span>
                                <span className="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600">Level {p.ppap_level}</span>
                                <span className="ml-2"><StatusBadge status={p.status} /></span>
                                {p.psw_no && <span className="ml-2 text-xs text-slate-500">PSW {p.psw_no}</span>}
                                {p.submission_date && <span className="ml-2 text-xs text-slate-500">dikirim {p.submission_date.slice(0, 10)}</span>}
                                {p.approval_date && p.status === 'APPROVED' && <span className="ml-2 text-xs text-emerald-700">disetujui {p.approval_date.slice(0, 10)}</span>}
                            </div>
                            <div className="flex gap-1">
                                {draft && can('npd-ppap', 'edit') && (<>
                                    <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => action.mutate({ id: p.id, act: 'sync' })}>
                                        <Icon name="check-circle" className="h-3.5 w-3.5" /> Cek Otomatis
                                    </button>
                                    <button className="btn btn-primary px-2 py-1 text-xs"
                                        onClick={() => { setError(''); setSubmitModal({ id: p.id, code: p.code, psw_no: p.psw_no || '', submission_date: today(), customer_pic: p.customer_pic || '' }); }}>
                                        <Icon name="send" className="h-3.5 w-3.5" /> Kirim ke Pelanggan
                                    </button>
                                </>)}
                                {['SUBMITTED', 'INTERIM'].includes(p.status) && can('npd-ppap', 'edit') && (
                                    <button className="btn btn-primary px-2 py-1 text-xs"
                                        onClick={() => { setError(''); setDecisionModal({ id: p.id, code: p.code, status: 'APPROVED', approval_date: today(), customer_pic: p.customer_pic || '', note: '' }); }}>
                                        <Icon name="check" className="h-3.5 w-3.5" /> Catat Jawaban Pelanggan
                                    </button>
                                )}
                                {draft && can('npd-ppap', 'delete') && (
                                    <button className="btn btn-ghost px-2 py-1 text-xs text-red-700"
                                        onClick={() => window.confirm(`Hapus ${p.code}?`) && action.mutate({ id: p.id, act: 'delete' })}>
                                        <Icon name="trash" className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-4 px-3 py-2 text-xs text-slate-600">
                            <span><b>{done}</b> sudah ada</span>
                            <span><b>{na}</b> tidak berlaku</span>
                            <span className={p.outstanding?.length ? 'font-medium text-red-700' : 'text-emerald-700'}>
                                {p.outstanding?.length
                                    ? `${p.outstanding.length} elemen wajib belum lengkap`
                                    : 'seluruh elemen wajib lengkap'}
                            </span>
                        </div>

                        {p.outstanding?.length > 0 && draft && (
                            <p className="mx-3 mb-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                Belum bisa dikirim — wajib untuk level {p.ppap_level}: {p.outstanding.map((o) => `#${o.element_no} ${o.name}`).join('; ')}.
                            </p>
                        )}

                        <div className="overflow-x-auto border-t border-slate-100">
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                    <th className="px-2 py-2 w-10">#</th><th className="px-3 py-2">Elemen</th>
                                    <th className="px-2 py-2 text-center">Wajib</th>
                                    <th className="px-3 py-2 w-52">Status</th><th className="px-3 py-2 w-56">Bukti</th>
                                    <th className="px-3 py-2">Catatan</th>
                                </tr></thead>
                                <tbody>
                                    {(p.detail || []).map((d) => {
                                        const required = (d.std?.level_required || '').split(',').includes(String(p.ppap_level));
                                        return (
                                            <tr key={d.id} className={`border-t border-slate-100 ${required && d.status === 'OPEN' ? 'bg-amber-50' : ''}`}>
                                                <td className="px-2 py-1.5 text-slate-400">{d.std?.element_no}</td>
                                                <td className="px-3 py-1.5">{d.std?.name}</td>
                                                <td className="px-2 py-1.5 text-center">
                                                    {required
                                                        ? <span className="text-xs font-medium text-red-600">wajib</span>
                                                        : <span className="text-xs text-slate-400">opsional</span>}
                                                </td>
                                                <td className="px-3 py-1.5">
                                                    {draft && can('npd-ppap', 'edit') ? (
                                                        <select className="field-input" value={d.status}
                                                            onChange={(e) => {
                                                                const next = e.target.value;
                                                                let note = d.note || '';
                                                                if (next === 'NA') {
                                                                    note = window.prompt('Alasan elemen ini tidak berlaku:', note) || '';
                                                                    if (!note) return;
                                                                }
                                                                setElement.mutate({ id: p.id, detailId: d.id, payload: { status: next, note, doc_id: d.doc_id } });
                                                            }}>
                                                            {ELEMENT_STATUS.map(([v, label]) => <option key={v} value={v}>{label}</option>)}
                                                        </select>
                                                    ) : <StatusBadge status={d.status} />}
                                                    {d.auto_source && <p className="mt-0.5 text-[11px] text-violet-600">otomatis: {d.auto_source}</p>}
                                                </td>
                                                <td className="px-3 py-1.5">
                                                    {draft && can('npd-ppap', 'edit') ? (
                                                        <Select value={d.doc_id} options={docs}
                                                            onChange={(v) => setElement.mutate({ id: p.id, detailId: d.id, payload: { status: d.status, doc_id: v, note: d.note } })}
                                                            getValue={(o) => o.id} getLabel={(o) => `${o.file_name} (${o.version})`} placeholder="— tanpa lampiran —" />
                                                    ) : (d.doc?.file_name || <span className="text-slate-400">—</span>)}
                                                </td>
                                                <td className="px-3 py-1.5 text-xs text-slate-500">{d.note || '—'}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                );
            })}

            {/* ── Submission baru ── */}
            <Modal open={!!createModal} onClose={() => setCreateModal(null)} title="PPAP Submission Baru"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setCreateModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={create.isPending} onClick={() => { setError(''); create.mutate(createModal); }}>
                        {create.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Buat
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {createModal && (
                    <div className="space-y-3">
                        <p className="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Seluruh 18 elemen akan dibuat, lalu langsung diperiksa terhadap data proyek — elemen yang buktinya sudah ada di sistem ditandai otomatis.
                        </p>
                        <div><label className="field-label">Level PPAP</label>
                            <select className="field-input" value={createModal.ppap_level} onChange={(e) => setCreateModal({ ...createModal, ppap_level: Number(e.target.value) })}>
                                {LEVELS.map(([v, label]) => <option key={v} value={v}>{label}</option>)}
                            </select></div>
                        <div><label className="field-label">Nomor PSW</label>
                            <input className="field-input" value={createModal.psw_no} maxLength={50} onChange={(e) => setCreateModal({ ...createModal, psw_no: e.target.value })} />
                            <p className="mt-0.5 text-[11px] text-slate-400">Boleh diisi belakangan, tapi wajib ada sebelum dikirim.</p></div>
                        <div><label className="field-label">PIC pelanggan</label>
                            <input className="field-input" value={createModal.customer_pic} maxLength={60} onChange={(e) => setCreateModal({ ...createModal, customer_pic: e.target.value })} /></div>
                    </div>
                )}
            </Modal>

            {/* ── Kirim ── */}
            <Modal open={!!submitModal} onClose={() => setSubmitModal(null)} title={`Kirim ke Pelanggan — ${submitModal?.code || ''}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setSubmitModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={action.isPending}
                        onClick={() => { setError(''); action.mutate({ id: submitModal.id, act: 'submit', body: submitModal }); }}>
                        {action.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="send" />} Kirim
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {submitModal && (
                    <div className="space-y-3">
                        <p className="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Setelah dikirim, isi submission terkunci. Elemen wajib yang belum lengkap akan menahan pengiriman.
                        </p>
                        <div><label className="field-label">Nomor PSW <span className="text-red-500">*</span></label>
                            <input className="field-input" value={submitModal.psw_no} maxLength={50} onChange={(e) => setSubmitModal({ ...submitModal, psw_no: e.target.value })} /></div>
                        <div><label className="field-label">Tanggal kirim</label>
                            <input type="date" className="field-input" value={submitModal.submission_date} onChange={(e) => setSubmitModal({ ...submitModal, submission_date: e.target.value })} /></div>
                        <div><label className="field-label">PIC pelanggan</label>
                            <input className="field-input" value={submitModal.customer_pic} maxLength={60} onChange={(e) => setSubmitModal({ ...submitModal, customer_pic: e.target.value })} /></div>
                    </div>
                )}
            </Modal>

            {/* ── Jawaban pelanggan ── */}
            <Modal open={!!decisionModal} onClose={() => setDecisionModal(null)} title={`Jawaban Pelanggan — ${decisionModal?.code || ''}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setDecisionModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={action.isPending}
                        onClick={() => { setError(''); action.mutate({ id: decisionModal.id, act: 'decision', body: decisionModal }); }}>
                        {action.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Catat
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {decisionModal && (
                    <div className="space-y-3">
                        <div><label className="field-label">Keputusan</label>
                            <select className="field-input" value={decisionModal.status} onChange={(e) => setDecisionModal({ ...decisionModal, status: e.target.value })}>
                                <option value="APPROVED">APPROVED — produksi massal diizinkan</option>
                                <option value="INTERIM">INTERIM — izin sementara dengan syarat</option>
                                <option value="REJECTED">REJECTED — ditolak</option>
                            </select>
                            <p className="mt-0.5 text-[11px] text-slate-400">
                                Hanya APPROVED yang membuka serah terima part ke produksi massal.
                            </p></div>
                        <div><label className="field-label">Tanggal</label>
                            <input type="date" className="field-input" value={decisionModal.approval_date} onChange={(e) => setDecisionModal({ ...decisionModal, approval_date: e.target.value })} /></div>
                        <div><label className="field-label">PIC pelanggan</label>
                            <input className="field-input" value={decisionModal.customer_pic} maxLength={60} onChange={(e) => setDecisionModal({ ...decisionModal, customer_pic: e.target.value })} /></div>
                        <div><label className="field-label">Catatan</label>
                            <textarea className="field-input" rows={3} maxLength={400} value={decisionModal.note} onChange={(e) => setDecisionModal({ ...decisionModal, note: e.target.value })} /></div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
