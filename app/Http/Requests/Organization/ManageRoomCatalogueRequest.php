<?php

namespace App\Http\Requests\Organization;

use App\Domain\Organization\Services\RoomCatalogueGuard;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * «الغرف الدراسية» writes. Who may: school managers, and timetable planners (`manageConstraints` — the holders who
 * managed rooms from the timetable before this page existed). Fields per action come from the route name.
 */
class ManageRoomCatalogueRequest extends FormRequest
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
            'organization.rooms.store' => [
                'branch_id' => ['required', 'integer', 'min:1'],
                'code' => ['required', 'string', 'max:'.RoomCatalogueGuard::MAX_CODE],
            ] + $this->roomRules(),
            'organization.rooms.update' => $this->roomRules(),
            'organization.room-types.store' => ['code' => ['required', 'string', 'max:30']] + $this->typeRules(),
            'organization.room-types.update' => $this->typeRules(),
            // `status` is a protected field name (SecuritySensitiveFieldGuard): 1 = in service, 0 = out of service.
            'organization.rooms.status', 'organization.room-types.status' => ['active' => ['required', 'integer', 'in:0,1']],
            default => [],
        };

        return array_merge($fields, ['school_id' => ['prohibited']], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    /** @return array<string, mixed> */
    private function roomRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.RoomCatalogueGuard::MAX_NAME],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'color_hue' => ['nullable', 'integer', 'between:0,359'],
            'room_number' => ['nullable', 'string', 'max:20'],
            'room_type_id' => ['nullable', 'integer', 'min:1'],
            'capacity' => ['nullable', 'integer', 'between:1,'.RoomCatalogueGuard::MAX_CAPACITY],
            'building' => ['nullable', 'string', 'max:100'],
            'floor' => ['nullable', 'integer', 'between:-5,100'],
            'location' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'supports_practical' => ['required', 'boolean'],
            'equipment' => ['nullable', 'string', 'max:'.RoomCatalogueGuard::MAX_TEXT],
            'suitable_for' => ['nullable', 'string', 'max:'.RoomCatalogueGuard::MAX_TEXT],
            'notes' => ['nullable', 'string', 'max:'.RoomCatalogueGuard::MAX_TEXT],
        ];
    }

    /** @return array<string, mixed> */
    private function typeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.RoomCatalogueGuard::MAX_NAME],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'color_hue' => ['nullable', 'integer', 'between:0,359'],
            'kind' => ['required', 'integer', 'in:1,2,3,4,9'],
            'supports_practical' => ['nullable', 'boolean'],
        ];
    }
}
