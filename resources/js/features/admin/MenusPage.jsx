import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';

const ICON_CHOICES = [
    'shield', 'database', 'tag', 'tags', 'ruler', 'coins', 'percent', 'factory', 'cog', 'users',
    'workflow', 'wrench', 'box', 'layers', 'shopping-cart', 'clipboard-list', 'file-text',
    'package-check', 'scale', 'calculator', 'check', 'send', 'ban', 'lock', 'undo', 'receipt',
    'warehouse', 'rows', 'boxes', 'calendar', 'trending-up', 'alert-triangle', 'bell', 'clock',
    'scissors', 'play', 'arrow-down', 'download', 'eye', 'upload', 'truck', 'package-open',
];

export default function MenusPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const refreshMenus = useAuth((s) => s.refreshMenus);

    const [modal, setModal] = useState(null); // create|edit
    const [record, setRecord] = useState(null);
    const [values, setValues] = useState({});
    const [error, setError] = useState('');

    const list = useQuery({
        queryKey: ['menus'],
        queryFn: async () => (await api.get('/menus')).data.data,
    });

    const save = useMutation({
        mutationFn: async (payload) => {
            if (modal?.mode === 'edit') return api.put(`/menus/${record.id}`, payload);
            return api.post('/menus', payload);
        },
        onSuccess: async () => {
            qc.invalidateQueries({ queryKey: ['menus'] });
            await refreshMenus(); // sidebar ikut berubah
            setModal(null);
        },
        onError: (e) => setError(apiError(e)),
    });

    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/menus/${id}`),
        onSuccess: async () => {
            qc.invalidateQueries({ queryKey: ['menus'] });
            await refreshMenus();
        },
        onError: (e) => alert(apiError(e)),
    });

    const openCreate = () => {
        const firstRoot = list.data?.find((m) => m.parent_id === 0);
        setValues({ name: '', link: '', parent_id: firstRoot?.id ?? '', icon: 'box', sort: 0 });
        setError('');
        setRecord(null);
        setModal({ mode: 'create' });
    };

    const openEdit = (row) => {
        setValues({ name: row.name, link: row.link === '0' ? '' : row.link, parent_id: row.parent_id, icon: row.icon || '', sort: row.sort ?? 0 });
        setError('');
        setRecord(row);
        setModal({ mode: 'edit' });
    };

    const submit = (e) => {
        e.preventDefault();
        setError('');
        save.mutate({ ...values, link: values.link || '0' });
    };

    const rows = list.data || [];
    const byId = Object.fromEntries(rows.map((m) => [m.id, m]));

    const columns = [
        {
            key: 'name',
            label: 'Nama',
            render: (v, r) => (
                <div className="flex items-center gap-2">
                    <Icon name={r.icon || 'box'} className="h-4 w-4 text-slate-400" />
                    <span className={r.parent_id === 0 ? 'font-semibold text-slate-800' : 'text-slate-600'}>{v}</span>
                </div>
            ),
        },
        { key: 'parent', label: 'Induk', render: (v) => (v && v.parent_id === 0 ? v.name : '—') },
        { key: 'link', label: 'Link', render: (v) => (v === '0' ? '—' : v) },
        { key: 'icon', label: 'Icon', render: (v) => v || '—' },
        { key: 'children_count', label: 'Anak', render: (v) => v || 0 },
        { key: 'sort', label: 'Urutan' },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold text-slate-800">Manajemen Menu Sidebar</h1>
                {can('menus', 'create') && (
                    <button className="btn btn-primary" onClick={openCreate}>
                        <Icon name="plus" /> Tambah Menu
                    </button>
                )}
            </div>

            <div className="card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {columns.map((c) => (
                                    <th key={c.key} className="px-4 py-3 whitespace-nowrap">{c.label}</th>
                                ))}
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {list.isLoading && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-10 text-center text-slate-400">
                                        <Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" />
                                    </td>
                                </tr>
                            )}
                            {!list.isLoading && rows.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-10 text-center text-slate-400">Tidak ada data.</td>
                                </tr>
                            )}
                            {!list.isLoading &&
                                rows.map((row) => (
                                    <tr key={row.id} className="border-b border-slate-100 hover:bg-blue-50/40">
                                        {columns.map((c) => (
                                            <td key={c.key} className="px-4 py-2.5 whitespace-nowrap text-slate-700">
                                                {c.render ? c.render(row[c.key], row) : (row[c.key] ?? '—')}
                                            </td>
                                        ))}
                                        <td className="px-4 py-2.5 text-right">
                                            <div className="flex justify-end gap-1">
                                                {can('menus', 'edit') && (
                                                    <button className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}>
                                                        <Icon name="pencil" />
                                                    </button>
                                                )}
                                                {can('menus', 'delete') && (
                                                    <button
                                                        className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                                        onClick={() => window.confirm(`Hapus menu "${row.name}"?`) && remove.mutate(row.id)}
                                                    >
                                                        <Icon name="trash" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal
                open={!!modal}
                onClose={() => setModal(null)}
                title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Menu`}
                footer={
                    <>
                        <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                        <button className="btn btn-primary" onClick={submit} disabled={save.isPending}>
                            {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />}
                            Simpan
                        </button>
                    </>
                }
            >
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <label className="field-label">Nama Menu <span className="text-red-500">*</span></label>
                        <input className="field-input" value={values.name ?? ''} onChange={(e) => setValues((v) => ({ ...v, name: e.target.value }))} />
                    </div>
                    <div>
                        <label className="field-label">Link (route)</label>
                        <input className="field-input" placeholder="contoh: users" value={values.link ?? ''} onChange={(e) => setValues((v) => ({ ...v, link: e.target.value }))} />
                    </div>
                    <div>
                        <label className="field-label">Menu Induk <span className="text-red-500">*</span></label>
                        <select className="field-input" value={values.parent_id ?? ''} onChange={(e) => setValues((v) => ({ ...v, parent_id: e.target.value }))}>
                            {rows.filter((m) => m.id !== record?.id).map((m) => (
                                <option key={m.id} value={m.id}>{m.parent_id === 0 ? m.name : `— ${byId[m.parent_id]?.name} / ${m.name}`}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="field-label">Icon</label>
                        <input className="field-input" list="icon-choices" value={values.icon ?? ''} onChange={(e) => setValues((v) => ({ ...v, icon: e.target.value }))} />
                        <datalist id="icon-choices">
                            {ICON_CHOICES.map((i) => <option key={i} value={i} />)}
                        </datalist>
                    </div>
                    <div>
                        <label className="field-label">Urutan (sort)</label>
                        <input className="field-input" type="number" min="0" value={values.sort ?? 0} onChange={(e) => setValues((v) => ({ ...v, sort: e.target.value }))} />
                    </div>
                </form>
            </Modal>
        </div>
    );
}
