/** Timetable engine props (server: TimetablePageController::engineProps) — read-only shapes for the sheets. */

export type EngineSettings = {
    working_days: number[];
    cycle_weeks: number;
    max_teacher_per_day: number;
    max_subject_per_day: number;
    double_changeover_minutes: number;
    weights: Record<string, number>;
};

export type EngineActivity = {
    id: number;
    subject_id: number;
    activity_type: number;
    weekly: number;
    block: number;
    distribution: number[] | null;
    distribution_text: string | null;
    room_id: number | null;
    room_type: number | null;
    workshop_id: number | null;
    week_pattern: number;
    term_id: number | null;
    note: string | null;
    targets: Array<{ section_id: number; group_id: number | null }>;
    teachers: Array<{ teacher_id: number; role: number; sessions: number | null }>;
};

export type EngineGroup = { id: number; division_id: number; section_id: number; name: string; student_count: number | null; division_name: string; members: number };

export type EngineAvailability = {
    id: number;
    teacher_id: number | null;
    room_id: number | null;
    section_id: number | null;
    workshop_id: number | null;
    day: number;
    period_id: number;
    week_no: number | null;
    kind: number;
};

export type EngineRule = {
    id: number;
    rule_type: string;
    priority: number;
    scope: Record<string, number | null>;
    params: Record<string, number | number[]>;
    reason: string | null;
    created_at: string;
};

export type EngineCatalogueEntry = { type: string; kind: string; scopes: string[]; params: Record<string, string> };

export type EngineRoom = { id: number; code: string; name: string; capacity: number | null; room_type: number | null };

export type EngineWorkshop = { id: number; code: string; name: string; capacity: number; safety_capacity: number; room_id: number | null };

export type EngineRun = {
    id: number;
    mode: number;
    status: number;
    is_what_if: boolean;
    scope: Record<string, unknown>;
    options: Record<string, unknown>;
    progress: { placed: number; total: number; hard_violations: number; soft_penalty: number } | null;
    quality: { overall: number | null; grade: string; feasible: boolean; metrics: Record<string, number | null> } | null;
    hard_violations: number | null;
    soft_penalty: number | null;
    activities_total: number | null;
    placed: number | null;
    unplaced: number | null;
    error: string | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string;
};

export type EngineVersion = {
    id: number;
    version_no: number;
    name: string;
    reason: string | null;
    status: number;
    entries_count: number;
    quality: { overall: number | null; errors: number; grade: string } | null;
    approval_request_id: number | null;
    effective_from: string | null;
    effective_to: string | null;
    published_at: string | null;
    created_at: string;
    stale: boolean;
};

export type Engine = {
    settings: EngineSettings;
    activities: EngineActivity[];
    groups: EngineGroup[];
    availability: EngineAvailability[];
    rules: EngineRule[];
    catalogue: EngineCatalogueEntry[];
    rooms: EngineRoom[];
    workshops: EngineWorkshop[];
    runs: EngineRun[];
    versions: EngineVersion[];
    status: { published_version_id: number | null; effective_version_id: number | null; stale: boolean; fingerprint: string };
};

export type RunDetail = EngineRun & {
    result: {
        unplaced: Array<{ card_id: string; activity_id: number | null; subject_id: number; teacher_id: number; section_ids: number[]; length: number; reasons: Record<string, number>; suggestions: Array<{ reason: string; day?: number; lesson?: number }> }>;
        violations: Array<{ rule_type: string; rule_id: number | null; source: string; hard: boolean; count: number; priority: number }>;
        compile_notes: Array<{ card_id: string; reason: string }>;
        stats: { iterations: number; elapsed_ms: number; stopped: boolean; cards: number; fixed: number; rows: number; replaced: number };
        diff: { counts: Record<string, number>; sections: number[] } | null;
    } | null;
};

export type Comparison = { counts: Record<string, number>; sections: number[]; quality: { a: number | null; b: number | null }; labels: { a: number | null; b: number | null } };

export type MoveSuggestions = { options: Array<{ kind: 'move' | 'swap'; day: number; period_id: number; with_schedule_id: number | null; hard: number; soft: number }>; reason: string | null };

export type Substitutes = {
    candidates: Array<{ teacher_id: number; name: string; compatibility: number; qualified: boolean; knows_section: boolean; lessons_that_day: number; over_limit: boolean }>;
    reason: string | null;
};

/** Run statuses (GenerationRunStatus) and version statuses (TimetableVersionStatus). */
export const RUN_ACTIVE = [1, 2];
export const RUN_SUCCEEDED = 3;
export const VERSION = { draft: 1, review: 2, approved: 3, rejected: 4, published: 5, superseded: 6, archived: 7 } as const;
