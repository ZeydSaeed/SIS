import { Head, usePage } from '@inertiajs/react';
import { useMemo, type ReactNode } from 'react';
import type {
    AdmissionPageAuthorization,
    AdmissionWorkspace,
} from '@/components/admission/admission-workspace';
import { useRegisterPageTitlebarHome } from '@/components/sis/page-titlebar-home-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

export type FollowUpPageProps = {
    workspace: AdmissionWorkspace;
    filters: { academic_year_id: number | null };
    authorization: AdmissionPageAuthorization;
};

export type FollowUpBodyProps = {
    students: AdmissionAcceptedStudentList;
    schools: Array<{ id: number; name: string }>;
    defaultSchoolId: number | null;
    defaultYearId: number | null;
    canManage: boolean;
    canViewPii: boolean;
};

type AdmissionAcceptedStudentList = NonNullable<
    AdmissionWorkspace['accepted_students']
>;

function Inner({
    props,
    title,
    render,
}: {
    props: FollowUpPageProps;
    title: string;
    render: (body: FollowUpBodyProps) => ReactNode;
}) {
    const a = t().admission;
    const page = usePage();
    const schoolId =
        (page.props.schoolContext as { schoolId?: number | null } | undefined)
            ?.schoolId ?? null;

    useRegisterPageTitlebarHome(
        useMemo(
            () => ({ href: '/admission', ariaLabel: a.backToAdmission }),
            [a.backToAdmission],
        ),
    );

    return (
        <>
            <Head title={title} />
            <div
                className="sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page sis-follow-up-page flex h-full min-h-0 flex-col overflow-hidden pb-4"
                dir="rtl"
                lang="ar"
            >
                {render({
                    students: props.workspace.accepted_students ?? [],
                    schools: props.workspace.school_options ?? [],
                    defaultSchoolId: schoolId,
                    defaultYearId: props.filters.academic_year_id,
                    canManage: props.authorization.can_manage,
                    canViewPii:
                        props.authorization.can_view_student_pii === true,
                })}
            </div>
        </>
    );
}

/** Shell of the two «متابعة طلبات التقديم» pages (overview and list): layout, breadcrumbs, title-bar home. */
export function FollowUpPage({
    props,
    title,
    breadcrumbs,
    render,
}: {
    props: FollowUpPageProps;
    title: string;
    breadcrumbs: BreadcrumbItem[];
    render: (body: FollowUpBodyProps) => ReactNode;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Inner props={props} title={title} render={render} />
        </AppLayout>
    );
}
