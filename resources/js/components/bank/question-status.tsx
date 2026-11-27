import {
    Archive,
    CheckCircle2,
    Clock,
    History,
    PencilLine,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { StatusBadge } from '@/components/sis/table-status-badge';
import { locale, t } from '@/i18n';

/** Statuses the server supports: the five of a version, plus `retired` for the question itself. */
export type BankStatus =
    | 'draft'
    | 'in_review'
    | 'approved'
    | 'rejected'
    | 'superseded'
    | 'retired';

/** Existing table tones (`sis-follow-up-tone--*`) and icons; no new colours. */
const TONES: Record<BankStatus, { tone: string; icon: LucideIcon }> = {
    draft: { tone: 'withdrawn', icon: PencilLine },
    in_review: { tone: 'under-review', icon: Clock },
    approved: { tone: 'transfer', icon: CheckCircle2 },
    rejected: { tone: 'rejected', icon: XCircle },
    superseded: { tone: 'withdrawn', icon: History },
    retired: { tone: 'withdrawn', icon: Archive },
};

export function BankStatusBadge({ status }: { status: BankStatus }) {
    const { tone, icon } = TONES[status];
    const labels = t().bank.statuses as Record<BankStatus, string>;

    return (
        <span className={`sis-follow-up-tone--${tone} inline-flex`}>
            <StatusBadge icon={icon}>{labels[status]}</StatusBadge>
        </span>
    );
}

/**
 * An instant from the server (ISO 8601, UTC) shown in the display time zone the server sends (EXAMIS L-F7), in the
 * page's language with Latin digits. The browser's own time zone plays no part.
 */
export function formatInstant(iso: string | null, timeZone: string): string {
    if (!iso) {
        return '—';
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale() === 'en' ? 'en-GB' : 'ar', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        numberingSystem: 'latn',
    }).format(date);
}
