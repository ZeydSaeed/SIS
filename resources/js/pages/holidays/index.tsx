import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleSlash,
    Eye,
    Layers,
    Pencil,
    PlusCircle,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    RegistryListField,
    RegistrySheetDialog,
    RegistryTextField,
    useRegistryRequest,
} from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import {
    isContextMenuKey,
    SisContextMenu,
    useContextMenu,
    type ContextMenuItem,
} from '@/components/sis/context-menu';
import {
    useRegisterPageRibbon,
    type PageRibbonCommand,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import {
    RibbonFilterField,
    type FilterSpec,
} from '@/components/sis/ribbon-filter';
import {
    ariaSort,
    SortButton,
    useTableSort,
} from '@/components/sis/table-sort';
import {
    tableActionIds,
    toggleTableRowChecked,
    toggleTableSelectAll,
} from '@/components/sis/table-row-selection';
import { Button } from '@/components/ui/button';
import { useFitTablePageSize } from '@/hooks/use-fit-table-page-size';
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
import { useSmoothVerticalScroll } from '@/hooks/use-smooth-vertical-scroll';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import {
    clipRowsToFitPageSize,
    fitAwareLastPage,
    useDebouncedFitPageSync,
} from '@/lib/sis-ribbon-layout';
import type { BreadcrumbItem } from '@/types';

type Holiday = {
    id: number;
    academic_year_id: number;
    name: string;
    start_date: string;
    end_date: string;
    days: number;
    holiday_type: number;
    global: boolean;
    status: number;
};

type Year = {
    id: number;
    name: string;
    code: string;
    start_date: string;
    end_date: string;
    is_current: boolean;
};

type Filters = {
    year: number | null;
    search: string;
    state: number | null;
    type: number | null;
};

type Props = {
    holidays: Holiday[];
    years: Year[];
    stats: { total: number; active: number; days: number };
    pagination: {
        page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
    filters: Filters;
    authorization: { can_manage: boolean };
};

type HolidayForm = {
    academic_year_id: string;
    name: string;
    start_date: string;
    end_date: string;
    holiday_type: string;
};

const ACTIVE = 1;
const RELOAD = ['holidays', 'stats', 'pagination', 'flash'];
const TYPES = [1, 2, 3] as const;

function formOf(holiday: Holiday | null, yearId: number | null): HolidayForm {
    return {
        academic_year_id: String(holiday?.academic_year_id ?? yearId ?? ''),
        name: holiday?.name ?? '',
        start_date: holiday?.start_date ?? '',
        end_date: holiday?.end_date ?? '',
        holiday_type: String(holiday?.holiday_type ?? 1),
    };
}

/** The pages to show around the current one (1 … n with a window). */
function visiblePages(current: number, last: number): number[] {
    const start = Math.max(1, Math.min(current - 2, last - 4));

    return Array.from(
        { length: Math.min(5, last) },
        (_, index) => start + index,
    ).filter((page) => page <= last);
}

export default function Holidays(props: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().holidays.title, href: '/holidays' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <HolidaysPage {...props} />
        </AppLayout>
    );
}

function HolidaysPage({
    holidays,
    years,
    stats,
    pagination,
    filters,
    authorization,
}: Props) {
    const i18n = t();
    const h = i18n.holidays;
    const canManage = authorization.can_manage;
    const request = useRegistryRequest(RELOAD);
    const menu = useContextMenu<Holiday>();
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [checkedIds, setCheckedIds] = useState<number[]>([]);
    const selectAllRef = useRef<HTMLInputElement>(null);
    const [sheet, setSheet] = useState<{
        id: number | null;
        viewOnly: boolean;
        form: HolidayForm;
        queue: number[];
    } | null>(null);
    const [confirmOut, setConfirmOut] = useState<Holiday[] | null>(null);
    const [saving, setSaving] = useState(false);

    // One holiday = the action target; several checked = a batch (take out / put back only).
    const actionIds = useMemo(
        () => tableActionIds(checkedIds, selectedId),
        [checkedIds, selectedId],
    );
    const targets = useMemo(
        () => holidays.filter((holiday) => actionIds.includes(holiday.id)),
        [actionIds, holidays],
    );
    const selected = targets.length === 1 ? targets[0] : null;
    // Memoised: it is a dependency of the registered ribbon (a new array every render would re-register it forever).
    const editableTargets = useMemo(
        () => targets.filter((holiday) => !holiday.global),
        [targets],
    );
    const tableRef = useRef<HTMLTableElement>(null);
    const scrollerRef = useRef<HTMLDivElement>(null);

    // Same list mechanics as «الغرف»: rows fit the visible height, resizable columns, sortable headers.
    const fitPageSize = useFitTablePageSize(scrollerRef, {
        fallbackRows: pagination.per_page,
        enabled: true,
    });
    const fitPageSizeRef = useRef(fitPageSize);
    fitPageSizeRef.current = fitPageSize;
    const shownHolidays = useMemo(
        () => clipRowsToFitPageSize(holidays, fitPageSize),
        [fitPageSize, holidays],
    );
    const fitLastPage = fitAwareLastPage(
        pagination.total,
        fitPageSize,
        pagination.last_page,
    );
    const { rows: displayHolidays, sort } = useTableSort(
        shownHolidays,
        (holiday, key) => {
            switch (key) {
                case 'name':
                    return holiday.name;
                case 'start':
                    return holiday.start_date;
                case 'end':
                    return holiday.end_date;
                case 'days':
                    return holiday.days;
                case 'type':
                    return h.types[holiday.holiday_type] ?? '';
                case 'scope':
                    return holiday.global ? h.scopeGlobal : h.scopeSchool;
                case 'status':
                    return holiday.status;
                default:
                    return null;
            }
        },
    );
    useResizableTableColumns(tableRef, {
        storageKey: 'academic.holidays',
        columnSignature: `holidays:${canManage ? 'select' : 'readonly'}:v1`,
        // Measured whenever the table is shown (also empty): every header fits on one line.
        enabled: filters.year !== null,
    });
    useSmoothVerticalScroll(scrollerRef, displayHolidays.length > 0);

    const rowIds = useMemo(
        () => displayHolidays.map((holiday) => holiday.id),
        [displayHolidays],
    );
    const pageChecked = rowIds.filter((id) => checkedIds.includes(id));
    const allChecked =
        rowIds.length > 0 && pageChecked.length === rowIds.length;
    const someChecked = pageChecked.length > 0 && !allChecked;
    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate = someChecked;
        }
    }, [someChecked]);
    // Drop selections of holidays that left the list after a reload / filter.
    useEffect(() => {
        setCheckedIds((current) =>
            current.filter((id) =>
                holidays.some((holiday) => holiday.id === id),
            ),
        );
        setSelectedId((current) =>
            current !== null &&
            holidays.some((holiday) => holiday.id === current)
                ? current
                : null,
        );
    }, [holidays]);
    const selectRow = (id: number) => {
        setSelectedId(id);
        setCheckedIds([id]);
    };
    const toggleChecked = (id: number) => {
        const next = toggleTableRowChecked(checkedIds, id);
        setCheckedIds(next.checkedIds);
        setSelectedId(next.selectedId);
    };
    const toggleAll = () => {
        const next = toggleTableSelectAll(checkedIds, rowIds, selectedId);
        setCheckedIds(next.checkedIds);
        setSelectedId(next.selectedId);
    };

    /** Server-side list: every filter / page change is a visit (no client filtering of the calendar). */
    const visit = (
        patch: Partial<Filters> & { page?: number; per_page?: number },
    ) => {
        const next = { ...filters, ...patch };
        router.get(
            '/holidays',
            {
                year: next.year ?? undefined,
                search: next.search || undefined,
                state: next.state ?? undefined,
                type: next.type ?? undefined,
                page: patch.page ?? 1,
                per_page: patch.per_page ?? fitPageSizeRef.current,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['holidays', 'pagination', 'filters', 'stats'],
            },
        );
    };
    const syncFitPageSize = useCallback(
        (perPage: number) =>
            visit({ per_page: perPage, page: pagination.page }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [filters, pagination.page],
    );
    useDebouncedFitPageSync(fitPageSize, pagination.per_page, syncFitPageSize);

    const run = async (action: () => Promise<boolean>, after?: () => void) => {
        if (saving) {
            return;
        }
        setSaving(true);
        try {
            if (await action()) {
                after?.();
            }
        } finally {
            setSaving(false);
        }
    };

    /** `queue` = the holidays a multi-selection «عرض» steps through (previous / next in the window). */
    const openHoliday = (
        holiday: Holiday | null,
        viewOnly: boolean,
        queue: number[] = [],
    ) =>
        setSheet({
            id: holiday?.id ?? null,
            viewOnly: viewOnly || holiday?.global === true,
            form: formOf(holiday, filters.year),
            queue,
        });
    /** Status for one or several holidays, in order — stops at the first failure (its error is already shown). */
    const setStatusMany = (
        items: Holiday[],
        active: boolean,
        after?: () => void,
    ) =>
        run(async () => {
            for (const holiday of items.filter((item) => !item.global)) {
                if (
                    !(await request('post', `/holidays/${holiday.id}/status`, {
                        active: active ? 1 : 0,
                    }))
                ) {
                    return false;
                }
            }

            return true;
        }, after);

    const saveHoliday = () => {
        if (sheet === null) {
            return;
        }
        const f = sheet.form;
        const payload = {
            name: f.name.trim(),
            start_date: f.start_date,
            end_date: f.end_date,
            holiday_type: Number(f.holiday_type),
        };
        void run(
            () =>
                sheet.id === null
                    ? request('post', '/holidays', {
                          ...payload,
                          academic_year_id: Number(f.academic_year_id),
                      })
                    : request('patch', `/holidays/${sheet.id}`, payload),
            () => setSheet(null),
        );
    };

    const menuItems = (holiday: Holiday): ContextMenuItem[] => [
        {
            id: 'view',
            label: h.view,
            icon: Eye,
            onSelect: () => openHoliday(holiday, true),
        },
        ...(canManage && !holiday.global
            ? [
                  {
                      id: 'edit',
                      label: h.edit,
                      icon: Pencil,
                      onSelect: () => openHoliday(holiday, false),
                  },
                  holiday.status === ACTIVE
                      ? {
                            id: 'out',
                            label: h.remove,
                            icon: Trash2,
                            danger: true,
                            separator: true,
                            onSelect: () => setConfirmOut([holiday]),
                        }
                      : {
                            id: 'in',
                            label: h.restore,
                            icon: RotateCcw,
                            separator: true,
                            onSelect: () => void setStatusMany([holiday], true),
                        },
              ]
            : []),
    ];

    // ── Ribbon «تحرير» ─────────────────────────────────────────────────────────────
    const ribbon = useMemo((): PageRibbonGroup[] => {
        const none = targets.length === 0;
        const notOne = selected === null;
        const nothingToChange = editableTargets.length === 0;
        const allOut =
            editableTargets.length > 0 &&
            editableTargets.every((holiday) => holiday.status !== ACTIVE);

        return [
            {
                id: 'holidays-actions',
                label: h.actions,
                commands: [
                    {
                        id: 'holidays-view',
                        label: i18n.common.view,
                        icon: Eye,
                        iconTone: 'sky',
                        disabled: none,
                        title: none ? i18n.orgRibbon.needsSelection : h.view,
                        onSelect: () =>
                            targets.length > 0 &&
                            openHoliday(
                                targets[0],
                                true,
                                targets.length > 1
                                    ? targets.map((holiday) => holiday.id)
                                    : [],
                            ),
                    },
                    ...(canManage
                        ? [
                              {
                                  id: 'holidays-add',
                                  label: h.add,
                                  icon: PlusCircle,
                                  iconTone: 'ok' as const,
                                  disabled: filters.year === null,
                                  onSelect: () => openHoliday(null, false),
                              },
                              {
                                  id: 'holidays-edit',
                                  label: i18n.common.edit,
                                  icon: Pencil,
                                  iconTone: 'amber' as const,
                                  tone: 'edit' as const,
                                  disabled:
                                      notOne ||
                                      selected?.global === true ||
                                      saving,
                                  onSelect: () =>
                                      selected && openHoliday(selected, false),
                              },
                              allOut
                                  ? {
                                        id: 'holidays-in',
                                        label: h.restore,
                                        icon: RotateCcw,
                                        iconTone: 'ok' as const,
                                        disabled: saving,
                                        onSelect: () =>
                                            void setStatusMany(
                                                editableTargets,
                                                true,
                                            ),
                                    }
                                  : {
                                        id: 'holidays-out',
                                        label: h.remove,
                                        icon: Trash2,
                                        iconTone: 'danger' as const,
                                        tone: 'delete' as const,
                                        disabled: nothingToChange || saving,
                                        onSelect: () =>
                                            setConfirmOut(editableTargets),
                                    },
                          ]
                        : []),
                ],
            },
            {
                id: 'holidays-state',
                label: h.filters,
                commands: [
                    {
                        key: null,
                        label: h.all,
                        icon: Layers,
                        iconTone: 'steel' as const,
                        count: stats.total,
                        title: `${h.daysTotal}: ${stats.days}`,
                    },
                    {
                        key: 1,
                        label: h.active,
                        icon: CheckCircle2,
                        iconTone: 'ok' as const,
                        count: stats.active,
                    },
                    {
                        key: 2,
                        label: h.inactive,
                        icon: CircleSlash,
                        iconTone: 'warning' as const,
                        count: stats.total - stats.active,
                    },
                ].map((tab): PageRibbonCommand => ({
                    id: `holidays-state-${tab.key ?? 'all'}`,
                    label: tab.label,
                    icon: tab.icon,
                    iconTone: tab.iconTone,
                    count: tab.count,
                    title: 'title' in tab ? tab.title : undefined,
                    pressed: filters.state === tab.key,
                    onSelect: () => visit({ state: tab.key }),
                })),
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        i18n,
        targets,
        selected,
        editableTargets,
        canManage,
        saving,
        filters,
        stats,
    ]);
    useRegisterPageRibbon('edit', ribbon);

    // The filters live on the home tab (the edit tab keeps the actions).
    const homeRibbon = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'holidays-filters',
                label: h.year,
                commands: [],
                custom: (
                    <div className="sis-ribbon__filters" dir="rtl">
                        <div className="sis-ribbon__filters-stack sis-ribbon__filters-stack--curriculum">
                            {[
                                {
                                    key: 'year',
                                    type: 'single',
                                    label: h.year,
                                    value:
                                        filters.year === null
                                            ? ''
                                            : String(filters.year),
                                    options: years.map((year) => ({
                                        value: String(year.id),
                                        label: year.name,
                                    })),
                                    onChange: (value: string) =>
                                        visit({
                                            year:
                                                value === ''
                                                    ? null
                                                    : Number(value),
                                        }),
                                },
                                {
                                    key: 'type',
                                    type: 'single',
                                    label: h.type,
                                    value:
                                        filters.type === null
                                            ? ''
                                            : String(filters.type),
                                    options: [
                                        { value: '', label: h.allTypes },
                                        ...TYPES.map((type) => ({
                                            value: String(type),
                                            label:
                                                h.types[type] ?? String(type),
                                        })),
                                    ],
                                    onChange: (value: string) =>
                                        visit({
                                            type:
                                                value === ''
                                                    ? null
                                                    : Number(value),
                                        }),
                                },
                            ].map((spec) => (
                                <RibbonFilterField
                                    key={spec.key}
                                    spec={spec as FilterSpec}
                                />
                            ))}
                        </div>
                    </div>
                ),
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [i18n, filters, years],
    );
    useRegisterPageRibbon('home', homeRibbon);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: filters.search,
            label: h.search,
            placeholder: h.search,
            onDraftChange: () => undefined,
            onCommit: (query: string) => visit({ search: query.trim() }),
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [filters, h.search],
    );
    useRegisterPageTitlebarSearch(titlebarSearch);

    const offset = (pagination.page - 1) * pagination.per_page;
    const columns: Array<{ key: string; label: string; num?: boolean }> = [
        { key: 'name', label: h.name },
        { key: 'start', label: h.startDate },
        { key: 'end', label: h.endDate },
        { key: 'days', label: h.days, num: true },
        { key: 'type', label: h.type },
        { key: 'scope', label: h.scope },
        { key: 'status', label: h.status },
    ];

    return (
        <>
            <Head title={h.title} />
            <div
                className="sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page flex h-full min-h-0 flex-col overflow-hidden pb-4"
                dir="rtl"
                lang="ar"
            >
                {canManage ? null : (
                    <p className="sis-branches-page__notice">{h.readOnly}</p>
                )}
                <div className="sis-admission-page-body">
                    <section
                        aria-label={h.tableCaption}
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        {filters.year === null ? (
                            <p className="text-sm">{h.noYear}</p>
                        ) : (
                            <>
                                <div className="sis-admission-periods-table sis-admission-drafts-table">
                                    <div
                                        className="sis-admission-drafts-table__scroller"
                                        ref={scrollerRef}
                                    >
                                        <table ref={tableRef}>
                                            <caption className="sr-only">
                                                {h.tableCaption}
                                            </caption>
                                            <thead>
                                                <tr>
                                                    {canManage ? (
                                                        <th className="sis-admission-drafts-table__select">
                                                            <input
                                                                ref={
                                                                    selectAllRef
                                                                }
                                                                type="checkbox"
                                                                checked={
                                                                    allChecked
                                                                }
                                                                disabled={
                                                                    saving
                                                                }
                                                                aria-label={
                                                                    h.selectAll
                                                                }
                                                                onChange={
                                                                    toggleAll
                                                                }
                                                            />
                                                        </th>
                                                    ) : null}
                                                    <th className="sis-admission-drafts-table__num">
                                                        #
                                                    </th>
                                                    {columns.map((column) => (
                                                        <th
                                                            key={column.key}
                                                            className={
                                                                column.num
                                                                    ? 'sis-admission-drafts-table__num'
                                                                    : undefined
                                                            }
                                                            aria-sort={ariaSort(
                                                                sort,
                                                                column.key,
                                                            )}
                                                        >
                                                            <SortButton
                                                                sort={sort}
                                                                column={
                                                                    column.key
                                                                }
                                                            >
                                                                {column.label}
                                                            </SortButton>
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {displayHolidays.length === 0 ? (
                                                    <tr className="sis-attendance-table__empty">
                                                        <td
                                                            colSpan={
                                                                columns.length +
                                                                (canManage
                                                                    ? 2
                                                                    : 1)
                                                            }
                                                        >
                                                            {stats.total === 0
                                                                ? h.empty
                                                                : h.noResult}
                                                            {canManage &&
                                                            stats.total ===
                                                                0 ? (
                                                                <>
                                                                    {' '}
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            openHoliday(
                                                                                null,
                                                                                false,
                                                                            )
                                                                        }
                                                                    >
                                                                        <PlusCircle
                                                                            aria-hidden
                                                                        />
                                                                        {h.add}
                                                                    </Button>
                                                                </>
                                                            ) : null}
                                                        </td>
                                                    </tr>
                                                ) : null}
                                                {displayHolidays.map(
                                                    (holiday, index) => {
                                                        const checked =
                                                            checkedIds.includes(
                                                                holiday.id,
                                                            );
                                                        const rowSelected =
                                                            selectedId ===
                                                                holiday.id ||
                                                            checked;

                                                        return (
                                                            <tr
                                                                key={holiday.id}
                                                                tabIndex={0}
                                                                className={
                                                                    rowSelected
                                                                        ? 'sis-admission-periods-table__row--selected'
                                                                        : undefined
                                                                }
                                                                aria-selected={
                                                                    rowSelected
                                                                }
                                                                onClick={() =>
                                                                    selectRow(
                                                                        holiday.id,
                                                                    )
                                                                }
                                                                onDoubleClick={() =>
                                                                    openHoliday(
                                                                        holiday,
                                                                        !canManage,
                                                                    )
                                                                }
                                                                onContextMenu={(
                                                                    event,
                                                                ) => {
                                                                    if (
                                                                        !checked
                                                                    ) {
                                                                        selectRow(
                                                                            holiday.id,
                                                                        );
                                                                    }
                                                                    menu.open(
                                                                        event,
                                                                        holiday,
                                                                    );
                                                                }}
                                                                onKeyDown={(
                                                                    event,
                                                                ) => {
                                                                    if (
                                                                        isContextMenuKey(
                                                                            event,
                                                                        )
                                                                    ) {
                                                                        event.preventDefault();
                                                                        if (
                                                                            !checked
                                                                        ) {
                                                                            selectRow(
                                                                                holiday.id,
                                                                            );
                                                                        }
                                                                        menu.openAt(
                                                                            event.currentTarget,
                                                                            holiday,
                                                                        );
                                                                    } else if (
                                                                        event.key ===
                                                                        'Enter'
                                                                    ) {
                                                                        openHoliday(
                                                                            holiday,
                                                                            !canManage,
                                                                        );
                                                                    }
                                                                }}
                                                            >
                                                                {canManage ? (
                                                                    <td className="sis-admission-drafts-table__select">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={
                                                                                checked
                                                                            }
                                                                            disabled={
                                                                                saving
                                                                            }
                                                                            aria-label={`${h.selectHoliday}: ${holiday.name}`}
                                                                            onClick={(
                                                                                event,
                                                                            ) =>
                                                                                event.stopPropagation()
                                                                            }
                                                                            onChange={() =>
                                                                                toggleChecked(
                                                                                    holiday.id,
                                                                                )
                                                                            }
                                                                        />
                                                                    </td>
                                                                ) : null}
                                                                <td className="sis-admission-drafts-table__num">
                                                                    <span dir="ltr">
                                                                        {offset +
                                                                            index +
                                                                            1}
                                                                    </span>
                                                                </td>
                                                                <td className="sis-admission-drafts-table__name">
                                                                    <div className="sis-students-table__cell-scroll">
                                                                        {
                                                                            holiday.name
                                                                        }
                                                                    </div>
                                                                </td>
                                                                <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                                    <div
                                                                        className="sis-students-table__cell-scroll"
                                                                        dir="ltr"
                                                                    >
                                                                        {
                                                                            holiday.start_date
                                                                        }
                                                                    </div>
                                                                </td>
                                                                <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                                    <div
                                                                        className="sis-students-table__cell-scroll"
                                                                        dir="ltr"
                                                                    >
                                                                        {
                                                                            holiday.end_date
                                                                        }
                                                                    </div>
                                                                </td>
                                                                <td className="sis-admission-drafts-table__num">
                                                                    {
                                                                        holiday.days
                                                                    }
                                                                </td>
                                                                <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                                    <div className="sis-students-table__cell-scroll">
                                                                        {h
                                                                            .types[
                                                                            holiday
                                                                                .holiday_type
                                                                        ] ??
                                                                            '—'}
                                                                    </div>
                                                                </td>
                                                                <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                                    <div className="sis-students-table__cell-scroll">
                                                                        {holiday.global
                                                                            ? h.scopeGlobal
                                                                            : h.scopeSchool}
                                                                    </div>
                                                                </td>
                                                                <td
                                                                    className={`sis-students-table__status sis-students-table__status--tone-${holiday.status === ACTIVE ? 'dark' : 'light'}`}
                                                                    data-status={
                                                                        holiday.status
                                                                    }
                                                                >
                                                                    <div className="sis-students-table__cell-scroll">
                                                                        {holiday.status ===
                                                                        ACTIVE
                                                                            ? h.inCalendar
                                                                            : h.outOfCalendar}
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        );
                                                    },
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                {pagination.total > 0 ? (
                                    <nav
                                        className="sis-admission-drafts-pagination"
                                        aria-label={i18n.common.page}
                                    >
                                        <ul
                                            className="sis-admission-pagination"
                                            dir="ltr"
                                        >
                                            <li className="sis-admission-pagination__item">
                                                <button
                                                    type="button"
                                                    className="sis-admission-pagination__link"
                                                    aria-label={
                                                        i18n.common.previous
                                                    }
                                                    disabled={
                                                        pagination.page <= 1
                                                    }
                                                    onClick={() =>
                                                        visit({
                                                            page:
                                                                pagination.page -
                                                                1,
                                                        })
                                                    }
                                                >
                                                    <span aria-hidden="true">
                                                        &laquo;
                                                    </span>
                                                </button>
                                            </li>
                                            {visiblePages(
                                                pagination.page,
                                                fitLastPage,
                                            ).map((pageNum) => (
                                                <li
                                                    key={pageNum}
                                                    className="sis-admission-pagination__item"
                                                >
                                                    <button
                                                        type="button"
                                                        className={
                                                            pageNum ===
                                                            pagination.page
                                                                ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                                                : 'sis-admission-pagination__link'
                                                        }
                                                        aria-label={`${i18n.common.page} ${pageNum}`}
                                                        aria-current={
                                                            pageNum ===
                                                            pagination.page
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                        onClick={() =>
                                                            visit({
                                                                page: pageNum,
                                                            })
                                                        }
                                                    >
                                                        {pageNum}
                                                    </button>
                                                </li>
                                            ))}
                                            <li className="sis-admission-pagination__item">
                                                <button
                                                    type="button"
                                                    className="sis-admission-pagination__link"
                                                    aria-label={
                                                        i18n.common.next
                                                    }
                                                    disabled={
                                                        pagination.page >=
                                                        fitLastPage
                                                    }
                                                    onClick={() =>
                                                        visit({
                                                            page:
                                                                pagination.page +
                                                                1,
                                                        })
                                                    }
                                                >
                                                    <span aria-hidden="true">
                                                        &raquo;
                                                    </span>
                                                </button>
                                            </li>
                                        </ul>
                                    </nav>
                                ) : null}
                            </>
                        )}
                    </section>
                </div>
            </div>

            <SisContextMenu
                controller={menu}
                items={menuItems}
                label={h.menu}
            />

            {sheet !== null ? (
                <HolidaySheet
                    sheet={sheet}
                    years={years}
                    saving={saving}
                    canManage={canManage}
                    holiday={
                        holidays.find((holiday) => holiday.id === sheet.id) ??
                        null
                    }
                    onChange={(form) =>
                        setSheet((current) =>
                            current === null ? current : { ...current, form },
                        )
                    }
                    onEdit={() =>
                        setSheet((current) =>
                            current === null
                                ? current
                                : { ...current, viewOnly: false },
                        )
                    }
                    onStep={(id) => {
                        const next = holidays.find(
                            (holiday) => holiday.id === id,
                        );
                        if (next !== undefined) {
                            openHoliday(next, true, sheet.queue);
                        }
                    }}
                    onSave={saveHoliday}
                    onClose={() => setSheet(null)}
                />
            ) : null}

            <ConfirmDialog
                open={confirmOut !== null}
                title={h.remove}
                description={h.removeConfirm}
                confirmLabel={h.remove}
                tone="danger"
                confirmPending={saving}
                onConfirm={() =>
                    confirmOut !== null &&
                    void setStatusMany(confirmOut, false, () =>
                        setConfirmOut(null),
                    )
                }
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setConfirmOut(null);
                    }
                }}
            />
        </>
    );
}

/** «بيانات الإجازة»: add / view / edit one holiday (the academic year is fixed after creation). */
function HolidaySheet({
    sheet,
    years,
    saving,
    canManage,
    holiday,
    onChange,
    onEdit,
    onStep,
    onSave,
    onClose,
}: {
    sheet: {
        id: number | null;
        viewOnly: boolean;
        form: HolidayForm;
        queue: number[];
    };
    years: Year[];
    saving: boolean;
    canManage: boolean;
    holiday: Holiday | null;
    onChange: (form: HolidayForm) => void;
    onEdit: () => void;
    onStep: (id: number) => void;
    onSave: () => void;
    onClose: () => void;
}) {
    const i18n = t();
    const h = i18n.holidays;
    const queueIndex = sheet.id === null ? -1 : sheet.queue.indexOf(sheet.id);
    const stepping =
        sheet.viewOnly && sheet.queue.length > 1 && queueIndex >= 0;
    const editing = !sheet.viewOnly;
    const f = sheet.form;
    const set =
        <K extends keyof HolidayForm>(key: K) =>
        (value: HolidayForm[K]) =>
            onChange({ ...f, [key]: value });
    const year =
        years.find((item) => String(item.id) === f.academic_year_id) ?? null;
    const typeOptions = TYPES.map((type) => ({
        value: String(type),
        label: h.types[type] ?? String(type),
    }));
    const valid =
        f.name.trim() !== '' &&
        f.academic_year_id !== '' &&
        f.start_date !== '' &&
        f.end_date !== '' &&
        f.end_date >= f.start_date;
    const canEdit = canManage && holiday?.global !== true;

    return (
        <RegistrySheetDialog
            title={sheet.id === null ? h.add : sheet.viewOnly ? h.view : h.edit}
            className="sis-branches-sheet sis-timetable-sheet sis-rooms-form sis-rooms-sheet sis-rooms-three"
            onClose={onClose}
        >
            <SheetSection id="holiday-basic" title={h.sectionBasic}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                    <RegistryListField
                        label={h.year}
                        editing={editing && sheet.id === null}
                        required
                        value={f.academic_year_id}
                        display={year?.name ?? '—'}
                        options={years.map((item) => ({
                            value: String(item.id),
                            label: item.name,
                        }))}
                        onChange={set('academic_year_id')}
                        fieldClassName="sis-branches-field--wide"
                    />
                    <RegistryTextField
                        label={h.name}
                        editing={editing}
                        required
                        value={f.name}
                        onChange={set('name')}
                        fieldClassName="sis-branches-field--wide"
                    />
                    <RegistryTextField
                        label={h.startDate}
                        editing={editing}
                        required
                        type="date"
                        dir="ltr"
                        value={f.start_date}
                        onChange={set('start_date')}
                    />
                    <RegistryTextField
                        label={h.endDate}
                        editing={editing}
                        required
                        type="date"
                        dir="ltr"
                        value={f.end_date}
                        onChange={set('end_date')}
                    />
                    <RegistryListField
                        label={h.type}
                        editing={editing}
                        required
                        value={f.holiday_type}
                        display={
                            typeOptions.find((o) => o.value === f.holiday_type)
                                ?.label ?? '—'
                        }
                        options={typeOptions}
                        onChange={set('holiday_type')}
                    />
                    {year !== null ? (
                        <p className="sis-timetable-sheet__hint sis-branches-field--wide">
                            {h.year}: <bdi dir="ltr">{year.start_date}</bdi> —{' '}
                            <bdi dir="ltr">{year.end_date}</bdi>
                        </p>
                    ) : null}
                    {holiday?.global === true ? (
                        <p className="sis-timetable-sheet__hint sis-branches-field--wide">
                            {h.globalReadOnly}
                        </p>
                    ) : null}
                </div>
            </SheetSection>
            <div className="sis-admission-sheet__actions">
                <Button
                    type="button"
                    variant="outline"
                    disabled={saving}
                    onClick={onClose}
                >
                    {sheet.viewOnly ? h.close : h.cancel}
                </Button>
                {stepping ? (
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={queueIndex <= 0}
                            onClick={() => onStep(sheet.queue[queueIndex - 1])}
                        >
                            {i18n.common.previous}
                        </Button>
                        <span dir="ltr" aria-live="polite">
                            {queueIndex + 1} / {sheet.queue.length}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={queueIndex >= sheet.queue.length - 1}
                            onClick={() => onStep(sheet.queue[queueIndex + 1])}
                        >
                            {i18n.common.next}
                        </Button>
                    </>
                ) : null}
                {sheet.viewOnly ? (
                    canEdit ? (
                        <Button type="button" onClick={onEdit}>
                            <Pencil aria-hidden />
                            {i18n.common.edit}
                        </Button>
                    ) : null
                ) : (
                    <Button
                        type="button"
                        disabled={saving || !valid}
                        onClick={onSave}
                    >
                        {saving ? i18n.common.saving : h.save}
                    </Button>
                )}
            </div>
        </RegistrySheetDialog>
    );
}
