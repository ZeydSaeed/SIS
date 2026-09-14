import { Form, Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    defaults: {
        academic_year_id: number | null;
        effective_from: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Enrollments', href: '/enrollments' },
    { title: 'Enroll student', href: '/enrollments/create' },
];

export default function EnrollmentCreate({ defaults }: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Enroll student" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Enroll student"
                    description="Create a placement for the academic year. Server validation is authoritative."
                    icon={<ClipboardList className="size-6" aria-hidden />}
                />
                <Link href="/enrollments" className="sis-ops-hub__link w-fit px-3 py-2 text-sm" prefetch>
                    Back to list
                </Link>
                <Form
                    action="/enrollments"
                    method="post"
                    className="grid max-w-xl gap-3"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Student ID</span>
                                <input
                                    name="student_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.student_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.student_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Academic year ID</span>
                                <input
                                    name="academic_year_id"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={defaults.academic_year_id ?? undefined}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.academic_year_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.academic_year_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Class ID</span>
                                <input
                                    name="class_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.class_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.class_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Section ID</span>
                                <input
                                    name="section_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.section_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.section_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Effective from</span>
                                <input
                                    name="effective_from"
                                    type="date"
                                    required
                                    defaultValue={defaults.effective_from}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.effective_from ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.effective_from}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Specialization ID (optional)</span>
                                <input
                                    name="specialization_id"
                                    type="number"
                                    min={1}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.specialization_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.specialization_id}
                                    </span>
                                ) : null}
                            </label>
                            <button
                                type="submit"
                                disabled={processing}
                                className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm"
                            >
                                {processing ? 'Saving…' : 'Create enrollment'}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
