import { t } from '@/i18n';

type StatusChipProps = {
    kind: 'student' | 'attendance' | 'enrollment' | 'exam' | 'schedule' | 'generic';
    status: number;
};

function labelFor(kind: StatusChipProps['kind'], status: number): string {
    const i18n = t();
    if (kind === 'student') {
        const map: Record<number, string> = {
            0: i18n.status.inactive,
            1: i18n.status.active,
            2: i18n.status.suspended,
            3: i18n.status.graduated,
            4: i18n.status.withdrawn,
        };
        return map[status] ?? `${i18n.common.status} ${status}`;
    }
    if (kind === 'attendance') {
        const map: Record<number, string> = {
            1: i18n.attendance.open,
            2: i18n.attendance.closed,
            3: i18n.attendance.cancelled,
        };
        return map[status] ?? `${i18n.common.status} ${status}`;
    }
    if (kind === 'enrollment') {
        const map: Record<number, string> = {
            1: i18n.status.active,
            2: i18n.status.cancelled,
            3: i18n.status.completed,
        };
        return map[status] ?? `${i18n.common.status} ${status}`;
    }
    if (kind === 'exam') {
        const map: Record<number, string> = {
            1: i18n.status.draft,
            2: i18n.status.open,
            3: i18n.status.closed,
            4: i18n.status.cancelled,
        };
        return map[status] ?? `${i18n.common.status} ${status}`;
    }
    if (kind === 'schedule') {
        const map: Record<number, string> = {
            1: i18n.status.active,
            2: i18n.status.cancelled,
        };
        return map[status] ?? `${i18n.common.status} ${status}`;
    }
    return `${i18n.common.status} ${status}`;
}

function toneClass(kind: StatusChipProps['kind'], status: number): string {
    if (kind === 'attendance' && status === 1) {
        return 'bg-[color-mix(in_srgb,var(--sis-powder-blue)_35%,white)]';
    }
    if (kind === 'attendance' && status === 2) {
        return 'bg-[color-mix(in_srgb,var(--sis-ash-brown)_20%,white)]';
    }
    if (status === 1) {
        return 'bg-[color-mix(in_srgb,var(--sis-powder-blue)_35%,white)]';
    }
    if (status === 2 || status === 4) {
        return 'bg-[color-mix(in_srgb,var(--sis-powder-blush)_40%,white)]';
    }
    return 'bg-[color-mix(in_srgb,var(--sis-powder-petal)_40%,white)]';
}

/** Compact Arabic status chip for ops tables (palette-governed). */
export function StatusChip({ kind, status }: StatusChipProps) {
    return (
        <span
            className={`inline-flex min-h-8 items-center rounded-md px-2 py-1 text-xs font-medium ${toneClass(kind, status)}`}
        >
            {labelFor(kind, status)}
        </span>
    );
}

export function dayOfWeekLabel(day: number): string {
    const i18n = t();
    const map: Record<number, string> = {
        1: i18n.days.sunday,
        2: i18n.days.monday,
        3: i18n.days.tuesday,
        4: i18n.days.wednesday,
        5: i18n.days.thursday,
        6: i18n.days.friday,
        7: i18n.days.saturday,
        0: i18n.days.sunday,
    };
    return map[day] ?? `${i18n.common.day} ${day}`;
}
