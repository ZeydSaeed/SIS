import { useCallback, useEffect, useMemo, useRef, useState, lazy, Suspense, type MutableRefObject, type ReactNode } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { XCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { AdmissionPeriodFilter } from '@/components/admission/admission-period-filter';
import { useAdmissionSelection } from '@/components/admission/admission-selection';
import {
    ADMISSION_PERIOD_FILTER_ALL,
    ADMISSION_STAGE_PATHS,
    ADMISSION_STATUS_ACCEPTED,
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

const AdmissionRequestTypeDialog = lazy(async () => {
    const mod = await import('@/components/admission/admission-request-type-dialog');

    return { default: mod.AdmissionRequestTypeDialog };
});

const AdmissionAcceptedStudentsDialog = lazy(async () => {
    const mod = await import('@/components/admission/admission-accepted-students-dialog');

    return { default: mod.AdmissionAcceptedStudentsDialog };
});

type DraftDialogMode = 'draft' | 'createStudent' | 'academicTransfer';

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

function AdmissionEditFiltersRibbon({
    workspace,
    academicYearId,
    yearFilterAction,
    selectedPeriodId,
    periodQueryId,
    onPeriodSelect,
    searchDraftRef,
}: {
    workspace: AdmissionWorkspace;
    academicYearId: number | null;
    yearFilterAction: string;
    selectedPeriodId: number | null;
    periodQueryId: number;
    onPeriodSelect: (periodId: number) => void;
    searchDraftRef: MutableRefObject<string>;
}) {
    const i18n = t();
    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        return [
            {
                id: 'admission-filters',
                label: i18n.admission.ribbonFilters,
                commands: [],
                custom: (
                    <div className="sis-ribbon__filters" aria-label={i18n.admission.ribbonFilters}>
                        <div className="sis-admission-filters sis-admission-filters--ribbon">
                            <OpsYearFilter
                                action={yearFilterAction}
                                academicYearId={academicYearId}
                                extraParams={{
                                    get q() {
                                        const value = searchDraftRef.current.trim();

                                        return value === '' ? undefined : value;
                                    },
                                    application_period_id:
                                        periodQueryId === ADMISSION_PERIOD_FILTER_ALL
                                            ? ADMISSION_PERIOD_FILTER_ALL
                                            : periodQueryId > 0
                                                ? periodQueryId
                                                : undefined,
                                }}
                                label={i18n.enrollments.academicYear}
                                showLabel
                                inlineLabel
                                compact
                                showCurrentBadge={false}
                                controlClassName="sis-admission-year-control"
                            />
                            <AdmissionPeriodFilter
                                periods={workspace.active_periods ?? []}
                                selectedPeriodId={selectedPeriodId}
                                onPeriodSelect={onPeriodSelect}
                            />
                        </div>
                    </div>
                ),
            },
        ];
    }, [
        academicYearId,
        i18n.admission.ribbonFilters,
        i18n.enrollments.academicYear,
        onPeriodSelect,
        periodQueryId,
        searchDraftRef,
        selectedPeriodId,
        workspace.active_periods,
        yearFilterAction,
    ]);

    useRegisterPageRibbon('edit', editRibbonGroups);

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
    const [requestTypeOpen, setRequestTypeOpen] = useState(false);
    const [draftMode, setDraftMode] = useState<DraftDialogMode>('draft');
    const [acceptedOpen, setAcceptedOpen] = useState(false);
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

    const openRequestTypeDialog = () => {
        if (!authorization.can_manage) {
            return;
        }

        setRequestTypeOpen(true);
    };

    const handleDraftOpenChange = (open: boolean) => {
        setDraftOpen(open);
        if (!open) {
            setDraftMode('draft');
        }
    };

    const handleSelectVocationalRequest = () => {
        setRequestTypeOpen(false);
        openDraftDialog('draft');
    };

    const handleSelectAcademicTransfer = () => {
        setRequestTypeOpen(false);
        openDraftDialog('academicTransfer');
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

    const handlePeriodSelect = useCallback((periodId: number) => {
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
    }, [academicYearId, periodQueryId, yearFilterAction]);

    const handleStageSelect = (status: number) => {
        if (status === ADMISSION_STATUS_REQUEST) {
            openRequestTypeDialog();

            return;
        }

        if (status === ADMISSION_STATUS_ACCEPTED) {
            setAcceptedOpen(true);

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

    const ribbonActiveStatus = draftOpen || requestTypeOpen
        ? ADMISSION_STATUS_REQUEST
        : acceptedOpen
            ? ADMISSION_STATUS_ACCEPTED
            : activeStatus;

    return (
        <>
            <Head title={title} />
            <AdmissionCancelRibbon />
            <AdmissionEditFiltersRibbon
                workspace={workspace}
                academicYearId={academicYearId}
                yearFilterAction={yearFilterAction}
                selectedPeriodId={selectedPeriodId}
                periodQueryId={periodQueryId}
                onPeriodSelect={handlePeriodSelect}
                searchDraftRef={searchDraftRef}
            />
            <div className="sis-ops-hub sis-admission-page flex h-full min-h-0 flex-col overflow-hidden px-4 pb-4" dir="rtl" lang="ar">
                <AdmissionWorkflowProgress
                    steps={workspace.workflow_steps}
                    progress={workspace.workflow_progress}
                    activeStatus={ribbonActiveStatus}
                    onStageSelect={handleStageSelect}
                />

                {requestTypeOpen ? (
                    <Suspense fallback={null}>
                        <AdmissionRequestTypeDialog
                            open={requestTypeOpen}
                            onOpenChange={setRequestTypeOpen}
                            onSelectVocational={handleSelectVocationalRequest}
                            onSelectAcademicTransfer={handleSelectAcademicTransfer}
                        />
                    </Suspense>
                ) : null}

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

                {acceptedOpen ? (
                    <Suspense fallback={null}>
                        <AdmissionAcceptedStudentsDialog
                            open={acceptedOpen}
                            onOpenChange={setAcceptedOpen}
                            students={workspace.accepted_students ?? []}
                            defaultAcademicYearId={academicYearId}
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
