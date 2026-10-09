<?php

namespace App\Domain\Timetable\ValueObjects;

use App\Domain\Shared\ValueObjects\DisplayAppearance;

/**
 * How a period / break shows and prints: its title («استراحة الطلاب بعد الحصة الرابعة»), abbreviation, colour and
 * the timetables it appears / prints on (bitmask of {@see self::GENERAL} …). Lessons and breaks share it.
 */
final readonly class PeriodPresentation
{
    public const GENERAL = 1;

    public const TEACHERS = 2;

    public const SECTIONS = 4;

    public const STUDENTS = 8;

    public const ROOMS = 16;

    public const EVERYWHERE = 31;

    public const MAX_NAME = 60;

    public function __construct(
        public ?string $name = null,
        public ?string $abbreviation = null,
        public ?int $colorHue = null,
        public int $showIn = self::EVERYWHERE,
        public int $printIn = self::EVERYWHERE,
    ) {}

    public static function of(?string $name, ?string $abbreviation, ?int $colorHue, int $showIn, int $printIn): self
    {
        $name = $name === null ? null : trim((string) preg_replace('/\s+/u', ' ', $name));

        return new self($name === '' ? null : $name, DisplayAppearance::of($abbreviation, null)->abbreviation, $colorHue, $showIn, $printIn);
    }

    /** @return string|null error code */
    public function rejection(): ?string
    {
        if ($this->name !== null && mb_strlen($this->name) > self::MAX_NAME) {
            return 'timetable.period_name_too_long';
        }
        if ($this->showIn < 0 || $this->showIn > self::EVERYWHERE || $this->printIn < 0 || $this->printIn > self::EVERYWHERE) {
            return 'timetable.period_targets_invalid';
        }

        return DisplayAppearance::of($this->abbreviation, $this->colorHue)->rejection();
    }

    public function shows(int $target): bool
    {
        return ($this->showIn & $target) === $target;
    }

    public function prints(int $target): bool
    {
        return ($this->printIn & $target) === $target;
    }

    /** @return array{name: string|null, abbreviation: string|null, color_hue: int|null, show_in: int, print_in: int} */
    public function toColumns(): array
    {
        return [
            'name' => $this->name,
            'abbreviation' => $this->abbreviation,
            'color_hue' => $this->colorHue,
            'show_in' => $this->showIn,
            'print_in' => $this->printIn,
        ];
    }
}
