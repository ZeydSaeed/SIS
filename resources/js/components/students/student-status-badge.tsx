import { Badge } from '@/components/ui/badge';

const STATUS_LABELS: Record<number, string> = {
    0: 'Inactive',
    1: 'Active',
    2: 'Suspended',
    3: 'Graduated',
    4: 'Withdrawn',
};

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
    return <Badge variant={statusVariant(status)}>{STATUS_LABELS[status] ?? `Status ${status}`}</Badge>;
}
