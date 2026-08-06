import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, StatusBadge, LineTable, CellInput, useOptions, money } from '../procurement/common';
import { TypeBadge } from './common';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), dept: '', receiver: '', cost_center: '', note: '', lines: [] };

export default function WhsOutgoingPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    const [detail, setDetail] = useState(null);
    const machines = useOptions('machines');

    const list = useQuery({
        queryKey: ['whs-outgoing', { page }],
        queryFn: async () => (await api.get('/whs-outgoing', { params: { page, per_page: 15 } })).data,
    });
    // Hanya barang yang benar-benar ada stoknya yang bisa dipilih; sisanya cuma
    // mengundang dokumen yang gagal saat di-post.
    const stockItems = useQuery({
        queryKey: ['whs-avail-items'],
        queryFn: async () => (await api.get('/whs-outgoing/available-items')).data.data,
        enabled: !!modal,
    });

    const invalidate = () => {
        qc.invalidateQueries({ queryKey: ['whs-outgoing'] });
        qc.invalidateQueries({ queryKey: ['whs-stock'] });
        qc.invalidateQueries({ queryKey: ['whs-avail-items'] });
    };

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/whs-outgoing/${modal.id}`, payload) : api.post('/whs-outgoing', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const post = useMutation({
        mutationFn: async (id) => api.post(`/whs-outgoing/${id}/post`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/whs-outgoing/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const setLine = (i, k, v) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const delLine = (i) => set('lines', form.lines.filter((_, j) => j !== i));
    const addLine = () => set('lines', [...form.lines, { item_id: '', row: null, serial_code: '', serials: [], qty: 1, machine_id: '', cost_center: '', note: '' }]);

    /*
     * Memilih barang sekaligus mengambil daftar batch penerimaannya. Batch itu
     * yang nanti muncul di catatan downtime mesin, jadi petugas harus bisa
     * menunjuk yang benar-benar diambil dari rak — bukan sekadar "bearing".
     */
    const pickItem = async (i, itemId) => {
        const row = (stockItems.data || []).find((r) => r.item_id === itemId);
        setForm((f) => ({
            ...f,
            lines: f.lines.map((l, j) => (j === i
                ? { ...l, item_id: itemId, row, unit_cost: row?.unit_cost || 0, serial_code: '', serials: [] }
                : l)),
        }));

        if (!itemId || row?.whs_type === 'TOOL') return;   // alat memakai nomor unit

        const { data } = await api.get('/whs-outgoing/serials', { params: { item_id: itemId } });
        setForm((f) => ({
            ...f,
            lines: f.lines.map((l, j) => (j === i ? { ...l, serials: data.data } : l)),
        }));
    };

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/whs-outgoing/${row.id}`);
        const d = data.data;
        setForm({
            date: d.date?.slice(0, 10), dept: d.dept || '', receiver: d.receiver || '',
            cost_center: d.cost_center || '', note: d.note || '',
            lines: (d.detail || []).map((l) => ({
                item_id: l.item_id, row: null, qty: l.qty, unit_cost: l.unit_cost,
                machine_id: l.machine_id || '', cost_center: l.cost_center || '', note: l.note || '',
            })),
        });
        setModal({ mode: 'edit', id: row.id });
    };
    const openDetail = async (row) => {
        const { data } = await api.get(`/whs-outgoing/${row.id}`);
        setDetail(data.data);
    };

    const overStock = form.lines.some((l) => l.row && Number(l.qty) > l.row.qty);
    const toolQty = form.lines.filter((l) => l.row?.whs_type === 'TOOL').reduce((a, l) => a + (Number(l.qty) || 0), 0);

    const columns = [
        { key: 'code', label: 'No. Pengeluaran' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'dept', label: 'Bagian', render: (v) => v || '—' },
        { key: 'receiver', label: 'Penerima', render: (v) => v || '—' },
        { key: 'cost_center', label: 'Cost Center', render: (v) => v || '—' },
        { key: 'detail_count', label: '# Barang' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Pengeluaran WHS</h1>
                    <p className="text-sm text-slate-500">Sparepart &amp; barang habis pakai langsung jadi beban. Alat keluar sebagai pinjaman — tetap milik perusahaan sampai dikembalikan.</p>
                </div>
                {can('whs-outgoing', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Pengeluaran</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100" onClick={() => openDetail(row)}><Icon name="eye" /></button>
                        {row.status === 'DRAFT' && can('whs-outgoing', 'edit') && (<>
                            <button title="Post — stok berkurang" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50"
                                onClick={() => window.confirm('Post pengeluaran ini? Stok berkurang dan alat tercatat dipinjam.') && post.mutate(row.id)}><Icon name="check" /></button>
                            <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>
                        </>)}
                        {row.status === 'DRAFT' && can('whs-outgoing', 'delete') && (
                            <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus pengeluaran?') && remove.mutate(row.id)}><Icon name="trash" /></button>
                        )}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Pengeluaran WHS`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label>
                        <input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div><label className="field-label">Bagian</label>
                        <input className="field-input" value={form.dept} maxLength={50} onChange={(e) => set('dept', e.target.value)} placeholder="Maintenance" /></div>
                    <div><label className="field-label">Penerima</label>
                        <input className="field-input" value={form.receiver} maxLength={60} onChange={(e) => set('receiver', e.target.value)} placeholder="Nama pemegang" />
                        <p className="mt-0.5 text-[11px] text-slate-400">Untuk alat, nama ini yang tercatat sebagai pemegang.</p></div>
                    <div><label className="field-label">Cost Center</label>
                        <input className="field-input" value={form.cost_center} maxLength={40} onChange={(e) => set('cost_center', e.target.value)} /></div>
                    <div className="sm:col-span-4"><label className="field-label">Catatan</label>
                        <input className="field-input" value={form.note} maxLength={300} onChange={(e) => set('note', e.target.value)} /></div>
                </div>

                <LineTable title="Barang keluar" subtitle="Serial batch menentukan asal barang — kode inilah yang nanti dicatat saat mesin berhenti untuk penggantian."
                    onAdd={addLine} lines={form.lines} empty="Belum ada barang."
                    head={['Barang', 'Jenis', 'Serial batch', 'Stok', 'Qty', 'Mesin', 'Cost Center', '']}
                    row={(l, i) => (<>
                        <td className="min-w-[240px] px-2 py-1.5">
                            <Select value={l.item_id} onChange={(v) => pickItem(i, v)} options={stockItems.data}
                                getValue={(o) => o.item_id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— pilih barang —" />
                        </td>
                        <td className="w-28 px-2 py-1.5">{l.row?.whs_type ? <TypeBadge type={l.row.whs_type} /> : '—'}</td>
                        <td className="min-w-[210px] px-2 py-1.5">
                            {l.row?.whs_type === 'TOOL' ? (
                                <span className="text-xs text-slate-500">unit alat dipilih otomatis</span>
                            ) : (
                                <select className="field-input" value={l.serial_code || ''} onChange={(e) => setLine(i, 'serial_code', e.target.value)}>
                                    <option value="">— batch tertua (otomatis) —</option>
                                    {(l.serials || []).map((s) => (
                                        <option key={s.serial_code} value={s.serial_code}>
                                            {s.serial_code} · sisa {s.remaining}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </td>
                        <td className="w-20 px-2 py-1.5 text-right text-sm text-slate-500">{l.row ? money(l.row.qty) : '—'}</td>
                        <td className="w-24 px-2 py-1.5">
                            <CellInput type="number" value={l.qty} onChange={(v) => setLine(i, 'qty', v)}
                                className={l.row && Number(l.qty) > l.row.qty ? 'border-red-400' : ''} />
                        </td>
                        <td className="w-40 px-2 py-1.5">
                            <Select value={l.machine_id} onChange={(v) => setLine(i, 'machine_id', v)} options={machines.data}
                                getValue={(o) => o.id} getLabel={(o) => o.name} placeholder="—" />
                        </td>
                        <td className="w-32 px-2 py-1.5"><CellInput value={l.cost_center} onChange={(v) => setLine(i, 'cost_center', v)} /></td>
                        <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                    </>)} />

                {overStock && (
                    <div className="-mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">
                        Ada baris yang melebihi stok. Dokumen boleh disimpan, tapi akan ditolak saat di-post.
                    </div>
                )}
                {toolQty > 0 && (
                    <p className="-mt-3 text-xs text-violet-700">
                        {toolQty} unit alat akan tercatat dipinjam atas nama “{form.receiver || form.dept || 'belum diisi'}” dan belum dibebankan sebagai biaya.
                    </p>
                )}
            </Modal>

            <Modal open={!!detail} onClose={() => setDetail(null)} wide title={`Pengeluaran ${detail?.code || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setDetail(null)}>Tutup</button>}>
                {detail && (<>
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">Barang</th><th className="px-2 py-2">Jenis</th>
                                <th className="px-2 py-2">Serial batch</th>
                                <th className="px-2 py-2 text-right">Qty</th><th className="px-2 py-2 text-right">Kembali</th>
                                <th className="px-2 py-2 text-right">Harga</th><th className="px-2 py-2">Cost Center</th>
                            </tr></thead>
                            <tbody>
                                {(detail.detail || []).map((l) => (
                                    <tr key={l.id} className="border-t border-slate-100">
                                        <td className="px-2 py-1.5">{l.item?.code} — {l.item?.name}</td>
                                        <td className="px-2 py-1.5">{l.item?.whs_type && <TypeBadge type={l.item.whs_type} />}</td>
                                        <td className="px-2 py-1.5 font-mono text-xs text-slate-600">{l.serial_code || '—'}</td>
                                        <td className="px-2 py-1.5 text-right">{money(l.qty)}</td>
                                        <td className="px-2 py-1.5 text-right">{l.item?.whs_type === 'TOOL' ? money(l.qty_returned) : '—'}</td>
                                        <td className="px-2 py-1.5 text-right">{money(l.unit_cost)}</td>
                                        <td className="px-2 py-1.5 text-slate-500">{l.cost_center || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {(detail.units || []).length > 0 && (
                        <div className="mt-4">
                            <h4 className="mb-2 text-sm font-semibold text-slate-700">Unit alat yang keluar lewat dokumen ini</h4>
                            <div className="flex flex-wrap gap-2">
                                {detail.units.map((u) => (
                                    <span key={u.id} className={`rounded-md px-2 py-1 text-xs ${u.status === 'ON_LOAN' ? 'bg-violet-50 text-violet-700' : 'bg-slate-100 text-slate-600'}`}>
                                        {u.code} · {u.status === 'ON_LOAN' ? `di ${u.holder || '—'}` : u.status}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}
                </>)}
            </Modal>
        </div>
    );
}
