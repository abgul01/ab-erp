import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { StatusBadge } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), returner: '', note: '', lines: [] };

const CONDITIONS = [
    ['GOOD', 'Baik — masuk stok lagi'],
    ['DAMAGED', 'Rusak — keluar stok, jadi kerugian'],
    ['LOST', 'Hilang — keluar stok, jadi kerugian'],
];

export default function WhsReturnPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    const [detail, setDetail] = useState(null);

    const list = useQuery({
        queryKey: ['whs-returns', { page }],
        queryFn: async () => (await api.get('/whs-returns', { params: { page, per_page: 15 } })).data,
    });
    const onLoan = useQuery({
        queryKey: ['whs-on-loan'],
        queryFn: async () => (await api.get('/whs-returns/on-loan')).data.data,
    });

    const invalidate = () => {
        qc.invalidateQueries({ queryKey: ['whs-returns'] });
        qc.invalidateQueries({ queryKey: ['whs-on-loan'] });
        qc.invalidateQueries({ queryKey: ['whs-stock'] });
    };

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/whs-returns/${modal.id}`, payload) : api.post('/whs-returns', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const post = useMutation({
        mutationFn: async (id) => api.post(`/whs-returns/${id}/post`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/whs-returns/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const setLine = (i, k, v) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));

    // Alat dipilih dari daftar yang benar-benar sedang di luar; mengetik nomor
    // unit sendiri hanya membuka jalan untuk mengembalikan alat yang tak pernah
    // dipinjam.
    const toggleUnit = (u) => setForm((f) => {
        const exists = f.lines.find((l) => l.tool_unit_id === u.id);

        return {
            ...f,
            returner: f.returner || u.holder || '',
            lines: exists
                ? f.lines.filter((l) => l.tool_unit_id !== u.id)
                : [...f.lines, { tool_unit_id: u.id, out_det_id: u.out_det_id, unit: u, condition: 'GOOD', note: '' }],
        };
    });

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openDetail = async (row) => {
        const { data } = await api.get(`/whs-returns/${row.id}`);
        setDetail(data.data);
    };

    const columns = [
        { key: 'code', label: 'No. Pengembalian' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'returner', label: 'Dikembalikan oleh', render: (v) => v || '—' },
        { key: 'detail_count', label: '# Unit' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    const loans = onLoan.data || [];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Pengembalian Alat</h1>
                    <p className="text-sm text-slate-500">Alat yang kembali baik masuk stok lagi. Yang rusak atau hilang baru dibebankan sebagai kerugian saat ini juga.</p>
                </div>
                {can('whs-returns', 'create') && <button className="btn btn-primary" onClick={openCreate} disabled={loans.length === 0}><Icon name="plus" /> Terima Pengembalian</button>}
            </div>

            {/* Yang sedang di luar — pertanyaan paling sering ke gudang alat. */}
            <div className="mb-5 rounded-lg border border-slate-200 bg-white">
                <div className="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
                    <h2 className="text-sm font-semibold text-slate-700">Sedang dipinjam</h2>
                    <span className="text-xs text-slate-400">{loans.length} unit di luar gudang</span>
                </div>
                <div className="max-h-64 overflow-y-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th className="px-3 py-2">Unit</th><th className="px-3 py-2">Alat</th>
                            <th className="px-3 py-2">Pemegang</th><th className="px-3 py-2">Dokumen</th>
                            <th className="px-3 py-2 text-right">Lama</th>
                        </tr></thead>
                        <tbody>
                            {loans.length === 0 && <tr><td colSpan={5} className="px-3 py-6 text-center text-slate-400">Semua alat ada di gudang.</td></tr>}
                            {loans.map((u) => (
                                <tr key={u.id} className="border-t border-slate-100">
                                    <td className="px-3 py-1.5 font-medium">{u.unit_code}</td>
                                    <td className="px-3 py-1.5 text-slate-600">{u.item_code} — {u.item_name}</td>
                                    <td className="px-3 py-1.5">{u.holder || '—'}</td>
                                    <td className="px-3 py-1.5 text-slate-500">{u.out_code || '—'}</td>
                                    <td className={`px-3 py-1.5 text-right ${u.days_out > 30 ? 'font-medium text-amber-700' : 'text-slate-500'}`}>
                                        {u.days_out != null ? `${u.days_out} hari` : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100" onClick={() => openDetail(row)}><Icon name="eye" /></button>
                        {row.status === 'DRAFT' && can('whs-returns', 'edit') && (
                            <button title="Post" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50"
                                onClick={() => window.confirm('Post pengembalian ini?') && post.mutate(row.id)}><Icon name="check" /></button>
                        )}
                        {row.status === 'DRAFT' && can('whs-returns', 'delete') && (
                            <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus dokumen?') && remove.mutate(row.id)}><Icon name="trash" /></button>
                        )}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} wide title="Terima Pengembalian Alat"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending || form.lines.length === 0}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label>
                        <input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div><label className="field-label">Dikembalikan oleh</label>
                        <input className="field-input" value={form.returner} maxLength={60} onChange={(e) => set('returner', e.target.value)} /></div>
                    <div><label className="field-label">Catatan</label>
                        <input className="field-input" value={form.note} maxLength={300} onChange={(e) => set('note', e.target.value)} /></div>
                </div>

                <h4 className="mb-2 text-sm font-semibold text-slate-700">Pilih unit yang dikembalikan</h4>
                <div className="mb-4 max-h-56 overflow-y-auto rounded-md border border-slate-200">
                    <table className="w-full text-sm">
                        <tbody>
                            {loans.map((u) => {
                                const picked = form.lines.some((l) => l.tool_unit_id === u.id);
                                return (
                                    <tr key={u.id} className={`border-t border-slate-100 ${picked ? 'bg-violet-50' : ''}`}>
                                        <td className="w-10 px-2 py-1.5"><input type="checkbox" checked={picked} onChange={() => toggleUnit(u)} /></td>
                                        <td className="px-2 py-1.5 font-medium">{u.unit_code}</td>
                                        <td className="px-2 py-1.5 text-slate-600">{u.item_name}</td>
                                        <td className="px-2 py-1.5 text-slate-500">{u.holder || '—'}</td>
                                        <td className="px-2 py-1.5 text-right text-slate-400">{u.days_out != null ? `${u.days_out} hari` : ''}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {form.lines.length > 0 && (
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">Unit</th><th className="px-2 py-2">Kondisi saat kembali</th><th className="px-2 py-2">Catatan</th>
                            </tr></thead>
                            <tbody>
                                {form.lines.map((l, i) => (
                                    <tr key={l.tool_unit_id} className="border-t border-slate-100">
                                        <td className="px-2 py-1.5 font-medium">{l.unit?.unit_code}</td>
                                        <td className="w-72 px-2 py-1.5">
                                            <select className="field-input" value={l.condition} onChange={(e) => setLine(i, 'condition', e.target.value)}>
                                                {CONDITIONS.map(([v, label]) => <option key={v} value={v}>{label}</option>)}
                                            </select>
                                        </td>
                                        <td className="px-2 py-1.5">
                                            <input className="field-input" value={l.note} maxLength={150} onChange={(e) => setLine(i, 'note', e.target.value)} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Modal>

            <Modal open={!!detail} onClose={() => setDetail(null)} wide title={`Pengembalian ${detail?.code || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setDetail(null)}>Tutup</button>}>
                {detail && (
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">Unit</th><th className="px-2 py-2">Alat</th>
                                <th className="px-2 py-2">Kondisi</th><th className="px-2 py-2">Status unit sekarang</th>
                            </tr></thead>
                            <tbody>
                                {(detail.detail || []).map((l) => (
                                    <tr key={l.id} className="border-t border-slate-100">
                                        <td className="px-2 py-1.5 font-medium">{l.unit?.code}</td>
                                        <td className="px-2 py-1.5 text-slate-600">{l.unit?.item?.name}</td>
                                        <td className="px-2 py-1.5">{l.condition}</td>
                                        <td className="px-2 py-1.5 text-slate-500">{l.unit?.status}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Modal>
        </div>
    );
}
