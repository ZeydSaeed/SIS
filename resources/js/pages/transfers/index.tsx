import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import {
    blankToNull,
    RegistryListField,
    RegistrySheetDialog,
    RegistryTextField,
    useRegistryRequest,
} from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type TransferableApplication = {
    id: number;
    application_number: string;
    full_name: string;
    request_kind: number;
    status: number;
    school_id: number;
    school_name: string;
    period_id: number;
    period_name: string;
    academic_year_id: number;
    academic_year_name: string;
};

type TransferHistoryRow = {
    id: number;
    application_id: number;
    from_school_name: string;
    to_school_name: string;
    from_request_kind: number;
    to_request_kind: number;
    from_period_name: string;
    to_period_name: string;
    reason: string | null;
    transferred_by_name: string | null;
    created_at: string;
};

type PeriodOption = { id: number; name: string; academic_year_id: number; academic_year_name: string };

type Props = {
    transfers: {
        applications: TransferableApplication[];
        pagination: { page: number; per_page: number; total: number; total_pages: number };
        history: TransferHistoryRow[];
        schools: { id: number; name: string }[];
        periods: PeriodOption[];
    };
    filters: { q: string | null };
    authorization: { can_manage: boolean };
};

type Draft = {
    school_id: string;
    request_kind: string;
    academic_year_id: string;
    period_id: string;
    reason: string;
};

const RELOAD_PROPS = ['transfers', 'flash'];

function toDraft(row: TransferableApplication): Draft {
    return {
        school_id: String(row.school_id),
        request_kind: String(row.request_kind),
        academic_year_id: String(row.academic_year_id),
        period_id: String(row.period_id),
        reason: '',
    };
}

/** النقل — the only place an admission application's school, request kind or academic year changes. */
export default function TransfersIndex(props: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ title: t().transfers.title, href: '/transfers' }];

    // Inner component: titlebar search + page error contexts live inside AppLayout.
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <TransfersPage {...props} />
        </AppLayout>
    );
}

