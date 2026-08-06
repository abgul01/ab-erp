import { useQuery } from '@tanstack/react-query';
import api from '../../api/client';
import Icon from '../../components/Icon';
import { StatusBadge, money } from '../procurement/common';
import { PHASES } from './common';

/** Kartu KPI dengan satu angka besar dan satu kalimat penjelas. */
function Kpi({ label, value, unit, hint, tone = 'slate' }) {
    const tones = {
        slate: 'text-slate-800',
        emerald: 'text-emerald-700',
        amber: 'text-amber-700',
        red: 'text-red-700',
    };

    return (
        <div className="rounded-lg border border-slate-200 bg-white p-3">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className={`mt-0.5 text-2xl font-semibold ${tones[tone]}`}>
                {value ?? '—'}{value != null && unit ? <span className="ml-1 text-sm font-normal text-slate-400">{unit}</span> : null}
            </p>
            {hint && <p className="mt-0.5 text-[11px] text-slate-400">{hint}</p>}
        </div>
    );
}

/**
 * Laporan NPD (PRD §11).
 *
 * Rata-rata lead time hanya menghitung proyek yang sudah diserahterimakan —
 * memasukkan yang masih berjalan akan menurunkan angkanya secara palsu, karena
 * proyek yang baru dimulai kemarin ikut menyumbang satu hari. Berapa yang belum
 * ikut dihitung tetap ditampilkan, supaya angkanya bisa dinilai apa adanya.
 */
