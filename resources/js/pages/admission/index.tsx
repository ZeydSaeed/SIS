import { Form, Head, router } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { AdmissionWorkflowProgress } from '@/components/admission/admission-workflow-progress';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { PageHeader } from '@/components/sis/page-header';
import { StatusChip } from '@/components/sis/status-chip';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type Period = {
    id: number;
    academic_year_id: number;
    school_id: number;
    name: string;
    start_date: string;
    end_date: string;
    max_applications: number | null;
    status: number;
    created_at: string;
};

type Application = {
    id: number;
    application_period_id: number;
    application_number: string;
    first_name: string;
    last_name: string;
    national_id: string | null;
    birth_date: string;
    gender: number;
    grade_level_id: number;
    specialization_id: number | null;
    status: number;
    submitted_at: string | null;
    reviewed_by: number | null;
    reviewed_at: string | null;
    notes: string | null;
    student_id: number | null;
    created_at: string;
    updated_at: string;
    allowed_transitions: number[];
    can_convert: boolean;
};

type DocumentRow = {
    id: number;
    application_id: number;
    document_type: number;
    storage_key: string;
    file_name: string;
    file_hash: string;
    created_at: string;
};

type GradeLevel = { id: number; name: string };

type WorkflowStep = { status: number; key: string };

type PageProps = {
    workspace: {
        periods: Period[];
        applications: Application[];
        documents: DocumentRow[];
        grade_levels: GradeLevel[];
        workflow_steps: WorkflowStep[];
    };
    filters: {
        academic_year_id: number | null;
    };
    authorization: {
        can_manage: boolean;
    };
};

function applicationStatusLabel(status: number): string {
    const i18n = t().admission;
    const map: Record<number, string> = {
        1: i18n.statusDraft,
        2: i18n.statusSubmitted,
        3: i18n.statusUnderReview,
        4: i18n.statusInterview,
        5: i18n.statusWaitlisted,
        6: i18n.statusAccepted,
        7: i18n.statusRejected,
        8: i18n.statusWithdrawn,
        9: i18n.statusConverted,
    };
    return map[status] ?? String(status);
}

function periodStatusLabel(status: number): string {
    const i18n = t().admission;
    if (status === 0) return i18n.periodInactive;
    if (status === 1) return i18n.periodActive;
    if (status === 2) return i18n.periodArchived;
    return String(status);
}

function documentTypeLabel(type: number): string {
    const i18n = t().admission;
    if (type === 1) return i18n.documentTypeId;
    if (type === 2) return i18n.documentTypeBirth;
    if (type === 3) return i18n.documentTypePhoto;
    return i18n.documentTypeOther;
}

