const LEVEL_LABEL = {
    'prc_pr_main': 'PR',
    'prc_po_main': 'PO',
    'sls_so_main': 'SO',
};

export default function ApprovalBadge({ approvals = [] }) {
    if (approvals.length === 0) return null;
    const total = approvals.length;
    const done = approvals.filter((a) => a.status === 'APPROVED').length;
    const pendingIdx = approvals.findIndex((a) => a.status === 'PENDING');

    return (
        <div className="flex items-center gap-1.5">
            {approvals.map((a, i) => {
                let cls = 'h-2 w-5 rounded-full ';
                if (a.status === 'APPROVED') cls += 'bg-emerald-500';
                else if (a.status === 'REJECTED') cls += 'bg-red-400';
                else if (a.status === 'SKIPPED') cls += 'bg-slate-200';
                else cls += 'bg-amber-400 ring-2 ring-amber-200';
                return <span key={i} className={cls} title={`Level ${i + 1}: ${a.status}`} />;
            })}
            <span className="ml-1 text-xs text-slate-400">{done}/{total}</span>
        </div>
    );
}
