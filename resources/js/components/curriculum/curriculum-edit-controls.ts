import type { LucideIcon } from 'lucide-react';
import {
    BookOpen,
    CheckCircle2,
    CircleSlash,
    History,
    Layers,
    Users,
    Wrench,
} from 'lucide-react';

/** Edit-ribbon filter for curriculum tables (status / type / superseded). */
export type CurriculumEditFilter =
    | { kind: 'all' }
    | { kind: 'status'; value: 1 | 2 }
    | { kind: 'type'; value: 1 | 2 | 3 }
    | { kind: 'superseded' };

export type CurriculumStatusTab = {
    id: string;
    filter: CurriculumEditFilter;
    icon: LucideIcon;
    labelKey:
        | 'filterAll'
        | 'filterActive'
        | 'filterInactive'
        | 'subjectTypeCore'
        | 'subjectTypeSpecialization'
        | 'subjectTypePractical';
};

export type CurriculumDistributionAction = {
    id: string;
    icon: LucideIcon;
    /** Apply subject status (1 active / 2 inactive) or subject_type (1/2/3). */
    apply:
        | { kind: 'status'; value: 1 | 2 }
        | { kind: 'type'; value: 1 | 2 | 3 };
    labelKey:
        | 'filterActive'
        | 'filterInactive'
        | 'subjectTypeCore'
        | 'subjectTypeSpecialization'
        | 'subjectTypePractical';
};

export const CURRICULUM_STATUS_TABS: CurriculumStatusTab[] = [
    { id: 'status-all', filter: { kind: 'all' }, icon: Users, labelKey: 'filterAll' },
    {
        id: 'status-active',
        filter: { kind: 'status', value: 1 },
        icon: CheckCircle2,
        labelKey: 'filterActive',
    },
    {
        id: 'status-inactive',
        filter: { kind: 'status', value: 2 },
        icon: CircleSlash,
        labelKey: 'filterInactive',
    },
    {
        id: 'status-core',
        filter: { kind: 'type', value: 1 },
        icon: BookOpen,
        labelKey: 'subjectTypeCore',
    },
    {
        id: 'status-specialization',
        filter: { kind: 'type', value: 2 },
        icon: Layers,
        labelKey: 'subjectTypeSpecialization',
    },
    {
        id: 'status-practical',
        filter: { kind: 'type', value: 3 },
        icon: Wrench,
        labelKey: 'subjectTypePractical',
    },
];

export const CURRICULUM_DISTRIBUTION_ACTIONS: CurriculumDistributionAction[] = [
    {
        id: 'dist-active',
        icon: CheckCircle2,
        apply: { kind: 'status', value: 1 },
        labelKey: 'filterActive',
    },
    {
        id: 'dist-inactive',
        icon: CircleSlash,
        apply: { kind: 'status', value: 2 },
        labelKey: 'filterInactive',
    },
    {
        id: 'dist-core',
        icon: BookOpen,
        apply: { kind: 'type', value: 1 },
        labelKey: 'subjectTypeCore',
    },
    {
        id: 'dist-specialization',
        icon: Layers,
        apply: { kind: 'type', value: 2 },
        labelKey: 'subjectTypeSpecialization',
    },
    {
        id: 'dist-practical',
        icon: Wrench,
        apply: { kind: 'type', value: 3 },
        labelKey: 'subjectTypePractical',
    },
];

export const CURRICULUM_SUPERSEDED_ICON = History;

export function editFilterEquals(
    a: CurriculumEditFilter,
    b: CurriculumEditFilter,
): boolean {
    if (a.kind !== b.kind) {
        return false;
    }
    if (a.kind === 'all' || a.kind === 'superseded') {
        return true;
    }
    if (b.kind === 'all' || b.kind === 'superseded') {
        return false;
    }

    return a.value === b.value;
}

export function matchesCurriculumEditFilter(
    row: { status: number; subject_type: number },
    filter: CurriculumEditFilter,
): boolean {
    switch (filter.kind) {
        case 'all':
            return true;
        case 'status':
            return Number(row.status) === filter.value;
        case 'type':
            return Number(row.subject_type) === filter.value;
        case 'superseded':
            return Number(row.status) !== 1;
        default:
            return true;
    }
}
