import { useEffect, useMemo, useRef, useState, type ChangeEvent, type HTMLAttributes, type ReactNode } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import {
    StudentStatusBadge,
    normalizeStudentStatus,
} from '@/components/students/student-status-badge';
import { SheetSection } from '@/components/sis/admission-sheet';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { usePageError } from '@/components/sis/page-error-context';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import { useSheetMaximize } from '@/hooks/use-sheet-maximize';
import { t } from '@/i18n';
import {
    admissionBranchSelectOptions,
    admissionClassSelectOptions,
    admissionDepartmentSelectOptions,
    classKeyFromAdmittedClassName,
    resolveBranchIdByName,
} from '@/lib/enrollment-dialog-resolve';
import { pickDirtyPayload, sisSmoothMutation } from '@/lib/sis-ui-perf';
import { sisClassLabel } from '@/lib/sis-class-section-options';
import type { EnrollmentFormFilterOptions } from '@/components/enrollments/enrollment-record-form';

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
    academic_year_id?: number | null;
    academic_year_name?: string | null;
    academic_year_code?: string | null;
    father_occupation?: string | null;
    mother_occupation?: string | null;
    administrative_unit?: number | null;
    graduation_year?: number | null;
    previous_gpa?: number | null;
    previous_study_track?: number | null;
    mathematics_grade?: number | null;
    physics_grade?: number | null;
    request_kind?: number | null;
    status: number;
    documents?: Array<{ id: number; document_type: number | string; file_name: string; storage_key?: string }>;
};

type StudentRecordFormProps = {
    student: StudentRecordFormValues;
    canViewPii: boolean;
    canUpdate: boolean;
    mode?: 'edit' | 'create';
    proceedLabel?: string;
    initialEditing?: boolean;
    sheetTitle?: string;
    onClose?: () => void;
    heroDragProps?: HTMLAttributes<HTMLElement>;
    showWindowControls?: boolean;
    /** Hide per-form hero when a shared dialog chrome owns the title bar. */
    hideHero?: boolean;
    maximized?: boolean;
    onMaximize?: () => void;
    onSaved?: (student: StudentRecordFormValues) => void;
    onProceed?: (student: StudentRecordFormValues) => void;
};

type StudentViewDialogProps = {
    students: StudentRecordFormValues[];
    canViewPii: boolean;
    canUpdate?: boolean;
    title?: string;
    proceedLabel?: string;
    initialEditing?: boolean;
    onClose: () => void;
    onSaved?: (student: StudentRecordFormValues) => void;
    onProceed?: (student: StudentRecordFormValues) => void;
};

type StudentCreateDialogProps = {
    canViewPii: boolean;
    onClose: () => void;
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
    notes: string;
    mobile: string;
    guardian_mobile: string;
    email: string;
    school_name: string;
    academic_year_id: number | null;
    branch_name: string;
    department_name: string;
    admitted_class_name: string;
    class_key: string;
    father_occupation: string;
    mother_occupation: string;
    administrative_unit: number | null;
    graduation_year: string;
    previous_gpa: string;
    previous_study_track: number | null;
    mathematics_grade: string;
    physics_grade: string;
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
    const admittedClassName = student.admitted_class_name ?? '';

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
        notes: student.notes ?? '',
        mobile: student.mobile ?? '',
        guardian_mobile: student.guardian_mobile ?? '',
        email: student.email ?? '',
        school_name: student.school_name ?? '',
        academic_year_id: student.academic_year_id ?? null,
        branch_name: student.branch_name ?? '',
        department_name: student.department_name ?? '',
        admitted_class_name: admittedClassName,
        class_key: classKeyFromAdmittedClassName(admittedClassName),
        father_occupation: student.father_occupation ?? '',
        mother_occupation: student.mother_occupation ?? '',
        administrative_unit: student.administrative_unit ?? null,
        graduation_year: student.graduation_year != null ? String(student.graduation_year) : '',
        previous_gpa: student.previous_gpa != null ? String(student.previous_gpa) : '',
        previous_study_track: student.previous_study_track ?? null,
        mathematics_grade: student.mathematics_grade != null ? String(student.mathematics_grade) : '',
        physics_grade: student.physics_grade != null ? String(student.physics_grade) : '',
    };
}

export function emptyStudentRecordValues(): StudentRecordFormValues {
    return {
        id: 0,
        student_code: '',
        first_name: '',
        last_name: '',
        birth_date: '',
        gender: 0,
        religion: 0,
        status: 1,
        request_kind: null,
        documents: [],
    };
}

const STUDENT_DOCUMENT_SLOTS = [
    { type: 20, labelKey: 'docPersonalPhoto' as const },
    { type: 11, labelKey: 'docStudentIdFront' as const },
    { type: 12, labelKey: 'docStudentIdBack' as const },
    { type: 13, labelKey: 'docFatherIdFront' as const },
    { type: 14, labelKey: 'docFatherIdBack' as const },
    { type: 15, labelKey: 'docMotherIdFront' as const },
    { type: 16, labelKey: 'docMotherIdBack' as const },
    { type: 17, labelKey: 'docResidenceFront' as const },
    { type: 18, labelKey: 'docResidenceBack' as const },
    { type: 19, labelKey: 'docGraduationCertificate' as const },
] as const;

function csrfToken(): string {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        ?? ''
    );
}

