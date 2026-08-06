import { useRef, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { StatusBadge } from './common';

const today = () => new Date().toISOString().slice(0, 10);
const fmtNum = (v) => (v == null ? '—' : Number(v).toLocaleString('id-ID'));

/** PO Info Card — tampil setelah PO dipilih. */
function PoInfoCard({ po }) {
    if (!po) return null;
    const totalOrdered = po.lines?.reduce((s, l) => s + (l.qty_order || 0), 0) || 0;
    const totalSent = po.lines?.reduce((s, l) => s + (l.sent || 0), 0) || 0;
    const totalRemaining = totalOrdered - totalSent;
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
            <div className="mb-2 flex items-center justify-between">
                <div>
                    <span className="text-sm font-semibold text-slate-700">{po.code}</span>
                    <span className="ml-2 text-xs text-slate-500">{po.vendor}</span>
                </div>
                <span className="rounded bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700">
                    Sisa kirim: <strong>{fmtNum(totalRemaining)}</strong> pcs
                </span>
            </div>
            <div className="overflow-x-auto rounded border border-slate-200 bg-white">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-100 text-left font-semibold text-slate-500">
                            <th className="px-2 py-1.5">Item</th>
                            <th className="px-2 py-1.5">Part Name</th>
                            <th className="px-2 py-1.5 text-right">Order</th>
                            <th className="px-2 py-1.5 text-right">Terkirim</th>
                            <th className="px-2 py-1.5 text-right">Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        {po.lines?.map((l) => {
                            const remaining = (l.qty_order || 0) - (l.sent || 0);
                            return (
                                <tr key={l.item_id} className="border-b border-slate-100 last:border-0">
                                    <td className="px-2 py-1 font-medium text-slate-700">{l.item_code}</td>
                                    <td className="px-2 py-1 text-slate-500">{l.part_name}</td>
                                    <td className="px-2 py-1 text-right tabular-nums">{fmtNum(l.qty_order)}</td>
                                    <td className="px-2 py-1 text-right tabular-nums text-slate-400">{fmtNum(l.sent)}</td>
                                    <td className={`px-2 py-1 text-right font-semibold tabular-nums ${remaining > 0 ? 'text-emerald-600' : 'text-slate-400'}`}>
                                        {remaining <= 0 ? '0' : fmtNum(remaining)}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/** Section wrapper with title. */
function Section({ title, children, extra }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-4">
            <div className="mb-3 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-slate-700">{title}</h3>
                {extra}
            </div>
            {children}
        </div>
    );
}

/**
 * Subcont — Kirim (Delivery Note). Against a SUBCONT purchase order, scan the
 * WIP pallets (from cutting/processing) that go out to the vendor. Each scanned
 * pallet carries its WO + item, so the shipment is traceable per pallet.
 */
export default function SubcontDnPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(false);
    const [poId, setPoId] = useState('');
    const [date, setDate] = useState(today());
    const [rows, setRows] = useState([]);
    const [scan, setScan] = useState('');
    const [error, setError] = useState('');
    const [scanError, setScanError] = useState('');
    const [view, setView] = useState(null);
    const scanRef = useRef(null);

    const list = useQuery({ queryKey: ['subcont-dn', { page }], queryFn: async () => (await api.get('/subcont/dn', { params: { page, per_page: 15 } })).data });
    const pos = useQuery({ queryKey: ['subcont-dn', 'pos'], queryFn: async () => (await api.get('/subcont/pos')).data.data, enabled: modal });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['subcont-dn'] });

    const selectedPo = pos.data?.find((p) => p.id === Number(poId)) || null;

    const save = useMutation({
        mutationFn: async () => api.post('/subcont/dn', { date, po_id: poId, lines: rows.map((r) => ({ item_id: r.item_id, qty: r.qty, wo_id: r.wo_id, pallet_code: r.pallet_code })) }),
        onSuccess: () => { invalidate(); setModal(false); },
        onError: (e) => setError(apiError(e)),
    });
    const doScan = useMutation({
        mutationFn: async (code) => (await api.post('/subcont/scan-pallet', { po_id: poId, pallet_code: code })).data.data,
        onSuccess: (row) => {
            setRows((r) => [...r, row]);
            setScan('');
            setScanError('');
            setTimeout(() => scanRef.current?.focus(), 10);
        },
        onError: (e) => { setScanError(apiError(e)); setScan(''); },
    });
    const act = useMutation({ mutationFn: async (id) => api.post(`/subcont/dn/${id}/send`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/subcont/dn/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const open = () => { setPoId(''); setDate(today()); setRows([]); setScan(''); setError(''); setScanError(''); setModal(true); };
    const onSelectPo = (id) => { setPoId(id); setRows([]); setScan(''); setError(''); setScanError(''); setTimeout(() => scanRef.current?.focus(), 50); };
    const onScanKey = (e) => {
        if (e.key !== 'Enter' || !scan.trim()) return;
        e.preventDefault();
        const code = scan.trim();
        if (rows.some((r) => r.pallet_code === code)) { setScanError(`Pallet ${code} sudah discan.`); setScan(''); return; }
        setScanError('');
        doScan.mutate(code);
    };
    const openView = async (row) => { const { data } = await api.get(`/subcont/dn/${row.id}`); setView(data.data); };

    const totalQty = rows.reduce((s, r) => s + (Number(r.qty) || 0), 0);

    const columns = [
        { key: 'code', label: 'No. DN' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'po', label: 'PO Subcont', render: (v) => v?.code || '—' },
        { key: 'ven', label: 'Vendor', render: (v) => v?.company_n || '—' },
        { key: 'detail_count', label: '# Pallet' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Subcont — Kirim (Delivery Note)</h1>
                {can('subcont-dn', 'create') && <button className="btn btn-primary" onClick={open}><Icon name="plus" /> Buat DN</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {row.status === 'DRAFT' && can('subcont-dn', 'edit') && <button title="Kirim" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate(row.id)}><Icon name="send" /></button>}
                        {row.status === 'DRAFT' && can('subcont-dn', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus DN?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={modal} onClose={() => setModal(false)} size="max-w-4xl" title="Buat Delivery Note Subcont"
                footer={<>
                    <div className="mr-auto text-sm text-slate-500">
                        {rows.length > 0 && <span className="font-medium text-slate-700">{rows.length} pallet</span>}
                        {rows.length > 0 && totalQty > 0 && <span> &middot; <span className="font-medium text-slate-700">{fmtNum(totalQty)}</span> pcs</span>}
                    </div>
                    <button className="btn btn-ghost" onClick={() => setModal(false)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(); }} disabled={save.isPending || !poId || rows.length === 0}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                {/* ── Section 1: PO & Tanggal ── */}
                <div className="space-y-3">
                    <Section title="PO Subcont & Tanggal">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                            <div className="sm:col-span-3">
                                <label className="field-label">PO Subcont <span className="text-red-500">*</span></label>
                                <select className="field-input" value={poId} onChange={(e) => onSelectPo(e.target.value ? Number(e.target.value) : '')}>
                                    <option value="">— pilih PO Subcont —</option>
                                    {(pos.data || []).map((p) => <option key={p.id} value={p.id}>{p.code} — {p.vendor}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="field-label">Tanggal</label>
                                <input type="date" className="field-input" value={date} onChange={(e) => setDate(e.target.value)} />
                            </div>
                        </div>
                        {selectedPo && <PoInfoCard po={selectedPo} />}
                    </Section>

                    {/* ── Section 2: Scan Pallet ── */}
                    <Section title="Scan Pallet WIP"
                        extra={rows.length > 0 && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{rows.length} discan</span>}>
                        <div className="relative">
                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Icon name="search" className="h-4 w-4 text-slate-400" />
                            </span>
                            <input
                                ref={scanRef}
                                className="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 disabled:bg-slate-50 disabled:text-slate-400"
                                placeholder={poId ? 'Scan / ketik kode pallet, tekan Enter…' : 'Pilih PO dulu'}
                                value={scan}
                                disabled={!poId || doScan.isPending}
                                onChange={(e) => { setScan(e.target.value); setScanError(''); }}
                                onKeyDown={onScanKey}
                            />
                            {doScan.isPending && <span className="absolute inset-y-0 right-3 flex items-center"><Icon name="spinner" className="h-4 w-4 animate-spin text-slate-400" /></span>}
                        </div>
                        {scanError && <p className="mt-1.5 text-xs font-medium text-red-600">{scanError}</p>}
                        <p className="mt-1 text-xs text-slate-400">Pallet hasil cutting/processing; item pallet harus ada di PO subcont.</p>
                    </Section>

                    {/* ── Section 3: Daftar Pallet Discan ── */}
                    <Section title="Daftar Pallet Discan"
                        extra={rows.length > 0 && <span className="text-xs text-slate-500">Total: <span className="font-semibold text-slate-700">{fmtNum(totalQty)}</span> pcs</span>}>
                        <div className="overflow-x-auto rounded-lg border border-slate-200">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                        <th className="px-3 py-2 w-8">#</th>
                                        <th className="px-3 py-2">Pallet</th>
                                        <th className="px-3 py-2">Item</th>
                                        <th className="px-3 py-2">WO</th>
                                        <th className="px-3 py-2 text-right">Qty</th>
                                        <th className="px-3 py-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="px-3 py-10 text-center">
                                                <div className="flex flex-col items-center gap-1 text-slate-400">
                                                    <Icon name="box" className="h-8 w-8 opacity-40" />
                                                    <span className="text-sm">{poId ? 'Belum ada pallet discan.' : 'Pilih PO terlebih dulu, lalu scan pallet.'}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {rows.map((r, idx) => (
                                        <tr key={r.pallet_code} className="border-b border-slate-100 last:border-0 hover:bg-slate-50/50">
                                            <td className="px-3 py-2 text-center text-xs text-slate-400">{idx + 1}</td>
                                            <td className="px-3 py-2 font-medium text-slate-800">{r.pallet_code}</td>
                                            <td className="px-3 py-2">
                                                <span className="font-medium text-slate-700">{r.item_code}</span>
                                                <span className="ml-1 text-xs text-slate-400">{r.part_name}</span>
                                            </td>
                                            <td className="px-3 py-2 text-slate-500">{r.wo_code}</td>
                                            <td className="px-3 py-2 text-right tabular-nums font-medium">{fmtNum(r.qty)}</td>
                                            <td className="px-3 py-2">
                                                <button
                                                    className="rounded p-1 text-red-400 transition hover:bg-red-50 hover:text-red-600"
                                                    onClick={() => setRows((rs) => rs.filter((x) => x.pallet_code !== r.pallet_code))}
                                                    title="Hapus dari daftar"
                                                >
                                                    <Icon name="trash" className="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Section>
                </div>
            </Modal>

            <Modal open={!!view} onClose={() => setView(null)} size="max-w-2xl" title={`DN ${view?.code || ''} — ${view?.status || ''}`}>
                <div className="mb-2 text-sm text-slate-500">PO {view?.po?.code} &middot; {view?.ven?.company_n}</div>
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Pallet</th><th className="px-2 py-2">Item</th><th className="px-2 py-2 text-right">Qty</th></tr></thead>
                    <tbody>{(view?.detail || []).map((d) => <tr key={d.id} className="border-t border-slate-100"><td className="px-2 py-1.5 font-medium">{d.pallet_code || '—'}</td><td className="px-2 py-1.5">{d.item?.code} — {d.item?.part_name}</td><td className="px-2 py-1.5 text-right">{d.qty}</td></tr>)}</tbody>
                </table>
            </Modal>
        </div>
    );
}
