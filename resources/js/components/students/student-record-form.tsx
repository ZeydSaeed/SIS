import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { router, usePage } from '@inertiajs/react';
import { StudentStatusBadge } from '@/components/students/student-status-badge';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { SisListSelect } from '@/components/sis/sis-list-select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/i18n';

export type StudentRecordFormValues = {
    id: number;
    student_code: string;
    first_name: string;
    father_name?: string | null;
    grandfather_name?: string | null;
    great_grandfather_name?: string | null;
    last_name: string;
    mother_name?: string | null;
    maternal_father_name?: string | null;
    maternal_grandfather_name?: string | null;
    guardian_triple_name?: string | null;
    governorate?: string | null;
    neighborhood?: string | null;
    locality?: string | null;
    house_number?: string | null;
    birth_date: string;
    birth_place?: string | null;
    registration_place?: string | null;
    gender: number;
    nationality?: string | null;
    religion: number;
    mawalid_date?: string | null;
    national_id?: string | null;
    previous_school_name?: string | null;
    transfer_document_number?: number | null;
    transfer_document_date?: string | null;
    school_start_date?: string | null;
    admitted_class_name?: string | null;
    notes?: string | null;
    mobile?: string | null;
    guardian_mobile?: string | null;
    email?: string | null;
    school_name?: string | null;
    branch_id?: number | null;
    branch_name?: string | null;
    department_name?: string | null;
    specialization_name?: string | null;
    stage_name?: string | null;
    section_name?: string | null;
    academic_year_id?: number | null;
    academic_year_name?: string | null;
    academic_year_code?: string | null;
    status: number;
};

type PlacementOptions = {
    branches: Array<{ id: number; name: string }>;
    departments: Array<{ id: number; branch_id: number | null; name: string }>;
};

type StudentRecordFormProps = {
    student: StudentRecordFormValues;
    canViewPii: boolean;
    canUpdate: boolean;
    placementOptions?: PlacementOptions;
    onSaved?: (student: StudentRecordFormValues) => void;
};

type StudentViewDialogProps = {
    students: StudentRecordFormValues[];
    canViewPii: boolean;
    canUpdate?: boolean;
    placementOptions?: PlacementOptions;
    onClose: () => void;
    onSaved?: (student: StudentRecordFormValues) => void;
};

type DraftState = {
    first_name: string;
    father_name: string;
    grandfather_name: string;
    great_grandfather_name: string;
    last_name: string;
    mother_name: string;
    maternal_father_name: string;
    maternal_grandfather_name: string;
    guardian_triple_name: string;
    governorate: string;
    neighborhood: string;
    locality: string;
    house_number: string;
    birth_date: string;
    birth_place: string;
    registration_place: string;
    gender: number;
    nationality: string;
    religion: number;
    mawalid_date: string;
    national_id: string;
    previous_school_name: string;
    transfer_document_number: string;
    transfer_document_date: string;
    school_start_date: string;
    admitted_class_name: string;
    notes: string;
    mobile: string;
    guardian_mobile: string;
    email: string;
    school_name: string;
    branch_id: string;
    department_name: string;
    specialization_name: string;
    stage_name: string;
    section_name: string;
};

