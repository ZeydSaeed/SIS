import { Form, router, usePage } from '@inertiajs/react';
import { t } from '@/i18n';

type SchoolOption = { id: number; name: string; code: string };
type YearOption = { id: number; name: string; code: string; is_current: boolean };

type SharedProps = {
    schoolContext?: { schoolId: number | null; schools: SchoolOption[] };
    academicYears?: YearOption[];
    academicYearId?: number | null;
    opsBootstrap?: { enabled: boolean; needed: boolean };
    flash?: { success?: string | null; error?: string | null };
};

function OpsBootstrapPanel({ needed }: { needed: boolean }) {
    const i18n = t();

    if (!needed) {
        return null;
    }

    return (
        <div
            className="w-full rounded-md border border-[color:var(--sis-powder-blush)] bg-[color-mix(in_srgb,var(--sis-powder-blush)_22%,white)] p-3 text-sm"
            role="status"
        >
            <p className="font-medium">{i18n.context.bootstrapTitle}</p>
            <p className="mt-1 opacity-85">{i18n.context.bootstrapLead}</p>
            <Form
                action="/context/ops-bootstrap"
                method="post"
                className="mt-3"
                options={{ preserveScroll: false }}
            >
                {({ processing }) => (
                    <button
                        type="submit"
                        disabled={processing}
                        className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm"
                    >
                        {processing ? i18n.context.bootstrapWorking : i18n.context.bootstrapAction}
                    </button>
                )}
            </Form>
        </div>
    );
}

/** School + academic year context controls for authenticated ops shell. */
export function OpsContextBar() {
    const i18n = t();
    const { schoolContext, academicYears, academicYearId, opsBootstrap, flash } =
        usePage().props as SharedProps;
    const schools = schoolContext?.schools ?? [];
    const schoolId = schoolContext?.schoolId ?? null;
    const years = academicYears ?? [];
    const yearId =
        academicYearId ??
        years.find((y) => y.is_current)?.id ??
        years[0]?.id ??
        null;
    const bootstrapNeeded = Boolean(opsBootstrap?.enabled && opsBootstrap?.needed);

    if (schools.length === 0 && years.length === 0) {
        return (
            <div className="flex flex-col gap-3 border-b border-[color:var(--sis-powder-blush)] px-4 py-3" dir="rtl" lang="ar">
                {flash?.success ? (
                    <p className="text-sm" role="status">
                        {flash.success}
                    </p>
                ) : null}
                <p className="text-sm" role="status">
                    {i18n.context.noSchools} — {i18n.context.noYear}
                </p>
                <OpsBootstrapPanel needed={bootstrapNeeded} />
            </div>
        );
    }

    return (
        <div
            className="flex flex-wrap items-end gap-3 border-b border-[color:var(--sis-powder-blue)] px-4 py-3"
            dir="rtl"
            lang="ar"
            aria-label={i18n.context.contextBar}
        >
            {flash?.success ? (
                <p className="w-full text-sm" role="status">
                    {flash.success}
                </p>
            ) : null}

            <OpsBootstrapPanel needed={bootstrapNeeded} />

            {schools.length > 0 ? (
                <label className="flex min-w-[14rem] flex-1 flex-col gap-1 text-sm">
                    <span>{i18n.context.school}</span>
                    <select
                        className="sis-ops-hub__link min-h-11 px-3 py-2"
                        value={schoolId ?? ''}
                        aria-label={i18n.context.switchSchool}
                        onChange={(event) => {
                            const next = Number(event.target.value);
                            if (!Number.isFinite(next) || next < 1) {
                                return;
                            }
                            router.post(
                                '/context/school',
                                { school_id: next },
                                { preserveScroll: true },
                            );
                        }}
                    >
                        {schools.map((school) => (
                            <option key={school.id} value={school.id}>
                                {school.name} ({school.code})
                            </option>
                        ))}
                    </select>
                </label>
            ) : (
                <p className="text-sm text-[color:var(--sis-powder-blush)]">{i18n.context.noSchools}</p>
            )}

            {years.length > 0 ? (
                <label className="flex min-w-[14rem] flex-1 flex-col gap-1 text-sm">
                    <span>{i18n.context.year}</span>
                    <select
                        className="sis-ops-hub__link min-h-11 px-3 py-2"
                        value={yearId ?? ''}
                        aria-label={i18n.context.year}
                        onChange={(event) => {
                            const next = Number(event.target.value);
                            if (!Number.isFinite(next) || next < 1) {
                                return;
                            }
                            router.post(
                                '/context/academic-year',
                                { academic_year_id: next },
                                { preserveScroll: true },
                            );
                        }}
                    >
                        {years.map((year) => (
                            <option key={year.id} value={year.id}>
                                {year.name}
                                {year.is_current ? ` · ${i18n.status.active}` : ''}
                            </option>
                        ))}
                    </select>
                </label>
            ) : (
                <p className="text-sm text-[color:var(--sis-powder-blush)]">{i18n.context.noYear}</p>
            )}
        </div>
    );
}
