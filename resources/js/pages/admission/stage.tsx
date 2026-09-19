import { AdmissionDraftsCard } from '@/components/admission/admission-drafts-card';
import { AdmissionPageShell } from '@/components/admission/admission-page-shell';
import {
    ADMISSION_STAGE_PATHS,
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
    stage: {
        status: number;
        path: string;
        label_key:
            | 'statusDraft'
            | 'statusSubmitted'
            | 'statusUnderReview'
            | 'statusInterview'
            | 'statusWaitlisted'
            | 'statusAccepted'
            | 'statusConverted';
    };
};

function stageLabel(
    key: PageProps['stage']['label_key'],
): string {
    return t().admission[key];
}

/** Shared admission stage workspace — same table/ribbon as drafts & submitted. */
export default function AdmissionStage({
    workspace,
    filters,
    authorization,
    stage,
}: PageProps) {
    const i18n = t();
    const label = stageLabel(stage.label_key);
    const path = stage.path || ADMISSION_STAGE_PATHS[stage.status] || '/admission';
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.admission.title, href: '/admission' },
        { title: label, href: path },
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
            yearFilterAction={path}
            authorization={authorization}
            activeStatus={stage.status}
            homeHref={admissionHref}
        >
            <AdmissionDraftsCard
                workspace={workspace}
                canManage={authorization.can_manage}
                status={stage.status}
                yearFilterAction={path}
                academicYearId={filters.academic_year_id}
                homeHref={admissionHref}
            />
        </AdmissionPageShell>
    );
}
