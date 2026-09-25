import { Link, router } from '@inertiajs/react';
import { Home, Pencil, Save, Trash2 } from 'lucide-react';
import {
    forwardRef,
    useCallback,
    useEffect,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { AdmissionDateTimeField } from '@/components/admission/admission-date-time-field';
import { SisListSelect } from '@/components/sis/sis-list-select';
import {
    selectTableRow,
    tableActionIds,
    toggleTableRowChecked,
    toggleTableSelectAll,
} from '@/components/sis/table-row-selection';
import { useAdmissionSearchQuery } from '@/components/admission/admission-search-context';
import { useAdmissionSelectionClearer } from '@/components/admission/admission-selection';
import {
    ADMISSION_STATUS_ACCEPTED,
    ADMISSION_STATUS_CONVERTED,
    ADMISSION_STATUS_DRAFT,
    ADMISSION_STATUS_INTERVIEW,
    ADMISSION_STATUS_REJECTED,
    ADMISSION_STATUS_SUBMITTED,
    ADMISSION_STATUS_UNDER_REVIEW,
    ADMISSION_STATUS_WAITLISTED,
    ADMISSION_STATUS_WITHDRAWN,
    admissionAllowedTransitions,
    admissionApplicationFullName,
    admissionCanConvert,
    admissionSearchSegments,
    admissionWorkspaceQuery,
    lookupName,
    type AdmissionApplication,
    type AdmissionWorkspace,
} from '@/components/admission/admission-workspace';
import { formatAdmissionDateTime } from '@/components/admission/format-admission-datetime';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { usePageError } from '@/components/sis/page-error-context';
import {
    useRegisterPageRibbon,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
import { t } from '@/i18n';



function visiblePages(current: number, totalPages: number): number[] {
    const windowSize = 5;
    if (totalPages <= windowSize) {
        return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const half = Math.floor(windowSize / 2);
    let start = Math.max(1, current - half);
    let end = start + windowSize - 1;
    if (end > totalPages) {
        end = totalPages;
        start = Math.max(1, end - windowSize + 1);
    }

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

const TRANSITION_TONE: Record<number, 'light' | 'dark'> = {
    0: 'dark',
    1: 'dark',
    2: 'light',
    3: 'light',
    4: 'dark',
    5: 'light',
    6: 'dark',
    7: 'dark',
    8: 'dark',
    9: 'dark',
};

type Props = {
    workspace: AdmissionWorkspace;
    canManage: boolean;
    canViewStudentPii?: boolean;
    status?: number;
    yearFilterAction?: string;
    academicYearId?: number | null;
    homeHref?: string | null;
};

function applicationStatusLabel(status: number): string {
    const i18n = t().admission;
    const map: Record<number, string> = {
        1: i18n.statusDraft,
        2: i18n.statusSubmitted,
        3: i18n.statusUnderReview,
        4: i18n.statusInterview,
        5: i18n.statusWaitlisted,
        6: i18n.statusAccepted,
        7: i18n.statusRejected,
        8: i18n.statusWithdrawn,
        9: i18n.statusConverted,
    };

    return map[status] ?? String(status);
}

function documentTypeLabel(type: number): string {
    const i18n = t().admission;
    if (type === 1) return i18n.documentTypeId;
    if (type === 2) return i18n.documentTypeBirth;
    if (type === 3) return i18n.documentTypePhoto;
    if (type === 11) return i18n.docStudentIdFront;
    if (type === 12) return i18n.docStudentIdBack;
    if (type === 13) return i18n.docFatherIdFront;
    if (type === 14) return i18n.docFatherIdBack;
    if (type === 15) return i18n.docMotherIdFront;
    if (type === 16) return i18n.docMotherIdBack;
    if (type === 17) return i18n.docResidenceFront;
    if (type === 18) return i18n.docResidenceBack;
    if (type === 19) return i18n.docGraduationCertificate;
    return i18n.documentTypeOther;
}

function formatWhen(value: string | null): string {
    if (value === null || value === '') {
        return '—';
    }

    return formatAdmissionDateTime(value);
}

function transitionClassName(status: number): string {
    const tone = TRANSITION_TONE[status] ?? 'dark';

    return `sis-admission-drafts-table__transition sis-admission-drafts-table__transition--tone-${tone}`;
}

function documentsForApplication(
    workspace: AdmissionWorkspace,
    applicationId: number,
): string[] {
    return workspace.documents
        .filter((doc) => doc.application_id === applicationId)
        .map((doc) => documentTypeLabel(doc.document_type));
}

function CellAccordion({
    summary,
    children,
}: {
    summary: string;
    children: ReactNode;
}) {
    return (
        <details className="sis-admission-collapse">
            <summary className="sis-admission-collapse__summary" title={summary}>
                {summary}
            </summary>
            <div className="sis-admission-collapse__body">{children}</div>
        </details>
    );
}

/** Temporary short display — formal abbreviations come later. */
function displayText(value: string | null | undefined): string {
    const trimmed = (value ?? '').trim();

    return trimmed === '' ? '—' : trimmed;
}

function uniqueTransitions(
    workspace: AdmissionWorkspace,
    apps: AdmissionApplication[],
): number[] {
    const unique: number[] = [];
    for (const app of apps) {
        for (const value of admissionAllowedTransitions(workspace, app)) {
            if (!unique.includes(value)) {
                unique.push(value);
            }
        }
    }

    return withConvertTransition(apps, unique);
}

function intersectTransitions(
    workspace: AdmissionWorkspace,
    apps: AdmissionApplication[],
): number[] {
    if (apps.length === 0) {
        return [];
    }

    const shared = apps.reduce(
        (acc, app) =>
            acc.filter((value) =>
                admissionAllowedTransitions(workspace, app).includes(value),
            ),
        admissionAllowedTransitions(workspace, apps[0]),
    );

    return withConvertTransition(apps, shared);
}

/** Accepted applications expose convert-to-student first, then other transitions. */
function withConvertTransition(
    apps: AdmissionApplication[],
    transitions: number[],
): number[] {
    if (apps.length === 0 || !apps.every((app) => admissionCanConvert(app))) {
        return transitions;
    }

    const withoutConvert = transitions.filter(
        (value) => value !== ADMISSION_STATUS_CONVERTED,
    );

    return [ADMISSION_STATUS_CONVERTED, ...withoutConvert];
}

type DraftRowHandle = {
    save: () => void;
};

type DraftEditorRowProps = {
    app: AdmissionApplication;
    workspace: AdmissionWorkspace;
    rowNumber: number;
    canManage: boolean;
    canViewStudentPii: boolean;
    selected: boolean;
    checked: boolean;
    editing: boolean;
    searchQuery: string;
    onSelect: (applicationId: number) => void;
    onToggleChecked: (applicationId: number) => void;
    onSaved: () => void;
};

const DraftEditorRow = forwardRef<DraftRowHandle, DraftEditorRowProps>(function DraftEditorRow(
    {
        app,
        workspace,
        rowNumber,
        canManage,
        canViewStudentPii,
        selected,
        checked,
        editing,
        searchQuery,
        onSelect,
        onToggleChecked,
        onSaved,
    },
    ref,
) {
    const i18n = t().admission;
    const { showInertiaErrors } = usePageError();
    const errorsI18n = t().errors;
    const [notes, setNotes] = useState(app.notes ?? '');
    const [reviewedAt, setReviewedAt] = useState(app.reviewed_at ?? '');
    const [saving, setSaving] = useState(false);
    const documentLabels = documentsForApplication(workspace, app.id);
    const documentsLabel = documentLabels.join('، ');
    const hasNotes = (app.notes?.trim() ?? '') !== '' || (editing && notes.trim() !== '');
    const hasDocuments = documentLabels.length > 0;

    useEffect(() => {
        if (editing) {
            return;
        }

        setNotes(app.notes ?? '');
        setReviewedAt(app.reviewed_at ?? '');
    }, [app, editing]);

    const save = useCallback(() => {
        if (saving) {
            return;
        }

        setSaving(true);
        router.put(
            `/admission/applications/${app.id}`,
            {
                notes: notes.trim() === '' ? null : notes.trim(),
                reviewed_at: reviewedAt === '' ? null : reviewedAt,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => onSaved(),
                onError: (errors) => showInertiaErrors(errors, errorsI18n.saveFailed),
                onFinish: () => setSaving(false),
            },
        );
    }, [app.id, errorsI18n.saveFailed, notes, onSaved, reviewedAt, saving, showInertiaErrors]);

    useImperativeHandle(ref, () => ({ save }), [save]);

    return (
        <tr
            className={selected || checked ? 'sis-admission-periods-table__row--selected' : undefined}
            aria-selected={selected || checked}
            onClick={() => onSelect(app.id)}
        >
            {canManage ? (
                <td className="sis-admission-drafts-table__select">
                    <input
                        type="checkbox"
                        checked={checked}
                        aria-label={i18n.selectApplication}
                        onClick={(event) => event.stopPropagation()}
                        onChange={() => onToggleChecked(app.id)}
                    />
                </td>
            ) : null}
            <td className="sis-admission-drafts-table__num">
                <span dir="ltr">{rowNumber}</span>
            </td>
            <td className="sis-admission-drafts-table__name">
                {admissionSearchSegments(admissionApplicationFullName(app), searchQuery).map(
                    (segment, index) =>
                        segment.hit ? (
                            <mark
                                key={`hit-${index}`}
                                className="sis-admission-search-hit"
                            >
                                {segment.text}
                            </mark>
                        ) : (
                            <span key={`plain-${index}`}>{segment.text}</span>
                        ),
                )}
            </td>
            <td className="sis-admission-drafts-table__text sis-admission-drafts-table__text--wide">
                {displayText(
                    lookupName(
                        workspace.grade_levels,
                        app.grade_level_id,
                        app.intended_grade_name,
                    ),
                )}
            </td>
            <td className="sis-admission-drafts-table__text sis-admission-drafts-table__text--wide">
                {displayText(app.department_name)}
            </td>
            <td className="sis-admission-drafts-table__text sis-admission-drafts-table__text--wide">
                {displayText(
                    lookupName(
                        workspace.specializations,
                        app.specialization_id,
                        app.specialization_name,
                    ),
                )}
            </td>
            <td className="sis-admission-drafts-table__text sis-admission-drafts-table__text--wide">
                {displayText(lookupName(workspace.periods, app.application_period_id))}
            </td>
            <td className="sis-admission-drafts-table__when sis-admission-drafts-table__when--submitted">
                {formatWhen(app.created_at)}
            </td>
            <td className="sis-admission-drafts-table__when-cell sis-admission-drafts-table__when--reviewed">
                {editing ? (
                    <AdmissionDateTimeField
                        name={`reviewed_at_${app.id}`}
                        dateOnly
                        defaultValue={app.reviewed_at ?? ''}
                        idPrefix={`draft-reviewed-${app.id}`}
                        onValueChange={setReviewedAt}
                    />
                ) : (
                    formatWhen(app.reviewed_at)
                )}
            </td>
            <td
                className="sis-admission-drafts-table__reviewer-cell"
                onClick={(event) => event.stopPropagation()}
                onPointerDown={(event) => event.stopPropagation()}
            >
                <SisListSelect
                    value="future"
                    options={[{ value: 'future', label: i18n.reviewerListSoon }]}
                    onChange={() => undefined}
                    disabled={!editing}
                    triggerClassName="sis-admission-drafts-table__reviewer"
                    dir="rtl"
                    ariaLabel={i18n.reviewedBy}
                />
            </td>
            <td
                className={
                    hasNotes
                        ? 'sis-admission-drafts-table__content-cell sis-admission-drafts-table__content-cell--filled'
                        : 'sis-admission-drafts-table__content-cell'
                }
            >
                {editing ? (
                    <input
                        className={
                            notes.trim() !== ''
                                ? 'sis-ops-hub__link sis-admission-drafts-table__notes-input sis-admission-drafts-table__notes-input--filled'
                                : 'sis-ops-hub__link sis-admission-drafts-table__notes-input'
                        }
                        name={`notes-${app.id}`}
                        value={notes}
                        dir="rtl"
                        aria-label={i18n.notes}
                        onClick={(event) => event.stopPropagation()}
                        onChange={(event) => setNotes(event.target.value)}
                    />
                ) : (app.notes?.trim() ?? '') !== '' ? (
                    <CellAccordion summary={app.notes?.trim() ?? ''}>
                        {app.notes?.trim()}
                    </CellAccordion>
                ) : (
                    <span className="sis-admission-collapse__empty">—</span>
                )}
            </td>
            <td
                className={
                    hasDocuments
                        ? 'sis-admission-drafts-table__content-cell sis-admission-drafts-table__content-cell--filled'
                        : 'sis-admission-drafts-table__content-cell'
                }
            >
                {hasDocuments ? (
                    <CellAccordion summary={documentsLabel}>
                        <ul className="sis-admission-collapse__list">
                            {documentLabels.map((docLabel, index) => (
                                <li key={`${app.id}-doc-${index}`}>{docLabel}</li>
                            ))}
                        </ul>
                    </CellAccordion>
                ) : (
                    <span className="sis-admission-collapse__empty">—</span>
                )}
            </td>
        </tr>
    );
});

/** Stage workspace table — same columns/options for draft and submitted. */
export function AdmissionDraftsCard({
    workspace,
    canManage,
    canViewStudentPii = false,
    status = ADMISSION_STATUS_DRAFT,
    yearFilterAction,
    academicYearId = null,
    homeHref = null,
}: Props) {
    const i18n = t();
    const { showInertiaErrors } = usePageError();
    const searchQuery = useAdmissionSearchQuery();
    const selectedRowRef = useRef<DraftRowHandle>(null);
    const selectAllRef = useRef<HTMLInputElement>(null);
    const tableRef = useRef<HTMLTableElement>(null);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [checkedIds, setCheckedIds] = useState<number[]>([]);
    const [editing, setEditing] = useState(false);
    const [withdrawTarget, setWithdrawTarget] = useState<AdmissionApplication | null>(null);
    const [withdrawing, setWithdrawing] = useState(false);
    const [transitioning, setTransitioning] = useState(false);

    const pagination = workspace.pagination ?? {
        page: 1,
        per_page: 17,
        total: 0,
        total_pages: 1,
    };
    const rowOffset = (pagination.page - 1) * pagination.per_page;

    // Server already filters by stage + Active periods and sorts by applicant name.
    const rows = workspace.applications;

    useResizableTableColumns(tableRef, {
        storageKey: 'admission.drafts',
        columnSignature: canManage ? 'manage' : 'readonly',
        enabled: rows.length > 0,
    });

    const hasSelection = selectedId !== null;
    const selectedDraft = rows.find((app) => app.id === selectedId) ?? null;
    const canWithdraw =
        selectedDraft !== null &&
        admissionAllowedTransitions(workspace, selectedDraft).includes(
            ADMISSION_STATUS_WITHDRAWN,
        );
    const canEditStage = status !== ADMISSION_STATUS_CONVERTED;
    const emptyMessage = (() => {
        if (searchQuery.trim() !== '') {
            return i18n.admission.emptySearch;
        }

        switch (status) {
            case ADMISSION_STATUS_DRAFT:
                return i18n.admission.emptyDrafts;
            case ADMISSION_STATUS_SUBMITTED:
                return i18n.admission.emptySubmitted;
            case ADMISSION_STATUS_UNDER_REVIEW:
                return i18n.admission.emptyUnderReview;
            case ADMISSION_STATUS_INTERVIEW:
                return i18n.admission.emptyInterview;
            case ADMISSION_STATUS_WAITLISTED:
                return i18n.admission.emptyWaitlisted;
            case ADMISSION_STATUS_ACCEPTED:
                return i18n.admission.emptyAccepted;
            case ADMISSION_STATUS_REJECTED:
                return i18n.admission.emptyRejected;
            case ADMISSION_STATUS_WITHDRAWN:
                return i18n.admission.emptyWithdrawn;
            case ADMISSION_STATUS_CONVERTED:
                return i18n.admission.emptyConverted;
            default:
                return i18n.admission.emptyApplications;
        }
    })();
    const stageLabel = applicationStatusLabel(status);
    const rowIds = useMemo(() => rows.map((app) => app.id), [rows]);
    const visibleCheckedIds = useMemo(
        () => checkedIds.filter((id) => rowIds.includes(id)),
        [checkedIds, rowIds],
    );
    const actionIds = useMemo(
        () => tableActionIds(visibleCheckedIds, selectedId),
        [selectedId, visibleCheckedIds],
    );
    const allChecked = rowIds.length > 0 && visibleCheckedIds.length === rowIds.length;
    const someChecked = visibleCheckedIds.length > 0 && !allChecked;
    const selectedApps = useMemo(
        () => rows.filter((app) => actionIds.includes(app.id)),
        [actionIds, rows],
    );
    const toolbarTransitions =
        selectedApps.length > 0
            ? intersectTransitions(workspace, selectedApps)
            : uniqueTransitions(workspace, rows);
    const canApplyTransition = canManage && actionIds.length > 0 && !transitioning;

    useEffect(() => {
        if (selectedId !== null && !rows.some((app) => app.id === selectedId)) {
            setSelectedId(null);
            setEditing(false);
        }
    }, [rows, selectedId]);

    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate = someChecked;
        }
    }, [someChecked]);

    const selectRow = useCallback((applicationId: number) => {
        const next = selectTableRow(applicationId);
        setSelectedId(next.selectedId);
        setCheckedIds(next.checkedIds);
        setEditing((wasEditing) => (selectedId === applicationId ? wasEditing : false));
    }, [selectedId]);

    const exitEditing = useCallback(() => setEditing(false), []);

    const toggleChecked = useCallback((applicationId: number) => {
        const next = toggleTableRowChecked(checkedIds, applicationId);
        setCheckedIds(next.checkedIds);
        setSelectedId(next.selectedId);
        if (next.selectedId === null) {
            setEditing(false);
        }
    }, [checkedIds]);

    const toggleAll = useCallback(() => {
        const next = toggleTableSelectAll(checkedIds, rowIds, selectedId);
        setCheckedIds(next.checkedIds);
        setSelectedId(next.selectedId);
        if (next.selectedId === null) {
            setEditing(false);
        }
    }, [checkedIds, rowIds, selectedId]);

    const clearTableSelection = useCallback(() => {
        setCheckedIds([]);
        setSelectedId(null);
        setEditing(false);
    }, []);

    useAdmissionSelectionClearer(
        clearTableSelection,
        hasSelection || checkedIds.length > 0 || editing,
    );

    const applyTransition = useCallback((toStatus: number) => {
        if (!canApplyTransition) {
            return;
        }

        setTransitioning(true);

        const clearSelection = () => {
            setCheckedIds([]);
            setSelectedId(null);
            setEditing(false);
            setTransitioning(false);
        };

        if (toStatus === ADMISSION_STATUS_CONVERTED) {
            const ids = [...actionIds];
            const convertNext = (index: number) => {
                if (index >= ids.length) {
                    clearSelection();
                    return;
                }

                const isLast = index === ids.length - 1;
                router.post(
                    `/admission/applications/${ids[index]}/convert`,
                    {},
                    {
                        preserveScroll: !isLast,
                        preserveState: !isLast,
                        onSuccess: () => {
                            if (!isLast) {
                                convertNext(index + 1);
                            } else {
                                clearSelection();
                            }
                        },
                        onError: (errors) => {
                            setTransitioning(false);
                            showInertiaErrors(errors, i18n.errors.convertFailed);
                        },
                        onFinish: () => {
                            if (isLast) {
                                setTransitioning(false);
                            }
                        },
                    },
                );
            };

            convertNext(0);
            return;
        }

        router.post(
            '/admission/applications/bulk-transition',
            {
                application_ids: actionIds,
                to_status: toStatus,
                academic_year_id: academicYearId,
            },
            {
                preserveScroll: toStatus !== ADMISSION_STATUS_ACCEPTED,
                preserveState: toStatus !== ADMISSION_STATUS_ACCEPTED,
                onSuccess: () => {
                    setCheckedIds([]);
                    setSelectedId(null);
                    setEditing(false);
                },
                onError: (errors) => showInertiaErrors(errors, i18n.errors.transitionFailed),
                onFinish: () => setTransitioning(false),
            },
        );
    }, [academicYearId, actionIds, canApplyTransition, i18n.errors.convertFailed, i18n.errors.transitionFailed, showInertiaErrors]);

    const goPage = useCallback(
        (page: number) => {
            if (!yearFilterAction || page < 1 || page > pagination.total_pages || page === pagination.page) {
                return;
            }

            router.visit(
                `${yearFilterAction}${admissionWorkspaceQuery(
                    academicYearId,
                    workspace.selected_period_id,
                    page,
                    searchQuery,
                )}`,
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['workspace', 'filters'],
                },
            );
        },
        [
            academicYearId,
            pagination.page,
            pagination.total_pages,
            searchQuery,
            workspace.selected_period_id,
            yearFilterAction,
        ],
    );

    const ribbonGroups = useMemo((): PageRibbonGroup[] => {
        const groups: PageRibbonGroup[] = [];

        if (homeHref) {
            groups.push({
                id: 'admission-home',
                label: i18n.admission.homeCaption,
                commands: [
                    {
                        id: 'admission-home',
                        label: i18n.admission.homeCaption,
                        icon: Home,
                        onSelect: () => {
                            router.visit(homeHref);
                        },
                    },
                ],
            });
        }

        if (!canManage) {
            return groups;
        }

        groups.push({
            id: 'admission-draft-actions',
            label: i18n.common.actions,
            commands: [
                {
                    id: 'edit-draft',
                    label: i18n.common.edit,
                    icon: Pencil,
                    disabled: !hasSelection || !canEditStage,
                    onSelect: () => setEditing(true),
                },
                {
                    id: 'save-draft',
                    label: i18n.common.save,
                    icon: Save,
                    disabled: !hasSelection || !editing || !canEditStage,
                    onSelect: () => selectedRowRef.current?.save(),
                },
                {
                    id: 'delete-draft',
                    label: i18n.common.delete,
                    icon: Trash2,
                    disabled: !hasSelection || !canWithdraw,
                    onSelect: () => {
                        if (selectedDraft) {
                            setWithdrawTarget(selectedDraft);
                        }
                    },
                },
            ],
        });

        return groups;
    }, [
        canEditStage,
        canManage,
        canWithdraw,
        editing,
        hasSelection,
        homeHref,
        i18n.admission.homeCaption,
        i18n.common.actions,
        i18n.common.delete,
        i18n.common.edit,
        i18n.common.save,
        selectedDraft,
    ]);

    useRegisterPageRibbon('home', ribbonGroups);

    const confirmWithdraw = () => {
        if (withdrawTarget === null) {
            return;
        }

        setWithdrawing(true);
        router.post(
            `/admission/applications/${withdrawTarget.id}/transition`,
            { to_status: ADMISSION_STATUS_WITHDRAWN },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if (selectedId === withdrawTarget.id) {
                        setSelectedId(null);
                        setEditing(false);
                    }
                    setCheckedIds((current) => current.filter((id) => id !== withdrawTarget.id));
                },
                onError: (errors) => showInertiaErrors(errors, i18n.errors.deleteFailed),
                onFinish: () => {
                    setWithdrawing(false);
                    setWithdrawTarget(null);
                },
            },
        );
    };

    return (
        <section aria-label={stageLabel} className="flex min-h-0 flex-1 flex-col gap-3">
            {homeHref ? (
                <div className="sis-admission-drafts-table-home">
                    <Link
                        href={homeHref}
                        prefetch
                        className="sis-admission-drafts-home"
                        aria-label={i18n.admission.backToAdmission}
                    >
                        <ion-icon
                            name="arrow-up-right-box-outline"
                            class="sis-admission-drafts-home__ion"
                            dir="ltr"
                            flip-rtl="false"
                            aria-hidden="true"
                        ></ion-icon>
                    </Link>
                </div>
            ) : null}
            {rows.length === 0 ? (
                <p className="text-sm">{emptyMessage}</p>
            ) : (
                <>
                {canManage && toolbarTransitions.length > 0 ? (
                    <div
                        className="sis-admission-drafts-transitions"
                        role="toolbar"
                        aria-label={i18n.admission.nextStatuses}
                    >
                        <span className="sis-admission-drafts-transitions__label">
                            {i18n.admission.nextStatuses}
                        </span>
                        <div className="sis-admission-drafts-table__transitions">
                            {toolbarTransitions.map((value) => (
                                <button
                                    key={value}
                                    type="button"
                                    className={transitionClassName(value)}
                                    data-status={value}
                                    disabled={!canApplyTransition}
                                    title={
                                        canApplyTransition
                                            ? undefined
                                            : i18n.admission.transitionsNeedSelection
                                    }
                                    onClick={() => applyTransition(value)}
                                >
                                    {applicationStatusLabel(value)}
                                </button>
                            ))}
                        </div>
                    </div>
                ) : null}
                <div className="sis-admission-periods-table sis-admission-drafts-table">
                    <div className="sis-admission-drafts-table__scroller" data-allow-x-scroll>
                    <table ref={tableRef}>
                        <thead>
                            <tr>
                                {canManage ? (
                                    <th className="sis-admission-drafts-table__select">
                                        <input
                                            ref={selectAllRef}
                                            type="checkbox"
                                            checked={allChecked}
                                            aria-label={i18n.admission.selectAllApplications}
                                            onChange={toggleAll}
                                        />
                                    </th>
                                ) : null}
                                <th className="sis-admission-drafts-table__num">#</th>
                                <th className="sis-admission-drafts-table__name-head">{i18n.admission.quadName}</th>
                                <th title={i18n.admission.gradeLevel}>{i18n.admission.gradeLevelAbbr}</th>
                                <th title={i18n.admission.department}>{i18n.admission.departmentAbbr}</th>
                                <th title={i18n.admission.specialization}>{i18n.admission.specializationAbbr}</th>
                                <th title={i18n.admission.periodName}>{i18n.admission.periodNameAbbr}</th>
                                <th className="sis-admission-drafts-table__when--submitted">{i18n.admission.submittedAt}</th>
                                <th className="sis-admission-drafts-table__when--reviewed">{i18n.admission.reviewedAt}</th>
                                <th className="sis-admission-drafts-table__reviewer-cell" title={i18n.admission.reviewedBy}>
                                    {i18n.admission.reviewedByAbbr}
                                </th>
                                <th>{i18n.admission.notes}</th>
                                <th>{i18n.admission.documentType}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((app, index) => (
                                <DraftEditorRow
                                    key={app.id}
                                    ref={selectedId === app.id ? selectedRowRef : null}
                                    app={app}
                                    workspace={workspace}
                                    rowNumber={rowOffset + index + 1}
                                    canManage={canManage}
                                    canViewStudentPii={canViewStudentPii || canManage}
                                    selected={selectedId === app.id}
                                    checked={visibleCheckedIds.includes(app.id)}
                                    editing={editing && selectedId === app.id}
                                    searchQuery={searchQuery}
                                    onSelect={selectRow}
                                    onToggleChecked={toggleChecked}
                                    onSaved={exitEditing}
                                />
                            ))}
                        </tbody>
                    </table>
                    </div>
                </div>
                {pagination.total > 0 ? (
                    <nav
                        className="sis-admission-drafts-pagination"
                        aria-label={i18n.common.page}
                    >
                        <ul className="sis-admission-pagination" dir="ltr">
                            <li className="sis-admission-pagination__item">
                                <button
                                    type="button"
                                    className="sis-admission-pagination__link"
                                    aria-label={i18n.common.previous}
                                    disabled={pagination.page <= 1}
                                    onClick={() => goPage(pagination.page - 1)}
                                >
                                    <span aria-hidden="true">&laquo;</span>
                                </button>
                            </li>
                            {visiblePages(pagination.page, pagination.total_pages).map((pageNum) => (
                                <li key={pageNum} className="sis-admission-pagination__item">
                                    <button
                                        type="button"
                                        className={
                                            pageNum === pagination.page
                                                ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                                : 'sis-admission-pagination__link'
                                        }
                                        aria-label={`${i18n.common.page} ${pageNum}`}
                                        aria-current={pageNum === pagination.page ? 'page' : undefined}
                                        onClick={() => goPage(pageNum)}
                                    >
                                        {pageNum}
                                    </button>
                                </li>
                            ))}
                            <li className="sis-admission-pagination__item">
                                <button
                                    type="button"
                                    className="sis-admission-pagination__link"
                                    aria-label={i18n.common.next}
                                    disabled={pagination.page >= pagination.total_pages}
                                    onClick={() => goPage(pagination.page + 1)}
                                >
                                    <span aria-hidden="true">&raquo;</span>
                                </button>
                            </li>
                        </ul>
                    </nav>
                ) : null}
                </>
            )}

            <ConfirmDialog
                open={withdrawTarget !== null}
                title={i18n.admission.withdrawDraftTitle}
                description={i18n.admission.withdrawDraftConfirm}
                confirmLabel={i18n.admission.withdrawDraft}
                tone="danger"
                confirmPending={withdrawing}
                onConfirm={confirmWithdraw}
                onOpenChange={(open) => {
                    if (!open && !withdrawing) {
                        setWithdrawTarget(null);
                    }
                }}
            />
        </section>
    );
}
