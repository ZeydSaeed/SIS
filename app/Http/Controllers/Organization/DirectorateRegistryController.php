<?php

namespace App\Http\Controllers\Organization;

use App\Application\Organization\Commands\ChangeDirectorateStatusCommand;
use App\Application\Organization\Commands\ChangeDirectorateStatusHandler;
use App\Application\Organization\Commands\CreateDirectorateCommand;
use App\Application\Organization\Commands\CreateDirectorateHandler;
use App\Application\Organization\Commands\UpdateDirectorateCommand;
use App\Application\Organization\Commands\UpdateDirectorateHandler;
use App\Domain\Organization\ValueObjects\DirectorateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ChangeDirectorateStatusRequest;
use App\Http\Requests\Organization\CreateDirectorateRequest;
use App\Http\Requests\Organization\UpdateDirectorateRequest;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Authorization\SchoolScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DirectorateRegistryController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolScopeService $schoolScope,
    ) {}

    public function store(CreateDirectorateRequest $request, CreateDirectorateHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new CreateDirectorateCommand(
            name: trim((string) $request->validated('name')),
            region: $this->nullableText($request->validated('region')),
            schoolIds: $this->schoolIds($request->validated('school_ids', [])),
            allowedSchoolIds: $this->allowedSchoolIds($request),
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'directorate' => $result->errors[0] ?? 'organization.directorate_create_failed',
            ]);
        }

        $this->audit($request, 'organization.web.directorates.store', 'created', (int) $result->directorateId);

        return redirect()->back()->with('success', 'flash.organization.directorateCreated');
    }

    public function update(int $directorate, UpdateDirectorateRequest $request, UpdateDirectorateHandler $handler): RedirectResponse
    {
        $fields = [];
        if ($request->exists('name')) {
            $fields['name'] = trim((string) $request->validated('name'));
        }
        if ($request->exists('region')) {
            $fields['region'] = $this->nullableText($request->validated('region'));
        }

        $result = $handler->handle(new UpdateDirectorateCommand(
            directorateId: $directorate,
            fields: $fields,
            schoolIds: $request->exists('school_ids')
                ? $this->schoolIds($request->validated('school_ids', []))
                : null,
            allowedSchoolIds: $this->allowedSchoolIds($request),
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'directorate' => $result->errors[0] ?? 'organization.directorate_update_failed',
            ]);
        }

        $this->audit($request, 'organization.web.directorates.update', 'updated', $directorate);

        return redirect()->back()->with('success', 'flash.organization.directorateUpdated');
    }

    public function deactivate(int $directorate, ChangeDirectorateStatusRequest $request, ChangeDirectorateStatusHandler $handler): RedirectResponse
    {
        return $this->changeStatus($directorate, DirectorateStatus::Inactive, $request, $handler);
    }

    public function reactivate(int $directorate, ChangeDirectorateStatusRequest $request, ChangeDirectorateStatusHandler $handler): RedirectResponse
    {
        return $this->changeStatus($directorate, DirectorateStatus::Active, $request, $handler);
    }

    private function changeStatus(
        int $directorate,
        DirectorateStatus $status,
        ChangeDirectorateStatusRequest $request,
        ChangeDirectorateStatusHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new ChangeDirectorateStatusCommand(
            directorateId: $directorate,
            status: $status->value,
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'directorate' => $result->errors[0] ?? 'organization.directorate_update_failed',
            ]);
        }

        $deactivated = $status === DirectorateStatus::Inactive;
        $this->audit(
            $request,
            $deactivated ? 'organization.web.directorates.deactivate' : 'organization.web.directorates.reactivate',
            $deactivated ? 'deactivated' : 'reactivated',
            $directorate,
        );

        return redirect()->back()->with(
            'success',
            $deactivated ? 'flash.organization.directorateDeactivated' : 'flash.organization.directorateReactivated',
        );
    }

    /** @return list<int> */
    private function allowedSchoolIds(Request $request): array
    {
        $user = $request->user();
        assert($user !== null);

        return $this->schoolScope->allowedSchoolIds($user);
    }

    /** @return list<int> */
    private function schoolIds(mixed $value): array
    {
        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            is_array($value) ? $value : [],
        ));
    }

    private function audit(Request $request, string $action, string $outcome, int $directorateId): void
    {
        $this->securityAudit->record(
            SecurityEventType::OrganizationDataModified,
            $action,
            $outcome,
            $request->user(),
            'directorate:'.$directorateId,
        );
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
