import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { AdmissionActivePeriodsTable } from '@/components/admission/admission-active-periods-table';
import { AdmissionApplicationDraftDialog } from '@/components/admission/admission-application-draft-dialog';
import { AdmissionPeriodFilter } from '@/components/admission/admission-period-filter';
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
import { useRegisterPageTitlebarHome } from '@/components/sis/page-titlebar-home-context';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

const SEARCH_DEBOUNCE_MS = 300;

/** Ionicons arrow-up-right-box-outline — local SVG, no remote icon package. */
function ArrowUpRightBoxIcon() {
    return (
        <svg
            className="sis-admission-workflow-title__exit-icon"
            viewBox="0 0 512 512"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M384 224v184a40 40 0 0 1-40 40H104a40 40 0 0 1-40-40V168a40 40 0 0 1 40-40h167.48"
                stroke="currentColor"
                strokeWidth="32"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M336 64h112v112M224 288L440 72"
                stroke="currentColor"
                strokeWidth="32"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

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

function searchFromPageFilters(filters: unknown): string {
    if (filters && typeof filters === 'object' && 'q' in filters) {
        const value = (filters as { q?: string | null }).q;

        return typeof value === 'string' ? value : '';
    }

    return '';
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
    const i18n = t();
    const [draftOpen, setDraftOpen] = useState(false);
    const filtersQ = searchFromPageFilters(usePage().props.filters);
    const [search, setSearch] = useState(filtersQ);
    const skipSearchVisit = useRef(true);

    useEffect(() => {
        setSearch(filtersQ);
    }, [filtersQ]);

    const selectedPeriodId = workspace.selected_period_id ?? null;
    const periodQueryId = selectedPeriodId ?? ADMISSION_PERIOD_FILTER_ALL;
    const workspaceQuery = admissionWorkspaceQuery(
        academicYearId,
        periodQueryId,
        null,
        search,
    );
    const titlebarHome = useMemo(
        () => ({
            href:
                homeHref ??
                `/admission${admissionWorkspaceQuery(
                    academicYearId,
                    periodQueryId,
                    null,
                    search,
                )}`,
            ariaLabel: i18n.admission.backToAdmission,
        }),
        [
            academicYearId,
            homeHref,
            i18n.admission.backToAdmission,
            periodQueryId,
            search,
        ],
    );

    useRegisterPageTitlebarHome(titlebarHome);

    useEffect(() => {
        if (skipSearchVisit.current) {
            skipSearchVisit.current = false;

            return;
        }

        if (search.trim() === filtersQ.trim()) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.visit(
                `${yearFilterAction}${admissionWorkspaceQuery(
                    academicYearId,
                    periodQueryId,
                    1,
                    search,
                )}`,
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['workspace', 'filters'],
                },
            );
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [academicYearId, filtersQ, periodQueryId, search, yearFilterAction]);

    const handlePeriodSelect = (periodId: number) => {
        const next = periodId > 0 ? periodId : ADMISSION_PERIOD_FILTER_ALL;
        if (next === periodQueryId) {
            return;
        }

        router.visit(
            `${yearFilterAction}${admissionWorkspaceQuery(academicYearId, next, 1, search)}`,
        );
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
            <div className="sis-ops-hub sis-admission-page flex h-full min-h-0 flex-col overflow-hidden px-4 pb-4" dir="rtl" lang="ar">
                <div className="sis-admission-page-head">
                    <div className="sis-admission-page-head__row">
                        <PageHeader
                            title={title}
                            icon={<UserPlus className="sis-admission-page-head__icon" aria-hidden="true" />}
                        />
                        <div className="sis-admission-active-periods">
                            <AdmissionActivePeriodsTable
                                periods={workspace.active_periods ?? []}
                                selectedPeriodId={selectedPeriodId}
                                onPeriodSelect={handlePeriodSelect}
                            />
                        </div>
                        <div className="sis-admission-filter-stack">
                            <div className="sis-admission-filters">
                                <OpsYearFilter
                                    action={yearFilterAction}
                                    academicYearId={academicYearId}
                                    extraParams={{
                                        q: search.trim() || undefined,
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
                            <label className="sis-admission-search">
                                <span className="sr-only">{t().admission.searchStudents}</span>
                                <input
                                    type="search"
                                    value={search}
                                    maxLength={80}
                                    autoComplete="off"
                                    placeholder={t().admission.searchStudents}
                                    aria-label={t().admission.searchStudents}
                                    className="sis-admission-search__input"
                                    onChange={(event) => setSearch(event.target.value)}
                                />
                            </label>
                        </div>
                    </div>
                    <div className="sis-admission-workflow-title-row">
                        <h2 id="sis-admission-workflow-title" className="sis-admission-workflow-title">
                            {i18n.admission.workflowTitle}
                        </h2>
                        {homeHref ? (
                            <Link
                                href={homeHref}
                                prefetch
                                className="sis-admission-workflow-title__exit"
                                aria-label={i18n.admission.backToAdmission}
                                title={i18n.admission.backToAdmission}
                            >
                                <ArrowUpRightBoxIcon />
                            </Link>
                        ) : null}
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
                    onOpenChange={setDraftOpen}
                    periods={workspace.periods}
                    schools={workspace.schools}
                    gradeLevels={workspace.grade_levels}
                    departments={workspace.departments}
                    specializations={workspace.specializations}
                    canManage={authorization.can_manage}
                    academicYearId={academicYearId}
                />

                <div className="sis-admission-page-body">
                    <AdmissionSearchProvider value={search}>{children}</AdmissionSearchProvider>
                </div>
            </div>
        </AppLayout>
    );
}
