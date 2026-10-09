import { router } from '@inertiajs/react';
import { Download, EyeOff, RefreshCw, RotateCcw, Sparkles, Wand2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { RegistrySheetDialog } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import { EngineSelect, useEngineRequest } from './engine/engine-ui';

export type TestSeverity = 'critical' | 'error' | 'warning' | 'suggestion' | 'optimization';

export type TestFix = { code: string; action: string; params: Record<string, unknown>; safe: boolean; explain: Record<string, unknown> };

export type TestIssue = {
    key: string;
    severity: TestSeverity;
    category: string;
    code: string;
    source: 'input' | 'grid';
    section_id: number | null;
    teacher_id: number | null;
    subject_id: number | null;
    room_id: number | null;
    period_id: number | null;
    day: number | null;
    schedule_ids: number[];
    count: number | null;
    limit: number | null;
    detail: string | null;
    cause: string;
    blocks_generation: boolean;
    fixes: TestFix[];
    alternatives: string[];
    mark: 'ignored' | 'review' | null;
};

export type TestReport = {
    verdict: 'blocked' | 'ready_with_issues' | 'ready';
    can_generate: boolean;
    score: number;
    counts: Record<TestSeverity, number>;
    categories: Record<string, { total: number; worst: TestSeverity | null }>;
    totals: { sections: number; teachers: number; requirements: number; weekly_lessons: number; lesson_periods: number; slots_per_week: number };
    issues: TestIssue[];
    elapsed_ms: number;
    tested_at: string;
};

export type TestNames = {
    section: (id: number | null) => string;
    teacher: (id: number | null) => string;
    subject: (id: number | null) => string;
    room: (id: number | null) => string;
    day: (day: number | null) => string;
    period: (id: number | null) => string;
};

const SEVERITIES: TestSeverity[] = ['critical', 'error', 'warning', 'suggestion', 'optimization'];
/** Severity → the existing audit item / badge tone. */
const TONE: Record<TestSeverity, 'error' | 'warning' | 'info'> = { critical: 'error', error: 'error', warning: 'warning', suggestion: 'info', optimization: 'info' };

/** Fills a text template: {section} {teacher} {subject} {room} {day} {period} {count} {limit} and fix / explain params. */
export function fillTemplate(template: string, names: TestNames, values: Record<string, unknown>): string {
    return template.replace(/\{([a-z_]+)\}/g, (_, key: string) => {
        const value = values[key];
        switch (key) {
            case 'section':
            case 'section_id':
                return names.section((values.section_id as number | null) ?? null);
            case 'teacher':
                return names.teacher((values.teacher_id as number | null) ?? null);
            case 'subject':
                return names.subject((values.subject_id as number | null) ?? null);
            case 'room':
                return names.room((values.room_id as number | null) ?? null);
            case 'day':
                return names.day((values.day as number | null) ?? (values.day_of_week as number | null) ?? null);
            case 'period':
                return names.period((values.period_id as number | null) ?? null);
            default:
                return value === null || value === undefined ? '' : String(value);
        }
    });
}

/**
 * «اختبار الجدول» — before generating: every check (teachers, subjects, sections, rooms, periods, days, breaks,
 * constraints, data), each problem with its cause, the remedies the server computed («المساعد الذكي» explains why
 * each one works), alternatives, and «تجاهل» / «مراجعة لاحقاً». A remedy is applied only after confirmation, and
 * through the normal endpoints (the server validates it again).
 */
export function TestSheet({
    report,
    yearId,
    names,
    canManage,
    canGenerate,
    onFix,
    onGo,
    onGenerate,
    onClose,
}: {
    report: TestReport | null;
    yearId: number;
    names: TestNames;
    canManage: boolean;
    canGenerate: boolean;
    onFix: (fix: TestFix, issue: TestIssue) => Promise<boolean> | void;
    onGo: (issue: TestIssue) => void;
    onGenerate: () => void;
    onClose: () => void;
}) {
    const i18n = t();
    const tt = i18n.timetable;
    const x = tt.test;
    const request = useEngineRequest();
    const [severity, setSeverity] = useState<TestSeverity | 'all'>('all');
    const [category, setCategory] = useState('');
    const [showIgnored, setShowIgnored] = useState(false);
    const [loading, setLoading] = useState(report === null);
    const [pending, setPending] = useState<{ fix: TestFix; issue: TestIssue } | null>(null);

    const run = () => {
        setLoading(true);
        router.reload({ only: ['testReport'], data: { test: 1 }, onFinish: () => setLoading(false) });
    };
    useEffect(() => {
        run();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const shown = useMemo(
        () =>
            (report?.issues ?? []).filter(
                (issue) => (severity === 'all' || issue.severity === severity) && (category === '' || issue.category === category) && (showIgnored || issue.mark !== 'ignored'),
            ),
        [report, severity, category, showIgnored],
    );

    const text = (issue: TestIssue) => {
        const template = (x.codes as Record<string, string>)[issue.code] ?? (tt.findings as Record<string, string>)[issue.code] ?? (tt.issues as Record<string, string>)[issue.code] ?? issue.code;

        return fillTemplate(template, names, issue as unknown as Record<string, unknown>);
    };
    const causeText = (issue: TestIssue) => (x.causes as Record<string, string>)[issue.cause] ?? '';
    const fixText = (fix: TestFix) => fillTemplate((x.fixes as Record<string, string>)[fix.code] ?? fix.code, names, { ...fix.explain, ...fix.params });
    const fixWhy = (fix: TestFix) => {
        const why = (x.why as Record<string, string>)[fix.code];

        return why === undefined ? '' : fillTemplate(why, names, { ...fix.explain, ...fix.params });
    };

    const mark = async (issue: TestIssue, value: 1 | 2 | null) => {
        await request('post', '/timetable/test/marks', { academic_year_id: yearId, issue_key: issue.key, mark: value });
        run();
    };

    const exportCsv = () => {
        if (report === null) {
            return;
        }
        const rows = [[x.csvSeverity, x.csvCategory, x.csvProblem, x.csvCause, x.csvFix], ...report.issues.map((issue) => [
            (x.severities as Record<string, string>)[issue.severity],
            (x.categories as Record<string, string>)[issue.category],
            text(issue),
            causeText(issue),
            issue.fixes.map(fixText).join(' | '),
        ])];
        const csv = '﻿' + rows.map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\r\n');
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        link.download = `timetable-test-${yearId}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    };

    const categoryOptions = [{ value: '', label: x.allCategories }, ...Object.entries(x.categories as Record<string, string>).map(([value, label]) => ({ value, label: `${label} (${report?.categories[value]?.total ?? 0})` }))];

    return (
        <RegistrySheetDialog title={x.title} className="sis-branches-sheet sis-timetable-sheet sis-timetable-audit-sheet sis-timetable-engine-sheet sis-timetable-engine-sheet--readiness" onClose={onClose}>
            <div className="sis-timetable-audit__bar">
                {report === null ? (
                    <span className="sis-timetable-badge sis-timetable-badge--info">{loading ? x.running : x.notRun}</span>
                ) : (
                    <>
                        <span className={`sis-timetable-badge sis-timetable-badge--${report.verdict === 'blocked' ? 'error' : report.verdict === 'ready' ? 'info' : 'warning'}`}>{(x.verdicts as Record<string, string>)[report.verdict]}</span>
                        <span className="sis-timetable-badge sis-timetable-badge--info">
                            {x.score}: <bdi dir="ltr">{report.score}%</bdi>
                        </span>
                        {SEVERITIES.map((s) => (
                            <button
                                key={s}
                                type="button"
                                className={`sis-timetable-badge sis-timetable-badge--${TONE[s]}`}
                                aria-pressed={severity === s}
                                onClick={() => setSeverity(severity === s ? 'all' : s)}
                            >
                                {(x.severities as Record<string, string>)[s]}: <bdi dir="ltr">{report.counts[s]}</bdi>
                            </button>
                        ))}
                    </>
                )}
            </div>
            <div className="sis-timetable-audit__bar">
                <span className="sis-timetable-audit__filter">
                    <EngineSelect value={category} label={x.category} onChange={setCategory} options={categoryOptions} />
                </span>
                <label className="sis-timetable-audit__filter">
                    <input type="checkbox" checked={showIgnored} onChange={(e) => setShowIgnored(e.target.checked)} />
                    {x.showIgnored}
                </label>
                {report !== null ? (
                    <span className="sis-timetable-toolbar__meta">
                        {x.tested.replace('{ms}', String(report.elapsed_ms)).replace('{lessons}', String(report.totals.weekly_lessons)).replace('{slots}', String(report.totals.slots_per_week))}
                    </span>
                ) : null}
            </div>
            <div className="sis-timetable-audit__list">
                {report !== null ? (
                    <SheetSection id="timetable-test-checks" title={x.checks}>
                        <ul className="sis-timetable-audit__items sis-branches-field--wide">
                            {Object.entries(report.categories).map(([key, value]) => (
                                <li key={key} className={`sis-timetable-audit__item${value.worst !== null ? ` sis-timetable-audit__item--${TONE[value.worst]}` : ''}`}>
                                    <span className="sis-timetable-audit__text">
                                        {(x.categories as Record<string, string>)[key]} — {value.total === 0 ? x.passed : `${value.total} · ${(x.severities as Record<string, string>)[value.worst ?? 'suggestion']}`}
                                    </span>
                                    {value.total > 0 ? (
                                        <Button type="button" size="sm" variant="outline" onClick={() => setCategory(key)}>
                                            {x.show}
                                        </Button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    </SheetSection>
                ) : null}
                {report !== null && shown.length === 0 ? <p className="sis-timetable-audit__clean">{x.clean}</p> : null}
                {SEVERITIES.map((s) => {
                    const group = shown.filter((issue) => issue.severity === s);

                    return group.length === 0 ? null : (
                        <SheetSection key={s} id={`timetable-test-${s}`} title={`${(x.severities as Record<string, string>)[s]} (${group.length})`}>
                            <ul className="sis-timetable-audit__items sis-branches-field--wide">
                                {group.map((issue) => (
                                    <li key={issue.key} className={`sis-timetable-audit__item sis-timetable-audit__item--${TONE[s]}`}>
                                        <span className="sis-timetable-audit__text">
                                            <strong>{(x.categories as Record<string, string>)[issue.category]}</strong> · {text(issue)}
                                            {issue.mark !== null ? ` · ${(x.marks as Record<string, string>)[issue.mark]}` : ''}
                                            {causeText(issue) !== '' ? (
                                                <>
                                                    <br />
                                                    <small>
                                                        {x.cause}: {causeText(issue)}
                                                    </small>
                                                </>
                                            ) : null}
                                            {issue.fixes.map((fix, index) => (
                                                <span key={index}>
                                                    <br />
                                                    <small>
                                                        <Sparkles aria-hidden width={12} height={12} /> {fixText(fix)}
                                                        {fixWhy(fix) !== '' ? ` — ${x.because} ${fixWhy(fix)}` : ''}
                                                    </small>
                                                </span>
                                            ))}
                                            {issue.alternatives.length > 0 ? (
                                                <>
                                                    <br />
                                                    <small>
                                                        {x.alternatives}: {issue.alternatives.map((a) => (x.alternativeNames as Record<string, string>)[a] ?? a).join('، ')}
                                                    </small>
                                                </>
                                            ) : null}
                                        </span>
                                        <span>
                                            {issue.fixes.slice(0, 3).map((fix, index) => (
                                                <Button key={index} type="button" size="sm" variant={index === 0 ? 'default' : 'outline'} title={fixWhy(fix)} onClick={() => setPending({ fix, issue })}>
                                                    {(x.fixButtons as Record<string, string>)[fix.action] ?? x.apply}
                                                </Button>
                                            ))}{' '}
                                            {issue.section_id !== null || issue.teacher_id !== null || issue.schedule_ids.length > 0 ? (
                                                <Button type="button" size="sm" variant="outline" onClick={() => onGo(issue)}>
                                                    {tt.auditGoTo}
                                                </Button>
                                            ) : null}{' '}
                                            {canManage ? (
                                                issue.mark === null ? (
                                                    <>
                                                        <Button type="button" size="sm" variant="outline" title={x.ignoreHint} onClick={() => void mark(issue, 1)}>
                                                            <EyeOff aria-hidden />
                                                            {x.ignore}
                                                        </Button>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => void mark(issue, 2)}>
                                                            {x.review}
                                                        </Button>
                                                    </>
                                                ) : (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => void mark(issue, null)}>
                                                        <RotateCcw aria-hidden />
                                                        {x.unmark}
                                                    </Button>
                                                )
                                            ) : null}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </SheetSection>
                    );
                })}
            </div>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" onClick={onClose}>
                    {tt.close}
                </Button>
                <Button type="button" variant="outline" disabled={loading} onClick={run}>
                    <RefreshCw aria-hidden />
                    {x.rerun}
                </Button>
                <Button type="button" variant="outline" disabled={report === null} onClick={exportCsv}>
                    <Download aria-hidden />
                    {x.export}
                </Button>
                {canGenerate ? (
                    <Button type="button" disabled={report === null || !report.can_generate} title={report?.can_generate === false ? x.blockedHint : undefined} onClick={onGenerate}>
                        <Wand2 aria-hidden />
                        {x.generate}
                    </Button>
                ) : null}
            </div>
            <ConfirmDialog
                open={pending !== null}
                title={x.confirmTitle}
                description={pending === null ? '' : `${fixText(pending.fix)}${fixWhy(pending.fix) !== '' ? ` — ${x.because} ${fixWhy(pending.fix)}` : ''}${pending.fix.safe ? '' : ` ${x.unsafeNote}`}`}
                confirmLabel={x.apply}
                tone={pending?.fix.safe ? undefined : 'danger'}
                onConfirm={() => {
                    const current = pending;
                    setPending(null);
                    if (current !== null) {
                        void Promise.resolve(onFix(current.fix, current.issue)).then((ok) => {
                            if (ok !== false && ['patch_schedule', 'cancel_schedule', 'reshape_day', 'update_settings', 'auto_place', 'apply_abbreviations'].includes(current.fix.action)) {
                                run();
                            }
                        });
                    }
                }}
                onOpenChange={(open) => {
                    if (!open) {
                        setPending(null);
                    }
                }}
            />
        </RegistrySheetDialog>
    );
}
