<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\SetTimetableAvailabilityResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;
use App\Domain\Timetable\ValueObjects\AvailabilityKind;

final class SetTimetableAvailabilityHandler implements CommandHandler
{
    private const TARGETS = ['teacher' => 'teacher_ids', 'room' => 'room_ids', 'section' => 'section_ids', 'workshop' => 'workshop_ids'];

    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    public function handle(Command $command): SetTimetableAvailabilityResult
    {
        assert($command instanceof SetTimetableAvailabilityCommand);
        $refs = self::TARGETS[$command->targetType] ?? null;
        if ($refs === null || $command->slots === [] || count($command->slots) > 200
            || ($command->kind !== null && AvailabilityKind::tryFrom($command->kind) === null)) {
            return SetTimetableAvailabilityResult::failure('timetable.availability_invalid');
        }
        foreach ($command->slots as $slot) {
            if ($slot['day'] < 1 || $slot['day'] > 7) {
                return SetTimetableAvailabilityResult::failure('timetable.availability_invalid');
            }
        }
        if (! $this->config->referencesBelongToSchool($command->schoolId, $command->academicYearId, [$refs => [$command->targetId], 'period_ids' => array_column($command->slots, 'period_id')])) {
            return SetTimetableAvailabilityResult::failure('timetable.reference_not_in_school');
        }
        $target = [$command->targetType.'_id' => $command->targetId];

        return $this->tx->run(SetTimetableAvailabilityResult::class, $command->idempotencyKey, 'SetTimetableAvailability', function () use ($command, $target): SetTimetableAvailabilityResult {
            $changed = $this->config->setAvailability($command->schoolId, $command->academicYearId, $target, $command->slots, $command->kind, $command->weekNo, $command->reason, $command->userId);
            $this->tx->stage('configuration_changed', $command->schoolId, $command->academicYearId, $command->targetId, $command->userId, ['part' => 'availability', 'target' => $command->targetType, 'changed' => $changed]);

            return SetTimetableAvailabilityResult::success(null, ['changed' => $changed]);
        });
    }
}
