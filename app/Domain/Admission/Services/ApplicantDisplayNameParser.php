<?php

namespace App\Domain\Admission\Services;

/**
 * Splits a single display name into the five applicant name columns.
 */
final class ApplicantDisplayNameParser
{
    /**
     * @return array{
     *     first_name:string,
     *     father_name:?string,
     *     grandfather_name:?string,
     *     great_grandfather_name:?string,
     *     last_name:string
     * }
     */
    public function parse(string $displayName): array
    {
        $parts = preg_split('/\s+/u', trim($displayName)) ?: [];
        $parts = array_values(array_filter(
            $parts,
            static fn (string $part): bool => trim($part) !== '',
        ));

        if ($parts === []) {
            throw new \InvalidArgumentException('Applicant display name is required.');
        }

        $count = count($parts);

        return match (true) {
            $count === 1 => [
                'first_name' => $parts[0],
                'father_name' => null,
                'grandfather_name' => null,
                'great_grandfather_name' => null,
                'last_name' => $parts[0],
            ],
            $count === 2 => [
                'first_name' => $parts[0],
                'father_name' => null,
                'grandfather_name' => null,
                'great_grandfather_name' => null,
                'last_name' => $parts[1],
            ],
            $count === 3 => [
                'first_name' => $parts[0],
                'father_name' => $parts[1],
                'grandfather_name' => null,
                'great_grandfather_name' => null,
                'last_name' => $parts[2],
            ],
            $count === 4 => [
                'first_name' => $parts[0],
                'father_name' => $parts[1],
                'grandfather_name' => $parts[2],
                'great_grandfather_name' => null,
                'last_name' => $parts[3],
            ],
            default => [
                'first_name' => $parts[0],
                'father_name' => $parts[1],
                'grandfather_name' => $parts[2],
                'great_grandfather_name' => $parts[3],
                'last_name' => implode(' ', array_slice($parts, 4)),
            ],
        };
    }
}