function emptyToNull(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function isoDate(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const match = /^(\d{4}-\d{2}-\d{2})/.exec(value);

    return match ? match[1] : value;
}

function displayValue(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function displayAcademicYear(student: StudentRecordFormValues): string {
    const formatted = formatAcademicYearOptionLabel(
        student.academic_year_name ?? '',
        student.academic_year_code ?? '',
    );

    return formatted === '' ? '—' : formatted;
}

function formatCivilDate(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return value;
    }

    return `${match[2]}/${match[3]}/${match[1]}`;
}

function studentQuadName(student: Pick<
    StudentRecordFormValues,
    'first_name' | 'father_name' | 'grandfather_name' | 'great_grandfather_name' | 'last_name'
>): string {
    return [
        student.first_name,
        student.father_name,
        student.grandfather_name,
        student.great_grandfather_name,
        student.last_name,
    ]
        .map((part) => part?.trim() ?? '')
        .filter((part) => part !== '')
        .join(' ');
}

function draftFromStudent(student: StudentRecordFormValues): DraftState {
    return {
        first_name: student.first_name,
        father_name: student.father_name ?? '',
        grandfather_name: student.grandfather_name ?? '',
        great_grandfather_name: student.great_grandfather_name ?? '',
        last_name: student.last_name,
        mother_name: student.mother_name ?? '',
        maternal_father_name: student.maternal_father_name ?? '',
        maternal_grandfather_name: student.maternal_grandfather_name ?? '',
        guardian_triple_name: student.guardian_triple_name ?? '',
        governorate: student.governorate ?? '',
        neighborhood: student.neighborhood ?? '',
        locality: student.locality ?? '',
        house_number: student.house_number ?? '',
        birth_date: isoDate(student.birth_date),
        birth_place: student.birth_place ?? '',
        registration_place: student.registration_place ?? '',
        gender: student.gender,
        nationality: student.nationality ?? '',
        religion: student.religion,
        mawalid_date: isoDate(student.mawalid_date),
        national_id: student.national_id ?? '',
        previous_school_name: student.previous_school_name ?? '',
        transfer_document_number:
            student.transfer_document_number === null || student.transfer_document_number === undefined
                ? ''
                : String(student.transfer_document_number),
        transfer_document_date: isoDate(student.transfer_document_date),
        school_start_date: isoDate(student.school_start_date),
        admitted_class_name: student.admitted_class_name ?? '',
        notes: student.notes ?? '',
        mobile: student.mobile ?? '',
        guardian_mobile: student.guardian_mobile ?? '',
        email: student.email ?? '',
        school_name: student.school_name ?? '',
        branch_id: student.branch_id ? String(student.branch_id) : '',
        department_name: student.department_name ?? '',
        specialization_name: student.specialization_name ?? '',
        stage_name: student.stage_name ?? '',
        section_name: student.section_name ?? '',
    };
}

function controlClass(filled: boolean): string {
    return `sis-ops-hub__link sis-admission-draft-control${filled ? ' sis-admission-draft-field--filled' : ''}`;
}

function StatusLikeButton({
    children,
    tone,
    disabled = false,
    onClick,
}: {
    children: string;
    tone: 'edit' | 'close';
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            className={
                tone === 'close'
                    ? 'sis-student-record-form__action sis-student-record-form__action--close'
                    : 'sis-student-record-form__action sis-student-record-form__action--edit'
            }
            disabled={disabled}
            onMouseDown={(event) => event.preventDefault()}
            onClick={() => {
                onClick();
                requestAnimationFrame(() => {
                    if (document.activeElement instanceof HTMLElement) {
                        document.activeElement.blur();
                    }
                });
            }}
        >
            {children}
        </button>
    );
}

function isFilled(value: string | number | null | undefined): boolean {
    if (value === null || value === undefined) {
        return false;
    }

    const text = String(value).trim();

    return text !== '' && text !== '—';
}

function AcademicYearListField({
    label,
    student,
}: {
    label: string;
    student: StudentRecordFormValues;
}) {
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const years = academicYears ?? [];
    const selectedId = student.academic_year_id ?? null;
    const value = selectedId === null ? '' : String(selectedId);
    const options = years.map((year) => ({
        value: String(year.id),
        label: formatAcademicYearOptionLabel(year.name, year.code),
    }));

    if (
        selectedId !== null &&
        !options.some((option) => option.value === String(selectedId))
    ) {
        options.unshift({
            value: String(selectedId),
            label: displayAcademicYear(student),
        });
    }

    if (options.length === 0) {
        options.push({
            value: value || 'none',
            label: displayAcademicYear(student),
        });
    }

    const filled = isFilled(displayAcademicYear(student));

    return (
        <label className="flex flex-col gap-1 text-sm">
            <span>{label}</span>
            <div className={`${controlClass(filled)} sis-student-record-form__year-list`}>
                <SisListSelect
                    value={value}
                    options={options}
                    onChange={() => undefined}
                    triggerClassName="sis-student-record-form__value"
                    dir="ltr"
                    ariaLabel={label}
                />
            </div>
        </label>
    );
}

function DraftField({
    label,
    editing,
    value,
    display,
    type = 'text',
    dir = 'rtl',
    onChange,
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    type?: 'text' | 'date' | 'email';
    dir?: 'ltr' | 'rtl';
    onChange: (value: string) => void;
}) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span>{label}</span>
            <div
                className={`${controlClass(isFilled(editing ? value : display))}${editing ? '' : ' sis-admission-draft-readonly'}`}
                dir={dir}
            >
                {editing ? (
                    <input
                        type={type}
                        dir={dir}
                        className="sis-student-record-form__value"
                        value={value}
                        placeholder=" "
                        aria-label={label}
                        onChange={(event) => onChange(event.target.value)}
                    />
                ) : (
                    display
                )}
            </div>
        </label>
    );
}

