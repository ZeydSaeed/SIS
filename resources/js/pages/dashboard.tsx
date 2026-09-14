import { Head, Link } from '@inertiajs/react';
import { dashboard } from '@/routes';

type GateStatus = 'advanced' | 'partial' | 'gap';

type GateRow = {
    id: string;
    title: string;
    status: GateStatus;
    note: string;
};

const gates: GateRow[] = [
    { id: 'G1', title: 'Core SIS', status: 'advanced', note: 'Schools, years, students, enrollment' },
    { id: 'G2', title: 'Academic Operations', status: 'advanced', note: 'Curriculum, teachers, classes, attendance' },
    { id: 'G3', title: 'Exams & Grades', status: 'advanced', note: 'Sessions, grades, correction/finalize' },
    { id: 'G4', title: 'Student Lifecycle', status: 'partial', note: 'Admission, promotion, transfers, certificates' },
    { id: 'G5', title: 'Scheduling', status: 'partial', note: 'Periods/schedule API; conflict UI pending' },
    { id: 'G6', title: 'Results', status: 'partial', note: 'Term/annual/GPA/transcript reads; UI thin' },
    { id: 'G7', title: 'Operational UI', status: 'gap', note: 'Daily staff/teacher/admin flows' },
    { id: 'G8', title: 'Release Readiness', status: 'gap', note: 'Regression, RLS, backup, perf baseline' },
];

const statusLabel: Record<GateStatus, string> = {
    advanced: 'Advanced',
    partial: 'Needs work',
    gap: 'Largest gap',
};

export default function Dashboard() {
    return (
        <>
            <Head title="Operational Hub" />
            <div className="sis-ops-hub flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <header className="sis-ops-hub__hero max-w-3xl">
                    <p className="sis-ops-hub__eyebrow">SIS Core Operational Release</p>
                    <h1 className="sis-ops-hub__title">Operational Hub</h1>
                    <p className="sis-ops-hub__lead">
                        Daily school work starts here. Enrichment stops when G1–G8 pass — not when
                        Payroll, Inventory, or Desktop appear.
                    </p>
                </header>

                <section aria-labelledby="ops-flows-heading" className="sis-ops-hub__section">
                    <h2 id="ops-flows-heading" className="sis-ops-hub__section-title">
                        Available now
                    </h2>
                    <ul className="sis-ops-hub__links">
                        <li>
                            <Link href="/students" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">Students</span>
                                <span className="sis-ops-hub__link-desc">
                                    Directory, profile, enrollment-facing student work
                                </span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/enrollments" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">Enrollments</span>
                                <span className="sis-ops-hub__link-desc">
                                    Placements by class and section for the school year
                                </span>
                            </Link>
                        </li>
                        <li>
                            <Link
                                href="/intelligence/recommendations"
                                prefetch
                                className="sis-ops-hub__link"
                            >
                                <span className="sis-ops-hub__link-title">Database Intelligence</span>
                                <span className="sis-ops-hub__link-desc">
                                    Recommendations and operational DB guidance
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section aria-labelledby="ops-gates-heading" className="sis-ops-hub__section">
                    <h2 id="ops-gates-heading" className="sis-ops-hub__section-title">
                        Operational Transition Gate
                    </h2>
                    <p className="sis-ops-hub__section-lead">
                        When every gate is PASS: stop enrichment and enter the operational phase.
                    </p>
                    <ul className="sis-ops-hub__gates">
                        {gates.map((gate) => (
                            <li key={gate.id} className={`sis-ops-hub__gate sis-ops-hub__gate--${gate.status}`}>
                                <div className="sis-ops-hub__gate-head">
                                    <span className="sis-ops-hub__gate-id">{gate.id}</span>
                                    <span className="sis-ops-hub__gate-title">{gate.title}</span>
                                    <span className="sis-ops-hub__gate-status">{statusLabel[gate.status]}</span>
                                </div>
                                <p className="sis-ops-hub__gate-note">{gate.note}</p>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
