import { Form, Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

type Enrollment = {
    id: number;
    student_id: number;
    class_id: number;
    section_id: number;
    enrollment_number: string;
};

type PageProps = {
    enrollment: Enrollment;
};

export default function EnrollmentEdit({ enrollment }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Enrollments', href: '/enrollments' },
        { title: enrollment.enrollment_number, href: `/enrollments/${enrollment.id}` },
        { title: 'Edit placement', href: `/enrollments/${enrollment.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${enrollment.enrollment_number}`} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Update placement"
                    description={`Move enrollment ${enrollment.enrollment_number} to another class/section.`}
                    icon={<ClipboardList className="size-6" aria-hidden />}
                />
                <Link
                    href={`/enrollments/${enrollment.id}`}
                    className="sis-ops-hub__link w-fit px-3 py-2 text-sm"
                    prefetch
                >
                    Back to detail
                </Link>
                <Form
                    action={`/enrollments/${enrollment.id}`}
                    method="put"
                    className="grid max-w-xl gap-3"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <>
                            <p className="text-sm opacity-80">Student {enrollment.student_id}</p>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Class ID</span>
                                <input
                                    name="class_id"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={enrollment.class_id}
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
                                    defaultValue={enrollment.section_id}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.section_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.section_id}
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
                                {processing ? 'Saving…' : 'Update placement'}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
