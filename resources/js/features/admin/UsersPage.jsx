import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';

const FLAGS = [
    { key: 'can_view', label: 'Lihat' },
    { key: 'can_create', label: 'Buat' },
    { key: 'can_edit', label: 'Ubah' },
    { key: 'can_delete', label: 'Hapus' },
    { key: 'can_download', label: 'Unduh' },
    { key: 'can_import', label: 'Impor' },
];

const EMPTY_PERMS = Object.fromEntries(FLAGS.map((f) => [f.key, false]));

function StatusBadge({ status }) {
    const cls =
        status === 'ADMIN'
            ? 'bg-purple-100 text-purple-700'
            : status === 'ACTIVE'
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-slate-200 text-slate-600';
    return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${cls}`}>{status}</span>;
}

export default function UsersPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);

    const [q, setQ] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null); // create|edit|password
    const [record, setRecord] = useState(null);
    const [values, setValues] = useState({});
    const [pw, setPw] = useState({});
    const [error, setError] = useState('');
    const [permData, setPermData] = useState(null); // { user, menus, byMenu }
    const [permSaving, setPermSaving] = useState(false);
    const [permError, setPermError] = useState('');

    const list = useQuery({
        queryKey: ['users', { search, page }],
        queryFn: async () => (await api.get('/users', { params: { q: search, page, per_page: 15 } })).data,
    });

    const statuses = useQuery({
        queryKey: ['options', 'users-statuses'],
        queryFn: async () => (await api.get('/users/statuses')).data.data,
        staleTime: 60_000,
    });

    const vendors = useQuery({
        queryKey: ['options', 'contacts'],
        queryFn: async () => (await api.get('/contacts', { params: { per_page: 200, q: 'ven' } })).data.data,
        staleTime: 60_000,
    });

    const save = useMutation({
        mutationFn: async (payload) => {
            if (modal?.mode === 'edit') return api.put(`/users/${record.id}`, payload);
            return api.post('/users', payload);
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['users'] });
            setModal(null);
        },
        onError: (e) => setError(apiError(e)),
    });

    const changePassword = useMutation({
        mutationFn: async (payload) => api.post(`/users/${record.id}/password`, payload),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['users'] });
            setModal(null);
        },
        onError: (e) => setError(apiError(e)),
    });

    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/users/${id}`),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
        onError: (e) => alert(apiError(e)),
    });

    const openCreate = () => {
        setValues({ username: '', name: '', email: '', identity: '', password: '', ven_id: '', status_id: statuses.data?.find((s) => s.status === 'ACTIVE')?.id ?? '' });
        setError('');
        setRecord(null);
        setModal({ mode: 'create' });
    };

    const openEdit = (row) => {
        setValues({ username: row.username, name: row.name, email: row.email || '', identity: row.identity || '', ven_id: row.ven_id ?? '', status_id: row.status_id ?? '' });
        setError('');
        setRecord(row);
        setModal({ mode: 'edit' });
    };

    const openPassword = (row) => {
        setPw({ password: '', confirm: '' });
        setError('');
        setRecord(row);
        setModal({ mode: 'password' });
    };

    const submit = (e) => {
        e.preventDefault();
        setError('');
        if (modal.mode === 'password') {
            if (pw.password.length < 6) return setError('Password minimal 6 karakter.');
            if (pw.password !== pw.confirm) return setError('Konfirmasi password tidak sama.');
            return changePassword.mutate({ password: pw.password, password_confirmation: pw.confirm });
        }
        if (modal.mode === 'create' && (!values.password || values.password.length < 6)) {
            return setError('Password minimal 6 karakter.');
        }
        save.mutate(values);
    };

    const openPerms = async (row) => {
        setPermError('');
        setPermSaving(false);
        setRecord(row);
        try {
            const { data } = await api.get(`/users/${row.id}/permissions`);
            const byMenu = {};
            for (const m of data.data.menus) {
                byMenu[m.id] = { ...EMPTY_PERMS, ...(data.data.permissions[m.id] || {}) };
            }
            setPermData({ user: data.data.user, menus: data.data.menus, byMenu });
        } catch (e) {
            setPermError(apiError(e));
        }
    };

    const savePerms = async () => {
        setPermSaving(true);
        setPermError('');
        try {
            await api.put(`/users/${record.id}/permissions`, {
                permissions: permData.menus.map((m) => ({ menu_id: m.id, ...permData.byMenu[m.id] })),
            });
            qc.invalidateQueries({ queryKey: ['users'] });
            setPermData(null);
        } catch (e) {
            setPermError(apiError(e));
        } finally {
            setPermSaving(false);
        }
    };

    const setFlag = (menuId, flag, value) =>
        setPermData((prev) => ({
            ...prev,
            byMenu: { ...prev.byMenu, [menuId]: { ...prev.byMenu[menuId], [flag]: value } },
        }));

    const setGroup = (parent, children, value) => {
        setPermData((prev) => {
            const next = { ...prev.byMenu };
            for (const m of [parent, ...children]) {
                next[m.id] = { ...EMPTY_PERMS, can_view: value, can_create: value, can_edit: value };
            }
            return { ...prev, byMenu: next };
        });
    };

    const rows = list.data?.data || [];
    const meta = list.data?.meta;

    const columns = [
        {
            key: 'username',
            label: 'Username',
            render: (v, r) => (
                <div>
                    <div className="font-medium text-slate-800">{v}</div>
                    {r.status?.status === 'ADMIN' && <div className="text-[11px] text-purple-600">Super Admin</div>}
                </div>
            ),
        },
        { key: 'name', label: 'Nama' },
        { key: 'email', label: 'Email' },
        { key: 'identity', label: 'NIK' },
        { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v?.status} /> },
        { key: 'ven', label: 'Supplier', render: (v) => v?.company_n || '—' },
    ];

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold text-slate-800">Manajemen User</h1>
                {can('users', 'create') && (
                    <button className="btn btn-primary" onClick={openCreate}>
                        <Icon name="plus" /> Registrasi User
                    </button>
                )}
            </div>

            <form
                onSubmit={(e) => { e.preventDefault(); setPage(1); setSearch(q); }}
                className="mb-4 flex max-w-md items-center gap-2"
            >
                <div className="relative flex-1">
                    <Icon name="search" className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                        className="field-input pl-9"
                        placeholder="Cari username / nama / email…"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                </div>
                <button className="btn btn-ghost">Cari</button>
            </form>

            <DataTable
                columns={columns}
                rows={rows}
                meta={meta}
                loading={list.isLoading}
                onPageChange={setPage}
                actions={(row) => {
                    const isAdmin = row.status?.status === 'ADMIN';
                    return (
                        <div className="flex justify-end gap-1">
                            {can('users', 'edit') && (
                                <button className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" title="Edit" onClick={() => openEdit(row)}>
                                    <Icon name="pencil" />
                                </button>
                            )}
                            {can('users', 'edit') && (
                                <button className="rounded p-1.5 text-slate-500 hover:bg-amber-50 hover:text-amber-600" title="Reset Password" onClick={() => openPassword(row)}>
                                    <Icon name="lock" />
                                </button>
                            )}
                            {can('users', 'edit') && !isAdmin && (
                                <button className="rounded p-1.5 text-slate-500 hover:bg-purple-50 hover:text-purple-700" title="Hak Akses" onClick={() => openPerms(row)}>
                                    <Icon name="shield" />
                                </button>
                            )}
                            {can('users', 'delete') && (
                                <button
                                    className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    title="Hapus"
                                    onClick={() => window.confirm(`Hapus user "${row.username}"?`) && remove.mutate(row.id)}
                                >
                                    <Icon name="trash" />
                                </button>
                            )}
                        </div>
                    );
                }}
            />

            {/* Create / Edit */}
            <Modal
                open={modal && (modal.mode === 'create' || modal.mode === 'edit')}
                onClose={() => setModal(null)}
                title={`${modal?.mode === 'edit' ? 'Edit' : 'Registrasi'} User`}
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
                    <div>
                        <label className="field-label">Username <span className="text-red-500">*</span></label>
                        <input className="field-input" value={values.username ?? ''} onChange={(e) => setValues((v) => ({ ...v, username: e.target.value }))} />
                    </div>
                    <div>
                        <label className="field-label">Nama Lengkap <span className="text-red-500">*</span></label>
                        <input className="field-input" value={values.name ?? ''} onChange={(e) => setValues((v) => ({ ...v, name: e.target.value }))} />
                    </div>
                    <div>
                        <label className="field-label">Email</label>
                        <input className="field-input" type="email" value={values.email ?? ''} onChange={(e) => setValues((v) => ({ ...v, email: e.target.value }))} />
                    </div>
                    <div>
                        <label className="field-label">NIK / Identity</label>
                        <input className="field-input" value={values.identity ?? ''} onChange={(e) => setValues((v) => ({ ...v, identity: e.target.value }))} />
                    </div>
                    {modal?.mode === 'create' && (
                        <div>
                            <label className="field-label">Password <span className="text-red-500">*</span></label>
                            <input className="field-input" type="password" value={values.password ?? ''} onChange={(e) => setValues((v) => ({ ...v, password: e.target.value }))} />
                        </div>
                    )}
                    <div>
                        <label className="field-label">Status</label>
                        <select className="field-input" value={values.status_id ?? ''} onChange={(e) => setValues((v) => ({ ...v, status_id: e.target.value }))}>
                            <option value="">— pilih —</option>
                            {(statuses.data || []).map((s) => (
                                <option key={s.id} value={s.id}>{s.status}</option>
                            ))}
                        </select>
                    </div>
                    <div className="sm:col-span-2">
                        <label className="field-label">Supplier (akun portal vendor)</label>
                        <select className="field-input" value={values.ven_id ?? ''} onChange={(e) => setValues((v) => ({ ...v, ven_id: e.target.value }))}>
                            <option value="">— internal / staf —</option>
                            {(vendors.data || []).map((c) => (
                                <option key={c.id} value={c.id}>{c.company_n}</option>
                            ))}
                        </select>
                    </div>
                </form>
            </Modal>

            {/* Reset password */}
            <Modal
                open={modal?.mode === 'password'}
                onClose={() => setModal(null)}
                title={`Reset Password — ${record?.username || ''}`}
                footer={
                    <>
                        <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                        <button className="btn btn-primary" onClick={submit} disabled={changePassword.isPending}>
                            {changePassword.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />}
                            Simpan
                        </button>
                    </>
                }
            >
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                <form onSubmit={submit} className="grid grid-cols-1 gap-4">
                    <div>
                        <label className="field-label">Password Baru <span className="text-red-500">*</span></label>
                        <input className="field-input" type="password" value={pw.password ?? ''} onChange={(e) => setPw((v) => ({ ...v, password: e.target.value }))} />
                    </div>
                    <div>
                        <label className="field-label">Konfirmasi Password <span className="text-red-500">*</span></label>
                        <input className="field-input" type="password" value={pw.confirm ?? ''} onChange={(e) => setPw((v) => ({ ...v, confirm: e.target.value }))} />
                    </div>
                </form>
            </Modal>

            {/* Hak akses */}
            <Modal
                open={!!permData}
                onClose={() => setPermData(null)}
                title={`Hak Akses — ${permData?.user?.name || ''} (${permData?.user?.username || ''})`}
                wide
                footer={
                    <>
                        <button className="btn btn-ghost" onClick={() => setPermData(null)}>Batal</button>
                        <button className="btn btn-primary" onClick={savePerms} disabled={permSaving}>
                            {permSaving ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />}
                            Simpan Hak Akses
                        </button>
                    </>
                }
            >
                {permError && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{permError}</div>}
                <div className="max-h-[60vh] overflow-y-auto rounded-md border border-slate-200">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="sticky top-0 border-b border-slate-200 bg-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th className="px-3 py-2">Menu</th>
                                {FLAGS.map((f) => (
                                    <th key={f.key} className="px-2 py-2 text-center">{f.label}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {permData?.menus
                                .filter((m) => m.parent_id === 0)
                                .map((g) => {
                                    const children = permData.menus.filter((m) => m.parent_id === g.id);
                                    const groupOn = children.some((c) => permData.byMenu[c.id]?.can_view);
                                    return (
                                        <PermissionGroup
                                            key={g.id}
                                            group={g}
                                            children={children}
                                            byMenu={permData.byMenu}
                                            groupOn={groupOn}
                                            onGroup={(v) => setGroup(g, children, v)}
                                            onFlag={setFlag}
                                        />
                                    );
                                })}
                        </tbody>
                    </table>
                </div>
                <p className="mt-2 text-xs text-slate-400">Centang "Lihat" pada menu induk agar seluruh kelompok menu muncul di sidebar.</p>
            </Modal>
        </div>
    );
}

function PermissionGroup({ group, children, byMenu, groupOn, onGroup, onFlag }) {
    const flags = byMenu[group.id] || EMPTY_PERMS;
    return (
        <>
            <tr className="border-t border-b border-slate-200 bg-slate-50/60">
                <td className="px-3 py-2">
                    <label className="inline-flex cursor-pointer items-center gap-2">
                        <input type="checkbox" checked={Boolean(groupOn)} onChange={(e) => onGroup(e.target.checked)} />
                        <span className="font-semibold text-slate-700">{group.name}</span>
                    </label>
                </td>
                {FLAGS.map((f) => (
                    <td key={f.key} className="px-2 py-2 text-center">
                        <input type="checkbox" checked={Boolean(flags[f.key])} onChange={(e) => onFlag(group.id, f.key, e.target.checked)} />
                    </td>
                ))}
            </tr>
            {children.map((c) => {
                const cf = byMenu[c.id] || EMPTY_PERMS;
                return (
                    <tr key={c.id} className="border-b border-slate-100">
                        <td className="px-3 py-2 pl-8 text-slate-600">{c.name}</td>
                        {FLAGS.map((f) => (
                            <td key={f.key} className="px-2 py-2 text-center">
                                <input type="checkbox" checked={Boolean(cf[f.key])} onChange={(e) => onFlag(c.id, f.key, e.target.checked)} />
                            </td>
                        ))}
                    </tr>
                );
            })}
        </>
    );
}