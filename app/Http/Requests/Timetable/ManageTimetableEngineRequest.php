<?php

namespace App\Http\Requests\Timetable;

/**
 * Engine configuration writes (settings, activities, groups, availability, rules). The fields per action are
 * declared by {@see self::fieldRules()} from the route name, so each action validates exactly what it uses.
 */
class ManageTimetableEngineRequest extends TimetableEngineRequest
{
    protected function ability(): string
    {
        return 'manageConstraints';
    }

    protected function fieldRules(): array
    {
        $year = ['academic_year_id' => $this->yearRule()];

        return match ($this->route()?->getName()) {
            'timetable.settings.save' => $year + [
                'working_days' => ['required', 'array', 'min:1', 'max:7'],
                'working_days.*' => ['integer', 'between:1,7', 'distinct'],
                'cycle_weeks' => ['required', 'integer', 'between:1,4'],
                'max_teacher_per_day' => ['required', 'integer', 'between:1,20'],
                'max_subject_per_day' => ['required', 'integer', 'between:1,20'],
                'double_changeover_minutes' => ['required', 'integer', 'between:0,60'],
                'weights' => ['nullable', 'array'],
                'weights.*' => ['integer', 'between:1,1000000'],
            ],
            'timetable.activities.store' => $year + $this->activityRules() + [
                'subject_id' => ['required', 'integer', 'min:1'],
                'term_id' => ['nullable', 'integer', 'min:1'],
                'targets' => ['required', 'array', 'min:1', 'max:12'],
                'targets.*.section_id' => ['required', 'integer', 'min:1'],
                'targets.*.group_id' => ['nullable', 'integer', 'min:1'],
                'teachers' => ['required', 'array', 'min:1', 'max:4'],
                'teachers.*.teacher_id' => ['required', 'integer', 'min:1'],
                'teachers.*.role' => ['required', 'integer', 'in:1,2,3'],
                'teachers.*.sessions' => ['nullable', 'integer', 'between:1,40'],
            ],
            'timetable.activities.update' => $this->activityRules(),
            'timetable.activities.sync' => $year + [
                'section_ids' => ['nullable', 'array', 'max:500'],
                'section_ids.*' => ['integer', 'min:1'],
            ],
            'timetable.divisions.store' => $year + [
                'section_id' => ['required', 'integer', 'min:1'],
                'name' => ['required', 'string', 'max:100'],
                'group_count' => ['nullable', 'integer', 'between:2,10', 'required_without:capacity'],
                'capacity' => ['nullable', 'integer', 'between:1,500', 'required_without:group_count'],
                'group_names' => ['nullable', 'array', 'max:10'],
                'group_names.*' => ['nullable', 'string', 'max:100'],
            ],
            'timetable.availability.save' => $year + [
                'target_type' => ['required', 'string', 'in:teacher,room,section,workshop'],
                'target_id' => ['required', 'integer', 'min:1'],
                'slots' => ['required', 'array', 'min:1', 'max:200'],
                'slots.*.day' => ['required', 'integer', 'between:1,7'],
                'slots.*.period_id' => ['required', 'integer', 'min:1'],
                'kind' => ['nullable', 'integer', 'in:1,2,3'],
                'week_no' => ['nullable', 'integer', 'between:1,4'],
                'reason' => ['nullable', 'string', 'max:255'],
            ],
            'timetable.rules.store' => $year + [
                'rule_type' => ['required', 'string', 'max:60'],
                'priority' => ['required', 'integer', 'between:1,6'],
                'scope' => ['nullable', 'array'],
                'scope.*' => ['nullable', 'integer', 'min:1'],
                'params' => ['nullable', 'array'],
                'params.*' => ['nullable'],
                'reason' => ['nullable', 'string', 'max:255'],
            ],
            default => $year,
        };
    }

    /** @return array<string, mixed> */
    private function activityRules(): array
    {
        return [
            'activity_type' => ['required', 'integer', 'between:1,17'],
            'weekly_count' => ['required', 'integer', 'between:1,40'],
            'block_length' => ['required', 'integer', 'between:1,6'],
            'distribution' => ['nullable', 'string', 'max:30', 'regex:/^[1-6](\+[1-6])*$/'],
            'room_id' => ['nullable', 'integer', 'min:1'],
            'room_type' => ['nullable', 'integer', 'between:1,99'],
            'workshop_id' => ['nullable', 'integer', 'min:1'],
            'week_pattern' => ['required', 'integer', 'between:0,4'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
