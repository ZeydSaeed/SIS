<?php

namespace App\Domain\Shared\Exceptions;

class SisDomainException extends \DomainException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'domain.error',
    ) {
        parent::__construct($message);
    }

    public static function withCode(string $errorCode, ?string $message = null): self
    {
        return new self($message ?? $errorCode, $errorCode);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
