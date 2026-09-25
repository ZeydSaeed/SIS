import { useEffect, useMemo, useState, type ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import type { AdmissionAcceptedStudent } from '@/components/admission/admission-workspace';
import { formatAcademicYearOptionLabel } from '@/components/sis/ops-year-filter';
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

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    students: AdmissionAcceptedStudent[];
    defaultAcademicYearId?: number | null;
};

/** 1 = academic→vocational transfer, 2 = vocational school intake */
const REQUEST_KIND_VOCATIONAL = 2;
const REQUEST_KIND_TRANSFER = 1;

function SheetSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="sis-admission-sheet__section">
            <h3 className="sis-admission-sheet__banner sis-admission-sheet__banner--accent">{title}</h3>
            <div className="sis-admission-sheet__body">{children}</div>
        </section>
    );
}

function SheetField({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <label className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            {children}
        </label>
    );
}

function channelOf(student: AdmissionAcceptedStudent): number {
    return student.request_kind ?? REQUEST_KIND_VOCATIONAL;
}

/** Accepted-students roster — fixed sheet; 3-col channel name lists. */
export function AdmissionAcceptedStudentsDialog({
    open,
    onOpenChange,
    students,
    defaultAcademicYearId = null,
}: Props) {
    const i18n = t();
    const admission = i18n.admission;
    const [yearId, setYearId] = useState<string>('');
    const [periodId, setPeriodId] = useState<string>('');
    const { contentRef, heroDragProps, bringToFront } = useSmoothDialogDrag(open);

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
            { value: '', label: admission.allPeriods },
            ...[...map.entries()]
                .sort((left, right) => left[1].localeCompare(right[1], 'ar'))
                .map(([id, name]) => ({ value: String(id), label: name })),
        ];
    }, [students, yearId, admission.allPeriods]);

    const filteredByYearPeriod = useMemo(() => {
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

    const vocationalStudents = useMemo(
        () => filteredByYearPeriod.filter((student) => channelOf(student) === REQUEST_KIND_VOCATIONAL),
        [filteredByYearPeriod],
    );

    const transferStudents = useMemo(
        () => filteredByYearPeriod.filter((student) => channelOf(student) === REQUEST_KIND_TRANSFER),
        [filteredByYearPeriod],
    );

    const tableRows = useMemo(() => {
        const rowCount = Math.max(vocationalStudents.length, transferStudents.length);

        return Array.from({ length: rowCount }, (_, index) => ({
            key: `${vocationalStudents[index]?.id ?? 'v0'}-${transferStudents[index]?.id ?? 't0'}-${index}`,
            vocationalName: vocationalStudents[index]?.full_name ?? '',
            transferName: transferStudents[index]?.full_name ?? '',
        }));
    }, [vocationalStudents, transferStudents]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange} modal={false}>
            <DialogContent
                ref={contentRef}
                className="sis-admission-draft-dialog sis-admission-sheet-dialog sis-admission-accepted-dialog sm:max-w-4xl"
                overlayClassName="sis-admission-sheet-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onPointerDownCapture={bringToFront}
            >
                <DialogTitle className="sr-only">{admission.acceptedStudentsDialogTitle}</DialogTitle>

                <div className="sis-admission-sheet sis-admission-accepted-sheet">
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
                            <p className="sis-admission-sheet__hero-title">
                                {admission.acceptedStudentsDialogTitle}
                            </p>
                        </div>
                        <div className="sis-admission-sheet__hero-logo">
                            <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                        </div>
                    </header>

                    <SheetSection title={admission.sheetAcceptedFilters}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--2">
                            <SheetField label={admission.academicYear}>
                                <SisListSelect
                                    value={yearId}
                                    options={yearOptions}
                                    onChange={(next) => {
                                        setYearId(next);
                                        setPeriodId('');
                                    }}
                                    dir="ltr"
                                    ariaLabel={admission.academicYear}
                                    className={`sis-admission-sheet-list-select${yearId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${yearId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    menuClassName="sis-admission-sheet-list-select__menu"
                                />
                            </SheetField>
                            <SheetField label={admission.filterByPeriod}>
                                <SisListSelect
                                    value={periodId}
                                    options={periodOptions}
                                    onChange={setPeriodId}
                                    dir="rtl"
                                    ariaLabel={admission.filterByPeriod}
                                    className={`sis-admission-sheet-list-select${periodId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${periodId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    menuClassName="sis-admission-sheet-list-select__menu"
                                />
                            </SheetField>
                        </div>
                        <div className="sis-admission-accepted-sheet__stats" aria-live="polite">
                            <div className="sis-admission-accepted-sheet__stat">
                                <span className="sis-admission-accepted-sheet__stat-label">
                                    {admission.acceptedStatsVocational}
                                </span>
                                <span className="sis-admission-accepted-sheet__stat-value" dir="ltr">
                                    {vocationalStudents.length}
                                </span>
                            </div>
                            <div className="sis-admission-accepted-sheet__stat">
                                <span className="sis-admission-accepted-sheet__stat-label">
                                    {admission.acceptedStatsTransfer}
                                </span>
                                <span className="sis-admission-accepted-sheet__stat-value" dir="ltr">
                                    {transferStudents.length}
                                </span>
                            </div>
                        </div>
                    </SheetSection>

                    <div className="sis-admission-accepted-sheet__list-body">
                        {tableRows.length === 0 ? (
                            <p className="sis-admission-sheet__empty sis-admission-accepted-sheet__empty">
                                {admission.emptyAcceptedStudents}
                            </p>
                        ) : (
                            <div className="sis-admission-accepted-sheet__scroller">
                                <table className="sis-admission-accepted-sheet__table">
                                    <thead>
                                        <tr>
                                            <th scope="col" className="sis-admission-accepted-sheet__num">
                                                #
                                            </th>
                                            <th scope="col" className="sis-admission-accepted-sheet__channel">
                                                {admission.acceptedStatsVocational}
                                            </th>
                                            <th scope="col" className="sis-admission-accepted-sheet__channel">
                                                {admission.acceptedStatsTransfer}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tableRows.map((row, index) => (
                                            <tr key={row.key}>
                                                <td className="sis-admission-accepted-sheet__num" dir="ltr">
                                                    {index + 1}
                                                </td>
                                                <td className="sis-admission-accepted-sheet__channel">
                                                    {row.vocationalName}
                                                </td>
                                                <td className="sis-admission-accepted-sheet__channel">
                                                    {row.transferName}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {i18n.dialog.cancel}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
