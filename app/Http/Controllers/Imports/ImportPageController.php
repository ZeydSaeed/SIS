<?php

namespace App\Http\Controllers\Imports;

use App\Application\Imports\Commands\CancelImportCommand;
use App\Application\Imports\Commands\CancelImportHandler;
use App\Application\Imports\Commands\CommitImportCommand;
use App\Application\Imports\Commands\CommitImportHandler;
use App\Application\Imports\Commands\UploadImportCommand;
use App\Application\Imports\Commands\UploadImportHandler;
use App\Application\Imports\Queries\BuildImportSheetHandler;
use App\Application\Imports\Queries\BuildImportSheetQuery;
use App\Application\Imports\Queries\GetImportCenterHandler;
use App\Application\Imports\Queries\GetImportCenterQuery;
use App\Application\Imports\Results\ImportResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\ImportRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Models\User;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * «مركز الاستيراد» — Excel / CSV import of teachers, branches & departments, students and subjects:
 * template → upload → (queued) validation → preview with row errors and duplicates → commit → report. A kind is open
 * to the holders of its page's ability; nothing is written to the owning data before «اعتماد».
 */
final class ImportPageController extends Controller
{
    private const KINDS = ['teachers', 'structure', 'students', 'subjects'];

    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, GetImportCenterHandler $handler): Response
    {
        $kinds = $this->allowedKinds($request->user());
        abort_if($kinds === [], 403);
        $status = in_array($request->query('row_status'), ['1', '2', '3', '4', '5'], true) ? (int) $request->query('row_status') : null;

        return Inertia::render('imports/index', $handler->handle(new GetImportCenterQuery(
            schoolId: $this->schoolContext->requireId(),
            allowedKinds: $kinds,
            batchId: is_numeric($request->query('batch')) ? (int) $request->query('batch') : null,
            rowStatus: $status,
            page: max(1, (int) $request->query('page', 1)),
        )) + [
            'filters' => [
                'academic_year_id' => $this->academicYears->resolve($request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null),
                'row_status' => $status,
            ],
        ]);
    }

    public function template(Request $request, string $kind, BuildImportSheetHandler $handler): HttpResponse
    {
        abort_unless(in_array($kind, $this->allowedKinds($request->user()), true), 403);
        $sheet = $handler->handle(new BuildImportSheetQuery($this->schoolContext->requireId(), $kind));
        abort_if($sheet === null, 404);

        return $this->download($sheet);
    }

    public function store(ImportRequest $request, string $kind, UploadImportHandler $handler): RedirectResponse
    {
        abort_unless(in_array($kind, $this->allowedKinds($request->user()), true), 403);
        $file = $request->file('file');
        $year = $request->validated('academic_year_id');
        $result = $handler->handle(new UploadImportCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: $year === null ? $this->academicYears->resolve(null) : (int) $year,
            kind: $kind,
            fileName: (string) $file?->getClientOriginalName(),
            contents: $file === null ? '' : (string) file_get_contents($file->getRealPath()),
            userId: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'imports.web.upload', 'flash.imports.uploaded', ['batch' => $result->id]);
    }

    public function commit(ImportRequest $request, int $batch, CommitImportHandler $handler, GetImportCenterHandler $center): RedirectResponse
    {
        $this->authorizeBatch($request, $batch, $center);

        return $this->respond($request, $handler->handle(new CommitImportCommand($this->schoolContext->requireId(), $batch, $request->user()?->id, $request->header('X-Idempotency-Key'))),
            'imports.web.commit', 'flash.imports.committing', ['batch' => $batch]);
    }

    public function cancel(ImportRequest $request, int $batch, CancelImportHandler $handler, GetImportCenterHandler $center): RedirectResponse
    {
        $this->authorizeBatch($request, $batch, $center);

        return $this->respond($request, $handler->handle(new CancelImportCommand($this->schoolContext->requireId(), $batch, $request->header('X-Idempotency-Key'))),
            'imports.web.cancel', 'flash.imports.cancelled', ['batch' => $batch]);
    }

    public function errors(Request $request, int $batch, GetImportCenterHandler $center, BuildImportSheetHandler $sheets): HttpResponse
    {
        $found = $this->authorizeBatch($request, $batch, $center);
        $sheet = $sheets->handle(new BuildImportSheetQuery($this->schoolContext->requireId(), (string) $found['kind'], $batch));
        abort_if($sheet === null, 404);

        return $this->download($sheet);
    }

    /** @return list<string> the kinds this user may import (the ability of each owning page) */
    private function allowedKinds(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_values(array_filter(self::KINDS, static fn (string $kind): bool => match ($kind) {
            'teachers' => $user->can('manageTeachers'),
            'structure' => $user->can('manageSchools'),
            'subjects' => $user->can('manageCurriculum'),
            'students' => $user->can('create', StudentRecord::class),
            default => false,
        }));
    }

    /** @return array<string, mixed> the batch, when it is the school's and of a kind the user may import */
    private function authorizeBatch(Request $request, int $batchId, GetImportCenterHandler $center): array
    {
        $kinds = $this->allowedKinds($request->user());
        abort_if($kinds === [], 403);
        $batch = $center->handle(new GetImportCenterQuery($this->schoolContext->requireId(), $kinds, $batchId))['batch'];
        abort_if($batch === null, 404);

        return $batch;
    }

    /** @param  array{file_name: string, contents: string}  $sheet */
    private function download(array $sheet): HttpResponse
    {
        return response($sheet['contents'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$sheet['file_name'].'"',
        ]);
    }

    /** @param  array<string, int|null>  $query */
    private function respond(Request $request, ImportResult $result, string $action, string $flash, array $query): RedirectResponse
    {
        if ($result->failed()) {
            return redirect()->back()->withErrors(['import' => $result->errors[0] ?? 'import.failed']);
        }
        $this->securityAudit->record(SecurityEventType::DocumentsDataModified, $action, 'success', $request->user(), 'import:'.$result->id);

        return redirect()->route('imports.index', array_filter($query))->with('success', $flash);
    }
}
