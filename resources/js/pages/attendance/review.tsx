import {
    SheetsBoard,
    type BoardProps,
} from '@/components/attendance/sheets-board';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

export default function AttendanceReview(props: BoardProps) {
    const a = t().attendanceSystem;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
        { title: a.review.title, href: '/attendance/review' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SheetsBoard {...props} mode="review" />
        </AppLayout>
    );
}
