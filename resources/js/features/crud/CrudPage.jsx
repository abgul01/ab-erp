import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import { RESOURCES } from './resources';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import DynamicForm from '../../components/DynamicForm';
import Icon from '../../components/Icon';

function initialValues(fields, record) {
    const v = {};
    for (const f of fields) {
        if (record) v[f.name] = record[f.name];
        else if ('default' in f) v[f.name] = f.default;
        else v[f.name] = f.type === 'checkbox' ? false : '';
    }
    return v;
}

export default function CrudPage({ resourceKey }) {
    const cfg = RESOURCES[resourceKey];
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);

    const [q, setQ] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null); // { mode: 'create'|'edit', record }
    const [values, setValues] = useState({});
    const [error, setError] = useState('');

    const list = useQuery({
        queryKey: [resourceKey, { search, page }],
        queryFn: async () =>
            (await api.get(`/${resourceKey}`, { params: { q: search, page, per_page: 15 } })).data,
    });

    const save = useMutation({
        mutationFn: async (payload) => {
            if (modal.mode === 'edit') {
                return api.put(`/${resourceKey}/${modal.record.id}`, payload);
            }
            return api.post(`/${resourceKey}`, payload);
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: [resourceKey] });
            qc.invalidateQueries({ queryKey: ['options', resourceKey] });
            setModal(null);
        },
        onError: (e) => setError(apiError(e)),
    });

    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/${resourceKey}/${id}`),
        onSuccess: () => qc.invalidateQueries({ queryKey: [resourceKey] }),
        onError: (e) => alert(apiError(e)),
    });

    const openCreate = () => {
        setValues(initialValues(cfg.fields, null));
        setError('');
        setModal({ mode: 'create' });
    };
    const openEdit = (record) => {
        setValues(initialValues(cfg.fields, record));
        setError('');
        setModal({ mode: 'edit', record });
    };

    const submit = (e) => {
        e.preventDefault();
        setError('');
        save.mutate(values);
    };

    const rows = list.data?.data || [];
    const meta = list.data?.meta;

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold text-slate-800">{cfg.title}</h1>
                {can(resourceKey, 'create') && (
                    <button className="btn btn-primary" onClick={openCreate}>
                        <Icon name="plus" /> Tambah {cfg.singular}
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
                        placeholder="Cari…"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                </div>
                <button className="btn btn-ghost">Cari</button>
            </form>

            <DataTable
                columns={cfg.columns}
                rows={rows}
                meta={meta}
                loading={list.isLoading}
                onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can(resourceKey, 'edit') && (
                            <button className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}>
                                <Icon name="pencil" />
                            </button>
                        )}
                        {can(resourceKey, 'delete') && (
                            <button
                                className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                onClick={() => window.confirm('Hapus data ini?') && remove.mutate(row.id)}
                            >
                                <Icon name="trash" />
                            </button>
                        )}
                    </div>
                )}
            />

            <Modal
                open={!!modal}
                onClose={() => setModal(null)}
                wide
                title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} ${cfg.singular}`}
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
                <form onSubmit={submit}>
                    <DynamicForm fields={cfg.fields} values={values} onChange={(name, v) => setValues((prev) => ({ ...prev, [name]: v }))} />
                </form>
            </Modal>
        </div>
    );
}