export default function AdmissionIndex({ workspace, filters, authorization }: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.admission.title, href: '/admission' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.admission.title} />
            <div className="sis-ops-hub flex flex-col gap-6 p-4" dir="rtl" lang="ar">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <PageHeader
                        title={i18n.admission.title}
                        icon={<UserPlus className="text-primary size-8" aria-hidden="true" />}
                    />
                    <OpsYearFilter
                        action="/admission"
                        academicYearId={filters.academic_year_id}
                        showLabel
                        inlineLabel
                        showCurrentBadge={false}
                        controlClassName="sis-admission-year-control"
                    />
                </div>

                <AdmissionWorkflowProgress
                    steps={workspace.workflow_steps}
                    applicationStatuses={workspace.applications.map((app) => app.status)}
                />

                <section aria-label={i18n.admission.periodsTitle} className="flex flex-col gap-3">
                    <h2 className="sis-ops-hub__section-title text-base">{i18n.admission.periodsTitle}</h2>
                    {authorization.can_manage ? (
                        <Form
                            action="/admission/periods"
                            method="post"
                            className="border-border grid gap-3 rounded-xl border p-4 md:grid-cols-2"
                            options={{ preserveScroll: true }}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <OpsFormField
                                        label={i18n.admission.academicYearId}
                                        name="academic_year_id"
                                        error={errors.academic_year_id}
                                    >
                                        <OpsTextInput
                                            name="academic_year_id"
                                            type="number"
                                            min={1}
                                            required
                                            defaultValue={filters.academic_year_id ?? undefined}
                                            error={errors.academic_year_id}
                                        />
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.periodName}
                                        name="name"
                                        error={errors.name}
                                    >
                                        <OpsTextInput name="name" required error={errors.name} />
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.startDate}
                                        name="start_date"
                                        error={errors.start_date}
                                    >
                                        <OpsTextInput
                                            name="start_date"
                                            type="datetime-local"
                                            required
                                            error={errors.start_date}
                                        />
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.endDate}
                                        name="end_date"
                                        error={errors.end_date}
                                    >
                                        <OpsTextInput
                                            name="end_date"
                                            type="datetime-local"
                                            required
                                            error={errors.end_date}
                                        />
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.maxApplications}
                                        name="max_applications"
                                        error={errors.max_applications}
                                    >
                                        <OpsTextInput
                                            name="max_applications"
                                            type="number"
                                            min={1}
                                            error={errors.max_applications}
                                        />
                                    </OpsFormField>
                                    <div className="flex items-end">
                                        <Button type="submit" disabled={processing}>
                                            {i18n.admission.openPeriod}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    ) : null}

                    {workspace.periods.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{i18n.admission.emptyPeriods}</p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-black">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr>
                                        <th className="border border-black px-3 py-2 text-start">ID</th>
                                        <th className="border border-black px-3 py-2 text-start">
                                            {i18n.admission.periodName}
                                        </th>
                                        <th className="border border-black px-3 py-2 text-start">
                                            {i18n.admission.startDate}
                                        </th>
                                        <th className="border border-black px-3 py-2 text-start">
                                            {i18n.admission.endDate}
                                        </th>
                                        <th className="border border-black px-3 py-2 text-start">
                                            {i18n.admission.maxApplications}
                                        </th>
                                        <th className="border border-black px-3 py-2 text-start">
                                            {i18n.admission.periodStatus}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {workspace.periods.map((period) => (
                                        <tr key={period.id}>
                                            <td className="border border-black px-3 py-2" dir="ltr">
                                                {period.id}
                                            </td>
                                            <td className="border border-black px-3 py-2">{period.name}</td>
                                            <td className="border border-black px-3 py-2" dir="ltr">
                                                {period.start_date}
                                            </td>
                                            <td className="border border-black px-3 py-2" dir="ltr">
                                                {period.end_date}
                                            </td>
                                            <td className="border border-black px-3 py-2" dir="ltr">
                                                {period.max_applications ?? '—'}
                                            </td>
                                            <td className="border border-black px-3 py-2">
                                                {periodStatusLabel(period.status)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section aria-label={i18n.admission.applicationsTitle} className="flex flex-col gap-3">
                    <h2 className="sis-ops-hub__section-title text-base">
                        {i18n.admission.applicationsTitle}
                    </h2>

                    {authorization.can_manage ? (
                        <Form
                            action="/admission/applications"
                            method="post"
                            className="border-border grid gap-3 rounded-xl border p-4 md:grid-cols-2"
                            options={{ preserveScroll: true }}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <OpsFormField
                                        label={i18n.admission.periodId}
                                        name="application_period_id"
                                        error={errors.application_period_id}
                                    >
                                        <select
                                            name="application_period_id"
                                            required
                                            className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                                            defaultValue={workspace.periods[0]?.id ?? ''}
                                        >
                                            {workspace.periods.map((period) => (
                                                <option key={period.id} value={period.id}>
                                                    {period.name} (#{period.id})
                                                </option>
                                            ))}
                                        </select>
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.firstName}
                                        name="first_name"
                                        error={errors.first_name}
                                    >
                                        <OpsTextInput
                                            name="first_name"
                                            required
                                            error={errors.first_name}
                                        />
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.lastName}
                                        name="last_name"
                                        error={errors.last_name}
                                    >
                                        <OpsTextInput
                                            name="last_name"
                                            required
                                            error={errors.last_name}
                                        />
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.nationalId}
                                        name="national_id"
                                        error={errors.national_id}
                                    >
                                        <OpsTextInput name="national_id" error={errors.national_id} />
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.birthDate}
                                        name="birth_date"
                                        error={errors.birth_date}
                                    >
                                        <OpsTextInput
                                            name="birth_date"
                                            type="date"
                                            required
                                            error={errors.birth_date}
                                        />
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.gender}
                                        name="gender"
                                        error={errors.gender}
                                    >
                                        <select
                                            name="gender"
                                            required
                                            className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                                            defaultValue={1}
                                        >
                                            <option value={1}>{i18n.admission.genderMale}</option>
                                            <option value={2}>{i18n.admission.genderFemale}</option>
                                        </select>
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.gradeLevel}
                                        name="grade_level_id"
                                        error={errors.grade_level_id}
                                    >
                                        <select
                                            name="grade_level_id"
                                            required
                                            className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                                            defaultValue={workspace.grade_levels[0]?.id ?? ''}
                                        >
                                            {workspace.grade_levels.map((level) => (
                                                <option key={level.id} value={level.id}>
                                                    {level.name}
                                                </option>
                                            ))}
                                        </select>
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.specializationId}
                                        name="specialization_id"
                                        error={errors.specialization_id}
                                    >
                                        <OpsTextInput
                                            name="specialization_id"
                                            type="number"
                                            min={1}
                                            error={errors.specialization_id}
                                        />
                                    </OpsFormField>
                                    <OpsFormField
                                        label={i18n.admission.notes}
                                        name="notes"
                                        error={errors.notes}
                                    >
                                        <textarea
                                            name="notes"
                                            rows={2}
                                            className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm md:col-span-2"
                                        />
                                    </OpsFormField>
                                    <div className="flex items-end md:col-span-2">
                                        <Button type="submit" disabled={processing || workspace.periods.length === 0}>
                                            {i18n.admission.createDraft}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    ) : null}

                    {workspace.applications.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{i18n.admission.emptyApplications}</p>
                    ) : (
                        <div className="flex flex-col gap-4">
                            {workspace.applications.map((app) => (
                                <article
                                    key={app.id}
                                    className="border-border flex flex-col gap-3 rounded-xl border p-4"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-semibold" dir="ltr">
                                                {app.application_number}
                                            </span>
                                            <StatusChip kind="admission" status={app.status} />
                                        </div>
                                        <span className="text-muted-foreground text-xs" dir="ltr">
                                            #{app.id}
                                        </span>
                                    </div>

                                    <dl className="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                        <div>
                                            <dt className="text-muted-foreground">{i18n.admission.firstName}</dt>
                                            <dd>{app.first_name}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">{i18n.admission.lastName}</dt>
                                            <dd>{app.last_name}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">{i18n.admission.nationalId}</dt>
                                            <dd dir="ltr">{app.national_id ?? '—'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">{i18n.admission.birthDate}</dt>
                                            <dd dir="ltr">{app.birth_date}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">{i18n.admission.gender}</dt>
                                            <dd>
                                                {app.gender === 1
                                                    ? i18n.admission.genderMale
                                                    : i18n.admission.genderFemale}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">{i18n.admission.gradeLevel}</dt>
                                            <dd dir="ltr">{app.grade_level_id}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                {i18n.admission.specializationId}
                                            </dt>
                                            <dd dir="ltr">{app.specialization_id ?? '—'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">{i18n.admission.periodId}</dt>
                                            <dd dir="ltr">{app.application_period_id}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">{i18n.admission.submittedAt}</dt>
                                            <dd dir="ltr">{app.submitted_at ?? '—'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">{i18n.admission.reviewedBy}</dt>
                                            <dd dir="ltr">{app.reviewed_by ?? '—'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">{i18n.admission.reviewedAt}</dt>
                                            <dd dir="ltr">{app.reviewed_at ?? '—'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">{i18n.admission.studentId}</dt>
                                            <dd dir="ltr">{app.student_id ?? '—'}</dd>
                                        </div>
                                        <div className="sm:col-span-2 lg:col-span-3">
                                            <dt className="text-muted-foreground">{i18n.admission.notes}</dt>
                                            <dd>{app.notes ?? '—'}</dd>
                                        </div>
                                    </dl>

                                    {authorization.can_manage ? (
                                        <div className="flex flex-col gap-3 border-t pt-3">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="text-sm font-medium">
                                                    {i18n.admission.nextStatuses}:
                                                </span>
                                                {app.allowed_transitions.length === 0 ? (
                                                    <span className="text-muted-foreground text-sm">—</span>
                                                ) : (
                                                    app.allowed_transitions.map((status) => (
                                                        <Button
                                                            key={status}
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/admission/applications/${app.id}/transition`,
                                                                    { to_status: status },
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            {applicationStatusLabel(status)}
                                                        </Button>
                                                    ))
                                                )}
                                                {app.can_convert ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(
                                                                `/admission/applications/${app.id}/convert`,
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        {i18n.admission.convert}
                                                    </Button>
                                                ) : null}
                                            </div>

                                            <Form
                                                action={`/admission/applications/${app.id}/documents`}
                                                method="post"
                                                className="grid gap-2 md:grid-cols-4"
                                                options={{ preserveScroll: true }}
                                            >
                                                {({ errors, processing }) => (
                                                    <>
                                                        <OpsFormField
                                                            label={i18n.admission.documentType}
                                                            name="document_type"
                                                            error={errors.document_type}
                                                        >
                                                            <select
                                                                name="document_type"
                                                                required
                                                                className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                                                                defaultValue={1}
                                                            >
                                                                <option value={1}>
                                                                    {i18n.admission.documentTypeId}
                                                                </option>
                                                                <option value={2}>
                                                                    {i18n.admission.documentTypeBirth}
                                                                </option>
                                                                <option value={3}>
                                                                    {i18n.admission.documentTypePhoto}
                                                                </option>
                                                                <option value={4}>
                                                                    {i18n.admission.documentTypeOther}
                                                                </option>
                                                            </select>
                                                        </OpsFormField>
                                                        <OpsFormField
                                                            label={i18n.admission.fileName}
                                                            name="file_name"
                                                            error={errors.file_name}
                                                        >
                                                            <OpsTextInput
                                                                name="file_name"
                                                                required
                                                                error={errors.file_name}
                                                            />
                                                        </OpsFormField>
                                                        <OpsFormField
                                                            label={i18n.admission.storageKey}
                                                            name="storage_key"
                                                            error={errors.storage_key}
                                                        >
                                                            <OpsTextInput
                                                                name="storage_key"
                                                                error={errors.storage_key}
                                                            />
                                                        </OpsFormField>
                                                        <div className="flex items-end">
                                                            <Button type="submit" disabled={processing}>
                                                                {i18n.admission.registerDocument}
                                                            </Button>
                                                        </div>
                                                    </>
                                                )}
                                            </Form>
                                        </div>
                                    ) : null}
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                <section aria-label={i18n.admission.documentsTitle} className="flex flex-col gap-3">
                    <h2 className="sis-ops-hub__section-title text-base">{i18n.admission.documentsTitle}</h2>
                    {workspace.documents.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{i18n.admission.emptyDocuments}</p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-black">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr>
                                        <th className="border border-black px-3 py-2 text-start">ID</th>
                                        <th className="border border-black px-3 py-2 text-start">
                                            {i18n.admission.applicationsTitle}
                                        </th>
                                        <th className="border border-black px-3 py-2 text-start">
                                            {i18n.admission.documentType}
                                        </th>
                                        <th className="border border-black px-3 py-2 text-start">
                                            {i18n.admission.fileName}
                                        </th>
                                        <th className="border border-black px-3 py-2 text-start">
                                            {i18n.admission.storageKey}
                                        </th>
                                        <th className="border border-black px-3 py-2 text-start">
                                            {i18n.admission.fileHash}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {workspace.documents.map((doc) => (
                                        <tr key={doc.id}>
                                            <td className="border border-black px-3 py-2" dir="ltr">
                                                {doc.id}
                                            </td>
                                            <td className="border border-black px-3 py-2" dir="ltr">
                                                {doc.application_id}
                                            </td>
                                            <td className="border border-black px-3 py-2">
                                                {documentTypeLabel(doc.document_type)}
                                            </td>
                                            <td className="border border-black px-3 py-2">{doc.file_name}</td>
                                            <td className="border border-black px-3 py-2" dir="ltr">
                                                {doc.storage_key}
                                            </td>
                                            <td className="border border-black px-3 py-2" dir="ltr">
                                                {doc.file_hash}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
