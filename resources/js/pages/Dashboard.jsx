import { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Chart as ChartJS, ArcElement, CategoryScale, LinearScale, BarElement, PointElement, LineElement, Tooltip, Legend, Filler } from 'chart.js';
import { Bar, Doughnut, Line } from 'react-chartjs-2';
import api from '../api/client';
import { useAuth } from '../stores/auth';
import Icon from '../components/Icon';

ChartJS.register(ArcElement, CategoryScale, LinearScale, BarElement, PointElement, LineElement, Tooltip, Legend, Filler);

const fmtNum = (v) => (v == null ? '—' : Number(v).toLocaleString('id-ID'));
const fmtPct = (v) => (v == null ? '—' : `${Number(v).toFixed(1)}%`);

const WO_STATUS_COLORS = {
    DRAFT: '#94a3b8', OPEN: '#3b82f6', INPROGRESS: '#f59e0b', COMPLETED: '#10b981', CLOSED: '#6366f1', CANCELLED: '#ef4444',
};
const WO_STATUS_LABELS = {
    DRAFT: 'Draft', OPEN: 'Open', INPROGRESS: 'Proses', COMPLETED: 'Selesai', CLOSED: 'Tutup', CANCELLED: 'Batal',
};

const NAV_MAP = {
    pr: '/pr', po: '/po', grn: '/grn', wo: '/work-orders', so: '/sales-orders',
    do: '/delivery-orders', mps: '/mps', mrp: '/mrp', stockRm: '/stock-rm',
    stockFg: '/stock-fg', cutting: '/mes-cutting', processing: '/mes-processing',
    abnormal: '/mes-abnormal', invoice: '/ap-invoices', ar: '/sales-invoices',
    forecast: '/forecasts', subcontPo: '/subcont-po', journals: '/journals',
};

const TABS = [
    { key: 'executive', label: 'Eksekutif', icon: 'dashboard' },
    { key: 'production', label: 'Produksi', icon: 'factory' },
    { key: 'procurement', label: 'Pengadaan', icon: 'shopping-cart' },
    { key: 'sales', label: 'Penjualan & Gudang', icon: 'box' },
];

function TrendBadge({ current, previous }) {
    if (current == null || previous == null || previous === 0) return null;
    const diff = current - previous;
    const pct = previous > 0 ? (diff / previous) * 100 : 0;
    const isUp = diff > 0;
    const isNeutral = diff === 0;
    return (
        <span className={`inline-flex items-center gap-0.5 text-xs font-medium ${isNeutral ? 'text-slate-400' : isUp ? 'text-emerald-600' : 'text-red-500'}`}>
            <Icon name={isNeutral ? 'minus' : isUp ? 'trending-up' : 'trending-down'} className="h-3 w-3" />
            {isNeutral ? 'sama' : `${isUp ? '+' : ''}${fmtPct(pct)}`}
        </span>
    );
}

function KpiCard({ icon, label, value, sub, trend, color = 'blue', onClick, href }) {
    const navigate = useNavigate();
    const colorMap = {
        blue: 'bg-blue-50 text-blue-600 border-blue-100',
        emerald: 'bg-emerald-50 text-emerald-600 border-emerald-100',
        amber: 'bg-amber-50 text-amber-600 border-amber-100',
        red: 'bg-red-50 text-red-600 border-red-100',
        violet: 'bg-violet-50 text-violet-600 border-violet-100',
        slate: 'bg-slate-100 text-slate-600 border-slate-200',
        indigo: 'bg-indigo-50 text-indigo-600 border-indigo-100',
        sky: 'bg-sky-50 text-sky-600 border-sky-100',
    };
    const handleClick = onClick || (href ? () => navigate(href) : null);
    return (
        <div
            onClick={handleClick}
            className={`card flex items-center gap-4 p-4 ${handleClick ? 'cursor-pointer transition hover:shadow-md hover:-translate-y-0.5 active:translate-y-0' : ''}`}
        >
            <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border ${colorMap[color]}`}>
                <Icon name={icon} className="h-6 w-6" />
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <span className="text-2xl font-bold tabular-nums text-slate-800">{fmtNum(value)}</span>
                    {trend && <TrendBadge current={trend.current} previous={trend.previous} />}
                </div>
                <div className="text-sm text-slate-500 truncate">{label}</div>
                {sub && <div className="mt-0.5 text-xs text-slate-400 truncate">{sub}</div>}
            </div>
        </div>
    );
}

function MiniStat({ icon, label, value, color = 'slate' }) {
    const colorMap = {
        slate: 'text-slate-600 bg-slate-50',
        emerald: 'text-emerald-600 bg-emerald-50',
        amber: 'text-amber-600 bg-amber-50',
        red: 'text-red-600 bg-red-50',
        blue: 'text-blue-600 bg-blue-50',
        violet: 'text-violet-600 bg-violet-50',
    };
    return (
        <div className="flex items-center gap-2.5 rounded-lg border border-slate-100 px-3.5 py-2.5">
            <Icon name={icon} className={`h-4 w-4 ${colorMap[color]?.split(' ')[0] || 'text-slate-500'}`} />
            <div>
                <div className="text-sm font-semibold tabular-nums text-slate-800">{fmtNum(value)}</div>
                <div className="text-[11px] text-slate-400">{label}</div>
            </div>
        </div>
    );
}

function AlertItem({ icon, message, href, type = 'warning' }) {
    const navigate = useNavigate();
    const colors = {
        warning: 'bg-amber-50 border-amber-200 text-amber-800',
        danger: 'bg-red-50 border-red-200 text-red-700',
        info: 'bg-blue-50 border-blue-200 text-blue-700',
        success: 'bg-emerald-50 border-emerald-200 text-emerald-700',
    };
    const icons = {
        warning: 'alert-triangle', danger: 'alert-octagon', info: 'info', success: 'check-circle',
    };
    return (
        <div
            onClick={() => href && navigate(href)}
            className={`flex items-center gap-3 rounded-lg border px-4 py-2.5 text-sm ${colors[type]} ${href ? 'cursor-pointer transition hover:shadow-sm' : ''}`}
        >
            <Icon name={icons[type]} className="h-4 w-4 shrink-0" />
            <span className="flex-1">{message}</span>
            {href && <Icon name="right" className="h-4 w-4 shrink-0 opacity-50" />}
        </div>
    );
}

function ProgressBar({ label, value, total, color }) {
    const pct = total > 0 ? (value / total) * 100 : 0;
    return (
        <div className="flex items-center gap-3">
            <span className="w-24 text-xs font-medium text-slate-600 truncate">{label}</span>
            <div className="flex-1 h-2.5 rounded-full bg-slate-100 overflow-hidden">
                <div className="h-full rounded-full transition-all duration-500" style={{ width: `${pct}%`, backgroundColor: color || '#0b3d91' }} />
            </div>
            <span className="w-10 text-right text-xs font-semibold tabular-nums text-slate-700">{value}</span>
        </div>
    );
}

function WoDoughnutChart({ data, onSegmentClick }) {
    if (!data || Object.keys(data).length === 0) return <p className="text-sm text-slate-400 py-10 text-center">Belum ada data WO.</p>;
    const labels = Object.keys(data).map((k) => WO_STATUS_LABELS[k] || k);
    const values = Object.values(data);
    const colors = Object.keys(data).map((k) => WO_STATUS_COLORS[k] || '#94a3b8');
    return (
        <Doughnut
            data={{ labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0, hoverOffset: 12 }] }}
            options={{
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 14, padding: 14, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' } },
                    tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${fmtNum(ctx.parsed)} WO (${fmtPct((ctx.parsed / values.reduce((a, b) => a + b, 0)) * 100)} dari total)` } },
                },
                onClick: (e, elements) => {
                    if (elements.length > 0 && onSegmentClick) {
                        const idx = elements[0].index;
                        onSegmentClick(Object.keys(data)[idx]);
                    }
                },
            }}
        />
    );
}

