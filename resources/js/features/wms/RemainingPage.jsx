import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, useOptions, CellInput, money } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);

export default function RemainingPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(null);
    const [view, setView] = useState(null);
    const [error, setError] = useState('');

    const racks = useOptions('racks');
    const remRacks = (racks.data || []).filter((r) => r.active && r.rem_rack);
    const outs = useQuery({
        queryKey: ['outgoing-rm', 'for-remaining'],
        queryFn: async () => (await api.get('/outgoing-rm', { params: { per_page: 300 } })).data.data,
        staleTime: 30_000,
    });

    const list = useQuery({
        queryKey: ['remaining-rm', { page }],
        queryFn: async () => (await api.get('/remaining-rm', { params: { page, per_page: 15 } })).data,
    });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['remaining-rm'] }); qc.invalidateQueries({ queryKey: ['outgoing-rm'] }); qc.invalidateQueries({ queryKey: ['stock-rm'] }); };

    const save = useMutation({
        mutationFn: async (payload) => api.post('/remaining-rm', payload),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/remaining-rm/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setForm({ out_id: '', date: today(), rows: [] }); setError(''); setModal({ mode: 'create' }); };
    const onSelectOut = async (outId) => {
        if (!outId) { setForm((f) => ({ ...f, out_id: '', rows: [] })); return; }
        const { data } = await api.get(`/remaining-rm/available/${outId}`);
        setForm((f) => ({
            ...f, out_id: outId,
            rows: (data.data || []).map((s) => ({ serial_id: s.serial_id, max_length: s.length_rem, length: s.length_rem, weight: s.weight_rem, rack_id: '', checked: true })),
        }));
    };
    const openView = async (row) => { const { data } = await api.get(`/remaining-rm/${row.id}`); setView(data.data); };

    const setRow = (i, patch) => setForm((f) => ({ ...f, rows: f.rows.map((r, j) => (j === i ? { ...r, ...patch } : r)) }));
    const selected = form?.rows.filter((r) => r.checked) || [];

    const submit = () => {
        setError('');
        save.mutate({
            out_id: form.out_id, date: form.date,
            lines: selected.map((r) => ({ serial_id: r.serial_id, length: r.length, weight: r.weight, rack_id: r.rack_id })),
        });
    };

    const columns = [
        { key: 'code', label: 'No. Remaining' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'out', label: 'Outgoing', render: (v) => v?.code || '—' },
        { key: 'item', label: 'Item', render: (v) => (v ? `${v.code} — ${v.part_name}` : '—') },
        { key: 'detail_count', label: '# Serial' },
        { key: 'user', label: 'Oleh', render: (v) => v?.name || '—' },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Remaining / Tankan RM</h1>
                {can('remaining-rm', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Remaining</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {can('remaining-rm', 'delete') && <button title="Batalkan" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Batalkan remaining ini?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            {/* Create */}
            <Modal open={modal?.mode === 'create'} onClose={() => setModal(null)} size="max-w-5xl" title="Remaining / Tankan (kembalikan sisa ke rak remnant)"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit}
                        disabled={save.isPending || !form?.out_id || selected.length === 0 || selected.some((r) => !r.rack_id)}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan ({selected.length})
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {form && (<>
                    <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><label className="field-label">Transaksi Outgoing <span className="text-red-500">*</span></label>
                            <select className="field-input" value={form.out_id} onChange={(e) => onSelectOut(Number(e.target.value) || '')}>
                                <option value="">— pilih outgoing —</option>
                                {(outs.data || []).map((o) => <option key={o.id} value={o.id}>{o.code} — {o.item?.code} ({o.date?.slice(0, 10)})</option>)}
                            </select>
                        </div>
                        <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))} /></div>
                    </div>

                    {form.out_id && form.rows.length === 0 && <p className="rounded-md border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">Tidak ada sisa yang belum dikembalikan pada outgoing ini.</p>}

                    {form.rows.length > 0 && (
                        <div className="overflow-x-auto rounded-md border border-slate-200">
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                    {['', 'Serial', 'Sisa Tercatat (mm)', 'Panjang Kembali (mm)', 'Berat (kg)', 'Rak Remnant *'].map((h, k) => <th key={k} className="whitespace-nowrap px-3 py-2">{h}</th>)}
                                </tr></thead>
                                <tbody>
                                    {form.rows.map((r, i) => (
                                        <tr key={r.serial_id} className={`border-t border-slate-100 ${r.checked ? '' : 'opacity-40'}`}>
                                            <td className="px-3 py-2"><input type="checkbox" className="h-4 w-4" checked={r.checked} onChange={(e) => setRow(i, { checked: e.target.checked })} /></td>
                                            <td className="whitespace-nowrap px-3 py-2 font-medium text-slate-700">{r.serial_id}</td>
                                            <td className="px-3 py-2 text-right">{money(r.max_length)}</td>
                                            <td className="px-3 py-2"><div className="w-28"><CellInput type="number" step="0.01" value={r.length} onChange={(v) => setRow(i, { length: v })} /></div></td>
                                            <td className="px-3 py-2"><div className="w-28"><CellInput type="number" step="0.01" value={r.weight} onChange={(v) => setRow(i, { weight: v })} /></div></td>
                                            <td className="min-w-[170px] px-3 py-2">
                                                <Select value={r.rack_id} onChange={(v) => setRow(i, { rack_id: v })} options={remRacks} getValue={(o) => o.id} getLabel={(o) => o.location} placeholder="— rak remnant —" disabled={!r.checked} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {remRacks.length === 0 && <p className="mt-2 text-xs text-amber-600">Belum ada rak dengan flag "Rak Remnant" — buat dulu di Master Rak.</p>}
                </>)}
            </Modal>

            {/* View */}
            <Modal open={!!view} onClose={() => setView(null)} wide title={`Remaining ${view?.code || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setView(null)}>Tutup</button>}>
                {view && (<>
                    <div className="mb-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                        <div><span className="text-slate-400">Outgoing</span><div className="font-medium">{view.out?.code}</div></div>
                        <div><span className="text-slate-400">Item</span><div className="font-medium">{view.item?.code}</div></div>
                        <div><span className="text-slate-400">Tanggal</span><div className="font-medium">{view.date?.slice(0, 10)}</div></div>
                        <div><span className="text-slate-400">Oleh</span><div className="font-medium">{view.user?.name}</div></div>
                    </div>
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                {['Serial', 'Panjang (mm)', 'Berat (kg)', 'Rak'].map((h, k) => <th key={k} className="px-3 py-2">{h}</th>)}
                            </tr></thead>
                            <tbody>
                                {(view.detail || []).map((d) => (
                                    <tr key={d.id} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5 font-medium text-slate-700">{d.serial_id}</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.length)}</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.weight)}</td>
                                        <td className="px-3 py-1.5">{d.rack?.location || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>)}
            </Modal>
        </div>
    );
}
