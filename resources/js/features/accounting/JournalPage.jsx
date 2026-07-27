import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, useOptions, money } from '../procurement/common';

const ym = () => new Date().toISOString().slice(0, 7).replace('-', '');
const today = () => new Date().toISOString().slice(0, 10);
const emptyLine = () => ({ coa_id: '', debit: '', credit: '', memo: '' });

/** Journals + general ledger: list, trial balance, manual entry, GL generation. */
export default function JournalPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [tab, setTab] = useState('journals');   // 'journals' | 'trial'
    const [period, setPeriod] = useState(ym());
    const [view, setView] = useState(null);
    const [manual, setManual] = useState(null);   // { date, descrip, lines }
    const [error, setError] = useState('');

    const coa = useOptions('coa');
    const list = useQuery({ queryKey: ['journals', period], queryFn: async () => (await api.get('/journals', { params: { period, per_page: 100 } })).data, enabled: tab === 'journals' });
    const tb = useQuery({ queryKey: ['journals', 'trial', period], queryFn: async () => (await api.get('/journals/trial-balance', { params: { period } })).data.data, enabled: tab === 'trial' });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['journals'] });

    const generate = useMutation({
        mutationFn: async () => (await api.post('/journals/generate', { period })).data.data,
        onSuccess: (d) => { invalidate(); alert(`Generate GL ${d.period}: sales-inv ${d.sales_inv}, ap-inv ${d.ap_inv}, depresiasi ${d.depreciation}.`); }, onError: (e) => alert(apiError(e)),
    });
    const saveManual = useMutation({
        mutationFn: async () => api.post('/journals', { date: manual.date, descrip: manual.descrip, lines: manual.lines.filter((l) => l.coa_id && (Number(l.debit) > 0 || Number(l.credit) > 0)).map((l) => ({ coa_id: l.coa_id, debit: Number(l.debit) || 0, credit: Number(l.credit) || 0, memo: l.memo })) }),
        onSuccess: () => { invalidate(); setManual(null); }, onError: (e) => setError(apiError(e)),
    });
    const reverse = useMutation({ mutationFn: async (id) => api.post(`/journals/${id}/reverse`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });
    const openView = async (row) => { const { data } = await api.get(`/journals/${row.id}`); setView(data.data); };

    const openManual = () => { setError(''); setManual({ date: today(), descrip: '', lines: [emptyLine(), emptyLine()] }); };
    const setLine = (i, k, v) => setManual((m) => ({ ...m, lines: m.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)) }));
    const mDebit = manual?.lines.reduce((a, l) => a + (Number(l.debit) || 0), 0) || 0;
    const mCredit = manual?.lines.reduce((a, l) => a + (Number(l.credit) || 0), 0) || 0;
    const balanced = manual && Math.abs(mDebit - mCredit) < 0.005 && mDebit > 0;

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Jurnal & Buku Besar</h1>
                    <p className="text-xs text-slate-400">Jurnal double-entry, buku besar, dan neraca saldo. Generate GL memposting jurnal dari dokumen (invoice, depresiasi).</p>
                </div>
                <div className="flex items-end gap-2">
                    <div><label className="field-label">Periode</label><input className="field-input w-28" maxLength={6} value={period} onChange={(e) => setPeriod(e.target.value)} /></div>
                    {can('journals', 'create') && <button className="btn btn-ghost" onClick={() => generate.mutate()} disabled={generate.isPending}>{generate.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="database" />} Generate GL</button>}
                    {can('journals', 'create') && <button className="btn btn-primary" onClick={openManual}><Icon name="plus" /> Jurnal Manual</button>}
                </div>
            </div>

            <div className="mb-4 flex gap-1 border-b border-slate-200">
                {[['journals', 'Buku Besar'], ['trial', 'Neraca Saldo']].map(([k, label]) => (
                    <button key={k} className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium ${tab === k ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`} onClick={() => setTab(k)}>{label}</button>
                ))}
            </div>

            {tab === 'journals' && (
                <div className="card overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-3 py-2">No. Jurnal</th><th className="px-3 py-2">Tanggal</th><th className="px-3 py-2">Tipe</th><th className="px-3 py-2">Referensi</th>
                                <th className="px-3 py-2">Keterangan</th><th className="px-3 py-2 text-right">Nilai</th><th className="px-3 py-2">Status</th><th className="px-3 py-2"></th>
                            </tr></thead>
                            <tbody>
                                {list.isLoading && <tr><td colSpan={8} className="px-3 py-6 text-center text-slate-400">Memuat…</td></tr>}
                                {!list.isLoading && (list.data?.data || []).length === 0 && <tr><td colSpan={8} className="px-3 py-6 text-center text-slate-400">Belum ada jurnal untuk periode ini. Klik <b>Generate GL</b>.</td></tr>}
                                {(list.data?.data || []).map((j) => (
                                    <tr key={j.id} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5 font-medium">{j.code}</td>
                                        <td className="px-3 py-1.5">{j.date?.slice(0, 10)}</td>
                                        <td className="px-3 py-1.5">{j.jrn_type}</td>
                                        <td className="px-3 py-1.5 text-slate-500">{j.ref_type}{j.ref_id ? ` #${j.ref_id}` : ''}</td>
                                        <td className="px-3 py-1.5">{j.descrip}</td>
                                        <td className="px-3 py-1.5 text-right">{money(j.debit_total)}</td>
                                        <td className="px-3 py-1.5"><span className={`rounded px-1.5 py-0.5 text-xs ${j.status === 'POSTED' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>{j.status}</span></td>
                                        <td className="px-3 py-1.5">
                                            <div className="flex justify-end gap-1">
                                                <button title="Lihat" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openView(j)}><Icon name="search" /></button>
                                                {j.status === 'POSTED' && can('journals', 'edit') && <button title="Balik jurnal" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Balik (reverse) jurnal ini?') && reverse.mutate(j.id)}><Icon name="undo" /></button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {tab === 'trial' && (
                <div className="card overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <th className="px-3 py-2">Kode</th><th className="px-3 py-2">Akun</th><th className="px-3 py-2">Golongan</th>
                                <th className="px-3 py-2 text-right">Saldo Debit</th><th className="px-3 py-2 text-right">Saldo Kredit</th>
                            </tr></thead>
                            <tbody>
                                {tb.isLoading && <tr><td colSpan={5} className="px-3 py-6 text-center text-slate-400">Memuat…</td></tr>}
                                {!tb.isLoading && (tb.data?.rows || []).length === 0 && <tr><td colSpan={5} className="px-3 py-6 text-center text-slate-400">Belum ada saldo. Generate GL dulu.</td></tr>}
                                {(tb.data?.rows || []).map((r) => (
                                    <tr key={r.code} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5 font-medium">{r.code}</td>
                                        <td className="px-3 py-1.5">{r.name}</td>
                                        <td className="px-3 py-1.5 text-slate-500">{r.acc_group}</td>
                                        <td className="px-3 py-1.5 text-right">{r.balance_debit ? money(r.balance_debit) : '—'}</td>
                                        <td className="px-3 py-1.5 text-right">{r.balance_credit ? money(r.balance_credit) : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                            {tb.data && (
                                <tfoot><tr className="border-t-2 border-slate-200 bg-slate-50 font-semibold text-slate-700">
                                    <td className="px-3 py-2" colSpan={3}>Total ({tb.data.total_debit === tb.data.total_credit ? 'seimbang ✓' : 'TIDAK SEIMBANG'})</td>
                                    <td className="px-3 py-2 text-right">{money(tb.data.total_debit)}</td>
                                    <td className="px-3 py-2 text-right">{money(tb.data.total_credit)}</td>
                                </tr></tfoot>
                            )}
                        </table>
                    </div>
                </div>
            )}

            {/* View journal */}
            <Modal open={!!view} onClose={() => setView(null)} size="max-w-2xl" title={`Jurnal ${view?.code || ''}`}>
                <div className="mb-2 text-sm text-slate-500">{view?.date?.slice(0, 10)} · {view?.descrip}</div>
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Akun</th><th className="px-2 py-2 text-right">Debit</th><th className="px-2 py-2 text-right">Kredit</th></tr></thead>
                    <tbody>
                        {(view?.detail || []).map((d) => (
                            <tr key={d.id} className="border-t border-slate-100"><td className="px-2 py-1.5">{d.coa?.code} — {d.coa?.name} {d.memo && <span className="text-xs text-slate-400">({d.memo})</span>}</td><td className="px-2 py-1.5 text-right">{Number(d.debit) ? money(d.debit) : ''}</td><td className="px-2 py-1.5 text-right">{Number(d.credit) ? money(d.credit) : ''}</td></tr>
                        ))}
                    </tbody>
                </table>
            </Modal>

            {/* Manual entry */}
            <Modal open={!!manual} onClose={() => setManual(null)} size="max-w-3xl" title="Jurnal Manual"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setManual(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); saveManual.mutate(); }} disabled={saveManual.isPending || !balanced}>{saveManual.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {manual && (<>
                    <div className="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div><label className="field-label">Tanggal</label><input type="date" className="field-input" value={manual.date} onChange={(e) => setManual((m) => ({ ...m, date: e.target.value }))} /></div>
                        <div className="sm:col-span-2"><label className="field-label">Keterangan</label><input className="field-input" maxLength={300} value={manual.descrip} onChange={(e) => setManual((m) => ({ ...m, descrip: e.target.value }))} /></div>
                    </div>
                    <div className="overflow-x-auto rounded-md border border-slate-200">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500"><th className="px-2 py-2">Akun</th><th className="px-2 py-2 text-right">Debit</th><th className="px-2 py-2 text-right">Kredit</th><th className="px-2 py-2">Memo</th><th></th></tr></thead>
                            <tbody>
                                {manual.lines.map((l, i) => (
                                    <tr key={i} className="border-t border-slate-100">
                                        <td className="min-w-[220px] px-2 py-1.5"><Select value={l.coa_id} onChange={(v) => setLine(i, 'coa_id', v)} options={(coa.data || []).filter((c) => c.postable)} getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— akun —" /></td>
                                        <td className="px-2 py-1.5"><input type="number" step="0.01" className="field-input w-32 text-right" value={l.debit} onChange={(e) => setLine(i, 'debit', e.target.value)} /></td>
                                        <td className="px-2 py-1.5"><input type="number" step="0.01" className="field-input w-32 text-right" value={l.credit} onChange={(e) => setLine(i, 'credit', e.target.value)} /></td>
                                        <td className="px-2 py-1.5"><input className="field-input w-40" value={l.memo} onChange={(e) => setLine(i, 'memo', e.target.value)} /></td>
                                        <td className="px-2 py-1.5"><button className="rounded p-1 text-red-500 hover:bg-red-50" onClick={() => setManual((m) => ({ ...m, lines: m.lines.filter((_, j) => j !== i) }))}><Icon name="trash" /></button></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="mt-2 flex items-center justify-between">
                        <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => setManual((m) => ({ ...m, lines: [...m.lines, emptyLine()] }))}><Icon name="plus" className="h-3.5 w-3.5" /> Baris</button>
                        <div className={`text-sm font-medium ${balanced ? 'text-emerald-600' : 'text-red-600'}`}>Debit {money(mDebit)} · Kredit {money(mCredit)} {balanced ? '· seimbang ✓' : '· belum seimbang'}</div>
                    </div>
                </>)}
            </Modal>
        </div>
    );
}
