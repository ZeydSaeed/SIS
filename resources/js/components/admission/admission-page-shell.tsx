import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { XCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { AdmissionActivePeriodsTable } from '@/components/admission/admission-active-periods-table';
import { AdmissionApplicationDraftDialog } from '@/components/admission/admission-application-draft-dialog';
import { AdmissionPeriodFilter } from '@/components/admission/admission-period-filter';
import { AdmissionEnrollmentStatusFilter } from '@/components/admission/admission-enrollment-status-filter';
import { useAdmissionSelection } from '@/components/admission/admission-selection';
import {
    ADMISSION_PERIOD_FILTER_ALL,
    ADMISSION_STAGE_PATHS,
    ADMISSION_STATUS_CONVERTED,
    ADMISSION_STATUS_REQUEST,
    admissionWorkspaceQuery,
    type AdmissionPageAuthorization,
    type AdmissionWorkspace,
} from '@/components/admission/admission-workspace';
import { AdmissionSearchProvider } from '@/components/admission/admission-search-context';
import { AdmissionWorkflowProgress } from '@/components/admission/admission-workflow-progress';
import {
    useRegisterPageRibbon,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarHome } from '@/components/sis/page-titlebar-home-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { usePageAlignment } from '@/hooks/use-page-alignment';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type DraftDialogMode = 'draft' | 'createStudent';

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

type InnerProps = Omit<Props, 'breadcrumbs'>;

function searchFromPageFilters(filters: unknown): string {
    if (filters && typeof filters === 'object' && 'q' in filters) {
        const value = (filters as { q?: string | null }).q;

        return typeof value === 'string' ? value : '';
    }

    return '';
}

function enrollmentStatusFromPageFilters(filters: unknown): string | null {
    if (filters && typeof filters === 'object' && 'enrollment_status' in filters) {
        const value = (filters as { enrollment_status?: string | null }).enrollment_status;
        if (value === 'awaiting' || value === 'completed') {
            return value;
        }
    }

    return null;
}

function AdmissionCancelRibbon() {
    const i18n = t();
    const { hasTarget } = usePageAlignment();
    const { hasTableSelection, clearSelection } = useAdmissionSelection();
    const canClearSelection = hasTarget || hasTableSelection;
    const cancelRibbonGroups = useMemo((): PageRibbonGroup[] => {
        return [
            {
                id: 'page-actions',
                label: i18n.common.actions,
                commands: [
                    {
                        id: 'cancel-admission-selection',
                        label: i18n.common.cancel,
                        icon: XCircle,
                        disabled: !canClearSelection,
                        onSelect: clearSelection,
                    },
                ],
            },
        ];
    }, [
        canClearSelection,
        clearSelection,
        i18n.common.actions,
        i18n.common.cancel,
    ]);

    useRegisterPageRibbon('home', cancelRibbonGroups);

    return null;
}

/**
 * Must render inside AppLayout so titlebar search/home context providers are ancestors
 * (same pattern as StudentList / EnrollmentList).
 */
function AdmissionPageShellInner({
    title,
    workspace,
    academicYearId,
    yearFilterAction,
    authorization,
    activeStatus,
    onOtherStageSelect,
    homeHref = null,
    children,
}: InnerProps) {
    const i18n = t();
    const page = usePage();
    const [draftOpen, setDraftOpen] = useState(false);
    const [draftMode, setDraftMode] = useState<DraftDialogMode>('draft');
    const filtersQ = searchFromPageFilters(page.props.filters);
    const enrollmentStatusFilter = enrollmentStatusFromPageFilters(page.props.filters);
    const searchDraftRef = useRef(filtersQ);
    const academicYearIdRef = useRef(academicYearId);
    const periodQueryIdRef = useRef(0);
    const yearFilterActionRef = useRef(yearFilterAction);
    const enrollmentStatusRef = useRef(enrollmentStatusFilter);

    const selectedPeriodId = workspace.selected_period_id ?? null;
    const periodQueryId = selectedPeriodId ?? ADMISSION_PERIOD_FILTER_ALL;
    const enrollmentStatusForQuery =
        activeStatus === ADMISSION_STATUS_CONVERTED ? enrollmentStatusFilter : null;

    academicYearIdRef.current = academicYearId;
    periodQueryIdRef.current = periodQueryId;
    yearFilterActionRef.current = yearFilterAction;
    enrollmentStatusRef.current = enrollmentStatusForQuery;

    useEffect(() => {
        const [path, query = ''] = page.url.split('?');
        const params = new URLSearchParams(query);
        if (params.get('create_student') !== '1' || !authorization.can_manage) {
            return;
        }

        setDraftMode('createStudent');
        setDraftOpen(true);
        params.delete('create_student');
        const next = params.toString();
        router.visit(next === '' ? path : `${path}?${next}`, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
            showProgress: false,
        });
    }, [authorization.can_manage, page.url]);

    const openDraftDialog = (mode: DraftDialogMode = 'draft') => {
        if (!authorization.can_manage) {
            return;
        }

        setDraftMode(mode);
        setDraftOpen(true);
    };

    const handleDraftOpenChange = (open: boolean) => {
        setDraftOpen(open);
        if (!open) {
            setDraftMode('draft');
        }
    };

    const titlebarHome = useMemo(
        () => ({
            href:
                homeHref ??
                `/admission${admissionWorkspaceQuery(
                    academicYearId,
                    periodQueryId,
                    null,
                    filtersQ,
                )}`,
            ariaLabel: i18n.admission.backToAdmission,
        }),
        [
            academicYearId,
            filtersQ,
            homeHref,
            i18n.admission.backToAdmission,
            periodQueryId,
        ],
    );

    useRegisterPageTitlebarHome(titlebarHome);

    const commitSearch = useCallback((query: string) => {
        searchDraftRef.current = query;
        router.visit(
            `${yearFilterActionRef.current}${admissionWorkspaceQuery(
                academicYearIdRef.current,
                periodQueryIdRef.current,
                1,
                query,
                enrollmentStatusRef.current,
            )}`,
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['workspace', 'filters'],
                showProgress: false,
            },
        );
    }, []);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: filtersQ,
            label: i18n.admission.searchStudents,
            placeholder: i18n.admission.searchStudents,
            onDraftChange: (query: string) => {
                searchDraftRef.current = query;
            },
            onCommit: commitSearch,
        }),
        [commitSearch, filtersQ, i18n.admission.searchStudents],
    );

    useRegisterPageTitlebarSearch(titlebarSearch);

    const handlePeriodSelect = (periodId: number) => {
        const next = periodId > 0 ? periodId : ADMISSION_PERIOD_FILTER_ALL;
        if (next === periodQueryId) {
            return;
        }

        router.visit(
            `${yearFilterAction}${admissionWorkspaceQuery(
                academicYearId,
                next,
                1,
                searchDraftRef.current,
                enrollmentStatusForQuery,
            )}`,
        );
    };

    const handleEnrollmentStatusSelect = (next: string | null) => {
        if ((next ?? null) === (enrollmentStatusForQuery ?? null)) {
            return;
        }

        router.visit(
            `${yearFilterAction}${admissionWorkspaceQuery(
                academicYearId,
                periodQueryId,
                1,
                searchDraftRef.current,
                next,
            )}`,
            {
                preserveState: true,
                preserveScroll: true,
                only: ['workspace', 'filters'],
            },
        );
    };

    const handleStageSelect = (status: number) => {
        if (status === ADMISSION_STATUS_REQUEST) {
            openDraftDialog('draft');

            return;
        }

        const stagePath = ADMISSION_STAGE_PATHS[status];
        const keepEnrollmentStatus =
            status === ADMISSION_STATUS_CONVERTED ? enrollmentStatusForQuery : null;
        const liveQuery = admissionWorkspaceQuery(
            academicYearId,
            periodQueryId,
            null,
            searchDraftRef.current,
            keepEnrollmentStatus,
        );

        if (stagePath) {
            if (activeStatus !== status) {
                router.visit(`${stagePath}${liveQuery}`);
            }

            return;
        }

        if (onOtherStageSelect) {
            onOtherStageSelect(status);
            return;
        }

        router.visit(`/admission${liveQuery}`);
    };

    return (
        <>
            <Head title={title} />
            <AdmissionCancelRibbon />
            <div className="sis-ops-hub sis-admission-page flex h-full min-h-0 flex-col overflow-hidden px-4 pb-4" dir="rtl" lang="ar">
                <div className="sis-admission-page-head">
                    <div className="sis-admission-page-head__row">
                        <div className="sis-admission-active-periods">
                            <AdmissionActivePeriodsTable
                                periods={workspace.active_periods ?? []}
                                selectedPeriodId={selectedPeriodId}
                                onPeriodSelect={handlePeriodSelect}
                                searchQuery={activeStatus === null ? filtersQ : ''}
                            />
                        </div>
                        <div className="sis-admission-filter-stack">
                            <div
                                className={
                                    activeStatus === ADMISSION_STATUS_CONVERTED
                                        ? 'sis-admission-filters sis-admission-filters--with-enrollment'
                                        : 'sis-admission-filters'
                                }
                            >
                                <OpsYearFilter
                                    action={yearFilterAction}
                                    academicYearId={academicYearId}
                                    extraParams={{
                                        get q() {
                                            const value = searchDraftRef.current.trim();

                                            return value === '' ? undefined : value;
                                        },
                                        get enrollment_status() {
                                            return enrollmentStatusRef.current ?? undefined;
                                        },
                                    }}
                                    label={t().enrollments.academicYear}
                                    showLabel
                                    inlineLabel
                                    compact
                                    showCurrentBadge={false}
                                    controlClassName="sis-admission-year-control"
                                />
                                <AdmissionPeriodFilter
                                    periods={workspace.active_periods ?? []}
                                    selectedPeriodId={selectedPeriodId}
                                    onPeriodSelect={handlePeriodSelect}
                                />
                                {activeStatus === ADMISSION_STATUS_CONVERTED ? (
                                    <AdmissionEnrollmentStatusFilter
                                        value={enrollmentStatusForQuery}
                                        onChange={handleEnrollmentStatusSelect}
                                    />
                                ) : null}
                            </div>
                        </div>
                    </div>
                </div>

                <AdmissionWorkflowProgress
                    steps={workspace.workflow_steps}
                    progress={workspace.workflow_progress}
                    activeStatus={draftOpen ? ADMISSION_STATUS_REQUEST : activeStatus}
                    onStageSelect={handleStageSelect}
                />

                <AdmissionApplicationDraftDialog
                    open={draftOpen}
                    onOpenChange={handleDraftOpenChange}
                    periods={workspace.periods}
                    schools={workspace.schools}
                    gradeLevels={workspace.grade_levels}
                    branches={workspace.branches ?? []}
                    departments={workspace.departments}
                    specializations={workspace.specializations}
                    canManage={authorization.can_manage}
                    academicYearId={academicYearId}
                    mode={draftMode}
                />

                <div className="sis-admission-page-body">
                    <AdmissionSearchProvider value={filtersQ}>{children}</AdmissionSearchProvider>
                </div>
            </div>
        </>
    );
}

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
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <AdmissionPageShellInner
                title={title}
                workspace={workspace}
                academicYearId={academicYearId}
                yearFilterAction={yearFilterAction}
                authorization={authorization}
                activeStatus={activeStatus}
                onOtherStageSelect={onOtherStageSelect}
                homeHref={homeHref}
            >
                {children}
            </AdmissionPageShellInner>
        </AppLayout>
    );
}
