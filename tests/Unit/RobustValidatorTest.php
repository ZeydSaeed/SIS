<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RobustValidatorTest extends TestCase
{
    /** @return array<string, array{0: mixed}> */
    public static function hostile(): array
    {
        return [
            'array' => [[]],
            'nested array' => [[[1]]],
            'assoc array' => [['x' => 1]],
            'huge int' => [PHP_INT_MAX],
            'nul byte' => ["a\0b"],
            'garbage' => ['not a date'],
            'impossible day' => ['2026-02-30'],
        ];
    }

    #[Test]
    #[DataProvider('hostile')]
    public function date_rules_fail_with_validation_errors_instead_of_crashing(mixed $value): void
    {
        $rules = [
            'start' => ['required', 'date', 'before:today'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'from' => ['required', 'date_format:H:i'],
            'to' => ['required', 'date_format:H:i', 'after:from'],
        ];

        // Hostile value as the reference field of a comparison…
        $v = validator(['start' => $value, 'end' => '2026-01-01', 'from' => $value, 'to' => '10:00'], $rules);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('start'));
        $this->assertTrue($v->errors()->has('from'));

        // …and as the compared field.
        $v = validator(['start' => '2020-01-01', 'end' => $value, 'from' => '08:00', 'to' => $value], $rules);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('end'));
        $this->assertTrue($v->errors()->has('to'));
    }

    #[Test]
    public function valid_dates_still_pass(): void
    {
        $v = validator(
            ['start' => '2020-01-01', 'end' => '2020-02-01', 'from' => '08:00', 'to' => '08:45'],
            ['start' => ['required', 'date', 'before:today'], 'end' => ['nullable', 'date', 'after_or_equal:start'], 'from' => ['required', 'date_format:H:i'], 'to' => ['required', 'date_format:H:i', 'after:from']],
        );
        $this->assertTrue($v->passes());
    }
}
