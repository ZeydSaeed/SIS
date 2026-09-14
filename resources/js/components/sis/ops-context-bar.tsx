import { router, usePage } from '@inertiajs/react';
import { t } from '@/i18n';

type SchoolOption = { id: number; name: string; code: string };
type YearOption = { id: number; name: string; code: string; is_current: boolean };

type SharedProps = {
    schoolContext?: { schoolId: number | null; schools: SchoolOption[] };
    academicYears?: YearOption[];
};

/** School + academic year context controls for authenticated ops shell. */
export function OpsContextBar() {
    const i18n = t();
    const { schoolContext, academicYears } = usePage().props as SharedProps;
    const schools = schoolContext?.schools ?? [];
    const schoolId = schoolContext?.schoolId ?? null;
    const years = academicYears ?? [];

    if (schools.length === 0 && years.length === 0) {
        return null;
    }

    return (
        <div
            className="flex flex-wrap items-end gap-3 border-b border-[color:var(--sis-powder-blue)] px-4 py-3"
            dir="rtl"
            lang="ar"
            aria-label={i18n.context.contextBar}
        >
            {schools.length > 0 ? (
                <label className="flex min-w-[12rem] flex-col gap-1 text-sm">
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

            {years.length === 0 ? (
                <p className="text-sm text-[color:var(--sis-powder-blush)]">{i18n.context.noYear}</p>
            ) : null}
        </div>
    );
}
