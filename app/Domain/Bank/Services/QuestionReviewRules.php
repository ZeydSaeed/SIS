<?php

namespace App\Domain\Bank\Services;

use App\Domain\Bank\Exceptions\QuestionBankException;
use App\Domain\Bank\ValueObjects\QuestionVersionStatus;

/**
 * Lifecycle rules of a question version (DEC-047/056/057). The database enforces the same rules again (CHECK +
 * trigger); these give a clear refusal code before the write.
 */
final class QuestionReviewRules
{
    public const APPROVE = 'approve';

    public const REJECT = 'reject';

    public static function assertQuestionActive(string $questionStatus): void
    {
        if ($questionStatus !== 'active') {
            throw QuestionBankException::questionRetired();
        }
    }

    /** Only the author changes or submits their draft. */
    public static function assertAuthorCanChange(QuestionVersionStatus $status, int $authorId, int $actorId): void
    {
        if ($status !== QuestionVersionStatus::Draft) {
            throw QuestionBankException::invalidState($status->value);
        }
        if ($authorId !== $actorId) {
            throw QuestionBankException::notAuthor();
        }
    }

    /**
     * A version under review is decided by someone other than its author (four eyes, DEC-056: no self-approval
     * setting in 7.3a; the database CHECK refuses it too).
     */
    public static function assertCanDecide(QuestionVersionStatus $status, int $authorId, int $actorId, string $decision, string $note): void
    {
        if (! in_array($decision, [self::APPROVE, self::REJECT], true)) {
            throw QuestionBankException::decisionInvalid($decision);
        }
        if ($status !== QuestionVersionStatus::InReview) {
            throw QuestionBankException::invalidState($status->value);
        }
        if ($authorId === $actorId) {
            throw QuestionBankException::fourEyes();
        }
        if ($decision === self::REJECT && trim($note) === '') {
            throw QuestionBankException::noteRequired();
        }
    }
}
