import {
    createContext,
    createElement,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { resolveWindow } from '@/window/window-registry';
import type { SisOpenWindow, WindowManagerState } from '@/window/types';

const STORAGE_KEY = 'sis.window-shell.v1';

type WindowManagerApi = {
    windows: SisOpenWindow[];
    focusedInstanceId: string | null;
    open: (windowId: string) => void;
    close: (instanceId: string) => void;
    focus: (instanceId: string) => void;
    minimize: (instanceId: string) => void;
    restore: (instanceId: string) => void;
    maximize: (instanceId: string) => void;
    move: (instanceId: string, x: number, y: number) => void;
    resize: (instanceId: string, width: number, height: number) => void;
    cascade: () => void;
    cycleFocus: (direction?: 1 | -1) => void;
    closeFocused: () => void;
};

const WindowManagerContext = createContext<WindowManagerApi | null>(null);

function loadState(): WindowManagerState | null {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw) as WindowManagerState;
        if (!Array.isArray(parsed.windows)) {
            return null;
        }
        return parsed;
    } catch {
        return null;
    }
}

function saveState(state: WindowManagerState): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        // ignore
    }
}

function createInstance(windowId: string, nextZ: number, offset: number): SisOpenWindow | null {
    const descriptor = resolveWindow(windowId);
    if (!descriptor) {
        return null;
    }
    const min = descriptor.minimumSize ?? { width: 640, height: 480 };
    return {
        ...descriptor,
        instanceId: `${windowId}:${crypto.randomUUID()}`,
        x: 48 + offset * 28,
        y: 48 + offset * 28,
        width: Math.max(min.width, 720),
        height: Math.max(min.height, 520),
        zIndex: nextZ,
        minimized: false,
        maximized: false,
    };
}

