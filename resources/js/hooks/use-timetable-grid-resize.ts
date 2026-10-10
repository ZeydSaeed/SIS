import { useEffect } from 'react';

/**
 * «الجدول المدرسي»: resize the tables by hand, on the heads (nothing is added to the markup):
 * - the border of a column head (either side)      → that column's width, towards the right or the left;
 * - the border of a row head (day / period, up / down) → that row's height;
 * - the bottom-left corner of a table              → the whole table (width and height together);
 * - a double click on the corner                   → back to the automatic size.
 * Sizes are written as inline style on the existing `col` / `tr` / `table` elements (a view convenience; they are
 * dropped when `resetKey` changes, e.g. on a zoom change).
 */
const ROOT = '.sis-timetable-page .sis-timetable-grid';
const EDGE = 6;
const CORNER = 16;
const MIN_COL = 28;
const MIN_ROW = 20;

type Placed = { cell: HTMLTableCellElement; row: number; col: number };

type Zone =
    | { kind: 'col'; table: HTMLTableElement; grid: HTMLElement; cell: HTMLTableCellElement; edge: 'left' | 'right' }
    | { kind: 'row'; table: HTMLTableElement; grid: HTMLElement; cell: HTMLTableCellElement; edge: 'top' | 'bottom' }
    | { kind: 'corner'; table: HTMLTableElement; grid: HTMLElement };

/** Where every cell sits in the grid (row / column, spans counted). */
function placeCells(table: HTMLTableElement): Placed[] {
    const placed: Placed[] = [];
    const taken: boolean[][] = [];
    Array.from(table.rows).forEach((tr, row) => {
        let col = 0;
        Array.from(tr.cells).forEach((cell) => {
            while (taken[row]?.[col]) {
                col += 1;
            }
            placed.push({ cell, row, col });
            for (let r = 0; r < cell.rowSpan; r++) {
                for (let c = 0; c < cell.colSpan; c++) {
                    (taken[row + r] ??= [])[col + c] = true;
                }
            }
            col += cell.colSpan;
        });
    });

    return placed;
}

function columns(table: HTMLTableElement): HTMLTableColElement[] {
    return Array.from(table.querySelectorAll<HTMLTableColElement>(':scope > colgroup > col'));
}

/** The rendered width of every column (from the cells that cover one column only). */
function columnWidths(table: HTMLTableElement, placed: Placed[], count: number): number[] {
    const widths = new Array<number>(count).fill(0);
    placed.forEach(({ cell, col }) => {
        if (cell.colSpan === 1 && col < count && widths[col] === 0) {
            widths[col] = cell.getBoundingClientRect().width;
        }
    });
    const known = widths.filter((w) => w > 0);
    const average = known.length === 0 ? table.getBoundingClientRect().width / Math.max(1, count) : known.reduce((a, b) => a + b, 0) / known.length;

    return widths.map((w) => (w > 0 ? w : average));
}

function spacingOf(table: HTMLTableElement): number {
    return Number.parseFloat(getComputedStyle(table).borderSpacing) || 0;
}

function applyColumns(table: HTMLTableElement, cols: HTMLTableColElement[], widths: number[]): void {
    cols.forEach((col, index) => {
        col.style.width = `${Math.round(widths[index] ?? MIN_COL)}px`;
    });
    const total = widths.reduce((a, b) => a + b, 0) + spacingOf(table) * (widths.length + 1);
    table.style.tableLayout = 'fixed';
    table.style.width = `${Math.round(total)}px`;
    table.style.minWidth = `${Math.round(total)}px`;
}

function clearTable(table: HTMLTableElement): void {
    columns(table).forEach((col) => col.style.removeProperty('width'));
    Array.from(table.rows).forEach((tr) => tr.style.removeProperty('height'));
    table.style.removeProperty('width');
    table.style.removeProperty('min-width');
    table.style.removeProperty('table-layout');
    table.closest<HTMLElement>('.sis-timetable-grid')?.removeAttribute('data-tt-resized');
}