function TrendLineChart({ data, label, color, borderColor }) {
    if (!data || Object.keys(data).length < 2) return <p className="text-sm text-slate-400 py-10 text-center">Data tren belum mencukupi.</p>;
    const labels = Object.keys(data).map((m) => {
        const [y, mo] = m.split('-');
        const months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        return `${months[parseInt(mo)]}`;
    });
    const values = Object.values(data);
    return (
        <Line
            data={{
                labels,
                datasets: [{
                    label, data: values, borderColor: borderColor || color || '#0b3d91',
                    backgroundColor: (ctx) => {
                        if (!ctx.chart.chartArea) return 'transparent';
                        const g = ctx.chart.ctx.createLinearGradient(0, ctx.chart.chartArea.top, 0, ctx.chart.chartArea.bottom);
                        const hex = borderColor || color || '#0b3d91';
                        g.addColorStop(0, `${hex}33`);
                        g.addColorStop(1, `${hex}03`);
                        return g;
                    },
                    fill: true, tension: 0.35, pointRadius: 4, pointHoverRadius: 7,
                    borderWidth: 2.5,
                }],
            }}
            options={{
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => `${ctx.parsed.y} ${label}` } } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } }, grid: { color: '#f1f5f9' } },
                    x: { ticks: { font: { size: 11 } }, grid: { display: false } },
                },
                interaction: { intersect: false, mode: 'index' },
            }}
        />
    );
}

function BarChart({ data, label, color, horizontal, onClick }) {
    if (!data || Object.keys(data).length === 0) return <p className="text-sm text-slate-400 py-10 text-center">Belum ada data.</p>;
    const labels = Object.keys(data).map((m) => {
        if (/^\d{4}-\d{2}$/.test(m)) {
            const [y, mo] = m.split('-');
            const months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return `${months[parseInt(mo)]} ${y}`;
        }
        return m;
    });
    const values = Object.values(data);
    return (
        <Bar
            data={{
                labels,
                datasets: [{ label, data: values, backgroundColor: color || '#0b3d91', borderRadius: 6, maxBarThickness: 40, hoverBackgroundColor: typeof color === 'string' ? color + 'cc' : '#1e5fc4' }],
            }}
            options={{
                indexAxis: horizontal ? 'y' : 'x',
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (ctx) => `${ctx.parsed[horizontal ? 'x' : 'y']} ${label}` } },
                },
                scales: {
                    x: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } }, grid: { color: '#f1f5f9' } },
                    y: { ticks: { font: { size: 11 } }, grid: { display: false } },
                },
                onClick: (e, elements) => {
                    if (elements.length > 0 && onClick) onClick(elements[0].index);
                },
            }}
        />
    );
}

function HorizontalBar({ data, label, color }) {
    if (!data || data.length === 0) return <p className="text-sm text-slate-400 py-10 text-center">Belum ada data.</p>;
    const labels = data.map((d) => d.item_code);
    const values = data.map((d) => Number(d.value));
    return (
        <Bar
            data={{
                labels,
                datasets: [{ label: label || 'Qty', data: values, backgroundColor: color || '#6366f1', borderRadius: 6, maxBarThickness: 32 }],
            }}
            options={{
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => `${ctx.parsed.x} ${label || 'pcs'}` } } },
                scales: {
                    x: { beginAtZero: true, ticks: { font: { size: 11 } }, grid: { color: '#f1f5f9' } },
                    y: { ticks: { font: { size: 11 } }, grid: { display: false } },
                },
            }}
        />
    );
}

