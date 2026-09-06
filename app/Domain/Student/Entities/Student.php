<?php

namespace App\Domain\Student\Entities;

use App\Domain\Shared\Exceptions\SisDomainException;
use App\Domain\Student\ValueObjects\StudentCode;
use App\Domain\Student\ValueObjects\StudentStatus;

final class Student
{
    private function __construct(
        private readonly int $id,
        private StudentCode $code,
        private string $fullName,
        private StudentStatus $status,
    ) {}

    public static function reconstitute(
        int $id,
        StudentCode $code,
        string $fullName,
        StudentStatus $status,
    ): self {
        return new self($id, $code, $fullName, $status);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function code(): StudentCode
    {
        return $this->code;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function status(): StudentStatus
    {
        return $this->status;
    }

    public function canEnroll(): bool
    {
        return $this->status->canEnroll();
    }

    public function activate(): void
    {
        if ($this->status === StudentStatus::Graduated) {
            throw SisDomainException::withCode('student.cannot_activate_graduated');
        }

        $this->status = StudentStatus::Active;
    }

    public function suspend(): void
    {
        if ($this->status === StudentStatus::Graduated) {
            throw SisDomainException::withCode('student.cannot_suspend_graduated');
        }

        $this->status = StudentStatus::Suspended;
    }
}
