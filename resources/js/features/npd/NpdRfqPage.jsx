import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, StatusBadge, useOptions, money } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = {
    cus_id: '', code: '', date: today(), part_name: '', drawing_ref: '',
    qty: 0, target_price: 0, due_date: '', note: '',
};
const EMPTY_FEAS = {
    tech_ok: false, capacity_ok: false, cost_ok: false,
    material_avail: '', conclusion: 'CONDITIONAL', note: '',
};

export default function NpdRfqPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [feas, setFeas] = useState(null);
    const [convert, setConvert] = useState(null);
    const [error, setError] = useState('');

    const contacts = useOptions('contacts');
    // RFQ datang dari pelanggan; supplier tidak ada urusannya di sini.
    const customers = (contacts.data || []).filter((c) => c.category_id === 3);

    const list = useQuery({
        queryKey: ['npd-rfq', { page }],
        queryFn: async () => (await api.get('/npd-rfq', { params: { page, per_page: 15 } })).data,
    });

    const invalidate = () => {
        qc.invalidateQueries({ queryKey: ['npd-rfq'] });
        qc.invalidateQueries({ queryKey: ['npd-projects'] });
    };

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/npd-rfq/${modal.id}`, payload) : api.post('/npd-rfq', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const saveFeas = useMutation({
        mutationFn: async ({ id, payload }) => api.post(`/npd-rfq/${id}/feasibility`, payload),
        onSuccess: () => { invalidate(); setFeas(null); },
        onError: (e) => setError(apiError(e)),
    });
    const makeProject = useMutation({
        mutationFn: async (payload) => api.post('/npd-projects', payload),
        onSuccess: () => { invalidate(); setConvert(null); },
        onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/npd-rfq/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = (row) => {
        setError('');
        setForm({
            cus_id: row.cus_id, code: row.code, date: row.date?.slice(0, 10),
            part_name: row.part_name, drawing_ref: row.drawing_ref || '',
            qty: row.qty, target_price: row.target_price,
            due_date: row.due_date?.slice(0, 10) || '', note: row.note || '',
        });
        setModal({ mode: 'edit', id: row.id });
    };
    const openFeas = (row) => {
        setError('');
        setFeas({ id: row.id, code: row.code, ...(row.feasibility ? {
            tech_ok: !!row.feasibility.tech_ok, capacity_ok: !!row.feasibility.capacity_ok,
            cost_ok: !!row.feasibility.cost_ok, material_avail: row.feasibility.material_avail || '',
            conclusion: row.feasibility.conclusion, note: row.feasibility.note || '',
        } : EMPTY_FEAS) });
    };
    const openConvert = (row) => {
        setError('');
        setConvert({
            rfq_id: row.id, rfq_code: row.code,
            name: row.part_name, project_type: 'NEW',
            target_sop: '', priority: 'NORMAL',
        });
    };

    const columns = [
        { key: 'code', label: 'No. RFQ' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'cus', label: 'Pelanggan', render: (v) => v?.company_n || '—' },
        { key: 'part_name', label: 'Part' },
        { key: 'qty', label: 'Qty', render: (v) => money(v) },
        { key: 'target_price', label: 'Target Harga', render: (v) => money(v) },
        {
            key: 'feasibility',
            label: 'Feasibility',
            render: (v) => (v ? <StatusBadge status={v.conclusion} /> : <span className="text-xs text-slate-400">belum dinilai</span>),
        },
        {
            key: 'project',
            label: 'Proyek',
            render: (v) => (v ? <span className="font-medium text-slate-700">{v.code}</span> : <span className="text-xs text-slate-400">—</span>),
        },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">RFQ &amp; Feasibility</h1>
                    <p className="text-sm text-slate-500">
                        Permintaan penawaran pelanggan dan penilaian kelayakannya. Hanya kesimpulan <b>GO</b> yang boleh dijadikan proyek NPD.
                    </p>
                </div>
                {can('npd-rfq', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Catat RFQ</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('npd-rfq', 'edit') && (
                            <button title="Feasibility" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openFeas(row)}><Icon name="scale" /></button>
                        )}
                        {!row.main_id && row.feasibility?.conclusion === 'GO' && can('npd-projects', 'create') && (
                            <button title="Jadikan proyek NPD" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => openConvert(row)}><Icon name="send" /></button>
                        )}
                        {!row.main_id && can('npd-rfq', 'edit') && (
                            <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>
                        )}
                        {!row.main_id && can('npd-rfq', 'delete') && (
                            <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus RFQ?') && remove.mutate(row.id)}><Icon name="trash" /></button>
                        )}
                    </div>
                )} />

            {/* ── RFQ ── */}
            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Ubah' : 'Catat'} RFQ`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div><label className="field-label">No. RFQ <span className="text-red-500">*</span></label>
                        <input className="field-input" value={form.code} maxLength={50} onChange={(e) => set('code', e.target.value)} /></div>
                    <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label>
                        <input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div><label className="field-label">Due date</label>
                        <input type="date" className="field-input" value={form.due_date} onChange={(e) => set('due_date', e.target.value)} /></div>
                    <div className="sm:col-span-3"><label className="field-label">Pelanggan <span className="text-red-500">*</span></label>
                        <Select value={form.cus_id} onChange={(v) => set('cus_id', v)} options={customers}
                            getValue={(o) => o.id} getLabel={(o) => o.company_n} placeholder="— pilih pelanggan —" /></div>
                    <div className="sm:col-span-2"><label className="field-label">Nama Part <span className="text-red-500">*</span></label>
                        <input className="field-input" value={form.part_name} maxLength={100} onChange={(e) => set('part_name', e.target.value)} /></div>
                    <div><label className="field-label">Drawing Ref</label>
                        <input className="field-input" value={form.drawing_ref} maxLength={50} onChange={(e) => set('drawing_ref', e.target.value)} /></div>
                    <div><label className="field-label">Qty (per tahun)</label>
                        <input type="number" className="field-input" value={form.qty} onChange={(e) => set('qty', e.target.value)} /></div>
                    <div><label className="field-label">Target Harga</label>
                        <input type="number" step="0.01" className="field-input" value={form.target_price} onChange={(e) => set('target_price', e.target.value)} /></div>
                    <div className="sm:col-span-3"><label className="field-label">Catatan</label>
                        <input className="field-input" value={form.note} maxLength={300} onChange={(e) => set('note', e.target.value)} /></div>
                </div>
            </Modal>

            {/* ── Feasibility ── */}
            <Modal open={!!feas} onClose={() => setFeas(null)} title={`Feasibility — ${feas?.code || ''}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setFeas(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={saveFeas.isPending}
                        onClick={() => { setError(''); saveFeas.mutate({ id: feas.id, payload: feas }); }}>
                        {saveFeas.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {feas && (
                    <div className="space-y-3">
                        {[['tech_ok', 'Sanggup secara teknis'], ['capacity_ok', 'Kapasitas mesin tersedia'], ['cost_ok', 'Biaya masuk target harga']].map(([k, label]) => (
                            <label key={k} className="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" className="h-4 w-4" checked={!!feas[k]} onChange={(e) => setFeas({ ...feas, [k]: e.target.checked })} />
                                {label}
                            </label>
                        ))}
                        <div><label className="field-label">Ketersediaan material</label>
                            <input className="field-input" value={feas.material_avail} maxLength={150} onChange={(e) => setFeas({ ...feas, material_avail: e.target.value })} /></div>
                        <div><label className="field-label">Kesimpulan <span className="text-red-500">*</span></label>
                            <select className="field-input" value={feas.conclusion} onChange={(e) => setFeas({ ...feas, conclusion: e.target.value })}>
                                <option value="GO">GO — lanjut jadi proyek</option>
                                <option value="CONDITIONAL">CONDITIONAL — masih ada syarat</option>
                                <option value="NO_GO">NO_GO — tidak dilanjutkan</option>
                            </select>
                            <p className="mt-0.5 text-[11px] text-slate-400">GO ditolak sistem selama masih ada aspek di atas yang belum dicentang.</p>
                        </div>
                        <div><label className="field-label">Catatan</label>
                            <textarea className="field-input" rows={3} value={feas.note} maxLength={400} onChange={(e) => setFeas({ ...feas, note: e.target.value })} /></div>
                    </div>
                )}
            </Modal>

            {/* ── Jadikan proyek ── */}
            <Modal open={!!convert} onClose={() => setConvert(null)} title={`Jadikan Proyek NPD — ${convert?.rfq_code || ''}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setConvert(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={makeProject.isPending}
                        onClick={() => { setError(''); makeProject.mutate(convert); }}>
                        {makeProject.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="check" />} Buat Proyek
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {convert && (
                    <div className="space-y-3">
                        <p className="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Lima fase APQP beserta deliverable wajibnya akan dibuat otomatis. Fase 1 langsung berjalan.
                        </p>
                        <div><label className="field-label">Nama proyek</label>
                            <input className="field-input" value={convert.name} maxLength={150} onChange={(e) => setConvert({ ...convert, name: e.target.value })} /></div>
                        <div><label className="field-label">Tipe proyek</label>
                            <select className="field-input" value={convert.project_type} onChange={(e) => setConvert({ ...convert, project_type: e.target.value })}>
                                <option value="NEW">NEW — part baru</option>
                                <option value="MODIFICATION">MODIFICATION — ubahan part lama</option>
                                <option value="DERIVATIVE">DERIVATIVE — turunan part lama</option>
                            </select></div>
                        <div><label className="field-label">Target SOP</label>
                            <input type="date" className="field-input" value={convert.target_sop} onChange={(e) => setConvert({ ...convert, target_sop: e.target.value })} /></div>
                        <div><label className="field-label">Prioritas</label>
                            <select className="field-input" value={convert.priority} onChange={(e) => setConvert({ ...convert, priority: e.target.value })}>
                                <option value="LOW">LOW</option><option value="NORMAL">NORMAL</option><option value="HIGH">HIGH</option>
                            </select></div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
