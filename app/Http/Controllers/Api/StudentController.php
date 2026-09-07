<?php

namespace App\Http\Controllers\Api;

use App\Application\Student\Commands\CreateStudentCommand;
use App\Application\Student\Commands\CreateStudentHandler;
use App\Application\Student\Commands\UpdateStudentCommand;
use App\Application\Student\Commands\UpdateStudentHandler;
use App\Application\Student\Queries\GetStudentHandler;
use App\Application\Student\Queries\GetStudentQuery;
use App\Application\Student\Queries\ListStudentsHandler;
use App\Application\Student\Queries\ListStudentsQuery;
use App\Application\Student\Queries\SearchStudentsHandler;
use App\Application\Student\Queries\SearchStudentsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CreateStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Intelligence\Support\CorrelationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request, ListStudentsHandler $handler): JsonResponse
    {
        $status = $request->filled('status') ? (int) $request->query('status') : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);

        $result = $handler->handle(new ListStudentsQuery(
            status: $status,
            page: $page,
            perPage: $perPage,
        ));

        return response()->json($result->toArray());
    }

    public function search(Request $request, SearchStudentsHandler $handler): JsonResponse
    {
        $term = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);

        $result = $handler->handle(new SearchStudentsQuery(
            term: $term,
            page: $page,
            perPage: $perPage,
        ));

        return response()->json($result->toArray());
    }

    public function show(int $student, GetStudentHandler $handler): JsonResponse
    {
        $detail = $handler->handle(new GetStudentQuery($student));

        return response()->json(['data' => $detail->toArray()]);
    }

    public function store(
        CreateStudentRequest $request,
        CreateStudentHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CreateStudentCommand(
            firstName: $request->validated('first_name'),
            middleName: $request->validated('middle_name'),
            lastName: $request->validated('last_name'),
            gender: (int) $request->validated('gender'),
            birthDate: $request->validated('birth_date'),
            studentCode: $request->validated('student_code'),
            nationalId: $request->validated('national_id'),
            birthPlace: $request->validated('birth_place'),
            nationality: $request->validated('nationality'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        return response()->json([
            'data' => [
                'id' => $result->studentId,
                'student_code' => $result->studentCode,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], 201);
    }

    public function update(
        UpdateStudentRequest $request,
        int $student,
        UpdateStudentHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new UpdateStudentCommand(
            studentId: $student,
            firstName: $request->validated('first_name'),
            middleName: $request->validated('middle_name'),
            lastName: $request->validated('last_name'),
            gender: (int) $request->validated('gender'),
            birthDate: $request->validated('birth_date'),
            nationalId: $request->validated('national_id'),
            birthPlace: $request->validated('birth_place'),
            nationality: $request->validated('nationality'),
        ));

        return response()->json([
            'data' => [
                'id' => $result->studentId,
            ],
            'meta' => [
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }
}
