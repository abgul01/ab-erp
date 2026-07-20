import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, useOptions, CellInput, money } from '../procurement/common';

const now = new Date();
const thisMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
const DOW = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

export default function MpsPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [month, setMonth] = useState(thisMonth); // YYYY-MM
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(null);
    const [drag, setDrag] = useState(null);
    const [error, setError] = useState('');

    const machines = useOptions('machines');
    const approvedMpp = useQuery({ queryKey: ['mpp', 'approved'], queryFn: async () => (await api.get('/mpp', { params: { status: 'APPROVED', per_page: 300 } })).data.data, staleTime: 20_000 });
    const list = useQuery({ queryKey: ['mps', 'all'], queryFn: async () => (await api.get('/mps', { params: { per_page: 500 } })).data.data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['mps'] });

    const [y, mo] = month.split('-').map(Number);
    const daysInMonth = new Date(y, mo, 0).getDate();
    const days = Array.from({ length: daysInMonth }, (_, i) => i + 1);
    const dateOf = (d) => `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

    const inMonth = (list.data || []).filter((m) => (m.plan_date || '').slice(0, 7) === month);
    const rows = {};
    inMonth.forEach((m) => { rows[m.item_id] ||= { item: m.item, item_id: m.item_id, byDay: {} }; (rows[m.item_id].byDay[+m.plan_date.slice(8, 10)] ||= []).push(m); });
    const rowList = Object.values(rows).sort((a, b) => (a.item?.code || '').localeCompare(b.item?.code || ''));

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/mps/${modal.id}`, p) : api.post('/mps', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const act = useMutation({ mutationFn: async (id) => api.post(`/mps/${id}/approve`), onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => alert(apiError(e)) });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/mps/${id}`), onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => alert(apiError(e)) });
    const reschedule = useMutation({
        mutationFn: async ({ m, date }) => api.put(`/mps/${m.id}`, { plan_date: date, item_id: m.item_id, qty: m.qty, machine_id: m.machine_id || null }),
        onSuccess: invalidate, onError: (e) => alert(apiError(e)),
    });

    const openCreate = (date = '') => { setForm({ mpp_id: '', plan_date: date, item_id: '', item_label: '', qty: '', machine_id: '' }); setError(''); setModal({ mode: 'create' }); };
    const openEdit = (m) => { setForm({ mpp_id: '', plan_date: m.plan_date?.slice(0, 10), item_id: m.item_id, item_label: m.item ? `${m.item.code} — ${m.item.part_name}` : '', qty: m.qty, machine_id: m.machine_id || '', status: m.status }); setError(''); setModal({ mode: 'edit', id: m.id, status: m.status }); };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const onSelectMpp = (mppId) => {
        const m = (approvedMpp.data || []).find((x) => x.id === Number(mppId));
        if (!m) { set('mpp_id', ''); return; }
        setForm((f) => ({ ...f, mpp_id: mppId, item_id: m.item_id, item_label: m.item ? `${m.item.code} — ${m.item.part_name}` : '', plan_date: f.plan_date || `${m.period.slice(0, 4)}-${m.period.slice(4, 6)}-01`, qty: f.qty || m.plan_qty }));
    };
    const submit = () => { setError(''); const { mpp_id, item_label, status, ...payload } = form; save.mutate(payload); };

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold text-slate-800">MPS — Jadwal Produksi (Gantt)</h1>
                <div className="flex flex-wrap items-center gap-2">
                    <input type="month" className="field-input w-40" value={month} onChange={(e) => setMonth(e.target.value)} />
                    {can('mps', 'create') && <button className="btn btn-primary" onClick={() => openCreate()}><Icon name="plus" /> Tambah MPS</button>}
                </div>
            </div>
            <p className="mb-3 text-xs text-slate-400">Klik bar untuk lihat/edit. Bar DRAFT bisa di-<b>drag</b> ke tanggal lain untuk reschedule. Klik sel kosong untuk tambah.</p>

            <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                <table className="border-collapse text-sm" style={{ minWidth: 220 + daysInMonth * 42 }}>
                    <thead>
                        <tr>
                            <th className="sticky left-0 z-10 w-56 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left text-xs font-semibold text-slate-500">Item FG</th>
                            {days.map((d) => {
                                const dow = new Date(y, mo - 1, d).getDay();
                                return <th key={d} className={`w-[42px] border-b border-slate-100 px-1 py-1 text-center text-[11px] ${dow === 0 || dow === 6 ? 'bg-slate-50 text-slate-400' : 'text-slate-500'}`}><div>{d}</div><div className="text-[9px] text-slate-400">{DOW[dow]}</div></th>;
                            })}
                        </tr>
                    </thead>
                    <tbody>
                        {list.isLoading && <tr><td colSpan={daysInMonth + 1} className="px-4 py-6 text-center text-slate-400">Memuat…</td></tr>}
                        {!list.isLoading && rowList.length === 0 && <tr><td colSpan={daysInMonth + 1} className="px-4 py-6 text-center text-slate-400">Belum ada MPS di bulan ini. Klik "Tambah MPS".</td></tr>}
                        {rowList.map((r) => (
                            <tr key={r.item_id} className="border-t border-slate-100">
                                <td className="sticky left-0 z-10 border-r border-slate-200 bg-white px-3 py-2">
                                    <div className="font-medium text-slate-700">{r.item?.code}</div>
                                    <div className="truncate text-xs text-slate-400">{r.item?.part_name}</div>
                                </td>
                                {days.map((d) => {
                                    const cell = r.byDay[d] || [];
                                    const dow = new Date(y, mo - 1, d).getDay();
                                    return (
                                        <td key={d}
                                            className={`h-12 border-l border-slate-50 p-0.5 align-top ${dow === 0 || dow === 6 ? 'bg-slate-50/50' : ''}`}
                                            onDragOver={(e) => { if (drag) e.preventDefault(); }}
                                            onDrop={() => { if (drag && drag.status === 'DRAFT') reschedule.mutate({ m: drag, date: dateOf(d) }); setDrag(null); }}
                                            onClick={() => cell.length === 0 && can('mps', 'create') && openCreate(dateOf(d))}
                                        >
                                            {cell.map((m) => (
                                                <div key={m.id}
                                                    draggable={m.status === 'DRAFT'}
                                                    onDragStart={() => setDrag(m)}
                                                    onClick={(e) => { e.stopPropagation(); openEdit(m); }}
                                                    title={`${m.item?.code} · ${m.plan_date?.slice(0, 10)} · qty ${m.qty} · ${m.status}${m.machine ? ' · ' + (m.machine.code || m.machine.name) : ''}`}
                                                    className={`mb-0.5 cursor-pointer rounded px-1 py-0.5 text-center text-[11px] font-semibold leading-tight text-white shadow-sm ${m.status === 'APPROVED' ? 'bg-emerald-500' : 'bg-amber-500'} ${m.status === 'DRAFT' ? 'hover:brightness-110' : ''}`}>
                                                    {money(m.qty)}
                                                </div>
                                            ))}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="mt-2 flex gap-4 text-xs text-slate-500">
                <span className="flex items-center gap-1"><span className="inline-block h-3 w-3 rounded bg-amber-500" /> Draft (bisa drag/edit)</span>
                <span className="flex items-center gap-1"><span className="inline-block h-3 w-3 rounded bg-emerald-500" /> Approved (terkunci)</span>
            </div>

            {/* Create / edit */}
            <Modal open={!!modal} onClose={() => setModal(null)} wide title={`${modal?.mode === 'edit' ? 'MPS' : 'Tambah MPS'}`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Tutup</button>
                    {modal?.mode === 'edit' && modal.status === 'DRAFT' && can('mps', 'delete') && <button className="btn btn-ghost text-red-600" onClick={() => window.confirm('Hapus MPS?') && remove.mutate(modal.id)}><Icon name="trash" /> Hapus</button>}
                    {modal?.mode === 'edit' && modal.status === 'DRAFT' && can('mps', 'edit') && <button className="btn btn-ghost text-emerald-700" onClick={() => act.mutate(modal.id)}><Icon name="check" /> Approve</button>}
                    {(modal?.mode === 'create' || modal?.status === 'DRAFT') && can('mps', 'edit') && (
                        <button className="btn btn-primary" onClick={submit} disabled={save.isPending}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                    )}
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {modal && form && (<>
                    {modal.mode === 'create' && (
                        <div className="mb-4">
                            <label className="field-label">Dari MPP (approved) <span className="text-red-500">*</span></label>
                            <Select value={form.mpp_id} onChange={onSelectMpp} options={approvedMpp.data} getValue={(o) => o.id} getLabel={(o) => `${o.period.slice(0, 4)}-${o.period.slice(4, 6)} · ${o.item?.code} (rencana ${o.plan_qty})`} placeholder="— pilih MPP —" />
                            <p className="mt-1 text-xs text-slate-400">Total MPS per (item, bulan) ≤ rencana MPP.</p>
                        </div>
                    )}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2"><label className="field-label">Item FG</label><input className="field-input bg-slate-50" value={form.item_label} disabled placeholder="— dari MPP —" /></div>
                        <div><label className="field-label">Tanggal <span className="text-red-500">*</span></label><input type="date" className="field-input" value={form.plan_date} onChange={(e) => set('plan_date', e.target.value)} /></div>
                        <div><label className="field-label">Qty <span className="text-red-500">*</span></label><CellInput type="number" value={form.qty} onChange={(v) => set('qty', v)} /></div>
                        <div className="sm:col-span-2"><label className="field-label">Mesin (opsional)</label>
                            <Select value={form.machine_id} onChange={(v) => set('machine_id', v)} options={machines.data} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— pilih mesin —" /></div>
                    </div>
                </>)}
            </Modal>
        </div>
    );
}