function newIdempotencyKey(prefix: string): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${prefix}-${Date.now()}`;
}

function isFilled(value: string | number | null | undefined): boolean {
    if (value === null || value === undefined) {
        return false;
    }

    const text = String(value).trim();

    return text !== '' && text !== '—';
}

function filledControlClass(filled: boolean, editing: boolean): string {
    return `sis-admission-sheet__control${filled ? ' sis-admission-draft-field--filled' : ''}${editing ? '' : ' sis-admission-draft-readonly'}`;
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
        <label className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            {editing ? (
                <input
                    type={type}
                    dir={dir}
                    className={filledControlClass(isFilled(value), true)}
                    value={value}
                    placeholder=" "
                    aria-label={label}
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : (
                <div className={filledControlClass(isFilled(display), false)} dir={dir}>
                    {display}
                </div>
            )}
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
    const selected = options.some((option) => option.value === value)
        ? String(value)
        : '';
    const selectOptions = options.map((option) => ({
        value: String(option.value),
        label: option.label,
    }));

    return (
        <label className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            {editing ? (
                <SisListSelect
                    value={selected}
                    options={selectOptions}
                    onChange={(next) => {
                        if (next === '') {
                            return;
                        }

                        onChange(Number(next));
                    }}
                    ariaLabel={label}
                    includeBlank={selected === ''}
                    className={`sis-admission-sheet-list-select${isFilled(selected || display) ? ' sis-admission-draft-field--filled' : ''}`}
                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${isFilled(selected || display) ? ' sis-admission-draft-field--filled' : ''}`}
                    menuClassName="sis-admission-sheet-list-select__menu"
                />
            ) : (
                <div className={filledControlClass(isFilled(display), false)}>{display}</div>
            )}
        </label>
    );
}

function DraftListSelect({
    label,
    editing,
    value,
    display,
    options,
    onChange,
    disabled = false,
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
    disabled?: boolean;
}) {
    return (
        <label className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            {editing ? (
                <SisListSelect
                    value={value}
                    options={options}
                    onChange={onChange}
                    ariaLabel={label}
                    includeBlank
                    disabled={disabled}
                    className={`sis-admission-sheet-list-select${isFilled(value) ? ' sis-admission-draft-field--filled' : ''}`}
                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${isFilled(value) ? ' sis-admission-draft-field--filled' : ''}`}
                    menuClassName="sis-admission-sheet-list-select__menu"
                />
            ) : (
                <div className={filledControlClass(isFilled(display), false)}>{display}</div>
            )}
        </label>
    );
}

function DraftDisplayField({
    label,
    display,
}: {
    label: string;
    display: string;
}) {
    return (
        <div className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            <div className={filledControlClass(isFilled(display), false)} aria-readonly="true">
                {display}
            </div>
        </div>
    );
}

function AcademicYearListField({
    label,
    editing,
    value,
    student,
    onChange,
}: {
    label: string;
    editing: boolean;
    value: number | null;
    student: StudentRecordFormValues;
    onChange: (value: number | null) => void;
}) {
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const years = academicYears ?? [];
    const selectedId = value;
    const selectValue = selectedId === null ? '' : String(selectedId);
    const options = years.map((year) => ({
        value: String(year.id),
        label: formatAcademicYearOptionLabel(year.name, year.code),
    }));

    if (
        selectedId !== null
        && !options.some((option) => option.value === String(selectedId))
    ) {
        options.unshift({
            value: String(selectedId),
            label: displayAcademicYear({ ...student, academic_year_id: selectedId }),
        });
    }

    const displayLabel =
        selectedId === null
            ? '—'
            : options.find((option) => option.value === String(selectedId))?.label
              ?? displayAcademicYear({ ...student, academic_year_id: selectedId });

    return (
        <label className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            {editing ? (
                <SisListSelect
                    value={selectValue}
                    options={options}
                    onChange={(next) => {
                        if (next === '') {
                            onChange(null);
                            return;
                        }

                        const parsed = Number(next);
                        onChange(Number.isFinite(parsed) ? parsed : null);
                    }}
                    ariaLabel={label}
                    includeBlank
                    className={`sis-admission-sheet-list-select${isFilled(displayLabel === '—' ? '' : displayLabel) ? ' sis-admission-draft-field--filled' : ''}`}
                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${isFilled(displayLabel === '—' ? '' : displayLabel) ? ' sis-admission-draft-field--filled' : ''}`}
                    menuClassName="sis-admission-sheet-list-select__menu"
                />
            ) : (
                <div className={filledControlClass(isFilled(displayLabel), false)}>
                    {displayLabel}
                </div>
            )}
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
        <label className="sis-admission-sheet__field sis-student-record-form__notes-field">
            <span className="sis-admission-sheet__label">{label}</span>
            {editing ? (
                <textarea
                    className={`${filledControlClass(isFilled(value), true)} sis-student-record-form__notes`}
                    aria-label={label}
                    value={value}
                    placeholder=" "
                    rows={2}
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : (
                <div
                    className={`${filledControlClass(isFilled(display), false)} sis-student-record-form__notes`}
                >
                    {display}
                </div>
            )}
        </label>
    );
}

function GenderField({
    label,
    name,
    editing,
    value,
    display,
    maleLabel,
    femaleLabel,
    onChange,
}: {
    label: string;
    name: string;
    editing: boolean;
    value: number;
    display: string;
    maleLabel: string;
    femaleLabel: string;
    onChange: (value: number) => void;
}) {
    if (!editing) {
        return <DraftDisplayField label={label} display={display} />;
    }

    return (
        <fieldset className="sis-admission-sheet__field sis-admission-sheet__choice-group">
            <legend className="sis-admission-sheet__label">{label}</legend>
            <div className="sis-admission-sheet__choices" role="radiogroup" aria-label={label}>
                <label className="sis-admission-sheet__choice">
                    <input
                        type="radio"
                        name={name}
                        value="1"
                        checked={value === 1}
                        onChange={() => onChange(1)}
                    />
                    <span className="sis-admission-sheet__choice-dot" aria-hidden="true" />
                    <span>{maleLabel}</span>
                </label>
                <label className="sis-admission-sheet__choice">
                    <input
                        type="radio"
                        name={name}
                        value="2"
                        checked={value === 2}
                        onChange={() => onChange(2)}
                    />
                    <span className="sis-admission-sheet__choice-dot" aria-hidden="true" />
                    <span>{femaleLabel}</span>
                </label>
            </div>
        </fieldset>
    );
}

