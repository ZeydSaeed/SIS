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
};

/** Segmented RTL admission workflow — each stage is an actionable button. */
export function AdmissionWorkflowProgress({
    steps,
    progress,
    activeStatus = null,
    onStageSelect,
}: Props) {
    const i18n = t();
    const displaySteps =
        steps[0]?.status === ADMISSION_REQUEST_STEP.status
            ? steps
            : [ADMISSION_REQUEST_STEP, ...steps];
    const stages = buildStages(displaySteps, progress ?? { overall_percent: 0, stages: [] });
    const overallPercent = clampPercent(progress?.overall_percent ?? 0);

    return (
        <section aria-labelledby="sis-admission-workflow-title" className="sis-admission-progress flex flex-col">
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
                    data-contrast={overallPercent >= 45 ? 'light' : 'dark'}
                >
                    <span
                        className="sis-admission-progress__overall-fill"
                        style={{ width: `${overallPercent}%` }}
                    />
                    <span className="sis-admission-progress__overall-value" dir="ltr">
                        {overallPercent}%
                    </span>
                </div>
            </div>
        </section>
    );
}
