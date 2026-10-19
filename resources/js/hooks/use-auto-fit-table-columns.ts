import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';

/** The smallest font scale of a squeezed column (below it the text is clipped). */
const MIN_FIT_SCALE = 0.62;
const MIN_COLUMN = 36;
/** Columns that keep their own narrow width (the checkbox and the sequence number). */
const FIXED_CLASSES = [
    'sis-admission-drafts-table__select',
    'sis-admission-drafts-table__num',
    'sis-admission-periods-table__num',
];

type Options = {
    storageKey: string;
    columnSignature: string;
    enabled?: boolean;
    /** Changes whenever the rows on screen change (ids, page, filter), so the columns are fitted to them again. */
    rowsKey?: string;
};

function isFixed(cell: HTMLTableCellElement): boolean {
    return FIXED_CLASSES.some((name) => cell.classList.contains(name));
}

/**
 * The list-table behaviour of every themed page (one source: attendance tables behave the same):
 *  - each column opens as wide as its longest text (header and rows) and is fitted again when the rows change;
 *  - the table stretches to its card, so a wider room (sidebar or section rail folded) is filled at once;
 *  - when the room is too narrow the columns shrink in proportion, never below their header, and a squeezed column's
 *    font is reduced (down to MIN_FIT_SCALE) so its text stays whole — back to full size when the column is wide again;
 *  - the room is read again whenever the card's width settles (sidebar / rail / side cards opening or folding).
 * Hand-resizing a column header edge still works (the same column hook underneath).
 */
export function useAutoFitTableColumns(
    tableRef: RefObject<HTMLTableElement | null>,
    scrollerRef: RefObject<HTMLElement | null>,
    { storageKey, columnSignature, enabled = true, rowsKey = '' }: Options,
): void {
    const [roomWidth, setRoomWidth] = useState(0);
    /** Each column's width with its text at full size (set by the last measure). */
    const neededRef = useRef<number[]>([]);

    useEffect(() => {
        const scroller = scrollerRef.current;
        if (!enabled || scroller === null) {
            return;
        }
        let timer = 0;
        const observer = new ResizeObserver(() => {
            window.clearTimeout(timer);
            timer = window.setTimeout(
                () => setRoomWidth(Math.round(scroller.clientWidth / 4) * 4),
                160,
            );
        });
        observer.observe(scroller);

        return () => {
            observer.disconnect();
            window.clearTimeout(timer);
        };
    }, [enabled, scrollerRef]);

    const measure = useCallback(
        (cells: HTMLTableCellElement[]): number[] => {
            const table = tableRef.current;
            if (table === null || cells.length === 0) {
                return cells.map((cell) => Math.ceil(cell.offsetWidth));
            }
            cells.forEach((cell) => {
                cell.style.width = '';
                cell.style.minWidth = '';
                cell.style.maxWidth = '';
            });
            table.style.tableLayout = '';
            table.style.minWidth = '';
            table.style.maxWidth = '';
            table
                .querySelectorAll<HTMLElement>('th, td')
                .forEach((cell) => cell.style.removeProperty('--fit-scale'));

            // Squeezed to 1px with one-line cells (class rules in app.css), every column falls back to its longest text.
            const inlineWidth = table.style.getPropertyValue('width');
            table.classList.add('sis-table--cols-measuring');
            table.style.setProperty('width', '1px', 'important');
            const body = table.tBodies[0];
            const natural = cells.map((cell) =>
                Math.ceil(cell.getBoundingClientRect().width),
            );
            // The header alone: the least a column may shrink to.
            const bodyDisplay = body?.style.display ?? '';
            if (body) {
                body.style.display = 'none';
            }
            const headers = cells.map((cell) =>
                Math.ceil(cell.getBoundingClientRect().width),
            );
            if (body) {
                body.style.display = bodyDisplay;
            }
            table.style.setProperty('width', inlineWidth);
            table.classList.remove('sis-table--cols-measuring');

            const needed = natural.map((width, index) =>
                Math.max(width, headers[index] ?? 0, MIN_COLUMN),
            );
            neededRef.current = needed.slice();
            const floor = cells.map((cell, index) =>
                isFixed(cell)
                    ? needed[index]
                    : Math.min(needed[index], Math.max(headers[index] ?? 0, MIN_COLUMN)),
            );
            const room = (scrollerRef.current?.clientWidth ?? 0) - cells.length - 4;
            const total = needed.reduce((sum, width) => sum + width, 0);
            if (room <= 0 || total <= room) {
                return needed;
            }
            // Too narrow: shrink in proportion to what each column can give (down to its header).
            const give = needed.map((width, index) => width - floor[index]);
            const spare = give.reduce((sum, value) => sum + value, 0);
            const cut = Math.min(spare, total - room);

            return needed.map((width, index) =>
                spare > 0 ? Math.floor(width - (give[index] * cut) / spare) : width,
            );
        },
        // The measure reads refs only; it is replaced whenever the signature (and so the fit) changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [columnSignature, rowsKey, roomWidth],
    );

    useResizableTableColumns(tableRef, {
        storageKey,
        columnSignature: `${columnSignature}:${rowsKey}:w${roomWidth}`,
        enabled,
        fitToContent: true,
        measure,
    });

    // A squeezed column gets a smaller font so its text stays whole; back to full size when it is wide enough.
    const applyFitScale = useCallback(() => {
        const table = tableRef.current;
        const head = table?.tHead?.rows[0]?.cells;
        if (!table || !head) {
            return;
        }
        const scales = Array.from(head).map((cell, index) => {
            const needed = neededRef.current[index];
            if (!needed || isFixed(cell)) {
                return 1;
            }
            const style = getComputedStyle(cell);
            const pad =
                Number.parseFloat(style.paddingLeft) +
                Number.parseFloat(style.paddingRight) +
                1;
            const have = cell.offsetWidth - pad;
            const want = needed - pad;
            if (want <= 0 || have >= want) {
                return 1;
            }

            return Math.max(MIN_FIT_SCALE, Math.round((have / want) * 100) / 100);
        });
        Array.from(table.rows).forEach((row) => {
            if (row.cells.length !== head.length) {
                return;
            }
            Array.from(row.cells).forEach((cell, index) => {
                const scale = scales[index] ?? 1;
                if (scale >= 1) {
                    cell.style.removeProperty('--fit-scale');
                } else {
                    cell.style.setProperty('--fit-scale', String(scale));
                }
            });
        });
    }, [tableRef]);

    useLayoutEffect(() => {
        const table = tableRef.current;
        if (!enabled || table === null || table.style.tableLayout !== 'fixed') {
            return;
        }
        // Stretch to the card: the browser spreads a wider room over the columns at once.
        table.style.setProperty('width', '100%');
        applyFitScale();
    }, [applyFitScale, columnSignature, enabled, rowsKey, roomWidth, tableRef]);

    useEffect(() => {
        const table = tableRef.current;
        if (!enabled || table === null) {
            return;
        }
        let frame = 0;
        const observer = new ResizeObserver(() => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(applyFitScale);
        });
        observer.observe(table);

        return () => {
            observer.disconnect();
            cancelAnimationFrame(frame);
        };
    }, [applyFitScale, enabled, tableRef]);
}
