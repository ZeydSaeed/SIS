import {
    SheetsBoard,
    type BoardProps,
} from '@/components/attendance/sheets-board';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

/** «كل الجلسات»: every attendance sheet of the school year, on the same board as the review page. */
export default function AttendanceIndex(props: BoardProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SheetsBoard {...props} mode="sessions" />
        </AppLayout>
    );
}