function SoFulfillmentChart({ data }) {
    if (!data) return <p className="text-sm text-slate-400 py-10 text-center">Belum ada data SO.</p>;
    const pending = data.pending || 0;
    const delivered = data.delivered || 0;
    const ordered = data.ordered || 0;
    const pct = ordered > 0 ? (delivered / ordered) * 100 : 0;
    if (ordered === 0) return <p className="text-sm text-slate-400 py-10 text-center">Tidak ada SO aktif.</p>;
    return (
        <div className="space-y-4">
            <div className="flex items-center justify-center gap-8 py-2">
                <div className="text-center">
                    <div className="text-2xl font-bold tabular-nums text-slate-800">{fmtNum(ordered)}</div>
                    <div className="text-xs text-slate-400">Total Dipesan</div>
                </div>
                <div className="text-center">
                    <div className="text-2xl font-bold tabular-nums text-emerald-600">{fmtNum(delivered)}</div>
                    <div className="text-xs text-slate-400">Terkirim</div>
                </div>
                <div className="text-center">
                    <div className="text-2xl font-bold tabular-nums text-amber-600">{fmtNum(pending)}</div>
                    <div className="text-xs text-slate-400">Belum Kirim</div>
                </div>
            </div>
            <div className="h-3 rounded-full bg-slate-100 overflow-hidden">
                <div className="h-full rounded-full bg-emerald-500 transition-all duration-700" style={{ width: `${Math.min(pct, 100)}%` }} />
            </div>
            <div className="flex justify-between text-xs text-slate-400">
                <span>{fmtPct(pct)} terkirim</span>
                <span>{fmtPct(100 - pct)} pending</span>
            </div>
        </div>
    );
}

function MrpBreakdownChart({ mrp }) {
    if (!mrp || !mrp.total_net_req) return <p className="text-sm text-slate-400 py-10 text-center">Belum ada data MRP.</p>;
    const labels = ['Kebutuhan RM (saran PR)', 'Kebutuhan FG (saran WO)'];
    const values = [mrp.rm_net_req || 0, mrp.fg_net_req || 0];
    const colors = ['#f59e0b', '#6366f1'];
    return (
        <div className="space-y-4">
            <Bar
                data={{
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderRadius: 6,
                        maxBarThickness: 48,
                    }],
                }}
                options={{
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: (ctx) => `${fmtNum(ctx.parsed.x)} pcs` } },
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { font: { size: 11 } }, grid: { color: '#f1f5f9' } },
                        y: { ticks: { font: { size: 11 } }, grid: { display: false } },
                    },
                }}
            />
            <div className="flex items-center justify-between rounded-lg bg-amber-50/50 px-4 py-2.5 text-sm">
                <span className="text-slate-600">Total <b>{mrp.unfulfilled_items}</b> material blm terpenuhi</span>
                <span className="font-semibold tabular-nums text-amber-700">{fmtNum(mrp.total_net_req)} pcs</span>
            </div>
        </div>
    );
}

