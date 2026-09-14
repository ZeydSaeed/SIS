import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { WindowManagerProvider, useWindowManager } from '@/window/window-manager-context';
import { DesktopWorkspace } from '@/window/desktop-workspace';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { t } from '@/i18n';
import { login } from '@/routes';

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
    requiresAuth: boolean;
};

function HubDesktopBody({ desktop }: { desktop: boolean }) {
    const i18n = t();
    const wm = useWindowManager();
    const { auth } = usePage().props as { auth?: { user?: unknown } };
    const loggedIn = Boolean(auth?.user);
    const [confirmCloseAll, setConfirmCloseAll] = useState(false);

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

    const modules: ModuleLink[] = [
        { windowId: 'student.list', title: i18n.modules.students, description: i18n.modules.studentsDesc, requiresAuth: true },
        { windowId: 'enrollment.list', title: i18n.modules.enrollments, description: i18n.modules.enrollmentsDesc, requiresAuth: true },
        { windowId: 'attendance.list', title: i18n.modules.attendance, description: i18n.modules.attendanceDesc, requiresAuth: true },
        { windowId: 'teachers.list', title: i18n.modules.teachers, description: i18n.modules.teachersDesc, requiresAuth: true },
        { windowId: 'timetable.list', title: i18n.modules.timetable, description: i18n.modules.timetableDesc, requiresAuth: true },
        { windowId: 'results.list', title: i18n.modules.results, description: i18n.modules.resultsDesc, requiresAuth: true },
        { windowId: 'exams.list', title: i18n.modules.exams, description: i18n.modules.examsDesc, requiresAuth: true },
        { windowId: 'grades.list', title: i18n.modules.grades, description: i18n.modules.gradesDesc, requiresAuth: true },
        { windowId: 'reports.hub', title: i18n.modules.reports, description: i18n.modules.reportsDesc, requiresAuth: true },
    ];

    const statusLabel: Record<GateStatus, string> = {
        advanced: i18n.gates.advanced,
        partial: i18n.gates.partial,
        gap: i18n.gates.gap,
    };

    const openModule = (mod: ModuleLink) => {
        if (mod.requiresAuth && !loggedIn) {
            window.location.href = `/login?redirect=${encodeURIComponent('/hub?desktop=1')}`;
            return;
        }
        if (desktop) {
            wm.open(mod.windowId);
            return;
        }
        const map: Record<string, string> = {
            'student.list': '/students',
            'enrollment.list': '/enrollments',
            'attendance.list': '/attendance',
            'teachers.list': '/teachers',
            'timetable.list': '/timetable',
            'results.list': '/results',
            'exams.list': '/exams',
            'grades.list': '/grades',
            'reports.hub': '/reports',
        };
        window.location.href = map[mod.windowId] ?? '/hub';
    };

    return (
        <div className="sis-desktop-shell" lang="ar" dir="rtl">
            <div className="sis-desktop-shell__menubar" role="menubar" aria-label={i18n.hub.desktopMenu}>
                <span className="sis-desktop-shell__brand">{i18n.brand}</span>
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
                {desktop ? (
                    <>
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
                    </>
                ) : null}
                {!loggedIn ? (
                    <Link href={login()} className="sis-desktop-shell__action ms-auto">
                        {i18n.hub.loginForData}
                    </Link>
                ) : (
                    <Link href="/dashboard" className="sis-desktop-shell__action ms-auto">
                        {i18n.hub.authDashboard}
                    </Link>
                )}
            </div>

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

            <div className="sis-ops-hub flex flex-col gap-6 p-4 md:p-6">
                <header className="sis-ops-hub__hero max-w-3xl">
                    <p className="sis-ops-hub__eyebrow">{i18n.hub.eyebrow}</p>
                    <h1 className="sis-ops-hub__title">{i18n.hub.title}</h1>
                    <p className="sis-ops-hub__lead">{i18n.hub.lead}</p>
                </header>

                <section aria-labelledby="hub-modules-heading" className="sis-ops-hub__section">
                    <h2 id="hub-modules-heading" className="sis-ops-hub__section-title">
                        {i18n.hub.modules}
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
                                    <span className="sis-ops-hub__link-desc">
                                        {mod.description}
                                        {mod.requiresAuth && !loggedIn ? ` · ${i18n.hub.loginRequired}` : ''}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>

                <section aria-labelledby="hub-gates-heading" className="sis-ops-hub__section">
                    <h2 id="hub-gates-heading" className="sis-ops-hub__section-title">
                        {i18n.hub.gates}
                    </h2>
                    <ul className="sis-ops-hub__gates">
                        {gates.map((gate) => (
                            <li
                                key={gate.id}
                                className={`sis-ops-hub__gate sis-ops-hub__gate--${gate.status}`}
                            >
                                <div className="sis-ops-hub__gate-head">
                                    <span className="sis-ops-hub__gate-id" dir="ltr">
                                        {gate.id}
                                    </span>
                                    <span className="sis-ops-hub__gate-title">{gate.title}</span>
                                    <span className="sis-ops-hub__gate-status">
                                        {statusLabel[gate.status]}
                                    </span>
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

export default function Hub() {
    const i18n = t();
    const params = useMemo(() => {
        if (typeof window === 'undefined') {
            return new URLSearchParams();
        }
        return new URLSearchParams(window.location.search);
    }, []);
    const embed = params.get('embed') === '1';
    const desktop =
        !embed &&
        (params.get('desktop') === '1' ||
            params.get('shell') === '1' ||
            (typeof window !== 'undefined' && window.matchMedia('(min-width: 1024px)').matches));

    if (embed) {
        return (
            <>
                <Head title={i18n.hub.title} />
                <div className="sis-ops-hub p-4" lang="ar" dir="rtl">
                    <h1 className="sis-ops-hub__title">{i18n.hub.title}</h1>
                    <p className="sis-ops-hub__lead">{i18n.hub.embedded}</p>
                </div>
            </>
        );
    }

    return (
        <WindowManagerProvider>
            <Head title={i18n.hub.title} />
            <HubDesktopBody desktop={desktop} />
        </WindowManagerProvider>
    );
}
