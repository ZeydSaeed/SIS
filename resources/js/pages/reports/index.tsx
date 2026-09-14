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
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.reports.title}
                    description={i18n.reports.description}
                    icon={<FileBarChart className="size-6" aria-hidden />}
                />
                <nav aria-label={i18n.common.reportLinks}>
                    <ul className="sis-ops-hub__links">
                        <li>
                            <Link href="/reports/attendance-daily" className="sis-ops-hub__link" dir="rtl" lang="ar" prefetch>
                                <span className="sis-ops-hub__link-title">{i18n.reports.dailySummary}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.reports.dailySummaryDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/reports/enrollment-roster" className="sis-ops-hub__link" dir="rtl" lang="ar" prefetch>
                                <span className="sis-ops-hub__link-title">{i18n.reports.enrollmentRoster}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.reports.enrollmentRosterDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/attendance" className="sis-ops-hub__link" dir="rtl" lang="ar" prefetch>
                                <span className="sis-ops-hub__link-title">{i18n.reports.openAttendance}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.attendanceDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/enrollments" className="sis-ops-hub__link" dir="rtl" lang="ar" prefetch>
                                <span className="sis-ops-hub__link-title">{i18n.reports.openEnrollments}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.enrollmentsDesc}</span>
                            </Link>
                        </li>
                    </ul>
                </nav>
            </div>
        </AppLayout>
    );
}