function DraftSelect({
    label,
    editing,
    value,
    display,
    onChange,
    options,
}: {
    label: string;
    editing: boolean;
    value: number;
    display: string;
    onChange: (value: number) => void;
    options: Array<{ value: number; label: string }>;
}) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span>{label}</span>
            <div
                className={`${controlClass(isFilled(editing ? value : display))}${editing ? '' : ' sis-admission-draft-readonly'}`}
            >
                {editing ? (
                    <SisListSelect
                        value={String(value)}
                        options={options.map((option) => ({
                            value: String(option.value),
                            label: option.label,
                        }))}
                        onChange={(next) => onChange(Number(next))}
                        triggerClassName="sis-student-record-form__value"
                        dir="rtl"
                        ariaLabel={label}
                    />
                ) : (
                    display
                )}
            </div>
        </label>
    );
}

function DraftOptionalSelect({
    label,
    editing,
    value,
    display,
    onChange,
    options,
    emptyLabel,
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    onChange: (value: string) => void;
    options: Array<{ value: string; label: string }>;
    emptyLabel: string;
}) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span>{label}</span>
            <div
                className={`${controlClass(isFilled(editing ? value : display))}${editing ? '' : ' sis-admission-draft-readonly'}`}
            >
                {editing ? (
                    <SisListSelect
                        value={value}
                        options={[{ value: '', label: emptyLabel }, ...options]}
                        onChange={onChange}
                        triggerClassName="sis-student-record-form__value"
                        dir="rtl"
                        ariaLabel={label}
                    />
                ) : (
                    display
                )}
            </div>
        </label>
    );
}

function DraftNotes({
    label,
    editing,
    value,
    display,
    onChange,
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    onChange: (value: string) => void;
}) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span>{label}</span>
            <div
                className={`${controlClass(isFilled(editing ? value : display))} sis-student-record-form__notes${editing ? '' : ' sis-admission-draft-readonly'}`}
            >
                {editing ? (
                    <textarea
                        className="sis-student-record-form__value"
                        aria-label={label}
                        value={value}
                        placeholder=" "
                        rows={1}
                        onChange={(event) => onChange(event.target.value)}
                    />
                ) : (
                    display
                )}
            </div>
        </label>
    );
}

function FormSection({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="sis-student-record-form__section" aria-labelledby={id}>
            <h4 id={id} className="sis-student-record-form__section-title">
                {title}
            </h4>
            {children}
        </section>
    );
}

