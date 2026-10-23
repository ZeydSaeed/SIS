import { Head, Link, useForm } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { PageHeader } from '@/components/sis/page-header';
import { StatusChip } from '@/components/sis/status-chip';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type ExamRow = {
    id: number;
    school_id: number;
    academic_year_id: number;
    term_id: number;
    exam_type_id: number;
    name: string;
    start_date: string;
    end_date: string;
    status: number;
};

type PageProps = {
    exams: {
        data: ExamRow[];
    };
    names: { terms: Record<number, string> };
    admin: {
        can_create: boolean;
        terms: { id: number; name: string }[];
        exam_types: { id: number; name: string; weight_percentage: number }[];
    };
    filters: {
        academic_year_id: number | null;
        status: number | null;
    };
};

const SELECT_CLASS = 'sis-ops-hub__link min-h-11 px-3 py-2';

function newKey(): string {
    return `exam-create-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

/** «امتحان جديد» — existing terms and exam types only; the server validates everything again. */
function CreateExamForm({
    admin,
    academicYearId,
    onDone,
}: {
    admin: PageProps['admin'];
    academicYearId: number;
    onDone: () => void;
}) {
    const a = t().exams.admin;
    const [key] = useState(newKey);
    const form = useForm({
        academic_year_id: academicYearId,
        term_id: '',
        exam_type_id: '',
        name: '',
        start_date: '',
        end_date: '',
    });

    if (admin.exam_types.length === 0) {
        return (
            <p className="text-sm" role="status">
                {a.noExamTypes}{' '}
                <Link href="/exams/types" className="underline">
                    {t().exams.types.open}
                </Link>
            </p>
        );
    }
    if (admin.terms.length === 0) {
        return (
            <p className="text-sm" role="status">
                {a.noTerms}
            </p>
        );
    }

    return (
        <form
            className="grid max-w-xl gap-3"
            data-window-id="exams.create"
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/exams', {
                    headers: { 'X-Idempotency-Key': key },
                    preserveScroll: true,
                });
            }}
        >
            <OpsFormField
                label={a.examName}
                name="name"
                error={form.errors.name}
            >
                <OpsTextInput
                    name="name"
                    required
                    value={form.data.name}
                    error={form.errors.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
            </OpsFormField>
            <OpsFormField
                label={a.term}
                name="term_id"
                error={form.errors.term_id}
            >
                <select
                    id="term_id"
                    name="term_id"
                    required
                    className={SELECT_CLASS}
                    value={form.data.term_id}
                    onChange={(e) => form.setData('term_id', e.target.value)}
                >
                    <option value="">—</option>
                    {admin.terms.map((term) => (
                        <option key={term.id} value={term.id}>
                            {term.name}
                        </option>
                    ))}
                </select>
            </OpsFormField>
            <OpsFormField
                label={a.examType}
                name="exam_type_id"
                error={form.errors.exam_type_id}
            >
                <select
                    id="exam_type_id"
                    name="exam_type_id"
                    required
                    className={SELECT_CLASS}
                    value={form.data.exam_type_id}
                    onChange={(e) =>
                        form.setData('exam_type_id', e.target.value)
                    }
                >
                    <option value="">—</option>
                    {admin.exam_types.map((type) => (
                        <option key={type.id} value={type.id}>
                            {`${type.name} (${type.weight_percentage}%)`}
                        </option>
                    ))}
                </select>
            </OpsFormField>
            <OpsFormField
                label={a.startDate}
                name="start_date"
                error={form.errors.start_date}
            >
                <OpsTextInput
                    name="start_date"
                    type="date"
                    dir="ltr"
                    required
                    value={form.data.start_date}
                    error={form.errors.start_date}
                    onChange={(e) => form.setData('start_date', e.target.value)}
                />
            </OpsFormField>
            <OpsFormField
                label={a.endDate}
                name="end_date"
                error={form.errors.end_date}
            >
                <OpsTextInput
                    name="end_date"
                    type="date"
                    dir="ltr"
                    required
                    value={form.data.end_date}
                    error={form.errors.end_date}
                    onChange={(e) => form.setData('end_date', e.target.value)}
                />
            </OpsFormField>
            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm disabled:opacity-50"
                >
                    {form.processing ? a.saving : a.create}
                </button>
                <button
                    type="button"
                    className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm"
                    onClick={onDone}
                >
                    {a.cancel}
                </button>
            </div>
        </form>
    );
}

export default function ExamsIndex({
    exams,
    names,
    admin,
    filters,
}: PageProps) {
    const i18n = t();
    const [creating, setCreating] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.exams.title, href: '/exams' },
    ];

    const columns: DataTableColumn<ExamRow>[] = [
        {
            id: 'name',
            header: i18n.exams.name,
            cell: (row) => row.name,
        },
        {
            id: 'term_id',
            header: i18n.exams.term,
            cell: (row) =>
                names.terms[row.term_id] ?? (
                    <span dir="ltr">{row.term_id}</span>
                ),
            hideOnMobile: true,
        },
        {
            id: 'dates',
            header: i18n.exams.dates,
            cell: (row) => (
                <span dir="ltr">
                    {row.start_date} – {row.end_date}
                </span>
            ),
        },
        {
            id: 'status',
            header: i18n.common.status,
            cell: (row) => <StatusChip kind="exam" status={row.status} />,
        },
        {
            id: 'actions',
            header: i18n.common.open,
            cell: (row) => (
                <a href={`/exams/${row.id}`} className="underline">
                    {i18n.common.view}
                </a>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.exams.title} />
            <div
                className="sis-ops-hub flex flex-col gap-4 p-4"
                dir="rtl"
                lang="ar"
            >
                <PageHeader
                    title={i18n.exams.title}
                    description={i18n.exams.description}
                    icon={<GraduationCap className="size-6" aria-hidden />}
                />
                <OpsYearFilter
                    action="/exams"
                    academicYearId={filters.academic_year_id}
                />
                <div className="flex flex-wrap gap-2">
                    <Link
                        href="/exams/types"
                        className="sis-ops-hub__link w-fit px-3 py-2 text-sm"
                        prefetch
                    >
                        {i18n.exams.types.open}
                    </Link>
                </div>
                {admin.can_create && filters.academic_year_id !== null ? (
                    creating ? (
                        <CreateExamForm
                            admin={admin}
                            academicYearId={filters.academic_year_id}
                            onDone={() => setCreating(false)}
                        />
                    ) : (
                        <button
                            type="button"
                            className="sis-ops-hub__link w-fit px-3 py-2 text-sm"
                            onClick={() => setCreating(true)}
                        >
                            {i18n.exams.admin.newExam}
                        </button>
                    )
                ) : null}
                <DataTable
                    columns={columns}
                    rows={exams.data}
                    rowKey={(row) => row.id}
                    emptyTitle={i18n.exams.emptyTitle}
                    emptyDescription={
                        filters.academic_year_id
                            ? i18n.exams.emptyDesc
                            : i18n.exams.selectYearContext
                    }
                    caption={i18n.exams.tableCaption}
                    mobileCard={(row) => (
                        <a
                            href={`/exams/${row.id}`}
                            className="block rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_20%,white)] p-3"
                        >
                            <div className="font-semibold">{row.name}</div>
                            <div className="text-sm opacity-80">
                                <span dir="ltr">
                                    {row.start_date} – {row.end_date}
                                </span>
                            </div>
                            <div className="text-sm opacity-80">
                                <StatusChip kind="exam" status={row.status} />
                            </div>
                        </a>
                    )}
                />
            </div>
        </AppLayout>
    );
}
