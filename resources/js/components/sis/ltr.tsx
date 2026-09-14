import type { ReactNode } from 'react';

/** Keep identifiers, scores, and codes visually LTR inside RTL UI. */
export function Ltr({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <span dir="ltr" className={className}>
            {children}
        </span>
    );
}
