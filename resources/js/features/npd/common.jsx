import Icon from '../../components/Icon';

/** Lima fase APQP, dipakai bersama oleh daftar proyek, detail, dan dashboard. */
export const PHASES = [
    { no: 1, short: 'Plan & Define' },
    { no: 2, short: 'Product Design' },
    { no: 3, short: 'Process Design' },
    { no: 4, short: 'Validation & PPAP' },
    { no: 5, short: 'Feedback' },
];

export const PROJECT_STATUS = ['DRAFT', 'RUNNING', 'ON_HOLD', 'HANDOVER', 'CLOSED', 'CANCELLED'];

const PHASE_STYLE = {
    PLANNED: 'bg-slate-100 text-slate-500',
    RUNNING: 'bg-sky-100 text-sky-700',
    SUBMITTED: 'bg-amber-100 text-amber-700',
    APPROVED: 'bg-emerald-100 text-emerald-700',
    REJECTED: 'bg-red-100 text-red-700',
};

export function PhaseBadge({ status }) {
    return (
        <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${PHASE_STYLE[status] || 'bg-slate-100 text-slate-600'}`}>
            {status}
        </span>
    );
}

/**
 * Rel lima fase: yang sudah lewat, yang sedang jalan, yang menunggu.
 *
 * Ini pertanyaan pertama siapa pun yang membuka proyek NPD — "ini sudah sampai
 * mana" — jadi jawabannya harus terbaca tanpa perlu menggulir.
 */
export function PhaseRail({ phases = [], current, onPick }) {
    return (
        <div className="flex flex-wrap items-stretch gap-1">
            {PHASES.map((p) => {
                const row = phases.find((x) => x.phase_no === p.no);
                const st = row?.status || 'PLANNED';
                const isCurrent = current === p.no;

                return (
                    <button
                        key={p.no}
                        type="button"
                        onClick={() => onPick && row && onPick(p.no)}
                        className={`flex-1 rounded-md border px-2 py-1.5 text-left transition
                            ${isCurrent ? 'border-[var(--ftpi-primary)] bg-white shadow-sm' : 'border-slate-200 bg-slate-50 hover:bg-white'}`}
                    >
                        <div className="flex items-center justify-between gap-1">
                            <span className="text-[11px] font-semibold text-slate-400">FASE {p.no}</span>
                            {st === 'APPROVED' && <Icon name="check-circle" className="h-3.5 w-3.5 text-emerald-600" />}
                            {st === 'SUBMITTED' && <Icon name="clock" className="h-3.5 w-3.5 text-amber-600" />}
                        </div>
                        <div className="truncate text-xs font-medium text-slate-700">{p.short}</div>
                        <div className="mt-0.5"><PhaseBadge status={st} /></div>
                    </button>
                );
            })}
        </div>
    );
}

/** Ringkas: berapa deliverable wajib yang masih menahan gate. */
export function pendingMandatory(phase) {
    return (phase?.deliverables || []).filter(
        (d) => d.std?.mandatory && !['DONE', 'WAIVED'].includes(d.status),
    );
}
