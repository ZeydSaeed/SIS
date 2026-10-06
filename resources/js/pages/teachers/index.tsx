import { Head, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarRange,
    CheckCircle2,
    ChevronUp,
    CircleSlash,
    Eye,
    Layers,
    Pencil,
    Plus,
    PlusCircle,
    RotateCcw,
    Save,
    Trash2,
    UserRound,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    blankToNull,
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

type Teacher = {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string;
    full_name: string;
    national_id: string | null;
    specialization_field: string | null;
    hire_date: string | null;
    status: number;
    is_primary: boolean;
    subject_ids: number[];
};

type Subject = { id: number; code: string; name: string };

type Props = {
    teachers: Teacher[];
    total: number;
    subjects: Subject[];
    filters: { academic_year_id: number | null };
    authorization: { can_manage: boolean };
};

type TeacherForm = {
    employee_code: string;
    first_name: string;
    last_name: string;
    national_id: string;
    specialization_field: string;
    hire_date: string;
};

/** TeacherStatus: 1 نشط · 2 موقوف. */
const ACTIVE = 1;
const RELOAD_PROPS = ['teachers', 'total', 'flash'];
/** GetTeacherRosterHandler::ROSTER_LIMIT */
const ROSTER_LIMIT = 500;

const EMPTY_FORM: TeacherForm = {
    employee_code: '',
    first_name: '',
    last_name: '',
    national_id: '',
    specialization_field: '',
    hire_date: '',
};

function formOf(teacher: Teacher | null): TeacherForm {
    if (teacher === null) {
        return EMPTY_FORM;
    }

    return {
        employee_code: teacher.employee_code,
        first_name: teacher.first_name,
        last_name: teacher.last_name,
        national_id: teacher.national_id ?? '',
        specialization_field: teacher.specialization_field ?? '',
        hire_date: teacher.hire_date?.slice(0, 10) ?? '',
    };
}

function StatusPill({ status }: { status: number }) {
    const i = t().teachers;

    return (
        <span className={`sis-branches-status${status === ACTIVE ? '' : ' sis-org-status--inactive'}`}>
            {status === ACTIVE ? i.statusActive : i.statusInactive}
        </span>
    );
}

function TeacherTile({ active, large = false }: { active: boolean; large?: boolean }) {
    return (
        <span
            className={`sis-branches-tile sis-branches-tile--${active ? 'computing' : 'default'}${large ? ' sis-branches-tile--lg' : ''}`}
            aria-hidden="true"
        >
            <UserRound />
        </span>
    );
}

export default function TeachersIndex(props: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ title: t().teachers.title, href: '/teachers' }];

    // Inner component: page error / flash contexts live inside AppLayout.
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <TeachersPage {...props} />
        </AppLayout>
    );
}

