import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { apiError } from '../../api/client';
import { useAuth } from '../../stores/auth';
import Modal from '../../components/Modal';
import Icon from '../../components/Icon';
import { Select, StatusBadge, LineTable, CellInput, useOptions, ItemSelect, VendorSelect, money } from '../procurement/common';

const period = () => {
    const d = new Date();
    return `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}`;
};

const COST_TYPE_LABEL = {
    MATERIAL: 'Material', LABOR: 'Upah', FOH: 'Overhead Pabrik', TOOLING: 'Tooling', OTHER: 'Lain-lain',
};

export default function NpdCostingPage() {
    const qc = useQueryClient();
    const can = useAuth((s) => s.can);
    const [projectId, setProjectId] = useState('');
    const [tab, setTab] = useState('bom');
    const [bomModal, setBomModal] = useState(null);
    const [costModal, setCostModal] = useState(null);
    const [partModal, setPartModal] = useState(null);
    const [quoteModal, setQuoteModal] = useState(null);
    const [error, setError] = useState('');

    const categories = useOptions('categories');
    const uoms = useOptions('uoms');

    const projects = useQuery({
        queryKey: ['npd-projects', 'costing'],
        queryFn: async () => (await api.get('/npd-projects', { params: { per_page: 200 } })).data.data,
    });
    const data = useQuery({
        queryKey: ['npd-costing', projectId],
        queryFn: async () => (await api.get(`/npd-costing/project/${projectId}`)).data.data,
        enabled: !!projectId,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['npd-costing'] });
    const onErr = (e) => setError(apiError(e));

    const saveBom = useMutation({
        mutationFn: async (payload) => (bomModal.id
            ? api.put(`/npd-costing/bom/${bomModal.id}`, payload)
            : api.post(`/npd-costing/project/${projectId}/bom`, payload)),
        onSuccess: () => { invalidate(); setBomModal(null); },
        onError: onErr,
    });
    const saveCost = useMutation({
        mutationFn: async (payload) => (costModal.id
            ? api.put(`/npd-costing/cost/${costModal.id}`, payload)
            : api.post(`/npd-costing/project/${projectId}/cost`, payload)),
        onSuccess: () => { invalidate(); setCostModal(null); },
        onError: onErr,
    });
    const costAction = useMutation({
        mutationFn: async ({ id, action, body }) => api.post(`/npd-costing/cost/${id}/${action}`, body || {}),
        onSuccess: () => { invalidate(); setQuoteModal(null); },
        onError: (e) => alert(apiError(e)),
    });
    const registerPart = useMutation({
        mutationFn: async (payload) => api.post(`/npd-costing/project/${projectId}/register-part`, payload),
        onSuccess: () => { invalidate(); qc.invalidateQueries({ queryKey: ['npd-projects'] }); setPartModal(null); },
        onError: onErr,
    });

    const project = data.data?.project;
    const boms = data.data?.boms || [];
    const costs = data.data?.costs || [];

    /* ---- BOM form ---- */
    const openBom = (bom) => {
        setError('');
        setBomModal(bom ? {
            id: bom.id, version: bom.version, effective_date: bom.effective_date?.slice(0, 10) || '', note: bom.note || '',
            lines: (bom.detail || []).map((l) => ({
                item_id: l.item_id || '', new_item_code: l.new_item_code || '', new_item_name: l.new_item_name || '',
                role: l.role, qty: l.qty, length_use: l.length_use || '', uom_id: l.uom_id || '',
                ven_id: l.ven_id || '', unit_cost: l.unit_cost, note: l.note || '',
            })),
        } : { version: `v${boms.length + 1}`, effective_date: '', note: '', lines: [] });
    };
    const setBomLine = (i, k, v) => setBomModal((m) => ({ ...m, lines: m.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)) }));

    /* ---- Cost form ---- */
    const openCost = (cost) => {
        setError('');
        setCostModal(cost ? {
            id: cost.id, period: cost.period, bom_id: cost.bom_id || '',
            overhead: cost.overhead, margin_pct: cost.margin_pct, note: cost.note || '',
            manual_lines: (cost.detail || []).filter((d) => ['TOOLING', 'OTHER'].includes(d.cost_type))
                .map((d) => ({ cost_type: d.cost_type, descrip: d.descrip, amount: d.amount, note: d.note || '' })),
        } : {
            version: `v${costs.length + 1}`, period: period(), bom_id: boms[0]?.id || '',
            overhead: 0, margin_pct: 15, note: '',
        });
    };

    return (
        <div className="p-6">
            <div className="mb-4">
                <h1 className="text-xl font-semibold text-slate-800">BOM &amp; Costing NPD</h1>
                <p className="text-sm text-slate-500">
                    Harga material diambil dari kesepakatan supplier, tarif upah &amp; overhead dari tarif biaya periode terpilih —
                    angkanya tidak diketik ulang, supaya estimasi dan COGM nanti berbicara dalam bahasa yang sama.
                </p>
            </div>

            <div className="mb-4 flex flex-wrap items-end gap-3">
                <div className="min-w-[22rem]">
                    <label className="field-label">Proyek NPD</label>
                    <Select value={projectId} onChange={(v) => { setProjectId(v); setError(''); }} options={projects.data}
                        getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.name}`} placeholder="— pilih proyek —" />
                </div>
                {project && !project.item_id && can('npd-costing', 'create') && (
                    <button className="btn btn-primary" onClick={() => { setError(''); setPartModal({ code: '', part_name: project.part_name, type: 'FG', category_id: '' }); }}>
                        <Icon name="plus" /> Daftarkan Part ke Master
                    </button>
                )}
                {project?.item && (
                    <span className="mb-2 rounded-md bg-emerald-50 px-2 py-1 text-xs text-emerald-700">
                        Part terdaftar: {project.item.code} · golongan <b>{project.item.type}</b>
                        {project.item.active ? '' : ' (non-aktif sampai serah terima)'}
                    </span>
                )}
            </div>

            {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

            {!projectId && <p className="rounded-md border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-400">Pilih proyek untuk melihat BOM dan estimasi biayanya.</p>}

            {projectId && (<>
                <div className="mb-4 flex gap-1.5 border-b border-slate-200 pb-1">
                    {[['bom', 'Preliminary BOM'], ['cost', 'Estimasi Biaya & Quotation']].map(([k, label]) => (
                        <button key={k} onClick={() => setTab(k)}
                            className={`rounded-t px-3 py-1.5 text-sm ${tab === k ? 'border-b-2 border-[var(--ftpi-primary)] font-semibold text-slate-800' : 'text-slate-500 hover:text-slate-700'}`}>
                            {label}
                        </button>
                    ))}
                </div>

                {/* ── BOM ── */}
                {tab === 'bom' && (<>
                    <div className="mb-2 flex justify-end">
                        {can('npd-costing', 'create') && <button className="btn btn-primary" onClick={() => openBom(null)}><Icon name="plus" /> BOM Baru</button>}
                    </div>
                    {boms.length === 0 && <p className="rounded-md border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-400">Belum ada preliminary BOM.</p>}
                    {boms.map((b) => (
                        <div key={b.id} className="mb-4 rounded-lg border border-slate-200 bg-white">
                            <div className="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                                <div className="text-sm">
                                    <span className="font-semibold text-slate-700">BOM {b.version}</span>
                                    <span className="ml-2"><StatusBadge status={b.status} /></span>
                                    {b.effective_date && <span className="ml-2 text-xs text-slate-500">berlaku {b.effective_date.slice(0, 10)}</span>}
                                </div>
                                {b.status === 'DRAFT' && can('npd-costing', 'edit') && (
                                    <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => openBom(b)}><Icon name="pencil" className="h-3.5 w-3.5" /> Ubah</button>
                                )}
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                        <th className="px-3 py-2">Material</th><th className="px-3 py-2">Golongan</th>
                                        <th className="px-3 py-2 text-right">Qty</th><th className="px-3 py-2 text-right">Panjang (mm)</th>
                                        <th className="px-3 py-2 text-right">Harga satuan</th><th className="px-3 py-2">Sumber harga</th>
                                    </tr></thead>
                                    <tbody>
                                        {(b.detail || []).map((l) => (
                                            <tr key={l.id} className="border-t border-slate-100">
                                                <td className="px-3 py-1.5">
                                                    {l.label}
                                                    {!l.item_id && <span className="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-700">part baru</span>}
                                                </td>
                                                <td className="px-3 py-1.5 text-slate-500">{l.role}</td>
                                                <td className="px-3 py-1.5 text-right">{money(l.qty)}</td>
                                                <td className="px-3 py-1.5 text-right text-slate-500">{l.length_use ? money(l.length_use) : '—'}</td>
                                                <td className="px-3 py-1.5 text-right">{money(l.unit_cost)}</td>
                                                <td className="px-3 py-1.5 text-xs text-slate-400">
                                                    {l.cost_source === 'SUPPLIER' ? 'supplier prioritas' : l.cost_source === 'ITEM' ? 'harga pembelian' : 'diisi manual'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ))}
                </>)}

                {/* ── Costing ── */}
                {tab === 'cost' && (<>
                    <div className="mb-2 flex justify-end">
                        {can('npd-costing', 'create') && (
                            <button className="btn btn-primary" disabled={boms.length === 0} title={boms.length === 0 ? 'Buat preliminary BOM dulu' : undefined}
                                onClick={() => openCost(null)}><Icon name="plus" /> Estimasi Baru</button>
                        )}
                    </div>
                    {costs.length === 0 && <p className="rounded-md border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-400">Belum ada estimasi biaya.</p>}
                    {costs.map((c) => (
                        <div key={c.id} className="mb-4 rounded-lg border border-slate-200 bg-white">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
                                <div className="text-sm">
                                    <span className="font-semibold text-slate-700">Estimasi {c.version}</span>
                                    <span className="ml-2"><StatusBadge status={c.status} /></span>
                                    <span className="ml-2 text-xs text-slate-500">tarif periode {c.period}</span>
                                    {c.pricelist_det_id && <span className="ml-2 rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] text-emerald-700">sudah jadi pricelist</span>}
                                </div>
                                <div className="flex gap-1">
                                    {['DRAFT', 'REJECTED'].includes(c.status) && can('npd-costing', 'edit') && (<>
                                        <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => openCost(c)}><Icon name="pencil" className="h-3.5 w-3.5" /> Ubah</button>
                                        <button className="btn btn-ghost px-2 py-1 text-xs" onClick={() => costAction.mutate({ id: c.id, action: 'recalculate' })}><Icon name="calculator" className="h-3.5 w-3.5" /> Hitung ulang</button>
                                        <button className="btn btn-primary px-2 py-1 text-xs" onClick={() => costAction.mutate({ id: c.id, action: 'submit' })}><Icon name="send" className="h-3.5 w-3.5" /> Ajukan</button>
                                    </>)}
                                    {c.status === 'SUBMITTED' && can('npd-costing', 'edit') && (<>
                                        <button className="btn btn-ghost px-2 py-1 text-xs text-emerald-700" onClick={() => costAction.mutate({ id: c.id, action: 'approve' })}><Icon name="check" className="h-3.5 w-3.5" /> Setujui</button>
                                        <button className="btn btn-ghost px-2 py-1 text-xs text-red-700" onClick={() => {
                                            const note = window.prompt('Alasan penolakan:');
                                            if (note) costAction.mutate({ id: c.id, action: 'reject', body: { note } });
                                        }}><Icon name="ban" className="h-3.5 w-3.5" /> Tolak</button>
                                    </>)}
                                    {c.status === 'APPROVED' && !c.pricelist_det_id && can('npd-costing', 'edit') && (
                                        <button className="btn btn-primary px-2 py-1 text-xs"
                                            onClick={() => { setError(''); setQuoteModal({ id: c.id, version: c.version, price: c.quoted_price, valid_from: '', valid_to: '', min_qty: 1 }); }}>
                                            <Icon name="coins" className="h-3.5 w-3.5" /> Jadikan Pricelist
                                        </button>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3 px-3 py-3 text-sm lg:grid-cols-6">
                                <div><p className="text-xs text-slate-400">Material</p><p>{money(c.material_cost)}</p></div>
                                <div><p className="text-xs text-slate-400">Proses</p><p>{money(c.process_cost)}</p></div>
                                <div><p className="text-xs text-slate-400">Tooling</p><p>{money(c.tooling_cost)}</p></div>
                                <div><p className="text-xs text-slate-400">Overhead</p><p>{money(c.overhead)}</p></div>
                                <div><p className="text-xs text-slate-400">Total biaya</p><p className="font-medium">{money(c.total_cost)}</p></div>
                                <div><p className="text-xs text-slate-400">Harga penawaran (margin {c.margin_pct}%)</p><p className="font-semibold text-slate-800">{money(c.quoted_price)}</p></div>
                            </div>

                            <div className="overflow-x-auto border-t border-slate-100">
                                <table className="w-full text-sm">
                                    <thead><tr className="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                        <th className="px-3 py-2">Jenis</th><th className="px-3 py-2">Rincian</th>
                                        <th className="px-3 py-2 text-right">Cycle (dtk)</th><th className="px-3 py-2 text-right">Tarif</th>
                                        <th className="px-3 py-2 text-right">Jumlah</th><th className="px-3 py-2">Catatan</th>
                                    </tr></thead>
                                    <tbody>
                                        {(c.detail || []).length === 0 && <tr><td colSpan={6} className="px-3 py-4 text-center text-slate-400">Belum ada rincian.</td></tr>}
                                        {(c.detail || []).map((d) => (
                                            <tr key={d.id} className="border-t border-slate-100">
                                                <td className="px-3 py-1.5 text-slate-600">{COST_TYPE_LABEL[d.cost_type] || d.cost_type}</td>
                                                <td className="px-3 py-1.5">{d.descrip}</td>
                                                <td className="px-3 py-1.5 text-right text-slate-500">{d.cycle_sec ? money(d.cycle_sec) : '—'}</td>
                                                <td className="px-3 py-1.5 text-right">{money(d.rate)}</td>
                                                <td className="px-3 py-1.5 text-right font-medium">{money(d.amount)}</td>
                                                <td className="px-3 py-1.5 text-xs text-slate-400">{d.note || '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {c.process_cost === 0 && (
                                <p className="border-t border-slate-100 px-3 py-2 text-xs text-amber-700">
                                    Biaya proses masih nol — part belum terdaftar, cycle time belum diisi, atau tarif periode {c.period} belum ada.
                                </p>
                            )}
                        </div>
                    ))}
                </>)}
            </>)}

            {/* ── Daftarkan part ── */}
            <Modal open={!!partModal} onClose={() => setPartModal(null)} title="Daftarkan Part ke Master Item"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setPartModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={registerPart.isPending} onClick={() => { setError(''); registerPart.mutate(partModal); }}>
                        {registerPart.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Daftarkan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {partModal && (
                    <div className="space-y-3">
                        <p className="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Part didaftarkan sebagai item <b>non-aktif</b>: sudah bisa dipakai Work Order trial, tapi belum masuk perencanaan produksi.
                            Handover nanti yang melengkapinya dan mengaktifkannya.
                        </p>
                        <div><label className="field-label">Kode item <span className="text-red-500">*</span></label>
                            <input className="field-input" value={partModal.code} maxLength={50} onChange={(e) => setPartModal({ ...partModal, code: e.target.value })} /></div>
                        <div><label className="field-label">Nama part</label>
                            <input className="field-input" value={partModal.part_name} maxLength={50} onChange={(e) => setPartModal({ ...partModal, part_name: e.target.value })} /></div>
                        <div><label className="field-label">Golongan <span className="text-red-500">*</span></label>
                            <select className="field-input" value={partModal.type} onChange={(e) => setPartModal({ ...partModal, type: e.target.value })}>
                                <option value="FG">FG — barang jadi, direncanakan &amp; dijual</option>
                                <option value="PM">PM — komponen, masuk BOM barang lain</option>
                                <option value="RM">RM — bahan baku, dibeli per batang</option>
                            </select>
                            <p className="mt-0.5 text-[11px] text-slate-400">
                                Menentukan perlakuan seluruh sistem: hanya FG yang masuk rencana bulanan dan sales order.
                                Proyek modifikasi sering melahirkan komponen (PM), bukan produk baru.
                            </p></div>
                        <div><label className="field-label">Kategori <span className="text-red-500">*</span></label>
                            <Select value={partModal.category_id} onChange={(v) => setPartModal({ ...partModal, category_id: v })} options={categories.data}
                                getValue={(o) => o.id} getLabel={(o) => o.name_c} placeholder="— pilih kategori —" /></div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="field-label">OD (mm)</label><input type="number" step="0.01" className="field-input" value={partModal.o_d || ''} onChange={(e) => setPartModal({ ...partModal, o_d: e.target.value })} /></div>
                            <div><label className="field-label">Tebal (mm)</label><input type="number" step="0.01" className="field-input" value={partModal.thick || ''} onChange={(e) => setPartModal({ ...partModal, thick: e.target.value })} /></div>
                            <div><label className="field-label">Panjang (mm)</label><input type="number" step="0.01" className="field-input" value={partModal.length || ''} onChange={(e) => setPartModal({ ...partModal, length: e.target.value })} /></div>
                            <div><label className="field-label">Berat (kg)</label><input type="number" step="0.001" className="field-input" value={partModal.weight || ''} onChange={(e) => setPartModal({ ...partModal, weight: e.target.value })} /></div>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ── BOM form ── */}
            <Modal open={!!bomModal} onClose={() => setBomModal(null)} wide title={bomModal?.id ? `Ubah BOM ${bomModal.version}` : 'Preliminary BOM Baru'}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setBomModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={saveBom.isPending} onClick={() => { setError(''); saveBom.mutate(bomModal); }}>
                        {saveBom.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {bomModal && (<>
                    <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div><label className="field-label">Versi <span className="text-red-500">*</span></label>
                            <input className="field-input" value={bomModal.version} maxLength={20} onChange={(e) => setBomModal({ ...bomModal, version: e.target.value })} /></div>
                        <div><label className="field-label">Berlaku mulai</label>
                            <input type="date" className="field-input" value={bomModal.effective_date} onChange={(e) => setBomModal({ ...bomModal, effective_date: e.target.value })} /></div>
                        <div><label className="field-label">Catatan</label>
                            <input className="field-input" value={bomModal.note} maxLength={300} onChange={(e) => setBomModal({ ...bomModal, note: e.target.value })} /></div>
                    </div>

                    <LineTable title="Material" subtitle="Material yang belum ada di master cukup diisi kode & namanya — pada fase desain itu memang belum terdaftar."
                        onAdd={() => setBomModal({ ...bomModal, lines: [...bomModal.lines, { item_id: '', new_item_code: '', new_item_name: '', role: 'RM', qty: 1, length_use: '', uom_id: '', ven_id: '', unit_cost: 0, note: '' }] })}
                        lines={bomModal.lines} empty="Belum ada material."
                        head={['Item master', 'atau part baru', 'Golongan', 'Qty', 'Panjang (mm)', 'Supplier', 'Harga', '']}
                        row={(l, i) => (<>
                            <td className="min-w-[220px] px-2 py-1.5"><ItemSelect value={l.item_id} onChange={(v) => setBomLine(i, 'item_id', v)} placeholder="— belum ada —" /></td>
                            <td className="min-w-[200px] px-2 py-1.5">
                                <CellInput value={l.new_item_name} onChange={(v) => setBomLine(i, 'new_item_name', v)} />
                            </td>
                            <td className="w-28 px-2 py-1.5">
                                {l.item_id ? (
                                    // Golongan diambil dari master, bukan dipilih di sini.
                                    <span className="text-xs text-slate-500">ikut golongan item</span>
                                ) : (
                                    <select className="field-input" value={l.role} onChange={(e) => setBomLine(i, 'role', e.target.value)}>
                                        <option value="RM">RM</option><option value="PM">PM</option>
                                    </select>
                                )}
                            </td>
                            <td className="w-24 px-2 py-1.5"><CellInput type="number" step="0.001" value={l.qty} onChange={(v) => setBomLine(i, 'qty', v)} /></td>
                            <td className="w-28 px-2 py-1.5"><CellInput type="number" step="0.01" value={l.length_use} onChange={(v) => setBomLine(i, 'length_use', v)} /></td>
                            <td className="min-w-[170px] px-2 py-1.5"><VendorSelect value={l.ven_id} onChange={(v) => setBomLine(i, 'ven_id', v)} /></td>
                            <td className="w-28 px-2 py-1.5"><CellInput type="number" step="0.01" value={l.unit_cost} onChange={(v) => setBomLine(i, 'unit_cost', v)} /></td>
                            <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50"
                                onClick={() => setBomModal({ ...bomModal, lines: bomModal.lines.filter((_, j) => j !== i) })}><Icon name="trash" /></button></td>
                        </>)} />
                    <p className="-mt-3 text-xs text-slate-400">
                        Harga material akan ditimpa harga supplier prioritas saat estimasi dihitung, kecuali untuk part yang belum terdaftar.
                    </p>
                </>)}
            </Modal>

            {/* ── Cost form ── */}
            <Modal open={!!costModal} onClose={() => setCostModal(null)} wide title={costModal?.id ? 'Ubah Estimasi Biaya' : 'Estimasi Biaya Baru'}
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setCostModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={saveCost.isPending} onClick={() => { setError(''); saveCost.mutate(costModal); }}>
                        {saveCost.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="save" />} Simpan &amp; Hitung
                    </button>
                </>}>
                {error && <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                {costModal && (<>
                    <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        {!costModal.id && (
                            <div><label className="field-label">Versi <span className="text-red-500">*</span></label>
                                <input className="field-input" value={costModal.version} maxLength={20} onChange={(e) => setCostModal({ ...costModal, version: e.target.value })} /></div>
                        )}
                        <div><label className="field-label">Periode tarif (YYYYMM) <span className="text-red-500">*</span></label>
                            <input className="field-input" value={costModal.period} onChange={(e) => setCostModal({ ...costModal, period: e.target.value.replace(/\D/g, '').slice(0, 6) })} />
                            <p className="mt-0.5 text-[11px] text-slate-400">Tarif dibekukan di rinciannya.</p></div>
                        <div><label className="field-label">BOM dipakai</label>
                            <Select value={costModal.bom_id} onChange={(v) => setCostModal({ ...costModal, bom_id: v })} options={boms}
                                getValue={(o) => o.id} getLabel={(o) => `${o.version} (${o.status})`} placeholder="BOM terbaru" /></div>
                        <div><label className="field-label">Margin (%)</label>
                            <input type="number" step="0.01" className="field-input" value={costModal.margin_pct} onChange={(e) => setCostModal({ ...costModal, margin_pct: e.target.value })} /></div>
                        <div><label className="field-label">Overhead (Rp)</label>
                            <input type="number" step="0.01" className="field-input" value={costModal.overhead} onChange={(e) => setCostModal({ ...costModal, overhead: e.target.value })} /></div>
                        <div className="sm:col-span-3"><label className="field-label">Catatan</label>
                            <input className="field-input" value={costModal.note} maxLength={300} onChange={(e) => setCostModal({ ...costModal, note: e.target.value })} /></div>
                    </div>

                    {costModal.id && (
                        <LineTable title="Tooling & biaya lain" subtitle="Hanya bagian ini yang diketik manual — tooling dicatat sebagai biaya proyek, bukan aset tetap."
                            onAdd={() => setCostModal({ ...costModal, manual_lines: [...(costModal.manual_lines || []), { cost_type: 'TOOLING', descrip: '', amount: 0, note: '' }] })}
                            lines={costModal.manual_lines || []} empty="Belum ada biaya tooling atau lain-lain."
                            head={['Jenis', 'Rincian', 'Jumlah', 'Catatan', '']}
                            row={(l, i) => (<>
                                <td className="w-36 px-2 py-1.5">
                                    <select className="field-input" value={l.cost_type}
                                        onChange={(e) => setCostModal({ ...costModal, manual_lines: costModal.manual_lines.map((x, j) => (j === i ? { ...x, cost_type: e.target.value } : x)) })}>
                                        <option value="TOOLING">Tooling</option><option value="OTHER">Lain-lain</option>
                                    </select>
                                </td>
                                <td className="px-2 py-1.5"><CellInput value={l.descrip}
                                    onChange={(v) => setCostModal({ ...costModal, manual_lines: costModal.manual_lines.map((x, j) => (j === i ? { ...x, descrip: v } : x)) })} /></td>
                                <td className="w-32 px-2 py-1.5"><CellInput type="number" step="0.01" value={l.amount}
                                    onChange={(v) => setCostModal({ ...costModal, manual_lines: costModal.manual_lines.map((x, j) => (j === i ? { ...x, amount: v } : x)) })} /></td>
                                <td className="px-2 py-1.5"><CellInput value={l.note}
                                    onChange={(v) => setCostModal({ ...costModal, manual_lines: costModal.manual_lines.map((x, j) => (j === i ? { ...x, note: v } : x)) })} /></td>
                                <td className="px-2 py-1.5"><button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50"
                                    onClick={() => setCostModal({ ...costModal, manual_lines: costModal.manual_lines.filter((_, j) => j !== i) })}><Icon name="trash" /></button></td>
                            </>)} />
                    )}
                </>)}
            </Modal>

            {/* ── Jadikan pricelist ── */}
            <Modal open={!!quoteModal} onClose={() => setQuoteModal(null)} title="Jadikan Pricelist Pelanggan"
                footer={<>
                    <button className="btn btn-ghost" onClick={() => setQuoteModal(null)}>Batal</button>
                    <button className="btn btn-primary" disabled={costAction.isPending}
                        onClick={() => costAction.mutate({ id: quoteModal.id, action: 'to-pricelist', body: quoteModal })}>
                        {costAction.isPending ? <Icon name="spinner" className="h-4 w-4 animate-spin" /> : <Icon name="check" />} Catat Harga
                    </button>
                </>}>
                {quoteModal && (
                    <div className="space-y-3">
                        <p className="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Harga <b>{money(quoteModal.price)}</b> dari estimasi {quoteModal.version} akan dicatat sebagai pricelist <b>DRAFT</b> —
                            masih melewati approval pricelist seperti harga lainnya.
                        </p>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="field-label">Berlaku dari</label>
                                <input type="date" className="field-input" value={quoteModal.valid_from} onChange={(e) => setQuoteModal({ ...quoteModal, valid_from: e.target.value })} /></div>
                            <div><label className="field-label">Berlaku sampai</label>
                                <input type="date" className="field-input" value={quoteModal.valid_to} onChange={(e) => setQuoteModal({ ...quoteModal, valid_to: e.target.value })} /></div>
                            <div><label className="field-label">Qty minimum</label>
                                <input type="number" className="field-input" value={quoteModal.min_qty} onChange={(e) => setQuoteModal({ ...quoteModal, min_qty: e.target.value })} /></div>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
