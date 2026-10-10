import type { CSSProperties } from 'react';

/**
 * «تنسيق الجدول» (server: TimetableDisplaySettings, stored per school-year) and the display catalogue
 * (names, abbreviations and colours read from the owning pages). Pure helpers — the grid and the print share them.
 */
export type CellLayout = 'corner' | 'columns' | 'subject' | 'full';
export type CellField = 'subject' | 'teacher' | 'room' | 'class' | 'section' | 'students' | 'group' | 'branch' | 'department' | 'subject_type';
export type AbbreviateField = 'subject' | 'teacher' | 'room' | 'section' | 'class';
export type ColorBy = 'subject' | 'teacher' | 'section' | 'room' | 'none';

export type DisplaySettings = {
    layout: CellLayout;
    fields: Record<CellField, boolean>;
    abbreviate: Record<AbbreviateField, boolean>;
    teacher_title: boolean;
    /** The academic title before the teacher's name: its abbreviation or the full title. */
    teacher_title_style: 'abbreviation' | 'full';
    align_v: 'top' | 'middle' | 'bottom';
    align_h: 'start' | 'center' | 'end';
    font_family: 'segoe' | 'tahoma' | 'calibri' | 'aptos';
    font_scale: number;
    subject_weight: 400 | 600 | 700;
    secondary_muted: boolean;
    italic: boolean;
    text_color: 'oxford' | 'night' | 'steel';
    cell_height: number;
    column_width: number;
    color_by: ColorBy;
    period_header: { show_number: boolean; number_bold: boolean; show_time: boolean; time_muted: boolean; clock: '12' | '24' };
    /** «ترويسة الجدول»: «يعمل بموجبه اعتباراً من» (Y-m-d; null = the effective version's date). */
    heading: { effective_from: string | null };
};

export type DisplayRow = {
    id: number;
    name: string;
    abbreviation: string | null;
    color_hue: number | null;
    suggested: string | null;
    short: string;
    // Kind-specific extras.
    title?: string | null;
    title_abbreviation?: string | null;
    capacity?: number | null;
    code?: string;
    room_type_id?: number | null;
    supports_practical?: boolean;
    subject_type?: number;
    kind?: number;
};

export type DisplayKind = 'subjects' | 'teachers' | 'branches' | 'departments' | 'classes' | 'sections' | 'rooms' | 'room_types';

export type DisplayCatalog = Partial<Record<DisplayKind, Record<string, DisplayRow>>> & { settings?: DisplaySettings };

export const DEFAULT_DISPLAY: DisplaySettings = {
    layout: 'corner',
    fields: { subject: true, teacher: true, room: false, class: false, section: false, students: false, group: true, branch: false, department: false, subject_type: true },
    abbreviate: { subject: false, teacher: false, room: true, section: false, class: false },
    teacher_title: false,
    teacher_title_style: 'abbreviation',
    align_v: 'middle',
    align_h: 'start',
    font_family: 'segoe',
    font_scale: 100,
    subject_weight: 700,
    secondary_muted: true,
    italic: false,
    text_color: 'oxford',
    cell_height: 32,
    column_width: 0,
    color_by: 'subject',
    period_header: { show_number: true, number_bold: true, show_time: true, time_muted: true, clock: '12' },
    heading: { effective_from: null },
};

/** The approved font stacks (COLOR-TYPOGRAPHY-GOVERNANCE: Segoe UI, Tahoma, Calibri, Aptos only). */
export const FONT_STACKS: Record<DisplaySettings['font_family'], string> = {
    segoe: "'Segoe UI', Tahoma, Calibri, Aptos, sans-serif",
    tahoma: "Tahoma, 'Segoe UI', Calibri, Aptos, sans-serif",
    calibri: "Calibri, 'Segoe UI', Tahoma, Aptos, sans-serif",
    aptos: "Aptos, 'Segoe UI', Tahoma, Calibri, sans-serif",
};

export const TEXT_COLORS: Record<DisplaySettings['text_color'], string> = {
    oxford: 'var(--sis-oxford)',
    night: 'var(--sis-night)',
    steel: 'var(--sis-steel)',
};

/** Settings merged over the defaults (a partial or older stored object never breaks the grid). */
export function resolveDisplay(stored: Partial<DisplaySettings> | null | undefined): DisplaySettings {
    if (!stored) {
        return DEFAULT_DISPLAY;
    }

    return {
        ...DEFAULT_DISPLAY,
        ...stored,
        fields: { ...DEFAULT_DISPLAY.fields, ...(stored.fields ?? {}) },
        abbreviate: { ...DEFAULT_DISPLAY.abbreviate, ...(stored.abbreviate ?? {}) },
        period_header: { ...DEFAULT_DISPLAY.period_header, ...(stored.period_header ?? {}) },
        heading: { ...DEFAULT_DISPLAY.heading, ...(stored.heading ?? {}) },
    };
}

