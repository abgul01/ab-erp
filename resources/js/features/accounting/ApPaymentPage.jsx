import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { VendorSelect, money } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);

/** AP Payment — settle vendor invoices (Dr Utang Usaha / Cr Kas). */
export default function ApPaymentPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(false);
    const [venId, setVenId] = useState('');
    const [date, setDate] = useState(today());
    const [lines, setLines] = useState([]);   // {inv_id, code, outstanding, amount}
    const [error, setError] = useState('');
    const [view, setView] = useState(null);

    const list = useQuery({ queryKey: ['ap-payments', { page }], queryFn: async () => (await api.get('/ap-payments', { params: { page, per_page: 15 } })).data });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['ap-payments'] }); qc.invalidateQueries({ queryKey: ['journals'] }); };

    const save = useMutation({
        mutationFn: async () => api.post('/ap-payments', { date, ven_id: venId, lines: lines.filter((l) => Number(l.amount) > 0).map((l) => ({ inv_id: l.inv_id, amount: Number(l.amount) })) }),
        onSuccess: () => { invalidate(); setModal(false); }, onError: (e) => setError(apiError(e)),
    });
    const openView = async (row) => { const { data } = await api.get(`/ap-payments/${row.id}`); setView(data.data); };

    const open = () => { setVenId(''); setDate(today()); setLines([]); setError(''); setModal(true); };
    const onVendor = async (id) => {
        setVenId(id);
        if (!id) { setLines([]); return; }
        const { data } = await api.get(`/ap-payments/open-invoices/${id}`);
        setLines(data.data.map((l) => ({ ...l, amount: l.outstanding })));
    };
    const setAmt = (i, v) => setLines((ls) => ls.map((l, j) => (j === i ? { ...l, amount: v } : l)));
    const total = lines.reduce((a, l) => a + (Number(l.amount) || 0), 0);

    const columns = [
        { key: 'code', label: 'No. Bayar' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'ven', label: 'Vendor', render: (v) => v?.company_n || '—' },
        { key: 'amount', label: 'Jumlah', className: 'text-right', render: (v) => money(v) },
        { key: 'detail_count', label: '# Invoice' },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Pembayaran AP (Utang)</h1>
                {can('ap-payments', 'create') && <button className="btn btn-primary" onClick={open}><Icon name="plus" /> Buat Pembayaran</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (<div className="flex justify-end"><button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button></div>)} />

            <Modal open={modal} onClose={() => setModal(false)} size="max-w-3xl" title="Pembayaran ke Vendor"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(false)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(); }} disabled={save.isPending || !venId || total <= 0}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Bayar Rp {money(total)}</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="sm:col-span-2"><label className="field-label">Vendor <span className="text-red-500">*</span></label><VendorSelect value={venId} onChange={onVendor} /></div>
                    <div><label className="field-label">Tanggal</label><input type="date" className="field-input" value={date} onChange={(e) => setDate(e.target.value)} /></div>
                </div>
                <div className="overflow-x-auto rounded-md border border-slate-200">
                    <table className="w-full text-sm">
                        <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Invoice</th><th className="px-2 py-2">No. Vendor</th><th className="px-2 py-2">Jatuh Tempo</th><th className="px-2 py-2 text-right">Sisa Tagihan</th><th className="px-2 py-2 text-right">Bayar</th></tr></thead>
                        <tbody>
                            {!venId && <tr><td colSpan={5} className="px-2 py-6 text-center text-slate-400">Pilih vendor dulu.</td></tr>}
                            {venId && lines.length === 0 && <tr><td colSpan={5} className="px-2 py-6 text-center text-slate-400">Tidak ada tagihan terbuka.</td></tr>}
                            {lines.map((l, i) => (
                                <tr key={l.inv_id} className="border-t border-slate-100">
                                    <td className="px-2 py-1.5 font-medium">{l.code}</td>
                                    <td className="px-2 py-1.5">{l.inv_no || '—'}</td>
                                    <td className="px-2 py-1.5 text-slate-500">{l.due_date?.slice(0, 10) || '—'}</td>
                                    <td className="px-2 py-1.5 text-right">{money(l.outstanding)}</td>
                                    <td className="px-2 py-1.5 text-right"><input type="number" min="0" max={l.outstanding} step="0.01" className="field-input w-32 text-right" value={l.amount} onChange={(e) => setAmt(i, e.target.value)} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Modal>

            <Modal open={!!view} onClose={() => setView(null)} size="max-w-xl" title={`Pembayaran ${view?.code || ''}`}>
                <div className="mb-2 text-sm text-slate-500">{view?.ven?.company_n} · {view?.date?.slice(0, 10)} · Rp {money(view?.amount)}</div>
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Invoice</th><th className="px-2 py-2 text-right">Jumlah</th></tr></thead>
                    <tbody>{(view?.detail || []).map((d) => <tr key={d.id} className="border-t border-slate-100"><td className="px-2 py-1.5">{d.invoice?.code}</td><td className="px-2 py-1.5 text-right">{money(d.amount)}</td></tr>)}</tbody>
                </table>
            </Modal>
        </div>
    );
}
