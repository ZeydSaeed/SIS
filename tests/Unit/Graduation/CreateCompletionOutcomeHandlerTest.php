<?php

namespace Tests\Unit\Graduation;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Graduation\Commands\CreateCompletionOutcomeCommand;
use App\Application\Graduation\Commands\CreateCompletionOutcomeHandler;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Domain\Graduation\Exceptions\IdempotencyPayloadConflictException;
use App\Domain\Graduation\Repositories\GraduationWriteRepositoryInterface;
use App\Domain\Graduation\Support\GraduationIdempotencyGuard;
use App\Domain\Graduation\ValueObjects\GraduationAction;
use App\Domain\Shared\DomainEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateCompletionOutcomeHandlerTest extends TestCase
{
    #[Test]
    public function same_key_same_fingerprint_replays(): void
    {
        $fp = GraduationIdempotencyGuard::fingerprint(
            CreateCompletionOutcomeHandler::COMMAND_NAME,
            1,
            ['schema_version' => 1, 'enrollment_id' => 5],
        );

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn([
            'completion_outcome_id' => 99,
            'school_id' => 1,
            'enrollment_id' => 5,
            'request_fingerprint' => $fp,
        ]);

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->expects($this->once())->method('assertCan');

        $handler = new CreateCompletionOutcomeHandler(
            $uow,
            $this->createMock(GraduationWriteRepositoryInterface::class),
            $this->createMock(OutboxRepository::class),
            $idempotency,
            $authority,
        );

        $result = $handler->handle(new CreateCompletionOutcomeCommand(1, 5, 7, 'k1', 'c1'));
        $this->assertTrue($result->fromIdempotencyCache);
        $this->assertSame(99, $result->completionOutcomeId);
    }

    #[Test]
    public function same_key_different_fingerprint_rejects(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn([
            'completion_outcome_id' => 99,
            'school_id' => 1,
            'enrollment_id' => 5,
            'request_fingerprint' => 'different',
        ]);

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertCan');

        $handler = new CreateCompletionOutcomeHandler(
            $uow,
            $this->createMock(GraduationWriteRepositoryInterface::class),
            $this->createMock(OutboxRepository::class),
            $idempotency,
            $authority,
        );

        $this->expectException(IdempotencyPayloadConflictException::class);
        $handler->handle(new CreateCompletionOutcomeCommand(1, 5, 7, 'k1', 'c1'));
    }

    #[Test]
    public function successful_write_stores_idempotency_inside_transaction(): void
    {
        $storeCalledInside = false;

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(function (callable $callback) use (&$storeCalledInside) {
            $result = $callback();
            $storeCalledInside = true;

            return $result;
        });

        $repo = $this->createMock(GraduationWriteRepositoryInterface::class);
        $repo->method('findEnrollmentIdentity')->willReturn([
            'student_id' => 2,
            'academic_year_id' => 3,
            'school_id' => 1,
        ]);
        $repo->method('insertCompletionOutcome')->willReturn(42);

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(DomainEvent::class), 'c1');

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn(null);
        $idempotency->expects($this->once())->method('store');

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->expects($this->once())->method('assertCan')
            ->with(GraduationAction::CreateCompletionOutcome, 7, 1);

        $handler = new CreateCompletionOutcomeHandler($uow, $repo, $outbox, $idempotency, $authority);
        $result = $handler->handle(new CreateCompletionOutcomeCommand(1, 5, 7, 'k1', 'c1'));

        $this->assertTrue($storeCalledInside);
        $this->assertSame(42, $result->completionOutcomeId);
        $this->assertFalse($result->fromIdempotencyCache);
    }
}
