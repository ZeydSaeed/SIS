import { Head, router, usePage } from "@inertiajs/react";
import {
    AlertTriangle,
    Briefcase,
    Building2,
    CircleX,
    Eye,
    EyeOff,
    Factory,
    FilterX,
    GraduationCap,
    Layers,
    LayoutGrid,
    Lock,
    LockOpen,
    Megaphone,
    Rows3,
    Sprout,
    SquareCheck,
    type LucideIcon,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from "@/components/sis/ops-year-filter";
import { usePageError } from "@/components/sis/page-error-context";
import { sisListPageClass } from "@/components/sis/page-theme";
import {
    useRegisterPageRibbon,
    type PageRibbonCommand,
    type PageRibbonGroup,
} from "@/components/sis/page-ribbon-context";
import { useRegisterPageTitlebarHome } from "@/components/sis/page-titlebar-home-context";
import { RibbonFilterField } from "@/components/sis/ribbon-filter";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogTitle } from "@/components/ui/dialog";
import AppLogo from "@/components/app-logo";
import { SheetSection } from "@/components/sis/admission-sheet";
import { WindowControls } from "@/components/window-controls";
import { useSheetMaximize } from "@/hooks/use-sheet-maximize";
import { useDragScroll } from "@/hooks/use-drag-scroll";
import { useSmoothDialogDrag } from "@/hooks/use-smooth-dialog-drag";
import AppLayout from "@/layouts/app-layout";
import { t } from "@/i18n";
import type { BreadcrumbItem } from "@/types";

type Structure = {
    branches: Array<{ id: number; code: string; name: string }>;
    departments: Array<{
        id: number;
        branch_id: number | null;
        code: string;
        name: string;
    }>;
    classes: Array<{
        id: number;
        code: string;
        name: string;
        grade_level_id: number;
    }>;
    sections: Array<{
        id: number;
        class_id: number;
        code: string;
        name: string;
    }>;
};

type Props = {
    structure: Structure;
    filters: { academic_year_id: number | null };
};

/** «تصفية الصفوف والشعب»: ids of the chosen branches, departments and classes; section letters (A / B / C). */
type PlaceFilters = {
    branch: string[];
    department: string[];
    class: string[];
    section: string[];
};

const NO_FILTERS: PlaceFilters = {
    branch: [],
    department: [],
    class: [],
    section: [],
};

/**
 * One card of «الصفوف والشعب»: a specialization's class in a branch, with its sections (A, B, … in order).
 * `label`: الفرع — الاختصاص — الصف (the confirmation); the card shows the specialization, under its class column.
 */
type PlaceGroup = {
    key: string;
    branch: string;
    classId: number;
    className: string;
    department: string;
    label: string;
    sections: Array<{ key: string; name: string; label: string }>;
};

const pass = (chosen: string[], value: string): boolean =>
    chosen.length === 0 || chosen.includes(value);

/** الفرع · الاختصاص · الصف, each with its sections — narrowed by the filters (none chosen = all). */
function placeGroups(
    structure: Structure,
    filters: PlaceFilters,
    labels: { noDepartment: string; section: string },
): PlaceGroup[] {
    const groups: PlaceGroup[] = [];
    for (const branch of structure.branches.filter((row) =>
        pass(filters.branch, String(row.id)),
    )) {
        const own = structure.departments.filter(
            (row) => row.branch_id === branch.id,
        );
        const departments: Array<{ id: number; name: string } | null> =
            own.length > 0 ? own : [null];
        for (const department of departments.filter((row) =>
            pass(filters.department, String(row?.id ?? "")),
        )) {
            // الأول, الثاني, … in grade order; sections A, B, … alphabetically (owner's request, 2026-11-28).
            for (const klass of structure.classes
                .filter((row) => pass(filters.class, String(row.id)))
                .sort(
                    (a, b) =>
                        a.grade_level_id - b.grade_level_id || a.id - b.id,
                )) {
                const sections = structure.sections
                    .filter(
                        (row) =>
                            row.class_id === klass.id &&
                            pass(filters.section, row.name),
                    )
                    .sort((a, b) => a.name.localeCompare(b.name, "en"));
                if (sections.length === 0) {
                    continue;
                }
                const base = `${branch.id}|${department?.id ?? 0}|${klass.id}`;
                groups.push({
                    key: base,
                    branch: branch.name,
                    classId: klass.id,
                    className: klass.name,
                    department: department?.name ?? labels.noDepartment,
                    label: [
                        branch.name,
                        department?.name ?? labels.noDepartment,
                        klass.name,
                    ].join(" — "),
                    sections: sections.map((row) => ({
                        key: `${base}|${row.id}`,
                        name: row.name,
                        label: `${labels.section} ${row.name}`,
                    })),
                });
            }
        }
    }

    return groups;
}

