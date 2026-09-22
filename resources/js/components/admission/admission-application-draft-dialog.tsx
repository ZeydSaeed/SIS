import { Form } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ChangeEvent } from 'react';
import {
    admissionDateTimeNow,
    parseAdmissionDateTime,
} from '@/components/admission/format-admission-datetime';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
};

function filledClass(value: string): string {
    return value.trim() !== '' ? ' sis-admission-draft-field--filled' : '';
}

function markFilled(event: ChangeEvent<HTMLInputElement>): void {
    const el = event.currentTarget;
    el.classList.toggle('sis-admission-draft-field--filled', el.value.trim() !== '');
}

function DraftApplicationWhen({ value }: { value: string }) {
    const i18n = t().admission;
    const parts = parseAdmissionDateTime(value);

    if (parts === null) {
        return (
            <span className="sis-admission-periods-table__when" dir="ltr">
                {value}
            </span>
        );
    }

    const periodLabel = parts.period === 'pm' ? i18n.timePm : i18n.timeAm;

    return (
        <span className="sis-admission-periods-table__when">
            <span className="sis-admission-periods-table__date" dir="rtl" lang="en">
                <span>{parts.day}</span>
                <span className="sis-admission-periods-table__date-sep" aria-hidden="true">
                    /
                </span>
                <span>{parts.month}</span>
                <span className="sis-admission-periods-table__date-sep" aria-hidden="true">
                    /
                </span>
                <span>{parts.year}</span>
            </span>
            <span className="sis-admission-periods-table__time" dir="rtl">
                <span className="sis-admission-periods-table__clock" dir="ltr" lang="en">
                    {parts.hour}:{parts.minute}
                </span>
                <span className="sis-admission-periods-table__period">{periodLabel}</span>
            </span>
        </span>
    );
}

