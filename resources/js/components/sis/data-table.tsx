import type { ReactNode } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { EmptyState } from '@/components/sis/empty-state';
import { ErrorState } from '@/components/sis/error-state';
import { LoadingState } from '@/components/sis/loading-state';
import { ariaSort, cellSortValue, SortButton, useTableSort, type SortValue } from '@/components/sis/table-sort';
import { cn } from '@/lib/utils';

export type DataTableColumn<T> = {
    id: string;
    header: ReactNode;
    cell: (row: T) => ReactNode;
    className?: string;
    hideOnMobile?: boolean;
    /** Header-click sort value (defaults to the cell's text); `false` = not sortable. */
    sort?: ((row: T) => SortValue) | false;
};

type DataTableProps<T> = {
    columns: DataTableColumn<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    loading?: boolean;
    error?: string | null;
    emptyTitle?: string;
    emptyDescription?: string;
    onRowClick?: (row: T) => void;
    getRowAriaLabel?: (row: T) => string;
    mobileCard?: (row: T) => ReactNode;
    caption?: string;
    /** When false, omits the column header row (cells still render). */
    showHeader?: boolean;
    /** Marks the row as the current selection (highlight + aria-selected). */
    isRowSelected?: (row: T) => boolean;
};

export function DataTable<T>({
    columns,
    rows,
    rowKey,
    loading = false,
    error = null,
    emptyTitle = 'No records',
    emptyDescription,
    onRowClick,
    getRowAriaLabel,
    mobileCard,
    caption,
    showHeader = true,
    isRowSelected,
}: DataTableProps<T>) {
    const isMobile = useIsMobile();
    const { rows: sortedRows, sort } = useTableSort(rows, (row, key) => {
        const column = columns.find((c) => c.id === key);
        if (!column || column.sort === false) {
            return null;
        }

        return typeof column.sort === 'function' ? column.sort(row) : cellSortValue(column.cell(row));
    });
    const sortable = (column: DataTableColumn<T>): boolean =>
        column.sort !== false && column.header !== null && column.header !== undefined && column.header !== '';

    if (loading) {
        return <LoadingState />;
    }

    if (error) {
        return <ErrorState title={error} />;
    }

    if (isMobile && mobileCard) {
        if (rows.length === 0) {
            return <EmptyState title={emptyTitle} description={emptyDescription} />;
        }

        return (
            <div className="flex flex-col gap-3" role="list">
                {sortedRows.map((row) => (
                    <div key={rowKey(row)} role="listitem">
                        {mobileCard(row)}
                    </div>
                ))}
            </div>
        );
    }

    const visibleColumns = isMobile
        ? columns.filter((column) => !column.hideOnMobile)
        : columns;

    return (
        <div className="sis-data-table overflow-x-auto" data-allow-x-scroll>
            <table>
                {caption ? (
                    <caption>
                        {caption}
                    </caption>
                ) : null}
                {showHeader ? (
                    <thead>
                        <tr>
                            {visibleColumns.map((column) => (
                                <th
                                    key={column.id}
                                    scope="col"
                                    className={column.className}
                                    aria-sort={sortable(column) ? ariaSort(sort, column.id) : undefined}
                                >
                                    {sortable(column) ? (
                                        <SortButton sort={sort} column={column.id}>
                                            {column.header}
                                        </SortButton>
                                    ) : (
                                        column.header
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                ) : null}
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={Math.max(visibleColumns.length, 1)}>
                                <div className="flex flex-col gap-1 py-6">
                                    <span className="font-medium">{emptyTitle}</span>
                                    {emptyDescription ? (
                                        <span>{emptyDescription}</span>
                                    ) : null}
                                </div>
                            </td>
                        </tr>
                    ) : (
                        sortedRows.map((row) => (
                            <tr
                                key={rowKey(row)}
                                className={cn(
                                    onRowClick && 'cursor-pointer',
                                    isRowSelected?.(row) && 'sis-data-table__row--selected',
                                )}
                                aria-selected={isRowSelected ? isRowSelected(row) : undefined}
                                onClick={onRowClick ? () => onRowClick(row) : undefined}
                                onKeyDown={
                                    onRowClick
                                        ? (event) => {
                                              if (event.key === 'Enter' || event.key === ' ') {
                                                  event.preventDefault();
                                                  onRowClick(row);
                                              }
                                          }
                                        : undefined
                                }
                                tabIndex={onRowClick ? 0 : undefined}
                                aria-label={
                                    onRowClick && getRowAriaLabel
                                        ? getRowAriaLabel(row)
                                        : undefined
                                }
                            >
                                {visibleColumns.map((column) => (
                                    <td key={column.id} className={column.className}>
                                        {column.cell(row)}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}
