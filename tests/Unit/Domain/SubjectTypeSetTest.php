<?php

namespace Tests\Unit\Domain;

use App\Domain\Curriculum\ValueObjects\SubjectTypeSet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SubjectTypeSetTest extends TestCase
{
    #[Test]
    public function types_are_sorted_the_first_is_primary_and_hours_add_up(): void
    {
        $set = SubjectTypeSet::from([4, '3'], ['3' => 2, 4 => '3', 1 => 9]);

        $this->assertNotNull($set);
        $this->assertSame([3, 4], $set->types());
        $this->assertSame(3, $set->primary());
        $this->assertSame([3 => 2, 4 => 3], $set->hours);
        $this->assertSame(5, $set->totalHours());
    }

    #[Test]
    public function missing_hours_stay_null_and_bad_input_is_refused(): void
    {
        $this->assertNull(SubjectTypeSet::from([1])?->totalHours());
        $this->assertSame([1 => null, 3 => 2], SubjectTypeSet::from([1, 3], [3 => 2])?->hours);
        $this->assertNull(SubjectTypeSet::from([]));
        $this->assertNull(SubjectTypeSet::from([5]));
        $this->assertNull(SubjectTypeSet::from([3], [3 => 41]));
        $this->assertNull(SubjectTypeSet::from([3], [3 => -1]));
    }
}
