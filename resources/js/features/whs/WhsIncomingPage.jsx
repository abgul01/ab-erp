import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, VendorSelect, StatusBadge, LineTable, CellInput, money } from '../procurement/common';
import { WhsItemSelect, TypeBadge } from './common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), po_id: '', ven_id: '', do_no: '', note: '', lines: [] };

export default function WhsIncomingPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    const [detail, setDetail] = useState(null);

    const list = useQuery({
        queryKey: ['whs-incoming', { page }],
        queryFn: async () => (await api.get('/whs-incoming', { params: { page, per_page: 15 } })).data,
    });
    const openPos = useQuery({
        queryKey: ['whs-open-pos'],
        queryFn: async () => (await api.get('/whs-incoming/open-pos')).data.data,
        enabled: !!modal,
    });

    const invalidate = () => {
        qc.invalidateQueries({ queryKey: ['whs-incoming'] });
        // Stok berubah begitu penerimaan di-post, jadi layar stok ikut basi.
        qc.invalidateQueries({ queryKey: ['whs-stock'] });
    };

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/whs-incoming/${modal.id}`, payload) : api.post('/whs-incoming', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const post = useMutation({
        mutationFn: async (id) => api.post(`/whs-incoming/${id}/post`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/whs-incoming/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const setLine = (i, k, v) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const delLine = (i) => set('lines', form.lines.filter((_, j) => j !== i));
    const addLine = () => set('lines', [...form.lines, { po_det_id: null, item_id: '', item: null, qty: 1, unit_cost: 0, note: '' }]);

    const pickItem = (i, item) => setForm((f) => ({
        ...f,
        lines: f.lines.map((l, j) => (j === i
            ? { ...l, item_id: item?.id || '', item, unit_cost: Number(l.unit_cost) || Number(item?.standard_cost) || 0 }
            : l)),
    }));

    /* Tarik dari PO: baris yang masih kurang terima, lengkap dengan harganya. */
    const pullPo = async (poId) => {
        set('po_id', poId);
        if (!poId) return;
        const { data } = await api.get(`/whs-po/${poId}/open-lines`);
        const d = data.data;
        setForm((f) => ({
            ...f,
            po_id: poId,
            ven_id: d.ven_id || f.ven_id,
            lines: d.lines.map((l) => ({
                po_det_id: l.po_det_id, item_id: l.item_id,
                item: { id: l.item_id, code: l.code, name: l.name, whs_type: l.whs_type },
                qty: l.outstanding, unit_cost: l.price, note: '',
            })),
        }));
    };

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/whs-incoming/${row.id}`);
        const d = data.data;
        setForm({
            date: d.date?.slice(0, 10), po_id: d.po_id || '', ven_id: d.ven_id || '',
            do_no: d.do_no || '', note: d.note || '',
            lines: (d.detail || []).map((l) => ({
                po_det_id: l.po_det_id, item_id: l.item_id, item: l.item,
                qty: l.qty, unit_cost: l.unit_cost, note: l.note || '',
            })),
        });
        setModal({ mode: 'edit', id: row.id });
    };
    const openDetail = async (row) => {
        const { data } = await api.get(`/whs-incoming/${row.id}`);
        setDetail(data.data);
    };

    const total = form.lines.reduce((a, l) => a + (Number(l.qty) || 0) * (Number(l.unit_cost) || 0), 0);
    const toolLines = form.lines.filter((l) => l.item?.whs_type === 'TOOL');

    const columns = [
        { key: 'code', label: 'No. Penerimaan' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'po', label: 'PO', render: (v) => v?.code || '— tanpa PO' },
        { key: 'ven', label: 'Supplier', render: (v) => v?.company_n || '—' },
        { key: 'do_no', label: 'Surat Jalan', render: (v) => v || '—' },
        { key: 'detail_count', label: '# Barang' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Penerimaan WHS</h1>
                    <p className="text-sm text-slate-500">Stok baru bertambah setelah dokumen di-post. Alat mendapat nomor unit sendiri saat itu juga.</p>
                </div>
                {can('whs-incoming', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Penerimaan</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100" onClick={() => openDetail(row)}><Icon name="eye" /></button>
                        {row.status === 'DRAFT' && can('whs-incoming', 'edit') && (<>
                            <button title="Post — stok bertambah" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50"
                                onClick={() => window.confirm('Post penerimaan ini? Stok akan bertambah dan jurnal dibuat.') && post.mutate(row.id)}><Icon name="check" /></button>
                            <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>
                        </>)}
                        {row.status === 'DRAFT' && can('whs-incoming', 'delete') && (
                            <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus penerimaan?') && remove.mutate(row.id)}><Icon name="trash" /></button>
                        )}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Penerimaan WHS`}
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
                    <div className="sm:col-span-2"><label className="field-label">Tarik dari PO WHS</label>
                        <Select value={form.po_id} onChange={pullPo} options={openPos.data}
                            getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.vendor || 'tanpa supplier'}`} placeholder="— tanpa PO (pembelian langsung) —" />
                        <p className="mt-0.5 text-[11px] text-slate-400">Pembelian kecil tanpa PO tetap boleh diterima, supaya tidak dicatat di luar sistem.</p></div>
                    <div className="sm:col-span-2"><label className="field-label">Supplier</label>
                        <VendorSelect value={form.ven_id} onChange={(v) => set('ven_id', v)} /></div>
                    <div><label className="field-label">No. Surat Jalan</label>
                        <input className="field-input" value={form.do_no} maxLength={50} onChange={(e) => set('do_no', e.target.value)} /></div>
                    <div className="sm:col-span-3"><label className="field-label">Catatan</label>
                        <input className="field-input" value={form.note} maxLength={300} onChange={(e) => set('note', e.target.value)} /></div>
                </div>

                <LineTable title="Barang diterima" onAdd={addLine} lines={form.lines} empty="Belum ada barang."
                    head={['Barang', 'Jenis', 'Qty', 'Harga satuan', 'Jumlah', 'Catatan', '']}
                    row={(l, i) => (<>
                        <td className="min-w-[260px] px-2 py-1.5">
                            {l.po_det_id
                                ? <span className="text-sm">{l.item?.code} — {l.item?.name}</span>
                                : <WhsItemSelect value={l.item_id} onChange={(item) => pickItem(i, item)} />}
                        </td>
                        <td className="w-28 px-2 py-1.5">{l.item?.whs_type ? <TypeBadge type={l.item.whs_type} /> : '—'}</td>
                        <td className="w-24 px-2 py-1.5"><CellInput type="number" value={l.qty} onChange={(v) => setLine(i, 'qty', v)} /></td>
                        <td className="w-32 px-2 py-1.5"><CellInput type="number" step="0.01" value={l.unit_cost} onChange={(v) => setLine(i, 'unit_cost', v)} /></td>
                        <td className="w-32 px-2 py-1.5 text-right text-sm text-slate-600">{money((Number(l.qty) || 0) * (Number(l.unit_cost) || 0))}</td>
                        <td className="px-2 py-1.5"><CellInput value={l.note} onChange={(v) => setLine(i, 'note', v)} /></td>
                        <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                    </>)} />

                {form.lines.length > 0 && (
                    <div className="-mt-3 text-right text-sm">
                        <p className="font-medium text-slate-700">Total: {money(Math.round(total * 100) / 100)}</p>
                        {toolLines.length > 0 && (
                            <p className="text-xs text-violet-700">
                                {toolLines.reduce((a, l) => a + (Number(l.qty) || 0), 0)} unit alat akan dibuatkan nomor unit sendiri saat di-post.
                            </p>
                        )}
                        {form.lines.length > toolLines.length && (
                            <p className="text-xs text-slate-500">
                                {form.lines.length - toolLines.length} baris sparepart/habis pakai akan mendapat serial batch sendiri saat di-post.
                            </p>
                        )}
                    </div>
                )}
            </Modal>

            <Modal open={!!detail} onClose={() => setDetail(null)} wide title={`Penerimaan ${detail?.code || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setDetail(null)}>Tutup</button>}>
                {detail && (
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">Barang</th><th className="px-2 py-2">Jenis</th>
                                <th className="px-2 py-2">Serial batch</th>
                                <th className="px-2 py-2 text-right">Qty</th><th className="px-2 py-2 text-right">Harga</th>
                                <th className="px-2 py-2 text-right">Jumlah</th>
                            </tr></thead>
                            <tbody>
                                {(detail.detail || []).map((l) => (
                                    <tr key={l.id} className="border-t border-slate-100">
                                        <td className="px-2 py-1.5">{l.item?.code} — {l.item?.name}</td>
                                        <td className="px-2 py-1.5">{l.item?.whs_type && <TypeBadge type={l.item.whs_type} />}</td>
                                        <td className="px-2 py-1.5 font-mono text-xs text-slate-600">
                                            {l.serial_code || (l.item?.whs_type === 'TOOL' ? 'nomor unit' : '— belum di-post')}
                                        </td>
                                        <td className="px-2 py-1.5 text-right">{money(l.qty)}</td>
                                        <td className="px-2 py-1.5 text-right">{money(l.unit_cost)}</td>
                                        <td className="px-2 py-1.5 text-right">{money(Math.round(l.qty * l.unit_cost * 100) / 100)}</td>
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
