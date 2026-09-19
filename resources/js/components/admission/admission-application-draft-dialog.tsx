import { Form } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ChangeEvent } from 'react';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
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
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    periods: DraftPeriodOption[];
    schools: DraftSchoolOption[];
    gradeLevels: DraftNamedOption[];
    departments: DraftNamedOption[];
    specializations: DraftNamedOption[];
    canManage: boolean;
};

function filledClass(value: string): string {
    return value.trim() !== '' ? ' sis-admission-draft-field--filled' : '';
}

function markFilled(event: ChangeEvent<HTMLInputElement | HTMLSelectElement>): void {
    const el = event.currentTarget;
    el.classList.toggle('sis-admission-draft-field--filled', el.value.trim() !== '');
}

/** Admission draft dialog — row layout, compact fields. */
export function AdmissionApplicationDraftDialog({
    open,
    onOpenChange,
    periods,
    schools,
    gradeLevels,
    departments,
    specializations,
    canManage,
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
    const [specializationId, setSpecializationId] = useState('');
    const [specializationName, setSpecializationName] = useState('');

    useEffect(() => {
        if (open) {
            setPeriodId(String(activePeriods[0]?.id ?? ''));
            setGradeLevelId('');
            setGradeName('');
            setSpecializationId('');
            setSpecializationName('');
        }
    }, [open, activePeriods]);

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
                                <div className="sis-admission-draft-rows">
                                    <div className="sis-admission-draft-row sis-admission-draft-row--2">
                                        <OpsFormField
                                            label={i18n.admission.periodName}
                                            name="application_period_id"
                                            error={errors.application_period_id}
                                        >
                                            <select
                                                name="application_period_id"
                                                required
                                                className={`sis-ops-hub__link sis-admission-draft-control${filledClass(periodId)}`}
                                                value={periodId}
                                                onChange={(event) => {
                                                    setPeriodId(event.target.value);
                                                    markFilled(event);
                                                }}
                                            >
                                                {activePeriods.map((period) => (
                                                    <option key={period.id} value={period.id}>
                                                        {period.name}
                                                    </option>
                                                ))}
                                            </select>
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
                                            <select
                                                name="gender"
                                                required
                                                className="sis-ops-hub__link sis-admission-draft-control sis-admission-draft-field--filled"
                                                defaultValue={1}
                                                onChange={markFilled}
                                            >
                                                <option value={1}>{i18n.admission.genderMale}</option>
                                                <option value={2}>{i18n.admission.genderFemale}</option>
                                            </select>
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
                                            <select
                                                name="target_school_id"
                                                required
                                                className={`sis-ops-hub__link sis-admission-draft-control${filledClass(String(defaultSchoolId))}`}
                                                defaultValue={defaultSchoolId}
                                                onChange={markFilled}
                                            >
                                                {schools.map((school) => (
                                                    <option key={school.id} value={school.id}>
                                                        {school.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </OpsFormField>
                                        <OpsFormField
                                            label={i18n.admission.gradeLevel}
                                            name="grade_level_id"
                                            error={errors.grade_level_id ?? errors.intended_grade_name}
                                        >
                                            <select
                                                name="grade_level_id"
                                                required
                                                className={`sis-ops-hub__link sis-admission-draft-control${filledClass(gradeLevelId)}`}
                                                value={gradeLevelId}
                                                onChange={(event) => {
                                                    const nextId = event.target.value;
                                                    const option = event.target.selectedOptions[0];
                                                    setGradeLevelId(nextId);
                                                    setGradeName(nextId === '' ? '' : (option?.text ?? ''));
                                                    markFilled(event);
                                                }}
                                            >
                                                <option value="">{i18n.admission.selectOption}</option>
                                                {gradeLevels.map((level) => (
                                                    <option key={level.id} value={level.id}>
                                                        {level.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <input type="hidden" name="intended_grade_name" value={gradeName} />
                                        </OpsFormField>
                                        <OpsFormField
                                            label={i18n.admission.department}
                                            name="department_name"
                                            error={errors.department_name}
                                        >
                                            <select
                                                name="department_name"
                                                className="sis-ops-hub__link sis-admission-draft-control"
                                                defaultValue=""
                                                onChange={markFilled}
                                            >
                                                <option value="">{i18n.admission.selectOption}</option>
                                                {departments.map((department) => (
                                                    <option key={department.id} value={department.name}>
                                                        {department.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </OpsFormField>
                                        <OpsFormField
                                            label={i18n.admission.specialization}
                                            name="specialization_id"
                                            error={errors.specialization_id}
                                        >
                                            <select
                                                name="specialization_id"
                                                className={`sis-ops-hub__link sis-admission-draft-control${filledClass(specializationId)}`}
                                                value={specializationId}
                                                onChange={(event) => {
                                                    const nextId = event.target.value;
                                                    const option = event.target.selectedOptions[0];
                                                    setSpecializationId(nextId);
                                                    setSpecializationName(
                                                        nextId === '' ? '' : (option?.text ?? ''),
                                                    );
                                                    markFilled(event);
                                                }}
                                            >
                                                <option value="">{i18n.admission.selectOption}</option>
                                                {specializations.map((item) => (
                                                    <option key={item.id} value={item.id}>
                                                        {item.name}
                                                    </option>
                                                ))}
                                            </select>
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
