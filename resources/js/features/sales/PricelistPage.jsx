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
const yearEnd = () => `${new Date().getFullYear()}-12-31`;
const EMPTY = { cus_id: '', status: 'ACTIVE', lines: [], cus_items: [] };

/** Harga jual per customer per item, berlaku pada rentang tanggal (periode/validity). */
export default function PricelistPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [code, setCode] = useState('');
    const [itemPickerFor, setItemPickerFor] = useState(null);
    const [error, setError] = useState('');

    const currencies = useOptions('currencies');
    const idr = (currencies.data || []).find((c) => c.code === 'IDR');

    const list = useQuery({ queryKey: ['pricelists', { page }], queryFn: async () => (await api.get('/pricelists', { params: { page, per_page: 15 } })).data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['pricelists'] });

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/pricelists/${modal.id}`, p) : api.post('/pricelists', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/pricelists/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const loadCusItems = async (cusId) => (cusId ? (await api.get(`/sales-orders/customer-items/${cusId}`)).data.data : []);
    const onSelectCus = async (cusId) => {
        const cus_items = await loadCusItems(cusId);
        setForm((f) => ({ ...f, cus_id: cusId, cus_items, lines: [] }));
    };

    const openCreate = () => { setForm(EMPTY); setCode(''); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/pricelists/${row.id}`);
        const d = data.data;
        const cus_items = await loadCusItems(d.cus_id);
        setForm({
            cus_id: d.cus_id, status: d.status || 'ACTIVE', cus_items,
            lines: (d.detail || []).map((l) => ({
                item_id: l.item_id, price: l.price, currency_id: l.currency_id || '',
                valid_from: l.valid_from?.slice(0, 10) || '', valid_to: l.valid_to?.slice(0, 10) || '',
                min_qty: l.min_qty ?? '',
            })),
        });
        setCode(d.code || '');
        setModal({ mode: 'edit', id: row.id });
    };

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const itemLabel = (id) => { const it = (form.cus_items || []).find((x) => x.id === id); return it ? `${it.code} — ${it.part_name}` : (id ? `#${id}` : ''); };
    const addLine = () => set('lines', [...form.lines, { item_id: '', price: 0, currency_id: idr?.id || '', valid_from: today(), valid_to: yearEnd(), min_qty: '' }]);
    const setLine = (i, k, v) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const delLine = (i) => set('lines', form.lines.filter((_, j) => j !== i));

    const submit = () => {
        setError('');
        const { cus_items, ...rest } = form;
        save.mutate({ ...rest, lines: rest.lines.map((l) => ({ ...l, min_qty: l.min_qty === '' ? null : Number(l.min_qty) })) });
    };

    const columns = [
        { key: 'code', label: 'No. Pricelist' },
        { key: 'cus', label: 'Customer', render: (v) => v?.company_n || 'Umum' },
        { key: 'detail_count', label: '# Item' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
        { key: 'updated_at', label: 'Diubah', render: (v) => v?.slice(0, 10) },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Master Pricelist</h1>
                    <p className="text-xs text-slate-400">Harga jual per customer, berlaku pada periode tertentu. Dipakai otomatis saat input Sales Order.</p>
                </div>
                {can('pricelists', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Pricelist</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('pricelists', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {can('pricelists', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus pricelist?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} size="max-w-6xl" title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Pricelist`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit} disabled={save.isPending}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div><label className="field-label">No. Pricelist</label><input className="field-input bg-slate-100" readOnly value={code} placeholder="— otomatis saat simpan —" /></div>
                    <div className="sm:col-span-2"><label className="field-label">Customer <span className="text-red-500">*</span></label><VendorSelect value={form.cus_id} onChange={onSelectCus} /></div>
                    <div>
                        <label className="field-label">Status</label>
                        <select className="field-input" value={form.status} onChange={(e) => set('status', e.target.value)}>
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>
                    </div>
                </div>

                <LineTable title="Harga per Item" subtitle="Satu baris = satu harga yang berlaku pada rentang tanggal tertentu. Boleh ada beberapa periode untuk item yang sama."
                    onAdd={form.cus_id ? addLine : null} lines={form.lines} empty={form.cus_id ? 'Belum ada baris harga.' : 'Pilih customer dulu.'}
                    head={['Item', 'Mata Uang', 'Harga', 'Berlaku Dari', 'Sampai', 'Min. Qty', '']}
                    row={(l, i) => (<>
                        <td className="min-w-[260px] px-2 py-1.5">
                            <button type="button" className="field-input flex w-full items-center justify-between gap-2 text-left" onClick={() => setItemPickerFor(i)}>
                                <span className="truncate">{l.item_id ? itemLabel(l.item_id) : <span className="text-slate-400">— pilih item —</span>}</span>
                                <Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" />
                            </button>
                        </td>
                        <td className="px-2 py-1.5"><div className="w-28"><Select value={l.currency_id} onChange={(v) => setLine(i, 'currency_id', v)} options={currencies.data} getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="—" /></div></td>
                        <td className="px-2 py-1.5"><div className="w-36"><CellInput type="number" step="0.0001" value={l.price} onChange={(v) => setLine(i, 'price', v)} /></div></td>
                        <td className="px-2 py-1.5"><div className="w-36"><CellInput type="date" value={l.valid_from} onChange={(v) => setLine(i, 'valid_from', v)} /></div></td>
                        <td className="px-2 py-1.5"><div className="w-36"><CellInput type="date" value={l.valid_to} onChange={(v) => setLine(i, 'valid_to', v)} /></div></td>
                        <td className="px-2 py-1.5"><div className="w-24"><CellInput type="number" value={l.min_qty} onChange={(v) => setLine(i, 'min_qty', v)} /></div></td>
                        <td className="px-2 py-1.5 align-middle"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                    </>)} />
                <p className="text-xs text-slate-400">Min. Qty kosong = berlaku untuk semua jumlah. Bila ada beberapa baris yang cocok, sistem memakai min. qty tertinggi yang terpenuhi, lalu periode terbaru.</p>
            </Modal>

            <PickerModal open={itemPickerFor != null} onClose={() => setItemPickerFor(null)} title="Pilih Item (terdaftar customer)"
                rows={(form.cus_items || []).map((it) => ({ ...it, _key: it.id }))} searchKeys={['code', 'part_name']} columns={ITEM_COLUMNS}
                empty="Customer ini belum punya item terdaftar (tab Customer di Item Master)."
                onSelect={(row) => setLine(itemPickerFor, 'item_id', row.id)} />
        </div>
    );
}
