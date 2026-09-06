<?php

namespace App\Application\Contracts;

/**
 * @template TCommand of Command
 */
interface CommandHandler
{
    /**
     * @param  TCommand  $command
     */
    public function handle(Command $command): mixed;
}
