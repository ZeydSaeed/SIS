import { Form, Head, Link } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

type AttendanceRecord = {
    id: number;
    student_id: number;
    enrollment_id: number;
    status: number;
    notes: string | null;
};

type Session = {
    id: number;
    section_id: number;
    subject_id: number;
    academic_year_id: number;
    session_date: string;
    status: number;
    records?: AttendanceRecord[];
};

type PageProps = {
    session: Session;
};

type DraftRow = {
    student_id: number | '';
    enrollment_id: number | '';
    status: number;
    notes: string;
};

function buildInitialRows(records: AttendanceRecord[]): DraftRow[] {
    if (records.length === 0) {
        return [{ student_id: '', enrollment_id: '', status: 1, notes: '' }];
    }

    return records.map((record) => ({
        student_id: record.student_id,
        enrollment_id: record.enrollment_id,
        status: record.status,
        notes: record.notes ?? '',
    }));
}

export default function AttendanceMark({ session }: PageProps) {
    const records = session.records ?? [];
    const initialRows = buildInitialRows(records);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Attendance', href: '/attendance' },
        { title: `Session ${session.id}`, href: `/attendance/${session.id}` },
        { title: 'Mark', href: `/attendance/${session.id}/mark` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Mark attendance · Session ${session.id}`} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Mark section attendance"
                    description={`${session.session_date} · Section ${session.section_id} · Subject ${session.subject_id}`}
                    icon={<CalendarCheck className="size-6" aria-hidden />}
                />
                <Link
                    href={`/attendance/${session.id}`}
                    className="sis-ops-hub__link w-fit px-3 py-2 text-sm"
                    prefetch
                >
                    Back to session
                </Link>
                <Form
                    action={`/attendance/${session.id}/mark`}
                    method="post"
                    className="flex flex-col gap-4"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="academic_year_id" value={session.academic_year_id} />
                            {errors.academic_year_id ? (
                                <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                    {errors.academic_year_id}
                                </span>
                            ) : null}
                            {errors.records ? (
                                <span className="text-sm text-[color:var(--sis-powder-blush)]">{errors.records}</span>
                            ) : null}
                            <div className="flex flex-col gap-3">
                                {initialRows.map((row, index) => (
                                    <fieldset
                                        key={`row-${index}`}
                                        className="grid gap-3 rounded-md border border-[color:var(--sis-powder-blue)] p-3 sm:grid-cols-2"
                                    >
                                        <legend className="px-1 text-sm font-medium">Student row {index + 1}</legend>
                                        <label className="flex flex-col gap-1 text-sm">
                                            <span>Student ID</span>
                                            <input
                                                name={`records[${index}][student_id]`}
                                                type="number"
                                                min={1}
                                                required
                                                defaultValue={row.student_id === '' ? undefined : row.student_id}
                                                className="sis-ops-hub__link min-h-11 px-3 py-2"
                                            />
                                            {errors[`records.${index}.student_id`] ? (
                                                <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                                    {errors[`records.${index}.student_id`]}
                                                </span>
                                            ) : null}
                                        </label>
                                        <label className="flex flex-col gap-1 text-sm">
                                            <span>Enrollment ID</span>
                                            <input
                                                name={`records[${index}][enrollment_id]`}
                                                type="number"
                                                min={1}
                                                required
                                                defaultValue={
                                                    row.enrollment_id === '' ? undefined : row.enrollment_id
                                                }
                                                className="sis-ops-hub__link min-h-11 px-3 py-2"
                                            />
                                            {errors[`records.${index}.enrollment_id`] ? (
                                                <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                                    {errors[`records.${index}.enrollment_id`]}
                                                </span>
                                            ) : null}
                                        </label>
                                        <label className="flex flex-col gap-1 text-sm">
                                            <span>Status</span>
                                            <select
                                                name={`records[${index}][status]`}
                                                defaultValue={String(row.status)}
                                                className="sis-ops-hub__link min-h-11 px-3 py-2"
                                            >
                                                <option value="1">Present</option>
                                                <option value="2">Absent</option>
                                                <option value="3">Late</option>
                                            </select>
                                            {errors[`records.${index}.status`] ? (
                                                <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                                    {errors[`records.${index}.status`]}
                                                </span>
                                            ) : null}
                                        </label>
                                        <label className="flex flex-col gap-1 text-sm sm:col-span-2">
                                            <span>Notes (optional)</span>
                                            <input
                                                name={`records[${index}][notes]`}
                                                type="text"
                                                defaultValue={row.notes}
                                                className="sis-ops-hub__link min-h-11 px-3 py-2"
                                            />
                                            {errors[`records.${index}.notes`] ? (
                                                <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                                    {errors[`records.${index}.notes`]}
                                                </span>
                                            ) : null}
                                        </label>
                                    </fieldset>
                                ))}
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="sis-ops-hub__link min-h-11 w-fit px-4 py-2 text-sm"
                            >
                                {processing ? 'Saving…' : 'Save attendance'}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
