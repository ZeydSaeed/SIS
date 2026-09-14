import { Head, Link } from '@inertiajs/react';
import { FileBarChart } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];

export default function ReportsIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Reports"
                    description="Operational read-only reports for attendance and enrollment."
                    icon={<FileBarChart className="size-6" aria-hidden />}
                />
                <nav className="flex flex-col gap-2" aria-label="Report links">
                    <Link
                        href="/reports/attendance-daily"
                        className="sis-ops-hub__link px-4 py-3 text-sm"
                        prefetch
                    >
                        Daily section attendance summary
                    </Link>
                    <Link
                        href="/reports/enrollment-roster"
                        className="sis-ops-hub__link px-4 py-3 text-sm"
                        prefetch
                    >
                        Enrollment roster
                    </Link>
                    <Link href="/attendance" className="sis-ops-hub__link px-4 py-3 text-sm" prefetch>
                        Open attendance sessions
                    </Link>
                    <Link href="/enrollments" className="sis-ops-hub__link px-4 py-3 text-sm" prefetch>
                        Open enrollments
                    </Link>
                </nav>
            </div>
        </AppLayout>
    );
}
