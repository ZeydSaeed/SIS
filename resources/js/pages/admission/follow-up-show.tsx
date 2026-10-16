import { Head, usePage } from '@inertiajs/react';
import { useMemo, useRef } from 'react';
import {
    FollowUpShow,
    type FollowUpApplication,
} from '@/components/admission/follow-up/follow-up-show';
import { useRegisterPageTitlebarHome } from '@/components/sis/page-titlebar-home-context';
import { useSmoothVerticalScroll } from '@/hooks/use-smooth-vertical-scroll';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';

type PageProps = {
    application: FollowUpApplication;
    authorization: { can_manage: boolean; can_view_student_pii: boolean };
};

function Body({ application, authorization }: PageProps) {
    const a = t().admission;
    const { url } = usePage();
    // No scroll bar; the wheel glides (the project's smooth scroll).
    const scrollerRef = useRef<HTMLDivElement | null>(null);
    useSmoothVerticalScroll(scrollerRef);
    const year = new URLSearchParams(url.split('?')[1] ?? '').get(
        'academic_year_id',
    );

    // The title-bar home returns to the overview of the same year.
    useRegisterPageTitlebarHome(
        useMemo(
            () => ({
                href: year
                    ? `/admission/follow-up?academic_year_id=${year}`
                    : '/admission/follow-up',
                ariaLabel: a.followUpBackToOverview,
            }),
            [a.followUpBackToOverview, year],
        ),
    );

    return (
        <>
            <Head
                title={`${application.first_name} ${application.last_name} — ${a.acceptedStudentsDialogTitle}`}
            />
            <div
                ref={scrollerRef}
                className="sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page sis-follow-up-page sis-scroll-hidden flex h-full min-h-0 flex-col overflow-y-auto pb-4"
                dir="rtl"
                lang="ar"
            >
                <FollowUpShow
                    application={application}
                    canManage={authorization.can_manage}
                    canViewPii={authorization.can_view_student_pii}
                />
            </div>
        </>
    );
}

/** «متابعة طلبات التقديم» › one application (the EXAMIS staff application page). */
export default function AdmissionFollowUpShow(props: PageProps) {
    const a = t().admission;

    return (
        <AppLayout
            breadcrumbs={[
                { title: a.title, href: '/admission' },
                {
                    title: a.acceptedStudentsDialogTitle,
                    href: '/admission/follow-up',
                },
                { title: props.application.application_number, href: '#' },
            ]}
        >
            <Body {...props} />
        </AppLayout>
    );
}
