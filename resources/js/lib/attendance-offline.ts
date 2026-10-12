/**
 * Offline attendance drafts (ADR-023): an IndexedDB queue of unsynced draft saves (changed marks only — ids, statuses,
 * minutes late, notes; no names) and its sync to the server with the original idempotency key. Transport only:
 * every rule is enforced by the server when the draft arrives.
 */

export type QueuedMark = {
    enrollment_id: number;
    status: number;
    minutes_late: number | null;
    note: string | null;
};
export type QueuedDraft = {
    session_id: number;
    version: number;
    marks: QueuedMark[];
    key: string;
    xsrf: string;
    saved_at: string;
};
export type SyncOutcome =
    | { ok: true; version: number }
    | { ok: false; conflict: boolean; code: string | null };

const DB_NAME = 'sis-attendance';
const STORE = 'drafts';
const SYNC_TAG = 'sis-attendance-drafts';

function openDb(): Promise<IDBDatabase | null> {
    if (typeof indexedDB === 'undefined') {
        return Promise.resolve(null);
    }

    return new Promise((resolve) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = () =>
            req.result.createObjectStore(STORE, { keyPath: 'session_id' });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => resolve(null);
    });
}

function run<T>(
    mode: IDBTransactionMode,
    work: (store: IDBObjectStore) => IDBRequest<T> | null,
): Promise<T | null> {
    return openDb().then(
        (db) =>
            new Promise((resolve) => {
                if (db === null) {
                    resolve(null);

                    return;
                }
                try {
                    const tx = db.transaction(STORE, mode);
                    const req = work(tx.objectStore(STORE));
                    tx.oncomplete = () =>
                        resolve(req ? (req.result ?? null) : null);
                    tx.onerror = () => resolve(null);
                } catch {
                    resolve(null);
                }
            }),
    );
}

export function readQueued(sessionId: number): Promise<QueuedDraft | null> {
    return run<QueuedDraft>(
        'readonly',
        (store) => store.get(sessionId) as IDBRequest<QueuedDraft>,
    );
}

export function removeQueued(sessionId: number): Promise<null> {
    return run('readwrite', (store) => {
        store.delete(sessionId);

        return null;
    });
}

export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** Stores the draft on this device and asks the service worker to sync it when the connection returns. */
export async function queueDraft(
    draft: Omit<QueuedDraft, 'xsrf' | 'saved_at'>,
): Promise<boolean> {
    const entry: QueuedDraft = {
        ...draft,
        xsrf: xsrfToken(),
        saved_at: new Date().toISOString(),
    };
    await run('readwrite', (store) => {
        store.put(entry);

        return null;
    });
    try {
        const registration = await navigator.serviceWorker?.ready;
        const sync = (
            registration as ServiceWorkerRegistration & {
                sync?: { register: (tag: string) => Promise<void> };
            }
        ).sync;
        await sync?.register(SYNC_TAG);
    } catch {
        // No Background Sync: the page syncs on «online» / next visit.
    }

    return (await readQueued(draft.session_id)) !== null;
}

/** Sends one queued draft (JSON, same endpoint as the page form). */
export async function syncDraft(entry: QueuedDraft): Promise<SyncOutcome> {
    try {
        const response = await fetch(
            `/attendance/sheets/${entry.session_id}/draft`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Idempotency-Key': entry.key,
                    'X-XSRF-TOKEN': xsrfToken() || entry.xsrf,
                },
                body: JSON.stringify({
                    version: entry.version,
                    marks: entry.marks,
                }),
            },
        );
        const body = (await response.json().catch(() => ({}))) as {
            data?: { version?: number };
            error_code?: string;
        };
        if (response.ok) {
            await removeQueued(entry.session_id);

            return {
                ok: true,
                version: body.data?.version ?? entry.version + 1,
            };
        }

        return {
            ok: false,
            conflict: response.status === 409,
            code: body.error_code ?? null,
        };
    } catch {
        return { ok: false, conflict: false, code: null };
    }
}

/** Registers the attendance service worker (scope /attendance/). Safe to call on every attendance page. */
export function registerAttendanceServiceWorker(): void {
    if (
        typeof navigator === 'undefined' ||
        !('serviceWorker' in navigator) ||
        !window.isSecureContext
    ) {
        return;
    }
    void navigator.serviceWorker
        .register('/attendance-sw.js', { scope: '/attendance/' })
        .catch(() => undefined);
}
