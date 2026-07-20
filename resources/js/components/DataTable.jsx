import Icon from './Icon';

/**
 * Server-side data table. Parent owns query state (search/page) and passes
 * rows + meta down; this component only renders and emits events.
 */
export default function DataTable({
    columns, rows, meta, loading, onPageChange, actions,
}) {
    return (
        <div className="card overflow-hidden">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {columns.map((c) => (
                                <th key={c.key} className="px-4 py-3 whitespace-nowrap">{c.label}</th>
                            ))}
                            {actions && <th className="px-4 py-3 text-right">Aksi</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {loading && (
                            <tr>
                                <td colSpan={columns.length + (actions ? 1 : 0)} className="px-4 py-10 text-center text-slate-400">
                                    <Icon name="spinner" className="mx-auto h-5 w-5 animate-spin" />
                                </td>
                            </tr>
                        )}
                        {!loading && rows.length === 0 && (
                            <tr>
                                <td colSpan={columns.length + (actions ? 1 : 0)} className="px-4 py-10 text-center text-slate-400">
                                    Tidak ada data.
                                </td>
                            </tr>
                        )}
                        {!loading &&
                            rows.map((row) => (
                                <tr key={row.id} className="border-b border-slate-100 hover:bg-blue-50/40">
                                    {columns.map((c) => (
                                        <td key={c.key} className="px-4 py-2.5 whitespace-nowrap text-slate-700">
                                            {c.render ? c.render(row[c.key], row) : (row[c.key] ?? '—')}
                                        </td>
                                    ))}
                                    {actions && <td className="px-4 py-2.5 text-right">{actions(row)}</td>}
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            {meta && meta.last_page > 1 && (
                <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
                    <span>
                        Halaman {meta.page} dari {meta.last_page} · {meta.total} baris
                    </span>
                    <div className="flex gap-1">
                        <button
                            disabled={meta.page <= 1}
                            onClick={() => onPageChange(meta.page - 1)}
                            className="btn btn-ghost px-2 py-1"
                        >
                            <Icon name="left" />
                        </button>
                        <button
                            disabled={meta.page >= meta.last_page}
                            onClick={() => onPageChange(meta.page + 1)}
                            className="btn btn-ghost px-2 py-1"
                        >
                            <Icon name="right" />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
