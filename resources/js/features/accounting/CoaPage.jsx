import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';

const GROUPS = ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'COGS', 'EXPENSE'];
const GROUP_STYLE = { ASSET: 'bg-sky-100 text-sky-700', LIABILITY: 'bg-amber-100 text-amber-700', EQUITY: 'bg-violet-100 text-violet-700', REVENUE: 'bg-emerald-100 text-emerald-700', COGS: 'bg-rose-100 text-rose-700', EXPENSE: 'bg-slate-100 text-slate-600' };
const EMPTY = { code: '', name: '', acc_group: 'ASSET', postable: true };

/** Chart of Accounts master. */
export default function CoaPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');

    const list = useQuery({ queryKey: ['coa', { page }], queryFn: async () => (await api.get('/coa', { params: { page, per_page: 100 } })).data });
    const invalidate = () => qc.invalidateQueries({ queryKey: ['coa'] });

    const save = useMutation({
        mutationFn: async (p) => (modal.mode === 'edit' ? api.put(`/coa/${modal.id}`, p) : api.post('/coa', p)),
        onSuccess: () => { invalidate(); setModal(null); }, onError: (e) => setError(apiError(e)),
    });
    const remove = useMutation({ mutationFn: async (id) => api.delete(`/coa/${id}`), onSuccess: invalidate, onError: (e) => alert(apiError(e)) });

    const openCreate = () => { setForm(EMPTY); setError(''); setModal({ mode: 'create' }); };
    const openEdit = (r) => { setForm({ code: r.code, name: r.name, acc_group: r.acc_group, postable: !!r.postable }); setError(''); setModal({ mode: 'edit', id: r.id }); };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const columns = [
        { key: 'code', label: 'Kode' },
        { key: 'name', label: 'Nama Akun' },
        { key: 'acc_group', label: 'Golongan', render: (v) => <span className={`rounded px-1.5 py-0.5 text-xs ${GROUP_STYLE[v] || ''}`}>{v}</span> },
        { key: 'postable', label: 'Bisa Diposting', render: (v) => (v ? '✓' : '—') },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-800">Chart of Accounts</h1>
                    <p className="text-xs text-slate-400">Daftar akun buku besar (COA) untuk jurnal.</p>
                </div>
                {can('coa', 'create') && <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Akun</button>}
            </div>

            <DataTable columns={columns} rows={list.data?.data || []} meta={list.data?.meta} loading={list.isLoading} onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('coa', 'edit') && <button title="Edit" className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}><Icon name="pencil" /></button>}
                        {can('coa', 'delete') && <button title="Hapus" className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus akun?') && remove.mutate(row.id)}><Icon name="trash" /></button>}
                    </div>
                )} />

            <Modal open={!!modal} onClose={() => setModal(null)} title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Akun`}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                    <button className="btn btn-primary" onClick={() => { setError(''); save.mutate(form); }} disabled={save.isPending}>{save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan</button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label className="field-label">Kode <span className="text-red-500">*</span></label><input className="field-input" maxLength={20} value={form.code} onChange={(e) => set('code', e.target.value)} placeholder="1100" /></div>
                    <div><label className="field-label">Golongan <span className="text-red-500">*</span></label><select className="field-input" value={form.acc_group} onChange={(e) => set('acc_group', e.target.value)}>{GROUPS.map((g) => <option key={g} value={g}>{g}</option>)}</select></div>
                    <div className="sm:col-span-2"><label className="field-label">Nama Akun <span className="text-red-500">*</span></label><input className="field-input" maxLength={150} value={form.name} onChange={(e) => set('name', e.target.value)} /></div>
                    <div className="sm:col-span-2"><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.postable} onChange={(e) => set('postable', e.target.checked)} /> Bisa diposting (akun transaksi)</label></div>
                </div>
            </Modal>
        </div>
    );
}
