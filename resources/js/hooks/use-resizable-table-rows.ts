import { useLayoutEffect, type RefObject } from 'react';

const EDGE_HIT_PX = 5;
const MIN_ROW_PX = 22;
const MAX_ROW_PX = 120;
const STORAGE_PREFIX = 'sis.table.rows:';

function readStored(storageKey: string): number | null {
    try {
        const raw = window.localStorage.getItem(STORAGE_PREFIX + storageKey);
        const value = raw === null ? NaN : Number(raw);

        return Number.isFinite(value) && value >= MIN_ROW_PX ? value : null;
    } catch {
        return null;
    }
}

function writeStored(storageKey: string, value: number | null): void {
    try {
        if (value === null) {
            window.localStorage.removeItem(STORAGE_PREFIX + storageKey);
        } else {
            window.localStorage.setItem(
                STORAGE_PREFIX + storageKey,
                String(Math.round(value)),
            );
        }
    } catch {
        // Quota / private mode — the size lasts for this page only.
    }
}

/** The body row whose bottom edge is under the pointer. */
function edgeRow(
    table: HTMLTableElement,
    clientY: number,
    clientX: number,
): HTMLTableRowElement | null {
    const body = table.tBodies[0];
    if (!body) {
        return null;
    }
    const box = table.getBoundingClientRect();
    if (clientX < box.left || clientX > box.right) {
        return null;
    }
    for (const row of Array.from(body.rows)) {
        const rect = row.getBoundingClientRect();
        if (Math.abs(clientY - rect.bottom) <= EDGE_HIT_PX) {
            return row;
        }
    }

    return null;
}

/**
 * Drag the bottom edge of any body row to set the height of every row of the table (the rows stay even, so the
 * fit-to-height page size stays exact); double-click an edge to go back to the default. The height is written as
 * `--att-row-h` on `cardRef` (unzoomed — the zoom multiplies it) and kept per `storageKey`.
 */
export function useResizableTableRows(
    tableRef: RefObject<HTMLTableElement | null>,
    cardRef: RefObject<HTMLElement | null>,
    {
        storageKey,
        zoom,
        enabled = true,
    }: { storageKey: string; zoom: number; enabled?: boolean },
): void {
    useLayoutEffect(() => {
        const table = tableRef.current;
        const card = cardRef.current;
        if (!enabled || table === null || card === null) {
            return;
        }

        const apply = (base: number | null) => {
            if (base === null) {
                card.style.removeProperty('--att-row-h');
            } else {
                card.style.setProperty('--att-row-h', `${base}px`);
            }
        };
        apply(readStored(storageKey));

        let drag: { startY: number; startHeight: number } | null = null;
        let current: number | null = readStored(storageKey);

        const onMove = (event: PointerEvent) => {
            if (drag === null) {
                card.classList.toggle(
                    'sis-attendance-table--row-hover',
                    edgeRow(table, event.clientY, event.clientX) !== null,
                );

                return;
            }
            const height = Math.min(
                MAX_ROW_PX,
                Math.max(
                    MIN_ROW_PX,
                    drag.startHeight + (event.clientY - drag.startY),
                ),
            );
            current = height / (zoom || 1);
            apply(current);
        };
        const onDown = (event: PointerEvent) => {
            if (event.button !== 0) {
                return;
            }
            const row = edgeRow(table, event.clientY, event.clientX);
            if (row === null) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            drag = {
                startY: event.clientY,
                startHeight: row.getBoundingClientRect().height,
            };
            card.classList.add('sis-attendance-table--row-resizing');
            document.body.classList.add('sis-table-row-resizing');
        };
        const onUp = () => {
            if (drag === null) {
                return;
            }
            drag = null;
            card.classList.remove('sis-attendance-table--row-resizing');
            document.body.classList.remove('sis-table-row-resizing');
            writeStored(storageKey, current);
        };
        const onDoubleClick = (event: MouseEvent) => {
            if (edgeRow(table, event.clientY, event.clientX) === null) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            current = null;
            apply(null);
            writeStored(storageKey, null);
        };

        table.addEventListener('pointerdown', onDown, true);
        table.addEventListener('dblclick', onDoubleClick, true);
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
        window.addEventListener('pointercancel', onUp);

        return () => {
            table.removeEventListener('pointerdown', onDown, true);
            table.removeEventListener('dblclick', onDoubleClick, true);
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
            window.removeEventListener('pointercancel', onUp);
            card.classList.remove(
                'sis-attendance-table--row-hover',
                'sis-attendance-table--row-resizing',
            );
            document.body.classList.remove('sis-table-row-resizing');
        };
    }, [tableRef, cardRef, storageKey, zoom, enabled]);
}
