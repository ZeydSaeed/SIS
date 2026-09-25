import { Form, router, usePage } from '@inertiajs/react';
import { Pencil, Save, Trash2 } from 'lucide-react';
import {
    forwardRef,
    useCallback,
    useEffect,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
} from 'react';
import { AdmissionDateTimeField } from '@/components/admission/admission-date-time-field';
import { parseAdmissionDateTime } from '@/components/admission/format-admission-datetime';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { usePageError } from '@/components/sis/page-error-context';
import {
    useRegisterPageRibbon,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
import { SisListSelect } from '@/components/sis/sis-list-select';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { Button } from '@/components/ui/button';
import { useAdmissionSearchQuery } from '@/components/admission/admission-search-context';
import { useAdmissionSelectionClearer } from '@/components/admission/admission-selection';
import {
    admissionQueryMatches,
    admissionSearchSegments,
} from '@/components/admission/admission-workspace';
import { t } from '@/i18n';

export type AdmissionPeriodRow = {
    id: number;
    academic_year_id: number;
    name: string;
    start_date: string;
    end_date: string;
    max_applications: number | null;
    status: number;
};

function catalogAcademicYears(years: YearOption[]): YearOption[] {
    return years.filter((year) => {
        const startYear = Number((year.start_date ?? '').slice(0, 4));
        if (Number.isFinite(startYear) && startYear >= 2020 && startYear <= 2040) {
            return true;
        }

        const token = year.code.match(/20\d{2}/);
        if (token === null) {
            return false;
        }

        const parsed = Number(token[0]);

        return parsed >= 2020 && parsed <= 2040;
    });
}

function yearBounds(year: YearOption | undefined): { start?: string; end?: string } {
    return {
        start: year?.start_date,
        end: year?.end_date,
    };
}

type Props = {
    periods: AdmissionPeriodRow[];
    periodCounts?: Record<number, { total: number; submitted: number }>;
    academicYearId: number | null;
    canManage: boolean;
};

function periodStatusLabel(status: number): string {
    const i18n = t().admission;
    if (status === 0) return i18n.periodInactive;
    if (status === 1) return i18n.periodActive;
    if (status === 2) return i18n.periodArchived;
    return String(status);
}

function AdmissionPeriodWhen({ value }: { value: string }) {
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

export type PeriodRowHandle = {
    save: () => void;
};

type PeriodEditorRowProps = {
    period: AdmissionPeriodRow;
    serial: number;
    years: YearOption[];
    canManage: boolean;
    selected: boolean;
    editing: boolean;
    searchQuery: string;
    submittedCount: number;
    remaining: number | null;
    onSelect: (periodId: number) => void;
    onSaved: () => void;
};

const PeriodEditorRow = forwardRef<PeriodRowHandle, PeriodEditorRowProps>(function PeriodEditorRow(
    {
        period,
        serial,
        years,
        canManage,
        selected,
        editing,
        searchQuery,
        submittedCount,
        remaining,
        onSelect,
        onSaved,
    },
    ref,
) {
    const i18n = t().admission;
    const { showError, showInertiaErrors } = usePageError();
    const errorsI18n = t().errors;
    const [name, setName] = useState(period.name);
    const [academicYearId, setAcademicYearId] = useState(period.academic_year_id);
    const [startDate, setStartDate] = useState(period.start_date);
    const [endDate, setEndDate] = useState(period.end_date);
    const [maxApplications, setMaxApplications] = useState(
        period.max_applications === null ? '' : String(period.max_applications),
    );
    const [saving, setSaving] = useState(false);
    const selectedYear = years.find((year) => year.id === academicYearId);
    const bounds = yearBounds(selectedYear);
    const yearReady = academicYearId > 0;
    const yearLabel = selectedYear
        ? formatAcademicYearOptionLabel(selectedYear.name, selectedYear.code)
        : String(period.academic_year_id);

    useEffect(() => {
        if (editing) {
            return;
        }

        setName(period.name);
        setAcademicYearId(period.academic_year_id);
        setStartDate(period.start_date);
        setEndDate(period.end_date);
        setMaxApplications(period.max_applications === null ? '' : String(period.max_applications));
    }, [editing, period]);

    const save = useCallback(() => {
        if (!yearReady || saving) {
            return;
        }

        if (startDate === '' || endDate === '') {
            showError(errorsI18n.requiredFields);
            return;
        }

        setSaving(true);
        router.put(
            `/admission/periods/${period.id}`,
            {
                academic_year_id: academicYearId,
                name,
                start_date: startDate,
                end_date: endDate,
                max_applications: maxApplications === '' ? null : Number(maxApplications),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => onSaved(),
                onError: (errors) => showInertiaErrors(errors, errorsI18n.saveFailed),
                onFinish: () => setSaving(false),
            },
        );
    }, [
        academicYearId,
        endDate,
        errorsI18n.requiredFields,
        errorsI18n.saveFailed,
        maxApplications,
        name,
        onSaved,
        period.id,
        saving,
        showError,
        showInertiaErrors,
        startDate,
        yearReady,
    ]);

    useImperativeHandle(ref, () => ({ save }), [save]);

    const changeStatus = (status: string) => {
        if (!yearReady) {
            return;
        }

        router.patch(
            `/admission/periods/${period.id}/status`,
            {
                academic_year_id: academicYearId,
                status: Number(status),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => showInertiaErrors(errors, errorsI18n.statusFailed),
            },
        );
    };

    return (
        <tr
            className={selected ? 'sis-admission-periods-table__row--selected' : undefined}
            aria-selected={selected}
            onClick={() => onSelect(period.id)}
        >
            <td className="sis-admission-periods-table__num">
                <span dir="ltr">{serial}</span>
            </td>
            <td className="sis-admission-periods-table__year">
                {editing ? (
                    <SisListSelect
                        value={String(academicYearId)}
                        options={years.map((year) => ({
                            value: String(year.id),
                            label: formatAcademicYearOptionLabel(year.name, year.code),
                        }))}
                        onChange={(next) => setAcademicYearId(Number(next))}
                        triggerClassName="sis-ops-hub__link"
                        dir="ltr"
                        ariaLabel={i18n.academicYear}
                    />
                ) : (
                    <span dir="ltr">{yearLabel}</span>
                )}
            </td>
            <td>
                {editing ? (
                    <input
                        className="sis-ops-hub__link"
                        name={`period-${period.id}-name`}
                        value={name}
                        dir="rtl"
                        aria-label={i18n.periodName}
                        onChange={(event) => setName(event.target.value)}
                    />
                ) : (
                    admissionSearchSegments(period.name, searchQuery).map((segment, index) =>
                        segment.hit ? (
                            <mark key={`hit-${index}`} className="sis-admission-search-hit">
                                {segment.text}
                            </mark>
                        ) : (
                            <span key={`plain-${index}`}>{segment.text}</span>
                        ),
                    )
                )}
            </td>
            <td className="sis-admission-periods-table__when-cell">
                {editing ? (
                    <AdmissionDateTimeField
                        key={`start-${period.id}-${academicYearId}`}
                        name={`start_date_${period.id}`}
                        idPrefix={`edit-${period.id}`}
                        defaultValue={period.start_date}
                        required
                        boundStart={bounds.start}
                        boundEnd={bounds.end}
                        onValueChange={setStartDate}
                    />
                ) : (
                    <AdmissionPeriodWhen value={period.start_date} />
                )}
            </td>
            <td className="sis-admission-periods-table__when-cell">
                {editing ? (
                    <AdmissionDateTimeField
                        key={`end-${period.id}-${academicYearId}`}
                        name={`end_date_${period.id}`}
                        idPrefix={`edit-${period.id}`}
                        defaultValue={period.end_date}
                        required
                        boundStart={bounds.start}
                        boundEnd={bounds.end}
                        onValueChange={setEndDate}
                    />
                ) : (
                    <AdmissionPeriodWhen value={period.end_date} />
                )}
            </td>
            <td className="sis-admission-periods-table__max">
                {editing ? (
                    <input
                        className="sis-ops-hub__link"
                        type="number"
                        min={1}
                        dir="ltr"
                        value={maxApplications}
                        aria-label={i18n.maxApplications}
                        onChange={(event) => setMaxApplications(event.target.value)}
                    />
                ) : (
                    <span dir="ltr">{period.max_applications ?? '—'}</span>
                )}
            </td>
            <td className="sis-admission-periods-table__num" dir="ltr">
                {submittedCount}
            </td>
            <td className="sis-admission-periods-table__num" dir="ltr">
                {remaining === null ? i18n.unlimitedCapacity : remaining}
            </td>
            <td
                className="sis-admission-periods-table__status"
                data-status={period.status}
            >
                {canManage ? (
                    <SisListSelect
                        value={String(period.status)}
                        options={[
                            { value: '0', label: i18n.periodInactive },
                            { value: '1', label: i18n.periodActive },
                            { value: '2', label: i18n.periodArchived },
                        ]}
                        onChange={changeStatus}
                        disabled={!yearReady}
                        triggerClassName="sis-admission-periods-table__status-select"
                        dir="rtl"
                        ariaLabel={i18n.periodStatus}
                    />
                ) : (
                    periodStatusLabel(period.status)
                )}
            </td>
        </tr>
    );
});

export function AdmissionPeriodsCard({
    periods,
    periodCounts = {},
    academicYearId,
    canManage,
}: Props) {
    const i18n = t();
    const { showInertiaErrors } = usePageError();
    const searchQuery = useAdmissionSearchQuery();
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const years = useMemo(() => {
        const all = academicYears ?? [];
        const catalog = catalogAcademicYears(all);
        const byId = new Map(catalog.map((year) => [year.id, year]));

        for (const period of periods) {
            if (!byId.has(period.academic_year_id)) {
                const found = all.find((year) => year.id === period.academic_year_id);
                if (found) {
                    byId.set(found.id, found);
                }
            }
        }

        return [...byId.values()].sort((left, right) =>
            (left.start_date ?? '').localeCompare(right.start_date ?? ''),
        );
    }, [academicYears, periods]);
    const [createYearId, setCreateYearId] = useState<number | null>(academicYearId);
    const createYear = years.find((year) => year.id === createYearId);
    const createBounds = yearBounds(createYear);
    const selectedRowRef = useRef<PeriodRowHandle>(null);
    const tableRef = useRef<HTMLTableElement>(null);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [editing, setEditing] = useState(false);
    const [archiveTarget, setArchiveTarget] = useState<AdmissionPeriodRow | null>(null);
    const [archiving, setArchiving] = useState(false);
    const sorted = useMemo(
        () => [...periods].sort((left, right) => left.id - right.id),
        [periods],
    );
    const visible = useMemo(() => {
        if (searchQuery.trim() === '') {
            return sorted;
        }

        return sorted.filter((period) =>
            admissionQueryMatches(
                [period.name, period.start_date, period.end_date, String(period.id)].join(' '),
                searchQuery,
            ),
        );
    }, [searchQuery, sorted]);

    useResizableTableColumns(tableRef, {
        storageKey: 'admission.periods',
        columnSignature: 'v1',
        enabled: visible.length > 0,
    });

    const yearReady = academicYearId !== null;
    const hasSelection = selectedId !== null;

    useEffect(() => {
        setCreateYearId(academicYearId);
    }, [academicYearId]);

    useEffect(() => {
        if (selectedId !== null && !visible.some((period) => period.id === selectedId)) {
            setSelectedId(null);
            setEditing(false);
        }
    }, [selectedId, visible]);

    const selectRow = useCallback((periodId: number) => {
        setSelectedId(periodId);
        setEditing((wasEditing) => (selectedId === periodId ? wasEditing : false));
    }, [selectedId]);

    const exitEditing = useCallback(() => setEditing(false), []);

    const clearTableSelection = useCallback(() => {
        setSelectedId(null);
        setEditing(false);
    }, []);

    useAdmissionSelectionClearer(clearTableSelection, hasSelection || editing);

    const ribbonGroups = useMemo((): PageRibbonGroup[] => {
        if (!canManage) {
            return [];
        }

        return [
            {
                id: 'admission-period-actions',
                label: i18n.common.actions,
                commands: [
                    {
                        id: 'edit-period',
                        label: i18n.common.edit,
                        icon: Pencil,
                        disabled: !yearReady || !hasSelection,
                        onSelect: () => setEditing(true),
                    },
                    {
                        id: 'save-period',
                        label: i18n.common.save,
                        icon: Save,
                        disabled: !yearReady || !hasSelection || !editing,
                        onSelect: () => selectedRowRef.current?.save(),
                    },
                    {
                        id: 'delete-period',
                        label: i18n.common.delete,
                        icon: Trash2,
                        disabled: !yearReady || !hasSelection,
                        onSelect: () => {
                            const row = sorted.find((period) => period.id === selectedId);

                            if (row) {
                                setArchiveTarget(row);
                            }
                        },
                    },
                ],
            },
        ];
    }, [
        canManage,
        editing,
        hasSelection,
        i18n.common.actions,
        i18n.common.delete,
        i18n.common.edit,
        i18n.common.save,
        selectedId,
        sorted,
        yearReady,
    ]);

    useRegisterPageRibbon('home', ribbonGroups);

    const confirmArchive = () => {
        if (archiveTarget === null || academicYearId === null) {
            return;
        }

        setArchiving(true);
        router.post(
            `/admission/periods/${archiveTarget.id}/archive`,
            { academic_year_id: academicYearId },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if (selectedId === archiveTarget.id) {
                        setSelectedId(null);
                        setEditing(false);
                    }
                },
                onError: (errors) => showInertiaErrors(errors, i18n.errors.deleteFailed),
                onFinish: () => {
                    setArchiving(false);
                    setArchiveTarget(null);
                },
            },
        );
    };

    return (
        <section aria-label={i18n.admission.periodsTitle} className="sis-admission-periods">
            <div className="sis-admission-period-panel">
                {canManage ? (
                    <Form
                        action="/admission/periods"
                        method="post"
                        className="sis-admission-period-create"
                        options={{ preserveScroll: true }}
                        onError={(errors) => showInertiaErrors(errors, i18n.errors.createFailed)}
                    >
                        {({ errors, processing }) => (
                            <>
                                <OpsFormField
                                    label={i18n.admission.academicYear}
                                    name="academic_year_id"
                                    error={errors.academic_year_id}
                                >
                                    <SisListSelect
                                        name="academic_year_id"
                                        required
                                        value={createYearId === null ? '' : String(createYearId)}
                                        options={years.map((year) => ({
                                            value: String(year.id),
                                            label: formatAcademicYearOptionLabel(year.name, year.code),
                                        }))}
                                        onChange={(next) =>
                                            setCreateYearId(next === '' ? null : Number(next))
                                        }
                                        triggerClassName="sis-ops-hub__link"
                                        dir="ltr"
                                        ariaLabel={i18n.admission.academicYear}
                                    />
                                </OpsFormField>
                                <OpsFormField
                                    label={i18n.admission.periodName}
                                    name="name"
                                    error={errors.name}
                                >
                                    <OpsTextInput name="name" required dir="rtl" error={errors.name} />
                                </OpsFormField>
                                <OpsFormField
                                    label={i18n.admission.startDate}
                                    name="start_date"
                                    error={errors.start_date}
                                >
                                    <AdmissionDateTimeField
                                        key={`create-start-${createYearId ?? 'none'}`}
                                        name="start_date"
                                        required
                                        error={errors.start_date}
                                        boundStart={createBounds.start}
                                        boundEnd={createBounds.end}
                                    />
                                </OpsFormField>
                                <OpsFormField
                                    label={i18n.admission.endDate}
                                    name="end_date"
                                    error={errors.end_date}
                                >
                                    <AdmissionDateTimeField
                                        key={`create-end-${createYearId ?? 'none'}`}
                                        name="end_date"
                                        required
                                        error={errors.end_date}
                                        boundStart={createBounds.start}
                                        boundEnd={createBounds.end}
                                    />
                                </OpsFormField>
                                <OpsFormField
                                    label={i18n.admission.maxApplications}
                                    name="max_applications"
                                    error={errors.max_applications}
                                >
                                    <OpsTextInput
                                        name="max_applications"
                                        type="number"
                                        min={1}
                                        error={errors.max_applications}
                                    />
                                </OpsFormField>
                                <div className="sis-admission-period-panel__submit">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        className="sis-admission-period-submit"
                                        disabled={processing || createYearId === null}
                                    >
                                        {i18n.admission.openPeriod}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : null}

                {sorted.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{i18n.admission.emptyPeriods}</p>
                ) : visible.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{i18n.admission.emptySearch}</p>
                ) : (
                    <div className="sis-admission-periods-table">
                        <div className="sis-admission-periods-table__scroller" data-allow-x-scroll>
                            <table ref={tableRef}>
                                <thead>
                                    <tr>
                                        <th className="sis-admission-periods-table__num">#</th>
                                        <th>{i18n.admission.academicYear}</th>
                                        <th>{i18n.admission.periodName}</th>
                                        <th>{i18n.admission.startDate}</th>
                                        <th>{i18n.admission.endDate}</th>
                                        <th className="sis-admission-periods-table__max">
                                            {i18n.admission.maxApplications}
                                        </th>
                                        <th>{i18n.admission.periodApplicationsCount}</th>
                                        <th>{i18n.admission.remainingInPeriod}</th>
                                        <th className="sis-admission-periods-table__status">
                                            {i18n.admission.periodStatus}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visible.map((period, index) => {
                                        const counts = periodCounts[period.id] ?? {
                                            total: 0,
                                            submitted: 0,
                                        };
                                        const remaining =
                                            period.max_applications === null
                                                ? null
                                                : Math.max(0, period.max_applications - counts.total);

                                        return (
                                            <PeriodEditorRow
                                                key={period.id}
                                                ref={selectedId === period.id ? selectedRowRef : null}
                                                period={period}
                                                serial={index + 1}
                                                years={years}
                                                canManage={canManage}
                                                selected={selectedId === period.id}
                                                editing={editing && selectedId === period.id}
                                                searchQuery={searchQuery}
                                                submittedCount={counts.submitted}
                                                remaining={remaining}
                                                onSelect={selectRow}
                                                onSaved={exitEditing}
                                            />
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
            <ConfirmDialog
                open={archiveTarget !== null}
                title={i18n.admission.archivePeriodTitle}
                description={i18n.admission.archivePeriodConfirm}
                confirmLabel={i18n.admission.deletePeriod}
                tone="danger"
                confirmPending={archiving}
                onConfirm={confirmArchive}
                onOpenChange={(open) => {
                    if (!open && !archiving) {
                        setArchiveTarget(null);
                    }
                }}
            />
        </section>
    );
}
