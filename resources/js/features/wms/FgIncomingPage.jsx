import { useState, useRef, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Icon from '../../components/Icon';

const today = () => new Date().toISOString().slice(0, 10);
const fmtDate = (d) => (d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '');

/**
 * FG Incoming — scan-based transaction (reference app style): Start opens a
 * receipt (assigns the FG code), then each scanned pallet code is added as a
 * detail row; Finish commits, Cancel discards. A list view shows past receipts.
 */
export default function FgIncomingPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const user = useAuth((s) => s.user);
    const [mode, setMode] = useState('list');   // 'list' | 'create'
    const [page, setPage] = useState(1);
    const [view, setView] = useState(null);

    const list = useQuery({ queryKey: ['incoming-fg', { page }], queryFn: async () => (await api.get('/incoming-fg', { params: { page, per_page: 15 } })).data, enabled: mode === 'list' });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['incoming-fg'] }); qc.invalidateQueries({ queryKey: ['stock-fg'] }); };
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/incoming-fg/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const openView = async (row) => { const { data } = await api.get(`/incoming-fg/${row.id}`); setView(data.data); };

    const columns = [
        { key: 'code', label: 'FG Code' },
        { key: 'date', label: 'Date', render: (v) => fmtDate(v) },
        { key: 'user_id', label: 'User' },
        { key: 'detail_count', label: '# Pallet' },
    ];

    if (mode === 'create') {
        return <CreateView user={user} onClose={() => { setMode('list'); invalidate(); }} />;
    }

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Incoming Finished Goods</h1>
                    <p className="text-xs text-slate-400">Terima pallet hasil produksi (FULL di langkah terakhir) ke gudang FG dengan scan.</p>
                </div>
                {can('incoming-fg', 'create') && <button className="btn btn-primary" onClick={() => setMode('create')}><Icon name="plus" /> Create New</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {can('incoming-fg', 'delete') && <button title="Batalkan" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Batalkan penerimaan ini?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            {view && (
                <div className="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8" onClick={() => setView(null)}>
                    <div className="card my-4 w-full max-w-4xl" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                            <h3 className="font-semibold text-slate-800">Penerimaan {view.code}</h3>
                            <button className="rounded p-1 text-slate-400 hover:bg-slate-100" onClick={() => setView(null)}><Icon name="ban" /></button>
                        </div>
                        <div className="overflow-x-auto p-5">
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">No. Lot</th><th className="px-2 py-2">Code Pallet</th><th className="px-2 py-2">Item</th><th className="px-2 py-2 text-right">Qty</th></tr></thead>
                                <tbody>
                                    {(view.detail || []).map((d, i) => (
                                        <tr key={d.id} className="border-t border-slate-100"><td className="px-2 py-1.5 font-medium">{d.no_lot}</td><td className="px-2 py-1.5">{d.pal_pro_code}</td><td className="px-2 py-1.5">{d.item?.code} — {d.item?.part_name}</td><td className="px-2 py-1.5 text-right">{d.qty}</td></tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

/** The scan-based create screen (header + scan field + details table). */
function CreateView({ user, onClose }) {
    const [txn, setTxn] = useState(null);       // { id, code } after Start
    const [date, setDate] = useState(today());
    const [rows, setRows] = useState([]);       // scanned detail rows
    const [scan, setScan] = useState('');
    const [error, setError] = useState('');
    const scanRef = useRef(null);

    const uid = user?.id ? `U${String(user.id).padStart(4, '0')}` : 'U0001';

    const start = useMutation({
        mutationFn: async () => (await api.post('/incoming-fg/start', { date })).data.data,
        onSuccess: (d) => { setTxn(d); setError(''); setTimeout(() => scanRef.current?.focus(), 50); }, onError: (e) => setError(apiError(e)),
    });
    const doScan = useMutation({
        mutationFn: async (code) => (await api.post(`/incoming-fg/${txn.id}/scan`, { pallet_code: code })).data.data,
        onSuccess: (row) => { setRows((r) => [...r, row]); setScan(''); setError(''); scanRef.current?.focus(); }, onError: (e) => { setError(apiError(e)); setScan(''); },
    });
    const delDet = useMutation({
        mutationFn: async (detId) => api.delete(`/incoming-fg/det/${detId}`),
        onSuccess: (_r, detId) => setRows((r) => r.filter((x) => x.id !== detId)), onError: (e) => alert(apiError(e)),
    });
    const finish = useMutation({
        mutationFn: async () => api.post(`/incoming-fg/${txn.id}/finish`),
        onSuccess: onClose, onError: (e) => setError(apiError(e)),
    });
    const cancel = useMutation({
        mutationFn: async () => (txn ? api.delete(`/incoming-fg/${txn.id}`) : Promise.resolve()),
        onSuccess: onClose, onError: (e) => setError(apiError(e)),
    });

    useEffect(() => { if (txn) scanRef.current?.focus(); }, [txn]);
    const onScanKey = (e) => { if (e.key === 'Enter' && scan.trim()) { e.preventDefault(); doScan.mutate(scan.trim()); } };

    return (
        <div className="p-6">
            {/* Create card */}
            <div className="card mb-6">
                <div className="border-b border-slate-200 px-5 py-3 text-center text-lg font-semibold text-slate-700">FG Incoming Transaction Create</div>
                <div className="p-6">
                    {error && <div className="mx-auto mb-4 max-w-lg rounded-md bg-red-50 px-3 py-2 text-center text-sm text-red-700">{error}</div>}
                    <div className="mx-auto grid max-w-3xl grid-cols-1 gap-4 sm:grid-cols-3">
                        <div><label className="field-label">FG Code</label><input className="field-input bg-slate-100" readOnly value={txn?.code || '— otomatis saat Start —'} /></div>
                        <div><label className="field-label">Date</label><input type="date" className="field-input bg-slate-100" value={date} readOnly={!!txn} onChange={(e) => setDate(e.target.value)} /></div>
                        <div><label className="field-label">User</label><input className="field-input bg-slate-100" readOnly value={uid} /></div>
                    </div>

                    <div className="mt-5 flex flex-col items-center gap-4">
                        {!txn ? (
                            <button className="btn btn-primary bg-emerald-600 hover:bg-emerald-700" onClick={() => start.mutate()} disabled={start.isPending}>
                                {start.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : null} Start
                            </button>
                        ) : (
                            <div className="w-full max-w-md">
                                <input ref={scanRef} className="field-input text-center" placeholder="Scan Pallet Code Here" value={scan}
                                    onChange={(e) => setScan(e.target.value)} onKeyDown={onScanKey} disabled={doScan.isPending} />
                                {doScan.isPending && <p className="mt-1 text-center text-xs text-slate-400">memproses…</p>}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <div className="mb-6 flex justify-center gap-3">
                <button className="btn btn-primary" onClick={() => finish.mutate()} disabled={!txn || rows.length === 0 || finish.isPending}>{finish.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : null} FINISH</button>
                <button className="btn bg-red-600 text-white hover:bg-red-700" onClick={() => cancel.mutate()} disabled={cancel.isPending}>CANCEL</button>
            </div>

            {/* Details card */}
            <div className="card">
                <div className="border-b border-slate-200 px-5 py-3 text-center text-lg font-semibold text-slate-700">FG Incoming Transaction Details</div>
                <div className="overflow-x-auto p-4">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">No</th><th className="px-2 py-2">Code</th><th className="px-2 py-2">Customer</th><th className="px-2 py-2">Date</th>
                                <th className="px-2 py-2">User</th><th className="px-2 py-2">Part FG</th><th className="px-2 py-2">WIP</th><th className="px-2 py-2">Size</th>
                                <th className="px-2 py-2">Code Pallet</th><th className="px-2 py-2 text-right">Qty Pallet</th><th className="px-2 py-2 text-right">Qty FG</th><th className="px-2 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && <tr><td colSpan={12} className="px-2 py-6 text-center text-slate-400">Belum ada pallet discan.</td></tr>}
                            {rows.map((r, i) => (
                                <tr key={r.id} className="border-b border-slate-100">
                                    <td className="px-2 py-1.5">{i + 1}</td>
                                    <td className="px-2 py-1.5">{r.code}</td>
                                    <td className="px-2 py-1.5">{r.customer || '—'}</td>
                                    <td className="px-2 py-1.5">{fmtDate(r.date)}</td>
                                    <td className="px-2 py-1.5">{r.user_id}</td>
                                    <td className="px-2 py-1.5">{r.item_code} <span className="text-slate-400">{r.part_fg}</span></td>
                                    <td className="px-2 py-1.5">{r.wip}</td>
                                    <td className="px-2 py-1.5">{r.size}</td>
                                    <td className="px-2 py-1.5 font-medium">{r.code_pallet} {r.source === 'CUT' && <span className="ml-1 rounded bg-amber-100 px-1 text-[10px] text-amber-700">potong</span>}</td>
                                    <td className="px-2 py-1.5 text-right">{r.qty_pallet}</td>
                                    <td className="px-2 py-1.5 text-right">{r.qty_fg}</td>
                                    <td className="px-2 py-1.5"><button className="rounded p-1 text-red-500 hover:bg-red-50" onClick={() => delDet.mutate(r.id)}><Icon name="trash" /></button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
