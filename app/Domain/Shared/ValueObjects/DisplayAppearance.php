<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\ValueObject;

/**
 * How an entity shows on the timetable and in lists: a short label («الاختصار») and a colour.
 *
 * The colour is a hue (0–359) on the approved card palette — lightness and saturation stay governed by the
 * stylesheet, so any hue keeps the card's contrast. NULL = the default (the timetable's colour wheel / the
 * suggested abbreviation); clearing a value is «إعادة الافتراضي». The owning entity stores it; the timetable
 * only reads it.
 */
final readonly class DisplayAppearance extends ValueObject
{
    public const MAX_ABBREVIATION = 20;

    private function __construct(
        public ?string $abbreviation,
        public ?int $colorHue,
    ) {}

    /** Trims and collapses spaces; a blank abbreviation means «no abbreviation» (default). */
    public static function of(?string $abbreviation, ?int $colorHue): self
    {
        $abbreviation = $abbreviation === null ? null : trim((string) preg_replace('/\s+/u', ' ', $abbreviation));

        return new self($abbreviation === '' ? null : $abbreviation, $colorHue);
    }

    /** @return string|null error code */
    public function rejection(): ?string
    {
        if ($this->abbreviation !== null && mb_strlen($this->abbreviation) > self::MAX_ABBREVIATION) {
            return 'appearance.abbreviation_too_long';
        }
        if ($this->colorHue !== null && ($this->colorHue < 0 || $this->colorHue > 359)) {
            return 'appearance.color_invalid';
        }

        return null;
    }

    /** @return array{abbreviation: string|null, color_hue: int|null} */
    public function toArray(): array
    {
        return ['abbreviation' => $this->abbreviation, 'color_hue' => $this->colorHue];
    }
}
