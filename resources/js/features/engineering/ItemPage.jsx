import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import DataTable from '../../components/DataTable';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';

const TYPES = ['Pipe', 'Roundbar', 'Square Pipe', 'Square Bar', 'Plat Bar', 'Other'];

const EMPTY = {
    code: '', part_name: '', type: '', descrip: '', category_id: '', pm: false, active: true,
    o_d: '', i_d: '', thick: '', width: '', height: '', length: '', length_cut: '',
    weight: '', tolerance: '', min_stock: '', max_stock: '',
    rm_lines: [], pm_lines: [], processes: [], customers: [],
};

const TABS = [
    { key: 'main', label: 'Main' },
    { key: 'detail', label: 'Detail' },
    { key: 'bom', label: 'BOM (FG)' },
    { key: 'process', label: 'Proses (FG)' },
    { key: 'customer', label: 'Customer (FG)' },
];

/* ---------- option hooks ---------- */
function useOptions(resource) {
    return useQuery({
        queryKey: ['options', resource],
        queryFn: async () => (await api.get(`/${resource}`, { params: { per_page: 500 } })).data.data,
        staleTime: 60_000,
    });
}

function Select({ value, onChange, options, getValue, getLabel, placeholder }) {
    return (
        <select
            className="field-input"
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value === '' ? '' : Number(e.target.value))}
        >
            <option value="">{placeholder || '— pilih —'}</option>
            {(options || []).map((o) => (
                <option key={getValue(o)} value={getValue(o)}>{getLabel(o)}</option>
            ))}
        </select>
    );
}

