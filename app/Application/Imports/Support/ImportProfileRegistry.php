<?php

namespace App\Application\Imports\Support;

use App\Application\Imports\Contracts\ImportProfile;

/** The importable kinds, by key (profiles are contributed by their owning contexts through the container). */
final class ImportProfileRegistry
{
    /** @var array<string, ImportProfile> */
    private array $profiles = [];

    /** @param  iterable<ImportProfile>  $profiles */
    public function __construct(iterable $profiles)
    {
        foreach ($profiles as $profile) {
            $this->profiles[$profile->kind()] = $profile;
        }
    }

    public function get(string $kind): ?ImportProfile
    {
        return $this->profiles[$kind] ?? null;
    }

    /** @return array<string, ImportProfile> */
    public function all(): array
    {
        return $this->profiles;
    }
}
