import Dexie from 'dexie';

/**
 * Offline buffer for the shop-floor terminals.
 *
 * A queued item is the API call itself — method, url, body — plus a uuid the
 * terminal generates before it ever reaches the network. The server replays the
 * call through the same route it would have hit online and uses that uuid to
 * reject a second delivery, so flushing twice is harmless.
 *
 * PRD §2.1, LLD §6.2
 */
const db = new Dexie('abErpOffline');

db.version(2).stores({
    operations: '++id, clientUuid, status, createdAt',
    snapshot: 'key',
});

/** Server refuses work older than this, so there is no point holding it. */
const MAX_AGE_HOURS = 24;

const listeners = new Set();

function notify() {
    pendingCount().then((n) => listeners.forEach((fn) => fn(n)));
}

/** Subscribe to the pending count. Returns an unsubscribe function. */
export function onChange(fn) {
    listeners.add(fn);
    notify();
    return () => listeners.delete(fn);
}

export async function enqueue({ method, url, data }) {
    const op = {
        clientUuid: crypto.randomUUID(),
        method: String(method || 'POST').toUpperCase(),
        url,
        data: data || {},
        status: 'PENDING',
        error: null,
        createdAt: new Date().toISOString(),
    };
    op.id = await db.operations.add(op);
    notify();
    return op;
}

export function pendingCount() {
    return db.operations.where('status').equals('PENDING').count();
}

export function listOps() {
    return db.operations.orderBy('createdAt').toArray();
}

/** Drop operations the server would reject for age anyway. */
async function expire() {
    const cutoff = Date.now() - MAX_AGE_HOURS * 3600_000;
    const stale = await db.operations
        .where('status').equals('PENDING')
        .filter((o) => new Date(o.createdAt).getTime() < cutoff)
        .toArray();
    if (stale.length) {
        await db.operations.bulkUpdate(
            stale.map((o) => ({ key: o.id, changes: { status: 'EXPIRED', error: 'Lewat 24 jam offline.' } })),
        );
    }
}

/**
 * Send everything buffered. Each operation is reported individually: a scan the
 * server rejects on business grounds is kept as FAILED for the operator to look
 * at, it is not silently dropped and does not block the rest of the batch.
 */
export async function flush() {
    await expire();

    const ops = await db.operations.where('status').equals('PENDING').toArray();
    if (!ops.length) return { processed: 0, skipped: 0, failed: 0, results: [] };

    const token = localStorage.getItem('ab_token');
    const res = await fetch('/api/v1/mes/sync', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: JSON.stringify({
            operations: ops.map((o) => ({
                client_uuid: o.clientUuid,
                method: o.method,
                url: o.url,
                data: o.data,
                client_at: o.createdAt,
            })),
        }),
    });

    if (!res.ok) throw new Error(`Sync gagal (${res.status})`);

    const result = (await res.json()).data;
    const byUuid = Object.fromEntries((result.results || []).map((r) => [r.client_uuid, r]));

    const done = [];
    const failed = [];
    ops.forEach((o) => {
        const r = byUuid[o.clientUuid];
        if (!r || r.status === 'OK' || r.status === 'SKIPPED') done.push(o.id);
        else failed.push({ key: o.id, changes: { status: 'FAILED', error: r.error } });
    });

    await db.operations.bulkDelete(done);
    if (failed.length) await db.operations.bulkUpdate(failed);
    notify();

    return result;
}

/** Master data cached for offline scanning (work orders, machines, shifts). */
export async function saveSnapshot(data) {
    await db.snapshot.put({ key: 'mes', data, savedAt: new Date().toISOString() });
}

export async function getSnapshot() {
    return (await db.snapshot.get('mes')) || null;
}

/** Clear operations the operator has acknowledged as unrecoverable. */
export async function discardFailed() {
    await db.operations.where('status').anyOf('FAILED', 'EXPIRED').delete();
    notify();
}

export default db;
