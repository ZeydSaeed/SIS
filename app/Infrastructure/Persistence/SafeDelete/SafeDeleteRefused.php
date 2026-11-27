<?php

namespace App\Infrastructure\Persistence\SafeDelete;

/** Internal: rolls a multi-record safe delete back when one of its records is refused. */
final class SafeDeleteRefused extends \RuntimeException
{
    /** @param  array{status: string, linked_to?: string, id?: int}  $outcome */
    public function __construct(public readonly array $outcome)
    {
        parent::__construct('safe delete refused');
    }
}
