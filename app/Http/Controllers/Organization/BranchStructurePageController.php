<?php

namespace App\Http\Controllers\Organization;

use App\Application\Organization\Commands\CreateBranchCommand;
use App\Application\Organization\Commands\CreateBranchHandler;
use App\Application\Organization\Commands\CreateDepartmentCommand;
use App\Application\Organization\Commands\CreateDepartmentHandler;
use App\Application\Organization\Commands\DeleteBranchCommand;
use App\Application\Organization\Commands\DeleteBranchHandler;
use App\Application\Organization\Commands\DeleteDepartmentsCommand;
use App\Application\Organization\Commands\DeleteDepartmentsHandler;
use App\Application\Organization\Commands\UpdateBranchCommand;
use App\Application\Organization\Commands\UpdateBranchHandler;
use App\Application\Organization\Commands\UpdateDepartmentCommand;
use App\Application\Organization\Commands\UpdateDepartmentHandler;
use App\Application\Organization\Queries\GetBranchStructureHandler;
use App\Application\Organization\Queries\GetBranchStructureQuery;
use App\Application\Organization\Results\BranchStructureResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\DeleteDepartmentsRequest;
use App\Http\Requests\Organization\ManageBranchStructureRequest;
use App\Http\Requests\Organization\SaveBranchRequest;
use App\Http\Requests\Organization\SaveDepartmentRequest;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «الفروع والاختصاصات»: the current school's branches and their departments. */
final class BranchStructurePageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function index(Request $request, GetBranchStructureHandler $handler): Response
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->can('manageSchools') || $user->can('viewAdmission')), 403);

        return Inertia::render('organization/branches', [
            'branches' => $handler->handle(new GetBranchStructureQuery($this->schoolContext->requireId())),
            'authorization' => ['can_manage' => $user->can('manageSchools')],
        ]);
    }

    public function storeBranch(SaveBranchRequest $request, CreateBranchHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'branch', 'organization.web.branches.store', 'flash.organization.branchCreated', $handler->handle(
            new CreateBranchCommand(
                schoolId: $this->schoolContext->requireId(),
                name: (string) $request->validated('name'),
                description: $this->nullableText($request->validated('description')),
                idempotencyKey: (string) $request->header('X-Idempotency-Key'),
            ),
        ));
    }

    public function updateBranch(SaveBranchRequest $request, int $branch, UpdateBranchHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'branch', 'organization.web.branches.update', 'flash.organization.branchUpdated', $handler->handle(
            new UpdateBranchCommand(
                schoolId: $this->schoolContext->requireId(),
                branchId: $branch,
                name: (string) $request->validated('name'),
                description: $this->nullableText($request->validated('description')),
                idempotencyKey: (string) $request->header('X-Idempotency-Key'),
            ),
        ));
    }

    public function destroyBranch(ManageBranchStructureRequest $request, int $branch, DeleteBranchHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'branch', 'organization.web.branches.delete', 'flash.organization.branchDeleted', $handler->handle(
            new DeleteBranchCommand(
                schoolId: $this->schoolContext->requireId(),
                branchId: $branch,
                idempotencyKey: (string) $request->header('X-Idempotency-Key'),
            ),
        ));
    }

    public function storeDepartment(SaveDepartmentRequest $request, CreateDepartmentHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'department', 'organization.web.departments.store', 'flash.organization.departmentCreated', $handler->handle(
            new CreateDepartmentCommand(
                schoolId: $this->schoolContext->requireId(),
                branchId: (int) $request->validated('branch_id'),
                name: (string) $request->validated('name'),
                description: $this->nullableText($request->validated('description')),
                idempotencyKey: (string) $request->header('X-Idempotency-Key'),
            ),
        ));
    }

    public function updateDepartment(SaveDepartmentRequest $request, int $department, UpdateDepartmentHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'department', 'organization.web.departments.update', 'flash.organization.departmentUpdated', $handler->handle(
            new UpdateDepartmentCommand(
                schoolId: $this->schoolContext->requireId(),
                departmentId: $department,
                branchId: (int) $request->validated('branch_id'),
                name: (string) $request->validated('name'),
                description: $this->nullableText($request->validated('description')),
                idempotencyKey: (string) $request->header('X-Idempotency-Key'),
            ),
        ));
    }

    public function destroyDepartments(DeleteDepartmentsRequest $request, DeleteDepartmentsHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'department', 'organization.web.departments.delete', 'flash.organization.departmentsDeleted', $handler->handle(
            new DeleteDepartmentsCommand(
                schoolId: $this->schoolContext->requireId(),
                departmentIds: array_map('intval', (array) $request->validated('department_ids')),
                idempotencyKey: (string) $request->header('X-Idempotency-Key'),
            ),
        ));
    }

    private function respond(Request $request, string $errorKey, string $auditAction, string $flash, BranchStructureResult $result): RedirectResponse
    {
        if ($result->failed()) {
            return redirect()->back()->withErrors([$errorKey => $result->errors[0] ?? 'organization.structure_failed']);
        }

        $user = $request->user();
        assert($user !== null);
        $this->securityAudit->record(
            SecurityEventType::OrganizationDataModified,
            $auditAction,
            'success',
            $user,
            $errorKey.':'.$result->id,
        );

        return redirect()->back()->with('success', $flash);
    }

    private function nullableText(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }
}
