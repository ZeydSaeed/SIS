<?php

namespace App\Http\Requests\Timetable;

use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use Illuminate\Foundation\Http\FormRequest;

class ShowScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', ScheduleRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
