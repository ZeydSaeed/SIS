import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Eye, EyeOff, FilterX, Lock, LockOpen, Megaphone } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { formatAcademicYearOptionLabel, type YearOption } from '@/components/sis/ops-year-filter';
import { sisListPageClass } from '@/components/sis/page-theme';
import { useRegisterPageRibbon, type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarHome } from '@/components/sis/page-titlebar-home-context';
import { RibbonFilterField } from '@/components/sis/ribbon-filter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type Structure = {
    branches: Array<{ id: number; code: string; name: string }>;
    departments: Array<{ id: number; branch_id: number | null; code: string; name: string }>;
    classes: Array<{ id: number; code: string; name: string; grade_level_id: number }>;
    sections: Array<{ id: number; class_id: number; code: string; name: string }>;
};

type Props = {
    structure: Structure;
    filters: { academic_year_id: number | null };
};

/** «تصفية الصفوف والشعب»: ids of the chosen branches, departments and classes; section letters (A / B / C). */
type PlaceFilters = { branch: string[]; department: string[]; class: string[]; section: string[] };

const NO_FILTERS: PlaceFilters = { branch: [], department: [], class: [], section: [] };

/** One card of «الصفوف والشعب»: a class in a branch and specialization, with its sections. */
type PlaceGroup = { key: string; label: string; sections: Array<{ key: string; label: string }> };

const pass = (chosen: string[], value: string): boolean => chosen.length === 0 || chosen.includes(value);

