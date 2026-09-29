import { t } from '@/i18n';

type StudentStatusBadgeProps = {
    status: number;
};

export function normalizeStudentStatus(status: number | string | null | undefined): number {
    const value = Number(status);

    return Number.isFinite(value) ? value : -1;
}

export function studentStatusLabel(status: number | string | null | undefined): string {
    const i18n = t();
    const normalized = normalizeStudentStatus(status);
    const labels: Record<number, string> = {
        0: i18n.status.inactive,
        1: i18n.status.active,
        2: i18n.status.suspended,
        3: i18n.status.graduated,
        4: i18n.status.withdrawn,
    };

    return labels[normalized] ?? `${i18n.common.status} ${String(status ?? '')}`.trim();
}

export function StudentStatusBadge({ status }: StudentStatusBadgeProps) {
    const normalized = normalizeStudentStatus(status);
    const toneClass =
        normalized >= 0 && normalized <= 4
            ? `sis-student-status-badge--${normalized}`
            : 'sis-student-status-badge--0';

    return (
        <span
            className={`sis-student-status-badge ${toneClass}`}
            data-status={normalized}
            title={studentStatusLabel(normalized)}
        >
            {studentStatusLabel(normalized)}
        </span>
    );
}
