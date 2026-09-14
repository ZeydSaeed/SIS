import { WindowFrame } from '@/window/window-frame';
import { useWindowManager } from '@/window/window-manager-context';

export function DesktopWorkspace() {
    const wm = useWindowManager();
    const minimized = wm.windows.filter((w) => w.minimized);
    const open = wm.windows.filter((w) => !w.minimized);

    return (
        <div className="sis-desktop-workspace" data-window-id="workspace.root">
            <div className="sis-desktop-workspace__canvas" aria-live="polite">
                {open.map((win) => (
                    <WindowFrame key={win.instanceId} window={win} />
                ))}
            </div>
            {minimized.length > 0 ? (
                <div className="sis-desktop-workspace__taskbar" role="toolbar" aria-label="Minimized windows">
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
            ) : null}
        </div>
    );
}
