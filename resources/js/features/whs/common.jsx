import { useQuery } from '@tanstack/react-query';
import api from '../../api/client';

/** Jenis barang WHS, dan apa arti perbedaannya. */
export const WHS_TYPES = [
    { value: 'PART', label: 'Sparepart', hint: 'keluar sekali, jadi beban' },
    { value: 'CONSUMABLE', label: 'Habis Pakai', hint: 'keluar sekali, jadi beban' },
    { value: 'TOOL', label: 'Alat Kerja', hint: 'dipinjam, harus kembali' },
];

export const TYPE_LABEL = Object.fromEntries(WHS_TYPES.map((t) => [t.value, t.label]));

export const TYPE_STYLE = {
    PART: 'bg-sky-100 text-sky-700',
    CONSUMABLE: 'bg-slate-100 text-slate-600',
    TOOL: 'bg-violet-100 text-violet-700',
};

export function TypeBadge({ type }) {
    return (
        <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${TYPE_STYLE[type] || 'bg-slate-100 text-slate-600'}`}>
            {TYPE_LABEL[type] || type}
        </span>
    );
}

export function useWhsItems(params = {}) {
    return useQuery({
        queryKey: ['whs-items-opt', params],
        queryFn: async () => (await api.get('/whs-items', { params: { per_page: 500, active_only: 1, ...params } })).data.data,
        staleTime: 60_000,
    });
}

/**
 * Pemilih barang WHS yang mengembalikan barangnya, bukan hanya id-nya.
 *
 * Layar pemanggil hampir selalu butuh jenis dan harga acuannya juga — alat
 * diperlakukan lain dari barang habis pakai, dan itu ditentukan oleh jenisnya.
 */
export function WhsItemSelect({ value, onChange, type, placeholder }) {
    const { data } = useWhsItems(type ? { whs_type: type } : {});

    return (
        <select
            className="field-input"
            value={value ?? ''}
            onChange={(e) => {
                const id = e.target.value === '' ? '' : Number(e.target.value);
                onChange(id === '' ? null : (data || []).find((o) => o.id === id) || null);
            }}
        >
            <option value="">{placeholder || '— pilih barang —'}</option>
            {(data || []).map((o) => (
                <option key={o.id} value={o.id}>
                    {o.code} — {o.name} ({TYPE_LABEL[o.whs_type] || o.whs_type})
                </option>
            ))}
        </select>
    );
}