function ItemSelect({ value, onChange, placeholder, filter }) {
    const { data } = useOptions('items');
    const options = filter ? (data || []).filter(filter) : data;
    return <Select value={value} onChange={onChange} options={options}
        getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.part_name}`} placeholder={placeholder || '— pilih item —'} />;
}

/* ---------- small field helpers ---------- */
function Text({ label, value, onChange, required, type = 'text', step, span }) {
    return (
        <div className={span === 2 ? 'sm:col-span-2' : ''}>
            <label className="field-label">{label} {required && <span className="text-red-500">*</span>}</label>
            <input
                type={type} step={step} className="field-input"
                value={value ?? ''}
                onChange={(e) => onChange(type === 'number' ? (e.target.value === '' ? '' : e.target.value) : e.target.value)}
            />
        </div>
    );
}

function Check({ label, value, onChange }) {
    return (
        <label className="mt-6 inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" checked={Boolean(value)} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4" />
            {label}
        </label>
    );
}

function LineTable({ title, subtitle, onAdd, lines, head, row, empty }) {
    return (
        <div className="mb-5">
            <div className="mb-2 flex items-center justify-between">
                <div>
                    <h4 className="text-sm font-semibold text-slate-700">{title}</h4>
                    {subtitle && <p className="text-xs text-slate-400">{subtitle}</p>}
                </div>
                <button type="button" className="btn btn-ghost px-2 py-1 text-xs" onClick={onAdd}>
                    <Icon name="plus" className="h-3.5 w-3.5" /> Baris
                </button>
            </div>
            <div className="overflow-x-auto rounded-md border border-slate-200">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            {head.map((h, i) => <th key={i} className="px-2 py-2">{h}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {lines.length === 0 && (
                            <tr><td colSpan={head.length} className="px-2 py-4 text-center text-slate-400">{empty}</td></tr>
                        )}
                        {lines.map((l, i) => <tr key={i} className="border-t border-slate-100">{row(l, i)}</tr>)}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/* ---------- columns for the list ---------- */
const COLUMNS = [
    { key: 'code', label: 'Kode' },
    { key: 'part_name', label: 'Nama Part' },
    { key: 'type', label: 'Tipe' },
    { key: 'pm', label: 'PM', render: (v) => (v ? 'Ya' : '') },
    { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
];

export default function ItemPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);

    const [q, setQ] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null); // { mode, id }
    const [tab, setTab] = useState('main');
    const [form, setForm] = useState(EMPTY);
    const [error, setError] = useState('');

    const categories = useOptions('categories');
    const processes = useOptions('processes');
    const contacts = useOptions('contacts');

    // Group is a fixed domain: Material / FG (stored in m_i_category / category_id).
    const GROUP_ORDER = { Material: 0, FG: 1 };
    const groupOptions = (categories.data || [])
        .filter((c) => c.name_c in GROUP_ORDER)
        .sort((a, b) => GROUP_ORDER[a.name_c] - GROUP_ORDER[b.name_c]);
    const materialGroupId = groupOptions.find((g) => g.name_c === 'Material')?.id;

    // BOM pickers: RM = items in the Material group; PM = items flagged pm.
    const isMaterial = (o) => materialGroupId != null && Number(o.category_id) === Number(materialGroupId);
    const isPm = (o) => !!o.pm;

    const list = useQuery({
        queryKey: ['items', { search, page }],
        queryFn: async () => (await api.get('/items', { params: { q: search, page, per_page: 15 } })).data,
    });

    const save = useMutation({
        mutationFn: async (payload) =>
            modal.mode === 'edit' ? api.put(`/items/${modal.id}`, payload) : api.post('/items', payload),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['items'] });
            qc.invalidateQueries({ queryKey: ['options', 'items'] });
            setModal(null);
        },
        onError: (e) => setError(apiError(e)),
    });

    const remove = useMutation({
        mutationFn: async (id) => api.delete(`/items/${id}`),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['items'] }),
        onError: (e) => alert(apiError(e)),
    });

    const openCreate = () => {
        setForm(EMPTY);
        setTab('main');
        setError('');
        setModal({ mode: 'create' });
    };

    const openEdit = async (row) => {
        setError('');
        setTab('main');
        const { data } = await api.get(`/items/${row.id}`);
        const it = data.data;
        setForm({
            ...EMPTY,
            ...Object.fromEntries(Object.keys(EMPTY).map((k) => [k, it[k] ?? EMPTY[k]])),
            pm: !!it.pm,
            active: !!it.active,
            rm_lines: (it.rm_lines || []).map((l) => ({ mat_id: l.mat_id, length_cut: l.length_cut, length_use: l.length_use, priority: l.priority })),
            pm_lines: (it.pm_lines || []).map((l) => ({ pm_id: l.pm_id, qty: l.qty })),
            processes: (it.processes || []).map((p) => ({ proc_id: p.proc_id, sequence: p.sequence })),
            customers: (it.customers || []).map((c) => ({
                cus_id: c.cus_id, priority: c.priority, active: c.active === undefined ? true : !!c.active,
            })),
        });
        setModal({ mode: 'edit', id: row.id });
    };

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const submit = () => { setError(''); save.mutate(form); };

    /* line editors */
    const addRm = () => set('rm_lines', [...form.rm_lines, { mat_id: '', length_cut: '', length_use: '', priority: form.rm_lines.length + 1 }]);
    const addPm = () => set('pm_lines', [...form.pm_lines, { pm_id: '', qty: 1 }]);
    const addProc = () => set('processes', [...form.processes, { proc_id: '', sequence: form.processes.length + 1 }]);
    const addCus = () => set('customers', [...form.customers, { cus_id: '', priority: form.customers.length + 1, active: true }]);
    const setLine = (key, i, k, v) => set(key, form[key].map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const delLine = (key, i) => set(key, form[key].filter((_, j) => j !== i));

    return (
        <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold text-slate-800">Item Master</h1>
                {can('items', 'create') && (
                    <button className="btn btn-primary" onClick={openCreate}><Icon name="plus" /> Tambah Item</button>
                )}
            </div>

            <form
                onSubmit={(e) => { e.preventDefault(); setPage(1); setSearch(q); }}
                className="mb-4 flex max-w-md items-center gap-2"
            >
                <div className="relative flex-1">
                    <Icon name="search" className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input className="field-input pl-9" placeholder="Cari…" value={q} onChange={(e) => setQ(e.target.value)} />
                </div>
                <button className="btn btn-ghost">Cari</button>
            </form>

            <DataTable
                columns={COLUMNS}
                rows={list.data?.data || []}
                meta={list.data?.meta}
                loading={list.isLoading}
                onPageChange={setPage}
                actions={(row) => (
                    <div className="flex justify-end gap-1">
                        {can('items', 'edit') && (
                            <button className="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-700" onClick={() => openEdit(row)}>
                                <Icon name="pencil" />
                            </button>
                        )}
                        {can('items', 'delete') && (
                            <button className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" onClick={() => window.confirm('Hapus item ini?') && remove.mutate(row.id)}>
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
                title={`${modal?.mode === 'edit' ? 'Edit' : 'Tambah'} Item`}
                footer={
                    <>
                        <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                        <button className="btn btn-primary" onClick={submit} disabled={save.isPending}>
                            {save.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                        </button>
                    </>
                }
            >
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                {/* Tabs */}
                <div className="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
                    {TABS.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            onClick={() => setTab(t.key)}
                            className={`-mb-px border-b-2 px-3 py-2 text-sm font-medium transition ${
                                tab === t.key
                                    ? 'border-blue-600 text-blue-700'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                {/* Tab 1: Main */}
                {tab === 'main' && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Text label="Part Number (Kode)" value={form.code} onChange={(v) => set('code', v)} required />
                        <Text label="Part Name" value={form.part_name} onChange={(v) => set('part_name', v)} required />
                        <div>
                            <label className="field-label">Type <span className="text-red-500">*</span></label>
                            <select className="field-input" value={form.type} onChange={(e) => set('type', e.target.value)}>
                                <option value="">— pilih —</option>
                                {TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="field-label">Group <span className="text-red-500">*</span></label>
                            <Select value={form.category_id} onChange={(v) => set('category_id', v)} options={groupOptions}
                                getValue={(o) => o.id} getLabel={(o) => o.name_c} placeholder="— pilih Group (Material / FG) —" />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="field-label">Description</label>
                            <textarea className="field-input" rows={2} value={form.descrip ?? ''} onChange={(e) => set('descrip', e.target.value)} />
                        </div>
                        <Check label="PM (Part Material)" value={form.pm} onChange={(v) => set('pm', v)} />
                        <Check label="Active" value={form.active} onChange={(v) => set('active', v)} />
                    </div>
                )}

                {/* Tab 2: Detail */}
                {tab === 'detail' && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Text label="Outer Diameter — o_d (mm)" type="number" step="0.01" value={form.o_d} onChange={(v) => set('o_d', v)} />
                        <Text label="Inner Diameter — i_d (mm)" type="number" step="0.01" value={form.i_d} onChange={(v) => set('i_d', v)} />
                        <Text label="Thickness — thick (mm)" type="number" step="0.01" value={form.thick} onChange={(v) => set('thick', v)} />
                        <Text label="Width (mm)" type="number" step="0.01" value={form.width} onChange={(v) => set('width', v)} />
                        <Text label="Height (mm)" type="number" step="0.01" value={form.height} onChange={(v) => set('height', v)} />
                        <Text label="Length (mm)" type="number" step="0.01" value={form.length} onChange={(v) => set('length', v)} />
                        <Text label="Length Cut (mm)" type="number" step="0.01" value={form.length_cut} onChange={(v) => set('length_cut', v)} />
                        <Text label="Weight (kg)" type="number" step="0.01" value={form.weight} onChange={(v) => set('weight', v)} />
                        <Text label="Tolerance" value={form.tolerance} onChange={(v) => set('tolerance', v)} />
                        <div className="hidden sm:block" />
                        <Text label="Min Stock" type="number" value={form.min_stock} onChange={(v) => set('min_stock', v)} />
                        <Text label="Max Stock" type="number" value={form.max_stock} onChange={(v) => set('max_stock', v)} />
                    </div>
                )}

                {/* Tab 3: BOM */}
                {tab === 'bom' && (
                    <div>
                        <p className="mb-4 text-xs text-slate-400">Komposisi BOM untuk item FG. Bagian atas Raw Material, bagian bawah Part Material.</p>
                        <LineTable
                            title="Raw Material (RM)"
                            onAdd={addRm}
                            empty="Belum ada komponen RM."
                            lines={form.rm_lines}
                            head={['Material', 'Length Cut (mm)', 'Length Use (mm)', 'Prioritas', '']}
                            row={(l, i) => (
                                <>
                                    <td className="min-w-[220px] px-2 py-1.5"><ItemSelect value={l.mat_id} onChange={(v) => setLine('rm_lines', i, 'mat_id', v)} filter={isMaterial} placeholder="— pilih material —" /></td>
                                    <td className="px-2 py-1.5"><input type="number" step="0.01" className="field-input" value={l.length_cut ?? ''} onChange={(e) => setLine('rm_lines', i, 'length_cut', e.target.value)} /></td>
                                    <td className="px-2 py-1.5"><input type="number" step="0.01" className="field-input" value={l.length_use ?? ''} onChange={(e) => setLine('rm_lines', i, 'length_use', e.target.value)} /></td>
                                    <td className="w-24 px-2 py-1.5"><input type="number" className="field-input" value={l.priority ?? ''} onChange={(e) => setLine('rm_lines', i, 'priority', e.target.value)} /></td>
                                    <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine('rm_lines', i)}><Icon name="trash" /></button></td>
                                </>
                            )}
                        />
                        <LineTable
                            title="Part Material (PM)"
                            subtitle="Bisa merujuk FG lain (multi-level)."
                            onAdd={addPm}
                            empty="Belum ada komponen PM."
                            lines={form.pm_lines}
                            head={['Part / PM', 'Qty', '']}
                            row={(l, i) => (
                                <>
                                    <td className="min-w-[220px] px-2 py-1.5"><ItemSelect value={l.pm_id} onChange={(v) => setLine('pm_lines', i, 'pm_id', v)} filter={isPm} placeholder="— pilih PM —" /></td>
                                    <td className="w-32 px-2 py-1.5"><input type="number" className="field-input" value={l.qty ?? ''} onChange={(e) => setLine('pm_lines', i, 'qty', e.target.value)} /></td>
                                    <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine('pm_lines', i)}><Icon name="trash" /></button></td>
                                </>
                            )}
                        />
                    </div>
                )}

                {/* Tab 4: Process */}
                {tab === 'process' && (
                    <LineTable
                        title="Urutan Proses (Routing FG)"
                        subtitle="Urutan proses produksi untuk item FG ini."
                        onAdd={addProc}
                        empty="Belum ada proses."
                        lines={form.processes}
                        head={['Urutan', 'Proses', '']}
                        row={(l, i) => (
                            <>
                                <td className="w-24 px-2 py-1.5"><input type="number" className="field-input" value={l.sequence ?? ''} onChange={(e) => setLine('processes', i, 'sequence', e.target.value)} /></td>
                                <td className="min-w-[260px] px-2 py-1.5">
                                    <Select value={l.proc_id} onChange={(v) => setLine('processes', i, 'proc_id', v)} options={processes.data}
                                        getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name_p}`} placeholder="— pilih proses —" />
                                </td>
                                <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine('processes', i)}><Icon name="trash" /></button></td>
                            </>
                        )}
                    />
                )}

                {/* Tab 5: Customer */}
                {tab === 'customer' && (
                    <LineTable
                        title="Customer (FG)"
                        subtitle="Customer yang boleh menerima pengiriman item ini."
                        onAdd={addCus}
                        empty="Belum ada customer."
                        lines={form.customers}
                        head={['Customer', 'Prioritas', 'Aktif', '']}
                        row={(l, i) => (
                            <>
                                <td className="min-w-[260px] px-2 py-1.5">
                                    <Select value={l.cus_id} onChange={(v) => setLine('customers', i, 'cus_id', v)} options={contacts.data}
                                        getValue={(o) => o.id} getLabel={(o) => o.company_n} placeholder="— pilih customer —" />
                                </td>
                                <td className="w-28 px-2 py-1.5"><input type="number" className="field-input" value={l.priority ?? ''} onChange={(e) => setLine('customers', i, 'priority', e.target.value)} /></td>
                                <td className="px-2 py-1.5 text-center"><input type="checkbox" className="h-4 w-4" checked={!!l.active} onChange={(e) => setLine('customers', i, 'active', e.target.checked)} /></td>
                                <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={() => delLine('customers', i)}><Icon name="trash" /></button></td>
                            </>
                        )}
                    />
                )}
            </Modal>
        </div>
    );
}
