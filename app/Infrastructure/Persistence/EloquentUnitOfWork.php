<?php

namespace App\Infrastructure\Persistence;

use App\Application\Contracts\UnitOfWork;
use Illuminate\Support\Facades\DB;

final class EloquentUnitOfWork implements UnitOfWork
{
    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