/** CSS variables of the grid root (layout classes read them; values stay inside the governed palette). */
export function gridStyle(settings: DisplaySettings, zoom = 1): CSSProperties {
    return {
        '--tt-font': FONT_STACKS[settings.font_family],
        '--tt-font-scale': (settings.font_scale / 100) * zoom,
        '--tt-cell-height': `${(settings.cell_height / 10) * zoom}rem`,
        '--tt-col-width': settings.column_width === 0 ? 'auto' : `${(settings.column_width / 10) * zoom}rem`,
        '--tt-zoom': zoom,
        '--tt-subject-weight': settings.subject_weight,
        '--tt-text': TEXT_COLORS[settings.text_color],
    } as CSSProperties;
}

/**
 * Data attributes of the grid root. A formatting rule applies only when its setting differs from the default, so the
 * default grid (and its compact variant) renders exactly as before.
 */
export function gridData(settings: DisplaySettings, zoom = 1): Record<string, string> {
    const data: Record<string, string> = { 'data-tt-layout': settings.layout };
    const flag = (name: string, on: boolean, value = 'yes') => {
        if (on) {
            data[name] = value;
        }
    };
    flag('data-tt-align-v', settings.align_v !== 'middle', settings.align_v);
    flag('data-tt-align-h', settings.align_h !== 'start', settings.align_h);
    flag('data-tt-plain', !settings.secondary_muted);
    flag('data-tt-italic', settings.italic);
    flag('data-tt-font', settings.font_family !== 'segoe');
    flag('data-tt-scaled', settings.font_scale !== 100 || zoom !== 1);
    flag('data-tt-height', settings.cell_height !== DEFAULT_DISPLAY.cell_height || zoom !== 1);
    flag('data-tt-width', settings.column_width !== 0);
    // «تكبير / تصغير الجدول»: the table itself grows (columns included) inside its scrolling frame.
    flag('data-tt-zoomed', zoom !== 1);
    flag('data-tt-weight', settings.subject_weight !== 700);
    flag('data-tt-color', settings.text_color !== 'oxford');
    flag('data-tt-header-regular', !settings.period_header.number_bold);
    flag('data-tt-header-plain', !settings.period_header.time_muted);

    return data;
}

/** "13:15" in the chosen clock: 12-hour (ص / م) or 24-hour. */
export function formatClock(time: string, clock: '12' | '24', am: string, pm: string): string {
    const [h = 0, m = 0] = time.split(':').map(Number);
    if (clock === '24') {
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    }

    return `${h % 12 === 0 ? 12 : h % 12}:${String(m).padStart(2, '0')} ${h < 12 ? am : pm}`;
}

/** Lookup in the catalogue (ids arrive as object keys). */
export function displayRow(catalog: DisplayCatalog | null | undefined, kind: DisplayKind, id: number | null | undefined): DisplayRow | null {
    if (catalog === null || catalog === undefined || id === null || id === undefined) {
        return null;
    }

    return catalog[kind]?.[String(id)] ?? null;
}

/** The label of an entity in a cell: its abbreviation when the setting asks for it, else its name. */
export function entityLabel(catalog: DisplayCatalog | null | undefined, kind: DisplayKind, id: number | null | undefined, abbreviate: boolean, fallback: string): string {
    const row = displayRow(catalog, kind, id);
    if (row === null) {
        return fallback;
    }

    return abbreviate ? row.short : row.name;
}

/** The colour (hue) a lesson takes when the owner set one for the chosen «لوّن حسب». */
export function ownerHue(catalog: DisplayCatalog | null | undefined, colorBy: ColorBy, lesson: { subject_id: number; teacher_id: number; section_id: number; room_id: number | null }): number | null {
    switch (colorBy) {
        case 'subject':
            return displayRow(catalog, 'subjects', lesson.subject_id)?.color_hue ?? null;
        case 'teacher':
            return displayRow(catalog, 'teachers', lesson.teacher_id)?.color_hue ?? null;
        case 'section':
            return displayRow(catalog, 'sections', lesson.section_id)?.color_hue ?? null;
        case 'room':
            return lesson.room_id === null ? null : (displayRow(catalog, 'rooms', lesson.room_id)?.color_hue ?? null);
        default:
            return null;
    }
}
