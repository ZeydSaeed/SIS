<?php

namespace App\Http\Requests\Timetable;

/** «تثبيت / إلغاء التثبيت» of lessons. */
class LockSchedulesRequest extends TimetableEngineRequest
{
    protected function ability(): string
    {
        return 'lockSchedules';
    }

    protected function fieldRules(): array
    {
        return [
            'academic_year_id' => $this->yearRule(),
            'schedule_ids' => ['required', 'array', 'min:1', 'max:500'],
            'schedule_ids.*' => ['integer', 'min:1'],
            'lock' => ['required', 'boolean'],
        ];
    }
}
