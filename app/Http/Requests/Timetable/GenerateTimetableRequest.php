<?php

namespace App\Http\Requests\Timetable;

/** Generation runs: queue (mode, scope, options, what-if), cancel, apply, discard. */
class GenerateTimetableRequest extends TimetableEngineRequest
{
    protected function ability(): string
    {
        return 'generate';
    }

    protected function fieldRules(): array
    {
        if ($this->route()?->getName() !== 'timetable.runs.store') {
            return [];
        }

        return [
            'academic_year_id' => $this->yearRule(),
            'mode' => ['required', 'integer', 'between:1,5'],
            'scope' => ['nullable', 'array'],
            'scope.section_ids' => ['nullable', 'array', 'max:500'],
            'scope.section_ids.*' => ['integer', 'min:1'],
            'scope.teacher_ids' => ['nullable', 'array', 'max:500'],
            'scope.teacher_ids.*' => ['integer', 'min:1'],
            'scope.subject_ids' => ['nullable', 'array', 'max:500'],
            'scope.subject_ids.*' => ['integer', 'min:1'],
            'scope.branch_id' => ['nullable', 'integer', 'min:1'],
            'scope.department_id' => ['nullable', 'integer', 'min:1'],
            'scope.class_id' => ['nullable', 'integer', 'min:1'],
            'time_budget' => ['nullable', 'integer', 'between:2,120'],
            'seed' => ['nullable', 'integer', 'between:1,2147483647'],
            'objectives' => ['nullable', 'array', 'max:5'],
            'objectives.*' => ['string', 'in:minimize_teacher_gaps,avoid_last_lesson,morning_practicals'],
            'what_if' => ['nullable', 'array', 'max:10'],
            'what_if.*.type' => ['required', 'string', 'in:teacher_absent,room_closed,workshop_closed'],
            'what_if.*.id' => ['required', 'integer', 'min:1'],
            'what_if.*.days' => ['required', 'array', 'min:1', 'max:7'],
            'what_if.*.days.*' => ['integer', 'between:1,7'],
        ];
    }
}