function TransfersPage({ transfers, filters, authorization }: Props) {
    const i18n = t();
    const tr = i18n.transfers;
    const request = useRegistryRequest(RELOAD_PROPS);
    const [selected, setSelected] = useState<TransferableApplication | null>(null);
    const [draft, setDraft] = useState<Draft | null>(null);
    const [saving, setSaving] = useState(false);
    const { applications, pagination, history, schools, periods } = transfers;

    const kindLabel = useCallback(
        (kind: number): string =>
            kind === 1 ? i18n.admission.requestTypeAcademicTransfer : i18n.admission.requestTypeVocational,
        [i18n.admission.requestTypeAcademicTransfer, i18n.admission.requestTypeVocational],
    );

    const visit = useCallback((query: { q?: string | null; page?: number }) => {
        const params = new URLSearchParams();
        const q = (query.q ?? '').trim();
        if (q !== '') {
            params.set('q', q);
        }
        if ((query.page ?? 1) > 1) {
            params.set('page', String(query.page));
        }
        const suffix = params.toString();
        router.visit(suffix === '' ? '/transfers' : `/transfers?${suffix}`, {
            preserveState: true,
            preserveScroll: true,
            only: RELOAD_PROPS.concat('filters'),
        });
    }, []);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: filters.q ?? '',
            label: tr.search,
            placeholder: tr.search,
            onDraftChange: () => undefined,
            onCommit: (query: string) => visit({ q: query, page: 1 }),
        }),
        [filters.q, tr.search, visit],
    );
    useRegisterPageTitlebarSearch(titlebarSearch);

    const openTransfer = (row: TransferableApplication): void => {
        if (!authorization.can_manage) {
            return;
        }
        setSelected(row);
        setDraft(toDraft(row));
    };

    const closeTransfer = (): void => {
        setSelected(null);
        setDraft(null);
    };

    const setField = <K extends keyof Draft>(key: K, value: Draft[K]): void => {
        setDraft((current) => (current === null ? current : { ...current, [key]: value }));
    };

    const yearOptions = useMemo(() => {
        const seen = new Map<number, string>();
        for (const period of periods) {
            seen.set(period.academic_year_id, period.academic_year_name);
        }

        return Array.from(seen, ([id, name]) => ({ value: String(id), label: name }));
    }, [periods]);

    const periodOptions = useMemo(
        () =>
            periods
                .filter((period) => draft !== null && String(period.academic_year_id) === draft.academic_year_id)
                .map((period) => ({ value: String(period.id), label: period.name })),
        [draft, periods],
    );

    const changed =
        selected !== null &&
        draft !== null &&
        (draft.school_id !== String(selected.school_id) ||
            draft.request_kind !== String(selected.request_kind) ||
            draft.period_id !== String(selected.period_id));
    const canSave = changed && !saving && draft !== null && draft.period_id !== '' && draft.school_id !== '';

    const save = async (): Promise<void> => {
        if (!canSave || selected === null || draft === null) {
            return;
        }
        setSaving(true);
        try {
            const ok = await request('post', `/transfers/applications/${selected.id}`, {
                target_school_id: Number(draft.school_id),
                request_kind: Number(draft.request_kind),
                application_period_id: Number(draft.period_id),
                reason: blankToNull(draft.reason),
            });
            if (ok) {
                closeTransfer();
            }
        } finally {
            setSaving(false);
        }
    };

    const columns: DataTableColumn<TransferableApplication>[] = [
        {
            id: 'num',
            header: '#',
            cell: (row) => <span dir="ltr">{(pagination.page - 1) * pagination.per_page + applications.indexOf(row) + 1}</span>,
        },
        { id: 'number', header: tr.applicationNumber, cell: (row) => <span dir="ltr">{row.application_number}</span> },
        { id: 'name', header: tr.studentName, cell: (row) => row.full_name },
        { id: 'school', header: i18n.students.schoolName, cell: (row) => row.school_name },
        { id: 'kind', header: i18n.admission.requestTypeTitle, cell: (row) => kindLabel(row.request_kind), hideOnMobile: true },
        { id: 'year', header: i18n.admission.academicYear, cell: (row) => row.academic_year_name, hideOnMobile: true },
        { id: 'period', header: i18n.admission.periodNameAbbr, cell: (row) => row.period_name, hideOnMobile: true },
    ];

    const historyColumns: DataTableColumn<TransferHistoryRow>[] = [
        { id: 'date', header: tr.date, cell: (row) => <span dir="ltr">{row.created_at.slice(0, 16).replace('T', ' ')}</span> },
        { id: 'school', header: i18n.students.schoolName, cell: (row) => `${row.from_school_name} ← ${row.to_school_name}` },
        { id: 'kind', header: i18n.admission.requestTypeTitle, cell: (row) => `${kindLabel(row.from_request_kind)} ← ${kindLabel(row.to_request_kind)}`, hideOnMobile: true },
        { id: 'period', header: i18n.admission.periodNameAbbr, cell: (row) => `${row.from_period_name} ← ${row.to_period_name}`, hideOnMobile: true },
        { id: 'reason', header: tr.reason, cell: (row) => row.reason ?? '—', hideOnMobile: true },
        { id: 'by', header: tr.transferredBy, cell: (row) => row.transferred_by_name ?? '—', hideOnMobile: true },
    ];

    const schoolOptions = schools.map((school) => ({ value: String(school.id), label: school.name }));
    const kindOptions = [
        { value: '2', label: i18n.admission.requestTypeVocational },
        { value: '1', label: i18n.admission.requestTypeAcademicTransfer },
    ];

    return (
        <>
            <Head title={tr.title} />
            <div className="sis-ops-hub sis-admission-page flex h-full min-h-0 flex-col gap-3 overflow-auto px-4 pb-4" dir="rtl" lang="ar">
                <SheetSection id="transfers-applications" title={tr.applicationsSection}>
                    <DataTable
                        columns={columns}
                        rows={applications}
                        rowKey={(row) => row.id}
                        emptyTitle={tr.empty}
                        onRowClick={authorization.can_manage ? openTransfer : undefined}
                        isRowSelected={(row) => row.id === selected?.id}
                        getRowAriaLabel={(row) => `${tr.transferAction}: ${row.full_name}`}
                    />
                    {pagination.total_pages > 1 ? (
                        <nav className="sis-admission-drafts-pagination" aria-label={i18n.common.page}>
                            <ul className="sis-admission-pagination" dir="ltr">
                                <li className="sis-admission-pagination__item">
                                    <button
                                        type="button"
                                        className="sis-admission-pagination__link"
                                        aria-label={i18n.common.previous}
                                        disabled={pagination.page <= 1}
                                        onClick={() => visit({ q: filters.q, page: pagination.page - 1 })}
                                    >
                                        <span aria-hidden="true">&laquo;</span>
                                    </button>
                                </li>
                                <li className="sis-admission-pagination__item">
                                    <span className="sis-admission-pagination__link sis-admission-pagination__link--active" aria-current="page">
                                        {pagination.page} / {pagination.total_pages}
                                    </span>
                                </li>
                                <li className="sis-admission-pagination__item">
                                    <button
                                        type="button"
                                        className="sis-admission-pagination__link"
                                        aria-label={i18n.common.next}
                                        disabled={pagination.page >= pagination.total_pages}
                                        onClick={() => visit({ q: filters.q, page: pagination.page + 1 })}
                                    >
                                        <span aria-hidden="true">&raquo;</span>
                                    </button>
                                </li>
                            </ul>
                        </nav>
                    ) : null}
                </SheetSection>

                <SheetSection id="transfers-history" title={tr.historySection}>
                    <DataTable
                        columns={historyColumns}
                        rows={history}
                        rowKey={(row) => row.id}
                        emptyTitle={tr.historyEmpty}
                    />
                </SheetSection>
            </div>

            {selected !== null && draft !== null ? (
                <RegistrySheetDialog title={tr.sheetTitle} className="sis-transfer-sheet" onClose={closeTransfer}>
                    <SheetSection id="transfer-current" title={tr.currentSection}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                            <RegistryTextField label={tr.applicationNumber} editing={false} value={selected.application_number} dir="ltr" onChange={() => undefined} />
                            <RegistryTextField label={tr.studentName} editing={false} value={selected.full_name} fieldClassName="sis-enrollment-record-sheet__field--wide" onChange={() => undefined} />
                        </div>
                    </SheetSection>
                    <SheetSection id="transfer-target" title={tr.targetSection}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                            <RegistryListField
                                label={i18n.students.schoolName}
                                editing
                                required
                                value={draft.school_id}
                                display={selected.school_name}
                                options={schoolOptions}
                                fieldClassName="sis-enrollment-record-sheet__field--wide"
                                onChange={(value) => setField('school_id', value)}
                            />
                            <RegistryListField
                                label={i18n.admission.requestTypeTitle}
                                editing
                                required
                                value={draft.request_kind}
                                display={kindLabel(selected.request_kind)}
                                options={kindOptions}
                                fieldClassName="sis-enrollment-record-sheet__field--wide"
                                onChange={(value) => setField('request_kind', value)}
                            />
                        </div>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                            <RegistryListField
                                label={i18n.admission.academicYear}
                                editing
                                required
                                value={draft.academic_year_id}
                                display={selected.academic_year_name}
                                options={yearOptions}
                                fieldClassName="sis-enrollment-record-sheet__field--wide"
                                onChange={(value) => {
                                    setDraft((current) =>
                                        current === null
                                            ? current
                                            : {
                                                  ...current,
                                                  academic_year_id: value,
                                                  period_id:
                                                      periods.find((period) => String(period.academic_year_id) === value)?.id.toString() ?? '',
                                              },
                                    );
                                }}
                            />
                            <RegistryListField
                                label={i18n.admission.periodNameAbbr}
                                editing
                                required
                                value={draft.period_id}
                                display={selected.period_name}
                                options={periodOptions}
                                fieldClassName="sis-enrollment-record-sheet__field--wide"
                                onChange={(value) => setField('period_id', value)}
                            />
                        </div>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                            <RegistryTextField
                                label={tr.reason}
                                editing
                                value={draft.reason}
                                fieldClassName="sis-enrollment-record-sheet__field--wide"
                                onChange={(value) => setField('reason', value)}
                            />
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" onClick={closeTransfer} disabled={saving}>
                            {i18n.dialog.cancel}
                        </Button>
                        <Button type="button" disabled={!canSave} onClick={() => void save()}>
                            {saving ? i18n.common.saving : tr.transferAction}
                        </Button>
                    </div>
                </RegistrySheetDialog>
            ) : null}
        </>
    );
}
