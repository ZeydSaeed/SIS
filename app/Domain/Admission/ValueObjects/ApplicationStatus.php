<?php

namespace App\Domain\Admission\ValueObjects;

/**
 * Application lifecycle statuses (admission.applications.status).
 * Waitlist is a status — no separate waitlist table in Phase 2 blueprint.
 */
enum ApplicationStatus: int
{
    case Draft = 1;
    case Submitted = 2;
    case UnderReview = 3;
    case Interview = 4;
    case Waitlisted = 5;
    case Accepted = 6;
    case Rejected = 7;
    case Withdrawn = 8;
    case Converted = 9;

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Rejected, self::Withdrawn, self::Converted => true,
            default => false,
        };
    }

    public function canConvertToStudent(): bool
    {
        return $this === self::Accepted;
    }

    /**
     * Allowed manual transitions.
     * Converted only via ConvertApplicationToStudent or RegisterStudentViaAdmission.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Withdrawn],
            self::Submitted => [self::UnderReview, self::Withdrawn],
            self::UnderReview => [
                self::Interview,
                self::Waitlisted,
                self::Accepted,
                self::Rejected,
                self::Withdrawn,
            ],
            self::Interview => [
                self::Waitlisted,
                self::Accepted,
                self::Rejected,
                self::UnderReview,
                self::Withdrawn,
            ],
            self::Waitlisted => [
                self::Interview,
                self::Accepted,
                self::Rejected,
                self::Withdrawn,
            ],
            self::Accepted => [self::Withdrawn],
            self::Rejected, self::Withdrawn, self::Converted => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        foreach ($this->allowedTransitions() as $allowed) {
            if ($allowed === $target) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ordered pipeline steps for UI ribbon.
     * Excludes Draft (via «طلب قبول»), Converted (Accepted → student),
     * and terminal Withdrawn / Rejected (not ribbon buttons).
     *
     * @return list<self>
     */
    public static function pipelineSteps(): array
    {
        return [
            self::Submitted,
            self::UnderReview,
            self::Interview,
            self::Waitlisted,
            self::Accepted,
        ];
    }
}
