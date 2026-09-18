import type { LucideIcon } from 'lucide-react';
import {
    CheckCircle2,
    ClipboardList,
    Clock3,
    FilePenLine,
    GraduationCap,
    MessagesSquare,
    Send,
} from 'lucide-react';
import { t } from '@/i18n';

export type AdmissionWorkflowStep = { status: number; key: string };

type StageVisual = {
    status: number;
    label: string;
    icon: LucideIcon;
    percent: number;
    tone: 'light' | 'dark';
};

const STAGE_ICONS: Record<number, LucideIcon> = {
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
    1: 'dark',
    2: 'light',
    3: 'light',
    4: 'dark',
    5: 'light',
    6: 'dark',
    9: 'dark',
};

function pipelineIndex(status: number, steps: AdmissionWorkflowStep[]): number {
    return steps.findIndex((step) => step.status === status);
}

function labelForStatus(status: number): string {
    const i18n = t().admission;
    const map: Record<number, string> = {
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

function buildStages(
    steps: AdmissionWorkflowStep[],
    applicationStatuses: number[],
): { stages: StageVisual[]; overallPercent: number } {
    const pipelineStatuses = applicationStatuses
        .map((status) => pipelineIndex(status, steps))
        .filter((index) => index >= 0);

    const furthest =
        pipelineStatuses.length === 0 ? 0 : Math.max(...pipelineStatuses);

    const stages: StageVisual[] = steps.map((step, index) => {
        const atCount = pipelineStatuses.filter((i) => i === index).length;
        const reachedCount = pipelineStatuses.filter((i) => i >= index).length;
        const passedCount = pipelineStatuses.filter((i) => i > index).length;

        let percent = 0;
        if (pipelineStatuses.length === 0) {
            percent = index === 0 ? 0 : 0;
        } else if (index < furthest) {
            percent = 100;
        } else if (index === furthest) {
            percent =
                reachedCount === 0
                    ? 0
                    : Math.round((passedCount / reachedCount) * 100);
            if (atCount > 0 && percent < 15) {
                percent = Math.max(percent, 35);
            }
            if (atCount > 0 && passedCount === 0) {
                percent = Math.max(25, Math.min(60, 20 + atCount * 10));
            }
            if (atCount === 0 && passedCount > 0) {
                percent = 100;
            }
        }

        return {
            status: step.status,
            label: labelForStatus(step.status),
            icon: STAGE_ICONS[step.status] ?? FilePenLine,
            percent,
            tone: STAGE_TONE[step.status] ?? 'light',
        };
    });

    const overallPercent =
        pipelineStatuses.length === 0
            ? 0
            : Math.round(
                  (pipelineStatuses.reduce((sum, index) => sum + (index + 1), 0) /
                      (pipelineStatuses.length * steps.length)) *
                      100,
              );

    return { stages, overallPercent };
}

type Props = {
    steps: AdmissionWorkflowStep[];
    applicationStatuses: number[];
};

/** Segmented RTL admission workflow progress with fixed per-stage palette. */
export function AdmissionWorkflowProgress({ steps, applicationStatuses }: Props) {
    const i18n = t();
    const { stages, overallPercent } = buildStages(steps, applicationStatuses);

    return (
        <section aria-label={i18n.admission.workflowTitle} className="sis-admission-progress flex flex-col gap-3">
            <h2 className="sis-ops-hub__section-title text-base">{i18n.admission.workflowTitle}</h2>

            <ol className="sis-admission-progress__track" dir="rtl">
                {stages.map((stage) => {
                    const Icon = stage.icon;

                    return (
                        <li
                            key={stage.status}
                            className={`sis-admission-progress__segment sis-admission-progress__segment--status-${stage.status} sis-admission-progress__segment--tone-${stage.tone}`}
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
                        </li>
                    );
                })}
            </ol>

            <p className="sis-admission-progress__overall" dir="rtl">
                {i18n.admission.overallProgress}:{' '}
                <span dir="ltr">{overallPercent}%</span>
            </p>
        </section>
    );
}
