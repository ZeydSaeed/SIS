<?php

namespace App\Http\Requests\Timetable;

/** Versions: snapshot, submit for approval, publish (effective date), archive, restore. */
class PublishTimetableRequest extends TimetableEngineRequest
{
    protected function ability(): string
    {
        return 'publish';
    }

    protected function fieldRules(): array
    {
        return match ($this->route()?->getName()) {
            'timetable.versions.store' => [
                'academic_year_id' => $this->yearRule(),
                'name' => ['required', 'string', 'max:150'],
                'reason' => ['nullable', 'string', 'max:2000'],
                'generation_run_id' => ['nullable', 'integer', 'min:1'],
            ],
            'timetable.versions.publish' => ['effective_from' => ['required', 'date_format:Y-m-d']],
            default => [],
        };
    }
}
