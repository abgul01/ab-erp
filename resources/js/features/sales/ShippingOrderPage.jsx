import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { StatusBadge, VendorSelect } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = {
    date: today(), carrier_id: '', vehicle_no: '', driver: '', driver_phone: '',
    destination: '', plan_depart: '', note: '', do_ids: [],
};

export default function ShippingOrderPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    const [detail, setDetail] = useState(null);

    const list = useQuery({
        queryKey: ['shipping-orders', { page }],
        queryFn: async () => (await api.get('/shipping-orders', { params: { page, per_page: 15 } })).data,
    });
    // Deliveries not already riding on another truck.
    const dos = useQuery({
        queryKey: ['ship-available-dos', modal?.id],
        queryFn: async () => (await api.get('/shipping-orders/available-dos', { params: { ship_id: modal?.id } })).data.data,
        enabled: !!modal,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['shipping-orders'] });

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/shipping-orders/${modal.id}`, payload) : api.post('/shipping-orders', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({
        mutationFn: async ({ id, action }) => api.post(`/shipping-orders/${id}/${action}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/shipping-orders/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const toggleDo = (id) => set('do_ids', form.do_ids.includes(id) ? form.do_ids.filter((d) => d !== id) : [...form.do_ids, id]);

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/shipping-orders/${row.id}`);
        const d = data.data;
        setForm({
            date: d.date?.slice(0, 10), carrier_id: d.carrier_id || '', vehicle_no: d.vehicle_no || '',
            driver: d.driver || '', driver_phone: d.driver_phone || '', destination: d.destination || '',
            plan_depart: d.plan_depart ? String(d.plan_depart).slice(0, 16).replace(' ', 'T') : '',
            note: d.note || '', do_ids: (d.detail || []).map((x) => x.do_id),
        });
        setModal({ mode: 'edit', id: row.id });
    };
    const openDetail = async (row) => {
        const { data } = await api.get(`/shipping-orders/${row.id}`);
        setDetail(data.data);
    };

    const columns = [
        { key: 'code', label: 'No. Shipping Order' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'vehicle_no', label: 'Kendaraan', render: (v) => v || '—' },
        { key: 'driver', label: 'Sopir', render: (v) => v || '—' },
        { key: 'destination', label: 'Tujuan', render: (v) => v || '—' },
        { key: 'detail_count', label: '# DO' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Shipping Order</h1>
                    <p className="text-sm text-slate-500">Satu kendaraan, satu perjalanan, beberapa Delivery Order. Stok tetap keluar lewat DO.</p>
                </div>
                {can('shipping-orders', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Shipping Order</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100" onClick={() => openDetail(row)}><Icon name="eye" /></button>
                        {row.status === 'DRAFT' && can('shipping-orders', 'edit') && (<>
                            <button title="Berangkatkan" className="rounded p-1.5 text-amber-600 hover:bg-amber-50" onClick={() => act.mutate({ id: row.id, action: 'dispatch' })}><Icon name="send" /></button>
                            <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>
                        </>)}
                        {row.status === 'DISPATCHED' && can('shipping-orders', 'edit') && (
                            <button title="Tandai tiba" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate({ id: row.id, action: 'deliver' })}><Icon name="check" /></button>
                        )}
                        {['DRAFT', 'DISPATCHED'].includes(row.status) && can('shipping-orders', 'edit') && (
                            <button title="Batalkan" className="rounded p-1.5 text-red-600 hover:bg-red-50" onClick={() => window.confirm('Batalkan shipping order ini?') && act.mutate({ id: row.id, action: 'cancel' })}><Icon name="ban" /></button>
                        )}
                        {row.status === 'DRAFT' && can('shipping-orders', 'delete') && (
                            <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus shipping order?') && remove.mutate(row.id)}><Icon name="trash" /></button>
                        )}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Shipping Order`}
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
                    <div><label className="field-label">No. Kendaraan</label>
                        <input className="field-input" value={form.vehicle_no} maxLength={20} onChange={(e) => set('vehicle_no', e.target.value)} placeholder="B 1234 XYZ" />
                        <p className="mt-0.5 text-[11px] text-slate-400">Wajib diisi sebelum diberangkatkan.</p></div>
                    <div><label className="field-label">Rencana Berangkat</label>
                        <input type="datetime-local" className="field-input" value={form.plan_depart} onChange={(e) => set('plan_depart', e.target.value)} /></div>
                    <div><label className="field-label">Sopir</label>
                        <input className="field-input" value={form.driver} maxLength={60} onChange={(e) => set('driver', e.target.value)} /></div>
                    <div><label className="field-label">Telepon Sopir</label>
                        <input className="field-input" value={form.driver_phone} maxLength={25} onChange={(e) => set('driver_phone', e.target.value)} /></div>
                    <div><label className="field-label">Ekspedisi</label>
                        <VendorSelect value={form.carrier_id} onChange={(v) => set('carrier_id', v)} /></div>
                    <div className="sm:col-span-3"><label className="field-label">Tujuan</label>
                        <input className="field-input" value={form.destination} maxLength={200} onChange={(e) => set('destination', e.target.value)} /></div>
                    <div className="sm:col-span-3"><label className="field-label">Catatan</label>
                        <input className="field-input" value={form.note} maxLength={300} onChange={(e) => set('note', e.target.value)} /></div>
                </div>

                <h4 className="mb-2 text-sm font-semibold text-slate-700">Delivery Order yang diangkut</h4>
                <div className="max-h-64 overflow-y-auto rounded-md border border-slate-200">
                    <table className="w-full text-sm">
                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th className="w-10 px-2 py-2"></th><th className="px-2 py-2">DO</th><th className="px-2 py-2">Tanggal</th>
                            <th className="px-2 py-2">Pelanggan</th><th className="px-2 py-2">Status</th>
                        </tr></thead>
                        <tbody>
                            {(dos.data || []).length === 0 && (
                                <tr><td colSpan={5} className="px-2 py-4 text-center text-slate-400">Tidak ada DO yang bisa diangkut.</td></tr>
                            )}
                            {(dos.data || []).map((d) => (
                                <tr key={d.id} className="border-t border-slate-100">
                                    <td className="px-2 py-1.5">
                                        <input type="checkbox" checked={form.do_ids.includes(d.id)} onChange={() => toggleDo(d.id)} />
                                    </td>
                                    <td className="px-2 py-1.5 font-medium">{d.code}</td>
                                    <td className="px-2 py-1.5">{d.date?.slice(0, 10)}</td>
                                    <td className="px-2 py-1.5 text-slate-600">{d.customer || '—'}</td>
                                    <td className="px-2 py-1.5"><StatusBadge status={d.status} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <p className="mt-1 text-xs text-slate-400">
                    {form.do_ids.length} DO dipilih. DO berstatus DRAFT harus dikirim dari gudang FG dulu sebelum truk berangkat.
                </p>
            </Modal>

            <Modal open={!!detail} onClose={() => setDetail(null)} wide title={`Shipping Order ${detail?.code || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setDetail(null)}>Tutup</button>}>
                {detail && (<>
                    <div className="mb-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                        <div><p className="text-xs text-slate-400">Kendaraan</p><p>{detail.vehicle_no || '—'}</p></div>
                        <div><p className="text-xs text-slate-400">Sopir</p><p>{detail.driver || '—'} {detail.driver_phone ? `(${detail.driver_phone})` : ''}</p></div>
                        <div><p className="text-xs text-slate-400">Ekspedisi</p><p>{detail.carrier?.company_n || '—'}</p></div>
                        <div><p className="text-xs text-slate-400">Status</p><StatusBadge status={detail.status} /></div>
                        <div className="col-span-2"><p className="text-xs text-slate-400">Tujuan</p><p>{detail.destination || '—'}</p></div>
                        <div><p className="text-xs text-slate-400">Berangkat</p><p>{detail.departed_at ? String(detail.departed_at).slice(0, 16).replace('T', ' ') : '—'}</p></div>
                        <div><p className="text-xs text-slate-400">Tiba</p><p>{detail.arrived_at ? String(detail.arrived_at).slice(0, 16).replace('T', ' ') : '—'}</p></div>
                    </div>
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">DO</th><th className="px-2 py-2">Tanggal</th>
                                <th className="px-2 py-2">Pelanggan</th><th className="px-2 py-2">Status DO</th>
                            </tr></thead>
                            <tbody>
                                {(detail.detail || []).map((l) => (
                                    <tr key={l.id} className="border-t border-slate-100">
                                        <td className="px-2 py-1.5 font-medium">{l.delivery_order?.code}</td>
                                        <td className="px-2 py-1.5">{l.delivery_order?.date?.slice(0, 10)}</td>
                                        <td className="px-2 py-1.5 text-slate-600">{l.delivery_order?.so?.cus?.company_n || '—'}</td>
                                        <td className="px-2 py-1.5"><StatusBadge status={l.delivery_order?.status} /></td>
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
