import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import PickerModal from '../../components/PickerModal';
import { ITEM_COLUMNS } from '../procurement/PoPage';
import { Select, VendorSelect, StatusBadge, useOptions, LineTable, CellInput, money } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), cus_id: '', cus_po_no: '', currency_id: '', lines: [], cus_items: [] };

export default function SoPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [itemPickerFor, setItemPickerFor] = useState(null);
    const [error, setError] = useState('');

    const currencies = useOptions('currencies');
    const taxes = useOptions('taxes');

    const list = useQuery({ queryKey: ['sales-orders', { page }], queryFn: async () => (await api.get('/sales-orders', { params: { page, per_page: 15 } })).data });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['sales-orders'] }); };

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/sales-orders/${modal.id}`, p) : api.post('/sales-orders', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({ mutationFn: async ({ id, action }) => api.post(`/sales-orders/${id}/${action}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/sales-orders/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const loadCusItems = async (cusId) => { if (!cusId) return []; return (await api.get(`/sales-orders/customer-items/${cusId}`)).data.data; };
    const onSelectCus = async (cusId) => { const cus_items = await loadCusItems(cusId); setForm((f) => ({ ...f, cus_id: cusId, cus_items, lines: [] })); };

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/sales-orders/${row.id}`);
        const d = data.data;
        const cus_items = await loadCusItems(d.cus_id);
        setForm({ date: d.date?.slice(0, 10), cus_id: d.cus_id, cus_po_no: d.cus_po_no || '', currency_id: d.currency_id || '', cus_items, lines: (d.detail || []).map((l) => ({ item_id: l.item_id, qty: l.qty, price: l.price, tax_id: l.tax_id || '', due_date: l.due_date?.slice(0, 10) })) });
        setModal({ mode: 'edit', id: row.id });
    };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const itemLabel = (id) => { const it = (form.cus_items || []).find((x) => x.id === id); return it ? `${it.code} — ${it.part_name}` : (id ? `#${id}` : ''); };
    const addLine = () => set('lines', [...form.lines, { item_id: '', qty: 1, price: 0, tax_id: '', due_date: '' }]);
    const setLine = (i, k, v) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const delLine = (i) => set('lines', form.lines.filter((_, j) => j !== i));

    const submit = () => { setError(''); const { cus_items, ...payload } = form; save.mutate(payload); };

    const columns = [
        { key: 'code', label: 'No. SO' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'cus', label: 'Customer', render: (v) => v?.company_n || '—' },
        { key: 'cus_po_no', label: 'PO Customer' },
        { key: 'detail_count', label: '# Item' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Sales Order</h1>
                {can('sales-orders', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah SO</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {row.status === 'DRAFT' && can('sales-orders', 'edit') && <button title="Approve" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate({ id: row.id, action: 'approve' })}><Icon name="check" /></button>}
                        {row.status === 'APPROVED' && can('sales-orders', 'edit') && <button title="Close" className="rounded p-1.5 text-blue-600 hover:bg-blue-50" onClick={() => act.mutate({ id: row.id, action: 'close' })}><Icon name="lock" /></button>}
                        {['DRAFT', 'APPROVED'].includes(row.status) && can('sales-orders', 'edit') && <button title="Cancel" className="rounded p-1.5 text-red-600 hover:bg-red-50" onClick={() => window.confirm('Batalkan SO?') && act.mutate({ id: row.id, action: 'cancel' })}><Icon name="ban" /></button>}
                        {row.status === 'DRAFT' && can('sales-orders', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {row.status === 'DRAFT' && can('sales-orders', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus SO?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} size="max-w-[90rem]" title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Sales Order`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit} disabled={save.isPending}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div className="sm:col-span-2"><label className="field-label">Customer <span className="text-red-500">*</span></label><VendorSelect value={form.cus_id} onChange={onSelectCus} /></div>
                    <div><label className="field-label">No. PO Customer</label><input className="field-input" value={form.cus_po_no} onChange={(e) => set('cus_po_no', e.target.value)} /></div>
                    <div><label className="field-label">Mata Uang</label><Select value={form.currency_id} onChange={(v) => set('currency_id', v)} options={currencies.data} getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="IDR" /></div>
                </div>

                <LineTable title="Item SO" subtitle="Hanya item yang terdaftar untuk customer ini (tab Customer di Item Master)." onAdd={form.cus_id ? addLine : null} lines={form.lines}
                    empty={form.cus_id ? 'Belum ada item.' : 'Pilih customer dulu.'}
                    head={['Item', 'Qty', 'Harga', 'Tax', 'Due Date', 'Jumlah', '']}
                    row={(l, i) => (<>
                        <td className="min-w-[240px] px-2 py-1.5">
                            <button type="button" className="field-input flex w-full items-center justify-between gap-2 text-left" onClick={() => setItemPickerFor(i)}>
                                <span className="truncate">{l.item_id ? itemLabel(l.item_id) : <span className="text-slate-400">— pilih item —</span>}</span>
                                <Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" />
                            </button>
                        </td>
                        <td className="px-2 py-1.5"><div className="w-20"><CellInput type="number" value={l.qty} onChange={(v) => setLine(i, 'qty', v)} /></div></td>
                        <td className="px-2 py-1.5"><div className="w-32"><CellInput type="number" step="0.01" value={l.price} onChange={(v) => setLine(i, 'price', v)} /></div></td>
                        <td className="px-2 py-1.5"><div className="w-28"><Select value={l.tax_id} onChange={(v) => setLine(i, 'tax_id', v)} options={taxes.data} getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="—" /></div></td>
                        <td className="px-2 py-1.5"><div className="w-36"><CellInput type="date" value={l.due_date} onChange={(v) => setLine(i, 'due_date', v)} /></div></td>
                        <td className="px-2 py-1.5 text-right text-slate-600">{money((Number(l.qty) || 0) * (Number(l.price) || 0))}</td>
                        <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                    </>)} />
                <div className="mt-2 text-right text-sm font-semibold text-slate-700">Total: Rp {money(form.lines.reduce((a, l) => a + (Number(l.qty) || 0) * (Number(l.price) || 0), 0))}</div>
            </Modal>

            <PickerModal open={itemPickerFor != null} onClose={() => setItemPickerFor(null)} title="Pilih Item (terdaftar customer)"
                rows={(form.cus_items || []).map((it) => ({ ...it, _key: it.id }))} searchKeys={['code', 'part_name']} columns={ITEM_COLUMNS}
                empty="Customer ini belum punya item terdaftar (tab Customer di Item Master)."
                onSelect={(row) => setLine(itemPickerFor, 'item_id', row.id)} />
        </div>
    );
}
