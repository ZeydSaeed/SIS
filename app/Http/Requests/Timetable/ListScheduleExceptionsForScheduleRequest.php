<?php

namespace App\Http\Requests\Timetable;

use App\Domain\Timetable\Exceptions\ScheduleNotFoundException;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use Illuminate\Foundation\Http\FormRequest;

class ListScheduleExceptionsForScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scheduleId = (int) $this->route('schedule');
        $schedule = ScheduleRecord::query()->find($scheduleId);
        if ($schedule === null) {
            throw ScheduleNotFoundException::forId($scheduleId);
        }

        return $this->user()?->can('view', $schedule) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
