<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\LinkTeacherUserAccountResult;
use App\Domain\Shared\Exceptions\SisDomainException;
use App\Domain\Teachers\Repositories\TeacherAccountRepositoryInterface;

/**
 * One account ↔ one teacher. Only teachers working in the acting school that year can be linked; the user must
 * already exist (accounts are created by the identity administration, never here).
 */
final class LinkTeacherUserAccountHandler implements CommandHandler
{
    public const COMMAND_NAME = 'LinkTeacherUserAccount';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherAccountRepositoryInterface $accounts,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): LinkTeacherUserAccountResult
    {
        assert($command instanceof LinkTeacherUserAccountCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return LinkTeacherUserAccountResult::fromIdempotency($cached['user_id'] !== null ? (int) $cached['user_id'] : null);
        }
        if (! $this->accounts->teacherWorksInSchool($command->teacherId, $command->schoolId, $command->academicYearId)) {
            throw SisDomainException::withCode('teachers.account_teacher_not_in_school');
        }

        $email = $command->email !== null ? trim($command->email) : '';
        $userId = $email === '' ? null : $this->accounts->userIdByEmail($email);
        if ($email !== '' && $userId === null) {
            throw SisDomainException::withCode('teachers.account_email_unknown');
        }
        if ($userId !== null && $this->accounts->teacherLinkedToUser($userId, $command->teacherId) !== null) {
            throw SisDomainException::withCode('teachers.account_already_linked_conflict');
        }

        $this->unitOfWork->transaction(function () use ($command, $userId): void {
            $this->accounts->currentUserId($command->teacherId);
            $this->accounts->link($command->teacherId, $userId);
        });

        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, ['user_id' => $userId]);

        return LinkTeacherUserAccountResult::success($userId);
    }
}
