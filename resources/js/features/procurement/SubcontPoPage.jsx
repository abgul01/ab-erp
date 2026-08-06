import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { VendorSelect, StatusBadge, CellInput, money } from './common';

const today = () => new Date().toISOString().slice(0, 10);

/**
 * PO Subcont — dokumen PO tersendiri (terpisah dari PO umum). Item per baris
 * dibatasi ke barang yang terdaftar untuk vendor itu di Master Subcont; harga
 * default dari master. DRAFT → OPEN (approve) → dipakai di Subcont Kirim.
 */
export default function SubcontPoPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [venId, setVenId] = useState('');
    const [date, setDate] = useState(today());
    const [note, setNote] = useState('');
    const [lines, setLines] = useState([]);   // {item_id, item_code, part_name, process_id, process_code, qty, price}
    const [masterItems, setMasterItems] = useState([]);
    const [error, setError] = useState('');
    const [view, setView] = useState(null);

    const list = useQuery({ queryKey: ['subcont-po', { page }], queryFn: async () => (await api.get('/subcont-po', { params: { page, per_page: 15 } })).data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['subcont-po'] });

    const save = useMutation({
        mutationFn: async () => api.post('/subcont-po', { date, ven_id: venId, note, lines: lines.filter((l) => Number(l.qty) > 0).map((l) => ({ item_id: l.item_id, process_id: l.process_id || null, qty: Number(l.qty), price: Number(l.price) || 0 })) }),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({ mutationFn: async ({ id, action }) => api.post(`/subcont-po/${id}/${action}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/subcont-po/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setVenId(''); setDate(today()); setNote(''); setLines([]); setMasterItems([]); setError(''); setModal({ mode: 'create' }); };
    const onVendor = async (id) => {
        setVenId(id); setLines([]);
        if (!id) { setMasterItems([]); return; }
        const { data } = await api.get(`/subcont-items/for-vendor/${id}`);
        setMasterItems(data.data);
    };
    const addLine = (mi) => {
        if (lines.some((l) => l.item_id === mi.item_id && (l.process_id || '') === (mi.process_id || ''))) return;
        setLines((ls) => [...ls, { item_id: mi.item_id, item_code: mi.item_code, part_name: mi.part_name, process_id: mi.process_id, process_code: mi.process_code, qty: 1, price: mi.price }]);
    };
    const setLine = (i, k, v) => setLines((ls) => ls.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const openView = async (row) => { const { data } = await api.get(`/subcont-po/${row.id}`); setView(data.data); };

    const columns = [
        { key: 'code', label: 'No. PO' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'ven', label: 'Vendor', render: (v) => v?.company_n || '—' },
        { key: 'detail_count', label: '# Item' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">PO Subcont</h1>
                {can('subcont-po', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Buat PO Subcont</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {row.status === 'DRAFT' && can('subcont-po', 'edit') && <button title="Approve" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate({ id: row.id, action: 'approve' })}><Icon name="check" /></button>}
                        {row.status === 'OPEN' && can('subcont-po', 'edit') && <button title="Tutup" className="rounded p-1.5 text-blue-600 hover:bg-blue-50" onClick={() => act.mutate({ id: row.id, action: 'close' })}><Icon name="lock" /></button>}
                        {row.status === 'DRAFT' && can('subcont-po', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus PO?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} size="max-w-4xl" title="Buat PO Subcont"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(); }} disabled={save.isPending || !venId || lines.length === 0}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="sm:col-span-2"><label className="field-label">Vendor Subcont <span className="text-red-500">*</span></label><VendorSelect value={venId} onChange={onVendor} /></div>
                    <div><label className="field-label">Tanggal</label><input type="date" className="field-input" value={date} onChange={(e) => setDate(e.target.value)} /></div>
                    <div className="sm:col-span-3"><label className="field-label">Catatan</label><input className="field-input" maxLength={200} value={note} onChange={(e) => setNote(e.target.value)} placeholder="opsional" /></div>
                </div>

                {venId && (
                    <div className="mb-3">
                        <label className="field-label">Barang vendor ini (dari Master Subcont)</label>
                        {masterItems.length === 0 ? (
                            <p className="text-xs text-amber-600">Vendor ini belum punya barang di Master Subcont. Tambahkan dulu di menu Master Subcont.</p>
                        ) : (
                            <div className="flex flex-wrap gap-1">
                                {masterItems.map((mi) => (
                                    <button key={`${mi.item_id}-${mi.process_id || 0}`} type="button" className="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" onClick={() => addLine(mi)}>
                                        + {mi.item_code}{mi.process_code ? ` · ${mi.process_code}` : ''}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                <div className="overflow-x-auto rounded-md border border-slate-200">
                    <table className="w-full text-sm">
                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Barang</th><th className="px-2 py-2">Proses</th><th className="px-2 py-2 text-right">Qty</th><th className="px-2 py-2 text-right">Harga/pc</th><th className="px-2 py-2 text-right">Jumlah</th><th></th></tr></thead>
                        <tbody>
                            {lines.length === 0 && <tr><td colSpan={6} className="px-2 py-6 text-center text-slate-400">{venId ? 'Klik barang di atas untuk menambah baris.' : 'Pilih vendor dulu.'}</td></tr>}
                            {lines.map((l, i) => (
                                <tr key={`${l.item_id}-${l.process_id || 0}`} className="border-t border-slate-100">
                                    <td className="px-2 py-1.5"><span className="font-medium">{l.item_code}</span> <span className="text-slate-400">{l.part_name}</span></td>
                                    <td className="px-2 py-1.5 text-slate-500">{l.process_code || '— umum'}</td>
                                    <td className="px-2 py-1.5"><div className="w-20"><CellInput type="number" value={l.qty} onChange={(v) => setLine(i, 'qty', v)} /></div></td>
                                    <td className="px-2 py-1.5"><div className="w-28"><CellInput type="number" step="0.01" value={l.price} onChange={(v) => setLine(i, 'price', v)} /></div></td>
                                    <td className="px-2 py-1.5 text-right text-slate-600">{money((Number(l.qty) || 0) * (Number(l.price) || 0))}</td>
                                    <td className="px-2 py-1.5"><button className="rounded p-1 text-red-500 hover:bg-red-50" onClick={() => setLines((ls) => ls.filter((_, j) => j !== i))}><Icon name="trash" /></button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Modal>

            <Modal open={!!view} onClose={() => setView(null)} size="max-w-2xl" title={`PO Subcont ${view?.code || ''} — ${view?.status || ''}`}>
                <div className="mb-2 text-sm text-slate-500">{view?.ven?.company_n} · {view?.date?.slice(0, 10)}{view?.note ? ` · ${view.note}` : ''}</div>
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Barang</th><th className="px-2 py-2 text-right">Qty</th><th className="px-2 py-2 text-right">Harga</th></tr></thead>
                    <tbody>{(view?.detail || []).map((d) => <tr key={d.id} className="border-t border-slate-100"><td className="px-2 py-1.5">{d.item?.code} — {d.item?.part_name}</td><td className="px-2 py-1.5 text-right">{d.qty}</td><td className="px-2 py-1.5 text-right">{money(d.price)}</td></tr>)}</tbody>
                </table>
            </Modal>
        </div>
    );
}
