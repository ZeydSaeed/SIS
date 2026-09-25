import { Form, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ChangeEvent, type ReactNode } from 'react';
import {
    ADMISSION_BRANCH_OPTIONS,
    departmentsForBranch,
} from '@/components/admission/admission-branch-catalog';
import AppLogo from '@/components/app-logo';
import { OpsTextInput } from '@/components/sis/ops-form-field';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { usePageError } from '@/components/sis/page-error-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import { t } from '@/i18n';

export type DraftPeriodOption = {
    id: number;
    name: string;
    status: number;
};

export type DraftSchoolOption = {
    id: number;
    name: string;
};

export type DraftNamedOption = {
    id: number;
    name: string;
    branch_id?: number | null;
    department_id?: number | null;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    periods: DraftPeriodOption[];
    schools: DraftSchoolOption[];
    gradeLevels: DraftNamedOption[];
    branches: DraftNamedOption[];
    departments: DraftNamedOption[];
    specializations: DraftNamedOption[];
    canManage: boolean;
    academicYearId?: number | null;
    /** draft = طلب قبول مسودة; createStudent = إضافة طالب → محوّل */
    mode?: 'draft' | 'createStudent';
};

type DraftSelectOption = {
    value: string;
    label: string;
};

type DraftDocumentSlot = {
    type: number;
    labelKey:
        | 'docStudentIdFront'
        | 'docStudentIdBack'
        | 'docFatherIdFront'
        | 'docFatherIdBack'
        | 'docMotherIdFront'
        | 'docMotherIdBack'
        | 'docResidenceFront'
        | 'docResidenceBack'
        | 'docGraduationCertificate';
};

const DRAFT_DOCUMENT_SLOTS: readonly DraftDocumentSlot[] = [
    { type: 11, labelKey: 'docStudentIdFront' },
    { type: 12, labelKey: 'docStudentIdBack' },
    { type: 13, labelKey: 'docFatherIdFront' },
    { type: 14, labelKey: 'docFatherIdBack' },
    { type: 15, labelKey: 'docMotherIdFront' },
    { type: 16, labelKey: 'docMotherIdBack' },
    { type: 17, labelKey: 'docResidenceFront' },
    { type: 18, labelKey: 'docResidenceBack' },
    { type: 19, labelKey: 'docGraduationCertificate' },
] as const;

function filledClass(value: string): string {
    return value.trim() !== '' ? ' sis-admission-draft-field--filled' : '';
}

function markFilled(event: ChangeEvent<HTMLInputElement>): void {
    const el = event.currentTarget;
    el.classList.toggle('sis-admission-draft-field--filled', el.value.trim() !== '');
}

function SheetField({
    label,
    name,
    error,
    children,
    className = '',
}: {
    label: string;
    name: string;
    error?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <label className={`sis-admission-sheet__field ${className}`.trim()} htmlFor={name}>
            <span className="sis-admission-sheet__label">{label}</span>
            {children}
            {error ? (
                <span id={`${name}-error`} className="sis-admission-sheet__error" role="alert">
                    {error}
                </span>
            ) : null}
        </label>
    );
}

function SheetSection({
    title,
    tone,
    children,
}: {
    title: string;
    tone: 'accent' | 'dark';
    children: ReactNode;
}) {
    return (
        <section className="sis-admission-sheet__section">
            <h3 className={`sis-admission-sheet__banner sis-admission-sheet__banner--${tone}`}>{title}</h3>
            <div className="sis-admission-sheet__body">{children}</div>
        </section>
    );
}