/** A checkbox that can show «some» (a class with part of its sections ticked). */
function Tick({
    checked,
    some = false,
    disabled = false,
    onChange,
    label,
    strong = false,
}: {
    checked: boolean;
    some?: boolean;
    disabled?: boolean;
    onChange: (checked: boolean) => void;
    label: string;
    strong?: boolean;
}) {
    const ref = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (ref.current) {
            ref.current.indeterminate = some && !checked;
        }
    }, [some, checked]);

    return (
        <label
            className={`flex items-center gap-2 text-sm ${strong ? "font-semibold" : ""} ${disabled ? "opacity-60" : ""}`}
        >
            <input
                ref={ref}
                type="checkbox"
                checked={checked}
                disabled={disabled}
                onChange={(event) => onChange(event.target.checked)}
                className="size-4 cursor-pointer"
            />
            {label}
        </label>
    );
}

/** «النواقص» in the system's table theme (place, subject, teacher, what is missing) — empty: no grades are read. */
function MissingTable() {
    const w = t().results.entryWindow;

    return (
        <div className="sis-admission-periods-table sis-admission-drafts-table">
            <div className="sis-admission-drafts-table__scroller sis-scroll-x-thin">
                <table>
                    <caption className="sr-only">{w.showMissing}</caption>
                    <thead>
                        <tr>
                            <th className="sis-admission-drafts-table__name-head">
                                {w.placeColumn}
                            </th>
                            <th className="sis-admission-drafts-table__name-head">
                                {w.subjectColumn}
                            </th>
                            <th className="sis-admission-drafts-table__name-head">
                                {w.teacherColumn}
                            </th>
                            <th className="sis-admission-drafts-table__name-head">
                                {w.missingColumn}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td
                                className="sis-admission-drafts-table__text"
                                colSpan={4}
                            >
                                {w.noData}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/**
 * «إدخال الدرجات / إعلان النتائج للطلبة» (owner's request, 2026-11-28): the cyber_security «/admin?tab=window» page in
 * the system's own layout — opening mark entry to the teaching staff, then announcing the results by class and section
 * («الصفوف والشعب» of the school: الفرع · الاختصاص · الصف · الشعبة, narrowed on «الصفحة الرئيسية»). The commands are on
 * «تحرير». Opening entry and publishing are not saved yet (owner's choice): they live in this page and reset on reload.
 */
export default function ResultsEntryWindow(props: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().modules.results, href: "/results" },
        { title: t().results.entryWindow.title, href: "/results/entry-window" },
    ];

    // Inner component: the ribbon («الصفحة الرئيسية» filters, «تحرير» commands) and the title bar live inside AppLayout.
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <EntryWindowContent {...props} />
        </AppLayout>
    );
}

/** The fixed hues of the main branches (owner's request, 2026-11-28): التجاري · الزراعي · الصناعي. */
const BRANCH_HUES: Array<{ match: string; hue: number }> = [
    { match: "تجار", hue: 212 },
    { match: "زراع", hue: 128 },
    { match: "صناع", hue: 28 },
];

/**
 * A card's light colour by its branch: one colour per branch, the same on every card of it — التجاري, الزراعي and
 * الصناعي have their own; every other branch its own golden-angle hue (by its place in the school's branches).
 */
function branchTint(
    name: string,
    index: number,
): { background: string; borderColor: string } {
    const fixed = BRANCH_HUES.find((item) => name.includes(item.match));
    const hue = fixed?.hue ?? Math.round((index * 137.508 + 300) % 360);

    return {
        background: `hsl(${hue} 70% 95%)`,
        borderColor: `hsl(${hue} 50% 76%)`,
    };
}

type Tint = { background: string; borderColor: string };

type PlaceState = {
    chosen: Set<string>;
    published: Set<string>;
    entryOpen: boolean;
    toggle: (keys: string[], on: boolean) => void;
    unpublish: (key: string) => void;
};

/** The keys of the given cards' sections that can still be ticked (not published). */
const freeKeys = (cards: PlaceGroup[], published: Set<string>): string[] =>
    cards
        .flatMap((group) => group.sections.map((section) => section.key))
        .filter((key) => !published.has(key));

