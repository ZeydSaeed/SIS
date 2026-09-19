import { Form, router } from '@inertiajs/react';
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
import {
    useRegisterPageRibbon,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';

export type AdmissionPeriodRow = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    max_applications: number | null;
    status: number;
};

type Props = {
    periods: AdmissionPeriodRow[];
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
    academicYearId: number | null;
    canManage: boolean;
    selected: boolean;
    editing: boolean;
    onSelect: (periodId: number) => void;
    onSaved: () => void;
};

const PeriodEditorRow = forwardRef<PeriodRowHandle, PeriodEditorRowProps>(function PeriodEditorRow(
    { period, academicYearId, canManage, selected, editing, onSelect, onSaved },
    ref,
) {
    const i18n = t().admission;
    const [name, setName] = useState(period.name);
    const [startDate, setStartDate] = useState(period.start_date);
    const [endDate, setEndDate] = useState(period.end_date);
    const [maxApplications, setMaxApplications] = useState(
        period.max_applications === null ? '' : String(period.max_applications),
    );
    const [saving, setSaving] = useState(false);
    const yearReady = academicYearId !== null;

    useEffect(() => {
        if (editing) {
            return;
        }

        setName(period.name);
        setStartDate(period.start_date);
        setEndDate(period.end_date);
        setMaxApplications(period.max_applications === null ? '' : String(period.max_applications));
    }, [editing, period]);

    const save = useCallback(() => {
        if (!yearReady || saving || startDate === '' || endDate === '') {
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
                onFinish: () => setSaving(false),
            },
        );
    }, [
        academicYearId,
        endDate,
        maxApplications,
        name,
        onSaved,
        period.id,
        saving,
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
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <tr
            className={selected ? 'sis-admission-periods-table__row--selected' : undefined}
            aria-selected={selected}
            onClick={() => onSelect(period.id)}
        >
            <td className="sis-admission-periods-table__num" dir="ltr">
                {period.id}
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
                    period.name
                )}
            </td>
            <td className="sis-admission-periods-table__when-cell">
                {editing ? (
                    <AdmissionDateTimeField
                        name={`start_date_${period.id}`}
                        idPrefix={`edit-${period.id}`}
                        defaultValue={period.start_date}
                        required
                        onValueChange={setStartDate}
                    />
                ) : (
                    <AdmissionPeriodWhen value={period.start_date} />
                )}
            </td>
            <td className="sis-admission-periods-table__when-cell">
                {editing ? (
                    <AdmissionDateTimeField
                        name={`end_date_${period.id}`}
                        idPrefix={`edit-${period.id}`}
                        defaultValue={period.end_date}
                        required
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
            <td
                className="sis-admission-periods-table__status"
                data-status={period.status}
            >
                {canManage ? (
                    <select
                        className="sis-admission-periods-table__status-select"
                        value={period.status}
                        disabled={!yearReady}
                        aria-label={i18n.periodStatus}
                        onChange={(event) => changeStatus(event.target.value)}
                    >
                        <option value={0}>{i18n.periodInactive}</option>
                        <option value={1}>{i18n.periodActive}</option>
                        <option value={2}>{i18n.periodArchived}</option>
                    </select>
                ) : (
                    periodStatusLabel(period.status)
                )}
            </td>
        </tr>
    );
});

export function AdmissionPeriodsCard({ periods, academicYearId, canManage }: Props) {
    const i18n = t();
    const selectedRowRef = useRef<PeriodRowHandle>(null);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [editing, setEditing] = useState(false);
    const [archiveTarget, setArchiveTarget] = useState<AdmissionPeriodRow | null>(null);
    const [archiving, setArchiving] = useState(false);
    const sorted = useMemo(
        () => [...periods].sort((left, right) => left.id - right.id),
        [periods],
    );
    const yearReady = academicYearId !== null;
    const hasSelection = selectedId !== null;

    useEffect(() => {
        if (selectedId !== null && !sorted.some((period) => period.id === selectedId)) {
            setSelectedId(null);
            setEditing(false);
        }
    }, [selectedId, sorted]);

    const selectRow = useCallback((periodId: number) => {
        setSelectedId(periodId);
        setEditing((wasEditing) => (selectedId === periodId ? wasEditing : false));
    }, [selectedId]);

    const exitEditing = useCallback(() => setEditing(false), []);

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
                onFinish: () => {
                    setArchiving(false);
                    setArchiveTarget(null);
                },
            },
        );
    };

    return (
        <section aria-label={i18n.admission.periodsTitle} className="sis-admission-periods">
            <h2 className="sis-ops-hub__section-title sis-admission-periods-title text-base">
                {i18n.admission.periodsTitle}
            </h2>
            <div className="sis-admission-period-card">
                {canManage ? (
                    <Form
                        action="/admission/periods"
                        method="post"
                        className="sis-admission-period-create"
                        options={{ preserveScroll: true }}
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="academic_year_id"
                                    value={academicYearId ?? ''}
                                />
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
                                        name="start_date"
                                        required
                                        error={errors.start_date}
                                    />
                                </OpsFormField>
                                <OpsFormField
                                    label={i18n.admission.endDate}
                                    name="end_date"
                                    error={errors.end_date}
                                >
                                    <AdmissionDateTimeField
                                        name="end_date"
                                        required
                                        error={errors.end_date}
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
                                <div className="sis-admission-period-card__submit">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        className="sis-admission-period-submit"
                                        disabled={processing || academicYearId === null}
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
                ) : (
                    <div className="sis-admission-periods-table">
                        <table>
                            <thead>
                                <tr>
                                    <th className="sis-admission-periods-table__num">#</th>
                                    <th>{i18n.admission.periodName}</th>
                                    <th>{i18n.admission.startDate}</th>
                                    <th>{i18n.admission.endDate}</th>
                                    <th className="sis-admission-periods-table__max">
                                        {i18n.admission.maxApplications}
                                    </th>
                                    <th>{i18n.admission.periodStatus}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sorted.map((period) => (
                                    <PeriodEditorRow
                                        key={period.id}
                                        ref={selectedId === period.id ? selectedRowRef : null}
                                        period={period}
                                        academicYearId={academicYearId}
                                        canManage={canManage}
                                        selected={selectedId === period.id}
                                        editing={editing && selectedId === period.id}
                                        onSelect={selectRow}
                                        onSaved={exitEditing}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
            <ConfirmDialog
                open={archiveTarget !== null}
                title={i18n.admission.archivePeriodTitle}
                description={i18n.admission.archivePeriodConfirm}
                confirmLabel={i18n.admission.deletePeriod}
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
