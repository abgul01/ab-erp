import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { StatusBadge, LineTable, CellInput, Select, money } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), do_id: '', note: '', lines: [] };

export default function PackingListPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    const [detail, setDetail] = useState(null);

    const list = useQuery({
        queryKey: ['packing-lists', { page }],
        queryFn: async () => (await api.get('/packing-lists', { params: { page, per_page: 15 } })).data,
    });
    // Deliveries that can still be packed, with what is left on each line.
    const dos = useQuery({
        queryKey: ['ship-dos'],
        queryFn: async () => (await api.get('/shipping-orders/available-dos')).data.data,
        enabled: !!modal,
    });
    const doLines = useQuery({
        queryKey: ['pack-do-lines', form.do_id],
        queryFn: async () => (await api.get(`/packing-lists/do-lines/${form.do_id}`)).data.data,
        enabled: !!form.do_id && !!modal,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['packing-lists'] });

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/packing-lists/${modal.id}`, payload) : api.post('/packing-lists', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({
        mutationFn: async ({ id, action }) => api.post(`/packing-lists/${id}/${action}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/packing-lists/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const setLine = (i, k, v) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const delLine = (i) => set('lines', form.lines.filter((_, j) => j !== i));

    /*
     * A new box starts on the first line that still has pieces left, with the
     * remainder already filled in — the common case is one box per line, and
     * making the operator retype the quantity only invites a mismatch.
     */
    const addBox = () => {
        const line = (doLines.data?.lines || []).find((l) => l.outstanding > 0);
        const boxNo = `BOX-${form.lines.length + 1}`;
        set('lines', [...form.lines, {
            box_no: boxNo,
            do_detail_id: line?.do_detail_id || '',
            item_id: line?.item_id || '',
            qty: line?.outstanding || 1,
            net_weight: line ? Math.round(line.outstanding * (line.unit_weight || 0) * 100) / 100 : '',
            gross_weight: '', dimension: '', note: '',
        }]);
    };

    const pickLine = (i, doDetailId) => {
        const line = (doLines.data?.lines || []).find((l) => l.do_detail_id === doDetailId);
        setForm((f) => ({
            ...f,
            lines: f.lines.map((l, j) => (j === i
                ? { ...l, do_detail_id: doDetailId, item_id: line?.item_id || '', qty: line?.outstanding || l.qty }
                : l)),
        }));
    };

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/packing-lists/${row.id}`);
        const d = data.data;
        setForm({
            date: d.date?.slice(0, 10), do_id: d.do_id, note: d.note || '',
            lines: (d.detail || []).map((l) => ({
                box_no: l.box_no, do_detail_id: l.do_detail_id, item_id: l.item_id, qty: l.qty,
                net_weight: l.net_weight, gross_weight: l.gross_weight, dimension: l.dimension || '', note: l.note || '',
            })),
        });
        setModal({ mode: 'edit', id: row.id });
    };
    const openDetail = async (row) => {
        const { data } = await api.get(`/packing-lists/${row.id}`);
        setDetail(data.data);
    };

    const totNet = form.lines.reduce((a, l) => a + (Number(l.net_weight) || 0), 0);
    const totGross = form.lines.reduce((a, l) => a + (Number(l.gross_weight) || Number(l.net_weight) || 0), 0);

    const columns = [
        { key: 'code', label: 'No. Packing List' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'delivery_order', label: 'DO', render: (v) => v?.code || '—' },
        { key: 'detail_count', label: '# Kotak' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Packing List</h1>
                    <p className="text-sm text-slate-500">Pembagian satu Delivery Order ke dalam kotak, dengan berat bersih dan kotor per kotak.</p>
                </div>
                {can('packing-lists', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Packing List</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100" onClick={() => openDetail(row)}><Icon name="eye" /></button>
                        {row.status === 'DRAFT' && can('packing-lists', 'edit') && (<>
                            <button title="Finalkan" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate({ id: row.id, action: 'finalize' })}><Icon name="check" /></button>
                            <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>
                        </>)}
                        {row.status === 'DRAFT' && can('packing-lists', 'delete') && (
                            <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus packing list?') && remove.mutate(row.id)}><Icon name="trash" /></button>
                        )}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Packing List`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label>
                        <input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div className="sm:col-span-2"><label className="field-label">Delivery Order <span className="text-red-500">*</span></label>
                        <Select value={form.do_id} onChange={(v) => setForm((f) => ({ ...f, do_id: v, lines: [] }))} options={dos.data}
                            getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.customer || 'tanpa pelanggan'} (${o.status})`} placeholder="— pilih DO —" /></div>
                    <div className="sm:col-span-3"><label className="field-label">Catatan</label>
                        <input className="field-input" value={form.note} maxLength={300} onChange={(e) => set('note', e.target.value)} /></div>
                </div>

                {doLines.data && (
                    <div className="mb-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        {doLines.data.lines.map((l) => (
                            <div key={l.do_detail_id} className="flex justify-between">
                                <span>{l.item_code} — {l.part_name}</span>
                                <span>{l.qty_packed} / {l.qty} pcs dikemas{l.outstanding > 0 ? ` · sisa ${l.outstanding}` : ' · lengkap'}</span>
                            </div>
                        ))}
                    </div>
                )}

                <LineTable title="Kotak" subtitle="Berat kotor default mengikuti berat bersih bila dikosongkan."
                    onAdd={form.do_id ? addBox : undefined} addLabel="Kotak" lines={form.lines}
                    empty={form.do_id ? 'Belum ada kotak.' : 'Pilih DO dulu.'}
                    head={['No. Kotak', 'Baris DO', 'Qty', 'Netto (kg)', 'Bruto (kg)', 'Dimensi (mm)', 'Catatan', '']}
                    row={(l, i) => (<>
                        <td className="w-28 px-2 py-1.5"><CellInput value={l.box_no} onChange={(v) => setLine(i, 'box_no', v)} /></td>
                        <td className="min-w-[220px] px-2 py-1.5">
                            <Select value={l.do_detail_id} onChange={(v) => pickLine(i, v)} options={doLines.data?.lines || []}
                                getValue={(o) => o.do_detail_id} getLabel={(o) => `${o.item_code} — ${o.part_name}`} placeholder="— pilih baris —" />
                        </td>
                        <td className="w-24 px-2 py-1.5"><CellInput type="number" value={l.qty} onChange={(v) => setLine(i, 'qty', v)} /></td>
                        <td className="w-28 px-2 py-1.5"><CellInput type="number" step="0.01" value={l.net_weight} onChange={(v) => setLine(i, 'net_weight', v)} /></td>
                        <td className="w-28 px-2 py-1.5"><CellInput type="number" step="0.01" value={l.gross_weight} onChange={(v) => setLine(i, 'gross_weight', v)} /></td>
                        <td className="w-36 px-2 py-1.5"><CellInput value={l.dimension} onChange={(v) => setLine(i, 'dimension', v)} /></td>
                        <td className="px-2 py-1.5"><CellInput value={l.note} onChange={(v) => setLine(i, 'note', v)} /></td>
                        <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                    </>)} />

                {form.lines.length > 0 && (
                    <p className="-mt-3 text-right text-sm text-slate-600">
                        {form.lines.length} kotak · netto {money(Math.round(totNet * 100) / 100)} kg · bruto {money(Math.round(totGross * 100) / 100)} kg
                    </p>
                )}
            </Modal>

            <Modal open={!!detail} onClose={() => setDetail(null)} wide title={`Packing List ${detail?.code || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setDetail(null)}>Tutup</button>}>
                {detail && (<>
                    <div className="mb-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                        <div><p className="text-xs text-slate-400">DO</p><p>{detail.delivery_order?.code}</p></div>
                        <div><p className="text-xs text-slate-400">Pelanggan</p><p>{detail.delivery_order?.so?.cus?.company_n || '—'}</p></div>
                        <div><p className="text-xs text-slate-400">Tanggal</p><p>{detail.date?.slice(0, 10)}</p></div>
                        <div><p className="text-xs text-slate-400">Status</p><StatusBadge status={detail.status} /></div>
                    </div>
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">Kotak</th><th className="px-2 py-2">Item</th>
                                <th className="px-2 py-2 text-right">Qty</th><th className="px-2 py-2 text-right">Netto</th>
                                <th className="px-2 py-2 text-right">Bruto</th><th className="px-2 py-2">Dimensi</th>
                            </tr></thead>
                            <tbody>
                                {(detail.detail || []).map((l) => (
                                    <tr key={l.id} className="border-t border-slate-100">
                                        <td className="px-2 py-1.5 font-medium">{l.box_no}</td>
                                        <td className="px-2 py-1.5">{l.item?.code} — {l.item?.part_name}</td>
                                        <td className="px-2 py-1.5 text-right">{money(l.qty)}</td>
                                        <td className="px-2 py-1.5 text-right">{money(l.net_weight)}</td>
                                        <td className="px-2 py-1.5 text-right">{money(l.gross_weight)}</td>
                                        <td className="px-2 py-1.5 text-slate-500">{l.dimension || '—'}</td>
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
