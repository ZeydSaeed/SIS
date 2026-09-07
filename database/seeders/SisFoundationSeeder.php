<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Minimal organization + academic reference data for local dev and Phase 3.3 Students slice.
 */
class SisFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FoundationOrganizationSeeder::class,
            FoundationAcademicSeeder::class,
        ]);
    }
}
