import { Head, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { AdmissionFollowUpRoster } from '@/components/admission/admission-follow-up-roster';
import {
    type AdmissionPageAuthorization,
    type AdmissionWorkspace,
} from '@/components/admission/admission-workspace';
import { useRegisterPageTitlebarHome } from '@/components/sis/page-titlebar-home-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    workspace: AdmissionWorkspace;
    filters: { academic_year_id: number | null };
    authorization: AdmissionPageAuthorization;
};

/** Inside AppLayout, so the title-bar home and ribbon providers are ancestors. */
function FollowUpBody({ workspace, filters, authorization }: PageProps) {
    const i18n = t();
    const page = usePage();
    const schoolId =
        (page.props.schoolContext as { schoolId?: number | null } | undefined)
            ?.schoolId ?? null;

    useRegisterPageTitlebarHome(
        useMemo(
            () => ({
                href: '/admission',
                ariaLabel: i18n.admission.backToAdmission,
            }),
            [i18n.admission.backToAdmission],
        ),
    );

    return (
        <>
            <Head title={i18n.admission.acceptedStudentsDialogTitle} />
            <div
                className="sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page sis-admission-follow-up-page flex h-full min-h-0 flex-col overflow-hidden pb-4"
                dir="rtl"
                lang="ar"
            >
                <AdmissionFollowUpRoster
                    students={workspace.accepted_students ?? []}
                    defaultAcademicYearId={filters.academic_year_id}
                    canManage={authorization.can_manage}
                    schools={workspace.school_options ?? []}
                    defaultSchoolId={schoolId}
                />
            </div>
        </>
    );
}

/** «متابعة طلبات التقديم» — a full page with its own ribbon (filters in «الصفحة الرئيسية», editing in «تحرير»). */
export default function AdmissionFollowUp(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.admission.title, href: '/admission' },
        {
            title: i18n.admission.acceptedStudentsDialogTitle,
            href: '/admission/follow-up',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <FollowUpBody {...props} />
        </AppLayout>
    );
}
