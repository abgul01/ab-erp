import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { money } from '../procurement/common';

const STATUSES = [['PENDING', 'Menunggu keputusan'], ['DECIDED', 'Sudah diputuskan'], ['ALL', 'Semua']];

/**
 * Abnormal decision desk. The floor records the finding (serial/pallet + qty);
 * here it is judged into REPAIRED or NG. Only the NG side counts as scrap and
 * shows up in the KPL "Qty (NG)" column. A finding may be split and decided in
 * stages — it stays pending until the whole quantity has been judged.
 */
export default function AbnormalPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [status, setStatus] = useState('PENDING');
    const [form, setForm] = useState(null);

    const list = useQuery({
        queryKey: ['abnormal', status],
        queryFn: async () => (await api.get('/mes/abnormal', { params: { status } })).data.data,
    });
    const decide = useMutation({
        mutationFn: async (p) => api.post('/mes/abnormal/decide', p),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['abnormal'] }); setForm(null); },
        onError: (e) => alert(apiError(e)),
    });

    const cell = 'border border-slate-300 px-2 py-1.5';
    const rows = list.data || [];

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold text-slate-800">Keputusan Abnormal (Repair / NG)</h1>
                <select className="field-input w-56" value={status} onChange={(e) => setStatus(e.target.value)}>
                    {STATUSES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
            </div>
            <p className="mb-3 text-xs text-slate-400">Temuan abnormal dari lantai produksi belum menentukan nasib barang. Di sini diputuskan berapa yang bisa <b>di-repair</b> dan berapa yang <b>NG (scrap)</b>. Boleh dipecah dan diputuskan bertahap; hanya NG yang masuk kolom Qty (NG) di KPL.</p>

            <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                        <th className={cell}>Asal</th><th className={cell}>WIP</th><th className={cell}>Item</th>
                        <th className={cell}>Proses</th><th className={cell}>Mesin</th>
                        <th className={cell}>Serial / Pallet</th><th className={cell}>Tanggal</th><th className={cell}>Catatan</th>
                        <th className={cell}>Qty Abnormal</th><th className={cell}>Repair</th><th className={cell}>NG</th>
                        <th className={cell}>Sisa</th><th className={cell}>Aksi</th>
                    </tr></thead>
                    <tbody>
                        {list.isLoading && <tr><td className={cell} colSpan={13}>Memuat…</td></tr>}
                        {!list.isLoading && rows.length === 0 && <tr><td className={cell} colSpan={13}>Tidak ada temuan berstatus {status}.</td></tr>}
                        {rows.map((r, i) => (
                            <tr key={`${r.type}-${r.main_id}-${r.ref}-${i}`} className="hover:bg-slate-50">
                                <td className={cell}>
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${r.type === 'CUT' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700'}`}>
                                        {r.type === 'CUT' ? 'Cutting' : 'Processing'}
                                    </span>
                                </td>
                                <td className={cell}>{r.wip_code}</td>
                                <td className={cell}>{r.item_code}</td>
                                <td className={cell}>{r.process}</td>
                                <td className={cell}>{r.machine}</td>
                                <td className={cell}><span className="text-[11px] text-slate-400">{r.ref_label}</span><div className="font-medium text-slate-700">{r.ref}</div></td>
                                <td className={cell}>{(r.date || '').slice(0, 10)}</td>
                                <td className={cell}>{r.note || <span className="text-slate-300">—</span>}</td>
                                <td className={`${cell} font-semibold`}>{money(r.qty)}</td>
                                <td className={`${cell} text-emerald-700`}>{r.repair ? money(r.repair) : '—'}</td>
                                <td className={`${cell} text-red-600`}>{r.ng ? money(r.ng) : '—'}</td>
                                <td className={`${cell} ${r.remaining > 0 ? 'font-semibold text-amber-700' : 'text-slate-400'}`}>{money(r.remaining)}</td>
                                <td className={cell}>
                                    {r.remaining > 0 && can('mes-abnormal', 'edit') ? (
                                        <button className="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                                            onClick={() => setForm({ ...r, qty_repair: 0, qty_ng: r.remaining })}>
                                            <Icon name="check" className="h-3.5 w-3.5" /> Putuskan
                                        </button>
                                    ) : <span className="text-xs text-slate-400">selesai</span>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* ---------- Decision ---------- */}
            <Modal open={!!form} onClose={() => setForm(null)} title="Putuskan Temuan Abnormal"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setForm(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={decide.isPending}
                        onClick={() => decide.mutate({
                            type: form.type, main_id: form.main_id, serial_id: form.serial_id,
                            qty_ng: Number(form.qty_ng || 0), qty_repair: Number(form.qty_repair || 0),
                        })}>
                        {decide.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan Keputusan
                    </button>
                </>}>
                {form && (
                    <div className="space-y-3">
                        <div className="rounded-md bg-slate-50 p-3 text-sm">
                            <div><span className="text-slate-400">{form.ref_label}</span> <b>{form.ref}</b> · {form.item_code}</div>
                            <div className="text-slate-500">{form.type === 'CUT' ? 'Cutting' : 'Processing'} · {form.process} · mesin {form.machine} · WIP {form.wip_code}</div>
                            {form.note && <div className="mt-1 text-slate-600">Catatan: {form.note}</div>}
                            <div className="mt-1">Qty abnormal <b>{money(form.qty)}</b> · sudah diputuskan {money(form.ng + form.repair)} · <b className="text-amber-700">sisa {money(form.remaining)}</b></div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="field-label">Repair (bisa diperbaiki)</label>
                                <input type="number" min="0" max={form.remaining} className="field-input" value={form.qty_repair}
                                    onChange={(e) => {
                                        const v = Math.max(0, Math.min(form.remaining, Number(e.target.value || 0)));
                                        setForm({ ...form, qty_repair: v, qty_ng: Math.max(0, form.remaining - v) });
                                    }} /></div>
                            <div><label className="field-label">NG (scrap)</label>
                                <input type="number" min="0" max={form.remaining} className="field-input" value={form.qty_ng}
                                    onChange={(e) => {
                                        const v = Math.max(0, Math.min(form.remaining, Number(e.target.value || 0)));
                                        setForm({ ...form, qty_ng: v, qty_repair: Math.max(0, form.remaining - v) });
                                    }} /></div>
                        </div>
                        {Number(form.qty_ng) + Number(form.qty_repair) > form.remaining && (
                            <div className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">Total melebihi sisa ({money(form.remaining)}).</div>
                        )}
                        <p className="text-xs text-slate-400">Boleh diputuskan sebagian — sisanya tetap muncul di daftar sampai habis.</p>
                    </div>
                )}
            </Modal>
        </div>
    );
}
