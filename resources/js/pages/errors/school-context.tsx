import { Form, Head, Link, usePage } from '@inertiajs/react';
import { t } from '@/i18n';

export default function SchoolContextRequired({ message }: { message: string }) {
    const i18n = t();
    const { schoolContext } = usePage().props as {
        schoolContext?: { schools: Array<{ id: number; name: string; code: string }> };
    };
    const schools = schoolContext?.schools ?? [];

    return (
        <>
            <Head title={i18n.context.schoolRequiredTitle} />
            <div className="sis-ops-hub mx-auto flex max-w-xl flex-col gap-5 p-6" dir="rtl" lang="ar">
                <header className="sis-ops-hub__hero">
                    <p className="sis-ops-hub__eyebrow">{i18n.brand}</p>
                    <h1 className="sis-ops-hub__title">{i18n.context.schoolRequiredTitle}</h1>
                    <p className="sis-ops-hub__lead">{message}</p>
                </header>

                {schools.length === 0 ? (
                    <div
                        className="rounded-md border border-[color:var(--sis-powder-blush)] bg-[color-mix(in_srgb,var(--sis-powder-blush)_25%,white)] p-4 text-sm"
                        role="alert"
                    >
                        <p className="font-medium">{i18n.context.noSchools}</p>
                        <p className="mt-2 opacity-80">
                            اطلب من مسؤول النظام ربط دورك بمدرسة في جدول صلاحيات المستخدمين
                            (security.user_roles.school_id)، ثم أعد تسجيل الدخول.
                        </p>
                    </div>
                ) : (
                    <Form
                        action="/context/school"
                        method="post"
                        className="flex flex-col gap-3"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <>
                                <p className="text-sm opacity-80">{i18n.context.pickSchoolHint}</p>
                                <label className="flex flex-col gap-1 text-sm">
                                    <span>{i18n.context.school}</span>
                                    <select
                                        name="school_id"
                                        required
                                        className="sis-ops-hub__link min-h-11 px-3 py-2"
                                        defaultValue={schools[0]?.id}
                                    >
                                        {schools.map((school) => (
                                            <option key={school.id} value={school.id}>
                                                {school.name} ({school.code})
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="sis-ops-hub__link min-h-11 w-fit px-4 py-2 text-sm"
                                >
                                    {i18n.context.switchSchool}
                                </button>
                            </>
                        )}
                    </Form>
                )}

                <Link href="/dashboard" className="sis-ops-hub__link w-fit px-4 py-2 text-sm">
                    {i18n.dashboard.title}
                </Link>
                <Link href="/hub?desktop=1" className="sis-ops-hub__link w-fit px-4 py-2 text-sm">
                    {i18n.dashboard.guestHub}
                </Link>
            </div>
        </>
    );
}