export function useTimetableGridResize(enabled: boolean, resetKey: unknown): void {
    // A new zoom / format starts from the automatic sizes again.
    useEffect(() => {
        document.querySelectorAll<HTMLTableElement>(`${ROOT} table`).forEach(clearTable);
    }, [resetKey]);

    useEffect(() => {
        if (!enabled || typeof document === 'undefined') {
            return;
        }
        let hovered: HTMLElement | null = null;
        let dragging = false;

        const zoneAt = (event: PointerEvent): Zone | null => {
            const target = event.target as HTMLElement | null;
            const grid = target?.closest<HTMLElement>(ROOT) ?? null;
            const table = grid?.querySelector<HTMLTableElement>('table') ?? null;
            if (grid === null || table === null || target === null) {
                return null;
            }
            const box = grid.getBoundingClientRect();
            if (event.clientX - box.left <= CORNER && box.bottom - event.clientY <= CORNER) {
                return { kind: 'corner', table, grid };
            }
            const cell = target.closest<HTMLTableCellElement>('th');
            if (cell === null || !table.contains(cell)) {
                return null;
            }
            const r = cell.getBoundingClientRect();
            if (event.clientX - r.left <= EDGE) {
                return { kind: 'col', table, grid, cell, edge: 'left' };
            }
            if (r.right - event.clientX <= EDGE) {
                return { kind: 'col', table, grid, cell, edge: 'right' };
            }
            const inBody = cell.parentElement?.parentElement?.tagName !== 'THEAD';
            if (inBody && event.clientY - r.top <= EDGE) {
                return { kind: 'row', table, grid, cell, edge: 'top' };
            }
            if (inBody && r.bottom - event.clientY <= EDGE) {
                return { kind: 'row', table, grid, cell, edge: 'bottom' };
            }

            return null;
        };

        const cursorOf = (zone: Zone): string => (zone.kind === 'col' ? 'col-resize' : zone.kind === 'row' ? 'row-resize' : 'nesw-resize');

        const onMove = (event: PointerEvent) => {
            if (dragging) {
                return;
            }
            const zone = zoneAt(event);
            const target = event.target as HTMLElement | null;
            if (hovered !== null && (zone === null || hovered !== target)) {
                hovered.style.removeProperty('cursor');
                hovered = null;
            }
            if (zone !== null && target !== null) {
                target.style.cursor = cursorOf(zone);
                hovered = target;
            }
        };

        const startDrag = (zone: Zone, event: PointerEvent) => {
            const { table, grid } = zone;
            const placed = placeCells(table);
            const cols = columns(table);
            const count = Math.max(cols.length, ...placed.map((p) => p.col + p.cell.colSpan));
            const widths = columnWidths(table, placed, count);
            // Every column / row gets its measured size first, so only the chosen one changes.
            applyColumns(table, cols, widths);
            const rows = Array.from(table.rows);
            const heights = rows.map((tr) => tr.getBoundingClientRect().height);
            grid.setAttribute('data-tt-resized', 'yes');
            const startX = event.clientX;
            const startY = event.clientY;
            const startWidth = table.getBoundingClientRect().width;
            const startHeight = table.getBoundingClientRect().height;
            const cursor = cursorOf(zone);
            document.body.style.cursor = cursor;
            document.body.style.userSelect = 'none';
            dragging = true;

            const entry = zone.kind === 'corner' ? null : (placed.find((p) => p.cell === zone.cell) ?? null);

            const move = (e: PointerEvent) => {
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                if (zone.kind === 'col' && entry !== null) {
                    const index = entry.col + entry.cell.colSpan - 1;
                    const next = widths.slice();
                    next[index] = Math.max(MIN_COL, (widths[index] ?? MIN_COL) + (zone.edge === 'right' ? dx : -dx));
                    applyColumns(table, cols, next);
                } else if (zone.kind === 'row' && entry !== null) {
                    const span = Math.max(1, entry.cell.rowSpan);
                    const delta = (zone.edge === 'bottom' ? dy : -dy) / span;
                    for (let r = entry.row; r < entry.row + span; r++) {
                        const tr = rows[r];
                        if (tr !== undefined) {
                            tr.style.height = `${Math.round(Math.max(MIN_ROW, (heights[r] ?? MIN_ROW) + delta))}px`;
                        }
                    }
                } else if (zone.kind === 'corner') {
                    // The table's left edge follows the pointer (the page reads right to left): dragging left grows it.
                    const newWidth = Math.max(220, startWidth - dx);
                    const newHeight = Math.max(100, startHeight + dy);
                    const sx = newWidth / startWidth;
                    const sy = newHeight / startHeight;
                    applyColumns(table, cols, widths.map((w) => Math.max(MIN_COL, w * sx)));
                    rows.forEach((tr, index) => {
                        tr.style.height = `${Math.round(Math.max(MIN_ROW, (heights[index] ?? MIN_ROW) * sy))}px`;
                    });
                }
            };
            const end = () => {
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', end);
                document.removeEventListener('pointercancel', end);
                document.body.style.removeProperty('cursor');
                document.body.style.removeProperty('user-select');
                dragging = false;
            };
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', end);
            document.addEventListener('pointercancel', end);
        };

        const onDown = (event: PointerEvent) => {
            if (event.button !== 0 || dragging) {
                return;
            }
            const zone = zoneAt(event);
            if (zone === null) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            startDrag(zone, event);
        };

        const onDouble = (event: MouseEvent) => {
            const target = event.target as HTMLElement | null;
            const grid = target?.closest<HTMLElement>(ROOT) ?? null;
            const table = grid?.querySelector<HTMLTableElement>('table') ?? null;
            if (grid === null || table === null) {
                return;
            }
            const box = grid.getBoundingClientRect();
            if (event.clientX - box.left <= CORNER && box.bottom - event.clientY <= CORNER) {
                clearTable(table);
            }
        };

        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerdown', onDown, true);
        document.addEventListener('dblclick', onDouble);

        return () => {
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerdown', onDown, true);
            document.removeEventListener('dblclick', onDouble);
            hovered?.style.removeProperty('cursor');
        };
    }, [enabled]);
}
