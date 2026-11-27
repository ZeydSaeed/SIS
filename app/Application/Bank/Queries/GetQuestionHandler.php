<?php

namespace App\Application\Bank\Queries;

use App\Application\Bank\Contracts\QuestionBankReadRepositoryInterface;
use App\Domain\Bank\Exceptions\QuestionBankException;
use App\Domain\Bank\ValueObjects\QuestionContent;

/**
 * One question with its versions. Without `$withAnswerKey` the payload of every version is stripped of the answer key
 * (DEC-050: only authors and approvers see it). A question outside `$subjectIds` is reported as not found.
 */
final class GetQuestionHandler
{
    public function __construct(
        private readonly QuestionBankReadRepositoryInterface $read,
    ) {}

    /**
     * @param  list<int>|null  $subjectIds  null = school-wide
     * @return array<string, mixed>
     */
    public function handle(int $schoolId, int $questionId, ?array $subjectIds, bool $withAnswerKey): array
    {
        $question = $this->read->find($schoolId, $questionId);
        if ($question === null || ($subjectIds !== null && ! in_array((int) $question['subject_id'], $subjectIds, true))) {
            throw QuestionBankException::questionNotFound($questionId);
        }

        if (! $withAnswerKey) {
            $question['versions'] = array_map(static function (array $version): array {
                $content = QuestionContent::fromStored($version['type'], $version['stem'], $version['payload'], $version['max_mark']);
                $version['payload'] = $content->publicPayload();

                return $version;
            }, $question['versions']);
        }

        return $question;
    }
}
