import { Form, Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { PenLine } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { t } from '@/i18n';
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

export default function GradesActions({ grade, filters }: PageProps) {
    const i18n = t();
    const [gradeId, setGradeId] = useState(filters.grade_id?.toString() ?? '');
    const [confirmVoid, setConfirmVoid] = useState(false);
    const [confirmFinalize, setConfirmFinalize] = useState(false);
    const [voidReason, setVoidReason] = useState('');
    const [voiding, setVoiding] = useState(false);
    const [finalizing, setFinalizing] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.grades.title, href: '/grades' },
        { title: i18n.grades.actionsBreadcrumb, href: '/grades/actions' },
    ];

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
            <Head title={i18n.grades.actionsTitle} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={i18n.grades.actionsTitle}
                    description={i18n.grades.actionsDesc}
                    icon={<PenLine className="size-6" aria-hidden />}
                />
                <Link href="/grades" className="sis-ops-hub__link w-fit px-3 py-2 text-sm" prefetch>
                    {i18n.common.backToList}
                </Link>
                <form
                    onSubmit={onLookup}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    aria-label={i18n.common.lookupGrade}
                >
                    <label className="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                        <span>{i18n.grades.gradeId}</span>
                        <input
                            type="number"
                            min={1}
                            value={gradeId}
                            onChange={(e) => setGradeId(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            dir="ltr"
                        />
                    </label>
                    <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm">
                        {i18n.grades.loadGrade}
                    </button>
                </form>
                {grade ? (
                    <>
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="opacity-70">{i18n.grades.student}</dt>
                                <dd>
                                    <span dir="ltr">{grade.student_id}</span>
                                </dd>
                            </div>
                            <div>
                                <dt className="opacity-70">{i18n.grades.score}</dt>
                                <dd>
                                    <span dir="ltr">
                                        {grade.is_absent ? i18n.grades.absent : (grade.score ?? '—')}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt className="opacity-70">{i18n.common.status}</dt>
                                <dd>
                                    <span dir="ltr">{grade.status}</span>
                                </dd>
                            </div>
                            <div>
                                <dt className="opacity-70">{i18n.grades.finalized}</dt>
                                <dd>
                                    <span dir="ltr">{grade.finalized_at ?? i18n.grades.notFinalized}</span>
                                </dd>
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
                                        <h2 className="font-semibold">{i18n.grades.correct}</h2>
                                        <input
                                            type="hidden"
                                            name="academic_year_id"
                                            value={grade.academic_year_id}
                                        />
                                        <label className="flex flex-col gap-1 text-sm">
                                            <span>{i18n.grades.newScore}</span>
                                            <input
                                                name="score"
                                                type="number"
                                                step="0.01"
                                                defaultValue={grade.score ?? undefined}
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
                                                defaultValue={grade.is_absent ? '1' : '0'}
                                                className="sis-ops-hub__link min-h-11 px-3 py-2"
                                            >
                                                <option value="0">{i18n.common.no}</option>
                                                <option value="1">{i18n.common.yes}</option>
                                            </select>
                                        </label>
                                        <label className="flex flex-col gap-1 text-sm">
                                            <span>{i18n.grades.reason}</span>
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
                                            {i18n.grades.correct}
                                        </button>
                                    </>
                                )}
                            </Form>
                            <div className="grid gap-3 rounded-md border border-[color:var(--sis-powder-blue)] p-4">
                                <h2 className="font-semibold">{i18n.grades.voidGradeAction}</h2>
                                <label className="flex flex-col gap-1 text-sm">
                                    <span>{i18n.grades.reason}</span>
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
                                    {i18n.grades.void}
                                </button>
                                <ConfirmDialog
                                    open={confirmVoid}
                                    title={i18n.grades.voidConfirmTitle}
                                    description={i18n.grades.voidConfirmDesc}
                                    confirmLabel={i18n.grades.voidGradeAction}
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
                            <div className="grid gap-3 rounded-md border border-[color:var(--sis-powder-blue)] p-4">
                                <h2 className="font-semibold">{i18n.grades.finalizeGradeAction}</h2>
                                <p className="text-sm opacity-80">{i18n.grades.finalizeDesc}</p>
                                <button
                                    type="button"
                                    disabled={finalizing}
                                    className="sis-ops-hub__link w-fit px-4 py-2 text-sm disabled:opacity-50"
                                    onClick={() => setConfirmFinalize(true)}
                                >
                                    {i18n.grades.finalize}
                                </button>
                                <ConfirmDialog
                                    open={confirmFinalize}
                                    title={i18n.grades.finalizeConfirmTitle}
                                    description={i18n.grades.finalizeConfirmDesc}
                                    confirmLabel={i18n.grades.finalizeGradeAction}
                                    confirmPending={finalizing}
                                    onConfirm={() => {
                                        setFinalizing(true);
                                        router.post(
                                            `/grades/${grade.id}/finalize`,
                                            { academic_year_id: grade.academic_year_id },
                                            {
                                                onFinish: () => {
                                                    setFinalizing(false);
                                                    setConfirmFinalize(false);
                                                },
                                            },
                                        );
                                    }}
                                    onOpenChange={setConfirmFinalize}
                                />
                            </div>
                        </section>
                    </>
                ) : (
                    <p className="text-sm opacity-80">{i18n.grades.enterGradeIdHint}</p>
                )}
            </div>
        </AppLayout>
    );
}
