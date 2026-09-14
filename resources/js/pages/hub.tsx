import { Head, Link, usePage } from '@inertiajs/react';
import { WindowManagerProvider, useWindowManager } from '@/window/window-manager-context';
import { DesktopWorkspace } from '@/window/desktop-workspace';
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

const gates: GateRow[] = [
    { id: 'G1', title: 'Core SIS', status: 'advanced', note: 'Schools, years, students, enrollment' },
    { id: 'G2', title: 'Academic Operations', status: 'advanced', note: 'Curriculum, teachers, classes, attendance' },
    { id: 'G3', title: 'Exams & Grades', status: 'advanced', note: 'Sessions, grades, correction/finalize' },
    { id: 'G4', title: 'Student Lifecycle', status: 'partial', note: 'Admission, promotion, transfers, certificates' },
    { id: 'G5', title: 'Scheduling', status: 'partial', note: 'Periods/schedule API; conflict UI pending' },
    { id: 'G6', title: 'Results', status: 'advanced', note: 'Term/annual/GPA/transcript Inertia + API' },
    { id: 'G7', title: 'Operational UI', status: 'partial', note: 'Daily lists + forms wired' },
    { id: 'G8', title: 'Release Readiness', status: 'partial', note: 'Window shell + guest hub + checklist in progress' },
];

const modules: ModuleLink[] = [
    { windowId: 'student.list', title: 'Students', description: 'Directory and profiles', requiresAuth: true },
    { windowId: 'enrollment.list', title: 'Enrollments', description: 'Placements and forms', requiresAuth: true },
    { windowId: 'attendance.list', title: 'Attendance', description: 'Sessions, mark, close', requiresAuth: true },
    { windowId: 'teachers.list', title: 'Teachers', description: 'Staff directory', requiresAuth: true },
    { windowId: 'timetable.list', title: 'Timetable', description: 'Section schedules', requiresAuth: true },
    { windowId: 'results.list', title: 'Results', description: 'Official term/annual/GPA', requiresAuth: true },
    { windowId: 'exams.list', title: 'Exams', description: 'Exam catalog', requiresAuth: true },
    { windowId: 'grades.list', title: 'Grades', description: 'Enter and finalize', requiresAuth: true },
    { windowId: 'reports.hub', title: 'Reports', description: 'Daily summary and roster', requiresAuth: true },
];

const statusLabel: Record<GateStatus, string> = {
    advanced: 'Advanced',
    partial: 'Needs work',
    gap: 'Largest gap',
};

function HubDesktopBody({ desktop }: { desktop: boolean }) {
    const wm = useWindowManager();
    const { auth } = usePage().props as { auth?: { user?: unknown } };
    const loggedIn = Boolean(auth?.user);

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
        <div className="sis-desktop-shell">
            <div className="sis-desktop-shell__menubar">
                <span className="sis-desktop-shell__brand">SIS</span>
                {modules.slice(0, 6).map((mod) => (
                    <button
                        key={mod.windowId}
                        type="button"
                        className="sis-desktop-shell__action"
                        onClick={() => openModule(mod)}
                    >
                        {mod.title}
                    </button>
                ))}
                {!loggedIn ? (
                    <Link href={login()} className="sis-desktop-shell__action ms-auto">
                        Log in for school data
                    </Link>
                ) : (
                    <Link href="/dashboard" className="sis-desktop-shell__action ms-auto">
                        Authenticated dashboard
                    </Link>
                )}
            </div>

            <div className="sis-ops-hub flex flex-col gap-6 p-4 md:p-6">
                <header className="sis-ops-hub__hero max-w-3xl">
                    <p className="sis-ops-hub__eyebrow">Guest Operational Hub</p>
                    <h1 className="sis-ops-hub__title">SIS Desktop Workspace</h1>
                    <p className="sis-ops-hub__lead">
                        Static hub — no school PII without login. On desktop, modules open as
                        Windows-style windows managed by the central Window Manager.
                    </p>
                </header>

                <section aria-labelledby="hub-modules-heading" className="sis-ops-hub__section">
                    <h2 id="hub-modules-heading" className="sis-ops-hub__section-title">
                        Modules
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
                                        {mod.requiresAuth && !loggedIn ? ' · login required' : ''}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>

                <section aria-labelledby="hub-gates-heading" className="sis-ops-hub__section">
                    <h2 id="hub-gates-heading" className="sis-ops-hub__section-title">
                        Operational Transition Gate
                    </h2>
                    <ul className="sis-ops-hub__gates">
                        {gates.map((gate) => (
                            <li
                                key={gate.id}
                                className={`sis-ops-hub__gate sis-ops-hub__gate--${gate.status}`}
                            >
                                <div className="sis-ops-hub__gate-head">
                                    <span className="sis-ops-hub__gate-id">{gate.id}</span>
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
    const params = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
    const embed = params.get('embed') === '1';
    const desktop =
        !embed &&
        (params.get('desktop') === '1' ||
            (typeof window !== 'undefined' && window.matchMedia('(min-width: 1024px)').matches));

    if (embed) {
        return (
            <>
                <Head title="Operational Hub" />
                <div className="sis-ops-hub p-4">
                    <h1 className="sis-ops-hub__title">Operational Hub</h1>
                    <p className="sis-ops-hub__lead">Embedded hub pane.</p>
                </div>
            </>
        );
    }

    return (
        <WindowManagerProvider>
            <Head title="SIS Operational Hub" />
            <HubDesktopBody desktop={desktop} />
        </WindowManagerProvider>
    );
}
