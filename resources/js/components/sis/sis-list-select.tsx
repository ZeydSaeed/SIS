import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

export type SisListSelectOption = {
    value: string;
    label: string;
};

type SisListSelectProps = {
    value: string;
    options: readonly SisListSelectOption[];
    onChange: (value: string) => void;
    ariaLabel: string;
    name?: string;
    id?: string;
    required?: boolean;
    disabled?: boolean;
    includeBlank?: boolean;
    className?: string;
    triggerClassName?: string;
    dir?: 'rtl' | 'ltr';
    variant?: 'field' | 'overlay';
};

const MENU_MAX_PX = 192;

function optionId(listId: string, value: string): string {
    return `${listId}-opt-${value === '' ? 'blank' : value}`;
}

function placeMenu(trigger: HTMLElement, menu: HTMLElement): void {
    const rect = trigger.getBoundingClientRect();
    const gutter = 4;
    const spaceBelow = window.innerHeight - rect.bottom - gutter;
    const spaceAbove = rect.top - gutter;
    const openBelow = spaceBelow >= 96 || spaceBelow >= spaceAbove;
    const available = Math.max(96, openBelow ? spaceBelow : spaceAbove);

    menu.style.minWidth = `${Math.ceil(rect.width)}px`;
    menu.style.maxHeight = `${Math.min(MENU_MAX_PX, available)}px`;

    const width = Math.max(rect.width, menu.offsetWidth);
    let left = rect.right - width;
    if (left < gutter) {
        left = gutter;
    }
    if (left + width > window.innerWidth - gutter) {
        left = Math.max(gutter, window.innerWidth - width - gutter);
    }

    menu.style.left = `${Math.round(left)}px`;
    menu.style.width = `${Math.round(width)}px`;

    if (openBelow) {
        menu.style.top = `${Math.round(rect.bottom + 2)}px`;
        menu.style.bottom = 'auto';
    } else {
        menu.style.top = 'auto';
        menu.style.bottom = `${Math.round(window.innerHeight - rect.top + 2)}px`;
    }
}

/** Custom list control: hidden vertical scrollbar, keyboard + wheel browsing. */
export function SisListSelect({
    value,
    options,
    onChange,
    ariaLabel,
    name,
    id,
    required = false,
    disabled = false,
    includeBlank = false,
    className,
    triggerClassName,
    dir = 'rtl',
    variant = 'field',
}: SisListSelectProps) {
    const listId = useId();
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLUListElement>(null);
    const [open, setOpen] = useState(false);
    const items = includeBlank ? [{ value: '', label: '' }, ...options] : [...options];
    const selected = items.find((item) => item.value === value) ?? items[0];
    const selectedIndex = Math.max(
        0,
        items.findIndex((item) => item.value === value),
    );
    const [activeIndex, setActiveIndex] = useState(selectedIndex);

    useEffect(() => {
        if (open) {
            setActiveIndex(selectedIndex);
        }
    }, [open, selectedIndex]);

    useLayoutEffect(() => {
        if (!open || triggerRef.current === null || menuRef.current === null) {
            return;
        }

        placeMenu(triggerRef.current, menuRef.current);
        menuRef.current
            .querySelector<HTMLElement>('[aria-selected="true"]')
            ?.scrollIntoView({ block: 'nearest' });
    }, [open, items.length, value]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: PointerEvent) => {
            const target = event.target as Node | null;
            if (triggerRef.current?.contains(target) || menuRef.current?.contains(target)) {
                return;
            }

            setOpen(false);
        };
        const onReposition = (event: Event) => {
            if (menuRef.current?.contains(event.target as Node)) {
                return;
            }

            setOpen(false);
        };

        document.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('resize', onReposition);
        document.addEventListener('scroll', onReposition, true);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            window.removeEventListener('resize', onReposition);
            document.removeEventListener('scroll', onReposition, true);
        };
    }, [open]);

    const choose = (next: string) => {
        onChange(next);
        setOpen(false);
        triggerRef.current?.focus();
    };

    const moveActive = (delta: number) => {
        if (items.length === 0) {
            return;
        }

        setActiveIndex((current) => {
            const next = (current + delta + items.length) % items.length;
            requestAnimationFrame(() => {
                menuRef.current
                    ?.querySelector<HTMLElement>(`[data-index="${next}"]`)
                    ?.scrollIntoView({ block: 'nearest' });
            });

            return next;
        });
    };

    const menu =
        open && typeof document !== 'undefined'
            ? createPortal(
                  <ul
                      ref={menuRef}
                      id={listId}
                      role="listbox"
                      dir={dir}
                      data-sis-list-select=""
                      data-sis-align-exempt=""
                      className="sis-list-select__menu sis-scroll-hidden"
                      aria-label={ariaLabel}
                  >
                      {items.map((item, index) => (
                          <li
                              key={item.value === '' ? 'blank' : item.value}
                              id={optionId(listId, item.value)}
                              role="option"
                              data-index={index}
                              aria-selected={item.value === value}
                              className={`sis-list-select__option${item.value === value ? ' is-selected' : ''}${index === activeIndex ? ' is-active' : ''}`}
                              onMouseEnter={() => setActiveIndex(index)}
                              onPointerDown={(event) => {
                                  event.preventDefault();
                                  choose(item.value);
                              }}
                          >
                              {item.label === '' ? '\u00a0' : item.label}
                          </li>
                      ))}
                  </ul>,
                  document.body,
              )
            : null;

    return (
        <span
            className={`sis-list-select${variant === 'overlay' ? ' sis-list-select--overlay' : ''}${className ? ` ${className}` : ''}`}
            data-sis-list-select=""
        >
            {name ? <input type="hidden" name={name} value={value} required={required} /> : null}
            <button
                ref={triggerRef}
                id={id}
                type="button"
                role="combobox"
                disabled={disabled}
                dir={dir}
                className={`sis-list-select__trigger${variant === 'overlay' ? ' sis-list-select__trigger--overlay' : ''}${triggerClassName ? ` ${triggerClassName}` : ''}`}
                aria-label={ariaLabel}
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={open ? listId : undefined}
                aria-activedescendant={
                    open ? optionId(listId, items[activeIndex]?.value ?? '') : undefined
                }
                aria-required={required || undefined}
                onClick={() => {
                    if (!disabled) {
                        setOpen((current) => !current);
                    }
                }}
                onKeyDown={(event) => {
                    if (disabled) {
                        return;
                    }

                    if (!open && (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ')) {
                        event.preventDefault();
                        setOpen(true);

                        return;
                    }

                    if (!open) {
                        return;
                    }

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        setOpen(false);

                        return;
                    }

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        moveActive(1);

                        return;
                    }

                    if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        moveActive(-1);

                        return;
                    }

                    if (event.key === 'Home') {
                        event.preventDefault();
                        setActiveIndex(0);

                        return;
                    }

                    if (event.key === 'End') {
                        event.preventDefault();
                        setActiveIndex(items.length - 1);

                        return;
                    }

                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        choose(items[activeIndex]?.value ?? value);
                    }
                }}
            >
                {variant === 'overlay' ? null : (
                    <span className="sis-list-select__value">
                        {selected?.label === '' ? '\u00a0' : selected?.label}
                    </span>
                )}
            </button>
            {menu}
        </span>
    );
}
