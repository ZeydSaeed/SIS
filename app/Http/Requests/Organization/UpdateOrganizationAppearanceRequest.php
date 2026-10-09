<?php

namespace App\Http\Requests\Organization;

use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * «الاختصار واللون» of a branch / department (school managers) or a room / room type (school managers and
 * timetable planners). The handler checks the row belongs to the current school.
 */
class UpdateOrganizationAppearanceRequest extends FormRequest
{
    use RequiresOrganizationIdempotencyKey;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if ($user->can('manageSchools')) {
            return true;
        }

        return in_array($this->input('target'), ['room', 'room_type'], true) && $user->can('manageConstraints', ScheduleRecord::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'target' => ['required', 'string', 'in:branch,department,room,room_type'],
            'id' => ['required', 'integer', 'min:1'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'color_hue' => ['nullable', 'integer', 'between:0,359'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