/** Sheet selects use SisListSelect so menu bg/selection can follow accent derivatives (native OS lists cannot). */
function DraftSheetSelect({
    name,
    value,
    options,
    onChange,
    required = false,
    ariaLabel,
    dir = 'rtl',
    allowEmpty = false,
}: {
    name: string;
    value: string;
    options: DraftSelectOption[];
    onChange: (value: string) => void;
    required?: boolean;
    ariaLabel: string;
    dir?: 'rtl' | 'ltr';
    allowEmpty?: boolean;
}) {
    const filled = filledClass(value);

    return (
        <SisListSelect
            id={name}
            name={name}
            value={value}
            options={options}
            onChange={onChange}
            required={required}
            ariaLabel={ariaLabel}
            dir={dir}
            includeBlank={allowEmpty}
            className={`sis-admission-sheet-list-select${filled}`}
            triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${filled}`}
            menuClassName="sis-admission-sheet-list-select__menu"
        />
    );
}

/** Admission draft dialog — Employee Data Sheet layout (RTL Arabic). */
export function AdmissionApplicationDraftDialog({
    open,
    onOpenChange,
    periods,
    schools,
    gradeLevels: _gradeLevels,
    branches,
    departments: _departments,
    specializations: _specializations,
    canManage,
    academicYearId = null,
    mode = 'draft',
}: Props) {
    void _gradeLevels;
    void _specializations;
    void _departments;

    const i18n = t();
    const { showInertiaErrors } = usePageError();
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };

    const isCreateStudent = mode === 'createStudent';
    const formAction = isCreateStudent
        ? '/admission/applications/register-student'
        : '/admission/applications';
    const dialogTitle = isCreateStudent
        ? i18n.admission.createStudentDialogTitle
        : i18n.admission.draftDialogTitle;
    const submitLabel = isCreateStudent
        ? i18n.admission.createStudentSubmit
        : i18n.admission.createDraft;

    const activePeriods = useMemo(
        () => periods.filter((period) => period.status === 1),
        [periods],
    );
    const defaultPeriodId = activePeriods[0]?.id ?? '';
    const defaultSchoolId = schools[0]?.id ?? '';

    const [periodId, setPeriodId] = useState<string>(String(defaultPeriodId));
    const [schoolId, setSchoolId] = useState(String(defaultSchoolId));
    const [branchName, setBranchName] = useState('');
    const [departmentName, setDepartmentName] = useState('');
    const [gender, setGender] = useState('1');
    const [administrativeUnit, setAdministrativeUnit] = useState('');
    const [previousStudyTrack, setPreviousStudyTrack] = useState('');
    const [documentFiles, setDocumentFiles] = useState<Record<number, File | null>>({});
    const documentFileInputRef = useRef<HTMLInputElement>(null);
    const pendingDocumentTypeRef = useRef<number | null>(null);
    const wasOpenRef = useRef(false);

    const selectedAcademicYear = useMemo(() => {
        if (academicYearId == null) {
            return null;
        }

        return (academicYears ?? []).find((year) => year.id === academicYearId) ?? null;
    }, [academicYearId, academicYears]);

    const academicYearDisplay = selectedAcademicYear
        ? formatAcademicYearOptionLabel(selectedAcademicYear.name, selectedAcademicYear.code)
        : academicYearId != null
          ? String(academicYearId)
          : '—';

    const administrativeUnitOptions = useMemo(
        (): DraftSelectOption[] => [
            { value: '1', label: i18n.admission.administrativeUnitCenter },
            { value: '2', label: i18n.admission.administrativeUnitDistrict },
            { value: '3', label: i18n.admission.administrativeUnitSubdistrict },
        ],
        [i18n.admission.administrativeUnitCenter, i18n.admission.administrativeUnitDistrict, i18n.admission.administrativeUnitSubdistrict],
    );

    const previousStudyTrackOptions = useMemo(
        (): DraftSelectOption[] => [
            { value: '1', label: i18n.admission.previousStudyTrackScientific },
            { value: '2', label: i18n.admission.previousStudyTrackLiterary },
            { value: '3', label: i18n.admission.previousStudyTrackIndustrial },
            { value: '4', label: i18n.admission.previousStudyTrackCommercial },
            { value: '5', label: i18n.admission.previousStudyTrackVocational },
            { value: '6', label: i18n.admission.previousStudyTrackIntermediate },
        ],
        [
            i18n.admission.previousStudyTrackScientific,
            i18n.admission.previousStudyTrackLiterary,
            i18n.admission.previousStudyTrackIndustrial,
            i18n.admission.previousStudyTrackCommercial,
            i18n.admission.previousStudyTrackVocational,
            i18n.admission.previousStudyTrackIntermediate,
        ],
    );

    const branchOptions = useMemo(
        (): DraftSelectOption[] =>
            ADMISSION_BRANCH_OPTIONS.map((name) => ({ value: name, label: name })),
        [],
    );

    const departmentOptions = useMemo(
        (): DraftSelectOption[] =>
            departmentsForBranch(branchName).map((name) => ({ value: name, label: name })),
        [branchName],
    );

    const matchedBranchId = useMemo(() => {
        if (branchName === '') {
            return '';
        }

        const match = branches.find((branch) => branch.name === branchName);

        return match !== undefined ? String(match.id) : '';
    }, [branchName, branches]);

    useEffect(() => {
        const justOpened = open && !wasOpenRef.current;
        wasOpenRef.current = open;
        if (!justOpened) {
            return;
        }

        setPeriodId(String(activePeriods[0]?.id ?? ''));
        setSchoolId(String(schools[0]?.id ?? ''));
        setBranchName('');
        setDepartmentName('');
        setGender('1');
        setAdministrativeUnit('');
        setPreviousStudyTrack('');
        setDocumentFiles({});
        pendingDocumentTypeRef.current = null;
        if (documentFileInputRef.current) {
            documentFileInputRef.current.value = '';
        }
    }, [open, activePeriods, schools]);

    const openNativeDocumentPicker = (type: number) => {
        pendingDocumentTypeRef.current = type;
        const input = documentFileInputRef.current;
        if (input === null) {
            return;
        }

        input.value = '';
        input.click();
    };

    const onNativeDocumentPicked = (event: ChangeEvent<HTMLInputElement>) => {
        const type = pendingDocumentTypeRef.current;
        const file = event.target.files?.[0] ?? null;
        pendingDocumentTypeRef.current = null;

        if (type === null) {
            return;
        }

        if (file && !['image/jpeg', 'image/png'].includes(file.type)) {
            setDocumentFiles((current) => ({ ...current, [type]: null }));

            return;
        }

        setDocumentFiles((current) => ({ ...current, [type]: file }));
    };

    const { contentRef, heroDragProps, bringToFront } = useSmoothDialogDrag(open);

    if (!canManage) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange} modal={false}>
            <DialogContent
                ref={contentRef}
                className="sis-admission-draft-dialog sis-admission-sheet-dialog sm:max-w-4xl"
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

                {activePeriods.length === 0 ? (
                    <p className="sis-admission-sheet__empty">{i18n.admission.noOpenPeriod}</p>
                ) : (
                    <Form
                        action={formAction}
                        method="post"
                        className="sis-admission-draft-form sis-admission-sheet"
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        onError={(errors) => showInertiaErrors(errors, i18n.errors.createFailed)}
                    >
                        {({ errors, processing }) => (
                            <>
                                {academicYearId !== null ? (
                                    <input type="hidden" name="academic_year_id" value={academicYearId} />
                                ) : null}
                                <input type="hidden" name="application_period_id" value={periodId} />

                                <header className="sis-admission-sheet__hero" {...heroDragProps}>
                                    <WindowControls
                                        className="sis-admission-sheet__window-controls"
                                        label={i18n.window.controls}
                                        minimizeLabel={i18n.window.minimize}
                                        maximizeLabel={i18n.window.maximize}
                                        restoreLabel={i18n.window.restore}
                                        closeLabel={i18n.window.close}
                                        minimizable={false}
                                        maximizable={false}
                                        onClose={() => onOpenChange(false)}
                                    />
                                    <div className="sis-admission-sheet__hero-copy">
                                        <p className="sis-admission-sheet__hero-title">{dialogTitle}</p>
                                    </div>
                                    <div className="sis-admission-sheet__hero-logo">
                                        <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                                    </div>
                                </header>

                                <SheetSection title={i18n.admission.sheetRegistrationInfo} tone="accent">
                                    <div className="sis-admission-sheet__row sis-admission-sheet__row--4">
                                        <div className="sis-admission-sheet__field">
                                            <span className="sis-admission-sheet__label">
                                                {i18n.admission.academicYear}
                                            </span>
                                            <div
                                                className="sis-admission-sheet__control sis-admission-draft-field--filled"
                                                aria-readonly="true"
                                            >
                                                {academicYearDisplay}
                                            </div>
                                        </div>
                                        <SheetField
                                            label={i18n.admission.school}
                                            name="target_school_id"
                                            error={errors.target_school_id}
                                        >
                                            <DraftSheetSelect
                                                name="target_school_id"
                                                required
                                                value={schoolId}
                                                options={schools.map((school) => ({
                                                    value: String(school.id),
                                                    label: school.name,
                                                }))}
                                                onChange={setSchoolId}
                                                ariaLabel={i18n.admission.school}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.branch}
                                            name="branch_name"
                                            error={errors.branch_name}
                                        >
                                            <DraftSheetSelect
                                                name="branch_name"
                                                value={branchName}
                                                allowEmpty
                                                options={branchOptions}
                                                onChange={(next) => {
                                                    setBranchName(next);
                                                    setDepartmentName('');
                                                }}
                                                ariaLabel={i18n.admission.branch}
                                            />
                                            {matchedBranchId !== '' ? (
                                                <input type="hidden" name="branch_id" value={matchedBranchId} />
                                            ) : null}
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.department}
                                            name="department_name"
                                            error={errors.department_name}
                                        >
                                            <DraftSheetSelect
                                                name="department_name"
                                                value={departmentName}
                                                allowEmpty
                                                options={departmentOptions}
                                                onChange={setDepartmentName}
                                                ariaLabel={i18n.admission.department}
                                            />
                                        </SheetField>
                                    </div>
                                </SheetSection>

                                <SheetSection title={i18n.admission.sheetPersonalInfo} tone="accent">
                                    <div className="sis-admission-sheet__row sis-admission-sheet__row--5">
                                        <SheetField
                                            label={i18n.admission.studentName}
                                            name="first_name"
                                            error={errors.first_name}
                                        >
                                            <OpsTextInput
                                                name="first_name"
                                                required
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.first_name}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.fatherName}
                                            name="father_name"
                                            error={errors.father_name}
                                        >
                                            <OpsTextInput
                                                name="father_name"
                                                required
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.father_name}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.grandfatherName}
                                            name="grandfather_name"
                                            error={errors.grandfather_name}
                                        >
                                            <OpsTextInput
                                                name="grandfather_name"
                                                required
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.grandfather_name}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.greatGrandfatherName}
                                            name="great_grandfather_name"
                                            error={errors.great_grandfather_name}
                                        >
                                            <OpsTextInput
                                                name="great_grandfather_name"
                                                required
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.great_grandfather_name}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.lastName}
                                            name="last_name"
                                            error={errors.last_name}
                                        >
                                            <OpsTextInput
                                                name="last_name"
                                                required
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.last_name}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                    </div>

                                    <div className="sis-admission-sheet__row sis-admission-sheet__row--4">
                                        <SheetField
                                            label={i18n.admission.birthDate}
                                            name="birth_date"
                                            error={errors.birth_date}
                                        >
                                            <OpsTextInput
                                                name="birth_date"
                                                type="date"
                                                required
                                                error={errors.birth_date}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.birthPlace}
                                            name="birth_place"
                                            error={errors.birth_place}
                                        >
                                            <OpsTextInput
                                                name="birth_place"
                                                required
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.birth_place}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.nationalId}
                                            name="national_id"
                                            error={errors.national_id}
                                        >
                                            <OpsTextInput
                                                name="national_id"
                                                dir="ltr"
                                                placeholder=" "
                                                error={errors.national_id}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <fieldset className="sis-admission-sheet__field sis-admission-sheet__choice-group">
                                            <legend className="sis-admission-sheet__label">
                                                {i18n.admission.gender}
                                            </legend>
                                            <div className="sis-admission-sheet__choices" role="radiogroup">
                                                <label className="sis-admission-sheet__choice">
                                                    <input
                                                        type="radio"
                                                        name="gender"
                                                        value="1"
                                                        checked={gender === '1'}
                                                        onChange={() => setGender('1')}
                                                        required
                                                    />
                                                    <span className="sis-admission-sheet__choice-dot" aria-hidden="true" />
                                                    <span>{i18n.admission.genderMale}</span>
                                                </label>
                                                <label className="sis-admission-sheet__choice">
                                                    <input
                                                        type="radio"
                                                        name="gender"
                                                        value="2"
                                                        checked={gender === '2'}
                                                        onChange={() => setGender('2')}
                                                    />
                                                    <span className="sis-admission-sheet__choice-dot" aria-hidden="true" />
                                                    <span>{i18n.admission.genderFemale}</span>
                                                </label>
                                            </div>
                                            {errors.gender ? (
                                                <span className="sis-admission-sheet__error" role="alert">
                                                    {errors.gender}
                                                </span>
                                            ) : null}
                                        </fieldset>
                                    </div>
                                </SheetSection>

                                <SheetSection title={i18n.admission.sheetParentsInfo} tone="accent">
                                    <div className="sis-admission-sheet__row sis-admission-sheet__row--3">
                                        <SheetField
                                            label={i18n.admission.motherName}
                                            name="mother_name"
                                            error={errors.mother_name}
                                        >
                                            <OpsTextInput
                                                name="mother_name"
                                                required
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.mother_name}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.maternalFatherName}
                                            name="maternal_father_name"
                                            error={errors.maternal_father_name}
                                        >
                                            <OpsTextInput
                                                name="maternal_father_name"
                                                required
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.maternal_father_name}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.maternalGrandfatherName}
                                            name="maternal_grandfather_name"
                                            error={errors.maternal_grandfather_name}
                                        >
                                            <OpsTextInput
                                                name="maternal_grandfather_name"
                                                required
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.maternal_grandfather_name}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                    </div>

                                    <div className="sis-admission-sheet__row sis-admission-sheet__row--2">
                                        <SheetField
                                            label={i18n.admission.fatherOccupation}
                                            name="father_occupation"
                                            error={errors.father_occupation}
                                        >
                                            <OpsTextInput
                                                name="father_occupation"
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.father_occupation}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.motherOccupation}
                                            name="mother_occupation"
                                            error={errors.mother_occupation}
                                        >
                                            <OpsTextInput
                                                name="mother_occupation"
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.mother_occupation}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                    </div>
                                </SheetSection>

                                <SheetSection title={i18n.admission.sheetResidenceInfo} tone="accent">
                                    <div className="sis-admission-sheet__row sis-admission-sheet__row--3">
                                        <SheetField
                                            label={i18n.admission.governorate}
                                            name="governorate"
                                            error={errors.governorate}
                                        >
                                            <OpsTextInput
                                                name="governorate"
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.governorate}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.administrativeUnit}
                                            name="administrative_unit"
                                            error={errors.administrative_unit}
                                        >
                                            <DraftSheetSelect
                                                name="administrative_unit"
                                                value={administrativeUnit}
                                                allowEmpty
                                                options={administrativeUnitOptions}
                                                onChange={setAdministrativeUnit}
                                                ariaLabel={i18n.admission.administrativeUnit}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.neighborhood}
                                            name="neighborhood"
                                            error={errors.neighborhood}
                                        >
                                            <OpsTextInput
                                                name="neighborhood"
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.neighborhood}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                    </div>

                                    <div className="sis-admission-sheet__row sis-admission-sheet__row--2">
                                        <SheetField
                                            label={i18n.admission.studentMobile}
                                            name="student_mobile"
                                            error={errors.student_mobile}
                                        >
                                            <OpsTextInput
                                                name="student_mobile"
                                                dir="ltr"
                                                placeholder=" "
                                                error={errors.student_mobile}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.guardianMobile}
                                            name="guardian_mobile"
                                            error={errors.guardian_mobile}
                                        >
                                            <OpsTextInput
                                                name="guardian_mobile"
                                                dir="ltr"
                                                placeholder=" "
                                                error={errors.guardian_mobile}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                    </div>
                                </SheetSection>

                                <SheetSection title={i18n.admission.sheetPriorStudyInfo} tone="accent">
                                    <div className="sis-admission-sheet__row sis-admission-sheet__row--4">
                                        <SheetField
                                            label={i18n.admission.previousSchoolName}
                                            name="previous_school_name"
                                            error={errors.previous_school_name}
                                        >
                                            <OpsTextInput
                                                name="previous_school_name"
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.previous_school_name}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.graduationYear}
                                            name="graduation_year"
                                            error={errors.graduation_year}
                                        >
                                            <OpsTextInput
                                                name="graduation_year"
                                                type="number"
                                                min={1950}
                                                dir="ltr"
                                                placeholder=" "
                                                error={errors.graduation_year}
                                                className="sis-admission-sheet__control"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.previousGpa}
                                            name="previous_gpa"
                                            error={errors.previous_gpa}
                                        >
                                            <input
                                                id="previous_gpa"
                                                name="previous_gpa"
                                                type="number"
                                                step="0.01"
                                                min={0}
                                                max={100}
                                                dir="ltr"
                                                placeholder=" "
                                                aria-invalid={errors.previous_gpa ? true : undefined}
                                                aria-describedby={
                                                    errors.previous_gpa ? 'previous_gpa-error' : undefined
                                                }
                                                className="sis-admission-sheet__control sis-ops-hub__link min-h-11 px-3 py-2"
                                                onChange={markFilled}
                                            />
                                        </SheetField>
                                        <SheetField
                                            label={i18n.admission.previousStudyTrack}
                                            name="previous_study_track"
                                            error={errors.previous_study_track}
                                        >
                                            <DraftSheetSelect
                                                name="previous_study_track"
                                                value={previousStudyTrack}
                                                allowEmpty
                                                options={previousStudyTrackOptions}
                                                onChange={setPreviousStudyTrack}
                                                ariaLabel={i18n.admission.previousStudyTrack}
                                            />
                                        </SheetField>
                                    </div>
                                </SheetSection>

                                <SheetSection title={i18n.admission.sheetDocumentsInfo} tone="accent">
                                    <p className="sis-admission-sheet__docs-hint">
                                        {i18n.admission.documentUploadHint}
                                    </p>
                                    <input
                                        ref={documentFileInputRef}
                                        type="file"
                                        accept="image/jpeg,image/png,.jpg,.jpeg,.png"
                                        className="sis-admission-sheet__doc-file-input"
                                        aria-hidden="true"
                                        tabIndex={-1}
                                        onChange={onNativeDocumentPicked}
                                    />
                                    <div className="sis-admission-sheet__docs">
                                        {DRAFT_DOCUMENT_SLOTS.map((slot) => {
                                            const selected = documentFiles[slot.type] ?? null;
                                            const label = i18n.admission[slot.labelKey];

                                            return (
                                                <button
                                                    key={slot.type}
                                                    type="button"
                                                    className={`sis-admission-sheet__doc-btn${selected ? ' is-filled' : ''}`}
                                                    onClick={() => openNativeDocumentPicker(slot.type)}
                                                >
                                                    <span>{label}</span>
                                                    {selected ? (
                                                        <span className="sis-admission-sheet__doc-btn-file">
                                                            {selected.name}
                                                        </span>
                                                    ) : null}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </SheetSection>

                                <div className="sis-admission-sheet__actions">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => onOpenChange(false)}
                                    >
                                        {i18n.dialog.cancel}
                                    </Button>
                                    <Button type="submit" disabled={processing || schools.length === 0}>
                                        {submitLabel}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
