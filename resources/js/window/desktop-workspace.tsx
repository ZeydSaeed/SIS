import { useEffect } from 'react';
import { WindowFrame } from '@/window/window-frame';
import { useWindowManager } from '@/window/window-manager-context';

export function DesktopWorkspace() {
    const wm = useWindowManager();
    const minimized = wm.windows.filter((w) => w.minimized);
    const open = wm.windows.filter((w) => !w.minimized);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
                return;
            }
            if (event.key === 'Escape') {
                wm.closeFocused();
                return;
            }
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'w') {
                event.preventDefault();
                wm.closeFocused();
                return;
            }
            if (event.altKey && event.key === 'Tab') {
                event.preventDefault();
                wm.cycleFocus(event.shiftKey ? -1 : 1);
            }
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [wm]);

    return (
        <div className="sis-desktop-workspace" data-window-id="workspace.root" aria-label="Desktop workspace">
            <div className="sis-desktop-workspace__brand" aria-hidden>
                <span className="sis-desktop-workspace__brand-mark">SIS</span>
                <span className="sis-desktop-workspace__brand-sub">Operational Workspace</span>
            </div>
            <div className="sis-desktop-workspace__canvas" aria-live="polite">
                {open.map((win) => (
                    <WindowFrame key={win.instanceId} window={win} />
                ))}
            </div>
            <div className="sis-desktop-workspace__taskbar" role="toolbar" aria-label="Window taskbar">
                <button
                    type="button"
                    className="sis-desktop-workspace__task"
                    onClick={() => wm.cascade()}
                    aria-label="Cascade windows"
                >
                    Cascade
                </button>
                {minimized.map((win) => (
                    <button
                        key={win.instanceId}
                        type="button"
                        className="sis-desktop-workspace__task"
                        onClick={() => wm.restore(win.instanceId)}
                    >
                        {win.title}
                    </button>
                ))}
            </div>
        </div>
    );
}
