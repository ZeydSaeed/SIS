<?php

namespace App\Http\Controllers\Api;

use App\Application\Hr\Commands\CreateJobPositionCommand;
use App\Application\Hr\Commands\CreateJobPositionHandler;
use App\Application\Hr\Commands\DeactivateEmployeeCommand;
use App\Application\Hr\Commands\DeactivateEmployeeHandler;
use App\Application\Hr\Commands\DeactivateJobPositionCommand;
use App\Application\Hr\Commands\DeactivateJobPositionHandler;
use App\Application\Hr\Commands\ReactivateEmployeeCommand;
use App\Application\Hr\Commands\ReactivateEmployeeHandler;
use App\Application\Hr\Commands\ReactivateJobPositionCommand;
use App\Application\Hr\Commands\ReactivateJobPositionHandler;
use App\Application\Hr\Commands\RegisterEmployeeCommand;
use App\Application\Hr\Commands\RegisterEmployeeHandler;
use App\Application\Hr\DTOs\EmployeeDTO;
use App\Application\Hr\DTOs\JobPositionDTO;
use App\Application\Hr\Queries\GetEmployeeHandler;
use App\Application\Hr\Queries\GetEmployeeQuery;
use App\Application\Hr\Queries\GetJobPositionHandler;
use App\Application\Hr\Queries\GetJobPositionQuery;
use App\Application\Hr\Queries\ListEmployeesHandler;
use App\Application\Hr\Queries\ListEmployeesQuery;
use App\Application\Hr\Queries\ListJobPositionsHandler;
use App\Application\Hr\Queries\ListJobPositionsQuery;
use App\Domain\Hr\ValueObjects\EmployeeStatus;
use App\Domain\Hr\ValueObjects\JobPositionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\CreateJobPositionRequest;
use App\Http\Requests\Hr\DeactivateEmployeeRequest;
use App\Http\Requests\Hr\DeactivateJobPositionRequest;
use App\Http\Requests\Hr\ListEmployeesRequest;
use App\Http\Requests\Hr\ListJobPositionsRequest;
use App\Http\Requests\Hr\ShowEmployeeRequest;
use App\Http\Requests\Hr\ShowJobPositionRequest;
use App\Http\Requests\Hr\ReactivateEmployeeRequest;
use App\Http\Requests\Hr\ReactivateJobPositionRequest;
use App\Http\Requests\Hr\RegisterEmployeeRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class HrController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function storePosition(
        CreateJobPositionRequest $request,
        CreateJobPositionHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CreateJobPositionCommand(
            schoolId: $this->schoolContext->requireId(),
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            category: (int) $request->validated('category'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Job position create rejected.',
                'error_code' => $result->errors[0] ?? 'hr.job_position_create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::HrDataModified,
            'hr.job_positions.store',
            'created',
            $request->user(),
            'job_position:'.$result->jobPositionId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'job_position_id' => $result->jobPositionId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexPositions(
        ListJobPositionsRequest $request,
        ListJobPositionsHandler $handler,
    ): JsonResponse {
        $status = $request->validated('status');
        $items = $handler->handle(new ListJobPositionsQuery(
            schoolId: $this->schoolContext->requireId(),
            status: $status !== null ? (int) $status : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::HrDataAccess,
            'hr.job_positions.index',
            'listed',
            $request->user(),
            'school:'.$this->schoolContext->requireId(),
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (JobPositionDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'code' => $dto->code,
                'name' => $dto->name,
                'category' => $dto->category,
                'status' => $dto->status,
                'created_at' => $dto->createdAt,
                'updated_at' => $dto->updatedAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function showPosition(
        int $jobPosition,
        ShowJobPositionRequest $request,
        GetJobPositionHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetJobPositionQuery(
            schoolId: $schoolId,
            jobPositionId: $jobPosition,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Job position not found.',
                'error_code' => 'hr.job_position_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::HrDataAccess,
            'hr.job_positions.show',
            'viewed',
            $request->user(),
            'job_position:'.$jobPosition,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'code' => $dto->code,
                'name' => $dto->name,
                'category' => $dto->category,
                'status' => $dto->status,
                'created_at' => $dto->createdAt,
                'updated_at' => $dto->updatedAt,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storeEmployee(
        RegisterEmployeeRequest $request,
        RegisterEmployeeHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new RegisterEmployeeCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            employeeNumber: (string) $request->validated('employee_number'),
            firstName: (string) $request->validated('first_name'),
            lastName: (string) $request->validated('last_name'),
            nationalId: $request->validated('national_id'),
            hireDate: $request->validated('hire_date'),
            jobPositionId: $request->validated('job_position_id') !== null
                ? (int) $request->validated('job_position_id')
                : null,
            teacherId: $request->validated('teacher_id') !== null
                ? (int) $request->validated('teacher_id')
                : null,
            userId: null,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Employee register rejected.',
                'error_code' => $result->errors[0] ?? 'hr.employee_register_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::HrDataModified,
            'hr.employees.register',
            'registered',
            $request->user(),
            'employee:'.$result->employeeId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'employee_id' => $result->employeeId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexEmployees(
        ListEmployeesRequest $request,
        ListEmployeesHandler $handler,
    ): JsonResponse {
        $yearId = $request->validated('academic_year_id');
        $status = $request->validated('status');
        $schoolId = $this->schoolContext->requireId();

        $items = $handler->handle(new ListEmployeesQuery(
            schoolId: $schoolId,
            academicYearId: $yearId !== null ? (int) $yearId : null,
            status: $status !== null ? (int) $status : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::HrDataAccess,
            'hr.employees.index',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (EmployeeDTO $dto): array => [
                'id' => $dto->id,
                'employee_number' => $dto->employeeNumber,
                'user_id' => $dto->userId,
                'teacher_id' => $dto->teacherId,
                'national_id' => $dto->nationalId,
                'first_name' => $dto->firstName,
                'last_name' => $dto->lastName,
                'full_name' => $dto->fullName,
                'hire_date' => $dto->hireDate,
                'status' => $dto->status,
                'job_position_id' => $dto->jobPositionId,
                'academic_year_id' => $dto->academicYearId,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function showEmployee(
        int $employee,
        ShowEmployeeRequest $request,
        GetEmployeeHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetEmployeeQuery(
            schoolId: $schoolId,
            employeeId: $employee,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Employee not found.',
                'error_code' => 'hr.employee_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::HrDataAccess,
            'hr.employees.show',
            'viewed',
            $request->user(),
            'employee:'.$employee,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'employee_number' => $dto->employeeNumber,
                'user_id' => $dto->userId,
                'teacher_id' => $dto->teacherId,
                'national_id' => $dto->nationalId,
                'first_name' => $dto->firstName,
                'last_name' => $dto->lastName,
                'full_name' => $dto->fullName,
                'hire_date' => $dto->hireDate,
                'status' => $dto->status,
                'job_position_id' => $dto->jobPositionId,
                'academic_year_id' => $dto->academicYearId,
                'created_at' => $dto->createdAt,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivatePosition(
        int $jobPosition,
        DeactivateJobPositionRequest $request,
        DeactivateJobPositionHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateJobPositionCommand(
            schoolId: $this->schoolContext->requireId(),
            jobPositionId: $jobPosition,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'hr.job_position_deactivate_failed';

            return response()->json([
                'message' => 'Job position deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'hr.job_position_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::HrDataModified,
            'hr.job_positions.deactivate',
            'deactivated',
            $request->user(),
            'job_position:'.$jobPosition,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'job_position_id' => $result->jobPositionId,
                'status' => JobPositionStatus::Inactive,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivateEmployee(
        int $employee,
        DeactivateEmployeeRequest $request,
        DeactivateEmployeeHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateEmployeeCommand(
            schoolId: $this->schoolContext->requireId(),
            employeeId: $employee,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'hr.employee_deactivate_failed';

            return response()->json([
                'message' => 'Employee deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'hr.employee_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::HrDataModified,
            'hr.employees.deactivate',
            'deactivated',
            $request->user(),
            'employee:'.$employee,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'employee_id' => $result->employeeId,
                'status' => EmployeeStatus::Inactive,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivatePosition(
        int $jobPosition,
        ReactivateJobPositionRequest $request,
        ReactivateJobPositionHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReactivateJobPositionCommand(
            schoolId: $this->schoolContext->requireId(),
            jobPositionId: $jobPosition,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'hr.job_position_reactivate_failed';

            return response()->json([
                'message' => 'Job position reactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'hr.job_position_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::HrDataModified,
            'hr.job_positions.reactivate',
            'reactivated',
            $request->user(),
            'job_position:'.$jobPosition,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'job_position_id' => $result->jobPositionId,
                'status' => JobPositionStatus::Active,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivateEmployee(
        int $employee,
        ReactivateEmployeeRequest $request,
        ReactivateEmployeeHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReactivateEmployeeCommand(
            schoolId: $this->schoolContext->requireId(),
            employeeId: $employee,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'hr.employee_reactivate_failed';

            return response()->json([
                'message' => 'Employee reactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'hr.employee_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::HrDataModified,
            'hr.employees.reactivate',
            'reactivated',
            $request->user(),
            'employee:'.$employee,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'employee_id' => $result->employeeId,
                'status' => EmployeeStatus::Active,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
