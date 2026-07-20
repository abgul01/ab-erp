import { useState } from 'react';
import { useNavigate, Navigate } from 'react-router-dom';
import { useAuth } from '../stores/auth';
import { apiError } from '../api/client';
import Icon from '../components/Icon';

export default function Login() {
    const { token, login } = useAuth();
    const navigate = useNavigate();
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    if (token) return <Navigate to="/" replace />;

    const submit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            await login(username, password);
            navigate('/', { replace: true });
        } catch (err) {
            setError(apiError(err, 'Login gagal.'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-[var(--ftpi-primary-dark)] to-[var(--ftpi-primary)] p-4">
            <div className="w-full max-w-md">
                <div className="mb-6 text-center text-white">
                    <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-xl bg-white/15 text-2xl font-bold">
                        AB
                    </div>
                    <h1 className="text-2xl font-semibold tracking-wide">AB-ERP</h1>
                    <p className="text-sm text-blue-100">Sistem ERP + MES Manufaktur Pipa</p>
                </div>

                <div className="card p-6">
                    <form onSubmit={submit} className="space-y-4">
                        {error && <div className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                        <div>
                            <label className="field-label">Username</label>
                            <input className="field-input" value={username} onChange={(e) => setUsername(e.target.value)} autoFocus />
                        </div>
                        <div>
                            <label className="field-label">Password</label>
                            <input type="password" className="field-input" value={password} onChange={(e) => setPassword(e.target.value)} />
                        </div>
                        <button className="btn btn-primary w-full" disabled={loading}>
                            {loading && <Icon name="spinner" className="h-4 w-4 animate-spin" />}
                            Masuk
                        </button>
                    </form>
                    <p className="mt-4 text-center text-xs text-slate-400">
                        Demo: <b>admin</b> / password &nbsp;·&nbsp; <b>operator</b> / password
                    </p>
                </div>
            </div>
        </div>
    );
}
