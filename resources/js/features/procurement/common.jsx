import { useQuery } from '@tanstack/react-query';
import api from '../../api/client';
import Icon from '../../components/Icon';

/** Async option list from any endpoint (cached). */
export function useOptions(resource, params = {}) {
    return useQuery({
        queryKey: ['options', resource, params],
        queryFn: async () => (await api.get(`/${resource}`, { params: { per_page: 500, ...params } })).data.data,
        staleTime: 60_000,
    });
}

/** Generic <select> driven by an options array. */
export function Select({ value, onChange, options, getValue, getLabel, placeholder, disabled }) {
    return (
        <select
            className="field-input"
            value={value ?? ''}
            disabled={disabled}
            onChange={(e) => onChange(e.target.value === '' ? '' : Number(e.target.value))}
        >
            <option value="">{placeholder || '— pilih —'}</option>
            {(options || []).map((o) => (
                <option key={getValue(o)} value={getValue(o)}>{getLabel(o)}</option>
            ))}
        </select>
    );
}

export function ItemSelect({ value, onChange, filter, placeholder }) {
    const { data } = useOptions('items');
    const opts = filter ? (data || []).filter(filter) : data;
    return <Select value={value} onChange={onChange} options={opts}
        getValue={(o) => o.id} getLabel={(o) => `${o.code} — ${o.part_name}`} placeholder={placeholder || '— pilih item —'} />;
}

export function VendorSelect({ value, onChange }) {
    const { data } = useOptions('contacts');
    return <Select value={value} onChange={onChange} options={data}
        getValue={(o) => o.id} getLabel={(o) => o.company_n} placeholder="— pilih vendor —" />;
}

const STATUS_STYLE = {
    DRAFT: 'bg-slate-100 text-slate-600',
    SUBMITTED: 'bg-amber-100 text-amber-700',
    APPROVED: 'bg-emerald-100 text-emerald-700',
    REJECTED: 'bg-red-100 text-red-700',
    OPEN: 'bg-sky-100 text-sky-700',
    INPROGRESS: 'bg-amber-100 text-amber-700',
    CLOSE: 'bg-blue-100 text-blue-700',
    CLOSED: 'bg-blue-100 text-blue-700',
    CANCELLED: 'bg-red-100 text-red-700',
    POSTED: 'bg-emerald-100 text-emerald-700',
    FINAL: 'bg-blue-100 text-blue-700',
    RETURNED: 'bg-amber-100 text-amber-700',
    CLAIMED: 'bg-blue-100 text-blue-700',
    MATCHED: 'bg-emerald-100 text-emerald-700',
    PAID: 'bg-blue-100 text-blue-700',
};

// PO receiving state carries a numeric code: Open=1, In Progress=2, Close=3.
const STATUS_LABEL = {
    OPEN: '1 · Open',
    INPROGRESS: '2 · In Progress',
    CLOSE: '3 · Close',
};

export function StatusBadge({ status }) {
    return (
        <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLE[status] || 'bg-slate-100 text-slate-600'}`}>
            {STATUS_LABEL[status] || status}
        </span>
    );
}

/** Editable line table (add/remove rows). */
export function LineTable({ title, subtitle, onAdd, lines, head, row, empty, addLabel = 'Baris' }) {
    return (
        <div className="mb-5">
            <div className="mb-2 flex items-center justify-between">
                <div>
                    <h4 className="text-sm font-semibold text-slate-700">{title}</h4>
                    {subtitle && <p className="text-xs text-slate-400">{subtitle}</p>}
                </div>
                {onAdd && (
                    <button type="button" className="btn btn-ghost px-2 py-1 text-xs" onClick={onAdd}>
                        <Icon name="plus" className="h-3.5 w-3.5" /> {addLabel}
                    </button>
                )}
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
                        {lines.map((l, i) => <tr key={i} className="border-t border-slate-100 align-top">{row(l, i)}</tr>)}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/** Small number/text cell input for line tables. */
export function CellInput({ value, onChange, type = 'text', step, className = '' }) {
    return (
        <input
            type={type === 'number' ? 'number' : type} step={step}
            className={`field-input ${className}`}
            value={value ?? ''}
            onChange={(e) => onChange(type === 'number' ? (e.target.value === '' ? '' : e.target.value) : e.target.value)}
        />
    );
}

export const money = (v) => (v == null ? '' : Number(v).toLocaleString('id-ID'));
