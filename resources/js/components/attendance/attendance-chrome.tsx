import { Head, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BellRing,
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    Eye,
    FileCheck2,
    FilterX,
    LayoutDashboard,
    ListChecks,
    Pencil,
    RotateCcw,
    Settings2,
    UserCheck,
    ZoomIn,
    ZoomOut,
    type LucideIcon,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
    useSyncExternalStore,
    type CSSProperties,
    type ReactNode,
} from 'react';
import type { AttendanceAbilities } from '@/components/attendance/attendance-ui';
import {
    AttendanceRail,
    DaySidePanel,
    type Totals,
} from '@/components/attendance/day-board';
import { RegistrySheetDialog } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import {
    SisContextMenu,
    isContextMenuKey,
    useContextMenu,
    type ContextMenuItem,
} from '@/components/sis/context-menu';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { usePageError } from '@/components/sis/page-error-context';
import {
    ariaSort,
    cellSortValue,
    SortButton,
    useTableSort,
    type SortValue,
} from '@/components/sis/table-sort';
import {
    useRegisterPageRibbon,
    type PageRibbonCommand,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import {
    RIBBON_FILTER_TRIGGER,
    RibbonFilterField,
    type FilterSpec,
} from '@/components/sis/ribbon-filter';
import {
    tableActionIds,
    toggleTableRowChecked,
    toggleTableSelectAll,
} from '@/components/sis/table-row-selection';
import { Button } from '@/components/ui/button';
import { useFitTablePageSize } from '@/hooks/use-fit-table-page-size';
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
import { useResizableTableRows } from '@/hooks/use-resizable-table-rows';
import { useSmoothVerticalScroll } from '@/hooks/use-smooth-vertical-scroll';
import { t } from '@/i18n';
import { searchIncludes, searchNeedle } from '@/lib/search-normalize';
import { useDebouncedFitPageSync } from '@/lib/sis-ribbon-layout';

export type SectionKey =
    | 'my'
    | 'review'
    | 'dashboard'
    | 'staff'
    | 'excuses'
    | 'reports'
    | 'alerts'
    | 'settings'
    | 'sessions';
export type IconTone = NonNullable<PageRibbonCommand['iconTone']>;

/** The attendance sections («أقسام الحضور») — one icon, colour and shape per section, on the «تحرير» tab of every page. */
const SECTIONS: Array<{
    key: SectionKey;
    href: string;
    icon: LucideIcon;
    tone: IconTone;
    show: (a: AttendanceAbilities) => boolean;
}> = [
    {
        key: 'my',
        href: '/attendance/my',
        icon: CalendarClock,
        tone: 'education',
        show: () => true,
    },
    {
        key: 'sessions',
        href: '/attendance',
        icon: ListChecks,
        tone: 'growth',
        show: () => true,
    },
    {
        key: 'review',
        href: '/attendance/review',
        icon: ClipboardCheck,
        tone: 'amber',
        show: (a) => a.review_sheet,
    },
    {
        key: 'dashboard',
        href: '/attendance/dashboard',
        icon: LayoutDashboard,
        tone: 'sky',
        show: (a) => a.view_reports,
    },
    {
        key: 'staff',
        href: '/attendance/staff',
        icon: UserCheck,
        tone: 'forest',
        show: () => true,
    },
    {
        key: 'excuses',
        href: '/attendance/excuses',
        icon: FileCheck2,
        tone: 'rose',
        show: (a) => a.submit_excuse || a.review_excuse,
    },
    {
        key: 'reports',
        href: '/attendance/reports',
        icon: BarChart3,
        tone: 'steel',
        show: (a) => a.view_reports,
    },
    {
        key: 'alerts',
        href: '/attendance/alerts',
        icon: BellRing,
        tone: 'warning',
        show: (a) => a.manage_alerts,
    },
    {
        key: 'settings',
        href: '/attendance/settings',
        icon: Settings2,
        tone: 'authority',
        show: () => true,
    },
];

// ── Zoom («العرض»): one level for every attendance table, kept in this browser ──────────────────────────────────────
const ZOOM_KEY = 'sis.attendance.zoom';
const ZOOM_STEPS = [0.8, 0.9, 1, 1.1, 1.25, 1.4, 1.6];
const zoomListeners = new Set<() => void>();
let zoomLevel = (() => {
    try {
        const stored =
            typeof window === 'undefined'
                ? NaN
                : Number(window.localStorage.getItem(ZOOM_KEY));

        return ZOOM_STEPS.includes(stored) ? stored : 1;
    } catch {
        return 1;
    }
})();

function setZoom(next: number): void {
    zoomLevel = next;
    try {
        window.localStorage.setItem(ZOOM_KEY, String(next));
    } catch {
        // private mode — the zoom lasts for this page only
    }
    zoomListeners.forEach((listener) => listener());
}

export function useAttendanceZoom(): number {
    return useSyncExternalStore(
        (listener) => {
            zoomListeners.add(listener);

            return () => zoomListeners.delete(listener);
        },
        () => zoomLevel,
        () => 1,
    );
}

function zoomGroup(zoom: number): PageRibbonGroup {
    const c = t().attendanceSystem.chrome;
    const index = ZOOM_STEPS.indexOf(zoom);

    return {
        id: 'attendance-zoom',
        label: c.zoom,
        commands: [
            {
                id: 'attendance-zoom-in',
                label: c.zoomIn,
                icon: ZoomIn,
                iconTone: 'sky',
                disabled: index >= ZOOM_STEPS.length - 1,
                onSelect: () =>
                    setZoom(
                        ZOOM_STEPS[Math.min(ZOOM_STEPS.length - 1, index + 1)],
                    ),
            },
            {
                id: 'attendance-zoom-out',
                label: c.zoomOut,
                icon: ZoomOut,
                iconTone: 'sky',
                disabled: index <= 0,
                onSelect: () => setZoom(ZOOM_STEPS[Math.max(0, index - 1)]),
            },
            {
                id: 'attendance-zoom-reset',
                label: `${Math.round(zoom * 100)}%`,
                icon: RotateCcw,
                iconTone: 'steel',
                title: c.zoomReset,
                disabled: zoom === 1,
                onSelect: () => setZoom(1),
            },
        ],
    };
}

/**
 * Same order as the other pages: «عرض» / «تحرير» first, then the page's actions — the «المحدد» commands open the
 * page's actions group (one group «الإجراءات»).
 */
function orderedGroups(groups: PageRibbonGroup[]): PageRibbonGroup[] {
    const c = t().attendanceSystem.chrome;
    const selection = groups.find((g) => g.id.endsWith('-selection'));
    if (selection === undefined) {
        return groups;
    }
    const actions = groups.find((g) => g.id.endsWith('-actions'));
    const merged: PageRibbonGroup = {
        ...(actions ?? selection),
        label: c.actions,
        commands: [...(selection.commands ?? []), ...(actions?.commands ?? [])],
    };

    return [merged, ...groups.filter((g) => g !== selection && g !== actions)];
}

/** «تحرير»: the page's own command groups, the zoom of its tables, then the attendance sections. */
export function useAttendanceEditRibbon(
    current: SectionKey,
    abilities: AttendanceAbilities,
    pageGroups: PageRibbonGroup[],
    options: { zoom?: boolean } = {},
): void {
    const zoom = useAttendanceZoom();
    const withZoom = options.zoom !== false;
    const groups = useMemo((): PageRibbonGroup[] => {
        const n = t().attendanceSystem.nav;
        const sections: PageRibbonGroup = {
            id: 'attendance-sections',
            label: n.label,
            commands: SECTIONS.filter((s) => s.show(abilities)).map(
                (s): PageRibbonCommand => ({
                    id: `attendance-section-${s.key}`,
                    label: n[s.key === 'my' ? 'myDay' : s.key],
                    icon: s.icon,
                    iconTone: s.tone,
                    pressed: s.key === current,
                    onSelect: () =>
                        s.key === current ? undefined : router.visit(s.href),
                }),
            ),
        };

        return [
            ...orderedGroups(pageGroups),
            ...(withZoom ? [zoomGroup(zoom)] : []),
            sections,
        ];
    }, [abilities, current, pageGroups, withZoom, zoom]);
    useRegisterPageRibbon('edit', groups);
}

// ── Selection («تحديد» / «تحديد الكل») and the «عرض» / «تحرير» commands of the selected rows ────────────────────────
export type AttendanceSelection<T> = {
    checkedIds: number[];
    selectedId: number | null;
    /** The rows actions apply to: the checked rows, else the clicked row. */
    targets: T[];
    select: (id: number) => void;
    toggle: (id: number) => void;
    toggleAll: (ids: number[]) => void;
    clear: () => void;
};

const SELECTION_KEY = 'sis.attendance.selection:';

function readSelection(key: string): {
    checkedIds: number[];
    selectedId: number | null;
} {
    try {
        const raw =
            typeof window === 'undefined'
                ? null
                : window.sessionStorage.getItem(SELECTION_KEY + key);
        const parsed =
            raw === null
                ? null
                : (JSON.parse(raw) as {
                      checkedIds?: unknown;
                      selectedId?: unknown;
                  });
        const checkedIds = Array.isArray(parsed?.checkedIds)
            ? parsed.checkedIds.filter(
                  (id): id is number => typeof id === 'number',
              )
            : [];

        return {
            checkedIds,
            selectedId:
                typeof parsed?.selectedId === 'number'
                    ? parsed.selectedId
                    : null,
        };
    } catch {
        return { checkedIds: [], selectedId: null };
    }
}

/**
 * The table selection stays as the user left it — through filters, paging, saves and reloads of the page (kept for
 * the tab in sessionStorage, per page) — until the user changes it. Actions apply to the selected rows on screen.
 */
export function useAttendanceSelection<T>(
    rows: T[],
    rowKey: (row: T) => number,
    scope?: string,
): AttendanceSelection<T> {
    const key =
        scope ??
        (typeof window === 'undefined' ? '' : window.location.pathname);
    const [state, setState] = useState(() => readSelection(key));
    const keyRef = useRef(rowKey);
    keyRef.current = rowKey;

    const update = (next: {
        checkedIds: number[];
        selectedId: number | null;
    }) => {
        setState(next);
        try {
            window.sessionStorage.setItem(
                SELECTION_KEY + key,
                JSON.stringify(next),
            );
        } catch {
            // private mode — the selection lasts while the page is open
        }
    };

    const targets = useMemo(() => {
        const wanted = tableActionIds(state.checkedIds, state.selectedId);

        return rows.filter((row) => wanted.includes(keyRef.current(row)));
    }, [rows, state]);

    return {
        checkedIds: state.checkedIds,
        selectedId: state.selectedId,
        targets,
        select: (id) => update({ selectedId: id, checkedIds: [id] }),
        toggle: (id) => update(toggleTableRowChecked(state.checkedIds, id)),
        toggleAll: (visible) =>
            update(
                toggleTableSelectAll(
                    state.checkedIds,
                    visible,
                    state.selectedId,
                ),
            ),
        clear: () => update({ checkedIds: [], selectedId: null }),
    };
}

/**
 * The «المحدد» group: «عرض» and «تحرير» for one or several selected rows (+ the page's own extra commands).
 * `onEdit` null = the table is read-only here (the command stays visible, disabled, with the reason).
 */
export function selectionGroup<T>(
    id: string,
    targets: T[],
    onView: (rows: T[]) => void,
    onEdit: ((rows: T[]) => void) | null,
    options: {
        editLabel?: string;
        editDisabled?: boolean;
        editTitle?: string;
        extra?: PageRibbonCommand[];
    } = {},
): PageRibbonGroup {
    const c = t().attendanceSystem.chrome;
    const none = targets.length === 0;

    return {
        id,
        label: none ? c.selection : `${c.selection} (${targets.length})`,
        commands: [
            {
                id: `${id}-view`,
                label: c.view,
                icon: Eye,
                iconTone: 'sky',
                disabled: none,
                title: none ? c.needsSelection : c.view,
                onSelect: () => !none && onView(targets),
            },
            {
                id: `${id}-edit`,
                label: options.editLabel ?? c.edit,
                icon: Pencil,
                iconTone: 'amber',
                tone: 'edit',
                disabled:
                    none || onEdit === null || options.editDisabled === true,
                title: none
                    ? c.needsSelection
                    : onEdit === null
                      ? (options.editTitle ?? c.readOnly)
                      : (options.editTitle ?? options.editLabel ?? c.edit),
                onSelect: () => !none && onEdit?.(targets),
            },
            ...(options.extra ?? []),
        ],
    };
}

// ── Messages (guidance, warnings, empty results) as a dialog, once per page visit ────────────────────────────────
export type AttendanceMessage = {
    key: string;
    text: string;
    tone?: 'info' | 'warning' | 'error';
};
type ShowMessage = ReturnType<typeof usePageError>['showMessage'];
const shownMessages = new Set<string>();
let pendingMessages: {
    messages: AttendanceMessage[];
    title?: string;
    show: ShowMessage;
} | null = null;

/** Messages raised by the same render (page + its tables) are merged into one dialog. */
function enqueueMessages(
    show: ShowMessage,
    messages: AttendanceMessage[],
    title?: string,
): void {
    if (pendingMessages === null) {
        pendingMessages = { messages: [], title, show };
        queueMicrotask(() => {
            const batch = pendingMessages;
            pendingMessages = null;
            if (batch === null || batch.messages.length === 0) {
                return;
            }
            const all = batch.messages;
            const tone = all.some((m) => m.tone === 'error')
                ? 'error'
                : all.some((m) => m.tone === 'warning')
                  ? 'warning'
                  : 'info';
            batch.show({
                tone,
                title: batch.title ?? t().attendanceSystem.chrome.notice,
                description: all[0].text,
                details: all.slice(1).map((m) => m.text),
            });
        });
    }
    pendingMessages.messages.push(...messages);
    pendingMessages.title ??= title;
}

/**
 * Every guidance / warning / empty-result message of the page opens in the shared message dialog (never as text on
 * the page). A message set is shown once per URL; a flash (success / error) of the same visit takes precedence.
 */
export function useAttendanceMessages(
    messages: AttendanceMessage[],
    title?: string,
): void {
    const page = usePage();
    const { showMessage } = usePageError();
    const flash = (
        page.props as {
            flash?: { success?: string | null; error?: string | null };
        }
    ).flash;
    const signature = messages.map((m) => m.key).join('|');

    useEffect(() => {
        if (messages.length === 0 || flash?.success || flash?.error) {
            return;
        }
        // Paging (incl. the fit-to-height `per_page` sync) is the same visit for the user: never re-shown for it.
        const visit = page.url
            .replace(/([?&])(per_page|page)=[^&]*/g, '$1')
            .replace(/[?&]+$/, '');
        const key = `${visit}#${signature}`;
        if (shownMessages.has(key)) {
            return;
        }
        shownMessages.add(key);
        enqueueMessages(showMessage, messages, title);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page.url, signature]);
}

/** A date field of the filter strip — same trigger look as the list filters. */
export function RibbonDateField({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="sis-ribbon__filter-field" dir="rtl">
            <input
                type="date"
                dir="ltr"
                aria-label={label}
                title={label}
                className={RIBBON_FILTER_TRIGGER}
                value={value}
                onChange={(event) =>
                    event.target.value !== '' && onChange(event.target.value)
                }
            />
        </div>
    );
}

type FilterInput =
    | FilterSpec
    | {
          key: string;
          date: true;
          label: string;
          value: string;
          onChange: (value: string) => void;
      };

/** «الصفحة الرئيسية»: the page's filters in three columns (year first when the page has one), plus a clear button. */
export function useAttendanceHomeFilters(
    specs: FilterInput[],
    onClear: (() => void) | null,
    deps: unknown[],
): void {
    const groups = useMemo((): PageRibbonGroup[] => {
        const a = t().attendanceSystem.chrome;
        if (specs.length === 0) {
            return [];
        }
        // Three columns × two rows per group (the height the ribbon shows in full); more filters open the next group
        // beside it — every group always shown, never folded.
        const chunks: FilterInput[][] = [];
        for (let i = 0; i < specs.length; i += 6) {
            chunks.push(specs.slice(i, i + 6));
        }

        return chunks.map((chunk, index) => ({
            id:
                index === 0
                    ? 'attendance-filters'
                    : `attendance-filters-${index + 1}`,
            label: a.filters,
            commands: [],
            custom: (
                <div
                    className="sis-ribbon__filters"
                    aria-label={a.filters}
                    dir="rtl"
                >
                    <div
                        className="sis-ribbon__filters-stack sis-ribbon__filters-stack--attendance"
                        style={{
                            gridTemplateColumns: `repeat(${Math.min(3, Math.ceil(chunk.length / 2))}, var(--att-filter-w))`,
                        }}
                    >
                        {chunk.map((spec) =>
                            'date' in spec ? (
                                <RibbonDateField
                                    key={spec.key}
                                    label={spec.label}
                                    value={spec.value}
                                    onChange={spec.onChange}
                                />
                            ) : (
                                <RibbonFilterField key={spec.key} spec={spec} />
                            ),
                        )}
                    </div>
                    {onClear !== null && index === chunks.length - 1 ? (
                        <button
                            type="button"
                            className="sis-ribbon__item sis-ribbon__item--filter-clear"
                            data-item-id="attendance-filters-clear"
                            aria-label={a.clear}
                            title={a.clear}
                            onClick={onClear}
                        >
                            <FilterX className="sis-ribbon__icon" aria-hidden />
                            <span className="sis-ribbon__label">{a.clear}</span>
                        </button>
                    ) : null}
                </div>
            ),
        }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);
    useRegisterPageRibbon('home', groups);
}

/** The academic-year single filter (shared prop `academicYears`). */
export function useYearFilterSpec(
    yearId: number | null,
    onChange: (yearId: number) => void,
): FilterSpec {
    const years = ((usePage().props as { academicYears?: YearOption[] })
        .academicYears ?? []) as YearOption[];

    return {
        key: 'year',
        type: 'single',
        label: t().attendanceSystem.chrome.year,
        value: yearId === null ? '' : String(yearId),
        options: years.map((y) => ({
            value: String(y.id),
            label: formatAcademicYearOptionLabel(y.name, y.code),
        })),
        onChange: (value: string) => value !== '' && onChange(Number(value)),
    };
}

/** Title-bar search wired to a server query parameter (or a local filter). */
export function useAttendanceSearch(
    query: string,
    label: string,
    onCommit: (query: string) => void,
    onDraft?: (query: string) => void,
): void {
    const search = useMemo(
        () => ({
            committedQuery: query,
            label,
            placeholder: label,
            onDraftChange: onDraft ?? (() => undefined),
            onCommit,
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [query, label],
    );
    useRegisterPageTitlebarSearch(search);
}

/** Page shell — the same layout classes as «الغرف الدراسية» / «المعلمون»; scrolls with a hidden scrollbar. */
export function AttendancePage({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const shell = usePage<{
        attendanceShell?: AttendanceShell | null;
    }>().props.attendanceShell;

    return (
        <>
            <Head title={title} />
            <div
                className="sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page sis-scroll-hidden flex h-full min-h-0 flex-col pb-4"
                dir="rtl"
                lang="ar"
            >
                <div className="sis-admission-page-body">
                    {shell ? (
                        <div className="sis-att-shell">
                            <aside
                                className="sis-att-board__side"
                                aria-label={t().attendanceSystem.dashboard.board.side}
                            >
                                <DaySidePanel
                                    date={shell.date}
                                    today={shell.today}
                                    weekday={shell.weekday}
                                    now={shell.now}
                                    totals={shell.totals}
                                    onGo={goToDate}
                                />
                            </aside>
                            <div className="sis-att-shell__main">{children}</div>
                            <AttendanceRail
                                abilities={shell.abilities}
                                date={
                                    shell.date === shell.today
                                        ? null
                                        : shell.date
                                }
                            />
                        </div>
                    ) : (
                        children
                    )}
                </div>
            </div>
        </>
    );
}

/** The fixed attendance shell's server payload (one source: AttendanceShellData). */
type AttendanceShell = {
    date: string;
    today: string;
    now: string;
    weekday: number;
    totals: Totals;
    abilities: AttendanceAbilities;
};

/** A day picked in the side calendar: the same page, the same filters, another `date`. */
function goToDate(date: string): void {
    const params: Record<string, string | string[]> = {};
    new URLSearchParams(window.location.search).forEach((_, key) => {
        params[key] = new URLSearchParams(window.location.search).getAll(key);
    });
    // Every page reads the day it needs: the day board / staff list use `date`, the sheet lists and reports a range.
    params.date = date;
    params.date_from = date;
    params.date_to = date;
    router.get(window.location.pathname, params, {
        preserveScroll: true,
        preserveState: true,
    });
}

let measureCanvas: HTMLCanvasElement | null = null;

/** The width a header cell needs to show its text on one line (canvas metrics + the cell padding). */
function headerTextWidth(cell: HTMLElement): number {
    const ctx = (measureCanvas ??= document.createElement('canvas')).getContext(
        '2d',
    );
    const style = getComputedStyle(cell);
    if (ctx === null) {
        return cell.scrollWidth;
    }
    ctx.font = `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;

    return Math.ceil(
        ctx.measureText((cell.textContent ?? '').trim()).width +
            (Number.parseFloat(style.paddingLeft) || 0) +
            (Number.parseFloat(style.paddingRight) || 0) +
            14,
    );
}

export type AttendanceColumn<T> = {
    id: string;
    header: ReactNode;
    cell: (row: T, index: number) => ReactNode;
    /** num = narrow centred number · name = the main text column · text = nowrap scrolling text · status = coloured status cell */
    kind?: 'num' | 'name' | 'text' | 'status' | 'plain';
    /** For kind=status: 0..5 tone of the shared status palette. */
    tone?: (row: T) => number;
    /** For kind=status: the status' ribbon icon tone — the cell takes exactly that colour as its background. */
    iconTone?: (row: T) => string | undefined;
    /** Value for the «عرض» window (defaults to the cell). */
    text?: (row: T) => ReactNode;
    /** Header-click sort value (defaults to the cell's text); `false` = not sortable (default for kind=plain). */
    sort?: ((row: T) => SortValue) | false;
};

/** Server-side paging: the page size follows the visible height and is sent back as `per_page`. */
export type ServerPaging = {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
    onPage: (page: number, perPage: number) => void;
};

/**
 * The standard SIS table (same markup and classes as the rooms / teachers rosters): «تحديد» + «تحديد الكل»,
 * click to select, double-click / Enter to open, right-click / Shift+F10 for the row's menu; columns resized on the
 * header edges, rows on their bottom edges, everything scaled by the ribbon zoom; the page size fits the visible
 * height (server paging when `server` is given, else client paging) with the shared pagination below.
 */
const SELECT_COLUMN_PX = 52;

export function AttendanceTable<T>({
    caption,
    storageKey,
    rows,
    rowKey,
    columns,
    emptyText,
    selection,
    canSelect,
    onOpen,
    menuItems,
    menuLabel,
    server,
    fit = true,
    pageSize = 10,
    emptyAsDialog = false,
}: {
    caption: string;
    /** Saved column widths / row height key. */
    storageKey: string;
    rows: T[];
    rowKey: (row: T) => number;
    columns: AttendanceColumn<T>[];
    emptyText: string;
    selection?: AttendanceSelection<T>;
    /** Rows that may be checked (default: all). */
    canSelect?: (row: T) => boolean;
    onOpen?: (row: T) => void;
    menuItems?: (row: T) => ContextMenuItem[];
    menuLabel?: string;
    server?: ServerPaging;
    /** Fit the page size to the visible height (main table); false in windows. */
    fit?: boolean;
    pageSize?: number;
    emptyAsDialog?: boolean;
}) {
    const i18n = t();
    const zoom = useAttendanceZoom();
    const menu = useContextMenu<T>();
    const cardRef = useRef<HTMLDivElement | null>(null);
    const scrollerRef = useRef<HTMLDivElement | null>(null);
    const tableRef = useRef<HTMLTableElement | null>(null);
    const selectAllRef = useRef<HTMLInputElement | null>(null);
    const [clientPage, setClientPage] = useState(1);
    const sortable = (c: AttendanceColumn<T>): boolean =>
        c.sort !== undefined ? c.sort !== false : c.kind !== 'plain';

    // The titlebar search of the page (a page that registers its own search later wins): filters the rows here,
    // on the text of every column; the placeholder names what is searched.
    const [tableQuery, setTableQuery] = useState('');
    const searchable = fit && server === undefined;
    const searchConfig = useMemo(() => {
        if (!searchable) {
            return null;
        }
        const names = columns
            .map((c) => c.header)
            .filter((h): h is string => typeof h === 'string' && h !== '')
            .slice(0, 5)
            .join('، ');
        const placeholder = i18n.chrome.searchTable
            .replace(':caption', caption)
            .replace(':columns', names);

        return {
            committedQuery: tableQuery,
            label: placeholder,
            placeholder,
            onDraftChange: setTableQuery,
            onCommit: setTableQuery,
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchable, tableQuery, caption, columns.length]);
    useRegisterPageTitlebarSearch(searchConfig);
    const filteredRows = useMemo(() => {
        const q = searchNeedle(tableQuery);
        if (!searchable || q === '') {
            return rows;
        }

        return rows.filter((row) =>
            columns.some((c) => {
                const value = c.text ? c.text(row) : c.cell(row, 0);

                return searchIncludes(String(cellSortValue(value)), q);
            }),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [rows, tableQuery, searchable]);

    const { rows: sortedRows, sort } = useTableSort(filteredRows, (row, key) => {
        const column = columns.find((c) => c.id === key);
        if (!column) {
            return null;
        }

        return typeof column.sort === 'function'
            ? column.sort(row)
            : cellSortValue(
                  column.text ? column.text(row) : column.cell(row, 0),
              );
    });

    const fitSize = useFitTablePageSize(scrollerRef, {
        fallbackRows: server?.perPage ?? pageSize,
        enabled: fit,
        remountKey: rows.length === 0 ? 'empty' : 'rows',
    });
    // In a window (fit=false) the table shows every row and scrolls vertically — no pages, no horizontal scroll.
    const size = fit ? fitSize : Math.max(1, rows.length);
    const syncServer = useCallback(
        (perPage: number) => server?.onPage(server.page, perPage),
        [server],
    );
    useDebouncedFitPageSync(
        fitSize,
        server?.perPage ?? fitSize,
        syncServer,
        fit && server !== undefined && server.total > 0,
    );

    const lastPage = server
        ? Math.max(
              1,
              server.total > 0
                  ? Math.ceil(server.total / size)
                  : server.lastPage,
          )
        : Math.max(1, Math.ceil(filteredRows.length / size));
    const page = server ? server.page : Math.min(clientPage, lastPage);
    const shown = server
        ? sortedRows.slice(0, size)
        : sortedRows.slice((page - 1) * size, page * size);
    const offset = (page - 1) * (server ? server.perPage : size);

    // A window's table is measured again when its rows arrive (the student record loads after the window opens).
    const columnSignature = `v5:${selection ? 'sel' : 'ro'}:${columns.map((c) => c.id).join(',')}${fit ? '' : `:n${rows.length}`}`;
    const kinds = [
        ...(selection ? ['select'] : []),
        'num',
        ...columns.map((c) => c.kind ?? 'text'),
    ];
    /**
     * Initial widths: each column as wide as the longest text it shows (its header or any cell of the page; button
     * columns: their buttons). When the columns do not all fit, they shrink in proportion — never below their header
     * — so every column is on screen; the room left over goes to the main text column(s).
     */
    const measureWidths = useCallback(
        (cells: HTMLTableCellElement[]): number[] => {
            // Layout sizes only (offsetWidth / canvas text) — never the on-screen rectangles, which a window's open
            // animation scales. The table is measured in automatic layout: each column then takes its longest text.
            const table = tableRef.current;
            cells.forEach((cell) => {
                cell.style.width = '';
                cell.style.minWidth = '';
                cell.style.maxWidth = '';
            });
            if (table) {
                table.style.tableLayout = '';
                table.style.minWidth = '';
                table.style.maxWidth = '';
                // The stylesheet stretches tables to 100%: measure the content width instead.
                table.style.setProperty('width', 'max-content', 'important');
            }
            const headers = cells.map((cell, index) =>
                Math.max(
                    headerTextWidth(cell),
                    kinds[index] === 'select' || kinds[index] === 'num'
                        ? 36
                        : 48,
                ),
            );
            // Button columns: their buttons plus the same space on both sides.
            const needed = cells.map((cell, index) =>
                Math.max(
                    headers[index],
                    cell.offsetWidth + (kinds[index] === 'plain' ? 24 : 0),
                ),
            );
            table?.style.removeProperty('width');
            const room =
                (scrollerRef.current?.clientWidth ?? 0) - cells.length - 4;
            const total = needed.reduce((sum, w) => sum + w, 0);
            if (room > 0 && total > room) {
                // Shrink the text columns towards their header width, in proportion to what they can give.
                const give = needed.map((w, i) =>
                    kinds[i] === 'select' ||
                    kinds[i] === 'num' ||
                    kinds[i] === 'plain'
                        ? 0
                        : w - headers[i],
                );
                const spare = give.reduce((sum, g) => sum + g, 0);
                const cut = Math.min(spare, total - room);

                const shrunk = needed.map((w, i) =>
                    spare > 0 ? Math.floor(w - (give[i] * cut) / spare) : w,
                );
                const left = shrunk.reduce((sum, w) => sum + w, 0);
                // In a window: the window grows (up to the screen) so every column keeps at least its header width.
                if (!fit && left > room) {
                    const dialog = scrollerRef.current?.closest<HTMLElement>(
                        '.sis-admission-sheet-dialog',
                    );
                    dialog?.style.setProperty(
                        '--sis-sheet-w',
                        `min(96vw, ${Math.ceil(left + (dialog.offsetWidth - room) + 4)}px)`,
                    );
                }

                return shrunk;
            }
            const grow = kinds
                .map((k, i) => (k === 'name' ? i : -1))
                .filter((i) => i >= 0);
            const targets =
                grow.length > 0
                    ? grow
                    : kinds
                          .map((k, i) => (k === 'text' ? i : -1))
                          .filter((i) => i >= 0);
            const widths = needed.slice();
            if (room > total && targets.length > 0) {
                targets.forEach(
                    (i) =>
                        (widths[i] += Math.floor(
                            (room - total) / targets.length,
                        )),
                );
            }

            return widths;
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [columnSignature],
    );
    const measure = useCallback(
        (cells: HTMLTableCellElement[]): number[] => {
            // Measured with text on one line (the wrapping window cells would report the narrowest possible width).
            const probe = tableRef.current;
            probe?.classList.add('sis-attendance-table--measuring');
            try {
                return measureWidths(cells);
            } finally {
                probe?.classList.remove('sis-attendance-table--measuring');
            }
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [columnSignature],
    );
    // «تحديد»: a wider fixed column (scaled with the zoom like the checkbox itself).
    const selectWidth = Math.round(SELECT_COLUMN_PX * zoom);
    useResizableTableColumns(tableRef, {
        storageKey: `attendance.${storageKey}`,
        columnSignature,
        enabled: shown.length > 0 || !fit,
        measure,
        fixedColumns: selection ? { 0: selectWidth } : undefined,
    });
    useResizableTableRows(tableRef, cardRef, {
        storageKey: `attendance.${storageKey}`,
        zoom,
        enabled: shown.length > 0,
    });
    useSmoothVerticalScroll(scrollerRef, shown.length > 0);

    // After saved / hand-set widths and on every zoom: every column at least as wide as its header text, and the
    // table no wider than its card — what does not fit is taken back from the main text column(s), never below their
    // header. Works on the intended widths (style), not offsetWidth (cell borders would inflate it on every pass).
    useLayoutEffect(() => {
        const table = tableRef.current;
        const cells = Array.from(table?.tHead?.rows[0]?.cells ?? []);
        if (
            table === null ||
            cells.length === 0 ||
            table.style.tableLayout !== 'fixed'
        ) {
            return;
        }
        const kindOf = [
            ...(selection ? ['select'] : []),
            'num',
            ...columns.map((c) => c.kind ?? 'text'),
        ];
        const minOf = cells.map((cell, index) =>
            kindOf[index] === 'select' ? 0 : headerTextWidth(cell),
        );
        const widths = cells.map((cell, index) =>
            Math.max(
                Number.parseFloat(cell.style.width) || cell.offsetWidth,
                minOf[index],
            ),
        );
        const room = (scrollerRef.current?.clientWidth ?? 0) - cells.length - 4;
        let over = widths.reduce((sum, w) => sum + w, 0) - room;
        if (room > 0 && over > 0) {
            kindOf.forEach((kind, index) => {
                if (kind === 'name' && over > 0) {
                    const give = Math.min(over, widths[index] - minOf[index]);
                    widths[index] -= give;
                    over -= give;
                }
            });
        }
        let changed = false;
        cells.forEach((cell, index) => {
            const width = `${Math.round(widths[index])}px`;
            if (cell.style.width !== width) {
                cell.style.width = width;
                cell.style.minWidth = width;
                cell.style.maxWidth = width;
                changed = true;
            }
        });
        const total = `${Math.round(widths.reduce((sum, w) => sum + w, 0))}px`;
        if (changed || table.style.width !== total) {
            table.style.width = total;
            table.style.minWidth = total;
        }
    });

    useEffect(() => setClientPage(1), [rows.length, tableQuery]);

    const selectableIds = selection
        ? shown.filter((row) => canSelect?.(row) ?? true).map(rowKey)
        : [];
    const allChecked =
        selectableIds.length > 0 &&
        selectableIds.every((id) => selection?.checkedIds.includes(id));
    const someChecked =
        !allChecked &&
        selectableIds.some((id) => selection?.checkedIds.includes(id));
    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate = someChecked;
        }
    }, [someChecked]);

    useAttendanceMessages(
        emptyAsDialog && rows.length === 0
            ? [{ key: `empty:${storageKey}`, text: emptyText }]
            : [],
    );

    const cellClass = (
        kind: AttendanceColumn<T>['kind'],
    ): string | undefined =>
        kind === 'num'
            ? 'sis-admission-drafts-table__num'
            : kind === 'name'
              ? 'sis-admission-drafts-table__name'
              : kind === 'text'
                ? 'sis-admission-drafts-table__text sis-students-table__nowrap'
                : kind === 'plain'
                  ? 'sis-attendance-table__plain'
                  : undefined;

    const goTo = (next: number) =>
        server ? server.onPage(next, size) : setClientPage(next);

    return (
        <section aria-label={caption} className="flex min-h-0 flex-1 flex-col">
            <div
                ref={cardRef}
                className={`sis-admission-periods-table sis-admission-drafts-table sis-attendance-table${fit ? '' : ' sis-attendance-table--embedded'}`}
                style={{ '--att-zoom': zoom } as CSSProperties}
            >
                <div
                    className="sis-admission-drafts-table__scroller"
                    ref={scrollerRef}
                >
                    <table ref={tableRef}>
                        <caption className="sr-only">{caption}</caption>
                        <thead>
                            <tr>
                                {selection ? (
                                    <th className="sis-admission-drafts-table__select">
                                        <input
                                            ref={selectAllRef}
                                            type="checkbox"
                                            checked={allChecked}
                                            disabled={
                                                selectableIds.length === 0
                                            }
                                            aria-label={
                                                t().attendanceSystem.review
                                                    .selectAll
                                            }
                                            onChange={() =>
                                                selection.toggleAll(
                                                    selectableIds,
                                                )
                                            }
                                        />
                                    </th>
                                ) : null}
                                <th className="sis-admission-drafts-table__num">
                                    #
                                </th>
                                {columns.map((c) => (
                                    <th
                                        key={c.id}
                                        className={
                                            c.kind === 'num'
                                                ? 'sis-admission-drafts-table__num'
                                                : c.kind === 'name'
                                                  ? 'sis-admission-drafts-table__name-head'
                                                  : undefined
                                        }
                                        aria-sort={
                                            sortable(c)
                                                ? ariaSort(sort, c.id)
                                                : undefined
                                        }
                                    >
                                        {sortable(c) ? (
                                            <SortButton
                                                sort={sort}
                                                column={c.id}
                                            >
                                                {c.header}
                                            </SortButton>
                                        ) : (
                                            c.header
                                        )}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && !emptyAsDialog ? (
                                <tr className="sis-attendance-table__empty">
                                    <td
                                        colSpan={
                                            columns.length + (selection ? 2 : 1)
                                        }
                                    >
                                        {emptyText}
                                    </td>
                                </tr>
                            ) : null}
                            {shown.map((row, index) => {
                                const id = rowKey(row);
                                const checked =
                                    selection?.checkedIds.includes(id) ?? false;
                                const selected =
                                    selection?.selectedId === id || checked;

                                return (
                                    <tr
                                        key={id}
                                        tabIndex={0}
                                        className={
                                            selected
                                                ? 'sis-admission-periods-table__row--selected'
                                                : undefined
                                        }
                                        aria-selected={selected}
                                        onClick={() => selection?.select(id)}
                                        onDoubleClick={() => onOpen?.(row)}
                                        onContextMenu={(event) => {
                                            if (menuItems) {
                                                if (!checked) {
                                                    selection?.select(id);
                                                }
                                                menu.open(event, row);
                                            }
                                        }}
                                        onKeyDown={(event) => {
                                            if (
                                                menuItems &&
                                                isContextMenuKey(event)
                                            ) {
                                                event.preventDefault();
                                                if (!checked) {
                                                    selection?.select(id);
                                                }
                                                menu.openAt(
                                                    event.currentTarget,
                                                    row,
                                                );
                                            } else if (event.key === 'Enter') {
                                                onOpen?.(row);
                                            }
                                        }}
                                    >
                                        {selection ? (
                                            <td className="sis-admission-drafts-table__select">
                                                {(canSelect?.(row) ?? true) ? (
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        aria-label={`${t().attendanceSystem.review.select}: ${id}`}
                                                        onClick={(event) =>
                                                            event.stopPropagation()
                                                        }
                                                        onChange={() =>
                                                            selection.toggle(id)
                                                        }
                                                    />
                                                ) : null}
                                            </td>
                                        ) : null}
                                        <td className="sis-admission-drafts-table__num">
                                            <span dir="ltr">
                                                {offset + index + 1}
                                            </span>
                                        </td>
                                        {columns.map((c) =>
                                            c.kind === 'status' ? (
                                                <td
                                                    key={c.id}
                                                    className={`sis-students-table__status sis-students-table__status--tone-${(c.tone?.(row) ?? 0) === 1 ? 'dark' : 'light'}`}
                                                    data-status={
                                                        c.tone?.(row) ?? 0
                                                    }
                                                    data-icon-tone={c.iconTone?.(
                                                        row,
                                                    )}
                                                >
                                                    <div className="sis-students-table__cell-scroll">
                                                        {c.cell(row, index)}
                                                    </div>
                                                </td>
                                            ) : (
                                                <td
                                                    key={c.id}
                                                    className={cellClass(
                                                        c.kind,
                                                    )}
                                                >
                                                    {c.kind === 'plain' ||
                                                    c.kind === 'num' ? (
                                                        c.cell(row, index)
                                                    ) : (
                                                        <div className="sis-students-table__cell-scroll">
                                                            {c.cell(row, index)}
                                                        </div>
                                                    )}
                                                </td>
                                            ),
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
            {lastPage > 1 ? (
                <Pagination
                    page={page}
                    lastPage={lastPage}
                    onPage={goTo}
                    label={i18n.common.page}
                />
            ) : null}
            {menuItems ? (
                <SisContextMenu
                    controller={menu}
                    items={menuItems}
                    label={menuLabel ?? caption}
                />
            ) : null}
        </section>
    );
}

function visiblePages(current: number, total: number): number[] {
    const start = Math.max(1, Math.min(current - 2, total - 4));
    const end = Math.min(total, start + 4);

    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
}

export function Pagination({
    page,
    lastPage,
    onPage,
    label,
}: {
    page: number;
    lastPage: number;
    onPage: (page: number) => void;
    label: string;
}) {
    const i18n = t();

    return (
        <nav className="sis-admission-drafts-pagination" aria-label={label}>
            <ul className="sis-admission-pagination" dir="ltr">
                <li className="sis-admission-pagination__item">
                    <button
                        type="button"
                        className="sis-admission-pagination__link"
                        aria-label={i18n.common.previous}
                        disabled={page <= 1}
                        onClick={() => onPage(page - 1)}
                    >
                        <span aria-hidden="true">&laquo;</span>
                    </button>
                </li>
                {visiblePages(page, lastPage).map((p) => (
                    <li key={p} className="sis-admission-pagination__item">
                        <button
                            type="button"
                            className={
                                p === page
                                    ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                    : 'sis-admission-pagination__link'
                            }
                            aria-current={p === page ? 'page' : undefined}
                            onClick={() => onPage(p)}
                        >
                            {p}
                        </button>
                    </li>
                ))}
                <li className="sis-admission-pagination__item">
                    <button
                        type="button"
                        className="sis-admission-pagination__link"
                        aria-label={i18n.common.next}
                        disabled={page >= lastPage}
                        onClick={() => onPage(page + 1)}
                    >
                        <span aria-hidden="true">&raquo;</span>
                    </button>
                </li>
            </ul>
        </nav>
    );
}

/**
 * A window (same chrome as the rooms / teachers sheets): sections of fields, then «إلغاء» + the primary action.
 * `columns={2}` lays the fields out in two columns (wide fields span both). Closing with unsaved input asks first.
 */
export function AttendanceWindow({
    title,
    sections,
    confirmLabel,
    onConfirm,
    onClose,
    pending = false,
    confirmDisabled = false,
    columns = 1,
    wide = false,
    danger = false,
    footer,
}: {
    title: string;
    /** `plain`: the content is part of the window itself (a table) — no section banner, no inner card. */
    sections: Array<{
        id: string;
        title: string;
        content: ReactNode;
        plain?: boolean;
    }>;
    confirmLabel?: string;
    onConfirm?: () => void;
    onClose: () => void;
    pending?: boolean;
    confirmDisabled?: boolean;
    /** Unsaved input (informative; closing never asks). */
    dirty?: boolean;
    columns?: 1 | 2;
    /** Room for a table (62rem). */
    wide?: boolean;
    danger?: boolean;
    /** Extra buttons before «إلغاء» (e.g. previous / next of a multi-row «عرض»). */
    footer?: ReactNode;
}) {
    const close = () => {
        if (pending) {
            return;
        }
        // No «unsaved changes» question: closing a window is the user's explicit choice.
        onClose();
    };

    return (
        <RegistrySheetDialog
            title={title}
            className={[
                'sis-branches-sheet',
                columns === 2 ? 'sis-attendance-two' : '',
                wide ? 'sis-attendance-wide' : '',
                columns === 1 && !wide ? 'sis-narrow-form' : '',
            ]
                .filter(Boolean)
                .join(' ')}
            onClose={close}
        >
            {sections.map((s) =>
                s.plain ? (
                    <div
                        key={s.id}
                        className="sis-attendance-window__plain"
                        role="group"
                        aria-label={s.title}
                    >
                        {s.content}
                    </div>
                ) : (
                    <SheetSection key={s.id} id={s.id} title={s.title}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            {s.content}
                        </div>
                    </SheetSection>
                ),
            )}
            <div className="sis-admission-sheet__actions">
                {footer}
                <Button
                    type="button"
                    variant="outline"
                    disabled={pending}
                    onClick={close}
                >
                    {t().dialog.cancel}
                </Button>
                {confirmLabel && onConfirm ? (
                    <Button
                        type="button"
                        variant={danger ? 'destructive' : 'default'}
                        disabled={pending || confirmDisabled}
                        onClick={onConfirm}
                    >
                        {pending ? t().dialog.working : confirmLabel}
                    </Button>
                ) : null}
            </div>
        </RegistrySheetDialog>
    );
}

/** A multi-line field with the registry field look. */
export function WindowTextArea({
    label,
    value,
    onChange,
    required = false,
    editing = true,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    required?: boolean;
    editing?: boolean;
}) {
    return (
        <label className="sis-admission-sheet__field sis-branches-field--wide">
            <span className="sis-admission-sheet__label">
                {label}
                {required ? <span aria-hidden="true"> *</span> : null}
            </span>
            {editing ? (
                <textarea
                    className={`sis-admission-sheet__control min-h-20 ${value.trim() !== '' ? 'sis-admission-draft-field--filled' : ''}`}
                    value={value}
                    required={required}
                    maxLength={2000}
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : (
                <div className="sis-admission-sheet__control sis-admission-draft-readonly">
                    {value.trim() === '' ? '—' : value}
                </div>
            )}
        </label>
    );
}

/** Read-only fact in a window (same look as a read-only registry field). */
export function WindowFact({
    label,
    value,
    wide = false,
}: {
    label: string;
    value: ReactNode;
    wide?: boolean;
}) {
    return (
        <div
            className={`sis-admission-sheet__field ${wide ? 'sis-branches-field--wide' : ''}`.trim()}
        >
            <span className="sis-admission-sheet__label">{label}</span>
            <div className="sis-admission-sheet__control sis-admission-draft-readonly">
                {value}
            </div>
        </div>
    );
}

/** A time field (hh:mm) with the registry field look. */
export function WindowTimeField({
    label,
    value,
    onChange,
    editing = true,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    editing?: boolean;
}) {
    return (
        <label className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            <input
                className={`sis-admission-sheet__control ${value !== '' ? 'sis-admission-draft-field--filled' : ''}`}
                type="time"
                dir="ltr"
                disabled={!editing}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </label>
    );
}

/**
 * «عرض»: every column of the selected row(s) in a two-column window; several rows are stepped through with
 * «السابق» / «التالي». `extra` adds page-specific facts / actions for the row shown.
 */
export function useRowViewer<T>(
    columns: AttendanceColumn<T>[],
    titleOf: (row: T) => string,
    extra?: (row: T) => ReactNode,
) {
    const [state, setState] = useState<{ rows: T[]; index: number } | null>(
        null,
    );
    const c = t().attendanceSystem.chrome;
    const row = state === null ? null : (state.rows[state.index] ?? null);

    const element =
        state !== null && row !== null ? (
            <AttendanceWindow
                title={
                    state.rows.length > 1
                        ? `${titleOf(row)} (${state.index + 1} / ${state.rows.length})`
                        : titleOf(row)
                }
                columns={2}
                onClose={() => setState(null)}
                footer={
                    state.rows.length > 1 ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={state.index === 0}
                                onClick={() =>
                                    setState({
                                        ...state,
                                        index: state.index - 1,
                                    })
                                }
                            >
                                <ChevronRight aria-hidden />
                                {c.previous}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={state.index >= state.rows.length - 1}
                                onClick={() =>
                                    setState({
                                        ...state,
                                        index: state.index + 1,
                                    })
                                }
                            >
                                {c.next}
                                <ChevronLeft aria-hidden />
                            </Button>
                        </>
                    ) : null
                }
                sections={[
                    {
                        id: 'att-row-view',
                        title: c.details,
                        content: (
                            <>
                                {columns.map((col) => (
                                    <WindowFact
                                        key={col.id}
                                        label={
                                            typeof col.header === 'string'
                                                ? col.header
                                                : col.id
                                        }
                                        value={(
                                            col.text ??
                                            ((r: T) => col.cell(r, state.index))
                                        )(row)}
                                    />
                                ))}
                                {extra?.(row)}
                            </>
                        ),
                    },
                ]}
            />
        ) : null;

    return {
        open: (rows: T[]) => rows.length > 0 && setState({ rows, index: 0 }),
        close: () => setState(null),
        element,
    };
}

/**
 * One server action for each selected row, one after the other (each with its own idempotency key); stops at the
 * first refusal (its error opens in the dialog). `onDone` runs after the last success.
 */
export function postEach<T>(
    items: T[],
    request: (item: T) => {
        url: string;
        data: Record<string, unknown>;
        key: string;
    },
    callbacks: {
        onError: (errors: Record<string, string>) => void;
        onDone: () => void;
        onFinish?: () => void;
    },
): void {
    const step = (index: number) => {
        if (index >= items.length) {
            callbacks.onDone();
            callbacks.onFinish?.();

            return;
        }
        const { url, data, key } = request(items[index]);
        router.post(url, data as never, {
            preserveScroll: true,
            preserveState: true,
            headers: { 'X-Idempotency-Key': key },
            onSuccess: (page) => {
                // A domain refusal comes back as a redirect with flash.error (shown by the page's message dialog).
                if (
                    (page.props as { flash?: { error?: string | null } }).flash
                        ?.error
                ) {
                    callbacks.onFinish?.();

                    return;
                }
                step(index + 1);
            },
            onError: (errors) => {
                callbacks.onError(errors);
                callbacks.onFinish?.();
            },
            onNetworkError: () => {
                callbacks.onFinish?.();

                return true;
            },
        });
    };
    step(0);
}

/** Visit the current page with merged query parameters (server filters / pagination). */
export function visitWith(path: string, params: Record<string, unknown>): void {
    const clean: Record<string, string | string[]> = {};
    for (const [key, value] of Object.entries(params)) {
        if (
            value === null ||
            value === undefined ||
            value === '' ||
            value === false
        ) {
            continue;
        }
        if (Array.isArray(value)) {
            clean[key] = value.map(String);
            continue;
        }
        clean[key] =
            value === true
                ? '1'
                : typeof value === 'object'
                  ? JSON.stringify(value)
                  : String(value as string | number);
    }
    router.get(path, clean, { preserveScroll: true, preserveState: true });
}
