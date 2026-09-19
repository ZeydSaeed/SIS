import { AdmissionDraftsCard } from '@/components/admission/admission-drafts-card';
import { AdmissionPageShell } from '@/components/admission/admission-page-shell';
import {
    ADMISSION_STATUS_DRAFT,
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

export default function AdmissionDrafts({ workspace, filters, authorization }: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.admission.title, href: '/admission' },
        { title: i18n.admission.statusDraft, href: '/admission/drafts' },
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
            yearFilterAction="/admission/drafts"
            authorization={authorization}
            activeStatus={ADMISSION_STATUS_DRAFT}
            homeHref={admissionHref}
        >
            <AdmissionDraftsCard
                workspace={workspace}
                canManage={authorization.can_manage}
                status={ADMISSION_STATUS_DRAFT}
                yearFilterAction="/admission/drafts"
                academicYearId={filters.academic_year_id}
                homeHref={admissionHref}
            />
        </AdmissionPageShell>
    );
}
