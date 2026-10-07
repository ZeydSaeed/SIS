<?php

namespace App\Http\Requests\Timetable;

/** «اعتماد / رفض» a version in review. */
class DecideTimetableVersionRequest extends TimetableEngineRequest
{
    protected function ability(): string
    {
        return 'approve';
    }

    protected function fieldRules(): array
    {
        return ['decision' => ['required', 'string', 'in:approve,reject']];
    }
}
