<?php

namespace App\Http\Controllers\Timetable;

use App\Application\Timetable\Commands\ApplyTimetableGenerationCommand;
use App\Application\Timetable\Commands\ApplyTimetableGenerationHandler;
use App\Application\Timetable\Commands\ArchiveTimetableVersionCommand;
use App\Application\Timetable\Commands\ArchiveTimetableVersionHandler;
use App\Application\Timetable\Commands\CancelTimetableGenerationCommand;
use App\Application\Timetable\Commands\CancelTimetableGenerationHandler;
use App\Application\Timetable\Commands\ChangeTimetablePlaceStatusCommand;
use App\Application\Timetable\Commands\ChangeTimetablePlaceStatusHandler;
use App\Application\Timetable\Commands\CreateTimetableActivityCommand;
use App\Application\Timetable\Commands\CreateTimetableActivityHandler;
use App\Application\Timetable\Commands\CreateTimetableVersionCommand;
use App\Application\Timetable\Commands\CreateTimetableVersionHandler;
use App\Application\Timetable\Commands\DecideTimetableVersionCommand;
use App\Application\Timetable\Commands\DecideTimetableVersionHandler;
use App\Application\Timetable\Commands\DiscardTimetableGenerationCommand;
use App\Application\Timetable\Commands\DiscardTimetableGenerationHandler;
use App\Application\Timetable\Commands\EndTimetableActivityCommand;
use App\Application\Timetable\Commands\EndTimetableActivityHandler;
use App\Application\Timetable\Commands\EndTimetableDivisionCommand;
use App\Application\Timetable\Commands\EndTimetableDivisionHandler;
use App\Application\Timetable\Commands\EndTimetableRuleCommand;
use App\Application\Timetable\Commands\EndTimetableRuleHandler;
use App\Application\Timetable\Commands\LockSchedulesCommand;
use App\Application\Timetable\Commands\LockSchedulesHandler;
use App\Application\Timetable\Commands\PublishTimetableVersionCommand;
use App\Application\Timetable\Commands\PublishTimetableVersionHandler;
use App\Application\Timetable\Commands\QueueTimetableGenerationCommand;
use App\Application\Timetable\Commands\QueueTimetableGenerationHandler;
use App\Application\Timetable\Commands\RestoreTimetableVersionCommand;
use App\Application\Timetable\Commands\RestoreTimetableVersionHandler;
use App\Application\Timetable\Commands\SaveTimetableRoomCommand;
use App\Application\Timetable\Commands\SaveTimetableRoomHandler;
use App\Application\Timetable\Commands\SaveTimetableRuleCommand;
use App\Application\Timetable\Commands\SaveTimetableRuleHandler;
use App\Application\Timetable\Commands\SaveTimetableSettingsCommand;
use App\Application\Timetable\Commands\SaveTimetableWorkshopCommand;
use App\Application\Timetable\Commands\SaveTimetableWorkshopHandler;
use App\Application\Timetable\Commands\SaveTimetableSettingsHandler;
use App\Application\Timetable\Commands\SetTimetableAvailabilityCommand;
use App\Application\Timetable\Commands\SetTimetableAvailabilityHandler;
use App\Application\Timetable\Commands\SplitSectionIntoGroupsCommand;
use App\Application\Timetable\Commands\SplitSectionIntoGroupsHandler;
use App\Application\Timetable\Commands\SubmitTimetableVersionCommand;
use App\Application\Timetable\Commands\SubmitTimetableVersionHandler;
use App\Application\Timetable\Commands\SyncTimetableActivitiesCommand;
use App\Application\Timetable\Commands\SyncTimetableActivitiesHandler;
use App\Application\Timetable\Commands\UpdateTimetableActivityCommand;
use App\Application\Timetable\Commands\UpdateTimetableActivityHandler;
use App\Application\Timetable\Results\TimetableEngineResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\DecideTimetableVersionRequest;
use App\Http\Requests\Timetable\GenerateTimetableRequest;
use App\Http\Requests\Timetable\LockSchedulesRequest;
use App\Http\Requests\Timetable\ManageTimetableEngineRequest;
use App\Http\Requests\Timetable\PublishTimetableRequest;
use App\Http\Requests\Timetable\TimetableEngineRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;

/**
 * «الجدول الدراسي» engine writes: configuration, generation runs, locks, versions. Each action authorizes in its
 * FormRequest (policy + school access), delegates to one handler, audits, and returns to the page.
 */
