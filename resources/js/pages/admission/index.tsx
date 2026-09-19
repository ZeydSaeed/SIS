import { AdmissionPageShell } from '@/components/admission/admission-page-shell';
import { AdmissionPeriodsCard } from '@/components/admission/admission-periods-card';
import type {
    AdmissionPageAuthorization,
    AdmissionWorkspace,
} from '@/components/admission/admission-workspace';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    workspace: AdmissionWorkspace;
    filters: {
        academic_year_id: number | null;
    };
    authorization: AdmissionPageAuthorization;
};

export default function AdmissionIndex({ workspace, filters, authorization }: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.admission.title, href: '/admission' }];

    return (
        <AdmissionPageShell
            title={i18n.admission.title}
            breadcrumbs={breadcrumbs}
            workspace={workspace}
            academicYearId={filters.academic_year_id}
            yearFilterAction="/admission"
            authorization={authorization}
            activeStatus={null}
        >
            <AdmissionPeriodsCard
                periods={workspace.periods}
                academicYearId={filters.academic_year_id}
                canManage={authorization.can_manage}
            />
        </AdmissionPageShell>
    );
}
