import { useQuery } from '@tanstack/react-query';
import api from '../../api/client';
import DataTable from '../../components/DataTable';

/** Read-only finished-goods on-hand (received − issued), per item. */
export default function FgStockPage() {
    const list = useQuery({ queryKey: ['stock-fg'], queryFn: async () => (await api.get('/stock-fg')).data.data });

    const columns = [
        { key: 'item_code', label: 'Kode FG' },
        { key: 'part_name', label: 'Nama Part', render: (v) => v || '—' },
        { key: 'on_hand', label: 'On-hand (pcs)', className: 'text-right', render: (v) => <b className={v > 0 ? 'text-slate-800' : 'text-red-600'}>{v}</b> },
    ];

    return (
        <div className="p-6">
            <h1 className="mb-1 text-xl font-semibold text-slate-800">Stok Finished Goods</h1>
            <p className="mb-4 text-xs text-slate-400">Hasil produksi yang sudah diterima ke gudang FG dikurangi yang sudah dikirim. Sumbernya penerimaan FG dari lantai produksi.</p>
            <DataTable columns={columns} rows={list.data || []} loading={list.isLoading}
                empty="Belum ada stok FG. Terima hasil produksi lewat menu Incoming FG." />
        </div>
    );
}