export function StudentRecordForm({
    student,
    canViewPii,
    canUpdate,
    placementOptions = { branches: [], departments: [] },
    onSaved,
}: StudentRecordFormProps) {
    const i18n = t();
    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);
    const [draft, setDraft] = useState<DraftState>(() => draftFromStudent(student));

    useEffect(() => {
        if (editing) {
            return;
        }

        setDraft(draftFromStudent(student));
    }, [editing, student]);

    const filteredDepartments = useMemo(() => {
        if (draft.branch_id === '') {
            return placementOptions.departments;
        }

        return placementOptions.departments.filter(
            (department) =>
                department.branch_id === null
                || String(department.branch_id) === draft.branch_id,
        );
    }, [draft.branch_id, placementOptions.departments]);

    const branchDisplay =
        student.branch_name
        ?? placementOptions.branches.find((item) => item.id === student.branch_id)?.name
        ?? displayValue(student.branch_id);
    const name = studentQuadName(editing ? draft : student);
    const genderLabel =
        (editing ? draft.gender : student.gender) === 1
            ? i18n.students.male
            : (editing ? draft.gender : student.gender) === 2
              ? i18n.students.female
              : String(editing ? draft.gender : student.gender);
    const religionValue = editing ? draft.religion : student.religion;
    const religionLabel =
        religionValue === 1
            ? i18n.students.religionMuslim
            : religionValue === 2
              ? i18n.students.religionChristian
              : religionValue === 3
                ? i18n.students.religionOther
                : String(religionValue);

    const setField = <K extends keyof DraftState>(key: K, value: DraftState[K]) => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    const save = () => {
        if (saving) {
            return;
        }

        setSaving(true);
        const payload: Record<string, string | number | null> = {
            first_name: draft.first_name.trim(),
            last_name: draft.last_name.trim(),
            father_name: emptyToNull(draft.father_name),
            grandfather_name: emptyToNull(draft.grandfather_name),
            great_grandfather_name: emptyToNull(draft.great_grandfather_name),
            mother_name: emptyToNull(draft.mother_name),
            maternal_father_name: emptyToNull(draft.maternal_father_name),
            maternal_grandfather_name: emptyToNull(draft.maternal_grandfather_name),
            guardian_triple_name: emptyToNull(draft.guardian_triple_name),
            governorate: emptyToNull(draft.governorate),
            neighborhood: emptyToNull(draft.neighborhood),
            locality: emptyToNull(draft.locality),
            house_number: emptyToNull(draft.house_number),
            birth_date: draft.birth_date,
            birth_place: emptyToNull(draft.birth_place),
            registration_place: emptyToNull(draft.registration_place),
            gender: draft.gender,
            nationality: emptyToNull(draft.nationality),
            religion: draft.religion,
            mawalid_date: emptyToNull(draft.mawalid_date),
            previous_school_name: emptyToNull(draft.previous_school_name),
            transfer_document_number: emptyToNull(draft.transfer_document_number)
                ? Number(draft.transfer_document_number)
                : null,
            transfer_document_date: emptyToNull(draft.transfer_document_date),
            school_start_date: emptyToNull(draft.school_start_date),
            admitted_class_name: emptyToNull(draft.admitted_class_name),
            notes: emptyToNull(draft.notes),
            school_name: emptyToNull(draft.school_name),
            branch_id: draft.branch_id === '' ? null : Number(draft.branch_id),
            department_name: emptyToNull(draft.department_name),
            specialization_name: emptyToNull(draft.specialization_name),
            stage_name: emptyToNull(draft.stage_name),
            section_name: emptyToNull(draft.section_name),
        };

        if (canViewPii) {
            payload.national_id = emptyToNull(draft.national_id);
            payload.mobile = emptyToNull(draft.mobile);
            payload.guardian_mobile = emptyToNull(draft.guardian_mobile);
            payload.email = emptyToNull(draft.email);
        }

        router.put(`/students/${student.id}`, payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                const next: StudentRecordFormValues = {
                    ...student,
                    first_name: draft.first_name.trim(),
                    last_name: draft.last_name.trim(),
                    father_name: emptyToNull(draft.father_name),
                    grandfather_name: emptyToNull(draft.grandfather_name),
                    great_grandfather_name: emptyToNull(draft.great_grandfather_name),
                    mother_name: emptyToNull(draft.mother_name),
                    maternal_father_name: emptyToNull(draft.maternal_father_name),
                    maternal_grandfather_name: emptyToNull(draft.maternal_grandfather_name),
                    guardian_triple_name: emptyToNull(draft.guardian_triple_name),
                    governorate: emptyToNull(draft.governorate),
                    neighborhood: emptyToNull(draft.neighborhood),
                    locality: emptyToNull(draft.locality),
                    house_number: emptyToNull(draft.house_number),
                    birth_date: draft.birth_date,
                    birth_place: emptyToNull(draft.birth_place),
                    registration_place: emptyToNull(draft.registration_place),
                    gender: draft.gender,
                    nationality: emptyToNull(draft.nationality),
                    religion: draft.religion,
                    mawalid_date: emptyToNull(draft.mawalid_date),
                    previous_school_name: emptyToNull(draft.previous_school_name),
                    transfer_document_number: emptyToNull(draft.transfer_document_number)
                        ? Number(draft.transfer_document_number)
                        : null,
                    transfer_document_date: emptyToNull(draft.transfer_document_date),
                    school_start_date: emptyToNull(draft.school_start_date),
                    admitted_class_name: emptyToNull(draft.admitted_class_name),
                    notes: emptyToNull(draft.notes),
                    school_name: emptyToNull(draft.school_name),
                    branch_id: draft.branch_id === '' ? null : Number(draft.branch_id),
                    branch_name:
                        placementOptions.branches.find(
                            (item) => String(item.id) === draft.branch_id,
                        )?.name ?? null,
                    department_name: emptyToNull(draft.department_name),
                    specialization_name: emptyToNull(draft.specialization_name),
                    stage_name: emptyToNull(draft.stage_name),
                    section_name: emptyToNull(draft.section_name),
                    national_id: canViewPii ? emptyToNull(draft.national_id) : student.national_id,
                    mobile: canViewPii ? emptyToNull(draft.mobile) : student.mobile,
                    guardian_mobile: canViewPii
                        ? emptyToNull(draft.guardian_mobile)
                        : student.guardian_mobile,
                    email: canViewPii ? emptyToNull(draft.email) : student.email,
                };
                setEditing(false);
                onSaved?.(next);
            },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <article className="sis-admission-draft-form sis-student-record-form" dir="rtl" lang="ar">
            <header className="sis-student-record-form__head">
                <h3 className="sis-student-record-form__title">{name}</h3>
                <div className="sis-student-record-form__head-meta">
                    <StudentStatusBadge status={student.status} />
                    {canUpdate ? (
                        editing ? (
                            <StatusLikeButton tone="edit" disabled={saving} onClick={save}>
                                {saving ? i18n.common.saving : i18n.common.save}
                            </StatusLikeButton>
                        ) : (
                            <StatusLikeButton tone="edit" onClick={() => setEditing(true)}>
                                {i18n.common.edit}
                            </StatusLikeButton>
                        )
                    ) : null}
                </div>
            </header>

            <div className="sis-student-record-form__sections">
                <FormSection
                    id={`student-personal-${student.id}`}
                    title={i18n.students.personalSection}
                >
                    <div className="sis-admission-draft-rows">
                        <div className="sis-admission-draft-row">
                            <DraftField
                                label={i18n.students.firstName}
                                editing={editing}
                                value={draft.first_name}
                                display={displayValue(student.first_name)}
                                onChange={(value) => setField('first_name', value)}
                            />
                            <DraftField
                                label={i18n.students.fatherName}
                                editing={editing}
                                value={draft.father_name}
                                display={displayValue(student.father_name)}
                                onChange={(value) => setField('father_name', value)}
                            />
                            <DraftField
                                label={i18n.students.grandfatherName}
                                editing={editing}
                                value={draft.grandfather_name}
                                display={displayValue(student.grandfather_name)}
                                onChange={(value) => setField('grandfather_name', value)}
                            />
                            <DraftField
                                label={i18n.students.greatGrandfatherName}
                                editing={editing}
                                value={draft.great_grandfather_name}
                                display={displayValue(student.great_grandfather_name)}
                                onChange={(value) => setField('great_grandfather_name', value)}
                            />
                        </div>
                        <div className="sis-admission-draft-row">
                            <DraftField
                                label={i18n.students.familyName}
                                editing={editing}
                                value={draft.last_name}
                                display={displayValue(student.last_name)}
                                onChange={(value) => setField('last_name', value)}
                            />
                            <DraftField
                                label={i18n.students.motherName}
                                editing={editing}
                                value={draft.mother_name}
                                display={displayValue(student.mother_name)}
                                onChange={(value) => setField('mother_name', value)}
                            />
                            <DraftField
                                label={i18n.students.maternalFatherName}
                                editing={editing}
                                value={draft.maternal_father_name}
                                display={displayValue(student.maternal_father_name)}
                                onChange={(value) => setField('maternal_father_name', value)}
                            />
                            <DraftField
                                label={i18n.students.maternalGrandfatherName}
                                editing={editing}
                                value={draft.maternal_grandfather_name}
                                display={displayValue(student.maternal_grandfather_name)}
                                onChange={(value) => setField('maternal_grandfather_name', value)}
                            />
                        </div>
                        <div className="sis-admission-draft-row">
                            <DraftField
                                label={i18n.students.birthDate}
                                editing={editing}
                                type="date"
                                value={draft.birth_date}
                                display={formatCivilDate(student.birth_date)}
                                onChange={(value) => setField('birth_date', value)}
                            />
                            <DraftField
                                label={i18n.students.birthPlace}
                                editing={editing}
                                value={draft.birth_place}
                                display={displayValue(student.birth_place)}
                                onChange={(value) => setField('birth_place', value)}
                            />
                            <DraftSelect
                                label={i18n.students.gender}
                                editing={editing}
                                value={draft.gender}
                                display={genderLabel}
                                onChange={(value) => setField('gender', value)}
                                options={[
                                    { value: 1, label: i18n.students.male },
                                    { value: 2, label: i18n.students.female },
                                ]}
                            />
                            <DraftField
                                label={i18n.students.nationality}
                                editing={editing}
                                value={draft.nationality}
                                display={displayValue(student.nationality)}
                                onChange={(value) => setField('nationality', value)}
                            />
                        </div>
                        <div className="sis-admission-draft-row">
                            <DraftSelect
                                label={i18n.students.religion}
                                editing={editing}
                                value={draft.religion}
                                display={religionLabel}
                                onChange={(value) => setField('religion', value)}
                                options={[
                                    { value: 1, label: i18n.students.religionMuslim },
                                    { value: 2, label: i18n.students.religionChristian },
                                    { value: 3, label: i18n.students.religionOther },
                                ]}
                            />
                            {canViewPii ? (
                                <DraftField
                                    label={i18n.students.nationalId}
                                    editing={editing}
                                    value={draft.national_id}
                                    display={displayValue(student.national_id)}
                                    dir="ltr"
                                    onChange={(value) => setField('national_id', value)}
                                />
                            ) : null}
                            <DraftField
                                label={i18n.students.mawalidDate}
                                editing={editing}
                                type="date"
                                value={draft.mawalid_date}
                                display={formatCivilDate(student.mawalid_date)}
                                onChange={(value) => setField('mawalid_date', value)}
                            />
                            <DraftField
                                label={i18n.students.registrationPlace}
                                editing={editing}
                                value={draft.registration_place}
                                display={displayValue(student.registration_place)}
                                onChange={(value) => setField('registration_place', value)}
                            />
                        </div>
                    </div>
                </FormSection>

                <div className="sis-student-record-form__stack">
                    <FormSection
                        id={`student-address-${student.id}`}
                        title={i18n.students.addressSection}
                    >
                        <div className="sis-admission-draft-row">
                            <DraftField
                                label={i18n.students.governorate}
                                editing={editing}
                                value={draft.governorate}
                                display={displayValue(student.governorate)}
                                onChange={(value) => setField('governorate', value)}
                            />
                            <DraftField
                                label={i18n.students.neighborhood}
                                editing={editing}
                                value={draft.neighborhood}
                                display={displayValue(student.neighborhood)}
                                onChange={(value) => setField('neighborhood', value)}
                            />
                            <DraftField
                                label={i18n.students.locality}
                                editing={editing}
                                value={draft.locality}
                                display={displayValue(student.locality)}
                                onChange={(value) => setField('locality', value)}
                            />
                            <DraftField
                                label={i18n.students.houseNumber}
                                editing={editing}
                                value={draft.house_number}
                                display={displayValue(student.house_number)}
                                onChange={(value) => setField('house_number', value)}
                            />
                        </div>
                    </FormSection>

                    <FormSection
                        id={`student-study-${student.id}`}
                        title={i18n.students.studySection}
                    >
                        <div className="sis-admission-draft-rows">
                            <div className="sis-admission-draft-row">
                                <DraftField
                                    label={i18n.students.schoolName}
                                    editing={editing}
                                    value={draft.school_name}
                                    display={displayValue(student.school_name)}
                                    onChange={(value) => setField('school_name', value)}
                                />
                                <DraftOptionalSelect
                                    label={i18n.students.branchName}
                                    editing={editing}
                                    value={draft.branch_id}
                                    display={branchDisplay}
                                    emptyLabel={i18n.admission.selectOption}
                                    options={placementOptions.branches.map((item) => ({
                                        value: String(item.id),
                                        label: item.name,
                                    }))}
                                    onChange={(next) => {
                                        setDraft((current) => ({
                                            ...current,
                                            branch_id: next,
                                            department_name: '',
                                        }));
                                    }}
                                />
                                <AcademicYearListField
                                    label={i18n.students.academicYear}
                                    student={student}
                                />
                                <DraftField
                                    label={i18n.students.stageName}
                                    editing={editing}
                                    value={draft.stage_name}
                                    display={displayValue(student.stage_name)}
                                    onChange={(value) => setField('stage_name', value)}
                                />
                            </div>
                            <div className="sis-admission-draft-row">
                                <DraftOptionalSelect
                                    label={i18n.students.departmentName}
                                    editing={editing}
                                    value={draft.department_name}
                                    display={displayValue(student.department_name)}
                                    emptyLabel={i18n.admission.selectOption}
                                    options={filteredDepartments.map((item) => ({
                                        value: item.name,
                                        label: item.name,
                                    }))}
                                    onChange={(next) => setField('department_name', next)}
                                />
                                <DraftField
                                    label={i18n.students.sectionName}
                                    editing={editing}
                                    value={draft.section_name}
                                    display={displayValue(student.section_name)}
                                    onChange={(value) => setField('section_name', value)}
                                />
                                <DraftField
                                    label={i18n.students.admittedClassName}
                                    editing={editing}
                                    value={draft.admitted_class_name}
                                    display={displayValue(student.admitted_class_name)}
                                    onChange={(value) => setField('admitted_class_name', value)}
                                />
                                <DraftField
                                    label={i18n.students.specialization}
                                    editing={editing}
                                    value={draft.specialization_name}
                                    display={displayValue(student.specialization_name)}
                                    onChange={(value) => setField('specialization_name', value)}
                                />
                            </div>
                            <div className="sis-admission-draft-row">
                                <DraftField
                                    label={i18n.students.schoolStartDate}
                                    editing={editing}
                                    type="date"
                                    value={draft.school_start_date}
                                    display={formatCivilDate(student.school_start_date)}
                                    onChange={(value) => setField('school_start_date', value)}
                                />
                                <DraftField
                                    label={i18n.students.previousSchoolName}
                                    editing={editing}
                                    value={draft.previous_school_name}
                                    display={displayValue(student.previous_school_name)}
                                    onChange={(value) => setField('previous_school_name', value)}
                                />
                                <DraftField
                                    label={i18n.students.transferDocumentNumber}
                                    editing={editing}
                                    value={draft.transfer_document_number}
                                    display={displayValue(student.transfer_document_number)}
                                    onChange={(value) => setField('transfer_document_number', value)}
                                />
                                <DraftField
                                    label={i18n.students.transferDocumentDate}
                                    editing={editing}
                                    type="date"
                                    value={draft.transfer_document_date}
                                    display={formatCivilDate(student.transfer_document_date)}
                                    onChange={(value) => setField('transfer_document_date', value)}
                                />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection
                        id={`student-contact-${student.id}`}
                        title={i18n.students.contactSection}
                    >
                        <div className="sis-admission-draft-row">
                            <DraftField
                                label={i18n.students.guardianTripleName}
                                editing={editing}
                                value={draft.guardian_triple_name}
                                display={displayValue(student.guardian_triple_name)}
                                onChange={(value) => setField('guardian_triple_name', value)}
                            />
                            {canViewPii ? (
                                <>
                                    <DraftField
                                        label={i18n.students.mobile}
                                        editing={editing}
                                        value={draft.mobile}
                                        display={displayValue(student.mobile)}
                                        dir="ltr"
                                        onChange={(value) => setField('mobile', value)}
                                    />
                                    <DraftField
                                        label={i18n.students.guardianMobile}
                                        editing={editing}
                                        value={draft.guardian_mobile}
                                        display={displayValue(student.guardian_mobile)}
                                        dir="ltr"
                                        onChange={(value) => setField('guardian_mobile', value)}
                                    />
                                    <DraftField
                                        label={i18n.students.email}
                                        editing={editing}
                                        type="email"
                                        value={draft.email}
                                        display={displayValue(student.email)}
                                        dir="ltr"
                                        onChange={(value) => setField('email', value)}
                                    />
                                </>
                            ) : null}
                        </div>
                    </FormSection>
                </div>

                <div
                    className="sis-student-record-form__notes-row"
                    role="group"
                    aria-label={i18n.students.notes}
                >
                    <DraftNotes
                        label={i18n.students.notes}
                        editing={editing}
                        value={draft.notes}
                        display={displayValue(student.notes)}
                        onChange={(value) => setField('notes', value)}
                    />
                </div>
            </div>
        </article>
    );
}