export default function NpdReportPage() {
    const { data, isLoading } = useQuery({
        queryKey: ['npd-report'],
        queryFn: async () => (await api.get('/npd/report')).data.data,
    });

    if (isLoading) {
        return (
            <div className="flex h-64 items-center justify-center gap-3 text-slate-400">
                <Icon name="spinner" className="h-5 w-5 animate-spin" /> Memuat laporan…
            </div>
        );
    }

    const r = data || {};
    const lt = r.lead_time || {};
    const ot = r.on_time || {};
    const ppap = r.ppap || {};
    const ad = r.adoption || {};

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Laporan NPD</h1>
                <p className="text-sm text-slate-500">
                    Ukuran keberhasilan pengembangan produk: berapa lama dari RFQ sampai serah terima, seberapa sering tepat target,
                    dan di mana proyek paling sering tertahan. Per {r.as_of}.
                </p>
            </div>

            <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
                <Kpi label="Lead time rata-rata" value={lt.avg_days} unit="hari"
                    hint={`dari ${lt.completed || 0} proyek selesai · ${lt.ongoing || 0} masih berjalan`} />
                <Kpi label="Tercepat / terlama" value={lt.min_days != null ? `${lt.min_days}–${lt.max_days}` : null} unit="hari"
                    hint="rentang proyek yang sudah selesai" />
                <Kpi label="Tepat target SOP" value={ot.pct} unit="%"
                    tone={ot.pct == null ? 'slate' : ot.pct >= 80 ? 'emerald' : 'amber'}
                    hint={`${ot.on_time || 0} dari ${ot.with_target || 0} proyek bertarget`} />
                <Kpi label="Gate ditolak" value={r.gate_rejects?.total ?? 0}
                    tone={(r.gate_rejects?.total ?? 0) > 0 ? 'amber' : 'slate'}
                    hint="alasannya di bawah — bahan perbaikan proses" />
                <Kpi label="Part lahir dari NPD" value={ad.handed_over ?? 0}
                    tone="emerald" hint={`${ad.still_trial ?? 0} part masih berstatus uji coba`} />
            </div>

            <div className="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
                {/* PPAP */}
                <div className="rounded-lg border border-slate-200 bg-white p-3">
                    <h2 className="mb-2 text-sm font-semibold text-slate-700">PPAP Submission</h2>
                    {(ppap.total ?? 0) === 0 ? (
                        <p className="py-4 text-center text-sm text-slate-400">Belum ada submission PPAP.</p>
                    ) : (
                        <div className="grid grid-cols-4 gap-2 text-center text-sm">
                            <div><p className="text-lg font-semibold text-slate-800">{ppap.total}</p><p className="text-[11px] text-slate-400">total</p></div>
                            <div><p className="text-lg font-semibold text-emerald-700">{ppap.approved}</p><p className="text-[11px] text-slate-400">approved</p></div>
                            <div><p className="text-lg font-semibold text-amber-700">{ppap.interim}</p><p className="text-[11px] text-slate-400">interim</p></div>
                            <div><p className="text-lg font-semibold text-red-700">{ppap.rejected}</p><p className="text-[11px] text-slate-400">rejected</p></div>
                        </div>
                    )}
                    <p className="mt-2 text-[11px] text-slate-400">
                        Hanya yang <b>approved</b> yang membuka serah terima part ke produksi massal.
                    </p>
                </div>

                {/* Gate reject */}
                <div className="rounded-lg border border-slate-200 bg-white p-3">
                    <h2 className="mb-2 text-sm font-semibold text-slate-700">Gate yang pernah ditolak</h2>
                    {(r.gate_rejects?.recent || []).length === 0 ? (
                        <p className="py-4 text-center text-sm text-slate-400">Belum ada gate yang ditolak.</p>
                    ) : (
                        <div className="max-h-48 overflow-y-auto">
                            <table className="w-full text-sm">
                                <tbody>
                                    {r.gate_rejects.recent.map((g, i) => (
                                        <tr key={i} className="border-b border-slate-100 last:border-0">
                                            <td className="py-1.5 pr-2 align-top">
                                                <span className="font-medium">{g.code}</span>
                                                <span className="ml-1 text-xs text-slate-400">fase {g.phase_no}</span>
                                            </td>
                                            <td className="py-1.5 text-xs text-slate-600">{g.note || '—'}</td>
                                            <td className="py-1.5 pl-2 text-right text-[11px] text-slate-400">{String(g.acted_at || '').slice(0, 10)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            <h2 className="mb-2 text-sm font-semibold text-slate-700">Lead time per proyek</h2>
            <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                        <th className="px-3 py-2">Kode</th><th className="px-3 py-2">Proyek</th>
                        <th className="px-3 py-2">Pelanggan</th><th className="px-3 py-2">Fase</th>
                        <th className="px-3 py-2">RFQ</th><th className="px-3 py-2">Serah terima</th>
                        <th className="px-3 py-2 text-right">Lead time</th><th className="px-3 py-2">Status</th>
                    </tr></thead>
                    <tbody>
                        {(r.projects || []).length === 0 && (
                            <tr><td colSpan={8} className="px-3 py-6 text-center text-slate-400">Belum ada proyek NPD.</td></tr>
                        )}
                        {(r.projects || []).map((p) => (
                            <tr key={p.id} className={`border-t border-slate-100 ${p.late ? 'bg-amber-50' : ''}`}>
                                <td className="px-3 py-1.5 font-medium">{p.code}</td>
                                <td className="px-3 py-1.5">{p.name}</td>
                                <td className="px-3 py-1.5 text-slate-600">{p.customer || '—'}</td>
                                <td className="px-3 py-1.5 text-xs text-slate-500">
                                    {p.phase_no} · {PHASES.find((x) => x.no === p.phase_no)?.short}
                                </td>
                                <td className="px-3 py-1.5 text-slate-500">{String(p.rfq_date || '').slice(0, 10) || '—'}</td>
                                <td className="px-3 py-1.5 text-slate-500">{String(p.handover_date || '').slice(0, 10) || '—'}</td>
                                <td className="px-3 py-1.5 text-right">
                                    {money(p.days)} hari
                                    {p.ongoing && <span className="ml-1 text-[11px] text-slate-400">berjalan</span>}
                                </td>
                                <td className="px-3 py-1.5">
                                    {p.late
                                        ? <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-700">lewat target SOP</span>
                                        : <StatusBadge status={p.status} />}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-xs text-slate-400">
                Peringatan harian untuk task yang lewat tanggal, gate yang menggantung lebih dari 3 hari, dan target SOP yang terlampaui
                muncul di menu <b>Peringatan Operasional</b> dan di dashboard.
            </p>
        </div>
    );
}
