<?php

namespace App\Application\Observability\Contracts;

interface DatabaseHealthPort
{
    public function isAvailable(): bool;
}