export function StudentRecordForm({
    student,
    canViewPii,
    canUpdate,
    mode = 'edit',
    proceedLabel,
    initialEditing = false,
    sheetTitle,
    onClose,
    heroDragProps,
    showWindowControls = true,
    hideHero = false,
    maximized = false,
    onMaximize,
    onSaved,
    onProceed,
}: StudentRecordFormProps) {
    const i18n = t();
    const { showError, showInertiaErrors } = usePageError();
    const isCreate = mode === 'create';
    const [editing, setEditing] = useState(isCreate || initialEditing);
    const [saving, setSaving] = useState(false);
    const [uploadingDocType, setUploadingDocType] = useState<number | null>(null);
    const [documents, setDocuments] = useState(
        () => student.documents ?? [],
    );
    const documentFileInputRef = useRef<HTMLInputElement | null>(null);
    const pendingDocumentTypeRef = useRef<number | null>(null);
    const [draft, setDraft] = useState<DraftState>(() => draftFromStudent(student));
    const pageProps = usePage().props as {
        enrollmentFilterOptions?: EnrollmentFormFilterOptions;
    };
    const orgBranches = pageProps.enrollmentFilterOptions?.branches ?? [];

    useEffect(() => {
        setDocuments(student.documents ?? []);
    }, [student.id, student.documents]);

    useEffect(() => {
        if (isCreate || editing) {
            return;
        }

        setDraft(draftFromStudent(student));
    }, [editing, isCreate, student]);

    const name = studentQuadName(editing || isCreate ? draft : student);
    const activeGender = editing || isCreate ? draft.gender : student.gender;
    const genderLabel =
        activeGender === 1
            ? i18n.students.male
            : activeGender === 2
              ? i18n.students.female
              : i18n.students.gender;
    const religionValue = editing || isCreate ? draft.religion : student.religion;
    const religionLabel =
        religionValue === 1
            ? i18n.students.religionMuslim
            : religionValue === 2
              ? i18n.students.religionChristian
              : religionValue === 3
                ? i18n.students.religionOther
                : i18n.students.religion;
    const fieldsEditable = isCreate || editing;

    const branchOptions = useMemo(() => admissionBranchSelectOptions(), []);
    const departmentOptions = useMemo(
        () => admissionDepartmentSelectOptions(draft.branch_name),
        [draft.branch_name],
    );
    const classOptions = useMemo(() => admissionClassSelectOptions(), []);

    const setField = <K extends keyof DraftState>(key: K, value: DraftState[K]) => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    const requestKindLabel =
        student.request_kind === 1
            ? i18n.admission.requestTypeAcademicTransfer
            : student.request_kind === 2
              ? i18n.admission.requestTypeVocational
              : '—';

    const openNativeDocumentPicker = (type: number) => {
        if (!fieldsEditable || isCreate || student.id <= 0) {
            return;
        }

        pendingDocumentTypeRef.current = type;
        const input = documentFileInputRef.current;
        if (input === null) {
            return;
        }

        input.value = '';
        input.click();
    };

    const onNativeDocumentPicked = async (event: ChangeEvent<HTMLInputElement>) => {
        const type = pendingDocumentTypeRef.current;
        const file = event.target.files?.[0] ?? null;
        pendingDocumentTypeRef.current = null;

        if (type === null || file === null || student.id <= 0) {
            return;
        }

        if (!['image/jpeg', 'image/png', 'image/webp', 'application/pdf'].includes(file.type)) {
            showError(i18n.admission.documentUploadHint);

            return;
        }

        setUploadingDocType(type);
        try {
            const body = new FormData();
            body.append('document_type', String(type));
            body.append('file', file);

            const response = await fetch(`/students/${student.id}/documents/upload`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Idempotency-Key': newIdempotencyKey(`student-doc-${student.id}-${type}`),
                },
                credentials: 'same-origin',
                body,
            });

            const payload = (await response.json().catch(() => null)) as {
                data?: { document_id?: number; file_name?: string; document_type?: number };
                message?: string;
            } | null;

            if (!response.ok) {
                showError(payload?.message ?? i18n.errors.createFailed);

                return;
            }

            const documentId = Number(payload?.data?.document_id ?? 0);
            const fileName = String(payload?.data?.file_name ?? file.name);
            setDocuments((current) => {
                const withoutType = current.filter((doc) => Number(doc.document_type) !== type);
                return [
                    ...withoutType,
                    {
                        id: documentId,
                        document_type: type,
                        file_name: fileName,
                    },
                ];
            });
        } catch {
            showError(i18n.errors.createFailed);
        } finally {
            setUploadingDocType(null);
        }
    };

    const resolvedAdmittedClassName = (): string | null => {
        const fromKey = sisClassLabel(draft.class_key).trim();
        if (fromKey !== '') {
            return fromKey;
        }

        return emptyToNull(draft.admitted_class_name);
    };

    const buildPayload = (): Record<string, string | number | null> => {
        const admittedClassName = resolvedAdmittedClassName();
        const resolvedBranchId = resolveBranchIdByName(draft.branch_name, orgBranches);
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
            religion:
                draft.religion === 1 || draft.religion === 2 || draft.religion === 3
                    ? draft.religion
                    : 1,
            mawalid_date: emptyToNull(draft.mawalid_date),
            previous_school_name: emptyToNull(draft.previous_school_name),
            transfer_document_number: emptyToNull(draft.transfer_document_number)
                ? Number(draft.transfer_document_number)
                : null,
            transfer_document_date: emptyToNull(draft.transfer_document_date),
            school_start_date: emptyToNull(draft.school_start_date),
            notes: emptyToNull(draft.notes),
            school_name: emptyToNull(draft.school_name),
            department_name: emptyToNull(draft.department_name),
            admitted_class_name: admittedClassName,
            father_occupation: emptyToNull(draft.father_occupation),
            mother_occupation: emptyToNull(draft.mother_occupation),
            administrative_unit: draft.administrative_unit,
            graduation_year: emptyToNull(draft.graduation_year) ? Number(draft.graduation_year) : null,
            previous_gpa: emptyToNull(draft.previous_gpa) ? Number(draft.previous_gpa) : null,
            previous_study_track: draft.previous_study_track,
            mathematics_grade: emptyToNull(draft.mathematics_grade) ? Number(draft.mathematics_grade) : null,
            physics_grade: emptyToNull(draft.physics_grade) ? Number(draft.physics_grade) : null,
        };

        if (draft.academic_year_id !== null) {
            payload.academic_year_id = draft.academic_year_id;
        }

        if (resolvedBranchId !== null) {
            payload.branch_id = resolvedBranchId;
        } else if (student.branch_id != null) {
            payload.branch_id = student.branch_id;
        }

        if (canViewPii) {
            payload.national_id = emptyToNull(draft.national_id);
            payload.mobile = emptyToNull(draft.mobile);
            payload.guardian_mobile = emptyToNull(draft.guardian_mobile);
            payload.email = emptyToNull(draft.email);
        }

        return payload;
    };

    const save = () => {
        if (saving) {
            return;
        }

        if (draft.first_name.trim() === '' || draft.last_name.trim() === '' || draft.birth_date === '') {
            showError(i18n.errors.requiredFields);
            return;
        }

        if (draft.gender !== 1 && draft.gender !== 2) {
            showError(i18n.errors.requiredFields);
            return;
        }

        setSaving(true);
        const fullPayload = buildPayload();

        if (isCreate) {
            const idempotencyKey =
                typeof crypto !== 'undefined' && 'randomUUID' in crypto
                    ? crypto.randomUUID()
                    : `student-create-${Date.now()}`;

            router.post('/students', fullPayload, {
                headers: { 'X-Idempotency-Key': idempotencyKey },
                onError: (errors) => showInertiaErrors(errors, i18n.errors.createFailed),
                onFinish: () => setSaving(false),
            });

            return;
        }

        const baseline: Record<string, string | number | null> = {
            first_name: student.first_name,
            last_name: student.last_name,
            father_name: student.father_name ?? null,
            grandfather_name: student.grandfather_name ?? null,
            great_grandfather_name: student.great_grandfather_name ?? null,
            mother_name: student.mother_name ?? null,
            maternal_father_name: student.maternal_father_name ?? null,
            maternal_grandfather_name: student.maternal_grandfather_name ?? null,
            guardian_triple_name: student.guardian_triple_name ?? null,
            governorate: student.governorate ?? null,
            neighborhood: student.neighborhood ?? null,
            locality: student.locality ?? null,
            house_number: student.house_number ?? null,
            birth_date: isoDate(student.birth_date),
            birth_place: student.birth_place ?? null,
            registration_place: student.registration_place ?? null,
            gender: student.gender,
            nationality: student.nationality ?? null,
            religion: student.religion ?? 1,
            mawalid_date: isoDate(student.mawalid_date),
            previous_school_name: student.previous_school_name ?? null,
            transfer_document_number: student.transfer_document_number ?? null,
            transfer_document_date: isoDate(student.transfer_document_date),
            school_start_date: isoDate(student.school_start_date),
            notes: student.notes ?? null,
            school_name: student.school_name ?? null,
            department_name: student.department_name ?? null,
            admitted_class_name: student.admitted_class_name ?? null,
            father_occupation: student.father_occupation ?? null,
            mother_occupation: student.mother_occupation ?? null,
            administrative_unit: student.administrative_unit ?? null,
            graduation_year: student.graduation_year ?? null,
            previous_gpa: student.previous_gpa ?? null,
            previous_study_track: student.previous_study_track ?? null,
            mathematics_grade: student.mathematics_grade ?? null,
            physics_grade: student.physics_grade ?? null,
            academic_year_id: student.academic_year_id ?? null,
            branch_id: student.branch_id ?? null,
            national_id: student.national_id ?? null,
            mobile: student.mobile ?? null,
            guardian_mobile: student.guardian_mobile ?? null,
            email: student.email ?? null,
        };

        const payload = pickDirtyPayload(baseline, fullPayload, {
            always: ['first_name', 'last_name', 'birth_date'],
        });

        router.put(`/students/${student.id}`, payload, {
            ...sisSmoothMutation(['students', 'filters', 'authorization']),
            onSuccess: () => {
                const admittedClassName = resolvedAdmittedClassName();
                const resolvedBranchId = resolveBranchIdByName(draft.branch_name, orgBranches);
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
                    notes: emptyToNull(draft.notes),
                    school_name: emptyToNull(draft.school_name),
                    academic_year_id: draft.academic_year_id,
                    branch_name: emptyToNull(draft.branch_name),
                    department_name: emptyToNull(draft.department_name),
                    admitted_class_name: admittedClassName,
                    branch_id: resolvedBranchId ?? student.branch_id ?? null,
                    national_id: canViewPii ? emptyToNull(draft.national_id) : student.national_id,
                    mobile: canViewPii ? emptyToNull(draft.mobile) : student.mobile,
                    guardian_mobile: canViewPii
                        ? emptyToNull(draft.guardian_mobile)
                        : student.guardian_mobile,
                    email: canViewPii ? emptyToNull(draft.email) : student.email,
                    father_occupation: emptyToNull(draft.father_occupation),
                    mother_occupation: emptyToNull(draft.mother_occupation),
                    administrative_unit: draft.administrative_unit,
                    graduation_year: emptyToNull(draft.graduation_year) ? Number(draft.graduation_year) : null,
                    previous_gpa: emptyToNull(draft.previous_gpa) ? Number(draft.previous_gpa) : null,
                    previous_study_track: draft.previous_study_track,
                    mathematics_grade: emptyToNull(draft.mathematics_grade) ? Number(draft.mathematics_grade) : null,
                    physics_grade: emptyToNull(draft.physics_grade) ? Number(draft.physics_grade) : null,
                };
                setEditing(false);
                onSaved?.(next);
                onProceed?.(next);
            },
            onError: (errors) => showInertiaErrors(errors, i18n.errors.saveFailed),
            onFinish: () => setSaving(false),
        });
    };

    const resolvedSheetTitle =
        sheetTitle ??
        (isCreate ? i18n.students.createTitle : i18n.students.viewTitle);

    return (
        <article
            className="sis-admission-draft-form sis-admission-sheet sis-student-record-form"
            dir="rtl"
            lang="ar"
        >
            {!hideHero && (onClose || sheetTitle) ? (
                <header className="sis-admission-sheet__hero" {...heroDragProps}>
                    {onClose && showWindowControls ? (
                        <WindowControls
                            className="sis-admission-sheet__window-controls"
                            label={i18n.window.controls}
                            minimizeLabel={i18n.window.minimize}
                            maximizeLabel={i18n.window.maximize}
                            restoreLabel={i18n.window.restore}
                            closeLabel={i18n.window.close}
                            minimizable={false}
                            maximizable={Boolean(onMaximize)}
                            maximized={maximized}
                            onMaximize={onMaximize}
                            onClose={onClose}
                        />
                    ) : (
                        <span className="sis-admission-sheet__window-controls" aria-hidden="true" />
                    )}
                    <div className="sis-admission-sheet__hero-copy">
                        <p className="sis-admission-sheet__hero-title">{resolvedSheetTitle}</p>
                    </div>
                    <div className="sis-admission-sheet__hero-logo">
                        <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                    </div>
                </header>
            ) : null}

            <SheetSection
                id={`student-registration-${student.id}`}
                title={i18n.admission.sheetRegistrationInfo}
            >
                <div className="sis-student-record-form__name-line">
                    <DraftDisplayField
                        label={i18n.students.quadName}
                        display={displayValue(name)}
                    />
                    {isCreate ? null : (
                        <div className="sis-admission-sheet__field sis-student-record-form__status-field">
                            <span className="sis-admission-sheet__label">{i18n.common.status}</span>
                            <div
                                className="sis-student-record-form__status-value"
                                aria-readonly="true"
                            >
                                <StudentStatusBadge status={normalizeStudentStatus(student.status)} />
                            </div>
                        </div>
                    )}
                </div>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    {isCreate ? (
                        <DraftDisplayField
                            label={i18n.students.academicYear}
                            display="—"
                        />
                    ) : (
                        <AcademicYearListField
                            label={i18n.students.academicYear}
                            editing={fieldsEditable}
                            value={draft.academic_year_id}
                            student={student}
                            onChange={(value) => setField('academic_year_id', value)}
                        />
                    )}
                    <DraftField
                        label={i18n.students.schoolName}
                        editing={fieldsEditable}
                        value={draft.school_name}
                        display={displayValue(student.school_name)}
                        onChange={(value) => setField('school_name', value)}
                    />
                    <DraftListSelect
                        label={i18n.students.branchName}
                        editing={fieldsEditable}
                        value={draft.branch_name}
                        display={displayValue(student.branch_name)}
                        options={branchOptions}
                        onChange={(next) => {
                            setDraft((current) => ({
                                ...current,
                                branch_name: next,
                                department_name: next === current.branch_name ? current.department_name : '',
                            }));
                        }}
                    />
                    <DraftListSelect
                        label={i18n.admission.specialization}
                        editing={fieldsEditable}
                        value={draft.department_name}
                        display={displayValue(student.department_name)}
                        options={departmentOptions}
                        disabled={draft.branch_name.trim() === ''}
                        onChange={(value) => setField('department_name', value)}
                    />
                    <DraftDisplayField
                        label={i18n.admission.requestTypeTitle}
                        display={requestKindLabel}
                    />
                </div>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <DraftListSelect
                        label={i18n.admission.gradeLevel}
                        editing={fieldsEditable}
                        value={draft.class_key}
                        display={displayValue(student.admitted_class_name)}
                        options={classOptions}
                        onChange={(next) => {
                            setDraft((current) => ({
                                ...current,
                                class_key: next,
                                admitted_class_name: sisClassLabel(next) || current.admitted_class_name,
                            }));
                        }}
                    />
                    <DraftField
                        label={i18n.students.schoolStartDate}
                        editing={fieldsEditable}
                        type="date"
                        value={draft.school_start_date}
                        display={formatCivilDate(student.school_start_date)}
                        onChange={(value) => setField('school_start_date', value)}
                    />
                </div>
            </SheetSection>

            <SheetSection
                id={`student-personal-${student.id}`}
                title={i18n.admission.sheetPersonalInfo}
            >
                <div className="sis-admission-sheet__row sis-admission-sheet__row--5">
                    <DraftField
                        label={i18n.students.firstName}
                        editing={fieldsEditable}
                        value={draft.first_name}
                        display={displayValue(student.first_name)}
                        onChange={(value) => setField('first_name', value)}
                    />
                    <DraftField
                        label={i18n.students.fatherName}
                        editing={fieldsEditable}
                        value={draft.father_name}
                        display={displayValue(student.father_name)}
                        onChange={(value) => setField('father_name', value)}
                    />
                    <DraftField
                        label={i18n.students.grandfatherName}
                        editing={fieldsEditable}
                        value={draft.grandfather_name}
                        display={displayValue(student.grandfather_name)}
                        onChange={(value) => setField('grandfather_name', value)}
                    />
                    <DraftField
                        label={i18n.students.greatGrandfatherName}
                        editing={fieldsEditable}
                        value={draft.great_grandfather_name}
                        display={displayValue(student.great_grandfather_name)}
                        onChange={(value) => setField('great_grandfather_name', value)}
                    />
                    <DraftField
                        label={i18n.students.familyName}
                        editing={fieldsEditable}
                        value={draft.last_name}
                        display={displayValue(student.last_name)}
                        onChange={(value) => setField('last_name', value)}
                    />
                </div>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <DraftField
                        label={i18n.students.birthDate}
                        editing={fieldsEditable}
                        type="date"
                        value={draft.birth_date}
                        display={formatCivilDate(student.birth_date)}
                        onChange={(value) => setField('birth_date', value)}
                    />
                    <DraftField
                        label={i18n.students.birthPlace}
                        editing={fieldsEditable}
                        value={draft.birth_place}
                        display={displayValue(student.birth_place)}
                        onChange={(value) => setField('birth_place', value)}
                    />
                    {canViewPii ? (
                        <DraftField
                            label={i18n.students.nationalId}
                            editing={fieldsEditable}
                            value={draft.national_id}
                            display={displayValue(student.national_id)}
                            dir="ltr"
                            onChange={(value) => setField('national_id', value)}
                        />
                    ) : (
                        <DraftDisplayField label={i18n.students.nationalId} display="—" />
                    )}
                    <GenderField
                        label={i18n.students.gender}
                        name={`student-record-gender-${student.id || 'new'}`}
                        editing={fieldsEditable}
                        value={draft.gender}
                        display={genderLabel}
                        maleLabel={i18n.students.male}
                        femaleLabel={i18n.students.female}
                        onChange={(value) => setField('gender', value)}
                    />
                </div>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <DraftField
                        label={i18n.students.nationality}
                        editing={fieldsEditable}
                        value={draft.nationality}
                        display={displayValue(student.nationality)}
                        onChange={(value) => setField('nationality', value)}
                    />
                    <DraftSelect
                        label={i18n.students.religion}
                        editing={fieldsEditable}
                        value={draft.religion}
                        display={religionLabel}
                        onChange={(value) => setField('religion', value)}
                        options={[
                            { value: 1, label: i18n.students.religionMuslim },
                            { value: 2, label: i18n.students.religionChristian },
                            { value: 3, label: i18n.students.religionOther },
                        ]}
                    />
                    <DraftField
                        label={i18n.students.mawalidDate}
                        editing={fieldsEditable}
                        type="date"
                        value={draft.mawalid_date}
                        display={formatCivilDate(student.mawalid_date)}
                        onChange={(value) => setField('mawalid_date', value)}
                    />
                    <DraftField
                        label={i18n.students.registrationPlace}
                        editing={fieldsEditable}
                        value={draft.registration_place}
                        display={displayValue(student.registration_place)}
                        onChange={(value) => setField('registration_place', value)}
                    />
                </div>
            </SheetSection>

            <SheetSection
                id={`student-parents-${student.id}`}
                title={i18n.admission.sheetParentsInfo}
            >
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <DraftField
                        label={i18n.students.motherName}
                        editing={fieldsEditable}
                        value={draft.mother_name}
                        display={displayValue(student.mother_name)}
                        onChange={(value) => setField('mother_name', value)}
                    />
                    <DraftField
                        label={i18n.students.maternalFatherName}
                        editing={fieldsEditable}
                        value={draft.maternal_father_name}
                        display={displayValue(student.maternal_father_name)}
                        onChange={(value) => setField('maternal_father_name', value)}
                    />
                    <DraftField
                        label={i18n.students.maternalGrandfatherName}
                        editing={fieldsEditable}
                        value={draft.maternal_grandfather_name}
                        display={displayValue(student.maternal_grandfather_name)}
                        onChange={(value) => setField('maternal_grandfather_name', value)}
                    />
                    <DraftField
                        label={i18n.students.guardianTripleName}
                        editing={fieldsEditable}
                        value={draft.guardian_triple_name}
                        display={displayValue(student.guardian_triple_name)}
                        onChange={(value) => setField('guardian_triple_name', value)}
                    />
                    <DraftField
                        label={i18n.students.fatherOccupation}
                        editing={fieldsEditable}
                        value={draft.father_occupation}
                        display={displayValue(student.father_occupation)}
                        onChange={(value) => setField('father_occupation', value)}
                    />
                    <DraftField
                        label={i18n.students.motherOccupation}
                        editing={fieldsEditable}
                        value={draft.mother_occupation}
                        display={displayValue(student.mother_occupation)}
                        onChange={(value) => setField('mother_occupation', value)}
                    />
                </div>
            </SheetSection>

            <SheetSection
                id={`student-residence-${student.id}`}
                title={i18n.admission.sheetResidenceInfo}
            >
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <DraftField
                        label={i18n.students.governorate}
                        editing={fieldsEditable}
                        value={draft.governorate}
                        display={displayValue(student.governorate)}
                        onChange={(value) => setField('governorate', value)}
                    />
                    <DraftField
                        label={i18n.students.neighborhood}
                        editing={fieldsEditable}
                        value={draft.neighborhood}
                        display={displayValue(student.neighborhood)}
                        onChange={(value) => setField('neighborhood', value)}
                    />
                    <DraftSelect
                        label={i18n.students.administrativeUnit}
                        editing={fieldsEditable}
                        value={draft.administrative_unit ?? 0}
                        display={
                            draft.administrative_unit === 1
                                ? i18n.admission.administrativeUnitCenter
                                : draft.administrative_unit === 2
                                  ? i18n.admission.administrativeUnitDistrict
                                  : draft.administrative_unit === 3
                                    ? i18n.admission.administrativeUnitSubdistrict
                                    : '—'
                        }
                        onChange={(value) => setField('administrative_unit', value === 0 ? null : value)}
                        options={[
                            { value: 0, label: '— ' + i18n.admission.selectOption + ' —' },
                            { value: 1, label: i18n.admission.administrativeUnitCenter },
                            { value: 2, label: i18n.admission.administrativeUnitDistrict },
                            { value: 3, label: i18n.admission.administrativeUnitSubdistrict },
                        ]}
                    />
                    <DraftField
                        label={i18n.students.locality}
                        editing={fieldsEditable}
                        value={draft.locality}
                        display={displayValue(student.locality)}
                        onChange={(value) => setField('locality', value)}
                    />
                    <DraftField
                        label={i18n.students.houseNumber}
                        editing={fieldsEditable}
                        value={draft.house_number}
                        display={displayValue(student.house_number)}
                        onChange={(value) => setField('house_number', value)}
                    />
                </div>
                {canViewPii ? (
                    <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                        <DraftField
                            label={i18n.students.mobile}
                            editing={fieldsEditable}
                            value={draft.mobile}
                            display={displayValue(student.mobile)}
                            dir="ltr"
                            onChange={(value) => setField('mobile', value)}
                        />
                        <DraftField
                            label={i18n.students.guardianMobile}
                            editing={fieldsEditable}
                            value={draft.guardian_mobile}
                            display={displayValue(student.guardian_mobile)}
                            dir="ltr"
                            onChange={(value) => setField('guardian_mobile', value)}
                        />
                        <DraftField
                            label={i18n.students.email}
                            editing={fieldsEditable}
                            type="email"
                            value={draft.email}
                            display={displayValue(student.email)}
                            dir="ltr"
                            onChange={(value) => setField('email', value)}
                        />
                    </div>
                ) : null}
            </SheetSection>

            <SheetSection
                id={`student-prior-study-${student.id}`}
                title={i18n.admission.sheetPriorStudyInfo}
            >
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <DraftField
                        label={i18n.students.previousSchoolName}
                        editing={fieldsEditable}
                        value={draft.previous_school_name}
                        display={displayValue(student.previous_school_name)}
                        onChange={(value) => setField('previous_school_name', value)}
                    />
                    <DraftField
                        label={i18n.students.graduationYear}
                        editing={fieldsEditable}
                        value={draft.graduation_year}
                        display={displayValue(student.graduation_year)}
                        dir="ltr"
                        onChange={(value) => setField('graduation_year', value)}
                    />
                    <DraftField
                        label={i18n.students.previousGpa}
                        editing={fieldsEditable}
                        value={draft.previous_gpa}
                        display={displayValue(student.previous_gpa)}
                        dir="ltr"
                        onChange={(value) => setField('previous_gpa', value)}
                    />
                    <DraftSelect
                        label={i18n.students.previousStudyTrack}
                        editing={fieldsEditable}
                        value={draft.previous_study_track ?? 0}
                        display={
                            draft.previous_study_track === 1
                                ? i18n.admission.previousStudyTrackScientific
                                : draft.previous_study_track === 2
                                  ? i18n.admission.previousStudyTrackLiterary
                                  : draft.previous_study_track === 3
                                    ? i18n.admission.previousStudyTrackIndustrial
                                    : draft.previous_study_track === 4
                                      ? i18n.admission.previousStudyTrackCommercial
                                      : draft.previous_study_track === 5
                                        ? i18n.admission.previousStudyTrackVocational
                                        : '—'
                        }
                        onChange={(value) =>
                            setField('previous_study_track', value === 0 ? null : value)
                        }
                        options={[
                            { value: 0, label: '—' },
                            { value: 1, label: i18n.admission.previousStudyTrackScientific },
                            { value: 2, label: i18n.admission.previousStudyTrackLiterary },
                            { value: 3, label: i18n.admission.previousStudyTrackIndustrial },
                            { value: 4, label: i18n.admission.previousStudyTrackCommercial },
                            { value: 5, label: i18n.admission.previousStudyTrackVocational },
                        ]}
                    />
                </div>
                {(
                    isFilled(draft.mathematics_grade)
                    || isFilled(student.mathematics_grade)
                    || isFilled(draft.physics_grade)
                    || isFilled(student.physics_grade)
                ) ? (
                    <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                        {(
                            isFilled(draft.mathematics_grade)
                            || isFilled(student.mathematics_grade)
                        ) ? (
                            <DraftField
                                label={i18n.students.mathematicsGrade}
                                editing={fieldsEditable}
                                value={draft.mathematics_grade}
                                display={displayValue(student.mathematics_grade)}
                                dir="ltr"
                                onChange={(value) => setField('mathematics_grade', value)}
                            />
                        ) : null}
                        {(
                            isFilled(draft.physics_grade)
                            || isFilled(student.physics_grade)
                        ) ? (
                            <DraftField
                                label={i18n.students.physicsGrade}
                                editing={fieldsEditable}
                                value={draft.physics_grade}
                                display={displayValue(student.physics_grade)}
                                dir="ltr"
                                onChange={(value) => setField('physics_grade', value)}
                            />
                        ) : null}
                    </div>
                ) : null}
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <DraftField
                        label={i18n.students.transferDocumentNumber}
                        editing={fieldsEditable}
                        value={draft.transfer_document_number}
                        display={displayValue(student.transfer_document_number)}
                        onChange={(value) => setField('transfer_document_number', value)}
                    />
                    <DraftField
                        label={i18n.students.transferDocumentDate}
                        editing={fieldsEditable}
                        type="date"
                        value={draft.transfer_document_date}
                        display={formatCivilDate(student.transfer_document_date)}
                        onChange={(value) => setField('transfer_document_date', value)}
                    />
                </div>
            </SheetSection>

            <SheetSection
                id={`student-documents-${student.id}`}
                title={i18n.admission.sheetDocumentsInfo}
            >
                {fieldsEditable && !isCreate ? (
                    <p className="sis-admission-sheet__docs-hint">
                        {i18n.admission.documentUploadHint}
                    </p>
                ) : null}
                <input
                    ref={documentFileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf"
                    className="sis-admission-sheet__doc-file-input"
                    aria-hidden="true"
                    tabIndex={-1}
                    onChange={onNativeDocumentPicked}
                />
                <div className="sis-admission-sheet__docs">
                    {STUDENT_DOCUMENT_SLOTS.map((slot) => {
                        const uploaded = documents.find(
                            (doc) => Number(doc.document_type) === slot.type,
                        );
                        const label = i18n.admission[slot.labelKey];
                        const canUpload = fieldsEditable && !isCreate && student.id > 0;
                        const uploading = uploadingDocType === slot.type;

                        if (canUpload) {
                            return (
                                <button
                                    key={slot.type}
                                    type="button"
                                    className={`sis-admission-sheet__doc-btn${uploaded ? ' is-filled' : ''}`}
                                    disabled={uploading}
                                    onClick={() => openNativeDocumentPicker(slot.type)}
                                    aria-label={label}
                                >
                                    <span>{label}</span>
                                    <span className="sis-admission-sheet__doc-btn-file">
                                        {uploading
                                            ? i18n.common.saving
                                            : uploaded
                                              ? uploaded.file_name
                                              : '—'}
                                    </span>
                                </button>
                            );
                        }

                        return (
                            <div
                                key={slot.type}
                                className={`sis-admission-sheet__doc-btn${uploaded ? ' is-filled' : ''}`}
                                aria-label={label}
                            >
                                <span>{label}</span>
                                <span className="sis-admission-sheet__doc-btn-file">
                                    {uploaded ? uploaded.file_name : '—'}
                                </span>
                            </div>
                        );
                    })}
                </div>
            </SheetSection>

            <SheetSection id={`student-notes-${student.id}`} title={i18n.students.notes}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--full">
                    <DraftNotes
                        label={i18n.students.notes}
                        editing={fieldsEditable}
                        value={draft.notes}
                        display={displayValue(student.notes)}
                        onChange={(value) => setField('notes', value)}
                    />
                </div>
            </SheetSection>

            <div className="sis-admission-sheet__actions">
                {onClose ? (
                    <Button type="button" variant="outline" onClick={onClose}>
                        {i18n.dialog.cancel}
                    </Button>
                ) : null}
                {canUpdate || isCreate ? (
                    <>
                        {!isCreate ? (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={editing}
                                onClick={() => setEditing(true)}
                            >
                                {i18n.common.edit}
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            disabled={saving || (!isCreate && !editing)}
                            onClick={save}
                        >
                            {saving
                                ? i18n.common.saving
                                : isCreate
                                  ? i18n.students.createSubmit
                                  : (proceedLabel ?? i18n.common.save)}
                        </Button>
                    </>
                ) : onProceed ? (
                    <Button type="button" onClick={() => onProceed(student)}>
                        {proceedLabel ?? i18n.workflow.continueEnrollment}
                    </Button>
                ) : null}
            </div>
        </article>
    );
}

export function StudentViewDialog({
    students,
    canViewPii,
    canUpdate = false,
    title: titleOverride,
    proceedLabel,
    initialEditing = false,
    onClose,
    onSaved,
    onProceed,
}: StudentViewDialogProps) {
    const i18n = t();
    const count = students.length;
    const viewTitle = titleOverride ?? i18n.students.viewTitle;
    const dialogTitle =
        titleOverride ??
        (count > 1
            ? `${i18n.students.viewManyTitle} (${count})`
            : i18n.students.viewTitle);
    const { contentRef, heroDragProps, bringToFront, resizeHandles } = useSmoothDialogDrag(true, {
        resizable: true,
        minSize: { width: 520, height: 360 },
    });
    const { maximized, toggleMaximize, maximizeClassName } = useSheetMaximize(contentRef);

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
                className={`sis-admission-draft-dialog sis-admission-sheet-dialog sis-student-sheet-dialog${maximizeClassName}${count > 1 ? ' sis-student-sheet-dialog--many' : ''}`}
                overlayClassName="sis-admission-sheet-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onPointerDownCapture={bringToFront}
            >
                <DialogTitle className="sr-only">{dialogTitle}</DialogTitle>
                {maximized ? null : resizeHandles}
                {count > 1 ? (
                    <>
                        <header
                            className="sis-admission-sheet__hero sis-student-sheet-dialog__shared-hero"
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
                                <p className="sis-admission-sheet__hero-title">{viewTitle}</p>
                            </div>
                            <div className="sis-admission-sheet__hero-logo">
                                <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                            </div>
                        </header>
                        <div className="sis-student-sheet-dialog__many-scroller" dir="rtl">
                            {students.map((student) => (
                                <div
                                    key={student.id}
                                    className="sis-student-sheet-dialog__many-page"
                                >
                                    <StudentRecordForm
                                        student={student}
                                        canViewPii={canViewPii}
                                        canUpdate={canUpdate}
                                        proceedLabel={proceedLabel}
                                        initialEditing={initialEditing}
                                        hideHero
                                        onClose={onClose}
                                        onSaved={onSaved}
                                        onProceed={onProceed}
                                    />
                                </div>
                            ))}
                        </div>
                    </>
                ) : (
                    students.map((student) => (
                        <StudentRecordForm
                            key={student.id}
                            student={student}
                            canViewPii={canViewPii}
                            canUpdate={canUpdate}
                            proceedLabel={proceedLabel}
                            initialEditing={initialEditing}
                            sheetTitle={viewTitle}
                            onClose={onClose}
                            heroDragProps={maximized ? undefined : heroDragProps}
                            showWindowControls
                            maximized={maximized}
                            onMaximize={toggleMaximize}
                            onSaved={onSaved}
                            onProceed={onProceed}
                        />
                    ))
                )}
            </DialogContent>
        </Dialog>
    );
}

export function StudentCreateDialog({ canViewPii, onClose }: StudentCreateDialogProps) {
    const i18n = t();
    const { contentRef, heroDragProps, bringToFront, resizeHandles } = useSmoothDialogDrag(true, {
        resizable: true,
        minSize: { width: 520, height: 360 },
    });
    const { maximized, toggleMaximize, maximizeClassName } = useSheetMaximize(contentRef);

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
                className={`sis-admission-draft-dialog sis-admission-sheet-dialog sis-student-sheet-dialog${maximizeClassName}`}
                overlayClassName="sis-admission-sheet-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onPointerDownCapture={bringToFront}
            >
                <DialogTitle className="sr-only">{i18n.students.createTitle}</DialogTitle>
                {maximized ? null : resizeHandles}
                <StudentRecordForm
                    student={emptyStudentRecordValues()}
                    canViewPii={canViewPii}
                    canUpdate
                    mode="create"
                    sheetTitle={i18n.students.createTitle}
                    onClose={onClose}
                    heroDragProps={maximized ? undefined : heroDragProps}
                    maximized={maximized}
                    onMaximize={toggleMaximize}
                />
            </DialogContent>
        </Dialog>
    );
}
