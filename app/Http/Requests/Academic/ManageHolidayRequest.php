<?php

namespace App\Http\Requests\Academic;

use App\Domain\Academic\Services\HolidayGuard;
use App\Http\Requests\Organization\RequiresOrganizationIdempotencyKey;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * «الاجازات» writes. Who may: school managers, and timetable planners (`manageConstraints` — they decide which days
 * lessons do not run). The school is never a field: it is the user's school context. Fields per action come from
 * the route name.
 */
class ManageHolidayRequest extends FormRequest
{
    use RequiresOrganizationIdempotencyKey;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('manageSchools') || $user->can('manageConstraints', ScheduleRecord::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $fields = match ($this->route()?->getName()) {
            'leave.holidays.store' => ['academic_year_id' => ['required', 'integer', 'min:1']] + $this->holidayRules(),
            'leave.holidays.update' => $this->holidayRules(),
            // `status` is a protected field name (SecuritySensitiveFieldGuard): 1 = in the calendar, 0 = out of it.
            'leave.holidays.status' => ['active' => ['required', 'integer', 'in:0,1']],
            default => [],
        };

        return array_merge($fields, ['school_id' => ['prohibited']], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    /** @return array<string, mixed> */
    private function holidayRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.HolidayGuard::MAX_NAME],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'holiday_type' => ['required', 'integer', 'in:'.implode(',', HolidayGuard::TYPES)],
        ];
    }
}
