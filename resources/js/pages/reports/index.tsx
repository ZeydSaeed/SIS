import { Head, Link } from '@inertiajs/react';
import { FileBarChart } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

export default function ReportsIndex() {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.reports.title, href: '/reports' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.reports.title} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={i18n.reports.title}
                    description={i18n.reports.description}
                    icon={<FileBarChart className="size-6" aria-hidden />}
                />
                <nav className="flex flex-col gap-2" aria-label={i18n.common.reportLinks}>
                    <Link
                        href="/reports/attendance-daily"
                        className="sis-ops-hub__link px-4 py-3 text-sm"
                        prefetch
                    >
                        {i18n.reports.dailySummary}
                    </Link>
                    <Link
                        href="/reports/enrollment-roster"
                        className="sis-ops-hub__link px-4 py-3 text-sm"
                        prefetch
                    >
                        {i18n.reports.enrollmentRoster}
                    </Link>
                    <Link href="/attendance" className="sis-ops-hub__link px-4 py-3 text-sm" prefetch>
                        {i18n.reports.openAttendance}
                    </Link>
                    <Link href="/enrollments" className="sis-ops-hub__link px-4 py-3 text-sm" prefetch>
                        {i18n.reports.openEnrollments}
                    </Link>
                </nav>
            </div>
        </AppLayout>
    );
}