/** Admission draft dialog — row layout, compact fields. */
export function AdmissionApplicationDraftDialog({
    open,
    onOpenChange,
    periods,
    schools,
    gradeLevels,
    branches,
    departments,
    specializations,
    canManage,
    academicYearId = null,
}: Props) {
    const i18n = t();
    const activePeriods = useMemo(
        () => periods.filter((period) => period.status === 1),
        [periods],
    );
    const defaultPeriodId = activePeriods[0]?.id ?? '';
    const defaultSchoolId = schools[0]?.id ?? '';
    const [periodId, setPeriodId] = useState<string>(String(defaultPeriodId));
    const [gradeLevelId, setGradeLevelId] = useState('');
    const [gradeName, setGradeName] = useState('');
    const [branchId, setBranchId] = useState('');
    const [specializationId, setSpecializationId] = useState('');
    const [specializationName, setSpecializationName] = useState('');
    const [gender, setGender] = useState('1');
    const [schoolId, setSchoolId] = useState(String(defaultSchoolId));
    const [departmentName, setDepartmentName] = useState('');
    const [applicationAt, setApplicationAt] = useState(admissionDateTimeNow);

    const filteredDepartments = useMemo(() => {
        if (branchId === '') {
            return departments;
        }

        return departments.filter(
            (department) =>
                department.branch_id == null || String(department.branch_id) === branchId,
        );
    }, [branchId, departments]);

    const filteredSpecializations = useMemo(() => {
        const selectedDepartment = filteredDepartments.find(
            (department) => department.name === departmentName,
        );
        if (!selectedDepartment) {
            return specializations;
        }

        return specializations.filter(
            (item) =>
                item.department_id == null
                || item.department_id === selectedDepartment.id,
        );
    }, [departmentName, filteredDepartments, specializations]);

    useEffect(() => {
        if (open) {
            setPeriodId(String(activePeriods[0]?.id ?? ''));
            setGradeLevelId('');
            setGradeName('');
            setBranchId('');
            setSpecializationId('');
            setSpecializationName('');
            setGender('1');
            setSchoolId(String(schools[0]?.id ?? ''));
            setDepartmentName('');
            setApplicationAt(admissionDateTimeNow());
        }
    }, [open, activePeriods, schools]);

    const selectedPeriod = activePeriods.find((period) => String(period.id) === periodId);

    if (!canManage) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sis-admission-draft-dialog max-h-[90vh] overflow-y-auto sm:max-w-5xl"
                dir="rtl"
                lang="ar"
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
                    <DialogTitle>{i18n.admission.draftDialogTitle}</DialogTitle>
                </DialogHeader>

                {activePeriods.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{i18n.admission.noOpenPeriod}</p>
                ) : (
                    <Form
                        action="/admission/applications"
                        method="post"
                        className="sis-admission-draft-form"
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ errors, processing }) => (
                            <>
                                {academicYearId !== null ? (
                                    <input type="hidden" name="academic_year_id" value={academicYearId} />
                                ) : null}
                                <div className="sis-admission-draft-rows">
                                    <div className="sis-admission-draft-row sis-admission-draft-row--3">
                                        <OpsFormField
                                            label={i18n.admission.periodName}
                                            name="application_period_id"
                                            error={errors.application_period_id}
                                        >
                                            <SisListSelect
                                                name="application_period_id"
                                                required
                                                value={periodId}
                                                options={activePeriods.map((period) => ({
                                                    value: String(period.id),
                                                    label: period.name,
                                                }))}
                                                onChange={setPeriodId}
                                                triggerClassName={`sis-ops-hub__link sis-admission-draft-control${filledClass(periodId)}`}
                                                dir="rtl"
                                                ariaLabel={i18n.admission.periodName}
                                            />
                                        </OpsFormField>

                                        <OpsFormField label={i18n.admission.periodNumber} name="period_number">
                                            <div
                                                className={`sis-ops-hub__link sis-admission-draft-control sis-admission-draft-readonly${selectedPeriod ? ' sis-admission-draft-field--filled' : ''}`}
                                                dir="ltr"
                                                aria-live="polite"
                                            >
                                                {selectedPeriod ? selectedPeriod.id : '—'}
                                            </div>
                                        </OpsFormField>

                                        <OpsFormField
                                            label={i18n.admission.applicationDate}
                                            name="application_at"
                                        >
                                            <div
                                                className="sis-ops-hub__link sis-admission-draft-control sis-admission-draft-readonly sis-admission-draft-field--filled sis-admission-draft-when-box"
                                                aria-live="polite"
                                            >
                                                <DraftApplicationWhen value={applicationAt} />
                                            </div>
                                        </OpsFormField>
                                    </div>

                                    <div className="sis-admission-draft-row sis-admission-draft-row--5">
                                        <OpsFormField
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
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
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
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
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
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
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
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
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
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                    </div>

                                    <div className="sis-admission-draft-row sis-admission-draft-row--3">
                                        <OpsFormField
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
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
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
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
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
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                    </div>

                                    <div className="sis-admission-draft-row sis-admission-draft-row--3">
                                        <OpsFormField
                                            label={i18n.admission.birthDate}
                                            name="birth_date"
                                            error={errors.birth_date}
                                        >
                                            <OpsTextInput
                                                name="birth_date"
                                                type="date"
                                                required
                                                error={errors.birth_date}
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
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
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
                                            label={i18n.admission.gender}
                                            name="gender"
                                            error={errors.gender}
                                        >
                                            <SisListSelect
                                                name="gender"
                                                required
                                                value={gender}
                                                options={[
                                                    { value: '1', label: i18n.admission.genderMale },
                                                    { value: '2', label: i18n.admission.genderFemale },
                                                ]}
                                                onChange={setGender}
                                                triggerClassName={`sis-ops-hub__link sis-admission-draft-control${filledClass(gender)}`}
                                                dir="rtl"
                                                ariaLabel={i18n.admission.gender}
                                            />
                                        </OpsFormField>
                                    </div>

                                    <div className="sis-admission-draft-row sis-admission-draft-row--3">
                                        <OpsFormField
                                            label={i18n.admission.nationalId}
                                            name="national_id"
                                            error={errors.national_id}
                                        >
                                            <OpsTextInput
                                                name="national_id"
                                                dir="ltr"
                                                placeholder=" "
                                                error={errors.national_id}
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
                                            label={i18n.admission.governorate}
                                            name="governorate"
                                            error={errors.governorate}
                                        >
                                            <OpsTextInput
                                                name="governorate"
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.governorate}
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
                                            label={i18n.admission.neighborhood}
                                            name="neighborhood"
                                            error={errors.neighborhood}
                                        >
                                            <OpsTextInput
                                                name="neighborhood"
                                                dir="rtl"
                                                placeholder=" "
                                                error={errors.neighborhood}
                                                className="sis-admission-draft-control"
                                                onChange={markFilled}
                                            />
                                        </OpsFormField>
                                    </div>

                                    <div className="sis-admission-draft-row sis-admission-draft-row--4">
                                        <OpsFormField
                                            label={i18n.admission.school}
                                            name="target_school_id"
                                            error={errors.target_school_id}
                                        >
                                            <SisListSelect
                                                name="target_school_id"
                                                required
                                                value={schoolId}
                                                options={schools.map((school) => ({
                                                    value: String(school.id),
                                                    label: school.name,
                                                }))}
                                                onChange={setSchoolId}
                                                triggerClassName={`sis-ops-hub__link sis-admission-draft-control${filledClass(schoolId)}`}
                                                dir="rtl"
                                                ariaLabel={i18n.admission.school}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
                                            label={i18n.admission.gradeLevel}
                                            name="grade_level_id"
                                            error={errors.grade_level_id ?? errors.intended_grade_name}
                                        >
                                            <SisListSelect
                                                name="grade_level_id"
                                                required
                                                value={gradeLevelId}
                                                options={[
                                                    { value: '', label: i18n.admission.selectOption },
                                                    ...gradeLevels.map((level) => ({
                                                        value: String(level.id),
                                                        label: level.name,
                                                    })),
                                                ]}
                                                onChange={(next) => {
                                                    const option = gradeLevels.find(
                                                        (level) => String(level.id) === next,
                                                    );
                                                    setGradeLevelId(next);
                                                    setGradeName(option?.name ?? '');
                                                }}
                                                triggerClassName={`sis-ops-hub__link sis-admission-draft-control${filledClass(gradeLevelId)}`}
                                                dir="rtl"
                                                ariaLabel={i18n.admission.gradeLevel}
                                            />
                                            <input type="hidden" name="intended_grade_name" value={gradeName} />
                                        </OpsFormField>
                                        <OpsFormField
                                            label={i18n.admission.branch}
                                            name="branch_id"
                                            error={errors.branch_id}
                                        >
                                            <SisListSelect
                                                name="branch_id"
                                                value={branchId}
                                                options={[
                                                    { value: '', label: i18n.admission.selectOption },
                                                    ...branches.map((branch) => ({
                                                        value: String(branch.id),
                                                        label: branch.name,
                                                    })),
                                                ]}
                                                onChange={(next) => {
                                                    setBranchId(next);
                                                    setDepartmentName('');
                                                    setSpecializationId('');
                                                    setSpecializationName('');
                                                }}
                                                triggerClassName={`sis-ops-hub__link sis-admission-draft-control${filledClass(branchId)}`}
                                                dir="rtl"
                                                ariaLabel={i18n.admission.branch}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
                                            label={i18n.admission.department}
                                            name="department_name"
                                            error={errors.department_name}
                                        >
                                            <SisListSelect
                                                name="department_name"
                                                value={departmentName}
                                                options={[
                                                    { value: '', label: i18n.admission.selectOption },
                                                    ...filteredDepartments.map((department) => ({
                                                        value: department.name,
                                                        label: department.name,
                                                    })),
                                                ]}
                                                onChange={(next) => {
                                                    setDepartmentName(next);
                                                    setSpecializationId('');
                                                    setSpecializationName('');
                                                }}
                                                triggerClassName={`sis-ops-hub__link sis-admission-draft-control${filledClass(departmentName)}`}
                                                dir="rtl"
                                                ariaLabel={i18n.admission.department}
                                            />
                                        </OpsFormField>
                                        <OpsFormField
                                            label={i18n.admission.specialization}
                                            name="specialization_id"
                                            error={errors.specialization_id}
                                        >
                                            <SisListSelect
                                                name="specialization_id"
                                                value={specializationId}
                                                options={[
                                                    { value: '', label: i18n.admission.selectOption },
                                                    ...filteredSpecializations.map((item) => ({
                                                        value: String(item.id),
                                                        label: item.name,
                                                    })),
                                                ]}
                                                onChange={(next) => {
                                                    const option = filteredSpecializations.find(
                                                        (item) => String(item.id) === next,
                                                    );
                                                    setSpecializationId(next);
                                                    setSpecializationName(option?.name ?? '');
                                                }}
                                                triggerClassName={`sis-ops-hub__link sis-admission-draft-control${filledClass(specializationId)}`}
                                                dir="rtl"
                                                ariaLabel={i18n.admission.specialization}
                                            />
                                            <input
                                                type="hidden"
                                                name="specialization_name"
                                                value={specializationName}
                                            />
                                        </OpsFormField>
                                    </div>
                                </div>

                                <div className="sis-admission-draft-actions">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => onOpenChange(false)}
                                    >
                                        {i18n.dialog.cancel}
                                    </Button>
                                    <Button type="submit" disabled={processing || schools.length === 0}>
                                        {i18n.admission.createDraft}
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
