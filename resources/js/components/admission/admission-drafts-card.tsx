import { router } from '@inertiajs/react';
import { Pencil, Save, Trash2 } from 'lucide-react';
import {
    forwardRef,
    useCallback,
    useEffect,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
} from 'react';
import { AdmissionDateTimeField } from '@/components/admission/admission-date-time-field';
import {
    ADMISSION_STATUS_ACCEPTED,
    ADMISSION_STATUS_CONVERTED,
    ADMISSION_STATUS_DRAFT,
    ADMISSION_STATUS_INTERVIEW,
    ADMISSION_STATUS_SUBMITTED,
    ADMISSION_STATUS_UNDER_REVIEW,
    ADMISSION_STATUS_WAITLISTED,
    admissionApplicationFullName,
    lookupName,
    type AdmissionApplication,
    type AdmissionWorkspace,
} from '@/components/admission/admission-workspace';
import { formatAdmissionDateTime } from '@/components/admission/format-admission-datetime';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import {
    useRegisterPageRibbon,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';

const ADMISSION_STATUS_WITHDRAWN = 8;

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
    status?: number;
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
): string {
    const labels = workspace.documents
        .filter((doc) => doc.application_id === applicationId)
        .map((doc) => documentTypeLabel(doc.document_type));

    return labels.length > 0 ? labels.join('، ') : '—';
}

function uniqueTransitions(apps: AdmissionApplication[]): number[] {
    const unique: number[] = [];
    for (const app of apps) {
        for (const value of app.allowed_transitions) {
            if (!unique.includes(value)) {
                unique.push(value);
            }
        }
    }

    return withConvertTransition(apps, unique);
}

function intersectTransitions(apps: AdmissionApplication[]): number[] {
    if (apps.length === 0) {
        return [];
    }

    const shared = apps.reduce(
        (acc, app) => acc.filter((value) => app.allowed_transitions.includes(value)),
        apps[0].allowed_transitions,
    );

    return withConvertTransition(apps, shared);
}

/** Accepted applications expose convert-to-student beside manual transitions. */
function withConvertTransition(
    apps: AdmissionApplication[],
    transitions: number[],
): number[] {
    if (apps.length === 0 || !apps.every((app) => app.can_convert)) {
        return transitions;
    }

    if (transitions.includes(ADMISSION_STATUS_CONVERTED)) {
        return transitions;
    }

    return [...transitions, ADMISSION_STATUS_CONVERTED];
}

type DraftRowHandle = {
    save: () => void;
};

type DraftEditorRowProps = {
    app: AdmissionApplication;
    workspace: AdmissionWorkspace;
    index: number;
    canManage: boolean;
    selected: boolean;
    checked: boolean;
    editing: boolean;
    onSelect: (applicationId: number) => void;
    onToggleChecked: (applicationId: number) => void;
    onSaved: () => void;
};

const DraftEditorRow = forwardRef<DraftRowHandle, DraftEditorRowProps>(function DraftEditorRow(
    { app, workspace, index, canManage, selected, checked, editing, onSelect, onToggleChecked, onSaved },
    ref,
) {
    const i18n = t().admission;
    const [notes, setNotes] = useState(app.notes ?? '');
    const [reviewedAt, setReviewedAt] = useState(app.reviewed_at ?? '');
    const [saving, setSaving] = useState(false);

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
                onFinish: () => setSaving(false),
            },
        );
    }, [app.id, notes, onSaved, reviewedAt, saving]);

    useImperativeHandle(ref, () => ({ save }), [save]);

    return (
        <tr
            className={selected ? 'sis-admission-periods-table__row--selected' : undefined}
            aria-selected={selected}
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
                <span dir="ltr">{index + 1}</span>
            </td>
            <td className="sis-admission-drafts-table__name">
                {admissionApplicationFullName(app)}
            </td>
            <td>{lookupName(workspace.schools, app.target_school_id)}</td>
            <td>
                {lookupName(
                    workspace.grade_levels,
                    app.grade_level_id,
                    app.intended_grade_name,
                )}
            </td>
            <td>{app.department_name?.trim() || '—'}</td>
            <td>
                {lookupName(
                    workspace.specializations,
                    app.specialization_id,
                    app.specialization_name,
                )}
            </td>
            <td>{lookupName(workspace.periods, app.application_period_id)}</td>
            <td className="sis-admission-drafts-table__when">{formatWhen(app.created_at)}</td>
            <td className="sis-admission-drafts-table__when-cell">
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
            <td>
                <select
                    className="sis-admission-drafts-table__reviewer"
                    aria-label={i18n.reviewedBy}
                    defaultValue="future"
                    disabled={!editing}
                    onClick={(event) => event.stopPropagation()}
                >
                    <option value="future">{i18n.reviewerListSoon}</option>
                </select>
            </td>
            <td>
                {editing ? (
                    <input
                        className="sis-ops-hub__link"
                        name={`notes-${app.id}`}
                        value={notes}
                        dir="rtl"
                        aria-label={i18n.notes}
                        onClick={(event) => event.stopPropagation()}
                        onChange={(event) => setNotes(event.target.value)}
                    />
                ) : (
                    app.notes?.trim() || '—'
                )}
            </td>
            <td>{documentsForApplication(workspace, app.id)}</td>
        </tr>
    );
});

