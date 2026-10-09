<?php

namespace App\Domain\Teachers\Services;

use App\Domain\Shared\ValueObjects\DisplayAppearance;
use App\Domain\Teachers\Repositories\AcademicTitleRepositoryInterface;
use App\Domain\Teachers\ValueObjects\TeacherEmploymentType;

/**
 * The profile extras of a teacher: «نوع التعيين», «اللقب العلمي» (reference data) and «الاختصار» (≤ 20 chars).
 * One call answers the first broken rule, so the register / update handlers stay a single branch.
 */
final class TeacherTitleGuard
{
    public function __construct(
        private readonly AcademicTitleRepositoryInterface $titles,
    ) {}

    /**
     * @param  bool  $checkTitle  false when the request does not touch the title / abbreviation
     * @return string|null error code
     */
    public function profileRejection(?int $employmentType, bool $checkTitle, ?int $academicTitleId, ?string $abbreviation): ?string
    {
        if (! TeacherEmploymentType::isValid($employmentType)) {
            return 'teachers.employment_type_invalid';
        }
        if (! $checkTitle) {
            return null;
        }
        if ($academicTitleId !== null && ! $this->titles->activeExists($academicTitleId)) {
            return 'teachers.academic_title_invalid';
        }

        return DisplayAppearance::of($abbreviation, null)->rejection();
    }

    /** @return array<string, int|string|null> the columns to store — none when the request does not touch them */
    public static function columns(bool $apply, ?int $academicTitleId, ?string $abbreviation): array
    {
        return $apply ? ['academic_title_id' => $academicTitleId, 'abbreviation' => DisplayAppearance::of($abbreviation, null)->abbreviation] : [];
    }
}
