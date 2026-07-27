import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { StatusBadge } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);

/**
 * Delivery Order — ship an approved SO from FG stock. Pick the SO, set the qty
 * per line (capped at the outstanding order qty and on-hand FG stock), save as
 * DRAFT, then Ship to move stock and bump the SO's delivered count.
 */
export default function DeliveryOrderPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [soId, setSoId] = useState('');
    const [date, setDate] = useState(today());
    const [lines, setLines] = useState([]);   // {so_detail_id,item_id,item_code,part_name,outstanding,fg_stock,qty}
    const [error, setError] = useState('');
    const [view, setView] = useState(null);
    const [shipDo, setShipDo] = useState(null);      // ship-info payload
    const [alloc, setAlloc] = useState({});          // do_detail_id → { lot_code: qty }
    const [shipErr, setShipErr] = useState('');

    const list = useQuery({ queryKey: ['delivery-orders', { page }], queryFn: async () => (await api.get('/delivery-orders', { params: { page, per_page: 15 } })).data });
    const openSos = useQuery({ queryKey: ['delivery-orders', 'open-sos'], queryFn: async () => (await api.get('/delivery-orders/open-sos')).data.data, enabled: !!modal });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['delivery-orders'] }); qc.invalidateQueries({ queryKey: ['stock-fg'] }); qc.invalidateQueries({ queryKey: ['sales-orders'] }); };

    const save = useMutation({
        mutationFn: async () => api.post('/delivery-orders', { date, so_id: soId, lines: lines.filter((l) => Number(l.qty) > 0).map((l) => ({ so_detail_id: l.so_detail_id, item_id: l.item_id, qty: Number(l.qty) })) }),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({ mutationFn: async ({ id, action }) => api.post(`/delivery-orders/${id}/${action}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/delivery-orders/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const ship = useMutation({
        mutationFn: async (payload) => api.post(`/delivery-orders/${shipDo.id}/ship`, payload),
        onSuccess: () => { invalidate(); setShipDo(null); }, onError: (e) => setShipErr(apiError(e)),
    });

    const openShip = async (row) => {
        setShipErr('');
        const { data } = await api.get(`/delivery-orders/${row.id}/ship-info`);
        setShipDo(data.data);
        setAlloc({});   // empty = server auto-FIFO per line
    };
    const setLotQty = (ddId, lotCode, v) => setAlloc((a) => ({ ...a, [ddId]: { ...(a[ddId] || {}), [lotCode]: v } }));
    const autoLine = (line) => {
        // fill this line's lots FIFO up to its qty
        let need = line.qty; const picks = {};
        for (const lot of line.lots) { if (need <= 0) break; const take = Math.min(need, lot.remaining); picks[lot.lot_code] = take; need -= take; }
        setAlloc((a) => ({ ...a, [line.do_detail_id]: picks }));
    };
    const lineAllocated = (line) => Object.values(alloc[line.do_detail_id] || {}).reduce((s, q) => s + (Number(q) || 0), 0);
    const submitShip = (auto) => {
        setShipErr('');
        if (auto) { ship.mutate({ auto: true }); return; }
        const allocations = {};
        for (const line of shipDo.lines) {
            const picks = Object.entries(alloc[line.do_detail_id] || {}).filter(([, q]) => Number(q) > 0).map(([lot_code, q]) => ({ lot_code, qty: Number(q) }));
            if (picks.length) allocations[line.do_detail_id] = picks;
        }
        ship.mutate({ allocations });
    };

    const openCreate = () => { setSoId(''); setDate(today()); setLines([]); setError(''); setModal({ mode: 'create' }); };
    const onSelectSo = async (id) => {
        setSoId(id);
        if (!id) { setLines([]); return; }
        const { data } = await api.get(`/delivery-orders/so-lines/${id}`);
        setLines(data.data.map((l) => ({ ...l, qty: Math.min(l.outstanding, l.fg_stock) })));
    };
    const setQty = (i, v) => setLines((ls) => ls.map((l, j) => (j === i ? { ...l, qty: v } : l)));

    const openView = async (row) => { const { data } = await api.get(`/delivery-orders/${row.id}`); setView(data.data); };

    const columns = [
        { key: 'code', label: 'No. DO' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'so', label: 'SO', render: (v) => v?.code || '—' },
        { key: 'so', label: 'Customer', render: (v) => v?.cus?.company_n || '—' },
        { key: 'detail_count', label: '# Item' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Delivery Order</h1>
                {can('delivery-orders', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Buat DO</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {row.status === 'DRAFT' && can('delivery-orders', 'edit') && <button title="Kirim (pilih lot)" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => openShip(row)}><Icon name="send" /></button>}
                        {row.status === 'SHIPPED' && can('delivery-orders', 'edit') && <button title="Tandai diterima" className="rounded p-1.5 text-blue-600 hover:bg-blue-50" onClick={() => act.mutate({ id: row.id, action: 'receive' })}><Icon name="check" /></button>}
                        {row.status === 'DRAFT' && can('delivery-orders', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus DO?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} size="max-w-4xl" title="Buat Delivery Order"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(); }} disabled={save.isPending || !soId || !lines.some((l) => Number(l.qty) > 0)}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="sm:col-span-2">
                        <label className="field-label">Sales Order (APPROVED) <span className="text-red-500">*</span></label>
                        <select className="field-input" value={soId} onChange={(e) => onSelectSo(e.target.value ? Number(e.target.value) : '')}>
                            <option value="">— pilih SO —</option>
                            {(openSos.data || []).map((s) => <option key={s.id} value={s.id}>{s.code} — {s.customer}</option>)}
                        </select>
                    </div>
                    <div><label className="field-label">Tanggal</label><input type="date" className="field-input" value={date} onChange={(e) => setDate(e.target.value)} /></div>
                </div>

                <div className="overflow-x-auto rounded-md border border-slate-200">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">Item</th><th className="px-2 py-2">Part</th>
                                <th className="px-2 py-2 text-right">Sisa Order</th><th className="px-2 py-2 text-right">Stok FG</th><th className="px-2 py-2 text-right">Kirim</th>
                            </tr>
                        </thead>
                        <tbody>
                            {!soId && <tr><td colSpan={5} className="px-2 py-6 text-center text-slate-400">Pilih SO dulu.</td></tr>}
                            {soId && lines.length === 0 && <tr><td colSpan={5} className="px-2 py-6 text-center text-slate-400">Tidak ada baris yang belum terkirim.</td></tr>}
                            {lines.map((l, i) => {
                                const max = Math.min(l.outstanding, l.fg_stock);
                                const over = Number(l.qty) > max;
                                return (
                                    <tr key={l.so_detail_id} className="border-t border-slate-100">
                                        <td className="px-2 py-1.5 font-medium">{l.item_code}</td>
                                        <td className="px-2 py-1.5 truncate">{l.part_name}</td>
                                        <td className="px-2 py-1.5 text-right">{l.outstanding}</td>
                                        <td className={`px-2 py-1.5 text-right ${l.fg_stock < l.outstanding ? 'text-amber-600' : ''}`}>{l.fg_stock}</td>
                                        <td className="px-2 py-1.5 text-right">
                                            <input type="number" min="0" max={max} className={`field-input w-24 text-right ${over ? 'border-red-400' : ''}`} value={l.qty} onChange={(e) => setQty(i, e.target.value)} />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                <p className="mt-2 text-xs text-slate-400">Qty kirim dibatasi sisa order dan stok FG yang tersedia.</p>
            </Modal>

            {/* Ship: choose which lot(s) to draw from, or auto-FIFO */}
            <Modal open={!!shipDo} onClose={() => setShipDo(null)} size="max-w-4xl" title={`Kirim ${shipDo?.code || ''} — pilih lot`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setShipDo(null)}>Batal</button>
                    <button className="btn btn-ghost" onClick={() => submitShip(true)} disabled={ship.isPending} title="Ambil lot paling lama dulu (FIFO)"><Icon name="layers" /> Auto (FIFO)</button>
                    <button className="btn btn-primary" onClick={() => submitShip(false)} disabled={ship.isPending}>{ship.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="send" />} Kirim Pilihan</button>
                </>}>
                {shipErr && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{shipErr}</div>}
                <p className="mb-3 text-xs text-slate-400">Untuk tiap baris, tentukan qty dari tiap lot (total harus = qty kirim), atau klik <b>Auto baris</b>. Tombol <b>Auto (FIFO)</b> di bawah mengirim semua baris otomatis dari lot terlama.</p>
                <div className="space-y-4">
                    {(shipDo?.lines || []).map((line) => {
                        const allocd = lineAllocated(line);
                        const bad = allocd !== line.qty;
                        return (
                            <div key={line.do_detail_id} className="rounded-md border border-slate-200">
                                <div className="flex items-center justify-between bg-slate-50 px-3 py-2">
                                    <div className="text-sm"><b>{line.item_code}</b> <span className="text-slate-400">{line.part_name}</span> — kirim <b>{line.qty}</b></div>
                                    <div className="flex items-center gap-2">
                                        <span className={`text-xs ${bad ? 'text-red-600' : 'text-emerald-600'}`}>dialokasikan {allocd}/{line.qty}</span>
                                        <button type="button" className="btn btn-ghost px-2 py-1 text-xs" onClick={() => autoLine(line)}>Auto baris</button>
                                    </div>
                                </div>
                                <table className="w-full text-sm">
                                    <thead><tr className="text-left text-xs font-semibold text-slate-500"><th className="px-3 py-1.5">No. Lot</th><th className="px-3 py-1.5">Pallet</th><th className="px-3 py-1.5">Terima</th><th className="px-3 py-1.5 text-right">Sisa Lot</th><th className="px-3 py-1.5 text-right">Ambil</th></tr></thead>
                                    <tbody>
                                        {line.lots.length === 0 && <tr><td colSpan={5} className="px-3 py-3 text-center text-slate-400">Tidak ada stok lot untuk item ini.</td></tr>}
                                        {line.lots.map((lot) => (
                                            <tr key={lot.lot_code} className="border-t border-slate-100">
                                                <td className="px-3 py-1.5 font-medium">{lot.no_lot}</td>
                                                <td className="px-3 py-1.5">{lot.pallet}</td>
                                                <td className="px-3 py-1.5 text-slate-500">{lot.date?.slice(0, 10)}</td>
                                                <td className="px-3 py-1.5 text-right">{lot.remaining}</td>
                                                <td className="px-3 py-1.5 text-right"><input type="number" min="0" max={lot.remaining} className="field-input w-24 text-right" value={alloc[line.do_detail_id]?.[lot.lot_code] ?? ''} onChange={(e) => setLotQty(line.do_detail_id, lot.lot_code, e.target.value)} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        );
                    })}
                </div>
            </Modal>

            <Modal open={!!view} onClose={() => setView(null)} size="max-w-3xl" title={`DO ${view?.code || ''} — ${view?.status || ''}`}>
                <div className="mb-2 text-sm text-slate-500">SO {view?.so?.code} · {view?.so?.cus?.company_n}</div>
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Item</th><th className="px-2 py-2">Part</th><th className="px-2 py-2 text-right">Qty</th></tr></thead>
                    <tbody>
                        {(view?.detail || []).map((d) => (
                            <tr key={d.id} className="border-t border-slate-100"><td className="px-2 py-1.5">{d.item?.code}</td><td className="px-2 py-1.5">{d.item?.part_name}</td><td className="px-2 py-1.5 text-right">{d.qty}</td></tr>
                        ))}
                    </tbody>
                </table>
            </Modal>
        </div>
    );
}
