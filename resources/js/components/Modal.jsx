import Icon from './Icon';

export default function Modal({ open, title, onClose, children, footer, wide, size }) {
    if (!open) return null;
    const width = size || (wide ? 'max-w-4xl' : 'max-w-lg');
    return (
        <div className="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8">
            <div className={`card w-full ${width} my-4`}>
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h3 className="text-base font-semibold text-slate-800">{title}</h3>
                    <button onClick={onClose} className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <Icon name="x" />
                    </button>
                </div>
                <div className="px-5 py-4">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">{footer}</div>}
            </div>
        </div>
    );
}
