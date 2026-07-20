import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/client';
import DataTable from '../../components/DataTable';
import Icon from '../../components/Icon';
import { Select, useOptions, money } from '../procurement/common';

export default function StockRmPage() {
    const [q, setQ] = useState('');
    const [search, setSearch] = useState('');
    const [rackId, setRackId] = useState('');
    const [itemId, setItemId] = useState('');
    const [page, setPage] = useState(1);

    const racks = useOptions('racks');
    const items = useOptions('items');

    const summary = useQuery({
        queryKey: ['stock-rm', 'summary'],
        queryFn: async () => (await api.get('/stock-rm/summary')).data.data,
    });

    const list = useQuery({
        queryKey: ['stock-rm', { search, rackId, itemId, page }],
        queryFn: async () => (await api.get('/stock-rm', {
            params: { q: search, rack_id: rackId || undefined, item_id: itemId || undefined, page, per_page: 20 },
        })).data,
    });

    const columns = [
        { key: 'serial_id', label: 'Serial' },
        { key: 'source', label: 'Sumber', render: (v) => <span className={`rounded-full px-2 py-0.5 text-xs ${v === 'REMNANT' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'}`}>{v}</span> },
        { key: 'item_code', label: 'Kode Item' },
        { key: 'part_name', label: 'Nama Part' },
        { key: 'millsheet', label: 'Millsheet', render: (v) => v || '—' },
        { key: 'qty', label: 'Qty' },
        { key: 'length', label: 'Length (mm)', render: (v) => money(v) },
        { key: 'weight', label: 'Weight (kg)', render: (v) => money(v) },
        { key: 'rack', label: 'Rak', render: (v) => v || '—' },
        { key: 'ref_code', label: 'Dok. Asal' },
        { key: 'doc_date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
    ];

    return (
        <div className="p-6">
            <h1 className="mb-4 text-xl font-semibold text-slate-800">Stok Raw Material</h1>

            {/* Summary per item */}
            <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {(summary.data || []).map((s) => (
                    <div key={s.item_id} className="card p-4">
                        <div className="text-sm font-semibold text-slate-700">{s.item_code}</div>
                        <div className="mb-2 truncate text-xs text-slate-400">{s.part_name}</div>
                        <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                            <span><b className="text-base text-slate-800">{s.total_qty}</b> pcs</span>
                            <span><b>{s.serial_count}</b> serial</span>
                            <span><b>{money(s.total_weight)}</b> kg</span>
                        </div>
                    </div>
                ))}
                {summary.data?.length === 0 && <div className="card col-span-full p-6 text-center text-sm text-slate-400">Belum ada stok. Lakukan Incoming dari GR terlebih dulu.</div>}
            </div>

            {/* Filters */}
            <form onSubmit={(e) => { e.preventDefault(); setPage(1); setSearch(q); }} className="mb-4 flex flex-wrap items-center gap-2">
                <div className="relative w-64">
                    <Icon name="search" className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input className="field-input pl-9" placeholder="Cari serial / item…" value={q} onChange={(e) => setQ(e.target.value)} />
                </div>
                <div className="w-52"><Select value={itemId} onChange={(v) => { setItemId(v); setPage(1); }} options={items.data} getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="Semua item" /></div>
                <div className="w-44"><Select value={rackId} onChange={(v) => { setRackId(v); setPage(1); }} options={racks.data} getValue={(o) => o.id} getLabel={(o) => o.location} placeholder="Semua rak" /></div>
                <button className="btn btn-ghost">Cari</button>
            </form>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage} />
        </div>
    );
}
