import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CheckCircle2,
    ClipboardList,
    Clock3,
    FilePenLine,
    FilePlus2,
    GraduationCap,
    MessagesSquare,
    Send,
} from 'lucide-react';
import type {
    AdmissionWorkflowProgress as WorkflowProgressData,
} from '@/components/admission/admission-workspace';
import { t } from '@/i18n';

export type AdmissionWorkflowStep = { status: number; key: string };

type StageVisual = {
    status: number;
    label: string;
    icon: LucideIcon;
    percent: number;
    tone: 'light' | 'dark';
};

const ADMISSION_REQUEST_STEP: AdmissionWorkflowStep = { status: 0, key: 'AdmissionRequest' };

const STAGE_ICONS: Record<number, LucideIcon> = {
    0: FilePlus2,
    1: FilePenLine,
    2: Send,
    3: ClipboardList,
    4: MessagesSquare,
    5: Clock3,
    6: CheckCircle2,
    9: GraduationCap,
};

/** Stages with light backgrounds use dark text; dark backgrounds use white. */
const STAGE_TONE: Record<number, 'light' | 'dark'> = {
    0: 'dark',
    1: 'dark',
    2: 'light',
    3: 'light',
    4: 'dark',
    5: 'light',
    6: 'dark',
    9: 'dark',
};

function labelForStatus(status: number): string {
    const i18n = t().admission;
    const map: Record<number, string> = {
        0: i18n.statusAdmissionRequest,
        1: i18n.statusDraft,
        2: i18n.statusSubmitted,
        3: i18n.statusUnderReview,
        4: i18n.statusInterview,
        5: i18n.statusWaitlisted,
        6: i18n.statusAccepted,
        9: i18n.statusConverted,
    };
    return map[status] ?? String(status);
}

function clampPercent(value: number): number {
    if (value < 0) {
        return 0;
    }
    if (value > 100) {
        return 100;
    }

    return Math.round(value);
}

function percentForStatus(status: number, progress: WorkflowProgressData): number {
    const match = progress.stages.find((stage) => stage.status === status);

    return clampPercent(match?.percent ?? 0);
}

function buildStages(
    steps: AdmissionWorkflowStep[],
    progress: WorkflowProgressData,
): StageVisual[] {
    return steps.map((step) => ({
        status: step.status,
        label: labelForStatus(step.status),
        icon: STAGE_ICONS[step.status] ?? FilePenLine,
        percent: percentForStatus(step.status, progress),
        tone: STAGE_TONE[step.status] ?? 'light',
    }));
}

type Props = {
    steps: AdmissionWorkflowStep[];
    progress?: WorkflowProgressData;
    activeStatus?: number | null;
    onStageSelect?: (status: number) => void;
    homeHref?: string | null;
};

function AdmissionHomeMark() {
    return (
        <svg
            className="sis-admission-drafts-home__mark"
            viewBox="0 0 32 28"
            fill="none"
            aria-hidden="true"
        >
            <rect className="sis-admission-drafts-home__chimney" x="21.4" y="3" width="2.5" height="5.4" rx="0.35" />
            <path
                className="sis-admission-drafts-home__roof"
                d="M4 13.6 16 3.4 28 13.6"
                strokeWidth="2.45"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                className="sis-admission-drafts-home__body"
                d="M7.2 13.1h17.6V24.8H7.2z"
                strokeWidth="1.35"
                strokeLinejoin="round"
            />
            <rect className="sis-admission-drafts-home__door" x="13.7" y="18.3" width="4.6" height="6.5" rx="0.35" />
        </svg>
    );
}

/** Segmented RTL admission workflow — each stage is an actionable button. */
export function AdmissionWorkflowProgress({
    steps,
    progress,
    activeStatus = null,
    onStageSelect,
    homeHref = null,
}: Props) {
    const i18n = t();
    const displaySteps =
        steps[0]?.status === ADMISSION_REQUEST_STEP.status
            ? steps
            : [ADMISSION_REQUEST_STEP, ...steps];
    const stages = buildStages(displaySteps, progress ?? { overall_percent: 0, stages: [] });
    const overallPercent = clampPercent(progress?.overall_percent ?? 0);

    return (
        <section aria-label={i18n.admission.workflowTitle} className="sis-admission-progress flex flex-col">
            <div className="sis-admission-progress__headline">
                <h2 className="sis-ops-hub__section-title text-base">{i18n.admission.workflowTitle}</h2>
            </div>

            <ol className="sis-admission-progress__track" dir="rtl">
                {stages.map((stage) => {
                    const Icon = stage.icon;
                    const isActive = activeStatus === stage.status;

                    return (
                        <li key={stage.status} className="sis-admission-progress__item">
                            <button
                                type="button"
                                className={`sis-admission-progress__segment sis-admission-progress__segment--status-${stage.status} sis-admission-progress__segment--tone-${stage.tone}${isActive ? ' sis-admission-progress__segment--active' : ''}`}
                                aria-label={`${stage.label} ${stage.percent}%`}
                                aria-pressed={isActive}
                                aria-current={isActive ? 'true' : undefined}
                                data-active={isActive ? 'true' : undefined}
                                title={
                                    stage.status === 0
                                        ? i18n.admission.draftDialogTitle
                                        : i18n.admission.stageFilterHint
                                }
                                onClick={() => onStageSelect?.(stage.status)}
                            >
                                <span
                                    className="sis-admission-progress__fill"
                                    style={{ width: `${stage.percent}%` }}
                                    aria-hidden="true"
                                />
                                <span className="sis-admission-progress__content">
                                    <Icon className="sis-admission-progress__icon" aria-hidden="true" />
                                    <span className="sis-admission-progress__label">{stage.label}</span>
                                    <span className="sis-admission-progress__percent" dir="ltr">
                                        {stage.percent}%
                                    </span>
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>

            <div className="sis-admission-progress__overall-block">
                <div
                    className="sis-admission-progress__overall-track"
                    role="progressbar"
                    aria-label={i18n.admission.overallProgress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={overallPercent}
                >
                    <span
                        className="sis-admission-progress__overall-fill"
                        style={{ width: `${overallPercent}%` }}
                    />
                </div>
                <div className="sis-admission-progress__overall-row">
                    <p className="sis-admission-progress__overall" dir="rtl">
                        {i18n.admission.overallProgress}:{' '}
                        <span dir="ltr">{overallPercent}%</span>
                    </p>
                    {homeHref ? (
                        <Link
                            href={homeHref}
                            prefetch
                            className="sis-admission-drafts-home"
                            aria-label={i18n.admission.backToAdmission}
                        >
                            <i className="icofont-home" aria-hidden="true">
                                <AdmissionHomeMark />
                            </i>
                            <span className="sis-admission-drafts-home__caption">
                                {i18n.admission.homeCaption}
                            </span>
                        </Link>
                    ) : null}
                </div>
            </div>
        </section>
    );
}
