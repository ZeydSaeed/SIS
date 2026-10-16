import { Link, router } from '@inertiajs/react';
import { Eye, Inbox, Pencil, Save, X, XCircle } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import {
    ADMISSION_STATUS_REJECTED,
    ADMISSION_STATUS_WITHDRAWN,
    type AdmissionAcceptedStudent,
} from '@/components/admission/admission-workspace';
import {
    categoryOf,
    categoryToken,
    filterValues,
    FOLLOW_UP_LIST_PATH,
    followUpCategories,
    followUpShowHref,
    useFollowUpQuery,
    useFollowUpRoster,
} from '@/components/admission/follow-up/follow-up-data';
import { useFollowUpFiltersRibbon } from '@/components/admission/follow-up/follow-up-filters-ribbon';
import {
    applicationStatusLabel,
    FOLLOW_UP_TRANSITIONS,
    useFollowUpTransitions,
} from '@/components/admission/follow-up/follow-up-transitions';
import {
    CaptionCount,
    type FollowUpApplication,
} from '@/components/admission/follow-up/follow-up-show';
import { FollowUpViewDialog } from '@/components/admission/follow-up/follow-up-view-dialog';
import { usePageError } from '@/components/sis/page-error-context';
import {
    useRegisterPageRibbon,
    type PageRibbonCommand,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import {
    ariaSort,
    SortButton,
    useTableSort,
} from '@/components/sis/table-sort';
import {
    selectTableRow,
    tableActionIds,
    toggleTableRowChecked,
    toggleTableSelectAll,
    type TableRowSelection,
} from '@/components/sis/table-row-selection';
import { useFitTablePageSize } from '@/hooks/use-fit-table-page-size';
import { hasPageTextSelection } from '@/hooks/use-page-clipboard';
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
import { useSmoothVerticalScroll } from '@/hooks/use-smooth-vertical-scroll';
import { t } from '@/i18n';

type Props = {
    students: AdmissionAcceptedStudent[];
    schools: Array<{ id: number; name: string }>;
    defaultSchoolId: number | null;
    defaultYearId: number | null;
    canManage: boolean;
    canViewPii: boolean;
};

type EditDraft = {
    full_name: string;
    rejection_reason: string;
    withdrawal_reason: string;
};

/** Five page numbers around the current one — the students list pager. */
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

/** A cell's text on one line, scrolled sideways when the column is narrower — as on the students table. */
function CellScroll({ children }: { children: ReactNode }) {
    return <div className="sis-students-table__cell-scroll">{children}</div>;
}

function Highlight({
    text,
    query,
}: {
    text: string;
    query: string;
}): ReactNode {
    if (query === '' || text === '') {
        return text;
    }
    const at = text
        .toLocaleLowerCase('ar')
        .indexOf(query.toLocaleLowerCase('ar'));
    if (at === -1) {
        return text;
    }

    return (
        <>
            {text.slice(0, at)}
            <mark className="sis-admission-search-hit">
                {text.slice(at, at + query.length)}
            </mark>
            {text.slice(at + query.length)}
        </>
    );
}

/**
 * «متابعة طلبات التقديم» › a category — the EXAMIS staff list: heading line with removable filter chips and one
 * framed table; the students list pager under it. Rows are selected like the students table (click a row, tick
 * one or more, or the whole page) and the «تحرير» ribbon acts on the selection: view, edit, status moves.
 */
export function FollowUpList({
    students,
    schools,
    defaultSchoolId,
    defaultYearId,
    canManage,
    canViewPii,
}: Props) {
    const i18n = t();
    const a = i18n.admission;
    const { showInertiaErrors } = usePageError();
    const categories = useMemo(() => followUpCategories(), []);
    const { query, visit } = useFollowUpQuery(FOLLOW_UP_LIST_PATH);
    const { rows, schoolIds, periodIds, yearId, options } = useFollowUpRoster(
        students,
        query,
        schools,
        defaultSchoolId,
        defaultYearId,
    );
    const [selection, setSelection] = useState<TableRowSelection>({
        selectedId: null,
        checkedIds: [],
    });
    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);
    const [drafts, setDrafts] = useState<Record<number, EditDraft>>({});

    /** The chosen categories; none = every category. */
    const chosenCategories = useMemo(() => {
        const keys = filterValues(query.category);

        return categories.filter((item) => keys.includes(item.key));
    }, [categories, query.category]);
    const listed = useMemo(
        () =>
            rows
                .filter((row) =>
                    chosenCategories.length === 0
                        ? categoryOf(row, categories) !== null
                        : chosenCategories.some((item) => item.matches(row)),
                )
                .sort((left, right) =>
                    left.full_name.localeCompare(right.full_name, 'ar'),
                ),
        [categories, chosenCategories, rows],
    );
    const showReason =
        chosenCategories.length === 0 ||
        chosenCategories.some((item) => item.reason !== undefined);

    // Rows per page = rows that fit the table area, measured like the students table.
    const scrollerRef = useRef<HTMLDivElement | null>(null);
    const tableRef = useRef<HTMLTableElement | null>(null);
    const perPage = useFitTablePageSize(scrollerRef, {
        enabled: listed.length > 0,
    });
    const lastPage = Math.max(1, Math.ceil(listed.length / perPage));
    const page = Math.min(query.page, lastPage);
    const from = (page - 1) * perPage;
    const pageSlice = useMemo(
        () => listed.slice(from, from + perPage),
        [from, listed, perPage],
    );
    // Header click sorts the page on screen, as on the students table.
    const { rows: pageRows, sort } = useTableSort(pageSlice, (row, key) => {
        switch (key) {
            case 'name':
                return row.full_name;
            case 'category':
                return categoryOf(row, categories)?.label ?? null;
            case 'year':
                return row.academic_year_name;
            case 'period':
                return row.period_name;
            case 'school':
                return row.school_name;
            case 'reason': {
                const reasonKey = categoryOf(row, categories)?.reason;

                return reasonKey ? (row[reasonKey]?.trim() ?? null) : null;
            }
            default:
                return null;
        }
    });
    const pageIds = useMemo(() => pageRows.map((row) => row.id), [pageRows]);

    // Every column opens as wide as its longest text; fitted again when the rows on screen change.
    useResizableTableColumns(tableRef, {
        storageKey: 'admission.follow-up.list',
        columnSignature: `select:category${showReason ? ':reason' : ''}:${pageIds.join(',')}`,
        enabled: listed.length > 0,
        fitToContent: true,
    });
    useSmoothVerticalScroll(scrollerRef, listed.length > 0);

    // A new list (filters, category, page, search) starts with nothing selected and nothing being edited.
    const listKey = `${query.school}|${query.year}|${query.period}|${query.category}|${query.q}|${page}`;
    useEffect(() => {
        setSelection({ selectedId: null, checkedIds: [] });
        setEditing(false);
        setDrafts({});
    }, [listKey]);

    const actionIds = useMemo(
        () =>
            tableActionIds(selection.checkedIds, selection.selectedId).filter(
                (id) => listed.some((row) => row.id === id),
            ),
        [listed, selection],
    );
    const actionRows = useMemo(
        () => listed.filter((row) => actionIds.includes(row.id)),
        [actionIds, listed],
    );
    const actionSchoolIds = [
        ...new Set(actionRows.map((row) => row.school_id)),
    ];
    const actionSchoolId =
        actionSchoolIds.length === 1 ? actionSchoolIds[0] : null;

    const clearSelection = useCallback(() => {
        setSelection({ selectedId: null, checkedIds: [] });
        setEditing(false);
        setDrafts({});
    }, []);

    const {
        request: requestTransition,
        busy: moving,
        dialogs,
    } = useFollowUpTransitions(clearSelection);

    const beginEdit = useCallback(() => {
        const next: Record<number, EditDraft> = {};
        for (const row of actionRows) {
            next[row.id] = {
                full_name: row.full_name,
                rejection_reason: row.rejection_reason?.trim() ?? '',
                withdrawal_reason: row.withdrawal_reason?.trim() ?? '',
            };
        }
        setDrafts(next);
        setEditing(true);
    }, [actionRows]);

    const patchDraft = (id: number, patch: Partial<EditDraft>) =>
        setDrafts((current) => ({
            ...current,
            [id]: { ...current[id], ...patch },
        }));

    const saveEdits = useCallback(() => {
        if (!editing || saving || actionSchoolId === null) {
            return;
        }
        const updates: Array<{
            application_id: number;
            full_name: string;
            rejection_reason: string | null;
            withdrawal_reason: string | null;
            update_rejection_reason: boolean;
            update_withdrawal_reason: boolean;
        }> = [];

        for (const row of actionRows) {
            const draft = drafts[row.id];
            if (draft === undefined) {
                continue;
            }
            const nameChanged = draft.full_name.trim() !== row.full_name.trim();
            const rejectionChanged =
                row.status === ADMISSION_STATUS_REJECTED &&
                draft.rejection_reason.trim() !==
                    (row.rejection_reason?.trim() ?? '');
            const withdrawalChanged =
                row.status === ADMISSION_STATUS_WITHDRAWN &&
                draft.withdrawal_reason.trim() !==
                    (row.withdrawal_reason?.trim() ?? '');
            if (!nameChanged && !rejectionChanged && !withdrawalChanged) {
                continue;
            }
            if (draft.full_name.trim() === '') {
                showInertiaErrors(
                    { full_name: i18n.errors.saveFailed },
                    i18n.errors.saveFailed,
                );

                return;
            }
            if (
                (rejectionChanged && draft.rejection_reason.trim() === '') ||
                (withdrawalChanged && draft.withdrawal_reason.trim() === '')
            ) {
                showInertiaErrors(
                    { reason: a.reasonRequiredHint },
                    a.reasonRequiredHint,
                );

                return;
            }
            updates.push({
                application_id: row.id,
                full_name: draft.full_name.trim(),
                rejection_reason: rejectionChanged
                    ? draft.rejection_reason.trim()
                    : null,
                withdrawal_reason: withdrawalChanged
                    ? draft.withdrawal_reason.trim()
                    : null,
                update_rejection_reason: rejectionChanged,
                update_withdrawal_reason: withdrawalChanged,
            });
        }

        if (updates.length === 0) {
            setEditing(false);
            setDrafts({});

            return;
        }
        setSaving(true);
        router.put(
            '/admission/applications/follow-up',
            { updates },
            {
                // Saved inside the school of the selected applications.
                headers: { 'X-School-Id': String(actionSchoolId) },
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setEditing(false);
                    setDrafts({});
                },
                onError: (errors) =>
                    showInertiaErrors(errors, i18n.errors.saveFailed),
                onFinish: () => setSaving(false),
            },
        );
    }, [
        a.reasonRequiredHint,
        actionRows,
        actionSchoolId,
        drafts,
        editing,
        i18n.errors.saveFailed,
        saving,
        showInertiaErrors,
    ]);

    const openApplication = useCallback(
        (row: AdmissionAcceptedStudent) =>
            router.visit(followUpShowHref(row.id, row.school_id, query)),
        [query],
    );

    /** Undefined = closed, null = reading the selection. */
    const [viewing, setViewing] = useState<
        FollowUpApplication[] | null | undefined
    >(undefined);
    const openViewing = useCallback((chosen: AdmissionAcceptedStudent[]) => {
        if (chosen.length === 0) {
            return;
        }
        setViewing(null);
        // A partial reload of `viewing` alone: the URL stays, the roster is not read again.
        router.reload({
            only: ['viewing'],
            data: {
                view: chosen
                    .map((row) => `${row.id}:${row.school_id}`)
                    .join(','),
            },
            preserveUrl: true,
            onSuccess: (page) =>
                setViewing(
                    (page.props.viewing as FollowUpApplication[] | undefined) ??
                        [],
                ),
            onError: () => setViewing(undefined),
        });
    }, []);

    const busy = saving || moving;
    const needsSelection = actionIds.length === 0;
    const selectionHint = needsSelection
        ? a.followUpSelectFirst
        : actionSchoolId === null
          ? a.followUpOneSchool
          : undefined;

    const editGroups = useMemo((): PageRibbonGroup[] => {
        const recordCommands: PageRibbonCommand[] = [
            {
                id: 'view-follow-up',
                label: i18n.common.view,
                icon: Eye,
                title:
                    actionRows.length > 0
                        ? `${a.followUpView} (${actionRows.length})`
                        : a.followUpSelectFirst,
                // One or more (tick-all included): the selection opens in the «عرض» window.
                disabled: actionRows.length === 0 || busy,
                onSelect: () => openViewing(actionRows),
            },
        ];
        if (canManage) {
            recordCommands.push(
                {
                    id: 'edit-follow-up',
                    label: i18n.common.edit,
                    icon: Pencil,
                    tone: 'edit',
                    title: selectionHint,
                    // Editing saves inside one school's context.
                    disabled:
                        editing ||
                        needsSelection ||
                        actionSchoolId === null ||
                        busy,
                    onSelect: beginEdit,
                },
                {
                    id: 'save-follow-up',
                    label: saving ? i18n.common.saving : i18n.common.save,
                    icon: Save,
                    tone: 'save',
                    disabled: !editing || busy,
                    onSelect: saveEdits,
                },
            );
        }
        recordCommands.push({
            id: 'cancel-follow-up-selection',
            label: i18n.common.cancel,
            icon: XCircle,
            disabled: (needsSelection && !editing) || busy,
            onSelect: clearSelection,
        });

        const groups: PageRibbonGroup[] = [
            {
                id: 'follow-up-actions',
                label: i18n.common.actions,
                commands: recordCommands,
            },
        ];
        if (canManage) {
            groups.push({
                id: 'follow-up-status',
                label: a.followUpTransitions,
                commands: FOLLOW_UP_TRANSITIONS.map((move) => ({
                    id: `follow-up-status-${move.status}`,
                    label: applicationStatusLabel(move.status),
                    icon: move.icon,
                    tone: move.tone,
                    title: selectionHint ?? applicationStatusLabel(move.status),
                    disabled:
                        needsSelection ||
                        actionSchoolId === null ||
                        editing ||
                        busy,
                    onSelect: () =>
                        actionSchoolId !== null &&
                        requestTransition(
                            actionIds,
                            actionSchoolId,
                            move.status,
                        ),
                })),
            });
        }

        return groups;
    }, [
        a,
        actionIds,
        actionRows,
        actionSchoolId,
        beginEdit,
        busy,
        canManage,
        clearSelection,
        editing,
        i18n.common,
        needsSelection,
        openApplication,
        requestTransition,
        saveEdits,
        saving,
        selectionHint,
    ]);
    useRegisterPageRibbon('edit', editGroups);

    useFollowUpFiltersRibbon({
        query,
        visit,
        options,
        schoolIds,
        periodIds,
        yearId,
        categories,
        disabled: busy,
    });

    useRegisterPageTitlebarSearch(
        useMemo(
            () => ({
                committedQuery: query.q,
                label: a.acceptedStudentsDialogTitle,
                placeholder: i18n.chrome.search,
                onDraftChange: () => undefined,
                onCommit: (next: string) => visit({ q: next.trim() }),
            }),
            [a.acceptedStudentsDialogTitle, i18n.chrome.search, query.q, visit],
        ),
    );

    // Chips: filters chosen away from their defaults, each removable (as in the EXAMIS header bar).
    const chips: Array<{ key: string; label: string; remove: () => void }> = [];
    if (query.school === 'all') {
        chips.push({
            key: 'school-all',
            label: a.allSchools,
            remove: () => visit({ school: '', period: '' }),
        });
    } else if (query.school !== '') {
        // One chip per chosen school; removing the last one goes back to the user's school.
        for (const item of options.schools) {
            if (schoolIds.includes(item.value)) {
                chips.push({
                    key: `school-${item.value}`,
                    label: item.label,
                    remove: () =>
                        visit({
                            school: schoolIds
                                .filter((id) => id !== item.value)
                                .join(','),
                            period: '',
                        }),
                });
            }
        }
    }
    for (const item of options.periods) {
        if (periodIds.includes(item.value)) {
            chips.push({
                key: `period-${item.value}`,
                label: item.label,
                remove: () =>
                    visit({
                        period: periodIds
                            .filter((id) => id !== item.value)
                            .join(','),
                    }),
            });
        }
    }
    if (query.q !== '') {
        chips.push({
            key: 'q',
            label: `«${query.q}»`,
            remove: () => visit({ q: '' }),
        });
    }

    const goPage = (next: number) => {
        if (next >= 1 && next <= lastPage && next !== page) {
            visit({ page: next });
        }
    };
    const title =
        chosenCategories.length === 0
            ? a.followUpAll
            : chosenCategories.map((item) => item.label).join('، ');
    const checkedOnPage = selection.checkedIds.filter((id) =>
        pageIds.includes(id),
    );
    const allChecked =
        pageIds.length > 0 && checkedOnPage.length === pageIds.length;

    return (
        <div className="sis-admission-page-body">
            <h1 className="sr-only">
                {title} ({listed.length})
            </h1>
            {/* Filters chosen away from their defaults, each removable. */}
            {chips.length > 0 ? (
                <div className="sis-follow-up-chips sis-follow-up-roster__chips">
                    {chips.map((chip) => (
                        <button
                            key={chip.key}
                            type="button"
                            className="sis-follow-up-chip"
                            onClick={chip.remove}
                            aria-label={`${a.followUpRemoveFilter}: ${chip.label}`}
                        >
                            <span className="truncate">{chip.label}</span>
                            <X className="size-3.5 shrink-0" aria-hidden />
                        </button>
                    ))}
                </div>
            ) : null}

            <section
                aria-label={title}
                className="flex min-h-0 flex-1 flex-col"
            >
                {listed.length === 0 ? (
                    <div className="sis-follow-up-empty">
                        <Inbox
                            className="sis-follow-up-empty__icon"
                            aria-hidden
                        />
                        <p>{a.followUpEmptyList}</p>
                    </div>
                ) : (
                    <>
                        {/* The table's title band: what is listed and how many (over every page). */}
                        <div
                            className="sis-follow-up-table__caption sis-follow-up-roster__caption"
                            aria-hidden
                        >
                            <span>{title}</span>
                            <CaptionCount count={listed.length} />
                        </div>
                        <div className="sis-admission-periods-table sis-admission-drafts-table sis-follow-up-roster sis-follow-up-roster--captioned">
                            <div
                                className="sis-admission-drafts-table__scroller"
                                ref={scrollerRef}
                            >
                                <table ref={tableRef}>
                                    <caption className="sr-only">
                                        {title}
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th className="sis-admission-drafts-table__select">
                                                <input
                                                    type="checkbox"
                                                    checked={allChecked}
                                                    ref={(input) => {
                                                        if (input) {
                                                            input.indeterminate =
                                                                checkedOnPage.length >
                                                                    0 &&
                                                                !allChecked;
                                                        }
                                                    }}
                                                    disabled={busy}
                                                    aria-label={
                                                        a.followUpSelectAll
                                                    }
                                                    onChange={() =>
                                                        setSelection(
                                                            (current) =>
                                                                toggleTableSelectAll(
                                                                    current.checkedIds,
                                                                    pageIds,
                                                                    current.selectedId,
                                                                ),
                                                        )
                                                    }
                                                />
                                            </th>
                                            <th className="sis-admission-drafts-table__num">
                                                {i18n.students.seq}
                                            </th>
                                            <th
                                                className="sis-admission-drafts-table__name-head"
                                                aria-sort={ariaSort(
                                                    sort,
                                                    'name',
                                                )}
                                            >
                                                <SortButton
                                                    sort={sort}
                                                    column="name"
                                                >
                                                    {i18n.students.quadName}
                                                </SortButton>
                                            </th>
                                            <th
                                                className="sis-follow-up-roster__category"
                                                aria-sort={ariaSort(
                                                    sort,
                                                    'category',
                                                )}
                                            >
                                                <SortButton
                                                    sort={sort}
                                                    column="category"
                                                >
                                                    {a.followUpCategory}
                                                </SortButton>
                                            </th>
                                            <th
                                                aria-sort={ariaSort(
                                                    sort,
                                                    'year',
                                                )}
                                            >
                                                <SortButton
                                                    sort={sort}
                                                    column="year"
                                                >
                                                    {a.academicYear}
                                                </SortButton>
                                            </th>
                                            <th
                                                aria-sort={ariaSort(
                                                    sort,
                                                    'period',
                                                )}
                                            >
                                                <SortButton
                                                    sort={sort}
                                                    column="period"
                                                >
                                                    {a.followUpPeriod}
                                                </SortButton>
                                            </th>
                                            <th
                                                aria-sort={ariaSort(
                                                    sort,
                                                    'school',
                                                )}
                                            >
                                                <SortButton
                                                    sort={sort}
                                                    column="school"
                                                >
                                                    {a.filterBySchool}
                                                </SortButton>
                                            </th>
                                            {showReason ? (
                                                <th
                                                    aria-sort={ariaSort(
                                                        sort,
                                                        'reason',
                                                    )}
                                                >
                                                    <SortButton
                                                        sort={sort}
                                                        column="reason"
                                                    >
                                                        {a.followUpReason}
                                                    </SortButton>
                                                </th>
                                            ) : null}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {pageRows.map((row, index) => {
                                            const rowCategory = categoryOf(
                                                row,
                                                categories,
                                            );
                                            const CategoryIcon =
                                                rowCategory?.icon;
                                            const reasonKey =
                                                rowCategory?.reason;
                                            const draft = editing
                                                ? drafts[row.id]
                                                : undefined;
                                            const reason = reasonKey
                                                ? (row[reasonKey]?.trim() ?? '')
                                                : '';
                                            const checked =
                                                selection.checkedIds.includes(
                                                    row.id,
                                                );
                                            const selected =
                                                checked ||
                                                selection.selectedId === row.id;

                                            return (
                                                <tr
                                                    key={row.id}
                                                    className={
                                                        selected
                                                            ? 'sis-admission-periods-table__row--selected'
                                                            : undefined
                                                    }
                                                    aria-selected={selected}
                                                    onClick={() => {
                                                        if (
                                                            !hasPageTextSelection() &&
                                                            !editing
                                                        ) {
                                                            setSelection(
                                                                selectTableRow(
                                                                    row.id,
                                                                ),
                                                            );
                                                        }
                                                    }}
                                                    onDoubleClick={() =>
                                                        !editing &&
                                                        openApplication(row)
                                                    }
                                                >
                                                    <td className="sis-admission-drafts-table__select">
                                                        <input
                                                            type="checkbox"
                                                            checked={checked}
                                                            disabled={busy}
                                                            aria-label={`${a.followUpSelectApplication}: ${row.full_name}`}
                                                            onClick={(event) =>
                                                                event.stopPropagation()
                                                            }
                                                            onChange={() =>
                                                                setSelection(
                                                                    (current) =>
                                                                        toggleTableRowChecked(
                                                                            current.checkedIds,
                                                                            row.id,
                                                                        ),
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                    <td className="sis-admission-drafts-table__num">
                                                        <span dir="ltr">
                                                            {from + index + 1}
                                                        </span>
                                                    </td>
                                                    <td className="sis-admission-drafts-table__name">
                                                        {draft ? (
                                                            <input
                                                                type="text"
                                                                className="sis-students-table__edit-input"
                                                                value={
                                                                    draft.full_name
                                                                }
                                                                aria-label={
                                                                    i18n
                                                                        .students
                                                                        .quadName
                                                                }
                                                                onClick={(
                                                                    event,
                                                                ) =>
                                                                    event.stopPropagation()
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    patchDraft(
                                                                        row.id,
                                                                        {
                                                                            full_name:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        ) : (
                                                            <CellScroll>
                                                                <Link
                                                                    href={followUpShowHref(
                                                                        row.id,
                                                                        row.school_id,
                                                                        query,
                                                                    )}
                                                                    className="sis-follow-up-roster__link"
                                                                    onClick={(
                                                                        event,
                                                                    ) =>
                                                                        event.stopPropagation()
                                                                    }
                                                                >
                                                                    <Highlight
                                                                        text={
                                                                            row.full_name
                                                                        }
                                                                        query={
                                                                            query.q
                                                                        }
                                                                    />
                                                                </Link>
                                                            </CellScroll>
                                                        )}
                                                    </td>
                                                    <td
                                                        className={`sis-students-table__status sis-follow-up-roster__category${
                                                            rowCategory
                                                                ? ` sis-follow-up-tone--${categoryToken(rowCategory.key)}`
                                                                : ''
                                                        }`}
                                                    >
                                                        {rowCategory &&
                                                        CategoryIcon ? (
                                                            <CellScroll>
                                                                <span className="sis-follow-up-roster__badge">
                                                                    {/* The icon of the category's overview card, in the card's colour. */}
                                                                    <span
                                                                        className="sis-follow-up-roster__icon"
                                                                        aria-hidden
                                                                    >
                                                                        <CategoryIcon
                                                                            strokeWidth={
                                                                                2.25
                                                                            }
                                                                        />
                                                                    </span>
                                                                    {
                                                                        rowCategory.label
                                                                    }
                                                                </span>
                                                            </CellScroll>
                                                        ) : null}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text">
                                                        <CellScroll>
                                                            <span dir="ltr">
                                                                {
                                                                    row.academic_year_name
                                                                }
                                                            </span>
                                                        </CellScroll>
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text">
                                                        <CellScroll>
                                                            {row.period_name}
                                                        </CellScroll>
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text">
                                                        <CellScroll>
                                                            {row.school_name}
                                                        </CellScroll>
                                                    </td>
                                                    {showReason ? (
                                                        <td className="sis-admission-drafts-table__text sis-admission-drafts-table__text--wide">
                                                            {draft &&
                                                            reasonKey ? (
                                                                <input
                                                                    type="text"
                                                                    className="sis-students-table__edit-input"
                                                                    value={
                                                                        draft[
                                                                            reasonKey
                                                                        ]
                                                                    }
                                                                    aria-label={
                                                                        a.followUpReason
                                                                    }
                                                                    onClick={(
                                                                        event,
                                                                    ) =>
                                                                        event.stopPropagation()
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        patchDraft(
                                                                            row.id,
                                                                            {
                                                                                [reasonKey]:
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            ) : (
                                                                <CellScroll>
                                                                    <Highlight
                                                                        text={
                                                                            reason
                                                                        }
                                                                        query={
                                                                            query.q
                                                                        }
                                                                    />
                                                                </CellScroll>
                                                            )}
                                                        </td>
                                                    ) : null}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
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
                                        disabled={page <= 1}
                                        onClick={() => goPage(page - 1)}
                                    >
                                        <span aria-hidden="true">&laquo;</span>
                                    </button>
                                </li>
                                {visiblePages(page, lastPage).map((pageNum) => (
                                    <li
                                        key={pageNum}
                                        className="sis-admission-pagination__item"
                                    >
                                        <button
                                            type="button"
                                            className={
                                                pageNum === page
                                                    ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                                    : 'sis-admission-pagination__link'
                                            }
                                            aria-label={`${i18n.common.page} ${pageNum}`}
                                            aria-current={
                                                pageNum === page
                                                    ? 'page'
                                                    : undefined
                                            }
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
                                        disabled={page >= lastPage}
                                        onClick={() => goPage(page + 1)}
                                    >
                                        <span aria-hidden="true">&raquo;</span>
                                    </button>
                                </li>
                            </ul>
                        </nav>
                    </>
                )}
            </section>

            {dialogs}
            {viewing !== undefined ? (
                <FollowUpViewDialog
                    applications={viewing}
                    canViewPii={canViewPii}
                    showHref={(application) =>
                        followUpShowHref(
                            application.id,
                            application.school_id,
                            query,
                        )
                    }
                    onClose={() => setViewing(undefined)}
                />
            ) : null}
        </div>
    );
}
