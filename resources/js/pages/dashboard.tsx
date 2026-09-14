import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { t } from '@/i18n';
import { dashboard } from '@/routes';
import { DesktopWorkspace } from '@/window/desktop-workspace';
import { WindowManagerProvider, useWindowManager } from '@/window/window-manager-context';

type GateStatus = 'advanced' | 'partial' | 'gap';

type GateRow = {
    id: string;
    title: string;
    status: GateStatus;
    note: string;
};

type ModuleLink = {
    windowId: string;
    title: string;
    description: string;
    href: string;
};

function DashboardBody({ desktop }: { desktop: boolean }) {
    const i18n = t();
    const wm = useWindowManager();
    const [confirmCloseAll, setConfirmCloseAll] = useState(false);
    const { opsBootstrap } = usePage().props as {
        opsBootstrap?: { enabled: boolean; needed: boolean };
    };
    const bootstrapNeeded = Boolean(opsBootstrap?.enabled && opsBootstrap?.needed);

    const modules: ModuleLink[] = [
        { windowId: 'student.list', title: i18n.modules.students, description: i18n.modules.studentsDesc, href: '/students' },
        { windowId: 'enrollment.list', title: i18n.modules.enrollments, description: i18n.modules.enrollmentsDesc, href: '/enrollments' },
        { windowId: 'attendance.list', title: i18n.modules.attendance, description: i18n.modules.attendanceDesc, href: '/attendance' },
        { windowId: 'teachers.list', title: i18n.modules.teachers, description: i18n.modules.teachersDesc, href: '/teachers' },
        { windowId: 'timetable.list', title: i18n.modules.timetable, description: i18n.modules.timetableDesc, href: '/timetable' },
        { windowId: 'results.list', title: i18n.modules.results, description: i18n.modules.resultsDesc, href: '/results' },
        { windowId: 'exams.list', title: i18n.modules.exams, description: i18n.modules.examsDesc, href: '/exams' },
        { windowId: 'grades.list', title: i18n.modules.grades, description: i18n.modules.gradesDesc, href: '/grades' },
        { windowId: 'reports.hub', title: i18n.modules.reports, description: i18n.modules.reportsDesc, href: '/reports' },
    ];

    const gates: GateRow[] = [
        { id: 'G1', title: i18n.gates.g1, status: 'advanced', note: i18n.gates.g1Note },
        { id: 'G2', title: i18n.gates.g2, status: 'advanced', note: i18n.gates.g2Note },
        { id: 'G3', title: i18n.gates.g3, status: 'advanced', note: i18n.gates.g3Note },
        { id: 'G4', title: i18n.gates.g4, status: 'partial', note: i18n.gates.g4Note },
        { id: 'G5', title: i18n.gates.g5, status: 'partial', note: i18n.gates.g5Note },
        { id: 'G6', title: i18n.gates.g6, status: 'advanced', note: i18n.gates.g6Note },
        { id: 'G7', title: i18n.gates.g7, status: 'partial', note: i18n.gates.g7Note },
        { id: 'G8', title: i18n.gates.g8, status: 'partial', note: i18n.gates.g8Note },
    ];

    const statusLabel: Record<GateStatus, string> = {
        advanced: i18n.gates.advanced,
        partial: i18n.gates.partial,
        gap: i18n.gates.gap,
    };

    const openModule = (mod: ModuleLink) => {
        if (desktop) {
            wm.open(mod.windowId);
            return;
        }
        window.location.href = mod.href;
    };

    return (
        <div className="sis-desktop-shell" lang="ar" dir="rtl">
            {desktop ? (
                <div className="sis-desktop-shell__menubar" role="menubar" aria-label={i18n.hub.desktopMenu}>
                    <span className="sis-desktop-shell__brand">{i18n.dashboard.title}</span>
                    <nav className="sis-desktop-shell__dock" aria-label={i18n.hub.moduleLauncher}>
                        {modules.map((mod) => (
                            <button
                                key={mod.windowId}
                                type="button"
                                role="menuitem"
                                className="sis-desktop-shell__action"
                                onClick={() => openModule(mod)}
                            >
                                {mod.title}
                            </button>
                        ))}
                    </nav>
                    <button type="button" className="sis-desktop-shell__action" onClick={() => wm.cascade()}>
                        {i18n.hub.cascade}
                    </button>
                    <button
                        type="button"
                        className="sis-desktop-shell__action"
                        onClick={() => setConfirmCloseAll(true)}
                        disabled={wm.windows.length === 0}
                    >
                        {i18n.hub.closeAll}
                    </button>
                    <Link href="/hub?desktop=1" className="sis-desktop-shell__action ms-auto">
                        {i18n.dashboard.guestHub}
                    </Link>
                </div>
            ) : null}

            <ConfirmDialog
                open={confirmCloseAll}
                title={i18n.hub.closeAllTitle}
                description={i18n.hub.closeAllDesc}
                confirmLabel={i18n.hub.closeAll}
                onConfirm={() => {
                    wm.windows.forEach((w) => wm.close(w.instanceId));
                    setConfirmCloseAll(false);
                }}
                onOpenChange={setConfirmCloseAll}
            />

            <div className="sis-ops-hub flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <header className="sis-ops-hub__hero max-w-3xl">
                    <p className="sis-ops-hub__eyebrow">{i18n.dashboard.eyebrow}</p>
                    <h1 className="sis-ops-hub__title">{i18n.dashboard.title}</h1>
                    <p className="sis-ops-hub__lead">{i18n.dashboard.lead}</p>
                    {!desktop ? (
                        <p className="mt-3">
                            <Link href="/dashboard?desktop=1" className="sis-ops-hub__link inline-flex">
                                <span className="sis-ops-hub__link-title">{i18n.dashboard.openWindows}</span>
                            </Link>
                        </p>
                    ) : null}
                    {bootstrapNeeded ? (
                        <div
                            className="mt-4 rounded-md border border-[color:var(--sis-powder-blush)] bg-[color-mix(in_srgb,var(--sis-powder-blush)_22%,white)] p-4 text-sm"
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
                                        {processing
                                            ? i18n.context.bootstrapWorking
                                            : i18n.context.bootstrapAction}
                                    </button>
                                )}
                            </Form>
                        </div>
                    ) : null}
                </header>

                <section aria-labelledby="ops-flows-heading" className="sis-ops-hub__section">
                    <h2 id="ops-flows-heading" className="sis-ops-hub__section-title">
                        {i18n.dashboard.available}
                    </h2>
                    <ul className="sis-ops-hub__links">
                        {modules.map((mod) => (
                            <li key={mod.windowId}>
                                <button
                                    type="button"
                                    className="sis-ops-hub__link w-full text-start"
                                    onClick={() => openModule(mod)}
                                >
                                    <span className="sis-ops-hub__link-title">{mod.title}</span>
                                    <span className="sis-ops-hub__link-desc">{mod.description}</span>
                                </button>
                            </li>
                        ))}
                        <li>
                            <Link href="/intelligence/recommendations" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">{i18n.modules.intelligence}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.intelligenceDesc}</span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section aria-labelledby="ops-gates-heading" className="sis-ops-hub__section">
                    <h2 id="ops-gates-heading" className="sis-ops-hub__section-title">
                        {i18n.dashboard.gateSection}
                    </h2>
                    <p className="sis-ops-hub__section-lead">{i18n.dashboard.gateLead}</p>
                    <ul className="sis-ops-hub__gates">
                        {gates.map((gate) => (
                            <li key={gate.id} className={`sis-ops-hub__gate sis-ops-hub__gate--${gate.status}`}>
                                <div className="sis-ops-hub__gate-head">
                                    <span className="sis-ops-hub__gate-id" dir="ltr">
                                        {gate.id}
                                    </span>
                                    <span className="sis-ops-hub__gate-title">{gate.title}</span>
                                    <span className="sis-ops-hub__gate-status">{statusLabel[gate.status]}</span>
                                </div>
                                <p className="sis-ops-hub__gate-note">{gate.note}</p>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            {desktop ? <DesktopWorkspace /> : null}
        </div>
    );
}

export default function Dashboard() {
    const i18n = t();
    const { auth } = usePage().props as { auth?: { user?: unknown } };
    const params = useMemo(() => {
        if (typeof window === 'undefined') {
            return new URLSearchParams();
        }
        return new URLSearchParams(window.location.search);
    }, []);
    const desktop = params.get('desktop') === '1' || params.get('shell') === '1';

    return (
        <WindowManagerProvider>
            <Head title={i18n.dashboard.title} />
            <DashboardBody desktop={desktop && Boolean(auth?.user)} />
        </WindowManagerProvider>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'لوحة التحكم',
            href: dashboard(),
        },
    ],
};
