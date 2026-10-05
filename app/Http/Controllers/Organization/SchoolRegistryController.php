<?php

namespace App\Http\Controllers\Organization;

use App\Application\Organization\Commands\ChangeSchoolStatusCommand;
use App\Application\Organization\Commands\ChangeSchoolStatusHandler;
use App\Application\Organization\Commands\CreateSchoolCommand;
use App\Application\Organization\Commands\CreateSchoolHandler;
use App\Application\Organization\Commands\UpdateSchoolCommand;
use App\Application\Organization\Commands\UpdateSchoolHandler;
use App\Domain\Organization\ValueObjects\SchoolStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ChangeSchoolStatusRequest;
use App\Http\Requests\Organization\CreateSchoolRequest;
use App\Http\Requests\Organization\UpdateSchoolRequest;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;

final class SchoolRegistryController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function store(CreateSchoolRequest $request, CreateSchoolHandler $handler): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $result = $handler->handle(new CreateSchoolCommand(
            createdByUserId: (int) $user->id,
            sourceSchoolId: $this->schoolContext->requireId(),
            directorateId: (int) $request->validated('directorate_id'),
            name: (string) $request->validated('name'),
            address: $this->nullableText($request->validated('address')),
            phone: $this->nullableText($request->validated('phone')),
            email: $this->nullableText($request->validated('email')),
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'school' => $result->errors[0] ?? 'organization.school_create_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::OrganizationDataModified,
            'organization.web.schools.store',
            'created',
            $user,
            'school:'.$result->schoolId,
        );

        return redirect()->back()->with('success', 'flash.organization.schoolCreated');
    }

    public function update(int $school, UpdateSchoolRequest $request, UpdateSchoolHandler $handler): RedirectResponse
    {
        $fields = [];
        foreach (['name', 'directorate_id', 'address', 'phone', 'email'] as $key) {
            if (! $request->exists($key)) {
                continue;
            }
            $value = $request->validated($key);
            $fields[$key] = match ($key) {
                'directorate_id' => (int) $value,
                'name' => trim((string) $value),
                default => $this->nullableText($value),
            };
        }

        $result = $handler->handle(new UpdateSchoolCommand(
            schoolId: $school,
            fields: $fields,
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'school' => $result->errors[0] ?? 'organization.school_update_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::OrganizationDataModified,
            'organization.web.schools.update',
            'updated',
            $request->user(),
            'school:'.$school,
        );

        return redirect()->back()->with('success', 'flash.organization.schoolUpdated');
    }

    public function deactivate(int $school, ChangeSchoolStatusRequest $request, ChangeSchoolStatusHandler $handler): RedirectResponse
    {
        return $this->changeStatus($school, SchoolStatus::Inactive, $request, $handler);
    }

    public function reactivate(int $school, ChangeSchoolStatusRequest $request, ChangeSchoolStatusHandler $handler): RedirectResponse
    {
        return $this->changeStatus($school, SchoolStatus::Active, $request, $handler);
    }

    private function changeStatus(
        int $school,
        SchoolStatus $status,
        ChangeSchoolStatusRequest $request,
        ChangeSchoolStatusHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new ChangeSchoolStatusCommand(
            schoolId: $school,
            status: $status->value,
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'school' => $result->errors[0] ?? 'organization.school_update_failed',
            ]);
        }

        $deactivated = $status === SchoolStatus::Inactive;
        $this->securityAudit->record(
            SecurityEventType::OrganizationDataModified,
            $deactivated ? 'organization.web.schools.deactivate' : 'organization.web.schools.reactivate',
            $deactivated ? 'deactivated' : 'reactivated',
            $request->user(),
            'school:'.$school,
        );

        return redirect()->back()->with(
            'success',
            $deactivated ? 'flash.organization.schoolDeactivated' : 'flash.organization.schoolReactivated',
        );
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
