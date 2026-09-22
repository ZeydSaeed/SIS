import { AdmissionDraftsCard } from '@/components/admission/admission-drafts-card';
import { AdmissionPageShell } from '@/components/admission/admission-page-shell';
import {
    ADMISSION_STATUS_SUBMITTED,
    admissionWorkspaceQuery,
    type AdmissionPageAuthorization,
    type AdmissionWorkspace,
} from '@/components/admission/admission-workspace';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    workspace: AdmissionWorkspace;
    filters: {
        academic_year_id: number | null;
        application_period_id?: number | null;
        q?: string | null;
    };
    authorization: AdmissionPageAuthorization;
};

export default function AdmissionSubmitted({ workspace, filters, authorization }: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.admission.title, href: '/admission' },
        { title: i18n.admission.statusSubmitted, href: '/admission/submitted' },
    ];

    const admissionHref = `/admission${admissionWorkspaceQuery(
        filters.academic_year_id,
        filters.application_period_id ?? workspace.selected_period_id,
        null,
        filters.q,
    )}`;

    return (
        <AdmissionPageShell
            title={i18n.admission.title}
            breadcrumbs={breadcrumbs}
            workspace={workspace}
            academicYearId={filters.academic_year_id}
            yearFilterAction="/admission/submitted"
            authorization={authorization}
            activeStatus={ADMISSION_STATUS_SUBMITTED}
            homeHref={admissionHref}
        >
            <AdmissionDraftsCard
                workspace={workspace}
                canManage={authorization.can_manage}
                canUpdateStudent={authorization.can_update_student ?? false}
                canViewStudentPii={authorization.can_view_student_pii ?? false}
                status={ADMISSION_STATUS_SUBMITTED}
                yearFilterAction="/admission/submitted"
                academicYearId={filters.academic_year_id}
                homeHref={admissionHref}
            />
        </AdmissionPageShell>
    );
}
