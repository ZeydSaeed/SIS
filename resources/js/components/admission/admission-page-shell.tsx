import { useCallback, useEffect, useMemo, useRef, useState, lazy, Suspense, type ReactNode } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { XCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { AdmissionActivePeriodsTable } from '@/components/admission/admission-active-periods-table';
import { AdmissionPeriodFilter } from '@/components/admission/admission-period-filter';
import { useAdmissionSelection } from '@/components/admission/admission-selection';
import {
    ADMISSION_PERIOD_FILTER_ALL,
    ADMISSION_STAGE_PATHS,
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

const AdmissionApplicationDraftDialog = lazy(async () => {
    const mod = await import('@/components/admission/admission-application-draft-dialog');

    return { default: mod.AdmissionApplicationDraftDialog };
});

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
    const searchDraftRef = useRef(filtersQ);
    const academicYearIdRef = useRef(academicYearId);
    const periodQueryIdRef = useRef(0);
    const yearFilterActionRef = useRef(yearFilterAction);

    const selectedPeriodId = workspace.selected_period_id ?? null;
    const periodQueryId = selectedPeriodId ?? ADMISSION_PERIOD_FILTER_ALL;

    academicYearIdRef.current = academicYearId;
    periodQueryIdRef.current = periodQueryId;
    yearFilterActionRef.current = yearFilterAction;

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
            )}`,
            {
                preserveState: true,
                preserveScroll: true,
                only: ['workspace', 'filters', 'authorization', 'enrollmentFilterOptions'],
            },
        );
    };

    const handleStageSelect = (status: number) => {
        if (status === ADMISSION_STATUS_REQUEST) {
            openDraftDialog('draft');

            return;
        }

        const stagePath = ADMISSION_STAGE_PATHS[status];
        const liveQuery = admissionWorkspaceQuery(
            academicYearId,
            periodQueryId,
            null,
            searchDraftRef.current,
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
                            <div className="sis-admission-filters">
                                <OpsYearFilter
                                    action={yearFilterAction}
                                    academicYearId={academicYearId}
                                    extraParams={{
                                        get q() {
                                            const value = searchDraftRef.current.trim();

                                            return value === '' ? undefined : value;
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

                {draftOpen ? (
                    <Suspense fallback={null}>
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
                    </Suspense>
                ) : null}
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
