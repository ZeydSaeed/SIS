/*
 * SIS attendance service worker (ADR-023) — scope /attendance/.
 * - /build/* assets: cache-first after the first online load.
 * - attendance pages (GET navigations and Inertia visits): network-first, last good response as offline fallback.
 * - Background Sync «sis-attendance-drafts»: send queued draft saves (IndexedDB) with their original idempotency key.
 * No business logic here: the server validates every draft (version, roster, permissions).
 */
const CACHE = 'sis-attendance-v1';
const DB_NAME = 'sis-attendance';
const STORE = 'drafts';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k.startsWith('sis-attendance-') && k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(CACHE).then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );

        return;
    }
    if (url.pathname.startsWith('/attendance/') && !url.pathname.includes('/export')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copy));
                    }

                    return response;
                })
                .catch(() => caches.match(request).then((hit) => hit || Response.error())),
        );
    }
});

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'session_id' });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

function all(db) {
    return new Promise((resolve, reject) => {
        const req = db.transaction(STORE, 'readonly').objectStore(STORE).getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => reject(req.error);
    });
}

function remove(db, id) {
    return new Promise((resolve) => {
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => resolve();
    });
}

async function syncDrafts() {
    const db = await openDb();
    for (const entry of await all(db)) {
        const response = await fetch(`/attendance/sheets/${entry.session_id}/draft`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Idempotency-Key': entry.key,
                'X-XSRF-TOKEN': entry.xsrf || '',
            },
            body: JSON.stringify({ version: entry.version, marks: entry.marks }),
        });
        // Confirmed: drop it. Rejected (stale / invalid): keep it for the user to resolve on the page.
        if (response.ok) {
            await remove(db, entry.session_id);
        } else if (response.status >= 500) {
            throw new Error('server unavailable');
        }
    }
    const clients = await self.clients.matchAll();
    clients.forEach((client) => client.postMessage({ type: 'sis-attendance-synced' }));
}

self.addEventListener('sync', (event) => {
    if (event.tag === 'sis-attendance-drafts') {
        event.waitUntil(syncDrafts());
    }
});
