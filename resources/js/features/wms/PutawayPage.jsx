import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import { Select, useOptions, money } from '../procurement/common';

/**
 * Putaway — placing inspected bars on a rack.
 *
 * Scan-first: the operator scans a serial, the row is located and the rack is
 * assigned. Only serials that passed QAS appear, because an uninspected bar has
 * no business taking up a rack slot.
 */
export default function PutawayPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [scan, setScan] = useState('');
    const [rackId, setRackId] = useState('');
    const [onlyUnplaced, setOnlyUnplaced] = useState(true);
    const [flash, setFlash] = useState(null);

    const racks = useOptions('racks');
    const serials = useQuery({
        queryKey: ['putaway', { scan, onlyUnplaced }],
        queryFn: async () => (await api.get('/putaway/serials', {
            params: { q: scan || undefined, unplaced: onlyUnplaced ? 1 : undefined },
        })).data.data,
    });

    const place = useMutation({
        mutationFn: async ({ serialDbId, rack }) => api.post('/putaway', { serial_id: serialDbId, rack_id: rack }),
        onSuccess: (_r, v) => {
            setFlash(`Serial ditempatkan ke rak.`);
            setScan('');
            qc.invalidateQueries({ queryKey: ['putaway'] });
        },
        onError: (e) => alert(apiError(e)),
    });

    /** Enter on the scan box places the single matching serial straight away. */
    function submitScan() {
        const rows = serials.data || [];
        if (!rackId) return alert('Pilih rak tujuan terlebih dahulu.');
        const exact = rows.filter((r) => String(r.serial_id) === scan.trim());
        if (exact.length !== 1) return alert('Serial tidak ditemukan atau lebih dari satu yang cocok — pilih dari tabel.');
        place.mutate({ serialDbId: exact[0].serial_db_id, rack: rackId });
    }

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Putaway RM</h1>
                <p className="text-xs text-slate-400">
                    Scan serial yang sudah lolos QAS lalu tempatkan ke rak. Hanya serial berstatus OK yang muncul di sini.
                </p>
            </div>

            <div className="card mb-4 flex flex-wrap items-end gap-3 p-4">
                <div className="grow">
                    <label className="field-label">Scan Serial</label>
                    <input className="field-input font-mono" autoFocus value={scan} placeholder="scan nomor serial"
                        onChange={(e) => { setScan(e.target.value); setFlash(null); }}
                        onKeyDown={(e) => e.key === 'Enter' && submitScan()} />
                </div>
                <div className="w-72">
                    <label className="field-label">Rak tujuan</label>
                    <Select value={rackId} onChange={setRackId} options={racks.data}
                        getValue={(o) => o.id} getLabel={(o) => `${o.location}${o.rem_rack ? ' (remnant)' : ''} — ${o.descriptions || ''}`} />
                </div>
                <label className="flex items-center gap-2 pb-2 text-sm text-slate-600">
                    <input type="checkbox" checked={onlyUnplaced} onChange={(e) => setOnlyUnplaced(e.target.checked)} />
                    Hanya yang belum punya rak
                </label>
            </div>

            {flash && <div className="mb-3 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{flash}</div>}

            <div className="card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th className="px-4 py-3">Serial</th>
                            <th className="px-4 py-3">Item</th>
                            <th className="px-4 py-3">Panjang</th>
                            <th className="px-4 py-3">Berat</th>
                            <th className="px-4 py-3">Rak Saat Ini</th>
                            <th className="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {serials.isLoading && <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></td></tr>}
                        {!serials.isLoading && (serials.data || []).length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-400">
                                {onlyUnplaced ? 'Semua serial sudah punya rak.' : 'Tidak ada serial yang cocok.'}
                            </td></tr>
                        )}
                        {(serials.data || []).map((s) => (
                            <tr key={s.inc_detail_id} className="border-b border-slate-100 hover:bg-blue-50/40">
                                <td className="px-4 py-2.5 font-mono">{s.serial_id}</td>
                                <td className="px-4 py-2.5">{s.item_code}<div className="text-xs text-slate-400">{s.item_name}</div></td>
                                <td className="px-4 py-2.5">{money(s.length)}</td>
                                <td className="px-4 py-2.5">{money(s.weight)}</td>
                                <td className="px-4 py-2.5">{s.rack_code || <span className="text-slate-400">belum ditempatkan</span>}</td>
                                <td className="px-4 py-2.5 text-right">
                                    <button className="btn btn-ghost px-2 py-1 text-xs"
                                        disabled={!can('putaway', 'create') || !rackId || place.isPending}
                                        onClick={() => place.mutate({ serialDbId: s.serial_db_id, rack: rackId })}>
                                        Tempatkan
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