/** الفرع · الاختصاص · الصف, each with its sections — narrowed by the filters (none chosen = all). */
function placeGroups(structure: Structure, filters: PlaceFilters, labels: { noDepartment: string; section: string }): PlaceGroup[] {
    const groups: PlaceGroup[] = [];
    for (const branch of structure.branches.filter((row) => pass(filters.branch, String(row.id)))) {
        const own = structure.departments.filter((row) => row.branch_id === branch.id);
        const departments: Array<{ id: number; name: string } | null> = own.length > 0 ? own : [null];
        for (const department of departments.filter((row) => pass(filters.department, String(row?.id ?? '')))) {
            for (const klass of structure.classes.filter((row) => pass(filters.class, String(row.id)))) {
                const sections = structure.sections.filter((row) => row.class_id === klass.id && pass(filters.section, row.name));
                if (sections.length === 0) {
                    continue;
                }
                const base = `${branch.id}|${department?.id ?? 0}|${klass.id}`;
                groups.push({
                    key: base,
                    label: [branch.name, department?.name ?? labels.noDepartment, klass.name].join(' — '),
                    sections: sections.map((row) => ({ key: `${base}|${row.id}`, label: `${labels.section} ${row.name}` })),
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
        <label className={`flex items-center gap-2 text-sm ${strong ? 'font-semibold' : ''} ${disabled ? 'opacity-60' : ''}`}>
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
                            <th className="sis-admission-drafts-table__name-head">{w.placeColumn}</th>
                            <th className="sis-admission-drafts-table__name-head">{w.subjectColumn}</th>
                            <th className="sis-admission-drafts-table__name-head">{w.teacherColumn}</th>
                            <th className="sis-admission-drafts-table__name-head">{w.missingColumn}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td className="sis-admission-drafts-table__text" colSpan={4}>
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
        { title: t().modules.results, href: '/results' },
        { title: t().results.entryWindow.title, href: '/results/entry-window' },
    ];

    // Inner component: the ribbon («الصفحة الرئيسية» filters, «تحرير» commands) and the title bar live inside AppLayout.
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <EntryWindowContent {...props} />
        </AppLayout>
    );
}

/** A card's own light colour, different for each one (golden-angle hue by its place, as each curriculum's colour). */
function cardTint(index: number): { background: string; borderColor: string } {
    const hue = Math.round(((index + 1) * 137.508) % 360);

    return { background: `hsl(${hue} 70% 96%)`, borderColor: `hsl(${hue} 50% 78%)` };
}

function EntryWindowContent({ structure, filters }: Props) {
    const w = t().results.entryWindow;
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    useRegisterPageTitlebarHome(useMemo(() => ({ href: '/results', ariaLabel: t().overview.back }), []));

    const [placeFilters, setPlaceFilters] = useState<PlaceFilters>(NO_FILTERS);
    const [entryOpen, setEntryOpen] = useState(false);
    const [allPublished, setAllPublished] = useState(false);
    const [published, setPublished] = useState<Set<string>>(() => new Set());
    const [chosen, setChosen] = useState<Set<string>>(() => new Set());
    const [showMissing, setShowMissing] = useState(false);
    const [confirming, setConfirming] = useState(false);

    const groups = useMemo(
        () => placeGroups(structure, placeFilters, { noDepartment: w.noDepartment, section: w.section }),
        [structure, placeFilters, w.noDepartment, w.section],
    );
    // Each card keeps its colour whatever the filters show: its place in the unfiltered list.
    const tintIndex = useMemo(
        () =>
            new Map(
                placeGroups(structure, NO_FILTERS, { noDepartment: w.noDepartment, section: w.section }).map((group, index) => [group.key, index]),
            ),
        [structure, w.noDepartment, w.section],
    );
    const places = groups.flatMap((group) => group.sections.map((section) => section.key));
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

    const publish = () => {
        if (places.length > 0 && places.every((place) => chosen.has(place) || published.has(place))) {
            setAllPublished(true);
        } else {
            setPublished((before) => new Set([...before, ...chosen]));
        }
        setChosen(new Set());
        setShowMissing(false);
        setConfirming(false);
    };

    const chosenLabels = groups.flatMap((group) =>
        group.sections.filter((section) => chosen.has(section.key)).map((section) => `${group.label} — ${section.label}`),
    );

    const editGroups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'actions',
                label: w.ribbonActions,
                commands: [
                    entryOpen
                        ? { id: 'entry-close', label: w.close, icon: Lock, iconTone: 'warning', onSelect: () => setEntryOpen(false) }
                        : {
                              id: 'entry-open',
                              label: w.open,
                              icon: LockOpen,
                              iconTone: 'ok',
                              disabled: anyPublished,
                              title: anyPublished ? w.hideBeforeOpen : undefined,
                              onSelect: () => setEntryOpen(true),
                          },
                    {
                        id: 'missing',
                        label: showMissing ? w.hideMissing : w.showMissing,
                        icon: AlertTriangle,
                        iconTone: 'amber',
                        pressed: showMissing,
                        disabled: allPublished || chosen.size === 0,
                        onSelect: () => setShowMissing((on) => !on),
                    },
                    {
                        id: 'publish',
                        label: all && published.size === 0 ? w.publishAll : w.publishChosen,
                        icon: Megaphone,
                        iconTone: 'education',
                        disabled: allPublished || entryOpen || chosen.size === 0,
                        title: entryOpen ? w.closeFirst : undefined,
                        onSelect: () => setConfirming(true),
                    },
                    {
                        id: 'hide-results',
                        label: w.hideResults,
                        icon: EyeOff,
                        iconTone: 'steel',
                        disabled: !anyPublished,
                        onSelect: () => {
                            setAllPublished(false);
                            setPublished(new Set());
                        },
                    },
                ],
            },
        ],
        [all, allPublished, anyPublished, chosen.size, entryOpen, published.size, showMissing, w],
    );
    useRegisterPageRibbon('edit', editGroups);

    const homeGroups = useMemo((): PageRibbonGroup[] => {
        const multi = (key: keyof PlaceFilters, label: string, allLabel: string, options: Array<{ value: string; label: string }>, extra: Partial<PlaceFilters> = {}) => (
            <RibbonFilterField
                key={key}
                spec={{ key, type: 'multi', label, allLabel, values: placeFilters[key], options, onChange: (values) => setFilter({ [key]: values, ...extra }) }}
            />
        );
        const departments = structure.departments.filter((row) => row.branch_id !== null && pass(placeFilters.branch, String(row.branch_id)));
        const sectionNames = [...new Set(structure.sections.filter((row) => pass(placeFilters.class, String(row.class_id))).map((row) => row.name))].sort();
        const filtered = Object.values(placeFilters).some((values) => values.length > 0);

        return [
            {
                id: 'filter',
                label: w.ribbonFilters,
                commands: [],
                custom: (
                    <div className="sis-ribbon__filters" aria-label={w.ribbonFilters} dir="rtl">
                        <div className="sis-ribbon__filters-stack sis-ribbon__filters-stack--curriculum">
                            <RibbonFilterField
                                spec={{
                                    key: 'year',
                                    type: 'single',
                                    label: w.academicYear,
                                    value: filters.academic_year_id === null ? '' : String(filters.academic_year_id),
                                    options: (academicYears ?? []).map((year) => ({ value: String(year.id), label: formatAcademicYearOptionLabel(year.name, year.code) })),
                                    onChange: (year) => router.get('/results/entry-window', year === '' ? {} : { academic_year_id: year }),
                                }}
                            />
                            {multi('branch', w.branch, w.allBranches, structure.branches.map((row) => ({ value: String(row.id), label: row.name })), { department: [] })}
                            {multi('department', w.department, w.allDepartments, departments.map((row) => ({ value: String(row.id), label: row.name })))}
                            {multi('class', w.classLabel, w.allClasses, structure.classes.map((row) => ({ value: String(row.id), label: row.name })), { section: [] })}
                            {multi('section', w.sectionLabel, w.allSections, sectionNames.map((name) => ({ value: name, label: name })))}
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
                            <span className="sis-ribbon__label">{w.clearFilters}</span>
                        </button>
                    </div>
                ),
            },
        ];
        // setFilter only sets this page's state.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [academicYears, filters.academic_year_id, placeFilters, structure, w]);
    useRegisterPageRibbon('home', homeGroups);

    return (
        <>
            <Head title={w.title} />
            <div className={`${sisListPageClass({ enrollments: false, extra: 'sis-scroll-y-thin' })} gap-4 p-4`} dir="rtl" lang="ar">
                <h1 className="sr-only">{w.title}</h1>

                <section className="flex flex-wrap items-center gap-3 rounded-md border border-[color:var(--sis-powder-blue)] bg-white p-3">
                    {entryOpen ? (
                        <LockOpen className="size-5 text-[color:var(--sis-steel)]" aria-hidden />
                    ) : (
                        <Lock className="size-5 text-[color:var(--sis-mist)]" aria-hidden />
                    )}
                    <div className="flex-1">
                        <h2 className="text-lg font-semibold">{w.entryTitle}</h2>
                        <p className="text-sm">{entryOpen ? w.entryOpen : w.entryClosed}</p>
                        {!entryOpen && anyPublished ? <p className="text-sm">{w.hideBeforeOpen}</p> : null}
                    </div>
                </section>

                <section className="flex flex-col gap-3 rounded-md border border-[color:var(--sis-powder-blue)] bg-white p-3">
                    <header className="flex flex-wrap items-center gap-3">
                        {allPublished ? (
                            <Eye className="size-5 text-[color:var(--sis-steel)]" aria-hidden />
                        ) : (
                            <EyeOff className="size-5 text-[color:var(--sis-mist)]" aria-hidden />
                        )}
                        <div className="flex-1">
                            <h2 className="text-lg font-semibold">{w.announce}</h2>
                            <p className="text-sm">{allPublished ? w.published : entryOpen ? w.closeFirst : w.tickHint}</p>
                        </div>
                    </header>

                    {!allPublished ? (
                        <fieldset className="flex flex-col gap-3" disabled={entryOpen}>
                            <legend className="mb-2 text-sm font-semibold">{w.places}</legend>
                            {groups.length === 0 ? (
                                <p className="text-sm">{w.noPlaces}</p>
                            ) : (
                                <>
                                    <Tick
                                        checked={all}
                                        some={chosen.size > 0}
                                        disabled={entryOpen || free.length === 0}
                                        onChange={(on) => toggle(free, on)}
                                        label={w.allPlaces}
                                        strong
                                    />
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {groups.map((group) => {
                                            const keys = group.sections.map((section) => section.key).filter((key) => !published.has(key));
                                            const every = keys.length > 0 && keys.every((key) => chosen.has(key));

                                            return (
                                                <div
                                                    key={group.key}
                                                    className="flex flex-col gap-2 rounded-md border p-3"
                                                    style={cardTint(tintIndex.get(group.key) ?? 0)}
                                                >
                                                    <Tick
                                                        checked={every}
                                                        some={keys.some((key) => chosen.has(key))}
                                                        disabled={entryOpen || keys.length === 0}
                                                        onChange={(on) => toggle(keys, on)}
                                                        label={group.label}
                                                        strong
                                                    />
                                                    <div className="flex flex-col gap-2 ps-6">
                                                        {group.sections.map((section) => {
                                                            const out = published.has(section.key);

                                                            return (
                                                                <div key={section.key} className="flex flex-wrap items-center gap-2">
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
                                                                                onClick={() =>
                                                                                    setPublished((before) => {
                                                                                        const next = new Set(before);
                                                                                        next.delete(section.key);

                                                                                        return next;
                                                                                    })
                                                                                }
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
                                        })}
                                    </div>
                                </>
                            )}
                        </fieldset>
                    ) : null}

                    {showMissing && chosen.size > 0 ? <MissingTable /> : null}
                </section>

                <Dialog open={confirming} onOpenChange={setConfirming}>
                    <DialogContent className="sm:max-w-3xl" dir="rtl">
                        <DialogTitle>{w.confirmTitle}</DialogTitle>
                        <DialogDescription>{chosenLabels.join('، ')}</DialogDescription>
                        <div className="flex max-h-[55vh] flex-col gap-3 overflow-y-auto">
                            <MissingTable />
                        </div>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="outline">{w.cancel}</Button>
                            </DialogClose>
                            <Button onClick={publish}>{w.publish}</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
