import { ArrowDownWideNarrow, ArrowUpNarrowWide } from 'lucide-react';
import {
    isValidElement,
    useCallback,
    useMemo,
    useState,
    type ReactNode,
} from 'react';

/**
 * Click-to-sort column headers (same look as «القاعات الدراسية»: the header text is the button, a small arrow shows
 * the direction). Client tables sort the rows they hold; server-paged tables sort the page on screen.
 */
export type SortDirection = 'asc' | 'desc';

export type SortValue = string | number | boolean | null | undefined;

const collator = new Intl.Collator('ar', {
    numeric: true,
    sensitivity: 'base',
});

function isEmpty(v: SortValue): boolean {
    return v === null || v === undefined || v === '';
}

function compareValues(
    a: NonNullable<SortValue>,
    b: NonNullable<SortValue>,
): number {
    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }
    if (typeof a === 'boolean' && typeof b === 'boolean') {
        return Number(a) - Number(b);
    }

    return collator.compare(String(a), String(b));
}

/** Stable sort by `value`; empty values stay at the bottom in both directions, ties keep the original order. */
export function sortRows<T>(
    rows: T[],
    value: (row: T) => SortValue,
    direction: SortDirection,
): T[] {
    const sign = direction === 'asc' ? 1 : -1;

    return rows
        .map((row, index) => ({ row, index, v: value(row) }))
        .sort((a, b) => {
            if (isEmpty(a.v) || isEmpty(b.v)) {
                return isEmpty(a.v) === isEmpty(b.v)
                    ? a.index - b.index
                    : isEmpty(a.v)
                      ? 1
                      : -1;
            }

            return (
                sign *
                    compareValues(
                        a.v as NonNullable<SortValue>,
                        b.v as NonNullable<SortValue>,
                    ) || a.index - b.index
            );
        })
        .map((entry) => entry.row);
}

export type TableSort = {
    key: string | null;
    direction: SortDirection;
    toggle: (key: string) => void;
};

/** Which column is sorted and which way (header click: ascending, again: descending). */
export function useSortState(): TableSort {
    const [key, setKey] = useState<string | null>(null);
    const [direction, setDirection] = useState<SortDirection>('asc');

    const toggle = useCallback(
        (next: string) => {
            setDirection(key === next && direction === 'asc' ? 'desc' : 'asc');
            setKey(next);
        },
        [key, direction],
    );

    return { key, direction, toggle };
}

/** Sorts `rows` by the column the user picked; `value(row, key)` gives the cell's sort value. Empty values go last. */
export function useTableSort<T>(
    rows: T[],
    value: (row: T, key: string) => SortValue,
): { rows: T[]; sort: TableSort } {
    const sort = useSortState();
    const { key, direction } = sort;

    const sorted = useMemo(
        () =>
            key === null
                ? rows
                : sortRows(rows, (row) => value(row, key), direction),
        // `value` is an inline accessor; re-sorting follows the rows and the chosen column.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [rows, key, direction],
    );

    return { rows: sorted, sort };
}

/** Sort value of a rendered cell: its plain text (numbers stay numbers) — for column-config tables. */
export function cellSortValue(node: ReactNode): SortValue {
    if (typeof node === 'number' || typeof node === 'boolean') {
        return node;
    }
    const text = nodeText(node).trim();
    const numeric = text.replace(/[٠-٩]/g, (d) =>
        String(d.charCodeAt(0) - 0x0660),
    );

    return /^-?\d+(\.\d+)?%?$/.test(numeric)
        ? Number.parseFloat(numeric)
        : text;
}

function nodeText(node: ReactNode): string {
    if (node === null || node === undefined || typeof node === 'boolean') {
        return '';
    }
    if (typeof node === 'string' || typeof node === 'number') {
        return String(node);
    }
    if (Array.isArray(node)) {
        return node.map(nodeText).join(' ');
    }
    if (isValidElement<{ children?: ReactNode; title?: unknown }>(node)) {
        const inner = nodeText(node.props.children);

        return inner !== ''
            ? inner
            : typeof node.props.title === 'string'
              ? node.props.title
              : '';
    }

    return '';
}

export function ariaSort(
    sort: Pick<TableSort, 'key' | 'direction'>,
    key: string,
): 'ascending' | 'descending' | 'none' {
    return sort.key === key
        ? sort.direction === 'asc'
            ? 'ascending'
            : 'descending'
        : 'none';
}

/** The header's content: its label as a button, with the direction arrow on the sorted column. */
export function SortButton({
    sort,
    column,
    children,
}: {
    sort: Pick<TableSort, 'key' | 'direction'> & {
        toggle: (key: string) => void;
    };
    column: string;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            className="sis-students-table__sort"
            onClick={(event) => {
                event.stopPropagation();
                sort.toggle(column);
            }}
        >
            {children}
            {sort.key === column ? (
                sort.direction === 'asc' ? (
                    <ArrowUpNarrowWide aria-hidden width={12} height={12} />
                ) : (
                    <ArrowDownWideNarrow aria-hidden width={12} height={12} />
                )
            ) : null}
        </button>
    );
}
