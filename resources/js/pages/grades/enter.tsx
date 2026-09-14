import { Form, Head, Link } from '@inertiajs/react';
import { PenLine } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    defaults: {
        is_absent: boolean;
    };
};

export default function GradesEnter({ defaults }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.grades.title, href: '/grades' },
        { title: i18n.grades.enterGrade, href: '/grades/enter' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.grades.enterTitle} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={i18n.grades.enterTitle}
                    description={i18n.grades.enterDesc}
                    icon={<PenLine className="size-6" aria-hidden />}
                />
                <Link href="/grades" className="sis-ops-hub__link w-fit px-3 py-2 text-sm" prefetch>
                    {i18n.common.backToList}
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
                                <span>{i18n.grades.examEnrollmentId}</span>
                                <input
                                    name="exam_enrollment_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                    dir="ltr"
                                />
                                {errors.exam_enrollment_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.exam_enrollment_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.grades.scoreOptional}</span>
                                <input
                                    name="score"
                                    type="number"
                                    step="0.01"
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                    dir="ltr"
                                />
                                {errors.score ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.score}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.grades.absentQuestion}</span>
                                <select
                                    name="is_absent"
                                    defaultValue={defaults.is_absent ? '1' : '0'}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                >
                                    <option value="0">{i18n.grades.absentNoEnterScore}</option>
                                    <option value="1">{i18n.grades.absentYesAbsent}</option>
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
                                {processing ? i18n.common.saving : i18n.grades.enterGrade}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
