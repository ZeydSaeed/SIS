<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/** Refusals of publishing / withdrawing official results (EXAMIS A1). */
final class ResultPublicationException extends SisDomainException
{
    public static function notReady(int $missing, int $total): self
    {
        return new self("{$missing} of {$total} students have no complete official result.", 'results.publish.not_ready');
    }

    public static function alreadyPublished(): self
    {
        return new self('An overlapping publication is already active for this scope.', 'results.publish.already_published');
    }

    public static function notFound(int $id): self
    {
        return new self("Publication {$id} is not an active publication of this school.", 'results.publish.not_found');
    }

    public static function reasonRequired(): self
    {
        return new self('A withdrawal reason is required.', 'results.publish.reason_required');
    }

    public static function scopeInvalid(): self
    {
        return new self('The term and subject of a term publication must belong to the academic year.', 'results.publish.scope_invalid');
    }

    public static function kindInvalid(string $kind): self
    {
        return new self("Unknown result kind {$kind}.", 'results.publish.kind_invalid');
    }
}
