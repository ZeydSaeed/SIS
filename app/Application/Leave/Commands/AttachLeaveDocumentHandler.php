<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Contracts\LeaveAttachmentStorageInterface;
use App\Application\Leave\Results\AttachLeaveDocumentResult;
use App\Application\Leave\Support\LeaveRequestPreparer;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Services\LeaveTransitions;
use App\Domain\Leave\ValueObjects\LeaveEventType;

final class AttachLeaveDocumentHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.AttachLeaveDocument';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveRequestRepositoryInterface $requests,
        private readonly LeaveRequestPreparer $prepare,
        private readonly LeaveAttachmentStorageInterface $attachments,
    ) {}

    public function handle(Command $command): AttachLeaveDocumentResult
    {
        assert($command instanceof AttachLeaveDocumentCommand);

        return $this->tx->run(AttachLeaveDocumentResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $request = $this->requests->lock($command->schoolId, $command->requestId) ?? throw LeaveRuleViolation::requestNotFound();
            $this->prepare->assertMayActFor($this->prepare->personOf($request), $command->actorUserId, $command->canManage);
            LeaveTransitions::assert(LeaveTransitions::ATTACH, $request->status);
            $type = $this->prepare->type($request->schoolId, $request->leaveTypeId);

            $documentId = $this->attachments->store(
                $request->schoolId, $request->id, $type->isSensitive, $command->file['name'], $command->file['mime'],
                $command->file['contents'], $command->actorUserId, (string) $command->idempotencyKey,
            );
            // The file name stays out of the history (it may describe a diagnosis); only the document id is kept.
            $this->requests->addEvent($request->schoolId, $request->id, LeaveEventType::DocumentAttached, $request->status, $request->status, null, $command->actorUserId, null, ['document_id' => $documentId]);

            return [
                'data' => ['request_id' => $request->id, 'document_id' => $documentId],
                'event' => LeaveTransaction::event('request', $request->id, $request->schoolId, 'document_attached', $command->actorUserId, ['document_id' => $documentId]),
            ];
        });
    }
}
