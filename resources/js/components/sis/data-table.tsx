import type { ReactNode } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { EmptyState } from '@/components/sis/empty-state';
import { ErrorState } from '@/components/sis/error-state';
import { LoadingState } from '@/components/sis/loading-state';
import { cn } from '@/lib/utils';

export type DataTableColumn<T> = {
    id: string;
    header: ReactNode;
    cell: (row: T) => ReactNode;
    className?: string;
    hideOnMobile?: boolean;
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
}: DataTableProps<T>) {
    const isMobile = useIsMobile();

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
                {rows.map((row) => (
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
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-max min-w-full text-sm">
                {caption ? (
                    <caption className="bg-muted/30 border-b px-4 py-2 text-start text-sm font-medium">
                        {caption}
                    </caption>
                ) : null}
                <thead className="bg-muted/50">
                    <tr>
                        {visibleColumns.map((column) => (
                            <th
                                key={column.id}
                                scope="col"
                                className={cn(
                                    'whitespace-nowrap px-4 py-3 text-start font-medium',
                                    column.className,
                                )}
                            >
                                {column.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td
                                colSpan={Math.max(visibleColumns.length, 1)}
                                className="text-muted-foreground px-4 py-10 text-center"
                            >
                                <div className="flex flex-col gap-1">
                                    <span className="text-foreground font-medium">{emptyTitle}</span>
                                    {emptyDescription ? (
                                        <span className="text-sm">{emptyDescription}</span>
                                    ) : null}
                                </div>
                            </td>
                        </tr>
                    ) : (
                        rows.map((row) => (
                            <tr
                                key={rowKey(row)}
                                className={cn(
                                    'border-t',
                                    onRowClick && 'hover:bg-muted/40 cursor-pointer',
                                )}
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
                                    <td
                                        key={column.id}
                                        className={cn(
                                            'whitespace-nowrap px-4 py-3',
                                            column.className,
                                        )}
                                    >
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
