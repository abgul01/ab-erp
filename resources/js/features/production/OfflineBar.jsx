import { useEffect, useState } from 'react';
import api from '../../api/client';
import { discardFailed, flush, getSnapshot, onChange, saveSnapshot } from '../../stores/offlineQueue';

/**
 * Connection state for a shop-floor terminal.
 *
 * Keeps a master-data snapshot fresh while the link is up, watches the buffered
 * scan count, and flushes the queue as soon as the terminal is back online.
 */
export function useOfflineSync() {
    const [online, setOnline] = useState(navigator.onLine);
    const [pending, setPending] = useState(0);
    const [syncing, setSyncing] = useState(false);
    const [lastError, setLastError] = useState(null);
    const [snapshotAt, setSnapshotAt] = useState(null);

    useEffect(() => onChange(setPending), []);

    useEffect(() => {
        getSnapshot().then((s) => setSnapshotAt(s?.savedAt || null));
    }, []);

    async function sync() {
        setSyncing(true);
        setLastError(null);
        try {
            const r = await flush();
            if (r.failed > 0) setLastError(`${r.failed} operasi ditolak server.`);
        } catch (e) {
            setLastError(e.message);
        } finally {
            setSyncing(false);
        }
    }

    async function refreshSnapshot() {
        try {
            const { data } = await api.get('/mes/snapshot');
            await saveSnapshot(data.data);
            setSnapshotAt(new Date().toISOString());
        } catch {
            // Offline is the normal reason this fails; the cached copy stands.
        }
    }

    useEffect(() => {
        const up = () => { setOnline(true); sync(); refreshSnapshot(); };
        const down = () => setOnline(false);
        window.addEventListener('online', up);
        window.addEventListener('offline', down);
        if (navigator.onLine) up();
        return () => {
            window.removeEventListener('online', up);
            window.removeEventListener('offline', down);
        };
    }, []);

    return { online, pending, syncing, lastError, snapshotAt, sync, refreshSnapshot };
}

/** Status strip for the MES terminals. Stays out of the way while all is well. */
export default function OfflineBar() {
    const { online, pending, syncing, lastError, snapshotAt, sync } = useOfflineSync();

    if (online && pending === 0 && !lastError) return null;

    const tone = !online
        ? 'border-amber-300 bg-amber-50 text-amber-800'
        : lastError
            ? 'border-red-300 bg-red-50 text-red-700'
            : 'border-sky-300 bg-sky-50 text-sky-800';

    return (
        <div className={`mb-3 flex flex-wrap items-center gap-3 rounded-md border px-3 py-2 text-sm ${tone}`}>
            <span className={`inline-block h-2.5 w-2.5 rounded-full ${online ? 'bg-emerald-500' : 'bg-amber-500'}`} />
            <span className="font-medium">{online ? 'Online' : 'Offline — scan disimpan di terminal'}</span>
            {pending > 0 && <span>{pending} scan menunggu dikirim</span>}
            {lastError && <span>· {lastError}</span>}
            {!online && snapshotAt && (
                <span className="text-xs opacity-75">Data master per {new Date(snapshotAt).toLocaleString('id-ID')}</span>
            )}
            <span className="ml-auto flex gap-2">
                {online && pending > 0 && (
                    <button className="btn btn-ghost py-1 text-xs" disabled={syncing} onClick={sync}>
                        {syncing ? 'Mengirim…' : 'Kirim sekarang'}
                    </button>
                )}
                {lastError && (
                    <button className="btn btn-ghost py-1 text-xs" onClick={() => discardFailed().then(sync)}>
                        Buang yang gagal
                    </button>
                )}
            </span>
        </div>
    );
}
