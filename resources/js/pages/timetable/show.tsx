import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    schedule: {
        id: number;
        section_id: number;
        academic_year_id: number;
        day_of_week: number;
        period_id: number;
        subject_id: number;
        teacher_id: number;
        room_id: number | null;
        lifecycle_status: number;
    };
};

export default function TimetableShow({ schedule }: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.timetable.title, href: '/timetable' },
        {
            title: `${i18n.timetable.slot} #${schedule.id}`,
            href: `/timetable/${schedule.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${i18n.timetable.slot} ${schedule.id}`} />
            <div className="sis-ops-hub flex flex-col gap-6 p-4" dir="rtl" lang="ar">
                <Button asChild variant="ghost" size="sm" className="w-fit">
                    <Link href="/timetable">
                        <ArrowRight className="me-2 size-4" aria-hidden />
                        {i18n.common.backToList}
                    </Link>
                </Button>
                <PageHeader
                    title={`${i18n.timetable.slot} · ${schedule.id}`}
                    description={i18n.timetable.showDesc}
                />
                <dl className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.timetable.day}</dt>
                        <dd dir="ltr">{schedule.day_of_week}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.attendance.period}</dt>
                        <dd dir="ltr">{schedule.period_id}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.attendance.section}</dt>
                        <dd dir="ltr">{schedule.section_id}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.results.subject}</dt>
                        <dd dir="ltr">{schedule.subject_id}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.attendance.teacher}</dt>
                        <dd dir="ltr">{schedule.teacher_id}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.timetable.room}</dt>
                        <dd dir="ltr">{schedule.room_id ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.enrollments.academicYear}</dt>
                        <dd dir="ltr">{schedule.academic_year_id}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">{i18n.common.status}</dt>
                        <dd dir="ltr">{schedule.lifecycle_status}</dd>
                    </div>
                </dl>
            </div>
        </AppLayout>
    );
}