final class TimetableEngineController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    // ── Places: rooms and workshops («الأماكن») ──────────────────────────────

    public function storeRoom(ManageTimetableEngineRequest $request, SaveTimetableRoomHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new SaveTimetableRoomCommand(
            $this->school(), null, (int) $v['branch_id'], (string) $v['code'], (string) $v['name'], self::int($v['capacity'] ?? null),
            (int) $v['room_type'], $request->user()?->id, $request->idempotencyKey(),
        )), 'places.rooms.store', 'flash.timetable.engine.placeSaved');
    }

    public function updateRoom(int $room, ManageTimetableEngineRequest $request, SaveTimetableRoomHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new SaveTimetableRoomCommand(
            $this->school(), $room, 0, '', (string) $v['name'], self::int($v['capacity'] ?? null),
            (int) $v['room_type'], $request->user()?->id, $request->idempotencyKey(),
        )), 'places.rooms.update', 'flash.timetable.engine.placeSaved');
    }

    public function storeWorkshop(ManageTimetableEngineRequest $request, SaveTimetableWorkshopHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new SaveTimetableWorkshopCommand(
            $this->school(), null, (string) $v['code'], (string) $v['name'], (int) $v['capacity'], (int) $v['safety_capacity'],
            self::int($v['room_id'] ?? null), $request->user()?->id, $request->idempotencyKey(),
        )), 'places.workshops.store', 'flash.timetable.engine.placeSaved');
    }

    public function updateWorkshop(int $workshop, ManageTimetableEngineRequest $request, SaveTimetableWorkshopHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new SaveTimetableWorkshopCommand(
            $this->school(), $workshop, '', (string) $v['name'], (int) $v['capacity'], (int) $v['safety_capacity'],
            self::int($v['room_id'] ?? null), $request->user()?->id, $request->idempotencyKey(),
        )), 'places.workshops.update', 'flash.timetable.engine.placeSaved');
    }

    public function changePlaceStatus(ManageTimetableEngineRequest $request, ChangeTimetablePlaceStatusHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new ChangeTimetablePlaceStatusCommand(
            $this->school(), (string) $v['kind'], (int) $v['id'], (int) $v['active'] === 1 ? 1 : 2, $request->user()?->id, $request->idempotencyKey(),
        )), 'places.status', (int) $v['active'] === 1 ? 'flash.timetable.engine.placeEnabled' : 'flash.timetable.engine.placeDisabled');
    }

    // ── Configuration ────────────────────────────────────────────────────────

    public function saveSettings(ManageTimetableEngineRequest $request, SaveTimetableSettingsHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new SaveTimetableSettingsCommand(
            $this->school(), (int) $v['academic_year_id'], array_map('intval', $v['working_days']), (int) $v['cycle_weeks'],
            (int) $v['max_teacher_per_day'], (int) $v['max_subject_per_day'], (int) $v['double_changeover_minutes'],
            array_map('intval', $v['weights'] ?? []), $request->user()?->id, $request->idempotencyKey(),
        )), 'settings.save', 'flash.timetable.engine.saved');
    }

    public function storeActivity(ManageTimetableEngineRequest $request, CreateTimetableActivityHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new CreateTimetableActivityCommand(
            $this->school(), (int) $v['academic_year_id'], (int) $v['subject_id'], (int) $v['activity_type'], (int) $v['weekly_count'],
            (int) $v['block_length'], $v['distribution'] ?? null, self::int($v['room_id'] ?? null), self::int($v['room_type'] ?? null),
            self::int($v['workshop_id'] ?? null), (int) $v['week_pattern'], self::int($v['term_id'] ?? null), $v['note'] ?? null,
            array_map(static fn (array $t): array => ['section_id' => (int) $t['section_id'], 'group_id' => self::int($t['group_id'] ?? null)], $v['targets']),
            array_map(static fn (array $t): array => ['teacher_id' => (int) $t['teacher_id'], 'role' => (int) $t['role'], 'sessions' => self::int($t['sessions'] ?? null)], $v['teachers']),
            $request->user()?->id, $request->idempotencyKey(),
        )), 'activities.store', 'flash.timetable.engine.activitySaved');
    }

    public function updateActivity(int $activity, ManageTimetableEngineRequest $request, UpdateTimetableActivityHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new UpdateTimetableActivityCommand(
            $this->school(), $activity, (int) $v['activity_type'], (int) $v['weekly_count'], (int) $v['block_length'],
            $v['distribution'] ?? null, self::int($v['room_id'] ?? null), self::int($v['room_type'] ?? null), self::int($v['workshop_id'] ?? null),
            (int) $v['week_pattern'], $v['note'] ?? null, $request->user()?->id, $request->idempotencyKey(),
        )), 'activities.update', 'flash.timetable.engine.activitySaved');
    }

    public function endActivity(int $activity, ManageTimetableEngineRequest $request, EndTimetableActivityHandler $handler): RedirectResponse
    {
        return $this->respond($request, $handler->handle(new EndTimetableActivityCommand($this->school(), $activity, $request->user()?->id, $request->idempotencyKey())),
            'activities.end', 'flash.timetable.engine.activityEnded');
    }

    public function syncActivities(ManageTimetableEngineRequest $request, SyncTimetableActivitiesHandler $handler): RedirectResponse
    {
        $sections = $request->validated('section_ids');

        return $this->respond($request, $handler->handle(new SyncTimetableActivitiesCommand(
            $this->school(), (int) $request->validated('academic_year_id'), $sections === null ? null : array_map('intval', $sections),
            $request->user()?->id, $request->idempotencyKey(),
        )), 'activities.sync', 'flash.timetable.engine.activitiesSynced');
    }

    public function storeDivision(ManageTimetableEngineRequest $request, SplitSectionIntoGroupsHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new SplitSectionIntoGroupsCommand(
            $this->school(), (int) $v['academic_year_id'], (int) $v['section_id'], (string) $v['name'], self::int($v['group_count'] ?? null),
            self::int($v['capacity'] ?? null), array_values(array_map('strval', array_filter($v['group_names'] ?? [], static fn ($n): bool => $n !== null))),
            $request->user()?->id, $request->idempotencyKey(),
        )), 'divisions.store', 'flash.timetable.engine.divisionSaved');
    }

    public function endDivision(int $division, ManageTimetableEngineRequest $request, EndTimetableDivisionHandler $handler): RedirectResponse
    {
        return $this->respond($request, $handler->handle(new EndTimetableDivisionCommand($this->school(), (int) $request->validated('academic_year_id'), $division,
            $request->user()?->id, $request->idempotencyKey())), 'divisions.end', 'flash.timetable.engine.divisionEnded');
    }

    public function saveAvailability(ManageTimetableEngineRequest $request, SetTimetableAvailabilityHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new SetTimetableAvailabilityCommand(
            $this->school(), (int) $v['academic_year_id'], (string) $v['target_type'], (int) $v['target_id'],
            array_map(static fn (array $s): array => ['day' => (int) $s['day'], 'period_id' => (int) $s['period_id']], $v['slots']),
            self::int($v['kind'] ?? null), self::int($v['week_no'] ?? null), $v['reason'] ?? null, $request->user()?->id, $request->idempotencyKey(),
        )), 'availability.save', null);
    }

    public function storeRule(ManageTimetableEngineRequest $request, SaveTimetableRuleHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new SaveTimetableRuleCommand(
            $this->school(), (int) $v['academic_year_id'], (string) $v['rule_type'], (int) $v['priority'], $v['scope'] ?? [], $v['params'] ?? [],
            $v['reason'] ?? null, $request->user()?->id, $request->idempotencyKey(),
        )), 'rules.store', 'flash.timetable.engine.ruleSaved');
    }

    public function endRule(int $rule, ManageTimetableEngineRequest $request, EndTimetableRuleHandler $handler): RedirectResponse
    {
        return $this->respond($request, $handler->handle(new EndTimetableRuleCommand($this->school(), (int) $request->validated('academic_year_id'), $rule,
            $request->user()?->id, $request->idempotencyKey())), 'rules.end', 'flash.timetable.engine.ruleEnded');
    }

    // ── Generation ───────────────────────────────────────────────────────────

    public function storeRun(GenerateTimetableRequest $request, QueueTimetableGenerationHandler $handler): RedirectResponse
    {
        $v = $request->validated();
        $options = array_filter([
            'time_budget' => self::int($v['time_budget'] ?? null),
            'seed' => self::int($v['seed'] ?? null) ?? random_int(1, 1_000_000),
            'objectives' => $v['objectives'] ?? [],
            'what_if' => $v['what_if'] ?? [],
        ], static fn ($value): bool => $value !== null);

        return $this->respond($request, $handler->handle(new QueueTimetableGenerationCommand(
            $this->school(), (int) $v['academic_year_id'], (int) $v['mode'], $v['scope'] ?? [], $options, $request->user()?->id, $request->idempotencyKey(),
        )), 'runs.store', 'flash.timetable.engine.runQueued');
    }

    public function cancelRun(int $run, GenerateTimetableRequest $request, CancelTimetableGenerationHandler $handler): RedirectResponse
    {
        return $this->respond($request, $handler->handle(new CancelTimetableGenerationCommand($this->school(), $run, $request->user()?->id, $request->idempotencyKey())),
            'runs.cancel', 'flash.timetable.engine.runCancelled');
    }

    public function applyRun(int $run, GenerateTimetableRequest $request, ApplyTimetableGenerationHandler $handler): RedirectResponse
    {
        return $this->respond($request, $handler->handle(new ApplyTimetableGenerationCommand($this->school(), $run, $request->user()?->id, CorrelationContext::id(), $request->idempotencyKey())),
            'runs.apply', 'flash.timetable.engine.runApplied');
    }

    public function discardRun(int $run, GenerateTimetableRequest $request, DiscardTimetableGenerationHandler $handler): RedirectResponse
    {
        return $this->respond($request, $handler->handle(new DiscardTimetableGenerationCommand($this->school(), $run, $request->user()?->id, $request->idempotencyKey())),
            'runs.discard', 'flash.timetable.engine.runDiscarded');
    }

    public function lockSchedules(LockSchedulesRequest $request, LockSchedulesHandler $handler): RedirectResponse
    {
        $lock = (bool) $request->validated('lock');

        return $this->respond($request, $handler->handle(new LockSchedulesCommand(
            $this->school(), (int) $request->validated('academic_year_id'), array_map('intval', $request->validated('schedule_ids')), $lock,
            $request->user()?->id, $request->idempotencyKey(),
        )), $lock ? 'schedules.lock' : 'schedules.unlock', null);
    }

    // ── Versions ─────────────────────────────────────────────────────────────

    public function storeVersion(PublishTimetableRequest $request, CreateTimetableVersionHandler $handler): RedirectResponse
    {
        $v = $request->validated();

        return $this->respond($request, $handler->handle(new CreateTimetableVersionCommand(
            $this->school(), (int) $v['academic_year_id'], (string) $v['name'], $v['reason'] ?? null, self::int($v['generation_run_id'] ?? null),
            $request->user()?->id, $request->idempotencyKey(),
        )), 'versions.store', 'flash.timetable.engine.versionCreated');
    }

    public function submitVersion(int $version, PublishTimetableRequest $request, SubmitTimetableVersionHandler $handler): RedirectResponse
    {
        return $this->respond($request, $handler->handle(new SubmitTimetableVersionCommand($this->school(), $version, $request->user()?->id, $request->idempotencyKey())),
            'versions.submit', 'flash.timetable.engine.versionSubmitted');
    }

    public function decideVersion(int $version, DecideTimetableVersionRequest $request, DecideTimetableVersionHandler $handler): RedirectResponse
    {
        return $this->respond($request, $handler->handle(new DecideTimetableVersionCommand(
            $this->school(), $version, (string) $request->validated('decision'), (int) $request->user()?->id, $request->idempotencyKey(),
        )), 'versions.decide', 'flash.timetable.engine.versionDecided');
    }

    public function publishVersion(int $version, PublishTimetableRequest $request, PublishTimetableVersionHandler $handler): RedirectResponse
    {
        return $this->respond($request, $handler->handle(new PublishTimetableVersionCommand(
            $this->school(), $version, (string) $request->validated('effective_from'), $request->user()?->id, $request->idempotencyKey(),
        )), 'versions.publish', 'flash.timetable.engine.versionPublished');
    }

    public function archiveVersion(int $version, PublishTimetableRequest $request, ArchiveTimetableVersionHandler $handler): RedirectResponse
    {
        return $this->respond($request, $handler->handle(new ArchiveTimetableVersionCommand($this->school(), $version, $request->user()?->id, $request->idempotencyKey())),
            'versions.archive', 'flash.timetable.engine.versionArchived');
    }

    public function restoreVersion(int $version, PublishTimetableRequest $request, RestoreTimetableVersionHandler $handler): RedirectResponse
    {
        return $this->respond($request, $handler->handle(new RestoreTimetableVersionCommand($this->school(), $version, $request->user()?->id, CorrelationContext::id(), $request->idempotencyKey())),
            'versions.restore', 'flash.timetable.engine.versionRestored');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function school(): int
    {
        return $this->schoolContext->requireId();
    }

    private static function int(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function respond(TimetableEngineRequest $request, TimetableEngineResult $result, string $action, ?string $flash): RedirectResponse
    {
        if ($result->failed()) {
            $redirect = redirect()->back()->withErrors(['engine' => $result->errors[0] ?? 'timetable.engine_error']);

            return isset($result->data['blockers']) ? $redirect->with('engineBlockers', $result->data['blockers']) : $redirect;
        }
        $this->securityAudit->record(
            SecurityEventType::TimetableDataModified,
            'timetable.web.'.$action,
            'succeeded',
            $request->user(),
            'timetable:'.($result->id ?? 'school'),
            ['from_idempotency' => $result->fromIdempotencyCache, 'data' => array_intersect_key($result->data, array_flip(['created', 'changed', 'rows', 'status', 'effective_from']))],
        );
        $redirect = redirect()->back()->with('engineResult', ['action' => $action, 'id' => $result->id]);

        return $flash === null ? $redirect : $redirect->with('success', $flash);
    }
}
