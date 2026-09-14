import { Badge } from '@/components/ui/badge';
import { t } from '@/i18n';

function statusVariant(status: number): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 1) {
        return 'default';
    }

    if (status === 2) {
        return 'destructive';
    }

    return 'secondary';
}

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

    return (
        <Badge variant={statusVariant(status)}>
            {labels[status] ?? `${i18n.common.status} ${status}`}
        </Badge>
    );
}
