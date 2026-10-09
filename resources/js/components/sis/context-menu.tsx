import type { LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useLayoutEffect, useRef, useState, type MouseEvent as ReactMouseEvent, type ReactElement } from 'react';
import { createPortal } from 'react-dom';

/**
 * «قائمة الزر الأيمن» — one context menu for every SIS surface. It reuses the SIS list-menu look
 * (`sis-list-select__menu` / `__option`), opens at the pointer (or under the focused element from the keyboard:
 * Shift+F10 / the context-menu key), stays inside the viewport, follows full screen, and closes on Escape,
 * an outside click, scroll or resize. Items are plain data — the page decides what each entity offers.
 */
export type ContextMenuItem = {
    id: string;
    label: string;
    icon?: LucideIcon;
    onSelect: () => void;
    disabled?: boolean;
    /** A destructive action (delete, take out of service). */
    danger?: boolean;
    /** Draw a separator above this item. */
    separator?: boolean;
    /** Shown at the inline end (e.g. a keyboard hint). */
    hint?: string;
};

type MenuState<T> = { x: number; y: number; payload: T } | null;

export type ContextMenuController<T> = {
    state: MenuState<T>;
    /** Wire to `onContextMenu` of any element. */
    open: (event: ReactMouseEvent<HTMLElement> | MouseEvent, payload: T) => void;
    /** Opens from the keyboard under an element. */
    openAt: (element: HTMLElement, payload: T) => void;
    close: () => void;
};

export function useContextMenu<T>(): ContextMenuController<T> {
    const [state, setState] = useState<MenuState<T>>(null);
    const open = useCallback((event: ReactMouseEvent<HTMLElement> | MouseEvent, payload: T) => {
        event.preventDefault();
        event.stopPropagation();
        setState({ x: event.clientX, y: event.clientY, payload });
    }, []);
    const openAt = useCallback((element: HTMLElement, payload: T) => {
        const rect = element.getBoundingClientRect();
        setState({ x: rect.right - 8, y: rect.top + Math.min(rect.height, 24), payload });
    }, []);
    const close = useCallback(() => setState(null), []);

    return { state, open, openAt, close };
}

/** Keyboard access: Shift+F10 or the «ContextMenu» key on a focused element opens its menu. */
export function isContextMenuKey(event: { key: string; shiftKey: boolean }): boolean {
    return event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10');
}

const GUTTER = 6;

function portalRoot(): HTMLElement {
    return (document.fullscreenElement as HTMLElement | null) ?? document.body;
}

export function SisContextMenu<T>({
    controller,
    items,
    label,
}: {
    controller: ContextMenuController<T>;
    /** The items for the clicked entity; an empty list keeps the menu closed. */
    items: (payload: T) => ContextMenuItem[];
    label: string;
}): ReactElement | null {
    const { state, close } = controller;
    const menuRef = useRef<HTMLUListElement>(null);
    const list = state === null ? [] : items(state.payload);
    const enabled = list.map((item, index) => (item.disabled ? -1 : index)).filter((index) => index >= 0);
    const [active, setActive] = useState(-1);

    useLayoutEffect(() => {
        const menu = menuRef.current;
        if (state === null || menu === null) {
            return;
        }
        // Open towards the inline start in RTL, then keep the whole menu on screen.
        const width = menu.offsetWidth;
        const height = menu.offsetHeight;
        let left = state.x - width;
        if (left < GUTTER) {
            left = Math.min(state.x, window.innerWidth - width - GUTTER);
        }
        const top = state.y + height > window.innerHeight - GUTTER ? Math.max(GUTTER, state.y - height) : state.y;
        menu.style.left = `${Math.round(Math.max(GUTTER, left))}px`;
        menu.style.top = `${Math.round(top)}px`;
        menu.focus();
        setActive(enabled[0] ?? -1);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [state]);

    useEffect(() => {
        if (state === null) {
            return;
        }
        const onPointerDown = (event: PointerEvent) => {
            if (!menuRef.current?.contains(event.target as Node | null)) {
                close();
            }
        };
        const onClose = () => close();
        document.addEventListener('pointerdown', onPointerDown, true);
        window.addEventListener('resize', onClose);
        document.addEventListener('scroll', onClose, true);
        window.addEventListener('blur', onClose);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown, true);
            window.removeEventListener('resize', onClose);
            document.removeEventListener('scroll', onClose, true);
            window.removeEventListener('blur', onClose);
        };
    }, [state, close]);

    if (state === null || list.length === 0 || typeof document === 'undefined') {
        return null;
    }

    const choose = (item: ContextMenuItem) => {
        if (item.disabled) {
            return;
        }
        close();
        item.onSelect();
    };
    const step = (delta: number) => {
        if (enabled.length === 0) {
            return;
        }
        const at = enabled.indexOf(active);
        setActive(enabled[(at + delta + enabled.length) % enabled.length] ?? -1);
    };

    return createPortal(
        <ul
            ref={menuRef}
            role="menu"
            tabIndex={-1}
            dir="rtl"
            aria-label={label}
            data-sis-list-select=""
            data-sis-align-exempt=""
            className="sis-list-select__menu sis-scroll-hidden sis-context-menu"
            onContextMenu={(event) => event.preventDefault()}
            onKeyDown={(event) => {
                if (event.key === 'Escape' || event.key === 'Tab') {
                    event.preventDefault();
                    close();
                } else if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    step(1);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    step(-1);
                } else if (event.key === 'Home') {
                    event.preventDefault();
                    setActive(enabled[0] ?? -1);
                } else if (event.key === 'End') {
                    event.preventDefault();
                    setActive(enabled[enabled.length - 1] ?? -1);
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    const item = list[active];
                    if (item !== undefined) {
                        choose(item);
                    }
                }
            }}
        >
            {list.map((item, index) => {
                const Icon = item.icon;

                return [
                    item.separator && index > 0 ? <li key={`${item.id}-sep`} role="separator" className="sis-context-menu__separator" /> : null,
                    <li
                        key={item.id}
                        role="menuitem"
                        aria-disabled={item.disabled || undefined}
                        className={`sis-list-select__option sis-context-menu__item${index === active ? ' is-active' : ''}${item.disabled ? ' is-disabled' : ''}${item.danger ? ' sis-context-menu__item--danger' : ''}`}
                        onMouseEnter={() => (item.disabled ? undefined : setActive(index))}
                        onPointerDown={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            choose(item);
                        }}
                    >
                        {Icon ? <Icon aria-hidden className="sis-context-menu__icon" /> : <span className="sis-context-menu__icon" aria-hidden />}
                        <span className="sis-context-menu__label">{item.label}</span>
                        {item.hint ? (
                            <span className="sis-context-menu__hint" dir="ltr">
                                {item.hint}
                            </span>
                        ) : null}
                    </li>,
                ];
            })}
        </ul>,
        portalRoot(),
    );
}
