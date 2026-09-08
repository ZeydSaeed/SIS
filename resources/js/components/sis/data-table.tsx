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

    if (rows.length === 0) {
        return <EmptyState title={emptyTitle} description={emptyDescription} />;
    }

    if (isMobile && mobileCard) {
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
            <table className="w-full min-w-[640px] text-sm">
                {caption ? <caption className="sr-only">{caption}</caption> : null}
                <thead className="bg-muted/50">
                    <tr>
                        {visibleColumns.map((column) => (
                            <th
                                key={column.id}
                                scope="col"
                                className={cn('px-4 py-3 text-start font-medium', column.className)}
                            >
                                {column.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
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
                        >
                            {visibleColumns.map((column) => (
                                <td key={column.id} className={cn('px-4 py-3', column.className)}>
                                    {column.cell(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
