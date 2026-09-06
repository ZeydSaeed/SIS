<?php

namespace App\Application\Contracts;

interface UnitOfWork
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function transaction(callable $callback): mixed;
}