export function WindowManagerProvider({ children }: { children: ReactNode }) {
    const [state, setState] = useState<WindowManagerState>(() => {
        return (
            loadState() ?? {
                windows: [],
                focusedInstanceId: null,
                nextZ: 100,
            }
        );
    });

    useEffect(() => {
        saveState(state);
    }, [state]);

    const open = useCallback((windowId: string) => {
        setState((prev) => {
            const existing = prev.windows.find((w) => w.windowId === windowId && !w.minimized);
            if (existing) {
                return {
                    ...prev,
                    focusedInstanceId: existing.instanceId,
                    nextZ: prev.nextZ + 1,
                    windows: prev.windows.map((w) =>
                        w.instanceId === existing.instanceId
                            ? { ...w, zIndex: prev.nextZ + 1, minimized: false }
                            : w,
                    ),
                };
            }
            const minimized = prev.windows.find((w) => w.windowId === windowId && w.minimized);
            if (minimized) {
                return {
                    ...prev,
                    focusedInstanceId: minimized.instanceId,
                    nextZ: prev.nextZ + 1,
                    windows: prev.windows.map((w) =>
                        w.instanceId === minimized.instanceId
                            ? { ...w, minimized: false, zIndex: prev.nextZ + 1 }
                            : w,
                    ),
                };
            }
            const win = createInstance(windowId, prev.nextZ + 1, prev.windows.length);
            if (!win) {
                return prev;
            }
            return {
                windows: [...prev.windows, win],
                focusedInstanceId: win.instanceId,
                nextZ: prev.nextZ + 1,
            };
        });
    }, []);

    const close = useCallback((instanceId: string) => {
        setState((prev) => ({
            ...prev,
            windows: prev.windows.filter((w) => w.instanceId !== instanceId),
            focusedInstanceId:
                prev.focusedInstanceId === instanceId ? null : prev.focusedInstanceId,
        }));
    }, []);

    const focus = useCallback((instanceId: string) => {
        setState((prev) => ({
            ...prev,
            focusedInstanceId: instanceId,
            nextZ: prev.nextZ + 1,
            windows: prev.windows.map((w) =>
                w.instanceId === instanceId ? { ...w, zIndex: prev.nextZ + 1, minimized: false } : w,
            ),
        }));
    }, []);

    const minimize = useCallback((instanceId: string) => {
        setState((prev) => ({
            ...prev,
            windows: prev.windows.map((w) =>
                w.instanceId === instanceId ? { ...w, minimized: true, maximized: false } : w,
            ),
            focusedInstanceId:
                prev.focusedInstanceId === instanceId ? null : prev.focusedInstanceId,
        }));
    }, []);

    const restore = useCallback((instanceId: string) => {
        setState((prev) => ({
            ...prev,
            focusedInstanceId: instanceId,
            nextZ: prev.nextZ + 1,
            windows: prev.windows.map((w) =>
                w.instanceId === instanceId
                    ? { ...w, minimized: false, maximized: false, zIndex: prev.nextZ + 1 }
                    : w,
            ),
        }));
    }, []);

    const maximize = useCallback((instanceId: string) => {
        setState((prev) => ({
            ...prev,
            focusedInstanceId: instanceId,
            nextZ: prev.nextZ + 1,
            windows: prev.windows.map((w) =>
                w.instanceId === instanceId
                    ? { ...w, maximized: !w.maximized, minimized: false, zIndex: prev.nextZ + 1 }
                    : w,
            ),
        }));
    }, []);

    const move = useCallback((instanceId: string, x: number, y: number) => {
        setState((prev) => ({
            ...prev,
            windows: prev.windows.map((w) =>
                w.instanceId === instanceId && !w.maximized
                    ? { ...w, x: Math.max(0, x), y: Math.max(0, y) }
                    : w,
            ),
        }));
    }, []);

    const resize = useCallback((instanceId: string, width: number, height: number) => {
        setState((prev) => ({
            ...prev,
            windows: prev.windows.map((w) => {
                if (w.instanceId !== instanceId || w.maximized) {
                    return w;
                }
                const min = w.minimumSize ?? { width: 480, height: 360 };
                return {
                    ...w,
                    width: Math.max(min.width, width),
                    height: Math.max(min.height, height),
                };
            }),
        }));
    }, []);

    const cascade = useCallback(() => {
        setState((prev) => {
            let z = prev.nextZ;
            const visible = prev.windows.filter((w) => !w.minimized);
            const windows = prev.windows.map((w) => {
                if (w.minimized) {
                    return w;
                }
                const index = visible.findIndex((v) => v.instanceId === w.instanceId);
                z += 1;
                return {
                    ...w,
                    maximized: false,
                    x: 40 + index * 32,
                    y: 40 + index * 32,
                    zIndex: z,
                };
            });
            const last = visible[visible.length - 1];
            return {
                windows,
                focusedInstanceId: last?.instanceId ?? prev.focusedInstanceId,
                nextZ: z,
            };
        });
    }, []);

    const cycleFocus = useCallback((direction: 1 | -1 = 1) => {
        setState((prev) => {
            const visible = prev.windows
                .filter((w) => !w.minimized)
                .sort((a, b) => a.zIndex - b.zIndex);
            if (visible.length === 0) {
                return prev;
            }
            const currentIndex = visible.findIndex((w) => w.instanceId === prev.focusedInstanceId);
            const nextIndex =
                currentIndex === -1
                    ? 0
                    : (currentIndex + direction + visible.length) % visible.length;
            const target = visible[nextIndex];
            return {
                ...prev,
                focusedInstanceId: target.instanceId,
                nextZ: prev.nextZ + 1,
                windows: prev.windows.map((w) =>
                    w.instanceId === target.instanceId
                        ? { ...w, zIndex: prev.nextZ + 1 }
                        : w,
                ),
            };
        });
    }, []);

    const closeFocused = useCallback(() => {
        setState((prev) => {
            if (!prev.focusedInstanceId) {
                return prev;
            }
            const id = prev.focusedInstanceId;
            return {
                ...prev,
                windows: prev.windows.filter((w) => w.instanceId !== id),
                focusedInstanceId: null,
            };
        });
    }, []);

    const api = useMemo<WindowManagerApi>(
        () => ({
            windows: state.windows,
            focusedInstanceId: state.focusedInstanceId,
            open,
            close,
            focus,
            minimize,
            restore,
            maximize,
            move,
            resize,
            cascade,
            cycleFocus,
            closeFocused,
        }),
        [
            state,
            open,
            close,
            focus,
            minimize,
            restore,
            maximize,
            move,
            resize,
            cascade,
            cycleFocus,
            closeFocused,
        ],
    );

    return createElement(WindowManagerContext.Provider, { value: api }, children);
}

export function useWindowManager(): WindowManagerApi {
    const ctx = useContext(WindowManagerContext);
    if (!ctx) {
        throw new Error('useWindowManager must be used within WindowManagerProvider');
    }
    return ctx;
}

export function useWindowManagerOptional(): WindowManagerApi | null {
    return useContext(WindowManagerContext);
}