function TeachersPage({ teachers, total, subjects, filters, authorization }: Props) {
    const i18n = t();
    const tc = i18n.teachers;
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
    const subjectsById = useMemo(() => new Map(subjects.map((subject) => [subject.id, subject])), [subjects]);

    const [selectedId, setSelectedId] = useState<number | null>(teachers[0]?.id ?? null);
    /** A subject row of the selected teacher; when set, «حذف» unlinks it. */
    const [selectedSubjectId, setSelectedSubjectId] = useState<number | null>(null);
    const [collapsed, setCollapsed] = useState(true);
    const [form, setForm] = useState<TeacherForm>(EMPTY_FORM);
    const [saving, setSaving] = useState(false);
    const [addOpen, setAddOpen] = useState(false);
    const [newTeacher, setNewTeacher] = useState<TeacherForm>(EMPTY_FORM);
    const [assignOpen, setAssignOpen] = useState(false);
    const [assignSubjectId, setAssignSubjectId] = useState('');
    const [confirmDeactivate, setConfirmDeactivate] = useState(false);
    const [confirmUnlink, setConfirmUnlink] = useState(false);
    const [filterKey, setFilterKey] = useState<'all' | 'active' | 'inactive'>('all');
    const [query, setQuery] = useState('');
    const firstNameRef = useRef<HTMLInputElement | null>(null);

    const selected = teachers.find((teacher) => teacher.id === selectedId) ?? null;
    const selectTeacher = (teacherId: number) => {
        setSelectedId(teacherId);
        setSelectedSubjectId(null);
    };

    // Keep a valid selection after reloads (create / deactivate).
    useEffect(() => {
        if (selectedId === null || !teachers.some((teacher) => teacher.id === selectedId)) {
            setSelectedId(teachers[0]?.id ?? null);
        }
    }, [teachers, selectedId]);

    useEffect(() => {
        setForm(formOf(selected));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selected?.id, selected?.first_name, selected?.last_name, selected?.national_id, selected?.specialization_field, selected?.hire_date]);

    const activeCount = teachers.filter((teacher) => teacher.status === ACTIVE).length;
    const withSubjectsCount = teachers.filter((teacher) => teacher.subject_ids.length > 0).length;
    const visibleTeachers = useMemo(() => {
        const q = query.trim();

        return teachers.filter((teacher) => {
            if (filterKey === 'active' && teacher.status !== ACTIVE) return false;
            if (filterKey === 'inactive' && teacher.status === ACTIVE) return false;

            return (
                q === ''
                || teacher.full_name.includes(q)
                || teacher.employee_code.includes(q)
                || (teacher.specialization_field ?? '').includes(q)
            );
        });
    }, [teachers, filterKey, query]);

    const selectedForm = formOf(selected);
    const dirty =
        selected !== null
        && (Object.keys(form) as Array<keyof TeacherForm>).some((key) => form[key].trim() !== selectedForm[key].trim());
    const formValid = form.first_name.trim() !== '' && form.last_name.trim() !== '';
    const freeSubjects = subjects.filter((subject) => !(selected?.subject_ids ?? []).includes(subject.id));

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

    const saveTeacher = () =>
        selected &&
        run(() =>
            request('patch', `/teachers/${selected.id}`, {
                first_name: form.first_name.trim(),
                last_name: form.last_name.trim(),
                national_id: blankToNull(form.national_id),
                specialization_field: blankToNull(form.specialization_field),
                hire_date: blankToNull(form.hire_date),
            }),
        );

    const createTeacher = () =>
        run(
            () =>
                request('post', '/teachers', {
                    academic_year_id: yearId,
                    employee_code: newTeacher.employee_code.trim(),
                    first_name: newTeacher.first_name.trim(),
                    last_name: newTeacher.last_name.trim(),
                    national_id: blankToNull(newTeacher.national_id),
                    specialization_field: blankToNull(newTeacher.specialization_field),
                    hire_date: blankToNull(newTeacher.hire_date),
                }),
            () => {
                setAddOpen(false);
                setNewTeacher(EMPTY_FORM);
            },
        );

    const setStatus = (teacher: Teacher, active: boolean, after?: () => void) =>
        run(() => request('post', `/teachers/${teacher.id}/${active ? 'reactivate' : 'deactivate'}`), after);

    const assignSubject = () =>
        selected &&
        run(
            () => request('post', `/teachers/${selected.id}/subjects`, { subject_id: Number(assignSubjectId), academic_year_id: yearId }),
            () => {
                setAssignOpen(false);
                setAssignSubjectId('');
            },
        );

    const unlinkSubject = () =>
        selected &&
        selectedSubjectId !== null &&
        run(
            () => request('post', `/teachers/${selected.id}/subjects/unlink`, { subject_id: selectedSubjectId, academic_year_id: yearId }),
            () => {
                setConfirmUnlink(false);
                setSelectedSubjectId(null);
            },
        );

    const openAssign = () => {
        setAssignSubjectId('');
        setAssignOpen(true);
    };

    const changeYear = (next: string) => {
        if (next === '' || Number(next) === yearId) {
            return;
        }
        router.get('/teachers', { academic_year_id: Number(next) }, { preserveScroll: true });
    };

    const teachersPercent = teachers.length === 0 ? 0 : Math.round((withSubjectsCount / teachers.length) * 100);
    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const noSelection = selected === null;
        const selectedActive = selected?.status === ACTIVE;
        const groups: PageRibbonGroup[] = [
            {
                id: 'teachers-actions',
                label: i18n.common.actions,
                commands: [
                    {
                        id: 'teachers-view',
                        label: i18n.common.view,
                        icon: Eye,
                        title: noSelection ? r.needsSelection : i18n.common.view,
                        disabled: noSelection,
                        onSelect: () => setCollapsed(false),
                    },
                    ...(canManage
                        ? [
                              {
                                  id: 'teachers-edit',
                                  label: i18n.common.edit,
                                  icon: Pencil,
                                  tone: 'edit' as const,
                                  disabled: noSelection || saving,
                                  onSelect: () => {
                                      setCollapsed(false);
                                      window.requestAnimationFrame(() => firstNameRef.current?.focus());
                                  },
                              },
                              {
                                  id: 'teachers-save',
                                  label: i18n.common.save,
                                  icon: Save,
                                  tone: 'save' as const,
                                  disabled: saving || !dirty || !formValid,
                                  onSelect: () => void saveTeacher(),
                              },
                          ]
                        : []),
                    {
                        id: 'teachers-cancel',
                        label: i18n.common.cancel,
                        icon: XCircle,
                        disabled: noSelection || saving,
                        onSelect: () => {
                            if (selectedSubjectId !== null) {
                                setSelectedSubjectId(null);

                                return;
                            }
                            setForm(formOf(selected));
                            setCollapsed(true);
                        },
                    },
                    ...(canManage
                        ? [
                              selectedActive || noSelection || selectedSubjectId !== null
                                  ? {
                                        id: 'teachers-delete',
                                        label: i18n.common.delete,
                                        icon: Trash2,
                                        tone: 'delete' as const,
                                        title: selectedSubjectId !== null ? tc.unlinkSubject : tc.deactivate,
                                        disabled: noSelection || saving,
                                        onSelect: () => (selectedSubjectId !== null ? setConfirmUnlink(true) : setConfirmDeactivate(true)),
                                    }
                                  : {
                                        id: 'teachers-reactivate',
                                        label: tc.reactivate,
                                        icon: RotateCcw,
                                        disabled: saving,
                                        onSelect: () => selected && void setStatus(selected, true),
                                    },
                          ]
                        : []),
                ],
            },
            {
                id: 'teachers-filters',
                label: tc.filters,
                commands: [
                    { key: 'all' as const, label: tc.all, icon: Layers, count: teachers.length },
                    { key: 'active' as const, label: tc.active, icon: CheckCircle2, count: activeCount },
                    { key: 'inactive' as const, label: tc.inactive, icon: CircleSlash, count: teachers.length - activeCount },
                ].map((tab) => ({
                    id: `teachers-filter-${tab.key}`,
                    label: tab.label,
                    title: `${tab.label} (${tab.count})`,
                    icon: tab.icon,
                    count: tab.count,
                    pressed: filterKey === tab.key,
                    onSelect: () => setFilterKey(filterKey === tab.key && tab.key !== 'all' ? 'all' : tab.key),
                })),
            },
            {
                id: 'teachers-year',
                label: tc.academicYear,
                commands: [],
                custom: (
                    <div className="sis-ribbon__filters" aria-label={tc.academicYear} dir="rtl">
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
                                ariaLabel={tc.academicYear}
                            />
                        </div>
                    </div>
                ),
            },
        ];
        if (canManage) {
            groups.push({
                id: 'teachers-manage',
                label: r.manage,
                commands: [
                    { id: 'teachers-add', label: tc.addTeacher, icon: PlusCircle, disabled: yearId === null, onSelect: () => setAddOpen(true) },
                    {
                        id: 'teachers-assign-subject',
                        label: tc.assignSubject,
                        icon: BookOpen,
                        disabled: noSelection || yearId === null,
                        onSelect: openAssign,
                    },
                ],
            });
        }
        groups.push({
            id: 'teachers-progress',
            label: r.progress,
            commands: [],
            custom: (
                <div
                    className="sis-ribbon__progress-track"
                    role="progressbar"
                    aria-label={tc.progress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={teachersPercent}
                    data-contrast={teachersPercent >= 45 ? 'light' : 'dark'}
                    dir="rtl"
                    title={`${tc.progress}: ${teachersPercent}%`}
                >
                    <span className="sis-ribbon__progress-fill" style={{ width: `${teachersPercent}%` }} />
                    <span className="sis-ribbon__progress-value" dir="ltr">
                        {teachersPercent}%
                    </span>
                </div>
            ),
        });

        return groups;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activeCount, canManage, dirty, filterKey, formValid, i18n, saving, selected, selectedSubjectId, teachers, teachersPercent, years, yearId]);
    useRegisterPageRibbon('edit', editRibbonGroups);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: query,
            label: tc.search,
            placeholder: tc.search,
            onDraftChange: setQuery,
            onCommit: setQuery,
        }),
        [query, tc.search],
    );
    useRegisterPageTitlebarSearch(titlebarSearch);

    const formFields = (
        value: TeacherForm,
        onChange: (next: TeacherForm) => void,
        editing: boolean,
        codeEditing: boolean,
        firstRef?: typeof firstNameRef,
    ) => (
        <>
            <RegistryTextField
                label={tc.code}
                editing={codeEditing}
                required
                dir="ltr"
                value={value.employee_code}
                onChange={(employee_code) => onChange({ ...value, employee_code })}
            />
            <RegistryTextField
                label={tc.firstName}
                inputRef={firstRef}
                editing={editing}
                required
                value={value.first_name}
                onChange={(first_name) => onChange({ ...value, first_name })}
            />
            <RegistryTextField
                label={tc.lastName}
                editing={editing}
                required
                value={value.last_name}
                onChange={(last_name) => onChange({ ...value, last_name })}
            />
            <RegistryTextField
                label={tc.nationalId}
                editing={editing}
                dir="ltr"
                value={value.national_id}
                onChange={(national_id) => onChange({ ...value, national_id })}
            />
            <RegistryTextField
                label={tc.specialization}
                editing={editing}
                value={value.specialization_field}
                onChange={(specialization_field) => onChange({ ...value, specialization_field })}
            />
            <RegistryTextField
                label={tc.hireDate}
                editing={editing}
                type="date"
                dir="ltr"
                value={value.hire_date}
                onChange={(hire_date) => onChange({ ...value, hire_date })}
            />
        </>
    );

    return (
        <>
            <Head title={tc.title} />
            <div className="sis-ops-hub sis-branches-page sis-org-page" dir="rtl" lang="ar">
                <header className="sis-branches-page__head">
                    <div className="sis-branches-page__heading sis-branches-page__heading--centered">
                        <h1 className="sis-branches-page__title">
                            {tc.heading}
                            {schoolName !== null ? (
                                <>
                                    <span className="sis-branches-page__title-sep" aria-hidden="true">
                                        {' · '}
                                    </span>
                                    <span className="sis-branches-page__title-school">
                                        {tc.school}: {schoolName}
                                    </span>
                                </>
                            ) : null}
                        </h1>
                    </div>
                </header>
                {canManage ? null : <p className="sis-branches-page__notice">{tc.readOnly}</p>}
                {yearId === null ? <p className="sis-branches-page__notice">{tc.noYear}</p> : null}
                {total > teachers.length ? (
                    <p className="sis-branches-page__notice">
                        {tc.truncated.replace('{limit}', String(ROSTER_LIMIT)).replace('{total}', String(total))}
                    </p>
                ) : null}

                <div className="sis-branches-page__grid">
                    {/* Teachers list */}
                    <section className="sis-branches-card sis-branches-list" aria-label={tc.listTitle}>
                        <div className="sis-branches-card__head">
                            <h2 className="sis-branches-card__title">{tc.listTitle}</h2>
                            <span className="sis-branches-count" dir="ltr">
                                {teachers.length}
                            </span>
                        </div>
                        <div className="sis-branches-table sis-branches-table--branches" role="table" aria-label={tc.listTitle}>
                            <div className="sis-branches-table__row sis-branches-table__row--head" role="row">
                                <span role="columnheader">{tc.name}</span>
                            </div>
                            {visibleTeachers.length === 0 ? (
                                <div className="sis-branches-table__row" role="row">
                                    <span role="cell" className="sis-branches-empty">
                                        {teachers.length === 0 ? tc.noTeachers : tc.noSearchResult}
                                    </span>
                                </div>
                            ) : (
                                visibleTeachers.map((teacher) => (
                                    <div
                                        key={teacher.id}
                                        className={`sis-branches-table__row sis-branches-table__row--branch${
                                            teacher.id === selectedId ? ' is-selected' : ''
                                        }${teacher.status === ACTIVE ? '' : ' sis-org-item--inactive'}`}
                                        role="row"
                                        tabIndex={0}
                                        aria-selected={teacher.id === selectedId}
                                        onClick={() => selectTeacher(teacher.id)}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter' || event.key === ' ') {
                                                event.preventDefault();
                                                selectTeacher(teacher.id);
                                            }
                                        }}
                                    >
                                        <span role="cell" className="sis-branches-table__name">
                                            <TeacherTile active={teacher.status === ACTIVE} />
                                            <span className="sis-branches-item__text">
                                                <span className="sis-branches-item__name">
                                                    {teacher.full_name}
                                                    {teacher.status === ACTIVE ? null : <StatusPill status={teacher.status} />}
                                                </span>
                                                <span className="sis-branches-item__meta">
                                                    <span dir="ltr">{teacher.employee_code}</span>
                                                    {teacher.specialization_field ? ` · ${teacher.specialization_field}` : null}
                                                    {` · ${teacher.subject_ids.length} ${tc.subjectsCount}`}
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
                                {tc.addTeacher}
                            </button>
                        ) : null}
                    </section>

                    {/* Selected teacher */}
                    <section className="sis-branches-card sis-branches-detail" aria-label={selected?.full_name ?? tc.listTitle}>
                        {selected === null ? (
                            <p className="sis-branches-empty">{tc.selectTeacher}</p>
                        ) : (
                            <>
                                <div className="sis-branches-card__head sis-branches-detail__head">
                                    <TeacherTile active={selected.status === ACTIVE} large />
                                    <h2 className="sis-branches-card__title">{selected.full_name}</h2>
                                    <StatusPill status={selected.status} />
                                    <button
                                        type="button"
                                        className="sis-branches-icon-btn sis-branches-icon-btn--plain sis-branches-detail__toggle"
                                        aria-expanded={!collapsed}
                                        title={collapsed ? tc.expand : tc.collapse}
                                        aria-label={collapsed ? tc.expand : tc.collapse}
                                        onClick={() => setCollapsed((value) => !value)}
                                    >
                                        <ChevronUp aria-hidden className={collapsed ? 'is-collapsed' : undefined} />
                                    </button>
                                </div>

                                {collapsed ? null : (
                                    <div className="sis-branches-detail__form">
                                        {formFields(form, setForm, canManage, false, firstNameRef)}
                                        {canManage ? (
                                            <div className="sis-branches-detail__buttons">
                                                <Button type="button" disabled={!dirty || !formValid || saving} onClick={() => void saveTeacher()}>
                                                    {saving ? i18n.common.saving : tc.saveChanges}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={!dirty || saving}
                                                    onClick={() => setForm(formOf(selected))}
                                                >
                                                    {tc.cancel}
                                                </Button>
                                            </div>
                                        ) : null}
                                    </div>
                                )}

                                <div className="sis-branches-table" role="table" aria-label={tc.subjectsTitle}>
                                    <div className="sis-branches-table__row sis-branches-table__row--head" role="row">
                                        <span role="columnheader" className="sis-branches-table__heading">
                                            {tc.subjectsTitle}
                                            <span className="sis-branches-count" dir="ltr">
                                                {selected.subject_ids.length}
                                            </span>
                                        </span>
                                    </div>
                                    {selected.subject_ids.length === 0 ? (
                                        <div className="sis-branches-table__row" role="row">
                                            <span role="cell" className="sis-branches-empty">
                                                {tc.noSubjects}
                                            </span>
                                        </div>
                                    ) : (
                                        selected.subject_ids.map((subjectId) => {
                                            const subject = subjectsById.get(subjectId);

                                            return (
                                                <div
                                                    key={subjectId}
                                                    className={`sis-branches-table__row sis-branches-table__row--selectable${
                                                        subjectId === selectedSubjectId ? ' is-selected' : ''
                                                    }`}
                                                    role="row"
                                                    tabIndex={0}
                                                    aria-selected={subjectId === selectedSubjectId}
                                                    onClick={() => setSelectedSubjectId(subjectId)}
                                                    onKeyDown={(event) => {
                                                        if (event.key === 'Enter' || event.key === ' ') {
                                                            event.preventDefault();
                                                            setSelectedSubjectId(subjectId);
                                                        }
                                                    }}
                                                >
                                                    <span role="cell" className="sis-branches-table__name">
                                                        <BookOpen aria-hidden className="sis-branches-table__icon" />
                                                        {subject?.name ?? `#${subjectId}`}
                                                        {subject ? (
                                                            <span className="sis-branches-item__meta" dir="ltr">
                                                                {subject.code}
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                </div>
                                            );
                                        })
                                    )}
                                </div>
                                {canManage && yearId !== null ? (
                                    <button type="button" className="sis-branches-add-row" onClick={openAssign}>
                                        <Plus aria-hidden />
                                        {tc.assignSubjectTo}
                                    </button>
                                ) : null}
                            </>
                        )}
                    </section>
                </div>
            </div>

            {/* 1) Add teacher */}
            {addOpen ? (
                <RegistrySheetDialog title={tc.addTeacher} className="sis-branches-sheet" onClose={() => setAddOpen(false)}>
                    <SheetSection id="teachers-add" title={tc.addTeacher}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            {formFields(newTeacher, setNewTeacher, true, true)}
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setAddOpen(false)}>
                            {tc.cancel}
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                saving
                                || newTeacher.employee_code.trim() === ''
                                || newTeacher.first_name.trim() === ''
                                || newTeacher.last_name.trim() === ''
                            }
                            onClick={() => void createTeacher()}
                        >
                            {saving ? i18n.common.saving : tc.add}
                        </Button>
                    </div>
                </RegistrySheetDialog>
            ) : null}

            {/* 2) Assign a subject to the selected teacher */}
            {assignOpen && selected !== null ? (
                <RegistrySheetDialog title={tc.assignSubjectTo} className="sis-branches-sheet" onClose={() => setAssignOpen(false)}>
                    <SheetSection id="teachers-assign-subject" title={selected.full_name}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            {freeSubjects.length === 0 ? (
                                <p className="sis-branches-empty sis-branches-field--wide">{tc.noFreeSubjects}</p>
                            ) : (
                                <RegistryListField
                                    label={tc.subject}
                                    editing
                                    required
                                    value={assignSubjectId}
                                    display={subjectsById.get(Number(assignSubjectId))?.name ?? '—'}
                                    options={freeSubjects.map((subject) => ({ value: String(subject.id), label: `${subject.name} (${subject.code})` }))}
                                    onChange={setAssignSubjectId}
                                    fieldClassName="sis-branches-field--wide"
                                />
                            )}
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setAssignOpen(false)}>
                            {tc.cancel}
                        </Button>
                        <Button type="button" disabled={saving || assignSubjectId === ''} onClick={() => void assignSubject()}>
                            {saving ? i18n.common.saving : tc.assignSubject}
                        </Button>
                    </div>
                </RegistrySheetDialog>
            ) : null}

            <ConfirmDialog
                open={confirmDeactivate && selected !== null}
                title={tc.deactivate}
                description={tc.deactivateConfirm}
                confirmLabel={tc.deactivate}
                tone="danger"
                confirmPending={saving}
                onConfirm={() => selected && void setStatus(selected, false, () => setConfirmDeactivate(false))}
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setConfirmDeactivate(false);
                    }
                }}
            />
            <ConfirmDialog
                open={confirmUnlink && selectedSubjectId !== null}
                title={tc.unlinkSubject}
                description={tc.unlinkSubjectConfirm}
                confirmLabel={tc.unlinkSubject}
                tone="danger"
                confirmPending={saving}
                onConfirm={() => void unlinkSubject()}
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setConfirmUnlink(false);
                    }
                }}
            />
        </>
    );
}
