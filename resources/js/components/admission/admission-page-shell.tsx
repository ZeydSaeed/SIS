import { useState, type ReactNode } from 'react';
import { Head, router } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { AdmissionActivePeriodsTable } from '@/components/admission/admission-active-periods-table';
import { AdmissionApplicationDraftDialog } from '@/components/admission/admission-application-draft-dialog';
import {
    ADMISSION_STAGE_PATHS,
    ADMISSION_STATUS_REQUEST,
    admissionWorkspaceQuery,
    type AdmissionPageAuthorization,
    type AdmissionWorkspace,
} from '@/components/admission/admission-workspace';
import { AdmissionWorkflowProgress } from '@/components/admission/admission-workflow-progress';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type Props = {
    title: string;
    breadcrumbs: BreadcrumbItem[];
    workspace: AdmissionWorkspace;
    academicYearId: number | null;
    yearFilterAction: string;
    authorization: AdmissionPageAuthorization;
    activeStatus: number | null;
    onOtherStageSelect?: (status: number) => void;
    homeHref?: string | null;
    children: ReactNode;
};

export function AdmissionPageShell({
    title,
    breadcrumbs,
    workspace,
    academicYearId,
    yearFilterAction,
    authorization,
    activeStatus,
    onOtherStageSelect,
    homeHref = null,
    children,
}: Props) {
    const [draftOpen, setDraftOpen] = useState(false);

    const selectedPeriodId = workspace.selected_period_id ?? null;
    const workspaceQuery = admissionWorkspaceQuery(academicYearId, selectedPeriodId);

    const handlePeriodSelect = (periodId: number) => {
        if (periodId === selectedPeriodId) {
            return;
        }

        router.visit(`${yearFilterAction}${admissionWorkspaceQuery(academicYearId, periodId)}`);
    };

    const handleStageSelect = (status: number) => {
        if (status === ADMISSION_STATUS_REQUEST) {
            if (authorization.can_manage) {
                setDraftOpen(true);
            }

            return;
        }

        const stagePath = ADMISSION_STAGE_PATHS[status];
        if (stagePath) {
            if (activeStatus !== status) {
                router.visit(`${stagePath}${workspaceQuery}`);
            }

            return;
        }

        if (onOtherStageSelect) {
            onOtherStageSelect(status);
            return;
        }

        router.visit(`/admission${workspaceQuery}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <div className="sis-ops-hub sis-admission-page flex h-full min-h-0 flex-col gap-6 overflow-hidden p-4" dir="rtl" lang="ar">
                <div className="sis-admission-page-head">
                    <PageHeader
                        title={title}
                        icon={<UserPlus className="text-primary size-8" aria-hidden="true" />}
                    />
                    <div className="sis-admission-active-periods">
                        <AdmissionActivePeriodsTable
                            periods={workspace.active_periods ?? []}
                            selectedPeriodId={selectedPeriodId}
                            onPeriodSelect={handlePeriodSelect}
                        />
                    </div>
                    <div className="sis-admission-year-filter">
                        <OpsYearFilter
                            action={yearFilterAction}
                            academicYearId={academicYearId}
                            label={t().enrollments.academicYear}
                            showLabel
                            inlineLabel
                            showCurrentBadge={false}
                            controlClassName="sis-admission-year-control"
                        />
                    </div>
                </div>

                <AdmissionWorkflowProgress
                    steps={workspace.workflow_steps}
                    progress={workspace.workflow_progress}
                    activeStatus={draftOpen ? ADMISSION_STATUS_REQUEST : activeStatus}
                    onStageSelect={handleStageSelect}
                    homeHref={homeHref}
                />

                <AdmissionApplicationDraftDialog
                    open={draftOpen}
                    onOpenChange={setDraftOpen}
                    periods={workspace.periods}
                    schools={workspace.schools}
                    gradeLevels={workspace.grade_levels}
                    departments={workspace.departments}
                    specializations={workspace.specializations}
                    canManage={authorization.can_manage}
                    academicYearId={academicYearId}
                />

                <div className="sis-admission-page-body">{children}</div>
            </div>
        </AppLayout>
    );
}
