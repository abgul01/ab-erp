import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Icon from '../../components/Icon';
import { ItemSelect, Select, useOptions, money } from '../procurement/common';

const TABS = [
    ['where-used', 'Where Used'],
    ['compare', 'Bandingkan BOM'],
    ['copy', 'Salin BOM'],
];

/**
 * Engineering helpers around the bill of material: which products consume a
 * material, what changed between two revisions, and cloning a structure onto a
 * new item. These are read-mostly tools, so they sit apart from the item master
 * rather than crowding its BOM tab.
 */
export default function BomToolsPage() {
    const [tab, setTab] = useState('where-used');

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">Alat Bantu BOM</h1>
                <p className="text-xs text-slate-400">Where-used, perbandingan revisi, dan penyalinan struktur BOM antar item.</p>
            </div>

            <div className="mb-5 flex gap-1 border-b border-slate-200">
                {TABS.map(([k, label]) => (
                    <button key={k} onClick={() => setTab(k)}
                        className={`-mb-px border-b-2 px-4 py-2 text-sm ${tab === k ? 'border-blue-600 font-medium text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'where-used' && <WhereUsed />}
            {tab === 'compare' && <Compare />}
            {tab === 'copy' && <Copy />}
        </div>
    );
}

function WhereUsed() {
    const [itemId, setItemId] = useState('');
    const q = useQuery({
        queryKey: ['bom', 'where-used', itemId],
        queryFn: async () => (await api.get(`/bom-tools/where-used/${itemId}`)).data.data,
        enabled: !!itemId,
    });

    return (
        <div className="max-w-3xl space-y-4">
            <div>
                <label className="field-label">Material / komponen</label>
                <ItemSelect value={itemId} onChange={setItemId} placeholder="— pilih item —" />
            </div>

            {q.isLoading && <div className="text-slate-400">Memuat…</div>}
            {q.data && (
                <div className="card overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th className="px-4 py-3">Dipakai oleh</th>
                                <th className="px-4 py-3">BOM</th>
                                <th className="px-4 py-3">Perannya</th>
                            </tr>
                        </thead>
                        <tbody>
                            {q.data.length === 0 && <tr><td colSpan={3} className="px-4 py-8 text-center text-slate-400">Item ini belum dipakai di BOM mana pun.</td></tr>}
                            {q.data.map((r, i) => (
                                <tr key={i} className="border-b border-slate-100">
                                    <td className="px-4 py-2.5">{r.item_code}<div className="text-xs text-slate-400">{r.part_name}</div></td>
                                    <td className="px-4 py-2.5">#{r.bom_id}</td>
                                    <td className="px-4 py-2.5">
                                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{r.role === 'RM' ? 'Raw Material' : 'Part Material'}</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function Compare() {
    const [a, setA] = useState('');
    const [b, setB] = useState('');
    const boms = useOptions('bom-tools/list');

    const q = useQuery({
        queryKey: ['bom', 'compare', a, b],
        queryFn: async () => (await api.post('/bom-tools/compare', { bom_id_a: a, bom_id_b: b })).data.data,
        enabled: !!a && !!b,
    });

    return (
        <div className="max-w-4xl space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <div>
                    <label className="field-label">BOM A</label>
                    <Select value={a} onChange={setA} options={boms.data} getValue={(o) => o.id}
                        getLabel={(o) => `#${o.id} — ${o.item_code || ''}`} />
                </div>
                <div>
                    <label className="field-label">BOM B</label>
                    <Select value={b} onChange={setB} options={boms.data} getValue={(o) => o.id}
                        getLabel={(o) => `#${o.id} — ${o.item_code || ''}`} />
                </div>
            </div>

            {q.isError && <div className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{apiError(q.error)}</div>}
            {q.data && (
                <pre className="max-h-[32rem] overflow-auto rounded bg-slate-900 p-4 text-xs text-slate-100">
                    {JSON.stringify(q.data, null, 2)}
                </pre>
            )}
        </div>
    );
}

function Copy() {
    const can = useAuth((s) => s.can);
    const [source, setSource] = useState('');
    const [target, setTarget] = useState('');
    const boms = useOptions('bom-tools/list');

    const copy = useMutation({
        mutationFn: async () => (await api.post('/bom-tools/copy', { source_bom_id: source, target_item_id: target })).data.data,
        onSuccess: (d) => alert(`BOM disalin. BOM baru #${d.id}.`),
        onError: (e) => alert(apiError(e)),
    });

    return (
        <div className="max-w-xl space-y-4">
            <div>
                <label className="field-label">BOM sumber</label>
                <Select value={source} onChange={setSource} options={boms.data} getValue={(o) => o.id}
                    getLabel={(o) => `#${o.id} — ${o.item_code || ''}`} />
            </div>
            <div>
                <label className="field-label">Item tujuan</label>
                <ItemSelect value={target} onChange={setTarget} placeholder="— pilih item tujuan —" />
            </div>
            <button className="btn btn-primary" disabled={!can('bom-tools', 'create') || !source || !target || copy.isPending}
                onClick={() => window.confirm('Salin struktur BOM ini ke item tujuan?') && copy.mutate()}>
                <Icon name="save" /> {copy.isPending ? 'Menyalin…' : 'Salin BOM'}
            </button>
            <p className="text-xs text-slate-400">
                Penyalinan membuat BOM baru untuk item tujuan; BOM sumber tidak diubah.
            </p>
        </div>
    );
}
