/**
 * Instant cross-window / cross-page student status sync (no full reload).
 *
 * Delivery order:
 * 1. Long-lived BroadcastChannel (works across Window Manager iframes)
 * 2. sessionStorage last-event (replay on mount if a frame missed the message)
 * 3. localStorage write (storage event for other browsing contexts)
 * 4. Same-document listeners (rare: both lists in one document)
 *
 * IMPORTANT: never open→post→close BroadcastChannel — closing immediately
 * drops messages intermittently in Chromium.
 */

export type StudentStatusSyncPayload = {
    studentIds: number[];
    status: number;
    source?: 'students' | 'enrollments';
    at: number;
};

const CHANNEL = 'sis-student-status-sync-v1';
const STORAGE_KEY = 'sis-student-status-sync-v1';
const LAST_KEY = 'sis-student-status-sync-v1-last';
/** Replay window for frames that mounted just after a publish. */
const REPLAY_TTL_MS = 60_000;

type Listener = (payload: StudentStatusSyncPayload) => void;

const sameTabListeners = new Set<Listener>();
let sharedChannel: BroadcastChannel | null = null;
let channelListenerAttached = false;

function isPayload(value: unknown): value is StudentStatusSyncPayload {
    if (value === null || typeof value !== 'object') {
        return false;
    }

    const record = value as Record<string, unknown>;
    if (
        ! Array.isArray(record.studentIds)
        || typeof record.status !== 'number'
        || typeof record.at !== 'number'
    ) {
        return false;
    }

    return record.studentIds.every((id) => typeof id === 'number' && Number.isFinite(id));
}

function getSharedChannel(): BroadcastChannel | null {
    if (typeof BroadcastChannel === 'undefined') {
        return null;
    }

    if (sharedChannel !== null) {
        return sharedChannel;
    }

    try {
        sharedChannel = new BroadcastChannel(CHANNEL);
        return sharedChannel;
    } catch {
        sharedChannel = null;

        return null;
    }
}

function ensureChannelFanOut(): void {
    if (channelListenerAttached) {
        return;
    }

    const channel = getSharedChannel();
    if (channel === null) {
        return;
    }

    channel.addEventListener('message', (event: MessageEvent) => {
        if (! isPayload(event.data)) {
            return;
        }

        sameTabListeners.forEach((listener) => listener(event.data));
    });
    channelListenerAttached = true;
}

function persistLast(payload: StudentStatusSyncPayload): void {
    try {
        sessionStorage.setItem(LAST_KEY, JSON.stringify(payload));
    } catch {
        // private / quota
    }

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        // Keep value briefly so late subscribers can also read via storage;
        // do not remove in the same tick (drops some iframe deliveries).
        window.setTimeout(() => {
            try {
                if (localStorage.getItem(STORAGE_KEY) === JSON.stringify(payload)) {
                    localStorage.removeItem(STORAGE_KEY);
                }
            } catch {
                // ignore
            }
        }, 250);
    } catch {
        // private / quota
    }
}

export function readLastStudentStatusSync(maxAgeMs: number = REPLAY_TTL_MS): StudentStatusSyncPayload | null {
    try {
        const raw = sessionStorage.getItem(LAST_KEY);
        if (! raw) {
            return null;
        }

        const parsed: unknown = JSON.parse(raw);
        if (! isPayload(parsed)) {
            return null;
        }

        if (Date.now() - parsed.at > maxAgeMs) {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

export function publishStudentStatusSync(
    studentIds: number[],
    status: number,
    source?: 'students' | 'enrollments',
): void {
    const ids = [...new Set(studentIds.filter((id) => id > 0))];
    if (ids.length === 0) {
        return;
    }

    const payload: StudentStatusSyncPayload = {
        studentIds: ids,
        status,
        source,
        at: Date.now(),
    };

    persistLast(payload);

    const channel = getSharedChannel();
    if (channel !== null) {
        try {
            channel.postMessage(payload);
        } catch {
            // fall through to same-tab + storage
        }
    }

    // Same browsing context subscribers (this frame).
    sameTabListeners.forEach((listener) => listener(payload));
}

export function subscribeStudentStatusSync(listener: Listener): () => void {
    ensureChannelFanOut();
    sameTabListeners.add(listener);

    // Catch up if publish happened before this frame subscribed.
    const last = readLastStudentStatusSync();
    if (last !== null) {
        queueMicrotask(() => listener(last));
    }

    const onStorage = (event: StorageEvent) => {
        if (event.key !== STORAGE_KEY || ! event.newValue) {
            return;
        }

        try {
            const parsed: unknown = JSON.parse(event.newValue);
            if (isPayload(parsed)) {
                listener(parsed);
            }
        } catch {
            // ignore malformed
        }
    };

    window.addEventListener('storage', onStorage);

    return () => {
        sameTabListeners.delete(listener);
        window.removeEventListener('storage', onStorage);
        // Keep sharedChannel open for the lifetime of the document.
    };
}
