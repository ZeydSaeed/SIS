<?php

namespace App\Application\Bank\Queries;

use App\Application\Bank\Contracts\QuestionBankReadRepositoryInterface;

final class ListQuestionsHandler
{
    public function __construct(
        private readonly QuestionBankReadRepositoryInterface $read,
    ) {}

    /** @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public function handle(ListQuestionsQuery $query): array
    {
        $search = $query->search !== null ? trim($query->search) : null;

        return $this->read->paginate(
            $query->schoolId,
            $query->subjectIds,
            $query->subjectId,
            $query->status,
            $search === '' ? null : mb_substr((string) $search, 0, 100),
            max(1, $query->page),
            min(100, max(1, $query->perPage)),
        );
    }
}
