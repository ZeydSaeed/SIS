import { Head, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BellRing,
    CalendarClock,
    ClipboardCheck,
    FileCheck2,
    FilterX,
    LayoutDashboard,
    ListChecks,
    Settings2,
    UserCheck,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useRef, type ReactNode } from 'react';
import type { AttendanceAbilities } from '@/components/attendance/attendance-ui';
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
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';

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

/** «تحرير»: the page's own command groups, then the attendance sections. */
export function useAttendanceEditRibbon(
    current: SectionKey,
    abilities: AttendanceAbilities,
    pageGroups: PageRibbonGroup[],
): void {
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

        return [...pageGroups, sections];
    }, [abilities, current, pageGroups]);
    useRegisterPageRibbon('edit', groups);
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

/** «الصفحة الرئيسية»: the page's filters (year first when the page has one), plus a clear button. */
export function useAttendanceHomeFilters(
    specs: Array<
        | FilterSpec
        | {
              key: string;
              date: true;
              label: string;
              value: string;
              onChange: (value: string) => void;
          }
    >,
    onClear: (() => void) | null,
    deps: unknown[],
): void {
    const groups = useMemo((): PageRibbonGroup[] => {
        const a = t().attendanceSystem.chrome;
        if (specs.length === 0) {
            return [];
        }

        return [
            {
                id: 'attendance-filters',
                label: a.filters,
                commands: [],
                custom: (
                    <div
                        className="sis-ribbon__filters"
                        aria-label={a.filters}
                        dir="rtl"
                    >
                        <div className="sis-ribbon__filters-stack sis-ribbon__filters-stack--curriculum">
                            {specs.map((spec) =>
                                'date' in spec ? (
                                    <RibbonDateField
                                        key={spec.key}
                                        label={spec.label}
                                        value={spec.value}
                                        onChange={spec.onChange}
                                    />
                                ) : (
                                    <RibbonFilterField
                                        key={spec.key}
                                        spec={spec}
                                    />
                                ),
                            )}
                        </div>
                        {onClear !== null ? (
                            <button
                                type="button"
                                className="sis-ribbon__item sis-ribbon__item--filter-clear"
                                data-item-id="attendance-filters-clear"
                                aria-label={a.clear}
                                title={a.clear}
                                onClick={onClear}
                            >
                                <FilterX
                                    className="sis-ribbon__icon"
                                    aria-hidden
                                />
                                <span className="sis-ribbon__label">
                                    {a.clear}
                                </span>
                            </button>
                        ) : null}
                    </div>
                ),
            },
        ];
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

/** Page shell — the same layout classes as «الغرف الدراسية» / «المعلمون». */
export function AttendancePage({
    title,
    notices,
    children,
    scroll = false,
}: {
    title: string;
    notices?: ReactNode;
    children: ReactNode;
    scroll?: boolean;
}) {
    return (
        <>
            <Head title={title} />
            <div
                className={`sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page flex h-full min-h-0 flex-col pb-4 ${scroll ? 'overflow-y-auto' : 'overflow-hidden'}`}
                dir="rtl"
                lang="ar"
            >
                {notices}
                <div className="sis-admission-page-body">{children}</div>
            </div>
        </>
    );
}

export function PageNotice({ children }: { children: ReactNode }) {
    return <p className="sis-branches-page__notice">{children}</p>;
}

export type AttendanceColumn<T> = {
    id: string;
    header: ReactNode;
    cell: (row: T, index: number) => ReactNode;
    /** num = narrow centred number · name = the main text column · text = nowrap scrolling text · status = coloured status cell */
    kind?: 'num' | 'name' | 'text' | 'status' | 'plain';
    /** For kind=status: 0..5 tone of the shared status palette. */
    tone?: (row: T) => number;
};

/**
 * The standard SIS table (same markup and classes as the rooms / teachers rosters): optional check-box selection,
 * click to select, double-click / Enter to open, right-click / Shift+F10 for the row's menu, pagination below.
 */
export function AttendanceTable<T>({
    caption,
    rows,
    rowKey,
    columns,
    emptyText,
    selectable,
    checkedIds = [],
    selectedId = null,
    onToggle,
    onToggleAll,
    onSelect,
    onOpen,
    menuItems,
    menuLabel,
    page,
    lastPage,
    onPage,
    numberOffset = 0,
}: {
    caption: string;
    rows: T[];
    rowKey: (row: T) => number;
    columns: AttendanceColumn<T>[];
    emptyText: string;
    selectable?: (row: T) => boolean;
    checkedIds?: number[];
    selectedId?: number | null;
    onToggle?: (id: number) => void;
    onToggleAll?: (ids: number[]) => void;
    onSelect?: (row: T) => void;
    onOpen?: (row: T) => void;
    menuItems?: (row: T) => ContextMenuItem[];
    menuLabel?: string;
    page?: number;
    lastPage?: number;
    onPage?: (page: number) => void;
    numberOffset?: number;
}) {
    const i18n = t();
    const menu = useContextMenu<T>();
    const selectAllRef = useRef<HTMLInputElement | null>(null);
    const selectableIds = selectable ? rows.filter(selectable).map(rowKey) : [];
    const allChecked =
        selectableIds.length > 0 &&
        selectableIds.every((id) => checkedIds.includes(id));
    const someChecked =
        !allChecked && selectableIds.some((id) => checkedIds.includes(id));

    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate = someChecked;
        }
    }, [someChecked]);

    if (rows.length === 0) {
        return <p className="text-sm">{emptyText}</p>;
    }

    const cellClass = (
        kind: AttendanceColumn<T>['kind'],
    ): string | undefined =>
        kind === 'num'
            ? 'sis-admission-drafts-table__num'
            : kind === 'name'
              ? 'sis-admission-drafts-table__name'
              : kind === 'text'
                ? 'sis-admission-drafts-table__text sis-students-table__nowrap'
                : undefined;

    return (
        <section aria-label={caption} className="flex min-h-0 flex-1 flex-col">
            <div className="sis-admission-periods-table sis-admission-drafts-table">
                <div className="sis-admission-drafts-table__scroller">
                    <table>
                        <caption className="sr-only">{caption}</caption>
                        <thead>
                            <tr>
                                {selectable ? (
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
                                                onToggleAll?.(
                                                    allChecked
                                                        ? []
                                                        : selectableIds,
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
                                    >
                                        {c.header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => {
                                const id = rowKey(row);
                                const checked = checkedIds.includes(id);
                                const selected = selectedId === id || checked;

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
                                        onClick={() => onSelect?.(row)}
                                        onDoubleClick={() => onOpen?.(row)}
                                        onContextMenu={(event) => {
                                            if (menuItems) {
                                                onSelect?.(row);
                                                menu.open(event, row);
                                            }
                                        }}
                                        onKeyDown={(event) => {
                                            if (
                                                menuItems &&
                                                isContextMenuKey(event)
                                            ) {
                                                event.preventDefault();
                                                onSelect?.(row);
                                                menu.openAt(
                                                    event.currentTarget,
                                                    row,
                                                );
                                            } else if (event.key === 'Enter') {
                                                onOpen?.(row);
                                            }
                                        }}
                                    >
                                        {selectable ? (
                                            <td className="sis-admission-drafts-table__select">
                                                {selectable(row) ? (
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        aria-label={`${t().attendanceSystem.review.select}: ${id}`}
                                                        onClick={(event) =>
                                                            event.stopPropagation()
                                                        }
                                                        onChange={() =>
                                                            onToggle?.(id)
                                                        }
                                                    />
                                                ) : null}
                                            </td>
                                        ) : null}
                                        <td className="sis-admission-drafts-table__num">
                                            <span dir="ltr">
                                                {numberOffset + index + 1}
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
            {page !== undefined &&
            lastPage !== undefined &&
            lastPage > 1 &&
            onPage ? (
                <Pagination
                    page={page}
                    lastPage={lastPage}
                    onPage={onPage}
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
 * Closing with unsaved input asks first when `dirty` is set.
 */
export function AttendanceWindow({
    title,
    sections,
    confirmLabel,
    onConfirm,
    onClose,
    pending = false,
    confirmDisabled = false,
    dirty = false,
    narrow = true,
    danger = false,
}: {
    title: string;
    sections: Array<{ id: string; title: string; content: ReactNode }>;
    confirmLabel?: string;
    onConfirm?: () => void;
    onClose: () => void;
    pending?: boolean;
    confirmDisabled?: boolean;
    dirty?: boolean;
    narrow?: boolean;
    danger?: boolean;
}) {
    const close = () => {
        if (pending) {
            return;
        }
        if (dirty && !window.confirm(t().attendanceSystem.sheet.leaveConfirm)) {
            return;
        }
        onClose();
    };

    return (
        <RegistrySheetDialog
            title={title}
            className={`sis-branches-sheet ${narrow ? 'sis-narrow-form' : ''}`.trim()}
            onClose={close}
        >
            {sections.map((s) => (
                <SheetSection key={s.id} id={s.id} title={s.title}>
                    <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                        {s.content}
                    </div>
                </SheetSection>
            ))}
            <div className="sis-admission-sheet__actions">
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

/** Read-only fact in a window / facts strip (same look as a read-only registry field). */
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
        clean[key] = value === true ? '1' : String(value);
    }
    router.get(path, clean, { preserveScroll: true, preserveState: true });
}
