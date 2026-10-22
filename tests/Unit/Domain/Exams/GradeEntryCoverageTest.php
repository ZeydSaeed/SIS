<?php

namespace Tests\Unit\Domain\Exams;

use App\Domain\Exams\Data\GradeSeatPlacement;
use App\Domain\Exams\Data\TeachingScope;
use App\Domain\Exams\Services\GradeEntryCoverage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GradeEntryCoverageTest extends TestCase
{
    private static function seat(?int $branchId = 3, ?int $departmentId = 4): GradeSeatPlacement
    {
        // school 1, year 2, subject 9, branch 3, department 4, class 5, section 6
        return new GradeSeatPlacement(100, 1, 2, 9, $branchId, $departmentId, 5, 6);
    }

    /**
     * @param  array<string, int|null>  $overrides
     */
    private static function scope(array $overrides = []): TeachingScope
    {
        $v = $overrides + [
            'school' => 1, 'year' => 2, 'subject' => 9, 'branch' => 3,
            'department' => 4, 'class' => 5, 'section' => 6,
        ];

        return new TeachingScope($v['school'], $v['year'], $v['subject'], $v['branch'], $v['department'], $v['class'], $v['section']);
    }

    /**
     * @return iterable<string, array{array<string, int|null>, bool}>
     */
    public static function cases(): iterable
    {
        yield 'exact section assignment' => [[], true];
        yield 'whole class (section null)' => [['section' => null], true];
        yield 'whole department (class + section null)' => [['class' => null, 'section' => null], true];
        yield 'whole branch (department + class + section null)' => [['department' => null, 'class' => null, 'section' => null], true];
        yield 'other school' => [['school' => 7], false];
        yield 'other academic year' => [['year' => 8], false];
        yield 'other subject' => [['subject' => 10], false];
        yield 'other branch' => [['branch' => 30], false];
        yield 'other department' => [['department' => 40], false];
        yield 'other class' => [['class' => 50], false];
        yield 'other section of the same class' => [['section' => 60], false];
        yield 'other branch even when the rest is open' => [['branch' => 30, 'department' => null, 'class' => null, 'section' => null], false];
    }

    /**
     * @param  array<string, int|null>  $overrides
     */
    #[Test]
    #[DataProvider('cases')]
    public function covers_only_the_assigned_scope(array $overrides, bool $expected): void
    {
        $this->assertSame($expected, GradeEntryCoverage::covers(self::seat(), self::scope($overrides)));
    }

    #[Test]
    public function seat_without_branch_is_never_covered(): void
    {
        $this->assertFalse(GradeEntryCoverage::covers(self::seat(branchId: null), self::scope(['department' => null, 'class' => null, 'section' => null])));
    }

    #[Test]
    public function department_specific_assignment_does_not_cover_seat_without_department(): void
    {
        $this->assertFalse(GradeEntryCoverage::covers(self::seat(departmentId: null), self::scope()));
        $this->assertTrue(GradeEntryCoverage::covers(self::seat(departmentId: null), self::scope(['department' => null])));
    }

    #[Test]
    public function any_covers_needs_one_matching_assignment(): void
    {
        $this->assertFalse(GradeEntryCoverage::anyCovers(self::seat(), []));
        $this->assertFalse(GradeEntryCoverage::anyCovers(self::seat(), [self::scope(['subject' => 10]), self::scope(['section' => 60])]));
        $this->assertTrue(GradeEntryCoverage::anyCovers(self::seat(), [self::scope(['subject' => 10]), self::scope(['section' => null])]));
    }
}
