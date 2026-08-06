import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { ItemSelect, VendorSelect, Select, StatusBadge, LineTable, CellInput, useOptions, money } from './common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY_LINE = { item_id: '', price: 0, moq: 0, order_lot: 0, lead_time_days: 0, note: '' };

/**
 * Penawaran vendor dan perbandingannya.
 *
 * Layar ini menjawab pertanyaan yang selama ini tidak punya jawaban: harga di
 * master syarat beli itu dari mana, dan kenapa vendor ini yang dipilih padahal
 * ada yang lebih murah. Termurah sengaja tidak otomatis menang — mill yang lebih
 * murah dengan lead time 45 hari bisa membuat lini berhenti.
 */
export default function QuotationPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [tab, setTab] = useState('list');
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [detail, setDetail] = useState(null);
    const [compareItem, setCompareItem] = useState('');
    const [error, setError] = useState('');

    const currencies = useOptions('currencies');

    const list = useQuery({
        queryKey: ['quotations', { page }],
        queryFn: async () => (await api.get('/quotations', { params: { page, per_page: 15 } })).data,
        enabled: tab === 'list',
    });
    const comparison = useQuery({
        queryKey: ['quot-comparison', compareItem],
        queryFn: async () => (await api.get('/quotations/comparison', {
            params: compareItem ? { item_id: compareItem } : {},
        })).data.data,
        enabled: tab === 'compare',
    });

    const invalidate = () => {
        qc.invalidateQueries({ queryKey: ['quotations'] });
        qc.invalidateQueries({ queryKey: ['quot-comparison'] });
    };

    const save = useMutation({
        mutationFn: async (p) => (modal.id ? api.put(`/quotations/${modal.id}`, p) : api.post('/quotations', p)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const pick = useMutation({
        mutationFn: async (detailId) => api.post(`/quotations/line/${detailId}/select`),
        onSuccess: (r) => { invalidate(); alert(r.data.data.message); },
        onError: (e) => alert(apiError(e)),
    });
    const reject = useMutation({
        mutationFn: async ({ id, note }) => api.post(`/quotations/${id}/reject`, { note }),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/quotations/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const openCreate = () => {
        setError('');
        setModal({ date: today(), ven_id: '', ref_no: '', currency_id: '', valid_from: today(), valid_to: '', note: '', lines: [{ ...EMPTY_LINE }] });
    };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/quotations/${row.id}`);
        const q = data.data;
        setModal({
            id: q.id, date: q.date?.slice(0, 10), ven_id: q.ven_id, ref_no: q.ref_no || '',
            currency_id: q.currency_id || '', valid_from: q.valid_from?.slice(0, 10) || '',
            valid_to: q.valid_to?.slice(0, 10) || '', note: q.note || '',
            lines: (q.detail || []).map((d) => ({
                item_id: d.item_id, price: d.price, moq: d.moq, order_lot: d.order_lot,
                lead_time_days: d.lead_time_days, note: d.note || '',
            })),
        });
    };
    const openDetail = async (row) => {
        const { data } = await api.get(`/quotations/${row.id}`);
        setDetail(data.data);
    };

    const setLine = (i, k, v) => setModal((m) => ({ ...m, lines: m.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)) }));

    const columns = [
        { key: 'code', label: 'No. Penawaran' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'ven', label: 'Vendor', render: (v) => v?.company_n || '—' },
        { key: 'ref_no', label: 'Ref Vendor', render: (v) => v || '—' },
        { key: 'valid_to', label: 'Berlaku s/d', render: (v) => v?.slice(0, 10) || '—' },
        { key: 'detail_count', label: '# Material' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Penawaran Vendor</h1>
                    <p className="text-sm text-slate-500">
                        Harga di master syarat beli lahir dari sini. Penawaran yang dipilih menjadi harga yang dipakai MRP —
                        lengkap dengan tautan balik, sehingga “kenapa harganya segini” selalu bisa ditelusuri.
                    </p>
                </div>
                {tab === 'list' && can('quotations', 'create') && (
                    <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Catat Penawaran</button>
                )}
            </div>

            <div className="mb-4 flex gap-1.5 border-b border-slate-200 pb-1">
                {[['list', 'Daftar Penawaran'], ['compare', 'Perbandingan Harga']].map(([k, label]) => (
                    <button key={k} onClick={() => setTab(k)}
                        className={`rounded-t px-3 py-1.5 text-sm ${tab === k ? 'border-b-2 border-[var(--ftpi-primary)] font-semibold text-slate-800' : 'text-slate-500 hover:text-slate-700'}`}>
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'list' && (
                <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                    actions={(row) => (
                        <div className="flex justify-end gap-1">
                            <button title="Detail" className="rounded p-1.5 text-slate-500 hover:bg-slate-100" onClick={() => openDetail(row)}><Icon name="eye" /></button>
                            {row.status !== 'SELECTED' && can('quotations', 'edit') && (<>
                                <button title="Ubah" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>
                                <button title="Tolak" className="rounded p-1.5 text-red-600 hover:bg-red-50" onClick={() => {
                                    const note = window.prompt('Alasan penolakan penawaran:');
                                    if (note) reject.mutate({ id: row.id, note });
                                }}><Icon name="ban" /></button>
                            </>)}
                            {row.status !== 'SELECTED' && can('quotations', 'delete') && (
                                <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    onClick={() => window.confirm(`Hapus ${row.code}?`) && remove.mutate(row.id)}><Icon name="trash" /></button>
                            )}
                        </div>
                    )} />
            )}

            {tab === 'compare' && (<>
                <div className="mb-3 max-w-lg">
                    <label className="field-label">Saring per material</label>
                    <ItemSelect value={compareItem} onChange={(v) => setCompareItem(v)} placeholder="— semua material —" />
                </div>

                {(comparison.data || []).length === 0 && (
                    <p className="rounded-md border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-400">
                        Belum ada penawaran yang bisa dibandingkan.
                    </p>
                )}

                {(comparison.data || []).map((g) => (
                    <div key={g.item_id} className="mb-4 rounded-lg border border-slate-200 bg-white">
                        <div className="border-b border-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">
                            {g.item_code} — {g.part_name}
                            <span className="ml-2 text-xs font-normal text-slate-400">{g.quotes.length} penawaran</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                    <th className="px-3 py-2">Vendor</th><th className="px-3 py-2">Penawaran</th>
                                    <th className="px-3 py-2 text-right">Harga</th><th className="px-3 py-2 text-right">MOQ</th>
                                    <th className="px-3 py-2 text-right">Kelipatan</th><th className="px-3 py-2 text-right">Lead time</th>
                                    <th className="px-3 py-2">Berlaku s/d</th><th className="px-3 py-2 text-right"></th>
                                </tr></thead>
                                <tbody>
                                    {g.quotes.map((q) => (
                                        <tr key={q.quot_det_id} className={`border-t border-slate-100 ${q.selected ? 'bg-emerald-50' : q.expired ? 'text-slate-400' : ''}`}>
                                            <td className="px-3 py-1.5">
                                                {q.vendor}
                                                {q.selected && <span className="ml-2 rounded bg-emerald-100 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700">dipakai</span>}
                                            </td>
                                            <td className="px-3 py-1.5 text-xs text-slate-500">{q.quot_code}</td>
                                            <td className="px-3 py-1.5 text-right">
                                                {money(q.price)}
                                                {q.is_cheapest && <span className="ml-1 rounded bg-sky-100 px-1 py-0.5 text-[10px] text-sky-700">termurah</span>}
                                            </td>
                                            <td className="px-3 py-1.5 text-right">{money(q.moq)}</td>
                                            <td className="px-3 py-1.5 text-right">{money(q.order_lot)}</td>
                                            <td className="px-3 py-1.5 text-right">
                                                {q.lead_time_days} hari
                                                {q.is_fastest && <span className="ml-1 rounded bg-violet-100 px-1 py-0.5 text-[10px] text-violet-700">tercepat</span>}
                                            </td>
                                            <td className="px-3 py-1.5 text-xs">
                                                {q.valid_to?.slice(0, 10) || '—'}
                                                {q.expired && <span className="ml-1 text-red-600">kedaluwarsa</span>}
                                            </td>
                                            <td className="px-3 py-1.5 text-right">
                                                {!q.selected && !q.expired && can('quotations', 'edit') && (
                                                    <button className="btn btn-ghost px-2 py-1 text-xs"
                                                        onClick={() => window.confirm(`Pakai penawaran ${q.vendor} sebagai syarat beli material ini?`) && pick.mutate(q.quot_det_id)}>
                                                        <Icon name="check" className="h-3.5 w-3.5" /> Pakai
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ))}
            </>)}

            {/* ── Form penawaran ── */}
            <Modal open={!!modal} onClose={() => setModal(null)} wide title={modal?.id ? 'Ubah Penawaran' : 'Catat Penawaran Vendor'}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={save.isPending} onClick={() => { setError(''); save.mutate(modal); }}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {modal && (<>
                    <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2"><label className="field-label">Vendor <span className="text-red-500">*</span></label>
                            <VendorSelect value={modal.ven_id} onChange={(v) => setModal({ ...modal, ven_id: v })} /></div>
                        <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label>
                            <input type="date" className="field-input" value={modal.date} onChange={(e) => setModal({ ...modal, date: e.target.value })} /></div>
                        <div><label className="field-label">No. surat vendor</label>
                            <input className="field-input" value={modal.ref_no} maxLength={50} onChange={(e) => setModal({ ...modal, ref_no: e.target.value })} /></div>
                        <div><label className="field-label">Berlaku dari</label>
                            <input type="date" className="field-input" value={modal.valid_from} onChange={(e) => setModal({ ...modal, valid_from: e.target.value })} /></div>
                        <div><label className="field-label">Berlaku sampai</label>
                            <input type="date" className="field-input" value={modal.valid_to} onChange={(e) => setModal({ ...modal, valid_to: e.target.value })} />
                            <p className="mt-0.5 text-[11px] text-slate-400">Penawaran kedaluwarsa tidak bisa dipilih.</p></div>
                        <div><label className="field-label">Mata uang</label>
                            <Select value={modal.currency_id} onChange={(v) => setModal({ ...modal, currency_id: v })} options={currencies.data}
                                getValue={(o) => o.id} getLabel={(o) => o.code} placeholder="IDR (default)" /></div>
                        <div className="sm:col-span-2"><label className="field-label">Catatan</label>
                            <input className="field-input" value={modal.note} maxLength={300} onChange={(e) => setModal({ ...modal, note: e.target.value })} /></div>
                    </div>

                    <LineTable title="Material yang ditawarkan"
                        subtitle="MOQ, kelipatan, dan lead time melekat pada penawaran — ketiganya ikut jadi syarat beli bila penawaran ini dipilih."
                        onAdd={() => setModal({ ...modal, lines: [...modal.lines, { ...EMPTY_LINE }] })}
                        lines={modal.lines} empty="Belum ada material."
                        head={['Material', 'Harga', 'MOQ', 'Kelipatan', 'Lead time (hari)', 'Catatan', '']}
                        row={(l, i) => (<>
                            <td className="min-w-[240px] px-2 py-1.5"><ItemSelect value={l.item_id} onChange={(v) => setLine(i, 'item_id', v)} /></td>
                            <td className="w-32 px-2 py-1.5"><CellInput type="number" step="0.0001" value={l.price} onChange={(v) => setLine(i, 'price', v)} /></td>
                            <td className="w-24 px-2 py-1.5"><CellInput type="number" value={l.moq} onChange={(v) => setLine(i, 'moq', v)} /></td>
                            <td className="w-24 px-2 py-1.5"><CellInput type="number" value={l.order_lot} onChange={(v) => setLine(i, 'order_lot', v)} /></td>
                            <td className="w-28 px-2 py-1.5"><CellInput type="number" value={l.lead_time_days} onChange={(v) => setLine(i, 'lead_time_days', v)} /></td>
                            <td className="px-2 py-1.5"><CellInput value={l.note} onChange={(v) => setLine(i, 'note', v)} /></td>
                            <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50"
                                onClick={() => setModal({ ...modal, lines: modal.lines.filter((_, j) => j !== i) })}><Icon name="trash" /></button></td>
                        </>)} />
                </>)}
            </Modal>

            {/* ── Detail ── */}
            <Modal open={!!detail} onClose={() => setDetail(null)} wide title={detail ? `${detail.code} — ${detail.ven?.company_n}` : ''}
                footer={<button className="btn btn-ghost" onClick={() => setDetail(null)}>Tutup</button>}>
                {detail && (
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-3 py-2">Material</th><th className="px-3 py-2 text-right">Harga</th>
                                <th className="px-3 py-2 text-right">MOQ</th><th className="px-3 py-2 text-right">Lead time</th>
                                <th className="px-3 py-2">Dipakai</th>
                            </tr></thead>
                            <tbody>
                                {(detail.detail || []).map((d) => (
                                    <tr key={d.id} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5">{d.item?.code} — {d.item?.part_name}</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.price)}</td>
                                        <td className="px-3 py-1.5 text-right">{money(d.moq)}</td>
                                        <td className="px-3 py-1.5 text-right">{d.lead_time_days} hari</td>
                                        <td className="px-3 py-1.5">{d.selected ? <Icon name="check" className="h-4 w-4 text-emerald-600" /> : <span className="text-slate-300">—</span>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Modal>
        </div>
    );
}
