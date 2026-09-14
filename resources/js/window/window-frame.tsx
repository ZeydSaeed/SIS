import { useCallback, useRef, type PointerEvent as ReactPointerEvent } from 'react';
import { Minus, Square, X } from 'lucide-react';
import { useWindowManager } from '@/window/window-manager-context';
import type { SisOpenWindow } from '@/window/types';

type Props = {
    window: SisOpenWindow;
};

export function WindowFrame({ window: win }: Props) {
    const wm = useWindowManager();
    const dragRef = useRef<{ ox: number; oy: number; sx: number; sy: number } | null>(null);

    const onTitlePointerDown = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            if (win.maximized || win.movable === false) {
                return;
            }
            event.currentTarget.setPointerCapture(event.pointerId);
            dragRef.current = {
                ox: event.clientX,
                oy: event.clientY,
                sx: win.x,
                sy: win.y,
            };
            wm.focus(win.instanceId);
        },
        [wm, win],
    );

    const onTitlePointerMove = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            if (!dragRef.current) {
                return;
            }
            const dx = event.clientX - dragRef.current.ox;
            const dy = event.clientY - dragRef.current.oy;
            wm.move(win.instanceId, dragRef.current.sx + dx, dragRef.current.sy + dy);
        },
        [wm, win.instanceId],
    );

    const onTitlePointerUp = useCallback((event: ReactPointerEvent<HTMLDivElement>) => {
        if (dragRef.current) {
            event.currentTarget.releasePointerCapture(event.pointerId);
            dragRef.current = null;
        }
    }, []);

    if (win.minimized) {
        return null;
    }

    const style = win.maximized
        ? {
              left: 0,
              top: 0,
              width: '100%',
              height: '100%',
              zIndex: win.zIndex,
          }
        : {
              left: win.x,
              top: win.y,
              width: win.width,
              height: win.height,
              zIndex: win.zIndex,
          };

    return (
        <section
            className="sis-window"
            data-window-id={win.windowId}
            data-instance-id={win.instanceId}
            style={style}
            onMouseDown={() => wm.focus(win.instanceId)}
            aria-label={win.title}
        >
            <div
                className="sis-window__titlebar"
                onPointerDown={onTitlePointerDown}
                onPointerMove={onTitlePointerMove}
                onPointerUp={onTitlePointerUp}
            >
                <h2 className="sis-window__title">{win.title}</h2>
                <div className="sis-window__controls">
                    {win.minimizable !== false ? (
                        <button
                            type="button"
                            className="sis-window__control"
                            aria-label={`Minimize ${win.title}`}
                            onClick={() => wm.minimize(win.instanceId)}
                        >
                            <Minus className="size-3.5" aria-hidden />
                        </button>
                    ) : null}
                    {win.maximizable !== false ? (
                        <button
                            type="button"
                            className="sis-window__control"
                            aria-label={win.maximized ? `Restore ${win.title}` : `Maximize ${win.title}`}
                            onClick={() => wm.maximize(win.instanceId)}
                        >
                            <Square className="size-3.5" aria-hidden />
                        </button>
                    ) : null}
                    {win.closable !== false ? (
                        <button
                            type="button"
                            className="sis-window__control sis-window__control--close"
                            aria-label={`Close ${win.title}`}
                            onClick={() => wm.close(win.instanceId)}
                        >
                            <X className="size-3.5" aria-hidden />
                        </button>
                    ) : null}
                </div>
            </div>
            <div className="sis-window__body">
                <iframe
                    title={win.title}
                    src={win.href}
                    className="sis-window__frame"
                    sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-downloads"
                />
            </div>
        </section>
    );
}