export function StudentViewDialog({
    students,
    canViewPii,
    canUpdate = false,
    placementOptions,
    onClose,
    onSaved,
}: StudentViewDialogProps) {
    const i18n = t();
    const count = students.length;
    const title =
        count > 1
            ? `${i18n.students.viewManyTitle} (${count})`
            : i18n.students.viewTitle;

    return (
        <Dialog open onOpenChange={(open) => {
            if (!open) {
                onClose();
            }
        }}>
            <DialogContent
                className={`sis-admission-draft-dialog sis-student-view-dialog gap-1.5 p-3 sm:max-w-[min(96vw,92rem)]${count > 1 ? ' sis-student-view-dialog--many' : ''}`}
                dir="rtl"
                lang="ar"
                data-sis-align-exempt=""
                aria-describedby="student-view-dialog-desc"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => {
                    const target = event.target as HTMLElement | null;
                    if (target?.closest('[data-sis-list-select]')) {
                        event.preventDefault();
                    }
                }}
                onFocusOutside={(event) => {
                    const target = event.target as HTMLElement | null;
                    if (target?.closest('[data-sis-list-select]')) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription id="student-view-dialog-desc" className="sr-only">
                        {i18n.students.viewDialogDesc}
                    </DialogDescription>
                </DialogHeader>
                <div className="sis-student-view-dialog__body">
                    {students.map((student) => (
                        <StudentRecordForm
                            key={student.id}
                            student={student}
                            canViewPii={canViewPii}
                            canUpdate={canUpdate}
                            placementOptions={placementOptions}
                            onSaved={onSaved}
                        />
                    ))}
                </div>
                <div className="sis-admission-draft-actions">
                    <StatusLikeButton tone="close" onClick={onClose}>
                        {i18n.window.close}
                    </StatusLikeButton>
                </div>
            </DialogContent>
        </Dialog>
    );
}
