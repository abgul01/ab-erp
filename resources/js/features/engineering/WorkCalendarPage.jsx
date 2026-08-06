import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import MonthPicker, { currentPeriod, formatPeriod } from '../../components/MonthPicker';

const DOW = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const HOLIDAY_TYPES = [
    { value: 'NASIONAL', label: 'Nasional' },
    { value: 'CUTI_BERSAMA', label: 'Cuti Bersama' },
    { value: 'PERUSAHAAN', label: 'Perusahaan' },
];

const EMPTY_FORM = { date: '', name: '', type: 'NASIONAL', is_working: false, hours: 8, active: true };

const TABS = [
    { key: 'calendar', icon: 'calendar', label: 'Kalender' },
    { key: 'holiday', icon: 'tag', label: 'Hari Libur' },
];

/**
 * Working calendar.
 *
 * MPS schedules production onto these dates and CRP measures machine load
 * against these hours, so this screen decides whether both are telling the
 * truth. A month nobody has filled in falls back to "weekdays, two shifts" —
 * that is flagged rather than hidden, because a fallback is an assumption.
 *
 * Holidays live in a master on the second tab; generating a month applies the
 * active ones automatically.
 */
export default function WorkCalendarPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    // Keys are stable and separate from the labels — matching on the visible
    // text is how the page ended up opening on the wrong tab with neither
    // button highlighted.
    const [tab, setTab] = useState(TABS[0].key);

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Kalender Kerja</h1>
                <p className="text-xs text-slate-400">
                    Menentukan hari mana pabrik berjalan dan berapa jam produktifnya. Dipakai penjadwalan MPS dan perhitungan kapasitas CRP —
                    kalender yang salah membuat keduanya ikut salah.
                </p>
            </div>

            <div className="mb-4 flex gap-1 border-b border-slate-200">
                {TABS.map((t) => (
                    <button key={t.key}
                        className={`-mb-px flex items-center gap-2 border-b-2 px-4 py-2 text-sm ${tab === t.key ? 'border-blue-600 font-semibold text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
                        onClick={() => setTab(t.key)}>
                        <Icon name={t.icon} className="h-4 w-4" /> {t.label}
                    </button>
                ))}
            </div>

            {tab === 'calendar' ? <CalendarTab can={can} qc={qc} /> : <HolidayTab can={can} qc={qc} />}
        </div>
    );
}

function CalendarTab({ can, qc }) {
    const [period, setPeriod] = useState(currentPeriod());
    const [hours, setHours] = useState(16);
    const [saturday, setSaturday] = useState(false);

    const eff = useQuery({
        queryKey: ['work-calendar', 'effective', period],
        queryFn: async () => (await api.get('/work-calendar/effective', { params: { period } })).data.data,
    });
    const rows = useQuery({
        queryKey: ['work-calendar', period],
        queryFn: async () => (await api.get('/work-calendar', { params: { period } })).data.data,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['work-calendar'] });

    const generate = useMutation({
        mutationFn: async () => (await api.post('/work-calendar/generate', {
            period, hours: Number(hours), saturday_works: saturday,
        })).data.data,
        onSuccess: (d) => { invalidate(); alert(`Kalender ${formatPeriod(d.period)} dibuat: ${d.days_written} hari, ${d.working_days} hari kerja. Hari libur dari master otomatis diterapkan.`); },
        onError: (e) => alert(apiError(e)),
    });

    const toggle = useMutation({
        mutationFn: async (row) => api.put(`/work-calendar/${row.id}`, {
            is_working: !row.is_working,
            hours: !row.is_working ? Number(hours) : 0,
            note: !row.is_working ? null : 'Libur',
        }),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const days = rows.data || [];

    return (
        <div>
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div className="flex flex-wrap items-end gap-2">
                    <div><label className="field-label">Periode</label><MonthPicker value={period} onChange={setPeriod} className="w-40" /></div>
                    <div><label className="field-label">Jam/hari</label><input type="number" min="1" max="24" step="0.5" className="field-input w-24" value={hours} onChange={(e) => setHours(e.target.value)} /></div>
                    <label className="flex items-center gap-2 pb-2 text-sm text-slate-600">
                        <input type="checkbox" checked={saturday} onChange={(e) => setSaturday(e.target.checked)} /> Sabtu kerja
                    </label>
                    {can('work-calendar', 'create') && (
                        <button className="btn btn-primary" disabled={generate.isPending}
                            onClick={() => window.confirm(`Buat kalender ${formatPeriod(period)}? Hari yang sudah diatur akan ditimpa.`) && generate.mutate()}>
                            <Icon name="calendar" /> {generate.isPending ? 'Membuat…' : 'Generate Bulan Ini'}
                        </button>
                    )}
                </div>
            </div>

            {eff.data?.is_fallback && (
                <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Bulan ini belum punya kalender. Sementara sistem memakai asumsi <b>hari kerja Senin–Jumat, 16 jam/hari</b> —
                    libur nasional dan cuti bersama <b>belum diperhitungkan</b>. Tekan “Generate Bulan Ini” — hari libur dari master diterapkan otomatis.
                </div>
            )}

            <div className="mb-4 grid gap-3 sm:grid-cols-3">
                <Stat label="Hari kerja" value={eff.data?.working_days ?? '—'} />
                <Stat label="Total jam produktif" value={eff.data ? `${eff.data.total_hours} jam` : '—'} />
                <Stat label="Sumber" value={eff.data ? (eff.data.is_fallback ? 'Asumsi bawaan' : 'Kalender tersimpan') : '—'} />
            </div>

            <div className="card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th className="px-4 py-3">Tanggal</th>
                            <th className="px-4 py-3">Hari</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Jam</th>
                            <th className="px-4 py-3">Keterangan</th>
                            <th className="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.isLoading && <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></td></tr>}
                        {!rows.isLoading && days.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-400">Belum ada kalender untuk bulan ini.</td></tr>
                        )}
                        {days.map((d) => {
                            const dow = new Date(d.date).getDay();
                            return (
                                <tr key={d.id} className={`border-b border-slate-100 ${d.is_working ? '' : 'bg-slate-50'}`}>
                                    <td className="px-4 py-2">{d.date}</td>
                                    <td className={`px-4 py-2 ${dow === 0 ? 'text-red-600' : 'text-slate-600'}`}>{DOW[dow]}</td>
                                    <td className="px-4 py-2">
                                        <span className={`rounded-full px-2 py-0.5 text-xs ${d.is_working ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'}`}>
                                            {d.is_working ? 'Kerja' : 'Libur'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2">{d.is_working ? `${d.hours} jam` : '—'}</td>
                                    <td className="px-4 py-2 text-slate-500">{d.note || '—'}</td>
                                    <td className="px-4 py-2 text-right">
                                        {can('work-calendar', 'edit') && (
                                            <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => toggle.mutate(d)}>
                                                Jadikan {d.is_working ? 'Libur' : 'Kerja'}
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function HolidayTab({ can, qc }) {
    const [period, setPeriod] = useState(currentPeriod());
    const [form, setForm] = useState(EMPTY_FORM);
    const [editing, setEditing] = useState(null);
    const [showForm, setShowForm] = useState(false);

    const holidays = useQuery({
        queryKey: ['holidays', period],
        queryFn: async () => (await api.get('/holidays', { params: { period } })).data.data,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['holidays'] });

    const save = useMutation({
        mutationFn: async (payload) => editing
            ? api.put(`/holidays/${editing.id}`, payload)
            : api.post('/holidays', payload),
        onSuccess: () => { invalidate(); setShowForm(false); setForm(EMPTY_FORM); setEditing(null); },
        onError: (e) => alert(apiError(e)),
    });

    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/holidays/${id}`),
        onSuccess: invalidate,
        onError: (e) => alert(apiError(e)),
    });

    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: k === 'is_working' || k === 'active' ? e.target.checked : e.target.value }));

    const submit = () => {
        const payload = {
            date: form.date,
            name: form.name,
            type: form.type,
            is_working: form.is_working,
            hours: form.is_working ? Number(form.hours) : 0,
            active: form.active,
        };
        if (!payload.date || !payload.name.trim()) return alert('Isi tanggal dan nama libur.');
        save.mutate(payload);
    };

    const edit = (h) => {
        setEditing(h);
        setForm({
            date: h.date, name: h.name, type: h.type,
            is_working: h.is_working, hours: h.hours || 8, active: h.active,
        });
        setShowForm(true);
    };

    const rows = holidays.data || [];

    return (
        <div>
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div className="flex flex-wrap items-end gap-2">
                    <div><label className="field-label">Bulan</label><MonthPicker value={period} onChange={setPeriod} className="w-40" /></div>
                    <div className="mb-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-500">
                        Hari libur aktif diterapkan otomatis saat <b>Generate Bulan Ini</b> di tab Kalender.
                    </div>
                </div>
                {can('work-calendar', 'create') && (
                    <button className="btn btn-primary" onClick={() => { setEditing(null); setForm(EMPTY_FORM); setShowForm(!showForm); }}>
                        <Icon name="plus" /> {showForm && !editing ? 'Tutup' : 'Tambah Libur'}
                    </button>
                )}
            </div>

            {showForm && (
                <div className="card mb-4 p-4">
                    <div className="mb-3 text-sm font-semibold text-slate-700">
                        {editing ? `Ubah: ${editing.name}` : 'Hari libur baru'}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                        <div><label className="field-label">Tanggal</label><input type="date" className="field-input w-full" value={form.date} onChange={set('date')} /></div>
                        <div className="lg:col-span-2"><label className="field-label">Nama</label><input type="text" className="field-input w-full" placeholder="Idul Fitri 1448, …" value={form.name} onChange={set('name')} /></div>
                        <div><label className="field-label">Tipe</label>
                            <select className="field-input w-full" value={form.type} onChange={set('type')}>
                                {HOLIDAY_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                            </select>
                        </div>
                        <div className="flex items-end gap-4 pb-1">
                            <label className="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" checked={form.is_working} onChange={set('is_working')} /> Pabrik tetap jalan
                            </label>
                            {form.is_working && (
                                <div><label className="field-label">Jam</label><input type="number" min="1" max="24" step="0.5" className="field-input w-20" value={form.hours} onChange={set('hours')} /></div>
                            )}
                        </div>
                        <div className="flex items-end gap-2 pb-1">
                            <label className="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" checked={form.active} onChange={set('active')} /> Aktif
                            </label>
                            <button className="btn btn-primary px-3" disabled={save.isPending} onClick={submit}>
                                <Icon name="check" /> {editing ? 'Simpan' : 'Tambah'}
                            </button>
                            {editing && (
                                <button className="btn btn-ghost px-3" onClick={() => { setShowForm(false); setEditing(null); setForm(EMPTY_FORM); }}>
                                    <Icon name="x" /> Batal
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            )}

            <div className="card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th className="px-4 py-3">Tanggal</th>
                            <th className="px-4 py-3">Nama</th>
                            <th className="px-4 py-3">Tipe</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Jam</th>
                            <th className="px-4 py-3">Aktif</th>
                            <th className="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {holidays.isLoading && <tr><td colSpan={7} className="px-4 py-10 text-center text-slate-400"><Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" /></td></tr>}
                        {!holidays.isLoading && rows.length === 0 && (
                            <tr><td colSpan={7} className="px-4 py-10 text-center text-slate-400">Belum ada hari libur untuk bulan ini. Tambahkan dari master (mis. SKB 3 Menteri) lalu Generate kalender.</td></tr>
                        )}
                        {rows.map((h) => {
                            const type = HOLIDAY_TYPES.find((t) => t.value === h.type)?.label ?? h.type;
                            return (
                                <tr key={h.id} className="border-b border-slate-100">
                                    <td className="px-4 py-2">{h.date}</td>
                                    <td className="px-4 py-2 font-medium text-slate-700">{h.name}</td>
                                    <td className="px-4 py-2">
                                        <span className="rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">{type}</span>
                                    </td>
                                    <td className="px-4 py-2">
                                        <span className={`rounded-full px-2 py-0.5 text-xs ${h.is_working ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'}`}>
                                            {h.is_working ? 'Pabrik jalan' : 'Pabrik libur'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2">{h.is_working ? `${h.hours} jam` : '—'}</td>
                                    <td className="px-4 py-2">
                                        <span className={`rounded-full px-2 py-0.5 text-xs ${h.active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500'}`}>
                                            {h.active ? 'Aktif' : 'Nonaktif'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        {can('work-calendar', 'edit') && (
                                            <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => edit(h)}><Icon name="pencil" className="h-3.5 w-3.5" /></button>
                                        )}
                                        {can('work-calendar', 'delete') && (
                                            <button className="btn btn-ghost px-2 py-1 text-xs text-red-600"
                                                onClick={() => window.confirm(`Hapus ${h.name}?`) && remove.mutate(h.id)}>
                                                <Icon name="trash" className="h-3.5 w-3.5" />
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function Stat({ label, value }) {
    return (
        <div className="card p-3">
            <div className="text-xs text-slate-400">{label}</div>
            <div className="text-lg font-semibold text-slate-800">{value}</div>
        </div>
    );
}
