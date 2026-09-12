<?php

namespace App\Domain\Vocational\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class VocationalNotFoundException extends SisDomainException
{
    public static function specialization(int $id): self
    {
        return new self('vocational.specialization_not_found:'.$id);
    }

    public static function track(int $id): self
    {
        return new self('vocational.track_not_found:'.$id);
    }

    public static function subjectLink(int $id): self
    {
        return new self('vocational.specialization_subject_not_found:'.$id);
    }
}
