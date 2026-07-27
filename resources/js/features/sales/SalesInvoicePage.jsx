import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { VendorSelect, StatusBadge, money } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);

/**
 * Sales Invoice — bill a customer for goods shipped on Delivery Orders. Pick the
 * customer, tick the DO lines to bill; price comes from the SO line. Tax follows
 * PMK 131/2024 (DPP nilai lain 11/12, VAT 12%). Post to freeze and flag the DOs.
 */
export default function SalesInvoicePage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(false);
    const [cusId, setCusId] = useState('');
    const [date, setDate] = useState(today());
    const [dueDate, setDueDate] = useState('');
    const [taxInvNo, setTaxInvNo] = useState('');
    const [picked, setPicked] = useState({});   // do_detail_id → line
    const [error, setError] = useState('');
    const [view, setView] = useState(null);

    const list = useQuery({ queryKey: ['sales-invoices', { page }], queryFn: async () => (await api.get('/sales-invoices', { params: { page, per_page: 15 } })).data });
    const openDos = useQuery({ queryKey: ['sales-invoices', 'open-dos', cusId], queryFn: async () => (await api.get(`/sales-invoices/open-dos/${cusId}`)).data.data, enabled: modal && !!cusId });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['sales-invoices'] }); qc.invalidateQueries({ queryKey: ['delivery-orders'] }); };

    const save = useMutation({
        mutationFn: async () => api.post('/sales-invoices', {
            date, cus_id: cusId, due_date: dueDate || null, tax_inv_no: taxInvNo || null,
            do_detail_ids: Object.keys(picked).filter((k) => picked[k]).map(Number),
        }),
        onSuccess: () => { invalidate(); setModal(false); }, onError: (e) => setError(apiError(e)),
    });
    const post = useMutation({ mutationFn: async (id) => api.post(`/sales-invoices/${id}/post`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/sales-invoices/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const open = () => { setCusId(''); setDate(today()); setDueDate(''); setTaxInvNo(''); setPicked({}); setError(''); setModal(true); };
    const onSelectCus = (id) => { setCusId(id); setPicked({}); };
    const allLines = (openDos.data || []).flatMap((d) => d.lines.map((l) => ({ ...l, do_code: d.do_code })));
    const toggle = (l) => setPicked((p) => ({ ...p, [l.do_detail_id]: p[l.do_detail_id] ? undefined : l }));

    const chosen = allLines.filter((l) => picked[l.do_detail_id]);
    const dpp = chosen.reduce((a, l) => a + l.qty * l.price, 0);
    const nilaiLain = Math.round(dpp * (11 / 12) * 100) / 100;
    const vat = Math.round(nilaiLain * 0.12 * 100) / 100;
    const total = dpp + vat;

    const openView = async (row) => { const { data } = await api.get(`/sales-invoices/${row.id}`); setView(data.data); };

    const columns = [
        { key: 'code', label: 'No. Invoice' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'cus', label: 'Customer', render: (v) => v?.company_n || '—' },
        { key: 'total', label: 'Total', className: 'text-right', render: (v) => money(v) },
        { key: 'tax_inv_no', label: 'Faktur Pajak', render: (v) => v || '—' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Sales Invoice</h1>
                {can('sales-invoices', 'create') && <button className="btn btn-primary" onClick={open}><Icon name="plus" /> Buat Invoice</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {row.status === 'DRAFT' && can('sales-invoices', 'edit') && <button title="Posting" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => post.mutate(row.id)}><Icon name="check" /></button>}
                        {row.status === 'DRAFT' && can('sales-invoices', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus invoice?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={modal} onClose={() => setModal(false)} size="max-w-4xl" title="Buat Sales Invoice"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(false)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(); }} disabled={save.isPending || chosen.length === 0}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div className="sm:col-span-2"><label className="field-label">Customer <span className="text-red-500">*</span></label><VendorSelect value={cusId} onChange={onSelectCus} /></div>
                    <div><label className="field-label">Tanggal</label><input type="date" className="field-input" value={date} onChange={(e) => setDate(e.target.value)} /></div>
                    <div><label className="field-label">Jatuh Tempo</label><input type="date" className="field-input" value={dueDate} onChange={(e) => setDueDate(e.target.value)} /></div>
                    <div className="sm:col-span-2"><label className="field-label">No. Faktur Pajak (Coretax)</label><input className="field-input" maxLength={30} value={taxInvNo} onChange={(e) => setTaxInvNo(e.target.value)} placeholder="opsional" /></div>
                </div>

                <div className="overflow-x-auto rounded-md border border-slate-200">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2 w-10"></th><th className="px-2 py-2">DO</th><th className="px-2 py-2">Item</th><th className="px-2 py-2">Part</th>
                                <th className="px-2 py-2 text-right">Qty</th><th className="px-2 py-2 text-right">Harga</th><th className="px-2 py-2 text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            {!cusId && <tr><td colSpan={7} className="px-2 py-6 text-center text-slate-400">Pilih customer dulu.</td></tr>}
                            {cusId && allLines.length === 0 && <tr><td colSpan={7} className="px-2 py-6 text-center text-slate-400">Tidak ada DO terkirim yang belum difakturkan.</td></tr>}
                            {allLines.map((l) => (
                                <tr key={l.do_detail_id} className="border-t border-slate-100 hover:bg-slate-50 cursor-pointer" onClick={() => toggle(l)}>
                                    <td className="px-2 py-1.5"><input type="checkbox" checked={!!picked[l.do_detail_id]} onChange={() => toggle(l)} /></td>
                                    <td className="px-2 py-1.5">{l.do_code}</td>
                                    <td className="px-2 py-1.5 font-medium">{l.item_code}</td>
                                    <td className="px-2 py-1.5 truncate">{l.part_name}</td>
                                    <td className="px-2 py-1.5 text-right">{l.qty}</td>
                                    <td className="px-2 py-1.5 text-right">{money(l.price)}</td>
                                    <td className="px-2 py-1.5 text-right">{money(l.qty * l.price)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="mt-3 flex justify-end">
                    <dl className="w-72 space-y-1 text-sm">
                        <div className="flex justify-between text-slate-600"><dt>DPP</dt><dd>{money(dpp)}</dd></div>
                        <div className="flex justify-between text-slate-600"><dt>DPP nilai lain (11/12)</dt><dd>{money(nilaiLain)}</dd></div>
                        <div className="flex justify-between text-slate-600"><dt>PPN 12%</dt><dd>{money(vat)}</dd></div>
                        <div className="flex justify-between border-t border-slate-200 pt-1 font-semibold text-slate-800"><dt>Total</dt><dd>Rp {money(total)}</dd></div>
                    </dl>
                </div>
            </Modal>

            <Modal open={!!view} onClose={() => setView(null)} size="max-w-3xl" title={`Invoice ${view?.code || ''} — ${view?.status || ''}`}>
                <div className="mb-2 text-sm text-slate-500">{view?.cus?.company_n} · {view?.date?.slice(0, 10)}{view?.tax_inv_no ? ` · Faktur ${view.tax_inv_no}` : ''}</div>
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Item</th><th className="px-2 py-2 text-right">Qty</th><th className="px-2 py-2 text-right">Harga</th><th className="px-2 py-2 text-right">Jumlah</th></tr></thead>
                    <tbody>
                        {(view?.detail || []).map((d) => (
                            <tr key={d.id} className="border-t border-slate-100"><td className="px-2 py-1.5">{d.item?.code} — {d.item?.part_name}</td><td className="px-2 py-1.5 text-right">{d.qty}</td><td className="px-2 py-1.5 text-right">{money(d.price)}</td><td className="px-2 py-1.5 text-right">{money(d.amount)}</td></tr>
                        ))}
                    </tbody>
                </table>
                <div className="mt-3 flex justify-end">
                    <dl className="w-72 space-y-1 text-sm">
                        <div className="flex justify-between text-slate-600"><dt>DPP</dt><dd>{money(view?.dpp)}</dd></div>
                        <div className="flex justify-between text-slate-600"><dt>DPP nilai lain</dt><dd>{money(view?.dpp_nilai_lain)}</dd></div>
                        <div className="flex justify-between text-slate-600"><dt>PPN</dt><dd>{money(view?.vat)}</dd></div>
                        <div className="flex justify-between border-t border-slate-200 pt-1 font-semibold text-slate-800"><dt>Total</dt><dd>Rp {money(view?.total)}</dd></div>
                    </dl>
                </div>
            </Modal>
        </div>
    );
}
