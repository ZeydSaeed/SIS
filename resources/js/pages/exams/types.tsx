import { Head, Link, useForm } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type ExamType = {
    id: number;
    code: string;
    name: string;
    weight_percentage: number;
    in_use: boolean;
};

type PageProps = {
    types: { data: ExamType[] };
    can_manage: boolean;
};

function newKey(): string {
    return `exam-type-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

/** Add (type null) or edit one type; a type used by an exam keeps its code and weight (the server refuses otherwise). */
function TypeForm({ type, onDone }: { type: ExamType | null; onDone: () => void }) {
    const x = t().exams.types;
    const [key] = useState(newKey);
    const defaults = {
        code: type?.code ?? '',
        name: type?.name ?? '',
        weight_percentage: type ? String(type.weight_percentage) : '',
    };
    const form = useForm(defaults);
    const locked = type?.in_use ?? false;

    return (
        <form
            className="grid max-w-xl gap-3"
            data-window-id={type ? 'exams.types.edit' : 'exams.types.create'}
            onSubmit={(e) => {
                e.preventDefault();
                form.post(type ? `/exams/types/${type.id}` : '/exams/types', {
                    headers: { 'X-Idempotency-Key': key },
                    preserveScroll: true,
                    onSuccess: onDone,
                });
            }}
        >
            <OpsFormField label={x.name} name="name" error={form.errors.name}>
                <OpsTextInput
                    name="name"
                    required
                    value={form.data.name}
                    error={form.errors.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
            </OpsFormField>
            <OpsFormField label={x.code} name="code" error={form.errors.code}>
                {locked ? (
                    <span dir="ltr">{form.data.code}</span>
                ) : (
                    <OpsTextInput
                        name="code"
                        dir="ltr"
                        required
                        value={form.data.code}
                        error={form.errors.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                    />
                )}
            </OpsFormField>
            <OpsFormField label={x.weight} name="weight_percentage" error={form.errors.weight_percentage}>
                {locked ? (
                    <span dir="ltr">{form.data.weight_percentage}</span>
                ) : (
                    <OpsTextInput
                        name="weight_percentage"
                        type="number"
                        min={0}
                        dir="ltr"
                        required
                        value={form.data.weight_percentage}
                        error={form.errors.weight_percentage}
                        onChange={(e) => form.setData('weight_percentage', e.target.value)}
                    />
                )}
            </OpsFormField>
            {locked ? <p className="text-sm">{x.inUse}</p> : null}
            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm disabled:opacity-50"
                >
                    {form.processing ? x.saving : x.save}
                </button>
                <button type="button" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm" onClick={onDone}>
                    {x.cancel}
                </button>
            </div>
        </form>
    );
}

/** «أنواع الامتحانات» — the school's exam types and their weights. */
export default function ExamTypes({ types, can_manage }: PageProps) {
    const i18n = t();
    const x = i18n.exams.types;
    const [editing, setEditing] = useState<ExamType | 'new' | null>(null);
    const total = types.data.reduce((sum, type) => sum + type.weight_percentage, 0);

    const columns: DataTableColumn<ExamType>[] = [
        { id: 'name', header: x.name, cell: (row) => row.name },
        { id: 'code', header: x.code, cell: (row) => <span dir="ltr">{row.code}</span> },
        { id: 'weight', header: x.weight, cell: (row) => <span dir="ltr">{row.weight_percentage}</span>, sort: (row) => row.weight_percentage },
        { id: 'usage', header: x.usage, cell: (row) => (row.in_use ? x.inUse : x.unused), hideOnMobile: true },
    ];
    if (can_manage) {
        columns.push({
            id: 'actions',
            header: x.actions,
            sort: false,
            cell: (row) => (
                <button type="button" className="underline" onClick={() => setEditing(row)}>
                    {x.edit}
                </button>
            ),
        });
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.exams.title, href: '/exams' },
        { title: x.title, href: '/exams/types' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={x.title} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar" data-window-id="exams.types">
                <PageHeader title={x.title} description={x.description} icon={<ListChecks className="size-6" aria-hidden />} />
                <div className="flex flex-wrap gap-2">
                    <Link href="/exams" className="sis-ops-hub__link px-3 py-2 text-sm" prefetch>
                        {x.backToExams}
                    </Link>
                    {can_manage && editing === null ? (
                        <button type="button" className="sis-ops-hub__link px-3 py-2 text-sm" onClick={() => setEditing('new')}>
                            {x.add}
                        </button>
                    ) : null}
                </div>
                {!can_manage ? <p className="text-sm">{x.readOnly}</p> : null}
                {editing !== null ? (
                    <TypeForm key={editing === 'new' ? 'new' : editing.id} type={editing === 'new' ? null : editing} onDone={() => setEditing(null)} />
                ) : null}
                {types.data.length > 0 ? (
                    <p className="text-sm" role="status" data-types-total>
                        {x.total.replace(':total', String(total))} — {x.totalHint}
                    </p>
                ) : null}
                <DataTable
                    columns={columns}
                    rows={types.data}
                    rowKey={(row) => row.id}
                    emptyTitle={x.emptyTitle}
                    emptyDescription={x.emptyDesc}
                    caption={x.caption}
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-petal)_40%,white)] p-3">
                            <div className="font-semibold">{row.name}</div>
                            <div className="text-sm opacity-80">
                                <span dir="ltr">{row.code}</span> · {x.weight} <span dir="ltr">{row.weight_percentage}</span>
                            </div>
                            <div className="mt-1 text-sm">{row.in_use ? x.inUse : x.unused}</div>
                            {can_manage ? (
                                <button type="button" className="sis-ops-hub__link mt-2 inline-block text-sm" onClick={() => setEditing(row)}>
                                    {x.edit}
                                </button>
                            ) : null}
                        </div>
                    )}
                />
            </div>
        </AppLayout>
    );
}
