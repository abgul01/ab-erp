import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, VendorSelect, StatusBadge, LineTable, CellInput, useOptions, money } from '../procurement/common';
import { WhsItemSelect } from './common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), ven_id: '', currency_id: '', rate: 1, top_days: 30, eta: '', note: '', lines: [] };

export default function WhsPoPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    const currencies = useOptions('currencies');

    const list = useQuery({
        queryKey: ['whs-po', { page }],
        queryFn: async () => (await api.get('/whs-po', { params: { page, per_page: 15 } })).data,
    });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['whs-po'] });

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/whs-po/${modal.id}`, payload) : api.post('/whs-po', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({
        mutationFn: async ({ id, action, body }) => api.post(`/whs-po/${id}/${action}`, body || {}),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/whs-po/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const setLine = (i, k, v) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const delLine = (i) => set('lines', form.lines.filter((_, j) => j !== i));
    const addLine = () => set('lines', [...form.lines, { item_id: '', qty: 1, price: 0, note: '' }]);

    // Harga acuan di master jadi titik awal, bukan keputusan akhir: pembeli
    // tetap mengisi harga penawaran yang sebenarnya.
    const pickItem = (i, item) => setForm((f) => ({
        ...f,
        lines: f.lines.map((l, j) => (j === i
            ? { ...l, item_id: item?.id || '', price: Number(l.price) || Number(item?.standard_cost) || 0 }
            : l)),
    }));

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/whs-po/${row.id}`);
        const d = data.data;
        setForm({
            date: d.date?.slice(0, 10), ven_id: d.ven_id, currency_id: d.currency_id || '',
            rate: d.rate, top_days: d.top_days, eta: d.eta?.slice(0, 10) || '', note: d.note || '',
            lines: (d.detail || []).map((l) => ({ item_id: l.item_id, qty: l.qty, price: l.price, note: l.note || '' })),
        });
        setModal({ mode: 'edit', id: row.id });
    };

    const total = form.lines.reduce((a, l) => a + (Number(l.qty) || 0) * (Number(l.price) || 0), 0);

    const columns = [
        { key: 'code', label: 'No. PO' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'ven', label: 'Supplier', render: (v) => v?.company_n || '—' },
        { key: 'eta', label: 'ETA', render: (v) => v?.slice(0, 10) || '—' },
        { key: 'detail_count', label: '# Barang' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">PO WHS — Tools &amp; Sparepart</h1>
                    <p className="text-sm text-slate-500">Pembelian barang gudang non-material. Terpisah dari PO bahan baku: tanpa kuota impor dan tanpa landed cost per kg.</p>
                </div>
                {can('whs-po', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah PO</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {row.status === 'DRAFT' && can('whs-po', 'edit') && (<>
                            <button title="Submit" className="rounded p-1.5 text-amber-600 hover:bg-amber-50" onClick={() => act.mutate({ id: row.id, action: 'submit' })}><Icon name="send" /></button>
                            <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>
                        </>)}
                        {row.status === 'SUBMITTED' && can('whs-po', 'edit') && (<>
                            <button title="Approve" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate({ id: row.id, action: 'approve' })}><Icon name="check" /></button>
                            <button title="Reject" className="rounded p-1.5 text-red-600 hover:bg-red-50" onClick={() => {
                                const note = window.prompt('Alasan penolakan:');
                                if (note) act.mutate({ id: row.id, action: 'reject', body: { note } });
                            }}><Icon name="ban" /></button>
                        </>)}
                        {row.status === 'APPROVED' && can('whs-po', 'edit') && (
                            <button title="Tutup PO" className="rounded p-1.5 text-blue-600 hover:bg-blue-50"
                                onClick={() => window.confirm('Tutup PO ini? Sisa pesanan dianggap batal.') && act.mutate({ id: row.id, action: 'close' })}><Icon name="lock" /></button>
                        )}
                        {row.status === 'DRAFT' && can('whs-po', 'delete') && (
                            <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus PO?') && remove.mutate(row.id)}><Icon name="trash" /></button>
                        )}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} PO WHS`}
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
                    <div className="sm:col-span-2"><label className="field-label">Supplier <span className="text-red-500">*</span></label>
                        <VendorSelect value={form.ven_id} onChange={(v) => set('ven_id', v)} /></div>
                    <div><label className="field-label">Mata Uang</label>
                        <Select value={form.currency_id} onChange={(v) => set('currency_id', v)} options={currencies.data}
                            getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="IDR (default)" /></div>
                    <div><label className="field-label">TOP (hari)</label>
                        <input type="number" className="field-input" value={form.top_days} onChange={(e) => set('top_days', e.target.value)} /></div>
                    <div><label className="field-label">ETA</label>
                        <input type="date" className="field-input" value={form.eta} onChange={(e) => set('eta', e.target.value)} /></div>
                    <div className="sm:col-span-3"><label className="field-label">Catatan</label>
                        <input className="field-input" value={form.note} maxLength={300} onChange={(e) => set('note', e.target.value)} /></div>
                </div>

                <LineTable title="Barang" onAdd={addLine} lines={form.lines} empty="Belum ada barang."
                    head={['Barang', 'Qty', 'Harga', 'Jumlah', 'Catatan', '']}
                    row={(l, i) => (<>
                        <td className="min-w-[260px] px-2 py-1.5"><WhsItemSelect value={l.item_id} onChange={(item) => pickItem(i, item)} /></td>
                        <td className="w-24 px-2 py-1.5"><CellInput type="number" value={l.qty} onChange={(v) => setLine(i, 'qty', v)} /></td>
                        <td className="w-32 px-2 py-1.5"><CellInput type="number" step="0.01" value={l.price} onChange={(v) => setLine(i, 'price', v)} /></td>
                        <td className="w-32 px-2 py-1.5 text-right text-sm text-slate-600">{money((Number(l.qty) || 0) * (Number(l.price) || 0))}</td>
                        <td className="px-2 py-1.5"><CellInput value={l.note} onChange={(v) => setLine(i, 'note', v)} /></td>
                        <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                    </>)} />

                {form.lines.length > 0 && <p className="-mt-3 text-right text-sm font-medium text-slate-700">Total: {money(Math.round(total * 100) / 100)}</p>}
            </Modal>
        </div>
    );
}
