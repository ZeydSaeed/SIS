import { useLayoutEffect, type RefObject } from 'react';

const DEFAULT_MIN_WIDTH = 48;
const EDGE_HIT_PX = 7;
const STORAGE_PREFIX = 'sis.table.cols:';

type Options = {
    storageKey: string;
    /** Bump when column set changes (e.g. checkbox column). */
    columnSignature?: string;
    minWidth?: number;
    /** Set false while the table is unmounted (e.g. empty state). */
    enabled?: boolean;
};

function isRtl(element: Element): boolean {
    return getComputedStyle(element).direction === 'rtl';
}

function readStored(storageKey: string, signature: string): number[] | null {
    try {
        const raw = window.localStorage.getItem(STORAGE_PREFIX + storageKey);

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as { signature?: string; widths?: number[] };

        if (parsed.signature !== signature || !Array.isArray(parsed.widths)) {
            return null;
        }

        return parsed.widths.filter((value) => typeof value === 'number' && value > 0);
    } catch {
        return null;
    }
}

function writeStored(storageKey: string, signature: string, widths: number[]): void {
    try {
        window.localStorage.setItem(
            STORAGE_PREFIX + storageKey,
            JSON.stringify({ signature, widths }),
        );
    } catch {
        // Quota / private mode — ignore.
    }
}

function headerCells(table: HTMLTableElement): HTMLTableCellElement[] {
    const row = table.tHead?.rows[0];

    if (!row) {
        return [];
    }

    return Array.from(row.cells);
}

function measureWidths(cells: HTMLTableCellElement[]): number[] {
    return cells.map((cell) => Math.max(DEFAULT_MIN_WIDTH, Math.round(cell.getBoundingClientRect().width)));
}

function applyWidths(table: HTMLTableElement, widths: number[]): void {
    const cells = headerCells(table);

    if (cells.length === 0 || cells.length !== widths.length) {
        return;
    }

    let total = 0;

    cells.forEach((cell, index) => {
        const width = widths[index];
        cell.style.width = `${width}px`;
        cell.style.minWidth = `${width}px`;
        cell.style.maxWidth = `${width}px`;
        total += width;
    });

    table.style.tableLayout = 'fixed';
    table.style.width = `${total}px`;
    table.style.minWidth = `${total}px`;
}

function clearWidths(table: HTMLTableElement): void {
    headerCells(table).forEach((cell) => {
        cell.style.width = '';
        cell.style.minWidth = '';
        cell.style.maxWidth = '';
    });
    table.style.width = '';
    table.style.minWidth = '';
}

function edgeForCell(cell: HTMLTableCellElement, rtl: boolean): number {
    const rect = cell.getBoundingClientRect();

    return rtl ? rect.left : rect.right;
}

function findResizeCell(
    table: HTMLTableElement,
    clientX: number,
): { cell: HTMLTableCellElement; index: number } | null {
    const cells = headerCells(table);
    const rtl = isRtl(table);

    for (let index = 0; index < cells.length; index += 1) {
        const cell = cells[index];
        const edge = edgeForCell(cell, rtl);

        if (Math.abs(clientX - edge) <= EDGE_HIT_PX) {
            return { cell, index };
        }
    }

    return null;
}

/**
 * Drag header edges to resize columns. Widths persist in localStorage per storageKey.
 */
export function useResizableTableColumns(
    tableRef: RefObject<HTMLTableElement | null>,
    {
        storageKey,
        columnSignature = 'default',
        minWidth = DEFAULT_MIN_WIDTH,
        enabled = true,
    }: Options,
): void {
    useLayoutEffect(() => {
        if (!enabled) {
            return;
        }

        const table = tableRef.current;

        if (table === null) {
            return;
        }

        table.classList.add('sis-table--cols-resizable');
        const scroller =
            table.closest('.sis-admission-drafts-table__scroller')
            ?? table.closest('.sis-admission-periods-table__scroller')
            ?? table.parentElement;

        scroller?.classList.add('sis-table-scroller--resizable');

        let widths = readStored(storageKey, columnSignature);
        const cells = headerCells(table);

        if (cells.length === 0) {
            return;
        }

        if (widths === null || widths.length !== cells.length) {
            widths = measureWidths(cells);
        }

        applyWidths(table, widths);

        let dragIndex: number | null = null;
        let dragStartX = 0;
        let dragStartWidth = 0;
        let activeWidths = widths;

        const onPointerMoveHover = (event: PointerEvent): void => {
            if (dragIndex !== null) {
                return;
            }

            const hit = findResizeCell(table, event.clientX);
            table.classList.toggle('sis-table--cols-resize-hover', hit !== null);
        };

        const onPointerDown = (event: PointerEvent): void => {
            if (event.button !== 0) {
                return;
            }

            const target = event.target;

            if (!(target instanceof Element) || !table.tHead?.contains(target)) {
                return;
            }

            const hit = findResizeCell(table, event.clientX);

            if (hit === null) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            dragIndex = hit.index;
            dragStartX = event.clientX;
            dragStartWidth = activeWidths[hit.index] ?? hit.cell.getBoundingClientRect().width;
            table.classList.add('sis-table--cols-resizing');
            document.body.classList.add('sis-table-col-resizing');
            hit.cell.setPointerCapture?.(event.pointerId);
        };

        const onPointerMove = (event: PointerEvent): void => {
            if (dragIndex === null) {
                onPointerMoveHover(event);

                return;
            }

            const rtl = isRtl(table);
            const delta = rtl ? dragStartX - event.clientX : event.clientX - dragStartX;
            const next = Math.max(minWidth, Math.round(dragStartWidth + delta));
            activeWidths = activeWidths.map((width, index) =>
                index === dragIndex ? next : width,
            );
            applyWidths(table, activeWidths);
        };

        const endDrag = (event: PointerEvent): void => {
            if (dragIndex === null) {
                return;
            }

            dragIndex = null;
            table.classList.remove('sis-table--cols-resizing');
            document.body.classList.remove('sis-table-col-resizing');
            writeStored(storageKey, columnSignature, activeWidths);

            try {
                (event.target as Element | null)?.releasePointerCapture?.(event.pointerId);
            } catch {
                // ignore
            }
        };

        const onDoubleClick = (event: MouseEvent): void => {
            const target = event.target;

            if (!(target instanceof Element) || !table.tHead?.contains(target)) {
                return;
            }

            const hit = findResizeCell(table, event.clientX);

            if (hit === null) {
                return;
            }

            event.preventDefault();
            clearWidths(table);
            activeWidths = measureWidths(headerCells(table));
            applyWidths(table, activeWidths);
            writeStored(storageKey, columnSignature, activeWidths);
        };

        table.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', endDrag);
        window.addEventListener('pointercancel', endDrag);
        table.addEventListener('dblclick', onDoubleClick);

        return () => {
            table.removeEventListener('pointerdown', onPointerDown);
            window.removeEventListener('pointermove', onPointerMove);
            window.removeEventListener('pointerup', endDrag);
            window.removeEventListener('pointercancel', endDrag);
            table.removeEventListener('dblclick', onDoubleClick);
            table.classList.remove(
                'sis-table--cols-resizable',
                'sis-table--cols-resizing',
                'sis-table--cols-resize-hover',
            );
            document.body.classList.remove('sis-table-col-resizing');
            scroller?.classList.remove('sis-table-scroller--resizable');
        };
    }, [tableRef, storageKey, columnSignature, minWidth, enabled]);
}
