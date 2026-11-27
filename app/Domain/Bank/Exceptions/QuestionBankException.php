<?php

namespace App\Domain\Bank\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/** Refusals of the question bank (EXAMIS 7.3a). Codes ending in `not_found` render as 404 on the web. */
final class QuestionBankException extends SisDomainException
{
    public static function questionNotFound(int $id): self
    {
        return new self("Question {$id} was not found in the current school.", 'bank.question_not_found');
    }

    public static function versionNotFound(int $id): self
    {
        return new self("Question version {$id} was not found in the current school.", 'bank.version_not_found');
    }

    public static function invalidContent(string $reason): self
    {
        return new self("The question content is invalid: {$reason}.", 'bank.content_invalid');
    }

    public static function invalidState(string $status): self
    {
        return new self("The question version is {$status} and cannot take this action.", 'bank.invalid_state');
    }

    public static function questionRetired(): self
    {
        return new self('The question is retired.', 'bank.question_retired');
    }

    public static function notAuthor(): self
    {
        return new self('Only the author of the draft can change or submit it.', 'bank.not_author');
    }

    public static function fourEyes(): self
    {
        return new self('The author of a question version cannot decide it.', 'bank.four_eyes');
    }

    public static function openVersionExists(): self
    {
        return new self('The question already has a draft or a version under review.', 'bank.open_version_exists');
    }

    public static function decisionInvalid(string $decision): self
    {
        return new self("Unknown decision {$decision}.", 'bank.decision_invalid');
    }

    public static function noteRequired(): self
    {
        return new self('A rejection needs a written note.', 'bank.note_required');
    }

    public static function reasonRequired(): self
    {
        return new self('Retiring a question needs a written reason.', 'bank.reason_required');
    }

    public static function subjectNotAllowed(): self
    {
        return new self('The subject is not one of the subjects you teach.', 'bank.subject_not_allowed');
    }

    public static function keyRequired(): self
    {
        return new self('An idempotency key is required.', 'bank.idempotency_key_required');
    }

    public static function keyConflict(string $command): self
    {
        return new self("Idempotency key of {$command} reused with a different request.", 'bank.idempotency_conflict');
    }
}
