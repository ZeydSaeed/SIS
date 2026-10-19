import {
    CheckCircle2,
    CircleSlash,
    GraduationCap,
    PauseCircle,
    UserMinus,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Status cells of the shared table theme (`sis-table-theme`, see app.css): a flat tint, the icon outline and the name in one
 * colour. The tint comes from a `sis-follow-up-tone--*` class on the `<td>`; use `tableStatusCellClass` for it.
 */
export function tableStatusCellClass(tone: string): string {
    return `sis-follow-up-roster__category sis-follow-up-tone--${tone}`;
}

/** Student status (0 inactive … 4 withdrawn) → tone and icon, shared by every list that shows a student's status. */
export const STUDENT_STATUS_TONES: Record<number, string> = {
    0: 'withdrawn',
    1: 'transfer',
    2: 'waitlisted',
    3: 'submitted',
    4: 'rejected',
};

export const STUDENT_STATUS_ICONS: Record<number, LucideIcon> = {
    0: CircleSlash,
    1: CheckCircle2,
    2: PauseCircle,
    3: GraduationCap,
    4: UserMinus,
};

/** Icon before the name. Wrap it in the table's `CellScroll` inside a `tableStatusCellClass` cell. */
export function StatusBadge({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <span className="sis-follow-up-roster__badge">
            <span className="sis-follow-up-roster__icon" aria-hidden>
                <Icon strokeWidth={2} />
            </span>
            {children}
        </span>
    );
}
