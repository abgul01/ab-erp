import { useMemo } from 'react';

/**
 * Month selection for the planning and accounting screens.
 *
 * The database stores a period as `YYYYMM`, which is fine for a column and
 * hopeless as something to type: a plain text box invites 2026-07, Jul-26 or
 * 20267 and only tells you it was wrong after the request fails. These wrap the
 * browser's native month control and do the conversion, so the screen always
 * hands the API a valid period.
 */

/** `YYYYMM` → `YYYY-MM` for the native control. */
export const toInput = (period) =>
    /^\d{6}$/.test(String(period || '')) ? `${period.slice(0, 4)}-${period.slice(4, 6)}` : '';

/** `YYYY-MM` → `YYYYMM` for the API. */
export const toPeriod = (value) => (value ? String(value).replace('-', '') : '');

export const currentPeriod = () => {
    const d = new Date();

    return `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}`;
};

/** Add months to a `YYYYMM`, keeping it a valid period. */
export const addMonths = (period, n) => {
    const y = Number(String(period).slice(0, 4));
    const m = Number(String(period).slice(4, 6)) - 1 + n;
    const d = new Date(y, m, 1);

    return `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}`;
};

/** Readable form: `202607` → `Jul 2026`. */
export const formatPeriod = (period) => {
    if (!/^\d{6}$/.test(String(period || ''))) return period || '—';
    const d = new Date(Number(period.slice(0, 4)), Number(period.slice(4, 6)) - 1, 1);

    return d.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
};

/** Every period from `from` to `to` inclusive; empty if the range is inverted. */
export const periodRange = (from, to) => {
    if (!/^\d{6}$/.test(from) || !/^\d{6}$/.test(to) || from > to) return [];

    const out = [];
    for (let p = from; p <= to && out.length < 36; p = addMonths(p, 1)) out.push(p);

    return out;
};

/**
 * One month. `value`/`onChange` speak `YYYYMM`.
 */
export default function MonthPicker({ value, onChange, disabled, className = 'w-44', min, max }) {
    return (
        <input
            type="month"
            className={`field-input ${className}`}
            value={toInput(value)}
            disabled={disabled}
            min={toInput(min)}
            max={toInput(max)}
            onChange={(e) => onChange(toPeriod(e.target.value))}
        />
    );
}

/**
 * A span of months — one month when both ends are the same.
 *
 * `onChange` receives the list of periods, so a caller that runs per month can
 * loop over it without knowing how the range was expressed.
 */
export function MonthRangePicker({ from, to, onChange, disabled, max = 12 }) {
    const periods = useMemo(() => periodRange(from, to), [from, to]);
    const tooMany = periods.length > max;

    const emit = (nextFrom, nextTo) => {
        // Dragging the start past the end is a slip, not a request for an empty
        // range — move the other end along rather than showing nothing.
        const f = nextFrom || from;
        let t = nextTo || to;
        if (f && t && f > t) t = f;

        onChange({ from: f, to: t, periods: periodRange(f, t) });
    };

    return (
        <div className="flex flex-wrap items-end gap-2">
            <div>
                <label className="field-label">Dari bulan</label>
                <MonthPicker value={from} onChange={(v) => emit(v, null)} disabled={disabled} className="w-40" />
            </div>
            <div>
                <label className="field-label">Sampai bulan</label>
                <MonthPicker value={to} onChange={(v) => emit(null, v)} disabled={disabled} className="w-40" min={from} />
            </div>
            <span className={`pb-2 text-xs ${tooMany ? 'text-red-600' : 'text-slate-400'}`}>
                {periods.length === 0
                    ? 'pilih rentang'
                    : periods.length === 1
                        ? formatPeriod(periods[0])
                        : `${periods.length} bulan · ${formatPeriod(periods[0])} – ${formatPeriod(periods[periods.length - 1])}`}
                {tooMany && ` (maks ${max})`}
            </span>
        </div>
    );
}
