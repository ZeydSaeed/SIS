<?php

namespace App\Application\Contracts;

/**
 * @template TQuery of Query
 * @template TResult
 */
interface QueryHandler
{
    /**
     * @param  TQuery  $query
     * @return TResult
     */
    public function handle(Query $query): mixed;
}
