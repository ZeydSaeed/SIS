<?php

namespace App\Domain\Organization\Data;

use App\Domain\Shared\ValueObjects\DisplayAppearance;

/** What «الغرف الدراسية» saves for one room (code and branch are fixed after creation). */
final readonly class RoomDetails
{
    public function __construct(
        public string $name,
        public DisplayAppearance $appearance,
        public ?string $roomNumber,
        public ?int $roomTypeId,
        public ?int $capacity,
        public ?string $building,
        public ?int $floor,
        public ?string $location,
        public ?int $departmentId,
        public bool $supportsPractical,
        public ?string $equipment,
        public ?string $suitableFor,
        public ?string $notes,
    ) {}

    /** @return array<string, mixed> column → value (legacy `room_type` follows practical support) */
    public function toColumns(): array
    {
        return [
            'name' => trim($this->name),
            'abbreviation' => $this->appearance->abbreviation,
            'color_hue' => $this->appearance->colorHue,
            'room_number' => self::blank($this->roomNumber),
            'room_type_id' => $this->roomTypeId,
            'capacity' => $this->capacity,
            'building' => self::blank($this->building),
            'floor' => $this->floor,
            'location' => self::blank($this->location),
            'department_id' => $this->departmentId,
            'supports_practical' => $this->supportsPractical,
            'room_type' => $this->supportsPractical ? 2 : 1,
            'equipment' => self::blank($this->equipment),
            'suitable_for' => self::blank($this->suitableFor),
            'notes' => self::blank($this->notes),
        ];
    }

    private static function blank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