function LowStockChart({ data }) {
    if (!data || data.length === 0) return <p className="text-sm text-slate-400 py-10 text-center">Tidak ada item di bawah stok minimum.</p>;
    const labels = data.map((d) => d.item_code);
    const stock = data.map((d) => Number(d.stock_qty));
    const min = data.map((d) => Number(d.min_stock));
    return (
        <div className="space-y-3">
            {data.map((d, i) => {
                const s = Number(d.stock_qty);
                const m = Number(d.min_stock);
                const pct = m > 0 ? (s / m) * 100 : 0;
                return (
                    <div key={i} className="flex items-center gap-3">
                        <span className="w-20 text-xs font-medium text-slate-600 truncate" title={d.item_name}>{d.item_code}</span>
                        <div className="flex-1">
                            <div className="h-3 rounded-full bg-red-50 overflow-hidden border border-red-100">
                                <div className="h-full rounded-full bg-red-500 transition-all duration-500" style={{ width: `${Math.min(pct, 100)}%` }} />
                            </div>
                        </div>
                        <span className="w-20 text-right text-xs tabular-nums">
                            <span className="font-semibold text-red-600">{fmtNum(s)}</span>
                            <span className="text-slate-400"> / {fmtNum(m)}</span>
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

function Section({ title, subtitle, children, className = '', action }) {
    return (
        <div className={`card overflow-hidden ${className}`}>
            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                <div>
                    <h3 className="text-sm font-semibold text-slate-700">{title}</h3>
                    {subtitle && <p className="text-xs text-slate-400 mt-0.5">{subtitle}</p>}
                </div>
                {action && <div className="text-xs text-slate-400">{action}</div>}
            </div>
            <div className="p-5">
                {children}
            </div>
        </div>
    );
}

function TabButton({ active, icon, label, onClick }) {
    return (
        <button
            onClick={onClick}
            className={`flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all ${
                active
                    ? 'bg-white text-[var(--ftpi-primary)] shadow-sm border border-slate-200'
                    : 'text-slate-500 hover:text-slate-700 hover:bg-white/60 border border-transparent'
            }`}
        >
            <Icon name={icon} className="h-4 w-4" />
            <span className="hidden sm:inline">{label}</span>
        </button>
    );
}

export default function Dashboard() {
    const { user } = useAuth();
    const navigate = useNavigate();
    const [activeTab, setActiveTab] = useState('executive');
    const [period, setPeriod] = useState(() => {
        const d = new Date();
        return `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}`;
    });

    const can = useAuth((st) => st.can);

    const { data, isLoading } = useQuery({
        queryKey: ['dashboard'],
        queryFn: async () => (await api.get('/dashboard')).data.data,
        staleTime: 60_000,
    });

    /*
     * Open operational alerts (kuota impor, stok minimum). The nightly job
     * raises them; the dashboard is where someone actually looks, so a quota
     * about to run out is visible before a PO is refused because of it.
     */
    const { data: alertFeed } = useQuery({
        queryKey: ['dashboard-alerts'],
        queryFn: async () => (await api.get('/alerts')).data.data,
        enabled: !!can?.('alerts', 'view'),
        staleTime: 60_000,
    });

    const q = data || {};
    const m = q.master || {};
    const p = q.procurement || {};
    const pr = q.production || {};
    const w = q.wms || {};
    const s = q.sales || {};
    const mes = q.mes || {};
    const acc = q.accounting || {};
    const sc = q.subcont || {};
    const mr = q.mrp || {};
    const ch = q.charts || {};
    const tr = q.trends || {};

    const alerts = useMemo(() => {
        const items = [];
        // Satu kotak per jenis peringatan, bukan satu per item — deretan baris
        // serupa yang panjang berhenti dibaca. Daftar detail ada di halaman
        // Peringatan (/alerts); dashboard cukup memberi tahu jumlahnya.
        const byType = {};
        for (const a of alertFeed?.items || []) {
            const key = a.type || 'LAINNYA';
            byType[key] ??= { count: 0, sample: a };
            byType[key].count += 1;
        }
        const TYPE_LABEL = {
            MIN_STOCK: 'item stok di bawah minimum',
            QUOTA: 'kuota impor hampir habis',
        };
        for (const [type, g] of Object.entries(byType)) {
            const label = TYPE_LABEL[type] || 'peringatan operasional';
            items.push({
                type: 'warning',
                icon: 'alert-triangle',
                message: `${g.count} ${label} — lihat daftar di Peringatan`,
                href: '/alerts',
            });
        }
        if (s.so_overdue > 0) items.push({ type: 'danger', icon: 'alert-octagon', message: `${s.so_overdue} SO melewati due date — ${fmtNum(s.so_unfulfilled_qty)} pcs belum terkirim`, href: '/sales-orders' });
        if (p.po_overdue > 0) items.push({ type: 'danger', icon: 'alert-octagon', message: `${p.po_overdue} PO melewati tanggal estimasi — perlu tindakan segera`, href: '/po' });
        if (mr.unfulfilled_items > 0) items.push({ type: 'warning', icon: 'alert-triangle', message: `${mr.unfulfilled_items} material belum terpenuhi dari MRP terakhir (${fmtNum(mr.total_net_req)} pcs total kebutuhan)`, href: '/mrp' });
        if (w.scrap_candidates > 0) items.push({ type: 'warning', icon: 'alert-triangle', message: `${w.scrap_candidates} temuan abnormal (scrap candidate) menunggu keputusan`, href: '/mes-abnormal' });
        if (pr.mps_pending > 0) items.push({ type: 'info', icon: 'info', message: `${pr.mps_pending} MPS dalam status Draft — jadwalkan produksi`, href: '/mps' });
        if (pr.mrp_pending > 0) items.push({ type: 'info', icon: 'info', message: `${pr.mrp_pending} MRP dalam status Draft — review kebutuhan material`, href: '/mrp' });
        if (s.ar_unpaid > 0) items.push({ type: 'warning', icon: 'alert-triangle', message: `${s.ar_unpaid} invoice penjualan belum dibayar`, href: '/sales-invoices' });
        if (p.ap_unpaid > 0) items.push({ type: 'info', icon: 'info', message: `${p.ap_unpaid} invoice pembelian belum dibayar`, href: '/ap-invoices' });
        return items;
    }, [p, w, pr, s, mr, alertFeed]);

    const totalWO = useMemo(() => Object.values(ch.wo_by_status || {}).reduce((a, b) => a + b, 0), [ch.wo_by_status]);

    if (isLoading) {
        return (
            <div className="flex h-64 items-center justify-center gap-3">
                <Icon name="spinner" className="h-5 w-5 animate-spin text-[var(--ftpi-primary)]" />
                <span className="text-sm text-slate-400">Memuat dashboard…</span>
            </div>
        );
    }

    if (!data) {
        return (
            <div className="flex h-64 items-center justify-center">
                <div className="text-center">
                    <Icon name="alert-octagon" className="mx-auto h-8 w-8 text-red-400 mb-2" />
                    <p className="text-sm text-slate-500">Gagal memuat data dashboard.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="p-4 sm:p-6 space-y-5">
            {/* ── Header ── */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-800">Selamat datang, {user?.name}</h1>
                    <p className="text-sm text-slate-500">Dashboard Analisa ERP — PT. Fusoh Tube Parts Indonesia</p>
                </div>
                <div className="flex items-center gap-2">
                    <Icon name="calendar" className="h-4 w-4 text-slate-400" />
                    <input
                        className="field-input w-32 text-sm"
                        value={period}
                        onChange={(e) => setPeriod(e.target.value.replace(/\D/g, '').slice(0, 6))}
                        placeholder="YYYYMM"
                        title="Periode (YYYYMM)"
                    />
                </div>
            </div>

            {/* ── Alerts ── */}
            {alerts.length > 0 && (
                <div className="space-y-2">
                    <div className="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <Icon name="bell" className="h-3.5 w-3.5" /> Perlu perhatian
                    </div>
                    <div className="space-y-1.5">
                        {alerts.map((a, i) => <AlertItem key={i} {...a} />)}
                    </div>
                </div>
            )}

            {/* ── Tab Navigation ── */}
            <div className="flex gap-1.5 border-b border-slate-200 pb-1 overflow-x-auto">
                {TABS.map((tab) => (
                    <TabButton key={tab.key} active={activeTab === tab.key} icon={tab.icon} label={tab.label} onClick={() => setActiveTab(tab.key)} />
                ))}
            </div>

            {/* ════════════════════════════════════════════ */}
            {/* TAB 1: EXECUTIVE SUMMARY                    */}
            {/* ════════════════════════════════════════════ */}
            {activeTab === 'executive' && (
                <div className="space-y-5">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <KpiCard icon="factory" label="WO Aktif (Open + Proses)" value={(pr.wo_open || 0) + (pr.wo_inprogress || 0)} sub={`${fmtNum(pr.wo_completed)} selesai bulan ini`} color="blue" trend={{ current: tr.wo_this_month, previous: tr.wo_last_month }} href="/work-orders" />
                        <KpiCard icon="shopping-cart" label="PO Open" value={p.po_open} sub={`${p.po_partial} partial · ${p.po_overdue} overdue`} color="emerald" trend={{ current: tr.po_this_month, previous: tr.po_last_month }} href="/po" />
                        <KpiCard icon="clipboard-list" label="SO Open" value={s.so_open} sub={`${s.so_partial} partial · ${s.so_overdue} overdue · ${fmtNum(s.so_unfulfilled_qty)} pcs blm terkirim`} color="violet" trend={{ current: tr.so_this_month, previous: tr.so_last_month }} href="/sales-orders" />
                        <KpiCard icon="box" label="Item Stok RM" value={w.rm_stock_items} sub={`${fmtNum(w.rm_total_qty)} pcs · ${fmtNum(w.rm_total_weight)} kg`} color="amber" href="/stock-rm" />
                    </div>

                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <Section title="Status Work Order" subtitle={`${fmtNum(totalWO)} total WO`}>
                            <div className="h-60">
                                <WoDoughnutChart data={ch.wo_by_status} onSegmentClick={(status) => navigate('/work-orders')} />
                            </div>
                        </Section>
                        <Section title="Aktivitas Hari Ini" subtitle="Cutting, Processing, Downtime & Abnormal">
                            <div className="h-60">
                                <div className="grid grid-cols-2 gap-3 mb-4">
                                    <MiniStat icon="scissors" label="Cutting" value={mes.cutting_today} color="blue" />
                                    <MiniStat icon="cog" label="Processing" value={mes.processing_today} color="violet" />
                                    <MiniStat icon="clock" label="Downtime" value={mes.downtime_today} color="amber" />
                                    <MiniStat icon="alert-triangle" label="Abnormal" value={mes.abnormal_today} color="red" />
                                </div>
                                <div className="space-y-2">
                                    <AlertItem icon="check-circle" message={`Periode akuntansi: ${acc.period_open ? 'Aktif (OPEN)' : 'Tidak ada periode aktif'}`} type={acc.period_open ? 'success' : 'warning'} />
                                    <AlertItem icon="file-text" message={`${acc.journal_draft} jurnal dalam status Draft`} type={acc.journal_draft > 0 ? 'info' : 'success'} href="/journals" />
                                </div>
                            </div>
                        </Section>
                    </div>

                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                        <Section title="Pemenuhan SO" subtitle={`${s.so_overdue} SO overdue · ${fmtNum(s.so_unfulfilled_qty)} pcs pending`}>
                            <div className="h-44 flex items-center">
                                <SoFulfillmentChart data={ch.so_fulfillment} />
                            </div>
                        </Section>
                        <Section title="Kebutuhan MRP" subtitle={`${mr.unfulfilled_items} material blm terpenuhi`}>
                            <div className="h-44">
                                <MrpBreakdownChart mrp={mr} />
                            </div>
                        </Section>
                        <Section title="Stok Di Bawah Minimum" subtitle={`${w.low_stock_items} item RM`}>
                            <div className="h-44 overflow-y-auto">
                                <LowStockChart data={ch.low_stock} />
                            </div>
                        </Section>
                    </div>

                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <Section title="Tren GRN" subtitle="6 bulan terakhir">
                            <div className="h-52">
                                <TrendLineChart data={ch.grn_trend} label="GRN" color="#3b82f6" borderColor="#2563eb" />
                            </div>
                        </Section>
                        <Section title="Tren Sales Order" subtitle="6 bulan terakhir">
                            <div className="h-52">
                                <TrendLineChart data={ch.so_trend} label="SO" color="#10b981" borderColor="#059669" />
                            </div>
                        </Section>
                    </div>
                </div>
            )}

            {/* ════════════════════════════════════════════ */}
            {/* TAB 2: PRODUCTION                           */}
            {/* ════════════════════════════════════════════ */}
            {activeTab === 'production' && (
                <div className="space-y-5">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <KpiCard icon="file-text" label="WO Draft" value={pr.wo_draft} color="slate" href="/work-orders" />
                        <KpiCard icon="play" label="WO Open" value={pr.wo_open} color="blue" href="/work-orders" />
                        <KpiCard icon="loader" label="WO In Progress" value={pr.wo_inprogress} color="amber" href="/work-orders" />
                        <KpiCard icon="check-circle" label="WO Completed" value={pr.wo_completed} color="emerald" href="/work-orders" />
                    </div>

                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                        <Section title="Aktivitas MES Hari Ini" action={<span className="text-xs text-slate-400">{new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</span>}>
                            <div className="grid grid-cols-2 gap-3">
                                <MiniStat icon="scissors" label="Transaksi Cutting" value={mes.cutting_today} color="blue" />
                                <MiniStat icon="cog" label="Transaksi Processing" value={mes.processing_today} color="violet" />
                                <MiniStat icon="clock" label="Downtime" value={mes.downtime_today} color="amber" />
                                <MiniStat icon="alert-triangle" label="Abnormal" value={mes.abnormal_today} color="red" />
                            </div>
                            <div className="mt-4 flex gap-2">
                                <button onClick={() => navigate('/mes-cutting')} className="btn btn-ghost text-xs flex-1">Lihat Cutting</button>
                                <button onClick={() => navigate('/mes-processing')} className="btn btn-ghost text-xs flex-1">Lihat Processing</button>
                                <button onClick={() => navigate('/mes-report')} className="btn btn-ghost text-xs flex-1">Laporan MES</button>
                            </div>
                        </Section>

                        <Section title="Perencanaan" subtitle="MPS & MRP">
                            <div className="space-y-4">
                                <div className="flex items-center justify-between rounded-lg border border-slate-100 px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                                            <Icon name="clipboard-list" className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="text-sm font-medium text-slate-700">MPS Pending</div>
                                            <div className="text-xs text-slate-400">Master Production Schedule</div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-lg font-bold tabular-nums text-violet-600">{fmtNum(pr.mps_pending)}</span>
                                        <button onClick={() => navigate('/mps')} className="rounded-md border border-slate-200 px-2.5 py-1 text-xs text-slate-500 hover:bg-slate-50">Buka</button>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between rounded-lg border border-slate-100 px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                            <Icon name="clipboard-list" className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="text-sm font-medium text-slate-700">MRP Draft</div>
                                            <div className="text-xs text-slate-400">Material Requirement Planning</div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-lg font-bold tabular-nums text-amber-600">{fmtNum(pr.mrp_pending)}</span>
                                        <button onClick={() => navigate('/mrp')} className="rounded-md border border-slate-200 px-2.5 py-1 text-xs text-slate-500 hover:bg-slate-50">Buka</button>
                                    </div>
                                </div>
                                <div className="rounded-lg border border-slate-100 bg-amber-50/30 px-4 py-3">
                                    <div className="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-3">Kebutuhan Material (MRP Terakhir)</div>
                                    <div className="h-36">
                                        <MrpBreakdownChart mrp={mr} />
                                    </div>
                                    <button onClick={() => navigate('/mrp')} className="mt-3 w-full btn btn-ghost text-xs">Lihat Detail MRP</button>
                                </div>
                            </div>
                        </Section>

                        <Section title="Ringkasan Produksi" subtitle="Status WO">
                            <div className="space-y-3">
                                {Object.entries(ch.wo_by_status || {}).length > 0 ? (
                                    Object.entries(ch.wo_by_status || {}).map(([status, count]) => (
                                        <ProgressBar key={status} label={WO_STATUS_LABELS[status] || status} value={count} total={totalWO} color={WO_STATUS_COLORS[status]} />
                                    ))
                                ) : (
                                    <p className="text-sm text-slate-400 text-center py-4">Belum ada data WO.</p>
                                )}
                                <button onClick={() => navigate('/work-orders')} className="mt-2 w-full btn btn-ghost text-xs">Lihat Semua WO</button>
                            </div>
                        </Section>
                    </div>

                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <Section title="Distribusi Status WO" subtitle="Grafik donat">
                            <div className="h-56">
                                <WoDoughnutChart data={ch.wo_by_status} onSegmentClick={() => navigate('/work-orders')} />
                            </div>
                        </Section>
                        <Section title="Laporan MES" subtitle="Aktual vs Rencana">
                            <div className="space-y-3">
                                <p className="text-sm text-slate-500">Bandingkan hasil aktual produksi dengan rencana MPS per mesin/hari.</p>
                                <button onClick={() => navigate('/mes-report')} className="btn btn-primary w-full justify-center">
                                    <Icon name="file-text" className="h-4 w-4" /> Buka Laporan MES
                                </button>
                            </div>
                        </Section>
                    </div>
                </div>
            )}

            {/* ════════════════════════════════════════════ */}
            {/* TAB 3: PROCUREMENT                          */}
            {/* ════════════════════════════════════════════ */}
            {activeTab === 'procurement' && (
                <div className="space-y-5">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <KpiCard icon="file-text" label="PR Draft" value={p.pr_draft} color="amber" href="/pr" />
                        <KpiCard icon="shopping-cart" label="PO Open" value={p.po_open} sub={`${p.po_partial} partial`} color="blue" trend={{ current: tr.po_this_month, previous: tr.po_last_month }} href="/po" />
                        <KpiCard icon="package-check" label="GR Pending" value={p.gr_pending} color="emerald" href="/grn" />
                        <KpiCard icon="receipt" label="AP Belum Bayar" value={p.ap_unpaid} color="red" href="/ap-invoices" />
                    </div>

                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                        <Section title="Pipeline Pengadaan" subtitle="PR → PO → GR → AP">
                            <div className="space-y-3">
                                <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-white px-4 py-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><Icon name="file-text" className="h-5 w-5" /></div>
                                    <div className="flex-1"><div className="text-sm font-medium text-slate-700">PR Draft</div><div className="text-xs text-slate-400">Purchase Request</div></div>
                                    <span className="text-lg font-bold tabular-nums text-amber-600">{fmtNum(p.pr_draft)}</span>
                                </div>
                                <div className="flex items-center gap-2 text-sm text-slate-400 justify-center">
                                    <Icon name="arrow-down" className="h-4 w-4" />
                                </div>
                                <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-white px-4 py-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><Icon name="shopping-cart" className="h-5 w-5" /></div>
                                    <div className="flex-1"><div className="text-sm font-medium text-slate-700">PO Open + Partial</div><div className="text-xs text-slate-400">Purchase Order</div></div>
                                    <span className="text-lg font-bold tabular-nums text-blue-600">{fmtNum((p.po_open || 0) + (p.po_partial || 0))}</span>
                                </div>
                                <div className="flex items-center gap-2 text-sm text-slate-400 justify-center">
                                    <Icon name="arrow-down" className="h-4 w-4" />
                                </div>
                                <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-white px-4 py-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><Icon name="package-check" className="h-5 w-5" /></div>
                                    <div className="flex-1"><div className="text-sm font-medium text-slate-700">GR Pending</div><div className="text-xs text-slate-400">Goods Receipt</div></div>
                                    <span className="text-lg font-bold tabular-nums text-emerald-600">{fmtNum(p.gr_pending)}</span>
                                </div>
                                <div className="flex items-center gap-2 text-sm text-slate-400 justify-center">
                                    <Icon name="arrow-down" className="h-4 w-4" />
                                </div>
                                <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-white px-4 py-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-red-600"><Icon name="receipt" className="h-5 w-5" /></div>
                                    <div className="flex-1"><div className="text-sm font-medium text-slate-700">AP Belum Bayar</div><div className="text-xs text-slate-400">Account Payable</div></div>
                                    <span className="text-lg font-bold tabular-nums text-red-600">{fmtNum(p.ap_unpaid)}</span>
                                </div>
                            </div>
                        </Section>

                        <Section title="Tren GRN" subtitle="6 bulan terakhir">
                            <div className="h-56">
                                <TrendLineChart data={ch.grn_trend} label="GRN" color="#3b82f6" borderColor="#2563eb" />
                            </div>
                            <div className="mt-3 flex gap-2">
                                <button onClick={() => navigate('/grn')} className="btn btn-ghost text-xs flex-1">Lihat GRN</button>
                                <button onClick={() => navigate('/po')} className="btn btn-ghost text-xs flex-1">Lihat PO</button>
                            </div>
                        </Section>

                        <Section title="Subkontrak" subtitle="PO & DN">
                            <div className="space-y-3">
                                <div className="flex items-center justify-between rounded-lg border border-slate-100 px-4 py-3">
                                    <div>
                                        <div className="text-sm font-medium text-slate-700">Subcont PO Open</div>
                                        <div className="text-xs text-slate-400">Purchase Order Subkontrak</div>
                                    </div>
                                    <span className="text-lg font-bold tabular-nums text-blue-600">{fmtNum(sc.po_open)}</span>
                                </div>
                                <div className="flex items-center justify-between rounded-lg border border-slate-100 px-4 py-3">
                                    <div>
                                        <div className="text-sm font-medium text-slate-700">DN Sent</div>
                                        <div className="text-xs text-slate-400">Delivery Note terkirim</div>
                                    </div>
                                    <span className="text-lg font-bold tabular-nums text-amber-600">{fmtNum(sc.dn_sent)}</span>
                                </div>
                                <div className="flex items-center justify-between rounded-lg border border-slate-100 px-4 py-3">
                                    <div>
                                        <div className="text-sm font-medium text-slate-700">DN Draft</div>
                                        <div className="text-xs text-slate-400">Delivery Note draft</div>
                                    </div>
                                    <span className="text-lg font-bold tabular-nums text-slate-600">{fmtNum(sc.dn_draft)}</span>
                                </div>
                                <button onClick={() => navigate('/subcont-po')} className="mt-2 w-full btn btn-ghost text-xs">Lihat Subkontrak</button>
                            </div>
                        </Section>
                    </div>
                </div>
            )}

            {/* ════════════════════════════════════════════ */}
            {/* TAB 4: SALES & WMS                          */}
            {/* ════════════════════════════════════════════ */}
            {activeTab === 'sales' && (
                <div className="space-y-5">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <KpiCard icon="clipboard-list" label="SO Open" value={s.so_open} sub={`${s.so_partial} partial · ${s.so_overdue} overdue · ${fmtNum(s.so_unfulfilled_qty)} pcs blm terkirim`} color="blue" trend={{ current: tr.so_this_month, previous: tr.so_last_month }} href="/sales-orders" />
                        <KpiCard icon="send" label="DO Pending" value={s.do_pending} color="amber" href="/delivery-orders" />
                        <KpiCard icon="receipt" label="AR Belum Bayar" value={s.ar_unpaid} color="red" href="/sales-invoices" />
                        <KpiCard icon="clipboard-list" label="Forecast Bulan Ini" value={s.forecast_current_month} sub="pcs" color="emerald" href="/forecasts" />
                    </div>

                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                        <Section title="Tren Sales Order" subtitle="6 bulan terakhir">
                            <div className="h-44">
                                <TrendLineChart data={ch.so_trend} label="SO" color="#10b981" borderColor="#059669" />
                            </div>
                            <button onClick={() => navigate('/sales-orders')} className="mt-2 w-full btn btn-ghost text-xs">Lihat SO</button>
                        </Section>
                        <Section title="Pemenuhan SO" subtitle={`${s.so_overdue} SO overdue · ${fmtNum(s.so_unfulfilled_qty)} pcs blm terkirim`}>
                            <div className="h-44 flex items-center">
                                <SoFulfillmentChart data={ch.so_fulfillment} />
                            </div>
                            <button onClick={() => navigate('/delivery-orders')} className="mt-2 w-full btn btn-ghost text-xs">Kelola DO</button>
                        </Section>
                        <Section title="Piutang (AR)" subtitle="Invoice belum dibayar">
                            <div className="flex flex-col items-center justify-center py-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-red-50 mb-2">
                                    <Icon name="receipt" className="h-8 w-8 text-red-500" />
                                </div>
                                <span className="text-3xl font-bold tabular-nums text-red-600">{fmtNum(s.ar_unpaid)}</span>
                                <span className="text-sm text-slate-500">Invoice belum dibayar</span>
                                <button onClick={() => navigate('/sales-invoices')} className="mt-3 btn btn-primary text-xs">Kelola AR</button>
                            </div>
                        </Section>
                    </div>

                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <Section title="Stok Gudang" subtitle="Ringkasan inventory">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="rounded-xl border border-blue-100 bg-blue-50/50 p-4 text-center">
                                    <Icon name="box" className="mx-auto h-6 w-6 text-blue-500 mb-2" />
                                    <div className="text-2xl font-bold tabular-nums text-blue-700">{fmtNum(w.rm_stock_items)}</div>
                                    <div className="text-xs text-blue-600">Item RM Aktif</div>
                                    <div className="mt-2 text-xs text-blue-500">{fmtNum(w.rm_total_qty)} pcs · {fmtNum(w.rm_total_weight)} kg</div>
                                    {w.low_stock_items > 0 && (
                                        <div className="mt-2 flex items-center justify-center gap-1 rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-600">
                                            <Icon name="alert-triangle" className="h-3 w-3" /> {w.low_stock_items} item di bawah min
                                        </div>
                                    )}
                                    <button onClick={() => navigate('/stock-rm')} className="mt-3 btn btn-ghost text-xs w-full">Detail RM</button>
                                </div>
                                <div className="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-center">
                                    <Icon name="layers" className="mx-auto h-6 w-6 text-emerald-500 mb-2" />
                                    <div className="text-2xl font-bold tabular-nums text-emerald-700">{fmtNum(w.fg_stock_items)}</div>
                                    <div className="text-xs text-emerald-600">Item FG Aktif</div>
                                    <button onClick={() => navigate('/stock-fg')} className="mt-3 btn btn-ghost text-xs w-full">Detail FG</button>
                                </div>
                            </div>
                        </Section>

                        <Section title="Top 5 Stok RM" subtitle="Item dengan stok terbanyak">
                            <div className="h-52">
                                <HorizontalBar data={ch.top_rm_stock} label="Qty" color="#6366f1" />
                            </div>
                            <button onClick={() => navigate('/stock-rm')} className="mt-3 w-full btn btn-ghost text-xs">Lihat Semua Stok RM</button>
                        </Section>
                        <Section title="Stok Di Bawah Minimum" subtitle={`${w.low_stock_items} item RM perlu reorder`}>
                            <div className="h-52 overflow-y-auto">
                                <LowStockChart data={ch.low_stock} />
                            </div>
                            <button onClick={() => navigate('/stock-rm')} className="mt-3 w-full btn btn-ghost text-xs">Review Stok</button>
                        </Section>
                    </div>

                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                        <Section title="Scrap Candidates" subtitle="Temuan abnormal belum diputuskan">
                            <div className="flex flex-col items-center py-4">
                                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-red-50 mb-2">
                                    <Icon name="alert-triangle" className="h-7 w-7 text-red-500" />
                                </div>
                                <span className="text-2xl font-bold tabular-nums text-red-600">{fmtNum(w.scrap_candidates)}</span>
                                <span className="text-xs text-slate-500 mt-1">Temuan abnormal</span>
                                <button onClick={() => navigate('/mes-abnormal')} className="mt-3 btn btn-ghost text-xs">Review</button>
                            </div>
                        </Section>
                        <Section title="Subkontrak" subtitle="Ringkasan">
                            <div className="space-y-3 py-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-slate-600">PO Open</span>
                                    <span className="font-semibold text-blue-600">{fmtNum(sc.po_open)}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-slate-600">DN Sent</span>
                                    <span className="font-semibold text-amber-600">{fmtNum(sc.dn_sent)}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-slate-600">DN Draft</span>
                                    <span className="font-semibold text-slate-600">{fmtNum(sc.dn_draft)}</span>
                                </div>
                                <button onClick={() => navigate('/subcont-po')} className="mt-2 w-full btn btn-ghost text-xs">Detail</button>
                            </div>
                        </Section>
                        <Section title="Forecast" subtitle="Bulan ini">
                            <div className="flex flex-col items-center py-4">
                                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 mb-2">
                                    <Icon name="clipboard-list" className="h-7 w-7 text-emerald-500" />
                                </div>
                                <span className="text-2xl font-bold tabular-nums text-emerald-700">{fmtNum(s.forecast_current_month)}</span>
                                <span className="text-xs text-slate-500 mt-1">pcs forecast bulan ini</span>
                                <button onClick={() => navigate('/forecasts')} className="mt-3 btn btn-ghost text-xs">Lihat Forecast</button>
                            </div>
                        </Section>
                    </div>
                </div>
            )}
        </div>
    );
}
