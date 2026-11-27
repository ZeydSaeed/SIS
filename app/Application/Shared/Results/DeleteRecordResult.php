<?php

namespace App\Application\Shared\Results;

final readonly class DeleteRecordResult extends ApplicationResult
{
    /** @param  list<string>  $errors */
    private function __construct(bool $success, public int $id = 0, array $errors = [])
    {
        parent::__construct($success, $errors);
    }

    public static function deleted(int $id): self
    {
        return new self(true, $id);
    }

    /** @param  string  $code  e.g. `delete.not_found`, `delete.linked?with=…` */
    public static function refused(string $code): self
    {
        return new self(false, 0, [$code]);
    }
}
