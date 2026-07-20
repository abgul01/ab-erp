import { useQuery } from '@tanstack/react-query';
import api from '../api/client';

function AsyncSelect({ field, value, onChange }) {
    const { resource, valueKey, labelKey } = field.optionsFrom;
    const { data, isLoading } = useQuery({
        queryKey: ['options', resource],
        queryFn: async () => (await api.get(`/${resource}`, { params: { per_page: 200 } })).data.data,
        staleTime: 60_000,
    });

    return (
        <select className="field-input" value={value ?? ''} onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}>
            <option value="">{isLoading ? 'Memuat…' : `— pilih ${field.label} —`}</option>
            {(data || []).map((o) => (
                <option key={o[valueKey]} value={o[valueKey]}>
                    {o[labelKey]}
                </option>
            ))}
        </select>
    );
}

function Field({ field, value, onChange }) {
    if (field.type === 'checkbox') {
        return (
            <label className="mt-6 inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" checked={Boolean(value)} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4" />
                {field.label}
            </label>
        );
    }

    return (
        <div>
            <label className="field-label">
                {field.label} {field.required && <span className="text-red-500">*</span>}
            </label>
            {field.type === 'textarea' ? (
                <textarea className="field-input" rows={2} value={value ?? ''} onChange={(e) => onChange(e.target.value)} />
            ) : field.type === 'select' && field.optionsFrom ? (
                <AsyncSelect field={field} value={value} onChange={onChange} />
            ) : field.type === 'select' ? (
                <select className="field-input" value={value ?? ''} onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}>
                    <option value="">— pilih —</option>
                    {field.options.map((o) => (
                        <option key={o.value} value={o.value}>{o.label}</option>
                    ))}
                </select>
            ) : (
                <input
                    type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'}
                    step={field.step}
                    className="field-input"
                    value={value ?? ''}
                    onChange={(e) => onChange(field.type === 'number' ? (e.target.value === '' ? null : e.target.value) : e.target.value)}
                />
            )}
        </div>
    );
}

export default function DynamicForm({ fields, values, onChange }) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {fields.map((f) => (
                <div key={f.name} className={f.colSpan === 2 || f.type === 'textarea' ? 'sm:col-span-2' : ''}>
                    <Field field={f} value={values[f.name]} onChange={(v) => onChange(f.name, v)} />
                </div>
            ))}
        </div>
    );
}
