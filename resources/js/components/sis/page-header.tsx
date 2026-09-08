import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    description?: string;
    icon?: ReactNode;
    actions?: ReactNode;
};

export function PageHeader({ title, description, icon, actions }: PageHeaderProps) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="flex min-w-0 items-start gap-3">
                {icon ? <div className="mt-0.5 shrink-0">{icon}</div> : null}
                <div className="min-w-0">
                    <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                    {description ? (
                        <p className="text-muted-foreground mt-1 text-sm">{description}</p>
                    ) : null}
                </div>
            </div>
            {actions ? <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div> : null}
        </div>
    );
}