/** A specialization's card in a class column: its tick, then its sections (A, B, …) one under the other. */
function PlaceCard({
    group,
    tint,
    chosen,
    published,
    entryOpen,
    toggle,
    unpublish,
}: PlaceState & { group: PlaceGroup; tint: Tint }) {
    const w = t().results.entryWindow;
    const keys = freeKeys([group], published);

    return (
        <div className="flex flex-col gap-2 rounded-md border p-3" style={tint}>
            <Tick
                checked={
                    keys.length > 0 && keys.every((key) => chosen.has(key))
                }
                some={keys.some((key) => chosen.has(key))}
                disabled={entryOpen || keys.length === 0}
                onChange={(on) => toggle(keys, on)}
                label={group.department}
                strong
            />
            <div className="flex flex-col gap-2 ps-6">
                {group.sections.map((section) => {
                    const out = published.has(section.key);

                    return (
                        <div
                            key={section.key}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <Tick
                                checked={out || chosen.has(section.key)}
                                disabled={out || entryOpen}
                                onChange={(on) => toggle([section.key], on)}
                                label={section.label}
                            />
                            {out ? (
                                <>
                                    <Badge>{w.sectionPublished}</Badge>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-6 px-2 text-xs"
                                        onClick={() => unpublish(section.key)}
                                    >
                                        {w.hide}
                                    </Button>
                                </>
                            ) : null}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/**
 * One branch's block (owner's request, 2026-11-28): its title with a tick for all of it, then one column per class —
 * الأول, الثاني, … side by side — each holding its specializations' cards, sections in alphabetical order.
 */
function BranchBlock({
    branch,
    cards,
    tint,
    ...state
}: PlaceState & { branch: string; cards: PlaceGroup[]; tint: Tint }) {
    const w = t().results.entryWindow;
    const { chosen, published, entryOpen, toggle } = state;
    const blockKeys = freeKeys(cards, published);
    const columns: Array<{
        classId: number;
        className: string;
        cards: PlaceGroup[];
    }> = [];
    for (const group of cards) {
        const column = columns.find((item) => item.classId === group.classId);
        if (column) {
            column.cards.push(group);
        } else {
            columns.push({
                classId: group.classId,
                className: group.className,
                cards: [group],
            });
        }
    }

    return (
        <section
            className="flex flex-col gap-3 rounded-md border-2 bg-white p-3"
            style={{ borderColor: tint.borderColor }}
            aria-label={branch}
        >
            <div
                className="rounded-sm px-2 py-1"
                style={{ background: tint.background }}
            >
                <Tick
                    checked={
                        blockKeys.length > 0 &&
                        blockKeys.every((key) => chosen.has(key))
                    }
                    some={blockKeys.some((key) => chosen.has(key))}
                    disabled={entryOpen || blockKeys.length === 0}
                    onChange={(on) => toggle(blockKeys, on)}
                    label={`${w.branch}: ${branch}`}
                    strong
                />
            </div>
            <div className="sis-scroll-x-thin overflow-x-auto">
                <div
                    className="grid gap-3"
                    style={{
                        gridTemplateColumns: `repeat(${columns.length}, minmax(13rem, 1fr))`,
                    }}
                >
                    {columns.map((column) => {
                        const columnKeys = freeKeys(column.cards, published);

                        return (
                            <div
                                key={column.classId}
                                className="flex flex-col gap-3"
                                role="group"
                                aria-label={column.className}
                            >
                                <div
                                    className="border-b pb-1"
                                    style={{ borderColor: tint.borderColor }}
                                >
                                    <Tick
                                        checked={
                                            columnKeys.length > 0 &&
                                            columnKeys.every((key) =>
                                                chosen.has(key),
                                            )
                                        }
                                        some={columnKeys.some((key) =>
                                            chosen.has(key),
                                        )}
                                        disabled={
                                            entryOpen || columnKeys.length === 0
                                        }
                                        onChange={(on) =>
                                            toggle(columnKeys, on)
                                        }
                                        label={`${w.classLabel}: ${column.className}`}
                                        strong
                                    />
                                </div>
                                {column.cards.map((group) => (
                                    <PlaceCard
                                        key={group.key}
                                        group={group}
                                        tint={tint}
                                        {...state}
                                    />
                                ))}
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}

/** «حالة» on «تحرير»: which sections the cards show. */
type StatusView = "all" | "published" | "unpublished" | "chosen";

/** «توزيعات»: a branch's icon by its name (الصناعي · الزراعي · التجاري), a building for the others. */
function branchIcon(name: string): LucideIcon {
    if (name.includes("صناع")) {
        return Factory;
    }
    if (name.includes("زراع")) {
        return Sprout;
    }

    return name.includes("تجار") ? Briefcase : Building2;
}

/** One chosen section in «نشر النتائج؟». */
type ChosenRow = {
    key: string;
    branch: string;
    department: string;
    className: string;
    section: string;
};

/**
 * «نشر النتائج؟» in the system's window (the sheets' chrome: dark hero, drag, maximise, close). Not modal: no overlay,
 * the page behind stays usable (owner's request, 2026-11-28). The chosen sections are a table, then «النواقص».
 */
function PublishSheet({
    rows,
    onClose,
    onPublish,
}: {
    rows: ChosenRow[];
    onClose: () => void;
    onPublish: () => void;
}) {
    const i18n = t();
    const w = i18n.results.entryWindow;
    const { contentRef, heroDragProps, bringToFront, resizeHandles } =
        useSmoothDialogDrag(true, {
            resizable: true,
            minSize: { width: 560, height: 280 },
        });
    const { maximized, toggleMaximize, maximizeClassName } =
        useSheetMaximize(contentRef);
    // Up and down by dragging the content with the mouse (it glides on and slows down) or the wheel (eased).
    const bodyRef = useDragScroll<HTMLDivElement>(true);

    return (
        <Dialog
            open
            modal={false}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                ref={contentRef}
                // The «معلومات التسجيل» window's own classes (owner's request): same chrome, colours and order.
                className={`sis-admission-draft-dialog sis-admission-sheet-dialog sis-student-sheet-dialog sis-enrollment-record-sheet sis-results-publish-dialog${maximizeClassName}`}
                overlayClassName="sis-admission-sheet-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownCapture={bringToFront}
            >
                <DialogTitle className="sr-only">{w.confirmTitle}</DialogTitle>
                {maximized ? null : resizeHandles}
                <div className="sis-admission-draft-form sis-admission-sheet sis-student-record-form sis-results-publish-sheet">
                    <header
                        className="sis-admission-sheet__hero"
                        {...(maximized ? {} : heroDragProps)}
                    >
                        <WindowControls
                            className="sis-admission-sheet__window-controls"
                            label={i18n.window.controls}
                            minimizeLabel={i18n.window.minimize}
                            maximizeLabel={i18n.window.maximize}
                            restoreLabel={i18n.window.restore}
                            closeLabel={i18n.window.close}
                            minimizable={false}
                            maximizable
                            maximized={maximized}
                            onMaximize={toggleMaximize}
                            onClose={onClose}
                        />
                        <div className="sis-admission-sheet__hero-copy">
                            <p className="sis-admission-sheet__hero-title">
                                {w.confirmTitle}
                            </p>
                        </div>
                        <div className="sis-admission-sheet__hero-logo">
                            <AppLogo
                                tone="on-dark"
                                className="sis-admission-sheet__logo"
                            />
                        </div>
                    </header>

                    <div
                        ref={bodyRef}
                        className="sis-results-publish-sheet__body"
                    >
                        <SheetSection
                            id="results-publish-chosen"
                            title={`${w.chosenTitle} (${rows.length})`}
                        >
                            <div className="sis-admission-periods-table sis-admission-drafts-table">
                                <div className="sis-admission-drafts-table__scroller">
                                    <table>
                                        <caption className="sr-only">
                                            {w.chosenTitle}
                                        </caption>
                                        <thead>
                                            <tr>
                                                <th className="sis-admission-drafts-table__name-head">
                                                    {w.branch}
                                                </th>
                                                <th className="sis-admission-drafts-table__name-head">
                                                    {w.department}
                                                </th>
                                                <th className="sis-admission-drafts-table__name-head">
                                                    {w.classLabel}
                                                </th>
                                                <th className="sis-admission-drafts-table__name-head">
                                                    {w.sectionLabel}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((row) => (
                                                <tr key={row.key}>
                                                    <td className="sis-admission-drafts-table__text">
                                                        {row.branch}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text">
                                                        {row.department}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text">
                                                        {row.className}
                                                    </td>
                                                    <td
                                                        className="sis-admission-drafts-table__text"
                                                        dir="ltr"
                                                    >
                                                        {row.section}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </SheetSection>
                        <SheetSection
                            id="results-publish-missing"
                            title={w.showMissing}
                        >
                            <MissingTable />
                        </SheetSection>
                    </div>

                    {/* As «معلومات التسجيل»: «إلغاء» first, then the window's own action. */}
                    <div className="sis-admission-sheet__actions">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {w.cancel}
                        </Button>
                        <Button type="button" onClick={onPublish}>
                            {w.publish}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function EntryWindowContent({ structure, filters }: Props) {
    const w = t().results.entryWindow;
    const { academicYears } = usePage().props as {
        academicYears?: YearOption[];
    };
    const { showInfo } = usePageError();
    useRegisterPageTitlebarHome(
        useMemo(() => ({ href: "/results", ariaLabel: t().overview.back }), []),
    );

    const [placeFilters, setPlaceFilters] = useState<PlaceFilters>(NO_FILTERS);
    const [entryOpen, setEntryOpen] = useState(false);
    const [allPublished, setAllPublished] = useState(false);
    const [published, setPublished] = useState<Set<string>>(() => new Set());
    const [chosen, setChosen] = useState<Set<string>>(() => new Set());
    const [showMissing, setShowMissing] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [statusView, setStatusView] = useState<StatusView>("all");

    const groups = useMemo(
        () =>
            placeGroups(structure, placeFilters, {
                noDepartment: w.noDepartment,
                section: w.section,
            }),
        [structure, placeFilters, w.noDepartment, w.section],
    );
    // Each branch keeps its colour whatever the filters show: its place among the school's branches.
    const branchIndex = useMemo(
        () =>
            new Map(structure.branches.map((row, index) => [row.name, index])),
        [structure.branches],
    );
    // Memoised: «تحرير» depends on it, and a new array each render re-registered the ribbon in a loop (blank page).
    const places = useMemo(
        () =>
            groups.flatMap((group) =>
                group.sections.map((section) => section.key),
            ),
        [groups],
    );
    // Each branch is its own block (owner's request, 2026-11-28), in the school's branch order.
    // «حالة»: the cards show only the sections of the chosen view (empty cards are left out).
    const shownGroups = useMemo(() => {
        const keep = (key: string): boolean =>
            statusView === "all" ||
            (statusView === "published" &&
                (allPublished || published.has(key))) ||
            (statusView === "unpublished" &&
                !allPublished &&
                !published.has(key)) ||
            (statusView === "chosen" && chosen.has(key));

        return groups
            .map((group) => ({
                ...group,
                sections: group.sections.filter((section) => keep(section.key)),
            }))
            .filter((group) => group.sections.length > 0);
    }, [allPublished, chosen, groups, published, statusView]);
    const branchBlocks = useMemo(() => {
        const blocks = new Map<string, PlaceGroup[]>();
        shownGroups.forEach((group) =>
            blocks.set(group.branch, [
                ...(blocks.get(group.branch) ?? []),
                group,
            ]),
        );

        return [...blocks.entries()].map(([branch, cards]) => ({
            branch,
            cards,
        }));
    }, [shownGroups]);
    const free = places.filter((place) => !published.has(place));
    const all = free.length > 0 && free.every((place) => chosen.has(place));
    const anyPublished = allPublished || published.size > 0;

    const setFilter = (patch: Partial<PlaceFilters>) => {
        setPlaceFilters((current) => ({ ...current, ...patch }));
        // What was ticked belongs to the places shown before.
        setChosen(new Set());
        setShowMissing(false);
    };

    const toggle = (keys: string[], on: boolean) =>
        setChosen((before) => {
            const next = new Set(before);
            keys.forEach((key) => (on ? next.add(key) : next.delete(key)));

            return next;
        });

    const unpublish = (key: string) =>
        setPublished((before) => {
            const next = new Set(before);
            next.delete(key);

            return next;
        });

    const publish = () => {
        if (
            places.length > 0 &&
            places.every((place) => chosen.has(place) || published.has(place))
        ) {
            setAllPublished(true);
        } else {
            setPublished((before) => new Set([...before, ...chosen]));
        }
        setChosen(new Set());
        setShowMissing(false);
        setConfirming(false);
    };

    const chosenRows: ChosenRow[] = groups.flatMap((group) =>
        group.sections
            .filter((section) => chosen.has(section.key))
            .map((section) => ({
                key: section.key,
                branch: group.branch,
                department: group.department,
                className: group.className,
                section: section.name,
            })),
    );

    // «تحرير» as on «المناهج» (owner's request, 2026-11-28): «إجراءات», «حالة» with counts, «توزيعات». Every command
    // stays available; when it cannot act now it says why instead of being greyed out. Ids and labels never change
    // with the state (the state shows as «pressed»), so the icons keep their places when one is pressed.
    const editGroups = useMemo((): PageRibbonGroup[] => {
        const needChoice = (run: () => void) => () => {
            if (chosen.size === 0) {
                showInfo(w.chooseFirst);

                return;
            }
            run();
        };
        const actions: PageRibbonCommand[] = [
            {
                id: "entry-open",
                label: w.open,
                icon: LockOpen,
                iconTone: "ok",
                pressed: entryOpen,
                onSelect: () =>
                    anyPublished
                        ? showInfo(w.hideBeforeOpen)
                        : setEntryOpen(true),
            },
            {
                id: "entry-close",
                label: w.close,
                icon: Lock,
                iconTone: "warning",
                pressed: !entryOpen,
                onSelect: () => setEntryOpen(false),
            },
            {
                id: "missing",
                label: w.showMissing,
                icon: AlertTriangle,
                iconTone: "amber",
                pressed: showMissing,
                onSelect: needChoice(() => setShowMissing((on) => !on)),
            },
            {
                id: "publish",
                label: w.publish,
                icon: Megaphone,
                iconTone: "education",
                onSelect: needChoice(() =>
                    entryOpen ? showInfo(w.closeFirst) : setConfirming(true),
                ),
            },
            {
                id: "hide-results",
                label: w.hideResults,
                icon: EyeOff,
                iconTone: "steel",
                onSelect: () => {
                    if (!anyPublished) {
                        showInfo(w.nothingPublished);

                        return;
                    }
                    setAllPublished(false);
                    setPublished(new Set());
                },
            },
            {
                id: "clear-selection",
                label: w.clearSelection,
                icon: CircleX,
                iconTone: "rose",
                onSelect: needChoice(() => {
                    setChosen(new Set());
                    setShowMissing(false);
                }),
            },
        ];
        const publishedCount = allPublished
            ? places.length
            : places.filter((place) => published.has(place)).length;
        const views: Array<{
            id: StatusView;
            label: string;
            icon: LucideIcon;
            tone: NonNullable<PageRibbonCommand["iconTone"]>;
            count: number;
        }> = [
            {
                id: "all",
                label: w.statusAll,
                icon: LayoutGrid,
                tone: "sky",
                count: places.length,
            },
            {
                id: "published",
                label: w.statusPublished,
                icon: Eye,
                tone: "ok",
                count: publishedCount,
            },
            {
                id: "unpublished",
                label: w.statusUnpublished,
                icon: EyeOff,
                tone: "warning",
                count: places.length - publishedCount,
            },
            {
                id: "chosen",
                label: w.statusChosen,
                icon: SquareCheck,
                tone: "education",
                count: chosen.size,
            },
        ];
        const status: PageRibbonCommand[] = views.map((view) => ({
            id: `status-${view.id}`,
            label: view.label,
            title: `${view.label} (${view.count})`,
            icon: view.icon,
            iconTone: view.tone,
            count: view.count,
            pressed: statusView === view.id,
            onSelect: () => setStatusView(view.id),
        }));
        // «توزيعات»: every branch, specialization, class and section of the school; pressing one ticks (or unticks)
        // its free sections among the places shown (the «الصفحة الرئيسية» filters).
        const pick = (
            id: string,
            label: string,
            icon: LucideIcon,
            tone: NonNullable<PageRibbonCommand["iconTone"]>,
            keys: string[],
        ): PageRibbonCommand => {
            const every =
                keys.length > 0 && keys.every((key) => chosen.has(key));

            return {
                id,
                label,
                title: `${label} (${keys.length})`,
                icon,
                iconTone: tone,
                pressed: every,
                onSelect: () =>
                    entryOpen
                        ? showInfo(w.closeFirst)
                        : keys.length === 0
                          ? showInfo(w.nothingToChoose)
                          : toggle(keys, !every),
            };
        };
        const keysOf = (match: (group: PlaceGroup) => boolean) =>
            freeKeys(groups.filter(match), published);
        const departmentNames = [
            ...new Set(
                structure.departments
                    .filter((row) => row.branch_id !== null)
                    .map((row) => row.name),
            ),
        ];
        const sectionNames = [
            ...new Set(structure.sections.map((row) => row.name)),
        ].sort((a, b) => a.localeCompare(b, "en"));
        const orderedClasses = [...structure.classes].sort(
            (a, b) => a.grade_level_id - b.grade_level_id || a.id - b.id,
        );
        const branches = structure.branches.map((row) =>
            pick(
                `branch-${row.id}`,
                row.name,
                branchIcon(row.name),
                "forest",
                keysOf((group) => group.branch === row.name),
            ),
        );
        const departments = departmentNames.map((name) =>
            pick(
                `department-${name}`,
                name,
                Layers,
                "sky",
                keysOf((group) => group.department === name),
            ),
        );
        const classes = orderedClasses.map((row) =>
            pick(
                `class-${row.id}`,
                row.name,
                GraduationCap,
                "authority",
                keysOf((group) => group.classId === row.id),
            ),
        );
        const sections = sectionNames.map((name) =>
            pick(
                `section-${name}`,
                `${w.section} ${name}`,
                Rows3,
                "education",
                groups
                    .flatMap((group) => group.sections)
                    .filter(
                        (section) =>
                            section.name === name &&
                            !published.has(section.key),
                    )
                    .map((section) => section.key),
            ),
        );

        return [
            { id: "actions", label: w.ribbonActions, commands: actions },
            { id: "status", label: w.ribbonStatus, commands: status },
            {
                id: "distribution-branches",
                label: w.distBranches,
                commands: branches,
            },
            {
                id: "distribution-departments",
                label: w.distDepartments,
                commands: departments,
            },
            {
                id: "distribution-classes",
                label: w.distClasses,
                commands: classes,
            },
            {
                id: "distribution-sections",
                label: w.distSections,
                commands: sections,
            },
        ];
        // toggle only sets this page's state.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        allPublished,
        anyPublished,
        chosen,
        entryOpen,
        groups,
        places,
        published,
        showInfo,
        showMissing,
        statusView,
        structure,
        w,
    ]);
    useRegisterPageRibbon("edit", editGroups);

    const homeGroups = useMemo((): PageRibbonGroup[] => {
        const multi = (
            key: keyof PlaceFilters,
            label: string,
            allLabel: string,
            options: Array<{ value: string; label: string }>,
            extra: Partial<PlaceFilters> = {},
        ) => (
            <RibbonFilterField
                key={key}
                spec={{
                    key,
                    type: "multi",
                    label,
                    allLabel,
                    values: placeFilters[key],
                    options,
                    onChange: (values) =>
                        setFilter({ [key]: values, ...extra }),
                }}
            />
        );
        const departments = structure.departments.filter(
            (row) =>
                row.branch_id !== null &&
                pass(placeFilters.branch, String(row.branch_id)),
        );
        const sectionNames = [
            ...new Set(
                structure.sections
                    .filter((row) =>
                        pass(placeFilters.class, String(row.class_id)),
                    )
                    .map((row) => row.name),
            ),
        ].sort();
        const filtered = Object.values(placeFilters).some(
            (values) => values.length > 0,
        );

        return [
            {
                id: "filter",
                label: w.ribbonFilters,
                commands: [],
                custom: (
                    <div
                        className="sis-ribbon__filters"
                        aria-label={w.ribbonFilters}
                        dir="rtl"
                    >
                        <div className="sis-ribbon__filters-stack sis-ribbon__filters-stack--curriculum">
                            <RibbonFilterField
                                spec={{
                                    key: "year",
                                    type: "single",
                                    label: w.academicYear,
                                    value:
                                        filters.academic_year_id === null
                                            ? ""
                                            : String(filters.academic_year_id),
                                    options: (academicYears ?? []).map(
                                        (year) => ({
                                            value: String(year.id),
                                            label: formatAcademicYearOptionLabel(
                                                year.name,
                                                year.code,
                                            ),
                                        }),
                                    ),
                                    onChange: (year) =>
                                        router.get(
                                            "/results/entry-window",
                                            year === ""
                                                ? {}
                                                : { academic_year_id: year },
                                        ),
                                }}
                            />
                            {multi(
                                "branch",
                                w.branch,
                                w.allBranches,
                                structure.branches.map((row) => ({
                                    value: String(row.id),
                                    label: row.name,
                                })),
                                { department: [] },
                            )}
                            {multi(
                                "department",
                                w.department,
                                w.allDepartments,
                                departments.map((row) => ({
                                    value: String(row.id),
                                    label: row.name,
                                })),
                            )}
                            {multi(
                                "class",
                                w.classLabel,
                                w.allClasses,
                                structure.classes.map((row) => ({
                                    value: String(row.id),
                                    label: row.name,
                                })),
                                { section: [] },
                            )}
                            {multi(
                                "section",
                                w.sectionLabel,
                                w.allSections,
                                sectionNames.map((name) => ({
                                    value: name,
                                    label: name,
                                })),
                            )}
                        </div>
                        <button
                            type="button"
                            className="sis-ribbon__item sis-ribbon__item--filter-clear"
                            aria-label={w.clearFilters}
                            title={w.clearFilters}
                            disabled={!filtered}
                            onClick={() => setFilter(NO_FILTERS)}
                        >
                            <FilterX className="sis-ribbon__icon" aria-hidden />
                            <span className="sis-ribbon__label">
                                {w.clearFilters}
                            </span>
                        </button>
                    </div>
                ),
            },
        ];
        // setFilter only sets this page's state.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [academicYears, filters.academic_year_id, placeFilters, structure, w]);
    useRegisterPageRibbon("home", homeGroups);

    return (
        <>
            <Head title={w.title} />
            <div
                className={`${sisListPageClass({ enrollments: false, extra: "sis-scroll-y-thin" })} gap-4 p-4`}
                dir="rtl"
                lang="ar"
            >
                <h1 className="sr-only">{w.title}</h1>

                <section className="flex flex-wrap items-center gap-3 rounded-md border border-[color:var(--sis-powder-blue)] bg-white p-3">
                    {entryOpen ? (
                        <LockOpen
                            className="size-5 text-[color:var(--sis-steel)]"
                            aria-hidden
                        />
                    ) : (
                        <Lock
                            className="size-5 text-[color:var(--sis-mist)]"
                            aria-hidden
                        />
                    )}
                    <div className="flex-1">
                        <h2 className="text-lg font-semibold">
                            {w.entryTitle}
                        </h2>
                        <p className="text-sm">
                            {entryOpen ? w.entryOpen : w.entryClosed}
                        </p>
                        {!entryOpen && anyPublished ? (
                            <p className="text-sm">{w.hideBeforeOpen}</p>
                        ) : null}
                    </div>
                </section>

                <section className="flex flex-col gap-3 rounded-md border border-[color:var(--sis-powder-blue)] bg-white p-3">
                    <header className="flex flex-wrap items-center gap-3">
                        {/* Mark entry closed: results can be announced — the open eye; entry open: the closed one. */}
                        {!entryOpen ? (
                            <Eye
                                className="size-5 text-[color:var(--sis-steel)]"
                                aria-hidden
                            />
                        ) : (
                            <EyeOff
                                className="size-5 text-[color:var(--sis-mist)]"
                                aria-hidden
                            />
                        )}
                        <div className="flex-1">
                            <h2 className="text-lg font-semibold">
                                {w.announce}
                            </h2>
                            <p className="text-sm">
                                {allPublished
                                    ? w.published
                                    : entryOpen
                                      ? w.closeFirst
                                      : w.tickHint}
                            </p>
                        </div>
                    </header>

                    {!allPublished ? (
                        <fieldset
                            className="flex flex-col gap-3"
                            disabled={entryOpen}
                        >
                            <legend className="mb-2 text-sm font-semibold">
                                {w.places}
                            </legend>
                            {shownGroups.length === 0 ? (
                                <p className="text-sm">{w.noPlaces}</p>
                            ) : (
                                <>
                                    <Tick
                                        checked={all}
                                        some={chosen.size > 0}
                                        disabled={
                                            entryOpen || free.length === 0
                                        }
                                        onChange={(on) => toggle(free, on)}
                                        label={w.allPlaces}
                                        strong
                                    />
                                    <div className="flex flex-col gap-4">
                                        {branchBlocks.map((block) => (
                                            <BranchBlock
                                                key={block.branch}
                                                branch={block.branch}
                                                cards={block.cards}
                                                tint={branchTint(
                                                    block.branch,
                                                    branchIndex.get(
                                                        block.branch,
                                                    ) ?? 0,
                                                )}
                                                chosen={chosen}
                                                published={published}
                                                entryOpen={entryOpen}
                                                toggle={toggle}
                                                unpublish={unpublish}
                                            />
                                        ))}
                                    </div>
                                </>
                            )}
                        </fieldset>
                    ) : null}

                    {showMissing && chosen.size > 0 ? <MissingTable /> : null}
                </section>

                {confirming ? (
                    <PublishSheet
                        rows={chosenRows}
                        onClose={() => setConfirming(false)}
                        onPublish={publish}
                    />
                ) : null}
            </div>
        </>
    );
}
