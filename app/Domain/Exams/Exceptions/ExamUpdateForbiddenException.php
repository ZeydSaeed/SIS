<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

final class ExamUpdateForbiddenException extends DomainException
{
    public static function metadataNotAllowed(string $status): self
    {
        return new self("Metadata updates are not allowed when exam status is {$status}.");
    }

    public static function fieldNotMutable(string $field, string $status): self
    {
        return new self("Field '{$field}' is not mutable when exam status is {$status}.");
    }

    public static function terminalStatus(string $status): self
    {
        return new self("Exam status {$status} is terminal and cannot be changed.");
    }

    public static function useCancelExam(): self
    {
        return new self('Cancelling an exam requires CancelExam with exam.cancel permission.');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("Invalid exam status transition from {$from} to {$to}.");
    }

    public static function immutableField(string $field): self
    {
        return new self("Field '{$field}' is immutable.");
    }
}
