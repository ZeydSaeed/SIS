import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { getPortalRoot, placeMenu, type SisListSelectOption } from '@/components/sis/sis-list-select';

type SisMultiSelectProps = {
    /** The chosen values; empty = «الكل» (nothing is filtered). */
    values: readonly string[];
    options: readonly SisListSelectOption[];
    onChange: (values: string[]) => void;
    /** The first row and the trigger text while nothing is chosen («كل الشعب»). */
    allLabel: string;
    ariaLabel: string;
    disabled?: boolean;
    className?: string;
    triggerClassName?: string;
    menuClassName?: string;
    dir?: 'rtl' | 'ltr';
};

/** What the trigger says: «كل …», the one chosen name, the names (up to two), else «n محددة». */
export function multiSelectSummary(values: readonly string[], options: readonly SisListSelectOption[], allLabel: string, countLabel: (n: number) => string): string {
    const chosen = options.filter((option) => values.includes(option.value));
    if (chosen.length === 0) {
        return allLabel;
    }
    if (chosen.length <= 2) {
        return chosen.map((option) => option.label).join('، ');
    }

    return countLabel(chosen.length);
}

/**
 * The list control with several choices at once (same look as `SisListSelect`): a first «كل …» row clears the choice,
 * every other row toggles; the menu stays open while choosing.
 */
export function SisMultiSelect({ values, options, onChange, allLabel, ariaLabel, disabled = false, className, triggerClassName, menuClassName, dir = 'rtl' }: SisMultiSelectProps) {
    const listId = useId();
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLUListElement>(null);
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const rows = [{ value: '', label: allLabel }, ...options];
    const summary = multiSelectSummary(values, options, allLabel, (n) => `${n} / ${options.length}`);
    const isChosen = (value: string) => (value === '' ? values.length === 0 : values.includes(value));

    useLayoutEffect(() => {
        if (open && triggerRef.current !== null && menuRef.current !== null) {
            placeMenu(triggerRef.current, menuRef.current);
        }
    }, [open, rows.length, values.length]);

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
        const onReposition = () => {
            if (triggerRef.current !== null && menuRef.current !== null) {
                placeMenu(triggerRef.current, menuRef.current);
            }
        };
        document.addEventListener('pointerdown', onPointerDown, true);
        window.addEventListener('resize', onReposition);
        document.addEventListener('scroll', onReposition, true);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown, true);
            window.removeEventListener('resize', onReposition);
            document.removeEventListener('scroll', onReposition, true);
        };
    }, [open]);

    const toggle = (value: string) => {
        if (value === '') {
            onChange([]);

            return;
        }
        onChange(values.includes(value) ? values.filter((x) => x !== value) : [...values, value]);
    };

    const menu =
        open && typeof document !== 'undefined'
            ? createPortal(
                  <ul
                      ref={menuRef}
                      id={listId}
                      role="listbox"
                      aria-multiselectable="true"
                      dir={dir}
                      data-sis-list-select=""
                      data-sis-align-exempt=""
                      className={`sis-list-select__menu sis-list-select__menu--multi sis-scroll-hidden${menuClassName ? ` ${menuClassName}` : ''}`}
                      aria-label={ariaLabel}
                  >
                      {rows.map((row, index) => (
                          <li
                              key={row.value === '' ? 'all' : row.value}
                              role="option"
                              data-index={index}
                              aria-selected={isChosen(row.value)}
                              className={`sis-list-select__option${isChosen(row.value) ? ' is-selected' : ''}${index === activeIndex ? ' is-active' : ''}`}
                              onMouseEnter={() => setActiveIndex(index)}
                              onPointerDown={(event) => {
                                  event.preventDefault();
                                  event.stopPropagation();
                                  toggle(row.value);
                              }}
                          >
                              <span className="sis-list-select__check" aria-hidden="true">
                                  {isChosen(row.value) ? '✓' : ''}
                              </span>
                              {row.label}
                          </li>
                      ))}
                  </ul>,
                  getPortalRoot(),
              )
            : null;

    return (
        <span className={`sis-list-select${className ? ` ${className}` : ''}`} data-sis-list-select="">
            <button
                ref={triggerRef}
                type="button"
                role="combobox"
                disabled={disabled}
                dir={dir}
                className={`sis-list-select__trigger${triggerClassName ? ` ${triggerClassName}` : ''}`}
                aria-label={ariaLabel}
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={open ? listId : undefined}
                onPointerDown={(event) => event.stopPropagation()}
                onClick={(event) => {
                    event.preventDefault();
                    event.stopPropagation();
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
                    } else if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        setActiveIndex((current) => (current + 1) % rows.length);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        setActiveIndex((current) => (current - 1 + rows.length) % rows.length);
                    } else if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        toggle(rows[activeIndex]?.value ?? '');
                    }
                }}
            >
                <span className="sis-list-select__value">{summary}</span>
            </button>
            {menu}
        </span>
    );
}
