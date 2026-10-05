<?php

namespace Tests\Unit\Organization;

use App\Domain\Organization\Data\DirectorateSnapshot;
use App\Domain\Organization\Data\SchoolSnapshot;
use App\Domain\Organization\Repositories\DirectorateRepositoryInterface;
use App\Domain\Organization\Repositories\SchoolRepositoryInterface;
use App\Domain\Organization\Services\DirectorateRegistryGuard;
use App\Domain\Organization\Services\SchoolRegistryGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchoolRegistryGuardTest extends TestCase
{
    #[Test]
    public function accepts_a_valid_new_school(): void
    {
        $this->assertNull(new SchoolRegistryGuard($this->schools())->createRejectionCode('مدرسة', 1));
    }

    #[Test]
    public function rejects_invalid_school_create_input(): void
    {
        $guard = new SchoolRegistryGuard($this->schools());

        $this->assertSame('organization.school_name_invalid', $guard->createRejectionCode('  ', 1));
        $this->assertSame('organization.directorate_invalid', $guard->createRejectionCode('مدرسة', 99));
    }

    #[Test]
    public function validates_school_updates(): void
    {
        $guard = new SchoolRegistryGuard($this->schools());

        $this->assertNull($guard->updateRejectionCode(5, ['name' => 'جديد']));
        $this->assertSame('organization.school_not_found', $guard->updateRejectionCode(6, ['name' => 'جديد']));
        $this->assertSame('organization.school_update_empty', $guard->updateRejectionCode(5, []));
        $this->assertSame('organization.school_name_invalid', $guard->updateRejectionCode(5, ['name' => ' ']));
        $this->assertSame('organization.directorate_invalid', $guard->updateRejectionCode(5, ['directorate_id' => 99]));
    }

    #[Test]
    public function validates_school_status_changes(): void
    {
        $guard = new SchoolRegistryGuard($this->schools());

        $this->assertNull($guard->statusRejectionCode(7, 2));
        $this->assertNull($guard->statusRejectionCode(7, 1));
        $this->assertSame('organization.school_status_invalid', $guard->statusRejectionCode(7, 9));
        $this->assertSame('organization.school_not_found', $guard->statusRejectionCode(6, 2));
        // School 5 sits in directorate 3, which is not active in this fake.
        $this->assertSame('organization.directorate_invalid', $guard->statusRejectionCode(5, 1));
    }

    #[Test]
    public function validates_directorate_create_and_school_placement(): void
    {
        $guard = $this->directorateGuard();

        $this->assertNull($guard->createRejectionCode('مديرية', 1, [5], [5, 7]));
        $this->assertSame('organization.directorate_name_invalid', $guard->createRejectionCode(' ', 1, [], [5]));
        $this->assertSame('organization.ministry_missing', $guard->createRejectionCode('مديرية', null, [], [5]));
        $this->assertSame('organization.school_not_linked', $guard->createRejectionCode('مديرية', 1, [8], [5, 7]));
    }

    #[Test]
    public function validates_directorate_updates(): void
    {
        $guard = $this->directorateGuard();

        // Directorate 3 holds school 5; school 7 lives elsewhere.
        $this->assertNull($guard->updateRejectionCode(3, ['region' => null], null, [5, 7]));
        $this->assertNull($guard->updateRejectionCode(3, [], [5, 7], [5, 7]));
        $this->assertSame('organization.directorate_not_found', $guard->updateRejectionCode(9, ['name' => 'x'], null, [5]));
        $this->assertSame('organization.directorate_update_empty', $guard->updateRejectionCode(3, [], null, [5]));
        $this->assertSame('organization.directorate_school_removal', $guard->updateRejectionCode(3, [], [7], [5, 7]));
        $this->assertSame('organization.directorate_inactive', $guard->updateRejectionCode(4, [], [7], [5, 7]));
    }

    #[Test]
    public function validates_directorate_status_changes(): void
    {
        $guard = $this->directorateGuard();

        $this->assertNull($guard->statusRejectionCode(4, 1));
        $this->assertNull($guard->statusRejectionCode(4, 2));
        $this->assertSame('organization.directorate_has_schools', $guard->statusRejectionCode(3, 2));
        $this->assertSame('organization.directorate_status_invalid', $guard->statusRejectionCode(3, 7));
        $this->assertSame('organization.directorate_not_found', $guard->statusRejectionCode(9, 2));
    }

    private function directorateGuard(): DirectorateRegistryGuard
    {
        return new DirectorateRegistryGuard(new class implements DirectorateRepositoryInterface
        {
            public function find(int $directorateId): ?DirectorateSnapshot
            {
                return match ($directorateId) {
                    3 => new DirectorateSnapshot(3, 1, 'DIR-0003', 'نشطة', null, 1),
                    4 => new DirectorateSnapshot(4, 1, 'DIR-0004', 'معطلة', null, 2),
                    default => null,
                };
            }

            public function defaultMinistryId(): ?int
            {
                return 1;
            }

            public function nextCode(): string
            {
                return 'DIR-0005';
            }

            public function create(int $ministryId, string $code, string $name, ?string $region, string $createdAt): int
            {
                return 5;
            }

            public function update(int $directorateId, array $fields, string $updatedAt): bool
            {
                return true;
            }

            public function setStatus(int $directorateId, int $status, string $updatedAt): bool
            {
                return true;
            }

            public function activeSchoolCount(int $directorateId): int
            {
                return $directorateId === 3 ? 1 : 0;
            }

            public function schoolIdsIn(int $directorateId, array $schoolIds): array
            {
                return $directorateId === 3 ? array_values(array_intersect([5], $schoolIds)) : [];
            }
        }, $this->schools());
    }

    private function schools(): SchoolRepositoryInterface
    {
        return new class implements SchoolRepositoryInterface
        {
            public function find(int $schoolId): ?SchoolSnapshot
            {
                return match ($schoolId) {
                    5 => new SchoolSnapshot(5, 3, 'SCH-0005', 'مدرسة', 2, null, null, null, 1),
                    7 => new SchoolSnapshot(7, 1, 'SCH-0007', 'أخرى', 2, null, null, null, 1),
                    default => null,
                };
            }

            public function nextCode(): string
            {
                return 'SCH-0001';
            }

            public function directorateIsActive(int $directorateId): bool
            {
                return $directorateId === 1;
            }

            public function create(int $directorateId, string $code, string $name, int $schoolType, ?string $address, ?string $phone, ?string $email, string $createdAt): int
            {
                return 1;
            }

            public function update(int $schoolId, array $fields, string $updatedAt): bool
            {
                return true;
            }

            public function setStatus(int $schoolId, int $status, string $updatedAt): bool
            {
                return true;
            }
        };
    }
}
