import { Head, router, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    CheckCircle2,
    ChevronUp,
    CircleSlash,
    Eye,
    Layers,
    LayoutGrid,
    Pencil,
    Plus,
    PlusCircle,
    RotateCcw,
    Save,
    School,
    Trash2,
    UserRound,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    RegistryListField,
    RegistrySheetDialog,
    RegistryTextField,
    useRegistryRequest,
} from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { formatAcademicYearOptionLabel, type YearOption } from '@/components/sis/ops-year-filter';
import { useRegisterPageRibbon, type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Section = {
    id: number;
    code: string;
    name: string;
    capacity: number | null;
    homeroom_teacher_id: number | null;
    status: number;
    enrolled: number;
};

type SchoolClass = {
    id: number;
    code: string;
    name: string;
    grade_level_id: number;
    capacity: number | null;
    status: number;
    sections: Section[];
};

type Props = {
    classes: SchoolClass[];
    gradeLevels: Array<{ id: number; name: string; level_order: number }>;
    teachers: Array<{ id: number; full_name: string; active: boolean }>;
    filters: { academic_year_id: number | null };
    authorization: { can_manage: boolean };
};

type ClassForm = { name: string; grade_level_id: string; capacity: string };
type SectionDraft = { id: number | null; name: string; capacity: string; homeroom_teacher_id: string; viewOnly?: boolean };

/** EnrollmentStructureStatus: 1 نشط · 2 معطّل. */
const ACTIVE = 1;
const RELOAD_PROPS = ['classes', 'teachers', 'flash'];

function classFormOf(item: SchoolClass | null): ClassForm {
    return {
        name: item?.name ?? '',
        grade_level_id: item === null ? '' : String(item.grade_level_id),
        capacity: item?.capacity === null || item === null ? '' : String(item.capacity),
    };
}

function capacityValue(value: string): number | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : Number(trimmed);
}

function StatusPill({ status }: { status: number }) {
    const c = t().classStructure;

    return (
        <span className={`sis-branches-status${status === ACTIVE ? '' : ' sis-org-status--inactive'}`}>
            {status === ACTIVE ? c.statusActive : c.statusInactive}
        </span>
    );
}

function ClassTile({ active, large = false }: { active: boolean; large?: boolean }) {
    return (
        <span
            className={`sis-branches-tile sis-branches-tile--${active ? 'scientific' : 'default'}${large ? ' sis-branches-tile--lg' : ''}`}
            aria-hidden="true"
        >
            <School />
        </span>
    );
}

export default function OrganizationClassesSections(props: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ title: t().classStructure.title, href: '/organization/classes-sections' }];

    // Inner component: page error / flash contexts live inside AppLayout.
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ClassesSectionsPage {...props} />
        </AppLayout>
    );
}

