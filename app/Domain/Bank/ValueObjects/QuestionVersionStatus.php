<?php

namespace App\Domain\Bank\ValueObjects;

/**
 * bank.question_versions.status — draft → in_review → approved | rejected; approved → superseded when a newer version
 * is approved. Only a draft's content can change (DEC-057); rejected and superseded are final.
 */
enum QuestionVersionStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    /** A draft or a version under review: a question has at most one. */
    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::InReview;
    }
}
