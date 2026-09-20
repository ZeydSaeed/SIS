import { t } from '@/i18n';

type StudentStatusBadgeProps = {
    status: number;
};

export function StudentStatusBadge({ status }: StudentStatusBadgeProps) {
    const i18n = t();
    const labels: Record<number, string> = {
        0: i18n.status.inactive,
        1: i18n.status.active,
        2: i18n.status.suspended,
        3: i18n.status.graduated,
        4: i18n.status.withdrawn,
    };
    const toneClass =
        status >= 0 && status <= 4
            ? `sis-student-status-badge--${status}`
            : 'sis-student-status-badge--0';

    return (
        <span className={`sis-student-status-badge ${toneClass}`}>
            {labels[status] ?? `${i18n.common.status} ${status}`}
        </span>
    );
}
