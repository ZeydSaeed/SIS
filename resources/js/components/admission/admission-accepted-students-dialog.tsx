import { useEffect, useMemo, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { formatAcademicYearOptionLabel } from '@/components/sis/ops-year-filter';
import type { AdmissionAcceptedStudent } from '@/components/admission/admission-workspace';
import { t } from '@/i18n';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    students: AdmissionAcceptedStudent[];
    defaultAcademicYearId?: number | null;
};

/** Resizable accepted-students roster with year/period filters. */
export function AdmissionAcceptedStudentsDialog({
    open,
    onOpenChange,
    students,
    defaultAcademicYearId = null,
}: Props) {
    const i18n = t().admission;
    const [yearId, setYearId] = useState<string>('');
    const [periodId, setPeriodId] = useState<string>('');

    const yearOptions = useMemo(() => {
        const map = new Map<number, string>();
        for (const student of students) {
            if (!map.has(student.academic_year_id)) {
                map.set(student.academic_year_id, student.academic_year_name);
            }
        }

        return [...map.entries()]
            .sort((left, right) => left[1].localeCompare(right[1], 'ar'))
            .map(([id, name]) => ({
                value: String(id),
                label: formatAcademicYearOptionLabel(name, ''),
            }));
    }, [students]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const preferred =
            defaultAcademicYearId != null
            && yearOptions.some((option) => option.value === String(defaultAcademicYearId))
                ? String(defaultAcademicYearId)
                : (yearOptions[0]?.value ?? '');
        setYearId(preferred);
        setPeriodId('');
    }, [open, defaultAcademicYearId, yearOptions]);

    const periodOptions = useMemo(() => {
        const map = new Map<number, string>();
        for (const student of students) {
            if (yearId !== '' && student.academic_year_id !== Number(yearId)) {
                continue;
            }
            if (!map.has(student.period_id)) {
                map.set(student.period_id, student.period_name);
            }
        }

        return [
            { value: '', label: i18n.allPeriods },
            ...[...map.entries()]
                .sort((left, right) => left[1].localeCompare(right[1], 'ar'))
                .map(([id, name]) => ({ value: String(id), label: name })),
        ];
    }, [students, yearId, i18n.allPeriods]);

    const yearLabel =
        yearOptions.find((option) => option.value === yearId)?.label
        ?? i18n.academicYear;
    const periodLabel =
        periodOptions.find((option) => option.value === periodId)?.label
        ?? i18n.filterByPeriod;

    const visible = useMemo(() => {
        return students.filter((student) => {
            if (yearId !== '' && student.academic_year_id !== Number(yearId)) {
                return false;
            }
            if (periodId !== '' && student.period_id !== Number(periodId)) {
                return false;
            }

            return true;
        });
    }, [students, yearId, periodId]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sis-admission-draft-dialog sis-admission-accepted-dialog"
                overlayClassName="sis-student-view-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>{i18n.acceptedStudentsDialogTitle}</DialogTitle>
                </DialogHeader>

                <div className="sis-admission-filters sis-admission-accepted-dialog__filters">
                    <label className="sis-admission-period-filter" dir="rtl">
                        <span className="shrink-0 font-medium">{i18n.academicYear}</span>
                        <span className="sis-admission-select-fit">
                            <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                {yearLabel}
                            </span>
                            <SisListSelect
                                value={yearId}
                                options={yearOptions}
                                onChange={(next) => {
                                    setYearId(next);
                                    setPeriodId('');
                                }}
                                triggerClassName="sis-ops-hub__link sis-admission-year-control"
                                dir="ltr"
                                ariaLabel={i18n.academicYear}
                            />
                        </span>
                    </label>
                    <label className="sis-admission-period-filter" dir="rtl">
                        <span className="shrink-0 font-medium">{i18n.filterByPeriod}</span>
                        <span className="sis-admission-select-fit">
                            <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                {periodLabel}
                            </span>
                            <SisListSelect
                                value={periodId}
                                options={periodOptions}
                                onChange={setPeriodId}
                                triggerClassName="sis-ops-hub__link sis-admission-year-control"
                                dir="rtl"
                                ariaLabel={i18n.filterByPeriod}
                            />
                        </span>
                    </label>
                </div>

                {visible.length === 0 ? (
                    <p className="sis-admission-accepted-dialog__empty">{i18n.emptyAcceptedStudents}</p>
                ) : (
                    <div className="sis-admission-accepted-dialog__scroller">
                        <table className="sis-admission-accepted-dialog__table">
                            <thead>
                                <tr>
                                    <th scope="col" className="sis-admission-accepted-dialog__num">
                                        #
                                    </th>
                                    <th scope="col" className="sis-admission-accepted-dialog__name">
                                        {i18n.acceptedStudentName}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((student, index) => (
                                    <tr key={student.id}>
                                        <td className="sis-admission-accepted-dialog__num" dir="ltr">
                                            {index + 1}
                                        </td>
                                        <td className="sis-admission-accepted-dialog__name">
                                            {student.full_name}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
