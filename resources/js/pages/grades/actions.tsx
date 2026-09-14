import { Form, Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { PenLine } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import type { BreadcrumbItem } from '@/types';

type Grade = {
    id: number;
    academic_year_id: number;
    exam_enrollment_id: number;
    exam_session_id: number;
    enrollment_id: number;
    student_id: number;
    subject_id: number;
    score: string | null;
    max_score: string;
    is_absent: boolean;
    status: number;
    is_current: boolean;
    finalized_at: string | null;
};

type PageProps = {
    grade: Grade | null;
    filters: {
        grade_id: number | null;
        academic_year_id: number | null;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Grades', href: '/grades' },
    { title: 'Grade actions', href: '/grades/actions' },
];

export default function GradesActions({ grade, filters }: PageProps) {
    const [gradeId, setGradeId] = useState(filters.grade_id?.toString() ?? '');
    const [confirmVoid, setConfirmVoid] = useState(false);
    const [voidReason, setVoidReason] = useState('');
    const [voiding, setVoiding] = useState(false);

    const onLookup = (event: FormEvent) => {
        event.preventDefault();
        const parsed = Number.parseInt(gradeId, 10);
        router.get(
            '/grades/actions',
            {
                grade_id: Number.isFinite(parsed) && parsed > 0 ? parsed : undefined,
                academic_year_id: filters.academic_year_id ?? undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Grade actions" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Grade actions"
                    description="Correct, void, or finalize a student grade."
                    icon={<PenLine className="size-6" aria-hidden />}
                />
                <Link href="/grades" className="sis-ops-hub__link w-fit px-3 py-2 text-sm" prefetch>
                    Back to list
                </Link>
                <form
                    onSubmit={onLookup}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    aria-label="Lookup grade"
                >
                    <label className="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                        <span>Grade ID</span>
                        <input
                            type="number"
                            min={1}
                            value={gradeId}
                            onChange={(e) => setGradeId(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                        />
                    </label>
                    <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm">
                        Load grade
                    </button>
                </form>
                {grade ? (
                    <>
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="opacity-70">Student</dt>
                                <dd>{grade.student_id}</dd>
                            </div>
                            <div>
                                <dt className="opacity-70">Score</dt>
                                <dd>{grade.is_absent ? 'Absent' : (grade.score ?? '—')}</dd>
                            </div>
                            <div>
                                <dt className="opacity-70">Status</dt>
                                <dd>{grade.status}</dd>
                            </div>
                            <div>
                                <dt className="opacity-70">Finalized</dt>
                                <dd>{grade.finalized_at ?? 'Not finalized'}</dd>
                            </div>
                        </dl>
                        <section className="grid max-w-xl gap-6">
                            <Form
                                action={`/grades/${grade.id}/correct`}
                                method="post"
                                className="grid gap-3 rounded-md border border-[color:var(--sis-powder-blue)] p-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <h2 className="font-semibold">Correct grade</h2>
                                        <input
                                            type="hidden"
                                            name="academic_year_id"
                                            value={grade.academic_year_id}
                                        />
                                        <label className="flex flex-col gap-1 text-sm">
                                            <span>New score</span>
                                            <input
                                                name="score"
                                                type="number"
                                                step="0.01"
                                                defaultValue={grade.score ?? undefined}
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
                                                defaultValue={grade.is_absent ? '1' : '0'}
                                                className="sis-ops-hub__link min-h-11 px-3 py-2"
                                            >
                                                <option value="0">No</option>
                                                <option value="1">Yes</option>
                                            </select>
                                        </label>
                                        <label className="flex flex-col gap-1 text-sm">
                                            <span>Reason</span>
                                            <textarea
                                                name="reason"
                                                required
                                                minLength={3}
                                                rows={2}
                                                className="sis-ops-hub__link px-3 py-2"
                                            />
                                            {errors.reason ? (
                                                <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                                    {errors.reason}
                                                </span>
                                            ) : null}
                                        </label>
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="sis-ops-hub__link w-fit px-4 py-2 text-sm disabled:opacity-50"
                                        >
                                            Correct
                                        </button>
                                    </>
                                )}
                            </Form>
                            <div className="grid gap-3 rounded-md border border-[color:var(--sis-powder-blue)] p-4">
                                <h2 className="font-semibold">Void grade</h2>
                                <label className="flex flex-col gap-1 text-sm">
                                    <span>Reason</span>
                                    <textarea
                                        required
                                        minLength={3}
                                        rows={2}
                                        className="sis-ops-hub__link px-3 py-2"
                                        value={voidReason}
                                        onChange={(e) => setVoidReason(e.target.value)}
                                    />
                                </label>
                                <button
                                    type="button"
                                    disabled={voiding}
                                    className="sis-ops-hub__link w-fit px-4 py-2 text-sm disabled:opacity-50"
                                    onClick={() => setConfirmVoid(true)}
                                >
                                    Void
                                </button>
                                <ConfirmDialog
                                    open={confirmVoid}
                                    title="Void this grade?"
                                    description="Voiding marks the grade non-current. This is an audited academic action."
                                    confirmLabel="Void grade"
                                    confirmPending={voiding}
                                    onConfirm={() => {
                                        if (voidReason.trim().length < 3) {
                                            setConfirmVoid(false);
                                            return;
                                        }
                                        setVoiding(true);
                                        router.post(
                                            `/grades/${grade.id}/void`,
                                            {
                                                academic_year_id: grade.academic_year_id,
                                                reason: voidReason,
                                            },
                                            {
                                                onFinish: () => {
                                                    setVoiding(false);
                                                    setConfirmVoid(false);
                                                },
                                            },
                                        );
                                    }}
                                    onOpenChange={setConfirmVoid}
                                />
                            </div>
                            <Form
                                action={`/grades/${grade.id}/finalize`}
                                method="post"
                                className="grid gap-3 rounded-md border border-[color:var(--sis-powder-blue)] p-4"
                            >
                                {({ processing }) => (
                                    <>
                                        <h2 className="font-semibold">Finalize grade</h2>
                                        <input
                                            type="hidden"
                                            name="academic_year_id"
                                            value={grade.academic_year_id}
                                        />
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="sis-ops-hub__link w-fit px-4 py-2 text-sm disabled:opacity-50"
                                        >
                                            Finalize
                                        </button>
                                    </>
                                )}
                            </Form>
                        </section>
                    </>
                ) : (
                    <p className="text-sm opacity-80">Enter a grade ID to load correction actions.</p>
                )}
            </div>
        </AppLayout>
    );
}
