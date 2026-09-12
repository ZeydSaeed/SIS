<?php

namespace App\Http\Requests\Timetable;

use App\Infrastructure\Persistence\Eloquent\ScheduleExceptionRecord;
use Illuminate\Foundation\Http\FormRequest;

class ShowScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', ScheduleExceptionRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
