<?php

namespace App\Application\Graduation\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Domain\Graduation\Exceptions\PublicationPolicyNotConfiguredException;

/**
 * HD-38 OPEN — publication remains policy-gated; no silent workflow.
 */
final class PublishAwardHandler implements CommandHandler
{
    public function handle(Command $command): mixed
    {
        assert($command instanceof PublishAwardCommand);

        throw PublicationPolicyNotConfiguredException::blocked();
    }
}
