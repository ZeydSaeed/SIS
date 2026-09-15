import { useCallback, useRef, type PointerEvent as ReactPointerEvent } from 'react';
import { WindowControls } from '@/components/window-controls';
import { t } from '@/i18n';
import { useWindowManager } from '@/window/window-manager-context';
import type { SisOpenWindow } from '@/window/types';

type Props = {
    window: SisOpenWindow;
};

type ResizeEdge = 'n' | 's' | 'e' | 'w' | 'ne' | 'nw' | 'se' | 'sw';

export function WindowFrame({ window: win }: Props) {
    const wm = useWindowManager();
    const dragRef = useRef<{ ox: number; oy: number; sx: number; sy: number } | null>(null);
    const resizeRef = useRef<{
        edge: ResizeEdge;
        ox: number;
        oy: number;
        sx: number;
        sy: number;
        sw: number;
        sh: number;
    } | null>(null);
    const i18n = t();
    const focused = wm.focusedInstanceId === win.instanceId;

    const onTitlePointerDown = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            if (win.maximized || win.movable === false) {
                return;
            }
            if ((event.target as HTMLElement).closest('.sis-window__controls')) {
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

    const onTitleDoubleClick = useCallback(() => {
        if (win.maximizable === false) {
            return;
        }
        wm.maximize(win.instanceId);
    }, [wm, win.instanceId, win.maximizable]);

    const onResizePointerDown = useCallback(
        (edge: ResizeEdge) => (event: ReactPointerEvent<HTMLDivElement>) => {
            if (win.maximized || win.resizable === false) {
                return;
            }
            event.stopPropagation();
            event.currentTarget.setPointerCapture(event.pointerId);
            resizeRef.current = {
                edge,
                ox: event.clientX,
                oy: event.clientY,
                sx: win.x,
                sy: win.y,
                sw: win.width,
                sh: win.height,
            };
            wm.focus(win.instanceId);
        },
        [wm, win],
    );

    const onResizePointerMove = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            const state = resizeRef.current;
            if (!state) {
                return;
            }
            const dx = event.clientX - state.ox;
            const dy = event.clientY - state.oy;
            const min = win.minimumSize ?? { width: 480, height: 360 };
            let x = state.sx;
            let y = state.sy;
            let width = state.sw;
            let height = state.sh;

            if (state.edge.includes('e')) {
                width = Math.max(min.width, state.sw + dx);
            }
            if (state.edge.includes('s')) {
                height = Math.max(min.height, state.sh + dy);
            }
            if (state.edge.includes('w')) {
                width = Math.max(min.width, state.sw - dx);
                x = state.sx + (state.sw - width);
            }
            if (state.edge.includes('n')) {
                height = Math.max(min.height, state.sh - dy);
                y = state.sy + (state.sh - height);
            }

            wm.move(win.instanceId, x, y);
            wm.resize(win.instanceId, width, height);
        },
        [wm, win.instanceId, win.minimumSize],
    );

    const onResizePointerUp = useCallback((event: ReactPointerEvent<HTMLDivElement>) => {
        if (resizeRef.current) {
            event.currentTarget.releasePointerCapture(event.pointerId);
            resizeRef.current = null;
        }
    }, []);

    if (win.minimized) {
        return null;
    }

    const style = win.maximized
        ? {
              insetInlineStart: 0,
              top: 0,
              width: '100%',
              height: '100%',
              zIndex: win.zIndex,
          }
        : {
              insetInlineStart: win.x,
              top: win.y,
              width: win.width,
              height: win.height,
              zIndex: win.zIndex,
          };

    const edges: ResizeEdge[] = ['n', 's', 'e', 'w', 'ne', 'nw', 'se', 'sw'];

    return (
        <section
            className={`sis-window${focused ? ' sis-window--focused' : ''}`}
            role="dialog"
            aria-modal="false"
            aria-labelledby={`sis-window-title-${win.instanceId}`}
            data-window-id={win.windowId}
            data-instance-id={win.instanceId}
            style={style}
            onMouseDown={() => wm.focus(win.instanceId)}
        >
            <div
                className="sis-window__titlebar"
                onPointerDown={onTitlePointerDown}
                onPointerMove={onTitlePointerMove}
                onPointerUp={onTitlePointerUp}
                onDoubleClick={onTitleDoubleClick}
            >
                <h2 id={`sis-window-title-${win.instanceId}`} className="sis-window__title">
                    {win.title}
                </h2>
                <WindowControls
                    label={`${win.title} — ${i18n.window.controls}`}
                    minimizeLabel={`${i18n.window.minimize}: ${win.title}`}
                    maximizeLabel={`${i18n.window.maximize}: ${win.title}`}
                    restoreLabel={`${i18n.window.restore}: ${win.title}`}
                    closeLabel={`${i18n.window.close}: ${win.title}`}
                    maximized={win.maximized}
                    minimizable={win.minimizable !== false}
                    maximizable={win.maximizable !== false}
                    closable={win.closable !== false}
                    onMinimize={() => wm.minimize(win.instanceId)}
                    onMaximize={() => wm.maximize(win.instanceId)}
                    onClose={() => wm.close(win.instanceId)}
                />
            </div>
            <div className="sis-window__body">
                <iframe
                    title={win.title}
                    src={win.href}
                    className="sis-window__frame"
                    sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-downloads"
                />
            </div>
            {!win.maximized && win.resizable !== false
                ? edges.map((edge) => (
                      <div
                          key={edge}
                          className={`sis-window__edge sis-window__edge--${edge}`}
                          onPointerDown={onResizePointerDown(edge)}
                          onPointerMove={onResizePointerMove}
                          onPointerUp={onResizePointerUp}
                      />
                  ))
                : null}
        </section>
    );
}
