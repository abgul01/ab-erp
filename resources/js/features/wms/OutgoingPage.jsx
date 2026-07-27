import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, useOptions, CellInput, money } from '../procurement/common';

const today = () => new Date().toISOString().slice(0, 10);

export default function OutgoingPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(null);
    const [view, setView] = useState(null);
    const [error, setError] = useState('');

    const racks = useOptions('racks');
    const shifts = useQuery({ queryKey: ['shifts'], queryFn: async () => (await api.get('/shifts')).data.data });
    const [woCtx, setWoCtx] = useState(null);      // resolved WO from the scan
    const [serialView, setSerialView] = useState(false);

    /** Scanning the WO / Denpyou tells us which RM and which serials may leave. */
    const checkWo = useMutation({
        mutationFn: async (code) => (await api.get(`/outgoing-rm/wo/${encodeURIComponent(code)}`)).data.data,
        onSuccess: (d) => {
            setWoCtx(d);
            setForm((f) => ({ ...f, wo_id: d.wo_id }));
            // the WO already says which RM goes out — don't make the storeman pick it
            if (d.items.length === 1) {
                onSelectItem(d.items[0].item_id);
            } else {
                setForm((f) => ({ ...f, item_id: '', rows: [] }));
            }
        },
        onError: (e) => { setWoCtx(null); setForm((f) => ({ ...f, wo_id: '', item_id: '', rows: [] })); alert(apiError(e)); },
    });
    const remRacks = (racks.data || []).filter((r) => r.active && r.rem_rack);
    const wos = useQuery({
        queryKey: ['work-orders', 'released'],
        queryFn: async () => (await api.get('/work-orders', { params: { status: 2, per_page: 200 } })).data.data,
        staleTime: 20_000,
    });

    const list = useQuery({
        queryKey: ['outgoing-rm', { page }],
        queryFn: async () => (await api.get('/outgoing-rm', { params: { page, per_page: 15 } })).data,
    });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['outgoing-rm'] }); qc.invalidateQueries({ queryKey: ['remaining-rm'] }); qc.invalidateQueries({ queryKey: ['stock-rm'] }); };

    const save = useMutation({
        mutationFn: async (payload) => api.post('/outgoing-rm', payload),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/outgoing-rm/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setForm({ item_id: '', wo_id: '', wo_scan: '', shift_id: '', date: today(), rows: [] }); setWoCtx(null); setError(''); setModal({ mode: 'create' }); };
    const onSelectItem = async (itemId) => {
        if (!itemId) { setForm((f) => ({ ...f, item_id: '', rows: [] })); return; }
        const { data } = await api.get('/stock-rm', { params: { item_id: itemId, per_page: 200 } });
        setForm((f) => ({
            ...f, item_id: itemId,
            rows: (data.data || []).map((s) => ({
                serial_id: s.serial_id, source: s.source, rack: s.rack, qty: s.qty,
                length: Number(s.length) || 0, weight: Number(s.weight) || 0,
                checked: false, length_used: s.length, rem: false, rem_rack_id: '',
            })),
        }));
    };
    const openView = async (row) => { const { data } = await api.get(`/outgoing-rm/${row.id}`); setView(data.data); };

    const setRow = (i, patch) => setForm((f) => ({ ...f, rows: f.rows.map((r, j) => (j === i ? { ...r, ...patch } : r)) }));
    const selected = form?.rows.filter((r) => r.checked) || [];
    const allChecked = form?.rows.length > 0 && form.rows.every((r) => r.checked);
    const toggleAll = (checked) => setForm((f) => ({ ...f, rows: f.rows.map((r) => ({ ...r, checked })) }));

    const submit = () => {
        setError('');
        save.mutate({
            date: form.date, item_id: form.item_id, wo_id: form.wo_id || null, shift_id: form.shift_id || null,
            lines: selected.map((r) => ({
                serial_id: r.serial_id, qty: r.qty, length_used: r.length_used,
                rem: !!r.rem, rem_rack_id: r.rem ? r.rem_rack_id : null,
            })),
        });
    };

    const columns = [
        { key: 'code', label: 'No. Outgoing' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'item', label: 'Item', render: (v) => (v ? `${v.code} — ${v.part_name}` : '—') },
        { key: 'wo_id', label: 'WO', render: (v) => v || '—' },
        { key: 'detail_count', label: '# Serial' },
        { key: 'user', label: 'Oleh', render: (v) => v?.name || '—' },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Outgoing RM</h1>
                {can('outgoing-rm', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Outgoing</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(row)}><Icon name="search" /></button>
                        {can('outgoing-rm', 'delete') && <button title="Batalkan" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Batalkan outgoing ini? Serial kembali on-hand.') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            {/* Create */}
            <Modal open={modal?.mode === 'create'} onClose={() => setModal(null)} size="max-w-[95rem]" title="Outgoing RM (keluar gudang)"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={submit}
                        disabled={save.isPending || !form?.item_id || selected.length === 0 || selected.some((r) => r.rem && !r.rem_rack_id)}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan ({selected.length} serial)
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {form && (<>
                    <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        {/* the scan is the entry point — everything else follows from it */}
                        <div className="sm:col-span-2">
                            <label className="field-label">Scan WO / Denpyou <span className="text-red-500">*</span></label>
                            <div className="flex gap-2">
                                <input className="field-input" value={form.wo_scan || ''} autoFocus placeholder="Scan kode WO atau OP-…"
                                    disabled={!!woCtx}
                                    onChange={(e) => setForm((f) => ({ ...f, wo_scan: e.target.value }))}
                                    onKeyDown={(e) => { if (e.key === 'Enter' && form.wo_scan) checkWo.mutate(form.wo_scan); }}
                                    onBlur={() => { if (form.wo_scan && !woCtx) checkWo.mutate(form.wo_scan); }} />
                                {woCtx ? (
                                    <button className="btn btn-ghost shrink-0" onClick={() => { setWoCtx(null); setForm((f) => ({ ...f, wo_scan: '', wo_id: '', item_id: '', rows: [] })); }}>Ganti</button>
                                ) : (
                                    <button className="btn btn-primary shrink-0" disabled={!form.wo_scan || checkWo.isPending}
                                        onClick={() => checkWo.mutate(form.wo_scan)}>Cek WO</button>
                                )}
                            </div>
                        </div>
                        <div className="flex items-end">
                            <button className="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:bg-slate-300"
                                disabled={!woCtx} onClick={() => setSerialView(true)}>View Serial</button>
                        </div>
                        <div><label className="field-label">Shift</label>
                            <select className="field-input" value={form.shift_id} onChange={(e) => setForm((f) => ({ ...f, shift_id: e.target.value }))}>
                                <option value="">— pilih shift —</option>
                                {(shifts.data || []).map((s) => <option key={s.id} value={s.id}>{s.name || s.code}</option>)}
                            </select>
                        </div>

                        <div className="sm:col-span-2"><label className="field-label">Item (RM dari WO)</label>
                            {woCtx && woCtx.items.length > 1 ? (
                                <select className="field-input" value={form.item_id} onChange={(e) => onSelectItem(e.target.value ? Number(e.target.value) : '')}>
                                    <option value="">— pilih RM dari WO —</option>
                                    {woCtx.items.map((i) => <option key={i.item_id} value={i.item_id}>{i.code} — {i.part_name}</option>)}
                                </select>
                            ) : (
                                <input className="field-input bg-slate-100" readOnly
                                    value={woCtx?.items?.[0] ? `${woCtx.items[0].code} — ${woCtx.items[0].part_name}` : ''}
                                    placeholder="— otomatis dari WO —" />
                            )}
                        </div>
                        <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.date} onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))} /></div>
                        <div><label className="field-label">Work Order</label>
                            <input className="field-input bg-slate-100" readOnly value={woCtx?.wo_code || ''} placeholder="— belum discan —" /></div>
                    </div>

                    {woCtx && (
                        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm">
                            <b>{woCtx.wo_code}</b>{woCtx.no_dp ? ` · Denpyou ${woCtx.no_dp}` : ''} — FG {woCtx.fg?.code} x{money(woCtx.qty)}
                            <div className="text-xs text-emerald-700">RM dibutuhkan: {woCtx.items.map((i) => i.code).join(', ') || '—'}</div>
                        </div>
                    )}

                    {!form.item_id && (
                        <p className="rounded-md border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">
                            {woCtx ? 'Pilih salah satu RM milik WO untuk memuat serial on-hand.' : 'Scan WO / Denpyou terlebih dahulu — item dan serial mengikuti WO.'}
                        </p>
                    )}
                    {form.item_id && form.rows.length === 0 && <p className="rounded-md border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">Tidak ada stok on-hand untuk item ini.</p>}

                    {form.rows.length > 0 && (
                        <div className="max-h-[50vh] overflow-auto rounded-md border border-slate-200">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0"><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                    <th className="px-3 py-2"><input type="checkbox" className="h-4 w-4" checked={allChecked} onChange={(e) => toggleAll(e.target.checked)} /></th>
                                    {['Serial', 'Sumber', 'Rak', 'Panjang (mm)', 'Berat (kg)', 'Length Used (mm)', 'Sisa (mm)', 'Kembalikan Sisa?', 'Rak Remnant'].map((h, k) => <th key={k} className="whitespace-nowrap px-3 py-2">{h}</th>)}
                                </tr></thead>
                                <tbody>
                                    {form.rows.map((r, i) => {
                                        const remLen = Math.max(0, (Number(r.length) || 0) - (Number(r.length_used) || 0));
                                        return (
                                            <tr key={r.serial_id} className={`border-t border-slate-100 ${r.checked ? '' : 'opacity-40'}`}>
                                                <td className="px-3 py-2"><input type="checkbox" className="h-4 w-4" checked={r.checked} onChange={(e) => setRow(i, { checked: e.target.checked })} /></td>
                                                <td className="whitespace-nowrap px-3 py-2 font-medium text-slate-700">{r.serial_id}</td>
                                                <td className="px-3 py-2"><span className={`rounded-full px-2 py-0.5 text-xs ${r.source === 'REMNANT' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'}`}>{r.source}</span></td>
                                                <td className="whitespace-nowrap px-3 py-2 text-slate-500">{r.rack || '—'}</td>
                                                <td className="px-3 py-2 text-right">{money(r.length)}</td>
                                                <td className="px-3 py-2 text-right">{money(r.weight)}</td>
                                                <td className="px-3 py-2"><div className="w-28"><CellInput type="number" step="0.01" value={r.length_used} onChange={(v) => setRow(i, { length_used: v })} /></div></td>
                                                <td className="px-3 py-2 text-right text-slate-600">{money(remLen)}</td>
                                                <td className="px-3 py-2 text-center"><input type="checkbox" className="h-4 w-4" checked={r.rem} disabled={!r.checked || remLen <= 0} onChange={(e) => setRow(i, { rem: e.target.checked })} /></td>
                                                <td className="min-w-[150px] px-3 py-2">
                                                    <Select value={r.rem_rack_id} onChange={(v) => setRow(i, { rem_rack_id: v })} options={remRacks} getValue={(o) => o.id} getLabel={(o) => o.location} placeholder="— rak remnant —" disabled={!r.rem} />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <p className="mt-2 text-xs text-slate-400">Sisa (tankan) bisa dikembalikan langsung di sini, atau lewat transaksi Remaining tersendiri.</p>
                </>)}
            </Modal>

            {/* View */}
            <Modal open={!!view} onClose={() => setView(null)} wide title={`Outgoing ${view?.code || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setView(null)}>Tutup</button>}>
                {view && (<>
                    <div className="mb-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                        <div><span className="text-slate-400">Item</span><div className="font-medium">{view.item?.code}</div></div>
                        <div><span className="text-slate-400">WO</span><div className="font-medium">{view.wo_id || '—'}</div></div>
                        <div><span className="text-slate-400">Tanggal</span><div className="font-medium">{view.date?.slice(0, 10)}</div></div>
                        <div><span className="text-slate-400">Oleh</span><div className="font-medium">{view.user?.name}</div></div>
                    </div>
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                {['Serial', 'Qty', 'Panjang', 'Used', 'Sisa', 'Berat Used', 'Sisa Dikembalikan'].map((h, k) => <th key={k} className="px-3 py-2">{h}</th>)}
                            </tr></thead>
                            <tbody>
                                {(view.detail || []).map((d) => (
                                    <tr key={d.id} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5 font-medium text-slate-700">{d.serial_id}</td>
                                        <td className="px-3 py-1.5 text-right">{d.qty}</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.length_serial)}</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.length_used)}</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.length_rem)}</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.weight_used)}</td>
                                        <td className="px-3 py-1.5">{d.rem_data ? 'Ya' : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {(view.remaining_docs || []).length > 0 && (
                        <p className="mt-2 text-xs text-slate-500">Dokumen Remaining terkait: {(view.remaining_docs || []).map((r) => r.code).join(', ')}</p>
                    )}
                </>)}
            </Modal>

            {/* Serial yang di-booking ke WO hasil scan */}
            <Modal open={serialView} onClose={() => setSerialView(false)} size="max-w-4xl" title={`Serial ter-booking — ${woCtx?.wo_code || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setSerialView(false)}>Tutup</button>}>
                {woCtx && woCtx.items.map((it) => (
                    <div key={it.item_id} className="mb-4">
                        <div className="mb-1 text-sm font-semibold text-slate-700">{it.code} — {it.part_name}</div>
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                <th className="border border-slate-300 px-2 py-1.5">Serial</th>
                                <th className="border border-slate-300 px-2 py-1.5">Qty</th>
                                <th className="border border-slate-300 px-2 py-1.5">Length Book (mm)</th>
                                <th className="border border-slate-300 px-2 py-1.5">Sisa (mm)</th>
                                <th className="border border-slate-300 px-2 py-1.5">Status</th>
                            </tr></thead>
                            <tbody>
                                {it.serials.length === 0 && <tr><td className="border border-slate-300 px-2 py-1.5" colSpan={5}>Belum ada serial ter-booking.</td></tr>}
                                {it.serials.map((s) => (
                                    <tr key={s.serial_id} className={s.issued ? 'bg-slate-50 text-slate-400' : ''}>
                                        <td className="border border-slate-300 px-2 py-1.5 font-medium">{s.serial_id}</td>
                                        <td className="border border-slate-300 px-2 py-1.5">{money(s.qty)}</td>
                                        <td className="border border-slate-300 px-2 py-1.5">{money(s.length_book)}</td>
                                        <td className="border border-slate-300 px-2 py-1.5">{money(s.length_rem)}</td>
                                        <td className="border border-slate-300 px-2 py-1.5">
                                            {s.issued
                                                ? <span className="rounded bg-slate-300 px-2 py-0.5 text-xs text-slate-700">sudah keluar</span>
                                                : <span className="rounded bg-emerald-600 px-2 py-0.5 text-xs text-white">siap keluar</span>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ))}
            </Modal>
        </div>
    );
}
