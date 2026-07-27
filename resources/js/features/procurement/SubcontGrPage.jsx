import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';

const today = () => new Date().toISOString().slice(0, 10);

/**
 * Subcont — Terima (Goods Receipt). Record goods coming back from the vendor
 * against a sent Delivery Note (qty ok/ng); the DN closes when all of it returns.
 */
export default function SubcontGrPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(false);
    const [form, setForm] = useState({ date: today(), dn_id: '', ven_dn_no: '', qty_ok: 0, qty_ng: 0 });
    const [error, setError] = useState('');

    const list = useQuery({ queryKey: ['subcont-gr', { page }], queryFn: async () => (await api.get('/subcont/gr', { params: { page, per_page: 15 } })).data });
    const dns = useQuery({ queryKey: ['subcont-gr', 'open-dns'], queryFn: async () => (await api.get('/subcont/open-dns')).data.data, enabled: modal });
    const invalidate = () => { qc.invalidateQueries({ queryKey: ['subcont-gr'] }); qc.invalidateQueries({ queryKey: ['subcont-dn'] }); };

    const save = useMutation({
        mutationFn: async () => api.post('/subcont/gr', { ...form, qty_ok: Number(form.qty_ok), qty_ng: Number(form.qty_ng) }),
        onSuccess: () => { invalidate(); setModal(false); }, onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/subcont/gr/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const open = () => { setForm({ date: today(), dn_id: '', ven_dn_no: '', qty_ok: 0, qty_ng: 0 }); setError(''); setModal(true); };
    const selectedDn = (dns.data || []).find((d) => d.id === Number(form.dn_id));
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const recv = Number(form.qty_ok || 0) + Number(form.qty_ng || 0);

    const columns = [
        { key: 'code', label: 'No. GR' },
        { key: 'date', label: 'Tanggal', render: (v) => v?.slice(0, 10) },
        { key: 'dn', label: 'DN', render: (v) => v?.code || '—' },
        { key: 'ven_dn_no', label: 'No. DN Vendor', render: (v) => v || '—' },
        { key: 'qty_ok', label: 'OK', className: 'text-right' },
        { key: 'qty_ng', label: 'NG', className: 'text-right' },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-semibold text-slate-800">Subcont — Terima (Goods Receipt)</h1>
                {can('subcont-gr', 'create') && <button className="btn btn-primary" onClick={open}><Icon name="plus" /> Terima</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('subcont-gr', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus GR?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={modal} onClose={() => setModal(false)} size="max-w-xl" title="Terima dari Subcont"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(false)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(); }} disabled={save.isPending || !form.dn_id || recv < 1}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <label className="field-label">Delivery Note terkirim <span className="text-red-500">*</span></label>
                        <select className="field-input" value={form.dn_id} onChange={(e) => set('dn_id', e.target.value ? Number(e.target.value) : '')}>
                            <option value="">— pilih DN —</option>
                            {(dns.data || []).map((d) => <option key={d.id} value={d.id}>{d.code} — {d.vendor} (sisa {d.outstanding})</option>)}
                        </select>
                        {selectedDn && <p className="mt-1 text-xs text-slate-400">PO {selectedDn.po_code} · terkirim {selectedDn.sent}, diterima {selectedDn.received}, sisa {selectedDn.outstanding}</p>}
                    </div>
                    <div><label className="field-label">Tanggal</label><input type="date" className="field-input" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
                    <div><label className="field-label">No. DN Vendor</label><input className="field-input" maxLength={50} value={form.ven_dn_no} onChange={(e) => set('ven_dn_no', e.target.value)} placeholder="opsional" /></div>
                    <div><label className="field-label">Qty OK</label><input type="number" min="0" className="field-input" value={form.qty_ok} onChange={(e) => set('qty_ok', e.target.value)} /></div>
                    <div><label className="field-label">Qty NG</label><input type="number" min="0" className="field-input" value={form.qty_ng} onChange={(e) => set('qty_ng', e.target.value)} /></div>
                    {selectedDn && recv > selectedDn.outstanding && <p className="sm:col-span-2 text-xs text-red-600">Total diterima ({recv}) melebihi sisa DN ({selectedDn.outstanding}).</p>}
                </div>
            </Modal>
        </div>
    );
}
