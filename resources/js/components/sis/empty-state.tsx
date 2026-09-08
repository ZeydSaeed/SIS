import type { ReactNode } from 'react';

type EmptyStateProps = {
    title: string;
    description?: string;
    action?: ReactNode;
};

export function EmptyState({ title, description, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 px-4 py-12 text-center">
            <p className="text-base font-medium">{title}</p>
            {description ? (
                <p className="text-muted-foreground max-w-md text-sm">{description}</p>
            ) : null}
            {action ? <div className="mt-2">{action}</div> : null}
        </div>
    );
}