function ClassesSectionsPage({ classes, gradeLevels, teachers, filters, authorization }: Props) {
    const i18n = t();
    const c = i18n.classStructure;
    const r = i18n.orgRibbon;
    const canManage = authorization.can_manage;
    const yearId = filters.academic_year_id;
    const request = useRegistryRequest(RELOAD_PROPS);
    const page = usePage().props as {
        schoolContext?: { schoolId: number | null; schools: Array<{ id: number; name: string }> };
        academicYears?: YearOption[];
    };
    const schoolName = page.schoolContext?.schools.find((school) => school.id === page.schoolContext?.schoolId)?.name ?? null;
    const years = page.academicYears ?? [];
    const gradeName = useMemo(() => new Map(gradeLevels.map((grade) => [grade.id, grade.name])), [gradeLevels]);
    const teacherName = useMemo(() => new Map(teachers.map((teacher) => [teacher.id, teacher.full_name])), [teachers]);
    const gradeOptions = gradeLevels.map((grade) => ({ value: String(grade.id), label: grade.name }));
    const homeroomOptions = [
        { value: '', label: c.noHomeroom },
        ...teachers.filter((teacher) => teacher.active).map((teacher) => ({ value: String(teacher.id), label: teacher.full_name })),
    ];

    const [selectedId, setSelectedId] = useState<number | null>(classes[0]?.id ?? null);
    /** A section row of the selected class; when set, the «تحرير» actions target it. */
    const [selectedSectionId, setSelectedSectionId] = useState<number | null>(null);
    const [collapsed, setCollapsed] = useState(true);
    const [form, setForm] = useState<ClassForm>(classFormOf(null));
    const [saving, setSaving] = useState(false);
    const [addOpen, setAddOpen] = useState(false);
    const [newClass, setNewClass] = useState<ClassForm>(classFormOf(null));
    const [sectionDraft, setSectionDraft] = useState<SectionDraft | null>(null);
    const [confirmDeactivate, setConfirmDeactivate] = useState<'class' | 'section' | null>(null);
    const [filterKey, setFilterKey] = useState<'all' | 'active' | 'inactive'>('all');
    const [query, setQuery] = useState('');
    const nameInputRef = useRef<HTMLInputElement | null>(null);

    const selected = classes.find((item) => item.id === selectedId) ?? null;
    const selectedSection = selected?.sections.find((section) => section.id === selectedSectionId) ?? null;
    const selectClass = (classId: number) => {
        setSelectedId(classId);
        setSelectedSectionId(null);
    };

    // Keep a valid selection after reloads (create / deactivate).
    useEffect(() => {
        if (selectedId === null || !classes.some((item) => item.id === selectedId)) {
            setSelectedId(classes[0]?.id ?? null);
        }
    }, [classes, selectedId]);

    useEffect(() => {
        setForm(classFormOf(selected));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selected?.id, selected?.name, selected?.grade_level_id, selected?.capacity]);

    const activeCount = classes.filter((item) => item.status === ACTIVE).length;
    const allSections = classes.flatMap((item) => item.sections);
    const withHomeroom = allSections.filter((section) => section.homeroom_teacher_id !== null).length;
    const visibleClasses = useMemo(() => {
        const q = query.trim();

        return classes.filter((item) => {
            if (filterKey === 'active' && item.status !== ACTIVE) return false;
            if (filterKey === 'inactive' && item.status === ACTIVE) return false;

            return q === '' || item.name.includes(q) || item.code.includes(q) || item.sections.some((section) => section.name.includes(q));
        });
    }, [classes, filterKey, query]);

    const selectedForm = classFormOf(selected);
    const dirty =
        selected !== null && (Object.keys(form) as Array<keyof ClassForm>).some((key) => form[key].trim() !== selectedForm[key].trim());
    const formValid = form.name.trim() !== '' && form.grade_level_id !== '';
    const sectionEditable = sectionDraft !== null && sectionDraft.viewOnly !== true && sectionDraft.name.trim() !== '';

    const run = async (action: () => Promise<boolean>, after?: () => void): Promise<void> => {
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

    const saveClass = () =>
        selected &&
        run(() =>
            request('patch', `/organization/classes/${selected.id}`, {
                name: form.name.trim(),
                grade_level_id: Number(form.grade_level_id),
                capacity: capacityValue(form.capacity),
            }),
        );

    const createClass = () =>
        run(
            () =>
                request('post', '/organization/classes', {
                    academic_year_id: yearId,
                    name: newClass.name.trim(),
                    grade_level_id: Number(newClass.grade_level_id),
                    capacity: capacityValue(newClass.capacity),
                }),
            () => {
                setAddOpen(false);
                setNewClass(classFormOf(null));
            },
        );

    const saveSection = () => {
        if (sectionDraft === null || selected === null) {
            return;
        }
        const payload = {
            name: sectionDraft.name.trim(),
            capacity: capacityValue(sectionDraft.capacity),
            homeroom_teacher_id: sectionDraft.homeroom_teacher_id === '' ? null : Number(sectionDraft.homeroom_teacher_id),
        };
        void run(
            () =>
                sectionDraft.id === null
                    ? request('post', '/organization/sections', { ...payload, class_id: selected.id })
                    : request('patch', `/organization/sections/${sectionDraft.id}`, payload),
            () => setSectionDraft(null),
        );
    };

    const setClassStatus = (item: SchoolClass, active: boolean, after?: () => void) =>
        run(() => request('post', `/organization/classes/${item.id}/${active ? 'reactivate' : 'deactivate'}`), after);
    const setSectionStatus = (section: Section, active: boolean, after?: () => void) =>
        run(() => request('post', `/organization/sections/${section.id}/${active ? 'reactivate' : 'deactivate'}`), after);

    const openAddSection = () => setSectionDraft({ id: null, name: '', capacity: '', homeroom_teacher_id: '' });
    const openSection = (section: Section, viewOnly = false) =>
        setSectionDraft({
            id: section.id,
            name: section.name,
            capacity: section.capacity === null ? '' : String(section.capacity),
            homeroom_teacher_id: section.homeroom_teacher_id === null ? '' : String(section.homeroom_teacher_id),
            viewOnly,
        });

    const changeYear = (next: string) => {
        if (next === '' || Number(next) === yearId) {
            return;
        }
        router.get('/organization/classes-sections', { academic_year_id: Number(next) }, { preserveScroll: true });
    };

    const homeroomPercent = allSections.length === 0 ? 0 : Math.round((withHomeroom / allSections.length) * 100);
    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const noSelection = selected === null;
        const target = selectedSection ?? selected;
        const targetActive = target?.status === ACTIVE;
        const groups: PageRibbonGroup[] = [
            {
                id: 'classes-actions',
                label: i18n.common.actions,
                commands: [
                    {
                        id: 'classes-view',
                        label: i18n.common.view,
                        icon: Eye,
                        title: noSelection ? r.needsSelection : i18n.common.view,
                        disabled: noSelection,
                        onSelect: () => (selectedSection !== null ? openSection(selectedSection, true) : setCollapsed(false)),
                    },
                    ...(canManage
                        ? [
                              {
                                  id: 'classes-edit',
                                  label: i18n.common.edit,
                                  icon: Pencil,
                                  tone: 'edit' as const,
                                  disabled: noSelection || saving,
                                  onSelect: () => {
                                      if (selectedSection !== null) {
                                          openSection(selectedSection);

                                          return;
                                      }
                                      setCollapsed(false);
                                      window.requestAnimationFrame(() => nameInputRef.current?.focus());
                                  },
                              },
                              {
                                  id: 'classes-save',
                                  label: i18n.common.save,
                                  icon: Save,
                                  tone: 'save' as const,
                                  disabled: saving || !(sectionEditable || (dirty && formValid)),
                                  onSelect: () => (sectionEditable ? saveSection() : void saveClass()),
                              },
                          ]
                        : []),
                    {
                        id: 'classes-cancel',
                        label: i18n.common.cancel,
                        icon: XCircle,
                        disabled: noSelection || saving,
                        onSelect: () => {
                            if (selectedSectionId !== null) {
                                setSelectedSectionId(null);

                                return;
                            }
                            setForm(classFormOf(selected));
                            setCollapsed(true);
                        },
                    },
                    ...(canManage
                        ? [
                              targetActive || noSelection
                                  ? {
                                        id: 'classes-delete',
                                        label: i18n.common.delete,
                                        icon: Trash2,
                                        tone: 'delete' as const,
                                        title: selectedSection !== null ? c.deactivateSection : c.deactivateClass,
                                        disabled: noSelection || saving,
                                        onSelect: () => setConfirmDeactivate(selectedSection !== null ? 'section' : 'class'),
                                    }
                                  : {
                                        id: 'classes-reactivate',
                                        label: c.reactivate,
                                        icon: RotateCcw,
                                        disabled: saving,
                                        onSelect: () =>
                                            selectedSection !== null
                                                ? void setSectionStatus(selectedSection, true)
                                                : selected && void setClassStatus(selected, true),
                                    },
                          ]
                        : []),
                ],
            },
            {
                id: 'classes-filters',
                label: c.filters,
                commands: [
                    { key: 'all' as const, label: c.all, icon: Layers, count: classes.length },
                    { key: 'active' as const, label: c.active, icon: CheckCircle2, count: activeCount },
                    { key: 'inactive' as const, label: c.inactive, icon: CircleSlash, count: classes.length - activeCount },
                ].map((tab) => ({
                    id: `classes-filter-${tab.key}`,
                    label: tab.label,
                    title: `${tab.label} (${tab.count})`,
                    icon: tab.icon,
                    count: tab.count,
                    pressed: filterKey === tab.key,
                    onSelect: () => setFilterKey(filterKey === tab.key && tab.key !== 'all' ? 'all' : tab.key),
                })),
            },
            {
                id: 'classes-year',
                label: c.academicYear,
                commands: [],
                custom: (
                    <div className="sis-ribbon__filters" aria-label={c.academicYear} dir="rtl">
                        <div className="sis-ribbon__filter-field" dir="rtl">
                            <CalendarRange aria-hidden className="sis-branches-table__icon" />
                            <SisListSelect
                                value={yearId === null ? '' : String(yearId)}
                                options={years.map((year) => ({
                                    value: String(year.id),
                                    label: formatAcademicYearOptionLabel(year.name, year.code),
                                }))}
                                onChange={changeYear}
                                triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                                dir="rtl"
                                ariaLabel={c.academicYear}
                            />
                        </div>
                    </div>
                ),
            },
        ];
        if (canManage) {
            groups.push({
                id: 'classes-manage',
                label: r.manage,
                commands: [
                    { id: 'classes-add', label: c.addClass, icon: PlusCircle, disabled: yearId === null, onSelect: () => setAddOpen(true) },
                    {
                        id: 'sections-add',
                        label: c.addSection,
                        icon: LayoutGrid,
                        disabled: noSelection || selected?.status !== ACTIVE,
                        onSelect: openAddSection,
                    },
                ],
            });
        }
        groups.push({
            id: 'classes-progress',
            label: r.progress,
            commands: [],
            custom: (
                <div
                    className="sis-ribbon__progress-track"
                    role="progressbar"
                    aria-label={c.progress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={homeroomPercent}
                    data-contrast={homeroomPercent >= 45 ? 'light' : 'dark'}
                    dir="rtl"
                    title={`${c.progress}: ${homeroomPercent}%`}
                >
                    <span className="sis-ribbon__progress-fill" style={{ width: `${homeroomPercent}%` }} />
                    <span className="sis-ribbon__progress-value" dir="ltr">
                        {homeroomPercent}%
                    </span>
                </div>
            ),
        });

        return groups;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activeCount, canManage, classes, dirty, filterKey, formValid, homeroomPercent, i18n, saving, sectionDraft, sectionEditable, selected, selectedSection, selectedSectionId, years, yearId]);
    useRegisterPageRibbon('edit', editRibbonGroups);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: query,
            label: c.search,
            placeholder: c.search,
            onDraftChange: setQuery,
            onCommit: setQuery,
        }),
        [query, c.search],
    );
    useRegisterPageTitlebarSearch(titlebarSearch);

    const classFields = (value: ClassForm, onChange: (next: ClassForm) => void, editing: boolean, ref?: typeof nameInputRef) => (
        <>
            <RegistryTextField
                label={c.className}
                inputRef={ref}
                editing={editing}
                required
                value={value.name}
                onChange={(name) => onChange({ ...value, name })}
                fieldClassName="sis-branches-field--wide"
            />
            <RegistryListField
                label={c.gradeLevel}
                editing={editing}
                required
                value={value.grade_level_id}
                display={gradeName.get(Number(value.grade_level_id)) ?? '—'}
                options={gradeOptions}
                onChange={(grade_level_id) => onChange({ ...value, grade_level_id })}
                fieldClassName="sis-branches-field--wide"
            />
            <RegistryTextField
                label={`${c.capacity} (${c.capacityHint})`}
                editing={editing}
                type="number"
                dir="ltr"
                value={value.capacity}
                onChange={(capacity) => onChange({ ...value, capacity })}
                fieldClassName="sis-branches-field--wide"
            />
        </>
    );

    const confirmTarget = confirmDeactivate === 'section' ? selectedSection : selected;

    return (
        <>
            <Head title={c.title} />
            <div className="sis-ops-hub sis-branches-page sis-org-page" dir="rtl" lang="ar">
                <header className="sis-branches-page__head">
                    <div className="sis-branches-page__heading sis-branches-page__heading--centered">
                        <h1 className="sis-branches-page__title">
                            {c.heading}
                            {schoolName !== null ? (
                                <>
                                    <span className="sis-branches-page__title-sep" aria-hidden="true">
                                        {' · '}
                                    </span>
                                    <span className="sis-branches-page__title-school">
                                        {c.school}: {schoolName}
                                    </span>
                                </>
                            ) : null}
                        </h1>
                    </div>
                </header>
                {canManage ? null : <p className="sis-branches-page__notice">{c.readOnly}</p>}
                {yearId === null ? <p className="sis-branches-page__notice">{c.noYear}</p> : null}

                <div className="sis-branches-page__grid">
                    {/* Classes list */}
                    <section className="sis-branches-card sis-branches-list" aria-label={c.classesTitle}>
                        <div className="sis-branches-card__head">
                            <h2 className="sis-branches-card__title">{c.classesTitle}</h2>
                            <span className="sis-branches-count" dir="ltr">
                                {classes.length}
                            </span>
                        </div>
                        <div className="sis-branches-table sis-branches-table--branches" role="table" aria-label={c.classesTitle}>
                            <div className="sis-branches-table__row sis-branches-table__row--head" role="row">
                                <span role="columnheader">{c.class}</span>
                            </div>
                            {visibleClasses.length === 0 ? (
                                <div className="sis-branches-table__row" role="row">
                                    <span role="cell" className="sis-branches-empty">
                                        {classes.length === 0 ? c.noClasses : c.noSearchResult}
                                    </span>
                                </div>
                            ) : (
                                visibleClasses.map((item) => (
                                    <div
                                        key={item.id}
                                        className={`sis-branches-table__row sis-branches-table__row--branch${
                                            item.id === selectedId ? ' is-selected' : ''
                                        }${item.status === ACTIVE ? '' : ' sis-org-item--inactive'}`}
                                        role="row"
                                        tabIndex={0}
                                        aria-selected={item.id === selectedId}
                                        onClick={() => selectClass(item.id)}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter' || event.key === ' ') {
                                                event.preventDefault();
                                                selectClass(item.id);
                                            }
                                        }}
                                    >
                                        <span role="cell" className="sis-branches-table__name">
                                            <ClassTile active={item.status === ACTIVE} />
                                            <span className="sis-branches-item__text">
                                                <span className="sis-branches-item__name">
                                                    {item.name}
                                                    {item.status === ACTIVE ? null : <StatusPill status={item.status} />}
                                                </span>
                                                <span className="sis-branches-item__meta">
                                                    {gradeName.get(item.grade_level_id) ?? '—'}
                                                    {` · ${item.sections.length} ${c.sectionsCount}`}
                                                </span>
                                            </span>
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                        {canManage && yearId !== null ? (
                            <button type="button" className="sis-branches-add-row" onClick={() => setAddOpen(true)}>
                                <Plus aria-hidden />
                                {c.addClass}
                            </button>
                        ) : null}
                    </section>

                    {/* Selected class */}
                    <section className="sis-branches-card sis-branches-detail" aria-label={selected?.name ?? c.classesTitle}>
                        {selected === null ? (
                            <p className="sis-branches-empty">{c.selectClass}</p>
                        ) : (
                            <>
                                <div className="sis-branches-card__head sis-branches-detail__head">
                                    <ClassTile active={selected.status === ACTIVE} large />
                                    <h2 className="sis-branches-card__title">
                                        {c.class} {selected.name}
                                    </h2>
                                    <StatusPill status={selected.status} />
                                    <button
                                        type="button"
                                        className="sis-branches-icon-btn sis-branches-icon-btn--plain sis-branches-detail__toggle"
                                        aria-expanded={!collapsed}
                                        title={collapsed ? c.expand : c.collapse}
                                        aria-label={collapsed ? c.expand : c.collapse}
                                        onClick={() => setCollapsed((value) => !value)}
                                    >
                                        <ChevronUp aria-hidden className={collapsed ? 'is-collapsed' : undefined} />
                                    </button>
                                </div>

                                {collapsed ? null : (
                                    <div className="sis-branches-detail__form">
                                        {classFields(form, setForm, canManage, nameInputRef)}
                                        {canManage ? (
                                            <div className="sis-branches-detail__buttons">
                                                <Button type="button" disabled={!dirty || !formValid || saving} onClick={() => void saveClass()}>
                                                    {saving ? i18n.common.saving : c.saveChanges}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={!dirty || saving}
                                                    onClick={() => setForm(classFormOf(selected))}
                                                >
                                                    {c.cancel}
                                                </Button>
                                            </div>
                                        ) : null}
                                    </div>
                                )}

                                <div className="sis-branches-table" role="table" aria-label={c.sectionsTitle}>
                                    <div className="sis-branches-table__row sis-branches-table__row--head" role="row">
                                        <span role="columnheader" className="sis-branches-table__heading">
                                            {c.sectionsTitle}
                                            <span className="sis-branches-count" dir="ltr">
                                                {selected.sections.length}
                                            </span>
                                        </span>
                                    </div>
                                    {selected.sections.length === 0 ? (
                                        <div className="sis-branches-table__row" role="row">
                                            <span role="cell" className="sis-branches-empty">
                                                {c.noSections}
                                            </span>
                                        </div>
                                    ) : (
                                        selected.sections.map((section) => (
                                            <div
                                                key={section.id}
                                                className={`sis-branches-table__row sis-branches-table__row--selectable${
                                                    section.id === selectedSectionId ? ' is-selected' : ''
                                                }${section.status === ACTIVE ? '' : ' sis-org-item--inactive'}`}
                                                role="row"
                                                tabIndex={0}
                                                aria-selected={section.id === selectedSectionId}
                                                onClick={() => setSelectedSectionId(section.id)}
                                                onDoubleClick={() => openSection(section, !canManage)}
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter' || event.key === ' ') {
                                                        event.preventDefault();
                                                        setSelectedSectionId(section.id);
                                                    }
                                                }}
                                            >
                                                <span role="cell" className="sis-branches-table__name">
                                                    <LayoutGrid aria-hidden className="sis-branches-table__icon" />
                                                    <span className="sis-branches-item__text">
                                                        <span className="sis-branches-item__name">
                                                            {section.name}
                                                            {section.status === ACTIVE ? null : <StatusPill status={section.status} />}
                                                        </span>
                                                        <span className="sis-branches-item__meta">
                                                            {c.enrolled}:{' '}
                                                            <span dir="ltr">
                                                                {section.enrolled} / {section.capacity ?? c.unlimited}
                                                            </span>
                                                            {' · '}
                                                            <UserRound aria-hidden className="sis-branches-table__icon" />{' '}
                                                            {c.homeroom}:{' '}
                                                            {section.homeroom_teacher_id === null
                                                                ? c.noHomeroom
                                                                : (teacherName.get(section.homeroom_teacher_id) ?? `#${section.homeroom_teacher_id}`)}
                                                        </span>
                                                    </span>
                                                </span>
                                            </div>
                                        ))
                                    )}
                                </div>
                                {canManage && selected.status === ACTIVE ? (
                                    <button type="button" className="sis-branches-add-row" onClick={openAddSection}>
                                        <Plus aria-hidden />
                                        {c.addSectionToClass}
                                    </button>
                                ) : null}
                            </>
                        )}
                    </section>
                </div>
            </div>

            {/* 1) Add class */}
            {addOpen ? (
                <RegistrySheetDialog title={c.addClass} className="sis-branches-sheet" onClose={() => setAddOpen(false)}>
                    <SheetSection id="classes-add" title={c.addClass}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            {classFields(newClass, setNewClass, true)}
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setAddOpen(false)}>
                            {c.cancel}
                        </Button>
                        <Button
                            type="button"
                            disabled={saving || newClass.name.trim() === '' || newClass.grade_level_id === ''}
                            onClick={() => void createClass()}
                        >
                            {saving ? i18n.common.saving : c.add}
                        </Button>
                    </div>
                </RegistrySheetDialog>
            ) : null}

            {/* 2) Add / view / edit section (+ homeroom teacher) */}
            {sectionDraft !== null && selected !== null ? (
                <RegistrySheetDialog
                    title={sectionDraft.id === null ? c.addSectionToClass : sectionDraft.viewOnly ? c.viewSection : c.editSection}
                    className="sis-branches-sheet"
                    onClose={() => setSectionDraft(null)}
                >
                    <SheetSection id="classes-section" title={`${c.class} ${selected.name}`}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <RegistryTextField
                                label={c.sectionName}
                                editing={sectionDraft.viewOnly !== true}
                                required
                                value={sectionDraft.name}
                                onChange={(name) => setSectionDraft((current) => (current === null ? current : { ...current, name }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryTextField
                                label={`${c.capacity} (${c.capacityHint})`}
                                editing={sectionDraft.viewOnly !== true}
                                type="number"
                                dir="ltr"
                                value={sectionDraft.capacity}
                                onChange={(capacity) => setSectionDraft((current) => (current === null ? current : { ...current, capacity }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryListField
                                label={c.homeroom}
                                editing={sectionDraft.viewOnly !== true}
                                value={sectionDraft.homeroom_teacher_id}
                                display={
                                    sectionDraft.homeroom_teacher_id === ''
                                        ? c.noHomeroom
                                        : (teacherName.get(Number(sectionDraft.homeroom_teacher_id)) ?? '—')
                                }
                                options={homeroomOptions}
                                onChange={(homeroom_teacher_id) =>
                                    setSectionDraft((current) => (current === null ? current : { ...current, homeroom_teacher_id }))
                                }
                                fieldClassName="sis-branches-field--wide"
                            />
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setSectionDraft(null)}>
                            {c.cancel}
                        </Button>
                        {sectionDraft.viewOnly ? (
                            canManage ? (
                                <Button
                                    type="button"
                                    onClick={() => setSectionDraft((current) => (current === null ? current : { ...current, viewOnly: false }))}
                                >
                                    {i18n.common.edit}
                                </Button>
                            ) : null
                        ) : (
                            <Button type="button" disabled={saving || sectionDraft.name.trim() === ''} onClick={saveSection}>
                                {saving ? i18n.common.saving : sectionDraft.id === null ? c.add : c.saveChanges}
                            </Button>
                        )}
                    </div>
                </RegistrySheetDialog>
            ) : null}

            <ConfirmDialog
                open={confirmDeactivate !== null && confirmTarget !== null}
                title={confirmDeactivate === 'section' ? c.deactivateSection : c.deactivateClass}
                description={confirmDeactivate === 'section' ? c.deactivateSectionConfirm : c.deactivateClassConfirm}
                confirmLabel={confirmDeactivate === 'section' ? c.deactivateSection : c.deactivateClass}
                tone="danger"
                confirmPending={saving}
                onConfirm={() => {
                    const done = () => setConfirmDeactivate(null);
                    if (confirmDeactivate === 'section' && selectedSection !== null) {
                        void setSectionStatus(selectedSection, false, done);
                    } else if (confirmDeactivate === 'class' && selected !== null) {
                        void setClassStatus(selected, false, done);
                    }
                }}
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setConfirmDeactivate(null);
                    }
                }}
            />
        </>
    );
}
