<?php

namespace App\Application\Organization\Imports;

use App\Application\Imports\Contracts\ImportProfile;
use App\Application\Imports\Support\ImportContext;
use App\Application\Imports\Support\ImportValues;
use App\Application\Organization\Commands\CreateBranchCommand;
use App\Application\Organization\Commands\CreateBranchHandler;
use App\Application\Organization\Commands\CreateDepartmentCommand;
use App\Application\Organization\Commands\CreateDepartmentHandler;
use App\Application\Organization\Commands\UpdateOrganizationAppearanceCommand;
use App\Application\Organization\Commands\UpdateOrganizationAppearanceHandler;
use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface;

/**
 * «استيراد الفروع والاختصاصات»: one row per branch or per branch › department, matched by name in the school. Missing
 * branches / departments are created (with their status); existing ones only take the abbreviation given. Writes go
 * through the Organization handlers (names unique, a department under its branch, codes generated).
 */
final class StructureImportProfile implements ImportProfile
{
    public function __construct(
        private readonly BranchStructureRepositoryInterface $structure,
        private readonly CreateBranchHandler $createBranch,
        private readonly CreateDepartmentHandler $createDepartment,
        private readonly UpdateOrganizationAppearanceHandler $appearance,
    ) {}

    public function kind(): string
    {
        return 'structure';
    }

    public function ability(): string
    {
        return 'manageSchools';
    }

    public function needsYear(): bool
    {
        return false;
    }

    public function columns(): array
    {
        return [
            ['key' => 'branch', 'label' => 'الفرع', 'required' => true, 'aliases' => ['branch', 'اسم الفرع'], 'example' => 'الصناعي'],
            ['key' => 'branch_abbreviation', 'label' => 'اختصار الفرع', 'required' => false, 'aliases' => ['branch abbreviation'], 'example' => 'صناعي'],
            ['key' => 'department', 'label' => 'الاختصاص', 'required' => false, 'aliases' => ['department', 'specialization', 'القسم'], 'example' => 'الكهرباء'],
            ['key' => 'department_abbreviation', 'label' => 'اختصار الاختصاص', 'required' => false, 'aliases' => ['department abbreviation'], 'example' => 'كهرباء'],
            ['key' => 'status', 'label' => 'الحالة', 'required' => false, 'aliases' => ['status'], 'example' => 'نشط'],
        ];
    }

    public function plan(ImportContext $context, array $row): array
    {
        $errors = [];
        $branchName = ImportValues::nullable($row['branch'] ?? '');
        $departmentName = ImportValues::nullable($row['department'] ?? '');
        $status = ImportValues::status($row['status'] ?? '');
        if ($branchName === null) {
            $errors[] = 'import.required:branch';
        }
        if ($status === null) {
            $errors[] = 'import.status_invalid';
        }
        foreach (['branch_abbreviation', 'department_abbreviation'] as $key) {
            if (mb_strlen($row[$key] ?? '') > 20) {
                $errors[] = 'appearance.abbreviation_too_long';
            }
        }
        if ($departmentName === null && ($row['department_abbreviation'] ?? '') !== '') {
            $errors[] = 'import.required:department';
        }

        $branch = $branchName === null ? null : $this->branch($context, $branchName);
        $department = $branch !== null && $departmentName !== null ? self::department($branch, $departmentName) : null;
        $creates = $branch === null || ($departmentName !== null && $department === null);
        $touches = ($row['branch_abbreviation'] ?? '') !== '' || ($row['department_abbreviation'] ?? '') !== '';

        return [
            'action' => $creates ? self::CREATE : ($touches ? self::UPDATE : self::SKIP),
            'errors' => $errors,
            'key' => $branchName === null ? null : 'b:'.ImportValues::header($branchName).'|d:'.ImportValues::header((string) $departmentName),
            'entity_id' => $department['id'] ?? $branch['id'] ?? null,
            'data' => [
                'branch' => $branchName,
                'branch_abbreviation' => ImportValues::nullable($row['branch_abbreviation'] ?? ''),
                'department' => $departmentName,
                'department_abbreviation' => ImportValues::nullable($row['department_abbreviation'] ?? ''),
                'status' => $status ?? 1,
            ],
        ];
    }

    public function commit(ImportContext $context, array $data, int $action, ?int $entityId, string $idempotencyKey): array
    {
        $context->forget('structure');
        $branch = $this->branch($context, (string) $data['branch']);
        $branchId = $branch['id'] ?? null;
        if ($branchId === null) {
            $created = $this->createBranch->handle(new CreateBranchCommand($context->schoolId, (string) $data['branch'], null, $idempotencyKey.'-b', $data['department'] === null ? $data['status'] : 1));
            if ($created->failed() || $created->id === null) {
                return ['ok' => false, 'entity_id' => null, 'error' => $created->errors[0] ?? 'import.row_failed'];
            }
            $branchId = $created->id;
            $context->forget('structure');
        }
        if ($data['branch_abbreviation'] !== null) {
            $this->appearance->handle(new UpdateOrganizationAppearanceCommand($context->schoolId, 'branch', $branchId, $data['branch_abbreviation'], $branch['color_hue'] ?? null, $idempotencyKey.'-ba'));
        }
        if ($data['department'] === null) {
            return ['ok' => true, 'entity_id' => $branchId, 'error' => null];
        }

        $department = self::department($this->branch($context, (string) $data['branch']) ?? ['departments' => []], (string) $data['department']);
        $departmentId = $department['id'] ?? null;
        if ($departmentId === null) {
            $created = $this->createDepartment->handle(new CreateDepartmentCommand($context->schoolId, $branchId, (string) $data['department'], null, $idempotencyKey.'-d', $data['status']));
            if ($created->failed() || $created->id === null) {
                return ['ok' => false, 'entity_id' => null, 'error' => $created->errors[0] ?? 'import.row_failed'];
            }
            $departmentId = $created->id;
            $context->forget('structure');
        }
        if ($data['department_abbreviation'] !== null) {
            $this->appearance->handle(new UpdateOrganizationAppearanceCommand($context->schoolId, 'department', $departmentId, $data['department_abbreviation'], $department['color_hue'] ?? null, $idempotencyKey.'-da'));
        }

        return ['ok' => true, 'entity_id' => $departmentId, 'error' => null];
    }

    /** @return array<string, mixed>|null the non-archived branch of that name */
    private function branch(ImportContext $context, string $name): ?array
    {
        $needle = ImportValues::header($name);
        foreach ($context->remember('structure', fn (): array => $this->structure->structure($context->schoolId, activeOnly: false)) as $branch) {
            if ($branch['status'] !== BranchStructureRepositoryInterface::ARCHIVED && ImportValues::header($branch['name']) === $needle) {
                return $branch;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $branch */
    private static function department(array $branch, string $name): ?array
    {
        $needle = ImportValues::header($name);
        foreach ($branch['departments'] ?? [] as $department) {
            if ($department['status'] !== BranchStructureRepositoryInterface::ARCHIVED && ImportValues::header($department['name']) === $needle) {
                return $department;
            }
        }

        return null;
    }
}
