import { Head, Link, usePage } from '@inertiajs/react';
import { t } from '@/i18n';

export default function SchoolContextRequired({ message }: { message: string }) {
    const i18n = t();
    const { schoolContext } = usePage().props as {
        schoolContext?: { schools: Array<{ id: number; name: string }> };
    };
    const schools = schoolContext?.schools ?? [];

    return (
        <>
            <Head title={i18n.context.schoolRequiredTitle} />
            <div className="sis-ops-hub mx-auto flex max-w-lg flex-col gap-4 p-6" dir="rtl" lang="ar">
                <h1 className="sis-ops-hub__title text-2xl">{i18n.context.schoolRequiredTitle}</h1>
                <p className="sis-ops-hub__lead">{message}</p>
                {schools.length === 0 ? (
                    <p className="text-sm opacity-80">{i18n.context.noSchools}</p>
                ) : (
                    <p className="text-sm opacity-80">{i18n.context.pickSchoolHint}</p>
                )}
                <Link href="/dashboard" className="sis-ops-hub__link w-fit px-4 py-2 text-sm">
                    {i18n.dashboard.title}
                </Link>
            </div>
        </>
    );
}
