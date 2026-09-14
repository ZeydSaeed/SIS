import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    teacher: {
        id: number;
        employee_code: string;
        full_name: string;
        specialization_field: string | null;
        status: number;
        is_primary: boolean;
        academic_year_id: number;
        hire_date: string | null;
    };
};

export default function TeachersShow({ teacher }: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.teachers.title, href: '/teachers' },
        { title: teacher.full_name, href: `/teachers/${teacher.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={teacher.full_name} />
            <div className="sis-ops-hub flex flex-col gap-6 p-4" dir="rtl" lang="ar">
                <Button asChild variant="ghost" size="sm" className="w-fit">
                    <Link href="/teachers">
                        <ArrowRight className="me-2 size-4" aria-hidden />
                        {i18n.common.backToList}
                    </Link>
                </Button>
                <PageHeader title={teacher.full_name} description={i18n.teachers.showDesc} />
                <dl className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.teachers.code}</dt>
                        <dd dir="ltr">{teacher.employee_code}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.teachers.specialization}</dt>
                        <dd>{teacher.specialization_field ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.common.status}</dt>
                        <dd dir="ltr">{teacher.status}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.teachers.hireDate}</dt>
                        <dd dir="ltr">{teacher.hire_date ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.enrollments.academicYear}</dt>
                        <dd dir="ltr">{teacher.academic_year_id}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.teachers.primary}</dt>
                        <dd>{teacher.is_primary ? i18n.common.yes : i18n.common.no}</dd>
                    </div>
                </dl>
            </div>
        </AppLayout>
    );
}
