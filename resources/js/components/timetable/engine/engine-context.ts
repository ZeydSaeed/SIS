import type { Engine } from './engine-types';

/** What every engine sheet reads from the page (names resolved once, permissions from the server). */
export type EngineContext = {
    engine: Engine;
    yearId: number;
    lessonPeriods: Array<{ id: number; number: number; label: string }>;
    days: number[];
    dayLabel: (day: number) => string;
    sections: Array<{ id: number; label: string; classId: number }>;
    teachers: Array<{ id: number; name: string }>;
    subjects: Array<{ id: number; name: string }>;
    branches: Array<{ id: number; name: string; departments: Array<{ id: number; name: string }> }>;
    sectionLabel: (id: number | null) => string;
    teacherName: (id: number | null) => string;
    subjectName: (id: number | null) => string;
    can: { manage: boolean; generate: boolean; publish: boolean; approve: boolean };
};
