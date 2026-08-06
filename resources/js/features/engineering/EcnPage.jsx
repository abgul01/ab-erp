import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { ItemSelect, StatusBadge, LineTable, CellInput, Select } from '../procurement/common';

/*
 * Which master each kind of notice writes to, and the columns it may write.
 * The server keeps the same whitelist and refuses anything outside it — this
 * copy only spares the engineer from typing a column name that will be
 * rejected.
 */
const SCOPE = {
    ITEM: {
        table: 'm_item',
        label: 'Item Master',
        fields: ['part_name', 'descrip', 'o_d', 'i_d', 'thick', 'width', 'height', 'length', 'length_cut', 'weight', 'tolerance', 'min_stock', 'max_stock', 'moq', 'order_lot', 'lead_time_days', 'active'],
    },
    BOM: {
        table: 'm_bom_det_rm',
        label: 'Baris BOM (RM)',
        fields: ['mat_id', 'length_cut', 'length_use', 'priority'],
    },
    ROUTING: {
        table: 'm_process_main_det',
        label: 'Langkah Routing',
        fields: ['proc_id', 'sequence'],
    },
};

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY = { date: today(), change_type: 'ITEM', item_id: '', reason: '', impact: '', effective_date: today(), lines: [] };

export default function EcnPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');
    const [detail, setDetail] = useState(null);

    const list = useQuery({
        queryKey: ['ecn', { page }],
        queryFn: async () => (await api.get('/ecn', { params: { page, per_page: 15 } })).data,
    });

    // What the change would touch: the products that use this part, the work
    // orders already running against it, and the rows a line can point at.
    const impact = useQuery({
        queryKey: ['ecn-impact', form.item_id],
        queryFn: async () => (await api.get('/ecn/impact', { params: { item_id: form.item_id } })).data.data,
        enabled: !!form.item_id && !!modal,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['ecn'] });

    const save = useMutation({
        mutationFn: async (payload) => (modal.mode === 'edit' ? api.put(`/ecn/${modal.id}`, payload) : api.post('/ecn', payload)),
        onSuccess: () => { invalidate(); setModal(null); },
        onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({
        mutationFn: async ({ id, action, body }) => api.post(`/ecn/${id}/${action}`, body || {}),
        onSuccess: (res) => { invalidate(); if (detail) setDetail(res.data.data); },
        onError: (e) => alert(apiError(e)),
    });
    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/ecn/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const setLine = (i, k, v) => set('lines', form.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const delLine = (i) => set('lines', form.lines.filter((_, j) => j !== i));
    const addLine = () => set('lines', [...form.lines, {
        action: 'UPDATE',
        target_table: SCOPE[form.change_type].table,
        target_id: form.change_type === 'ITEM' ? form.item_id : '',
        field: '', new_value: '', note: '',
    }]);

    // Changing the kind of notice changes which table the lines address, so the
    // old lines cannot survive it.
    const setType = (t) => setForm((f) => ({ ...f, change_type: t, lines: [] }));

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = async (row) => {
        setError('');
        const { data } = await api.get(`/ecn/${row.id}`);
        const d = data.data;
        setForm({
            date: d.date?.slice(0, 10), change_type: d.change_type, item_id: d.item_id,
            reason: d.reason, impact: d.impact || '', effective_date: d.effective_date?.slice(0, 10),
            lines: (d.detail || []).map((l) => ({
                action: l.action, target_table: l.target_table, target_id: l.target_id || '',
                field: l.field || '', new_value: l.new_value || '', note: l.note || '',
            })),
        });
        setModal({ mode: 'edit', id: row.id });
    };
    const openDetail = async (row) => {
        const { data } = await api.get(`/ecn/${row.id}`);
        setDetail(data.data);
    };

    // The row a BOM/routing line points at, offered by name rather than by id.
    const targetOptions = () => (form.change_type === 'BOM' ? (impact.data?.bom_lines || []) : (impact.data?.routing_steps || []));
    const targetLabel = (o) => (form.change_type === 'BOM'
        ? `${o.mat_code} — ${o.part_name} (pakai ${o.length_use} mm)`
        : `#${o.sequence} ${o.proc_name}`);

    const columns = [
        { key: 'code', label: 'No. ECN' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'change_type', label: 'Jenis' },
        { key: 'item', label: 'Item', render: (v) => (v ? `${v.code} — ${v.part_name}` : '—') },
        { key: 'effective_date', label: 'Berlaku', render: (v) => v?.slice(0, 10) },
        { key: 'detail_count', label: '# Ubahan' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">ECN — Perubahan Teknik</h1>
                    <p className="text-sm text-slate-500">Perubahan item, BOM, atau routing disetujui dulu, baru diterapkan pada tanggal berlakunya.</p>
                </div>
                {can('ecn', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah ECN</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100" onClick={() => openDetail(row)}><Icon name="eye" /></button>
                        {row.status === 'DRAFT' && can('ecn', 'edit') && (
                            <button title="Submit" className="rounded p-1.5 text-amber-600 hover:bg-amber-50" onClick={() => act.mutate({ id: row.id, action: 'submit' })}><Icon name="send" /></button>
                        )}
                        {row.status === 'SUBMITTED' && can('ecn', 'edit') && (<>
                            <button title="Approve" className="rounded p-1.5 text-emerald-600 hover:bg-emerald-50" onClick={() => act.mutate({ id: row.id, action: 'approve' })}><Icon name="check" /></button>
                            <button title="Reject" className="rounded p-1.5 text-red-600 hover:bg-red-50" onClick={() => {
                                const note = window.prompt('Alasan penolakan:');
                                if (note) act.mutate({ id: row.id, action: 'reject', body: { note } });
                            }}><Icon name="ban" /></button>
                        </>)}
                        {row.status === 'APPROVED' && can('ecn', 'edit') && (
                            <button title="Terapkan ke master" className="rounded p-1.5 text-blue-600 hover:bg-blue-50"
                                onClick={() => window.confirm('Terapkan perubahan ini ke data master?') && act.mutate({ id: row.id, action: 'apply' })}><Icon name="check-circle" /></button>
                        )}
                        {row.status === 'DRAFT' && can('ecn', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {row.status === 'DRAFT' && can('ecn', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus ECN?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            {/* ── Form ── */}
            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} ECN`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>
                        {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label>
                        <input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div><label className="field-label">Jenis Perubahan <span className="text-red-500">*</span></label>
                        <select className="field-input" value={form.change_type} onChange={(e) => setType(e.target.value)}>
                            {Object.keys(SCOPE).map((t) => <option key={t} value={t}>{t} — {SCOPE[t].label}</option>)}
                        </select>
                    </div>
                    <div><label className="field-label">Berlaku Mulai <span className="text-red-500">*</span></label>
                        <input type="date" className="field-input" value={form.effective_date} onChange={(e) => set('effective_date', e.target.value)} />
                        <p className="mt-0.5 text-[11px] text-slate-400">Barang yang dibuat sebelum tanggal ini tetap revisi lama.</p>
                    </div>
                    <div className="sm:col-span-3"><label className="field-label">Item <span className="text-red-500">*</span></label>
                        <ItemSelect value={form.item_id} onChange={(v) => setForm((f) => ({ ...f, item_id: v, lines: [] }))} /></div>
                    <div className="sm:col-span-3"><label className="field-label">Alasan <span className="text-red-500">*</span></label>
                        <input className="field-input" value={form.reason} maxLength={400} onChange={(e) => set('reason', e.target.value)} placeholder="Mengapa perubahan ini perlu" /></div>
                    <div className="sm:col-span-3"><label className="field-label">Dampak</label>
                        <input className="field-input" value={form.impact} maxLength={400} onChange={(e) => set('impact', e.target.value)} placeholder="Pengaruh ke produksi, stok, atau pelanggan" /></div>
                </div>

                {impact.data && (
                    <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <p className="font-semibold">Dampak perubahan ini</p>
                        <p>{impact.data.where_used.length} BOM memakai item ini · {impact.data.open_wo_count} Work Order masih berjalan · {impact.data.routings.length} routing terdaftar</p>
                        {impact.data.open_wo_count > 0 && (
                            <p className="mt-1">WO berjalan: {impact.data.open_wo.map((w) => w.code).join(', ')} — pastikan PPIC tahu sebelum tanggal berlaku.</p>
                        )}
                    </div>
                )}

                <LineTable title="Perubahan" subtitle={`Menulis ke ${SCOPE[form.change_type].table}. Nilai lama direkam otomatis saat ECN disubmit.`}
                    onAdd={form.item_id ? addLine : undefined} lines={form.lines} empty={form.item_id ? 'Belum ada baris perubahan.' : 'Pilih item dulu.'}
                    head={['Aksi', 'Baris yang diubah', 'Kolom', 'Nilai baru', 'Catatan', '']}
                    row={(l, i) => (<>
                        <td className="w-28 px-2 py-1.5">
                            <select className="field-input" value={l.action} onChange={(e) => setLine(i, 'action', e.target.value)}>
                                <option value="UPDATE">UPDATE</option>
                                {form.change_type !== 'ITEM' && <option value="ADD">ADD</option>}
                                {form.change_type !== 'ITEM' && <option value="REMOVE">REMOVE</option>}
                            </select>
                        </td>
                        <td className="min-w-[220px] px-2 py-1.5">
                            {form.change_type === 'ITEM'
                                ? <span className="text-xs text-slate-500">Item yang dipilih di atas</span>
                                : (l.action === 'ADD'
                                    ? <span className="text-xs text-slate-500">Baris baru</span>
                                    : <Select value={l.target_id} onChange={(v) => setLine(i, 'target_id', v)} options={targetOptions()}
                                        getValue={(o) => o.id} getLabel={targetLabel} placeholder="— pilih baris —" />)}
                        </td>
                        <td className="w-44 px-2 py-1.5">
                            {l.action === 'REMOVE'
                                ? <span className="text-xs text-slate-500">seluruh baris</span>
                                : <select className="field-input" value={l.field} onChange={(e) => setLine(i, 'field', e.target.value)}>
                                    <option value="">— pilih kolom —</option>
                                    {SCOPE[form.change_type].fields.map((f) => <option key={f} value={f}>{f}</option>)}
                                </select>}
                        </td>
                        <td className="px-2 py-1.5">
                            {l.action === 'REMOVE'
                                ? <span className="text-xs text-slate-500">—</span>
                                : <CellInput value={l.new_value} onChange={(v) => setLine(i, 'new_value', v)} />}
                        </td>
                        <td className="px-2 py-1.5"><CellInput value={l.note} onChange={(v) => setLine(i, 'note', v)} /></td>
                        <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine(i)}><Icon name="trash" /></button></td>
                    </>)} />
                {form.change_type !== 'ITEM' && form.lines.some((l) => l.action === 'ADD') && (
                    <p className="-mt-3 text-xs text-slate-500">Baris ADD: isi kolom “Nilai baru” dengan JSON, contoh <code>{'{"mat_id":12,"length_use":600,"priority":1}'}</code>.</p>
                )}
            </Modal>

            {/* ── Detail ── */}
            <Modal open={!!detail} onClose={() => setDetail(null)} wide title={`ECN ${detail?.code || ''}`}
                footer={<button className="btn btn-ghost" onClick={() => setDetail(null)}>Tutup</button>}>
                {detail && (<>
                    <div className="mb-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                        <div><p className="text-xs text-slate-400">Jenis</p><p>{detail.change_type}</p></div>
                        <div><p className="text-xs text-slate-400">Item</p><p>{detail.item?.code} — {detail.item?.part_name}</p></div>
                        <div><p className="text-xs text-slate-400">Berlaku mulai</p><p>{detail.effective_date?.slice(0, 10)}</p></div>
                        <div><p className="text-xs text-slate-400">Status</p><StatusBadge status={detail.status} /></div>
                        <div className="col-span-2 sm:col-span-4"><p className="text-xs text-slate-400">Alasan</p><p>{detail.reason}</p></div>
                        {detail.impact && <div className="col-span-2 sm:col-span-4"><p className="text-xs text-slate-400">Dampak</p><p>{detail.impact}</p></div>}
                        {detail.applied_at && <div className="col-span-2 sm:col-span-4 text-xs text-emerald-700">Diterapkan ke master pada {String(detail.applied_at).slice(0, 16).replace('T', ' ')}.</div>}
                    </div>
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-2 py-2">Aksi</th><th className="px-2 py-2">Target</th><th className="px-2 py-2">Kolom</th>
                                <th className="px-2 py-2">Sebelum</th><th className="px-2 py-2">Sesudah</th><th className="px-2 py-2">Catatan</th>
                            </tr></thead>
                            <tbody>
                                {(detail.detail || []).map((l) => (
                                    <tr key={l.id} className="border-t border-slate-100">
                                        <td className="px-2 py-1.5">{l.action}</td>
                                        <td className="px-2 py-1.5 text-slate-500">{l.target_table}{l.target_id ? `#${l.target_id}` : ''}</td>
                                        <td className="px-2 py-1.5">{l.field || '—'}</td>
                                        <td className="px-2 py-1.5 text-slate-500">{l.old_value ?? '—'}</td>
                                        <td className="px-2 py-1.5 font-medium text-slate-800">{l.new_value ?? '—'}</td>
                                        <td className="px-2 py-1.5 text-slate-500">{l.note || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>)}
            </Modal>
        </div>
    );
}
