<?php

namespace App\Domain\Timetable\Support;

/**
 * «تنسيق الجدول» of a school-year (timetable.configs.display): how a lesson cell reads and prints. Unknown keys are
 * dropped and out-of-range values fall back to the default, so a stored setting can never break the grid. Fonts
 * and colours come from the approved lists only (Segoe UI / Tahoma / Calibri / Aptos; Oxford / Night / Steel).
 *
 * layout  corner   subject large, teacher small and muted in the lower corner
 *         columns  subject and teacher side by side
 *         subject  the subject only
 *         full     teacher + subject + room (+ the other chosen fields)
 */
final readonly class TimetableDisplaySettings
{
    public const LAYOUTS = ['corner', 'columns', 'subject', 'full'];

    public const FIELDS = ['subject', 'teacher', 'room', 'class', 'section', 'students', 'group', 'branch', 'department', 'subject_type'];

    public const ABBREVIATE = ['subject', 'teacher', 'room', 'section', 'class'];

    public const FONTS = ['segoe', 'tahoma', 'calibri', 'aptos'];

    public const TEXT_COLORS = ['oxford', 'night', 'steel'];

    public const COLOR_BY = ['subject', 'teacher', 'section', 'room', 'none'];

    private const DEFAULTS = [
        'layout' => 'corner',
        'fields' => ['subject' => true, 'teacher' => true, 'room' => false, 'class' => false, 'section' => false, 'students' => false,
            'group' => true, 'branch' => false, 'department' => false, 'subject_type' => true],
        'abbreviate' => ['subject' => false, 'teacher' => false, 'room' => true, 'section' => false, 'class' => false],
        'teacher_title' => false,
        'teacher_title_style' => 'abbreviation',
        'align_v' => 'middle',
        'align_h' => 'start',
        'font_family' => 'segoe',
        'font_scale' => 100,
        'subject_weight' => 700,
        'secondary_muted' => true,
        'italic' => false,
        'text_color' => 'oxford',
        'cell_height' => 32,
        'column_width' => 0,
        'color_by' => 'subject',
        'period_header' => ['show_number' => true, 'number_bold' => true, 'show_time' => true, 'time_muted' => true, 'clock' => '12'],
        'heading' => ['effective_from' => null],
    ];

    /** @param  array<string, mixed>  $values */
    private function __construct(private array $values) {}

    public static function defaults(): self
    {
        return new self(self::DEFAULTS);
    }

    /** @param  array<string, mixed>|null  $input  stored or submitted values (partial allowed) */
    public static function from(?array $input): self
    {
        $input ??= [];
        $d = self::DEFAULTS;
        $flags = static fn (array $defaults, mixed $given): array => array_map(
            static fn (bool $default, string $key): bool => is_array($given) && array_key_exists($key, $given) ? (bool) $given[$key] : $default,
            $defaults,
            array_keys($defaults),
        );
        $pick = static fn (string $key, array $allowed) => in_array($input[$key] ?? null, $allowed, true) ? $input[$key] : $d[$key];
        $int = static fn (string $key, int $min, int $max): int => is_numeric($input[$key] ?? null) && (int) $input[$key] >= $min && (int) $input[$key] <= $max ? (int) $input[$key] : $d[$key];
        $header = is_array($input['period_header'] ?? null) ? $input['period_header'] : [];
        $heading = is_array($input['heading'] ?? null) ? $input['heading'] : [];
        $effective = is_string($heading['effective_from'] ?? null) ? $heading['effective_from'] : '';
        $effectiveOk = preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective) === 1
            && checkdate((int) substr($effective, 5, 2), (int) substr($effective, 8, 2), (int) substr($effective, 0, 4));

        return new self([
            'layout' => $pick('layout', self::LAYOUTS),
            'fields' => array_combine(array_keys($d['fields']), $flags($d['fields'], $input['fields'] ?? null)),
            'abbreviate' => array_combine(array_keys($d['abbreviate']), $flags($d['abbreviate'], $input['abbreviate'] ?? null)),
            'teacher_title' => (bool) ($input['teacher_title'] ?? $d['teacher_title']),
            // the academic title before the teacher's name: its abbreviation or the full title
            'teacher_title_style' => $pick('teacher_title_style', ['abbreviation', 'full']),
            'align_v' => $pick('align_v', ['top', 'middle', 'bottom']),
            'align_h' => $pick('align_h', ['start', 'center', 'end']),
            'font_family' => $pick('font_family', self::FONTS),
            'font_scale' => $int('font_scale', 70, 150),
            'subject_weight' => in_array((int) ($input['subject_weight'] ?? 0), [400, 600, 700], true) ? (int) $input['subject_weight'] : $d['subject_weight'],
            'secondary_muted' => (bool) ($input['secondary_muted'] ?? $d['secondary_muted']),
            'italic' => (bool) ($input['italic'] ?? $d['italic']),
            'text_color' => $pick('text_color', self::TEXT_COLORS),
            'cell_height' => $int('cell_height', 24, 96),
            // 0 = automatic width.
            'column_width' => is_numeric($input['column_width'] ?? null) && ((int) $input['column_width'] === 0 || ((int) $input['column_width'] >= 40 && (int) $input['column_width'] <= 200)) ? (int) $input['column_width'] : $d['column_width'],
            'color_by' => $pick('color_by', self::COLOR_BY),
            'period_header' => [
                'show_number' => (bool) ($header['show_number'] ?? true),
                'number_bold' => (bool) ($header['number_bold'] ?? true),
                'show_time' => (bool) ($header['show_time'] ?? true),
                'time_muted' => (bool) ($header['time_muted'] ?? true),
                'clock' => in_array($header['clock'] ?? null, ['12', '24'], true) ? $header['clock'] : '12',
            ],
            'heading' => ['effective_from' => $effectiveOk ? $effective : null],
        ]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }
}
