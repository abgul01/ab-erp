import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { ItemSelect, VendorSelect, StatusBadge, LineTable, CellInput, money } from './common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY_LINE = { item_id: '', price: 0, moq: 0, order_lot: 0, lead_time_days: 0, commit_qty: 0, note: '' };

/**
 * Kontrak berperiode dengan vendor.
 *
 * Bedanya dengan penawaran: kontrak **mengunci** harga selama masa berlakunya.
 * Selama kontrak berjalan, penawaran lepas yang lebih murah sekalipun tidak
 * boleh menggantikan syarat belinya — itulah gunanya ditandatangani.
 */
export default function ContractPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [detail, setDetail] = useState(null);
    const [error, setError] = useState('');

    const list = useQuery({
        queryKey: ['contracts', { page }],
        queryFn: async () => (await api.get('/contracts', { params: { page, per_page: 15 } })).data,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['contracts'] });

    const save = useMutation({
        mutationFn: async (p) => api.post('/contracts', p),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({
        mutationFn: async ({ id, action, body }) => api.post(`/contracts/${id}/${action}`, body || {}),
        onSuccess: (r) => { invalidate(); if (r.data?.data?.message) alert(r.data.data.message); },
        onError: (e) => alert(apiError(e)),
    });

    const openDetail = async (row) => {
        const { data } = await api.get(`/contracts/${row.id}`);
        setDetail(data.data);
    };

    const setLine = (i, k, v) => setModal((m) => ({ ...m, lines: m.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)) }));

    const columns = [
        { key: 'code', label: 'No. Kontrak' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'ven', label: 'Vendor', render: (v) => v?.company_n || '—' },
        { key: 'valid_from', label: 'Mulai', render: (v) => v?.slice(0, 10) },
        { key: 'valid_to', label: 'Sampai', render: (v) => v?.slice(0, 10) },
        { key: 'detail_count', label: '# Material' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Kontrak Vendor</h1>
                    <p className="text-sm text-slate-500">
                        Kesepakatan berperiode yang mengunci harga. Selama kontrak berjalan, penawaran lepas tidak bisa menggantikan syarat belinya.
                    </p>
                </div>
                {can('contracts', 'create') && (
                    <button className="btn btn-primary" onClick={() => {
                        setError('');
                        setModal({
                            date: today(), ven_id: '', ref_no: '',
                            valid_from: today(), valid_to: '', note: '', lines: [{ ...EMPTY_LINE }],
                        });
                    }}><Icon name="plus" /> Buat Kontrak</button>
                )}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Detail" className="rounded p-1.5 text-slate-500 hover:bg-slate-100" onClick={() => openDetail(row)}><Icon name="eye" /></button>
                        {row.status === 'DRAFT' && can('contracts', 'edit') && (
                            <button title="Aktifkan" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50"
                                onClick={() => window.confirm('Aktifkan kontrak ini? Harganya akan mengunci syarat beli seluruh material di dalamnya.') && act.mutate({ id: row.id, action: 'activate' })}>
                                <Icon name="check" /></button>
                        )}
                        {['DRAFT', 'ACTIVE'].includes(row.status) && can('contracts', 'edit') && (
                            <button title="Batalkan" className="rounded p-1.5 text-red-600 hover:bg-red-50" onClick={() => {
                                const note = window.prompt('Alasan pembatalan kontrak:');
                                if (note) act.mutate({ id: row.id, action: 'cancel', body: { note } });
                            }}><Icon name="ban" /></button>
                        )}
                    </div>
                )} />

            {/* ── Form kontrak ── */}
            <Modal open={!!modal} onClose={() => setModal(null)} wide title="Kontrak Vendor Baru"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={save.isPending} onClick={() => { setError(''); save.mutate(modal); }}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {modal && (<>
                    <p className="mb-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        Kontrak dibuat sebagai DRAFT. Harganya baru mengunci syarat beli setelah diaktifkan.
                    </p>
                    <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2"><label className="field-label">Vendor <span className="text-red-500">*</span></label>
                            <VendorSelect value={modal.ven_id} onChange={(v) => setModal({ ...modal, ven_id: v })} /></div>
                        <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label>
                            <input type="date" className="field-input" value={modal.date} onChange={(e) => setModal({ ...modal, date: e.target.value })} /></div>
                        <div><label className="field-label">No. kontrak vendor</label>
                            <input className="field-input" value={modal.ref_no} maxLength={50} onChange={(e) => setModal({ ...modal, ref_no: e.target.value })} /></div>
                        <div><label className="field-label">Berlaku dari <span className="text-red-500">*</span></label>
                            <input type="date" className="field-input" value={modal.valid_from} onChange={(e) => setModal({ ...modal, valid_from: e.target.value })} /></div>
                        <div><label className="field-label">Berlaku sampai <span className="text-red-500">*</span></label>
                            <input type="date" className="field-input" value={modal.valid_to} onChange={(e) => setModal({ ...modal, valid_to: e.target.value })} /></div>
                        <div className="sm:col-span-3"><label className="field-label">Catatan</label>
                            <input className="field-input" value={modal.note} maxLength={300} onChange={(e) => setModal({ ...modal, note: e.target.value })} /></div>
                    </div>

                    <LineTable title="Material dalam kontrak"
                        onAdd={() => setModal({ ...modal, lines: [...modal.lines, { ...EMPTY_LINE }] })}
                        lines={modal.lines} empty="Belum ada material."
                        head={['Material', 'Harga', 'MOQ', 'Kelipatan', 'Lead time', 'Komitmen qty', '']}
                        row={(l, i) => (<>
                            <td className="min-w-[240px] px-2 py-1.5"><ItemSelect value={l.item_id} onChange={(v) => setLine(i, 'item_id', v)} /></td>
                            <td className="w-32 px-2 py-1.5"><CellInput type="number" step="0.0001" value={l.price} onChange={(v) => setLine(i, 'price', v)} /></td>
                            <td className="w-24 px-2 py-1.5"><CellInput type="number" value={l.moq} onChange={(v) => setLine(i, 'moq', v)} /></td>
                            <td className="w-24 px-2 py-1.5"><CellInput type="number" value={l.order_lot} onChange={(v) => setLine(i, 'order_lot', v)} /></td>
                            <td className="w-24 px-2 py-1.5"><CellInput type="number" value={l.lead_time_days} onChange={(v) => setLine(i, 'lead_time_days', v)} /></td>
                            <td className="w-28 px-2 py-1.5"><CellInput type="number" value={l.commit_qty} onChange={(v) => setLine(i, 'commit_qty', v)} /></td>
                            <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50"
                                onClick={() => setModal({ ...modal, lines: modal.lines.filter((_, j) => j !== i) })}><Icon name="trash" /></button></td>
                        </>)} />
                </>)}
            </Modal>

            {/* ── Detail ── */}
            <Modal open={!!detail} onClose={() => setDetail(null)} wide title={detail ? `${detail.code} — ${detail.ven?.company_n}` : ''}
                footer={<button className="btn btn-ghost" onClick={() => setDetail(null)}>Tutup</button>}>
                {detail && (<>
                    <p className="mb-3 text-sm text-slate-600">
                        Berlaku {detail.valid_from?.slice(0, 10)} – {detail.valid_to?.slice(0, 10)} · <StatusBadge status={detail.status} />
                    </p>
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-3 py-2">Material</th><th className="px-3 py-2 text-right">Harga</th>
                                <th className="px-3 py-2 text-right">MOQ</th><th className="px-3 py-2 text-right">Lead time</th>
                                <th className="px-3 py-2 text-right">Komitmen</th>
                            </tr></thead>
                            <tbody>
                                {(detail.detail || []).map((d) => (
                                    <tr key={d.id} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5">{d.item?.code} — {d.item?.part_name}</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.price)}</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.moq)}</td>
                                        <td className="px-3 py-1.5 text-right">{d.lead_time_days} hari</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.commit_qty)}</td>
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
