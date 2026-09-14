import { Head, Link } from '@inertiajs/react';
import { t } from '@/i18n';
import { dashboard } from '@/routes';

type GateStatus = 'advanced' | 'partial' | 'gap';

type GateRow = {
    id: string;
    title: string;
    status: GateStatus;
    note: string;
};

export default function Dashboard() {
    const i18n = t();

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

    return (
        <>
            <Head title={i18n.dashboard.title} />
            <div className="sis-ops-hub flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6" dir="rtl" lang="ar">
                <header className="sis-ops-hub__hero max-w-3xl">
                    <p className="sis-ops-hub__eyebrow">{i18n.dashboard.eyebrow}</p>
                    <h1 className="sis-ops-hub__title">{i18n.dashboard.title}</h1>
                    <p className="sis-ops-hub__lead">{i18n.dashboard.lead}</p>
                </header>

                <section aria-labelledby="ops-flows-heading" className="sis-ops-hub__section">
                    <h2 id="ops-flows-heading" className="sis-ops-hub__section-title">
                        {i18n.dashboard.available}
                    </h2>
                    <ul className="sis-ops-hub__links">
                        <li>
                            <Link href="/students" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">{i18n.modules.students}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.studentsDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/enrollments" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">{i18n.modules.enrollments}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.enrollmentsDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/attendance" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">{i18n.modules.attendance}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.attendanceDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/teachers" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">{i18n.modules.teachers}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.teachersDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/timetable" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">{i18n.modules.timetable}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.timetableDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/results" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">{i18n.modules.results}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.resultsDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/exams" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">{i18n.modules.exams}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.examsDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/grades" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">{i18n.modules.grades}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.gradesDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link href="/reports" prefetch className="sis-ops-hub__link">
                                <span className="sis-ops-hub__link-title">{i18n.modules.reports}</span>
                                <span className="sis-ops-hub__link-desc">{i18n.modules.reportsDesc}</span>
                            </Link>
                        </li>
                        <li>
                            <Link
                                href="/intelligence/recommendations"
                                prefetch
                                className="sis-ops-hub__link"
                            >
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
        </>
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
