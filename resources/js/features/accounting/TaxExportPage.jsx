import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import { money } from '../procurement/common';

const firstOfMonth = () => new Date().toISOString().slice(0, 8) + '01';
const today = () => new Date().toISOString().slice(0, 10);

/**
 * Statutory filings for a period: the Coretax e-Faktur XML for output VAT and
 * the e-Bupot CSV for PPh 23 withheld from vendors. The preview is there so the
 * period can be reconciled against the books before anything is filed.
 */
export default function TaxExportPage() {
    const can = useAuth((s) => s.can);
    const [from, setFrom] = useState(firstOfMonth());
    const [to, setTo] = useState(today());
    const [busy, setBusy] = useState(null);

    const preview = useQuery({
        queryKey: ['tax-export', from, to],
        queryFn: async () => (await api.get('/tax-export/preview', { params: { from, to } })).data.data,
    });

    async function download(kind, filename) {
        setBusy(kind);
        try {
            const res = await api.get(`/tax-export/${kind}`, { params: { from, to }, responseType: 'blob' });
            const url = URL.createObjectURL(res.data);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        } catch (e) {
            alert(apiError(e));
        } finally {
            setBusy(null);
        }
    }

    const d = preview.data;

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Ekspor Pajak</h1>
                <p className="text-xs text-slate-400">
                    e-Faktur (Coretax) untuk PPN keluaran dan e-Bupot untuk PPh 23 yang dipotong dari vendor.
                    Nilai diambil apa adanya dari dokumen yang sudah diposting.
                </p>
            </div>

            <div className="mb-5 flex flex-wrap items-end gap-3 rounded-md border border-slate-200 bg-white p-4">
                <div><label className="field-label">Dari tanggal</label><input type="date" className="field-input w-44" value={from} onChange={(e) => setFrom(e.target.value)} /></div>
                <div><label className="field-label">Sampai tanggal</label><input type="date" className="field-input w-44" value={to} onChange={(e) => setTo(e.target.value)} /></div>
                <button className="btn btn-ghost" onClick={() => preview.refetch()}><Icon name="search" /> Hitung Ulang</button>
            </div>

            {preview.isError && <div className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{apiError(preview.error)}</div>}

            <div className="grid gap-4 md:grid-cols-2">
                <Card
                    title="e-Faktur — PPN Keluaran"
                    note="Faktur penjualan bernomor seri pajak pada periode ini."
                    loading={preview.isLoading}
                    rows={[
                        ['Jumlah faktur', d?.efaktur.count ?? 0],
                        ['DPP', money(d?.efaktur.dpp ?? 0)],
                        ['PPN', money(d?.efaktur.vat ?? 0)],
                    ]}
                    disabled={!can('tax-export', 'download') || !(d?.efaktur.count)}
                    busy={busy === 'efaktur'}
                    label="Unduh XML Coretax"
                    onClick={() => download('efaktur', `efaktur-${from}-${to}.xml`)}
                />
                <Card
                    title="e-Bupot — PPh 23"
                    note="Invoice vendor yang memotong PPh 23 pada periode ini."
                    loading={preview.isLoading}
                    rows={[
                        ['Jumlah bukti potong', d?.ebupot23.count ?? 0],
                        ['DPP', money(d?.ebupot23.dpp ?? 0)],
                        ['PPh 23 dipotong', money(d?.ebupot23.wht23 ?? 0)],
                    ]}
                    disabled={!can('tax-export', 'download') || !(d?.ebupot23.count)}
                    busy={busy === 'ebupot23'}
                    label="Unduh CSV e-Bupot"
                    onClick={() => download('ebupot23', `ebupot23-${from}-${to}.csv`)}
                />
            </div>
        </div>
    );
}

function Card({ title, note, rows, loading, disabled, busy, label, onClick }) {
    return (
        <div className="rounded-md border border-slate-200 bg-white p-4">
            <div className="mb-1 font-semibold text-slate-800">{title}</div>
            <p className="mb-3 text-xs text-slate-400">{note}</p>
            <dl className="mb-4 space-y-1 text-sm">
                {rows.map(([k, v]) => (
                    <div key={k} className="flex justify-between border-b border-dashed border-slate-200 py-1">
                        <dt className="text-slate-500">{k}</dt>
                        <dd className="font-medium text-slate-800">{loading ? '…' : v}</dd>
                    </div>
                ))}
            </dl>
            <button className="btn btn-primary w-full" disabled={disabled || busy} onClick={onClick}>
                <Icon name="download" /> {busy ? 'Menyiapkan…' : label}
            </button>
        </div>
    );
}
