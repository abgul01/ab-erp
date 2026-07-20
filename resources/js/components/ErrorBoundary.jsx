import { Component } from 'react';

/**
 * Top-level safety net. Without this, any render error unmounts the whole
 * React tree and the user just sees a blank white page with no clue why.
 * Here we catch it, show the message, and offer a way back.
 */
export default class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { error: null };
    }

    static getDerivedStateFromError(error) {
        return { error };
    }

    componentDidCatch(error, info) {
        // Keep the details in the console for debugging.
        // eslint-disable-next-line no-console
        console.error('UI error:', error, info);
    }

    reset = () => this.setState({ error: null });

    render() {
        const { error } = this.state;
        if (!error) return this.props.children;

        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-50 p-6">
                <div className="w-full max-w-lg rounded-lg border border-red-200 bg-white p-6 shadow-sm">
                    <h1 className="mb-2 text-lg font-semibold text-red-700">Terjadi kesalahan tampilan</h1>
                    <p className="mb-3 text-sm text-slate-500">
                        Halaman gagal dirender. Detail teknis:
                    </p>
                    <pre className="mb-4 max-h-48 overflow-auto rounded bg-slate-900 p-3 text-xs text-red-300">
                        {String(error?.stack || error?.message || error)}
                    </pre>
                    <div className="flex gap-2">
                        <button
                            className="btn btn-primary"
                            onClick={() => { this.reset(); window.location.assign('/'); }}
                        >
                            Kembali ke beranda
                        </button>
                        <button className="btn btn-ghost" onClick={() => window.location.reload()}>
                            Muat ulang
                        </button>
                    </div>
                </div>
            </div>
        );
    }
}
