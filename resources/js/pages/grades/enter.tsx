import { Form, Head, Link } from '@inertiajs/react';
import { PenLine } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    defaults: {
        is_absent: boolean;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Grades', href: '/grades' },
    { title: 'Enter grade', href: '/grades/enter' },
];

export default function GradesEnter({ defaults }: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Enter grade" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Enter grade"
                    description="Record a score or absence for an exam enrollment."
                    icon={<PenLine className="size-6" aria-hidden />}
                />
                <Link href="/grades" className="sis-ops-hub__link w-fit px-3 py-2 text-sm" prefetch>
                    Back to list
                </Link>
                <Form
                    action="/grades"
                    method="post"
                    className="grid max-w-xl gap-3"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Exam enrollment ID</span>
                                <input
                                    name="exam_enrollment_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.exam_enrollment_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.exam_enrollment_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Score (leave empty if absent)</span>
                                <input
                                    name="score"
                                    type="number"
                                    step="0.01"
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.score ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.score}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Absent?</span>
                                <select
                                    name="is_absent"
                                    defaultValue={defaults.is_absent ? '1' : '0'}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                >
                                    <option value="0">No — enter score</option>
                                    <option value="1">Yes — absent</option>
                                </select>
                            </label>
                            {errors.is_absent ? (
                                <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                    {errors.is_absent}
                                </span>
                            ) : null}
                            <button
                                type="submit"
                                disabled={processing}
                                className="sis-ops-hub__link min-h-11 w-fit px-4 py-2 text-sm disabled:opacity-50"
                            >
                                {processing ? 'Saving…' : 'Enter grade'}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