/** Stage workspace table — same columns/options for draft and submitted. */
export function AdmissionDraftsCard({
    workspace,
    canManage,
    status = ADMISSION_STATUS_DRAFT,
}: Props) {
    const i18n = t();
    const selectedRowRef = useRef<DraftRowHandle>(null);
    const selectAllRef = useRef<HTMLInputElement>(null);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [checkedIds, setCheckedIds] = useState<number[]>([]);
    const [editing, setEditing] = useState(false);
    const [withdrawTarget, setWithdrawTarget] = useState<AdmissionApplication | null>(null);
    const [withdrawing, setWithdrawing] = useState(false);
    const [transitioning, setTransitioning] = useState(false);

    const rows = useMemo(
        () =>
            workspace.applications
                .filter((app) => app.status === status)
                .slice()
                .sort((left, right) => {
                    const leftAt = Date.parse(left.created_at);
                    const rightAt = Date.parse(right.created_at);

                    if (leftAt !== rightAt) {
                        return leftAt - rightAt;
                    }

                    return left.id - right.id;
                }),
        [status, workspace.applications],
    );

    const hasSelection = selectedId !== null;
    const selectedDraft = rows.find((app) => app.id === selectedId) ?? null;
    const canWithdraw =
        selectedDraft !== null &&
        selectedDraft.allowed_transitions.includes(ADMISSION_STATUS_WITHDRAWN);
    const canEditStage = status !== ADMISSION_STATUS_CONVERTED;
    const emptyMessage = (() => {
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
    const allChecked = rowIds.length > 0 && visibleCheckedIds.length === rowIds.length;
    const someChecked = visibleCheckedIds.length > 0 && !allChecked;
    const selectedApps = useMemo(
        () => rows.filter((app) => visibleCheckedIds.includes(app.id)),
        [rows, visibleCheckedIds],
    );
    const toolbarTransitions =
        selectedApps.length > 0 ? intersectTransitions(selectedApps) : uniqueTransitions(rows);
    const canApplyTransition = canManage && visibleCheckedIds.length > 0 && !transitioning;

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
        setSelectedId(applicationId);
        setEditing((wasEditing) => (selectedId === applicationId ? wasEditing : false));
    }, [selectedId]);

    const exitEditing = useCallback(() => setEditing(false), []);

    const toggleChecked = useCallback((applicationId: number) => {
        setCheckedIds((current) =>
            current.includes(applicationId)
                ? current.filter((id) => id !== applicationId)
                : [...current, applicationId],
        );
    }, []);

    const toggleAll = useCallback(() => {
        setCheckedIds((current) => {
            const visible = current.filter((id) => rowIds.includes(id));

            return visible.length === rowIds.length ? [] : rowIds;
        });
    }, [rowIds]);

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
            const ids = [...visibleCheckedIds];
            const convertNext = (index: number) => {
                if (index >= ids.length) {
                    clearSelection();
                    return;
                }

                router.post(
                    `/admission/applications/${ids[index]}/convert`,
                    {},
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => convertNext(index + 1),
                        onError: () => setTransitioning(false),
                    },
                );
            };

            convertNext(0);
            return;
        }

        router.post(
            '/admission/applications/bulk-transition',
            {
                application_ids: visibleCheckedIds,
                to_status: toStatus,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setCheckedIds([]);
                    setSelectedId(null);
                    setEditing(false);
                },
                onFinish: () => setTransitioning(false),
            },
        );
    }, [canApplyTransition, visibleCheckedIds]);

    const ribbonGroups = useMemo((): PageRibbonGroup[] => {
        if (!canManage) {
            return [];
        }

        return [
            {
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
            },
        ];
    }, [
        canEditStage,
        canManage,
        canWithdraw,
        editing,
        hasSelection,
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
                onFinish: () => {
                    setWithdrawing(false);
                    setWithdrawTarget(null);
                },
            },
        );
    };

    return (
        <section aria-label={stageLabel} className="flex min-h-0 flex-1 flex-col">
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
                    <div
                        className="sis-admission-periods-table__scroller"
                        data-allow-x-scroll
                    >
                    <table>
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
                                <th>{i18n.admission.quadName}</th>
                                <th>{i18n.admission.school}</th>
                                <th>{i18n.admission.gradeLevel}</th>
                                <th>{i18n.admission.department}</th>
                                <th>{i18n.admission.specialization}</th>
                                <th>{i18n.admission.periodName}</th>
                                <th>{i18n.admission.submittedAt}</th>
                                <th>{i18n.admission.reviewedAt}</th>
                                <th>{i18n.admission.reviewedBy}</th>
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
                                    index={index}
                                    canManage={canManage}
                                    selected={selectedId === app.id}
                                    checked={visibleCheckedIds.includes(app.id)}
                                    editing={editing && selectedId === app.id}
                                    onSelect={selectRow}
                                    onToggleChecked={toggleChecked}
                                    onSaved={exitEditing}
                                />
                            ))}
                        </tbody>
                    </table>
                    </div>
                </div>
                </>
            )}

            <ConfirmDialog
                open={withdrawTarget !== null}
                title={i18n.admission.withdrawDraftTitle}
                description={i18n.admission.withdrawDraftConfirm}
                confirmLabel={i18n.admission.withdrawDraft}
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
