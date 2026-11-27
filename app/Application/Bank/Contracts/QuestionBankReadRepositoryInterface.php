<?php

namespace App\Application\Bank\Contracts;

/** Read side of the question bank pages (server pagination; no answer keys unless asked for). */
interface QuestionBankReadRepositoryInterface
{
    /**
     * Questions of the school, newest first, with the subject name and their latest version (number, type, status,
     * stem excerpt). `$subjectIds` null = every subject; an empty list = none.
     *
     * @param  list<int>|null  $subjectIds
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function paginate(int $schoolId, ?array $subjectIds, ?int $subjectId, ?string $status, ?string $search, int $page, int $perPage): array;

    /**
     * One question with all its versions (newest first) and user names, or null when it is not in the school.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $schoolId, int $questionId): ?array;

    /**
     * Active subjects for the filter and the create form. `$subjectIds` null = every active subject.
     *
     * @param  list<int>|null  $subjectIds
     * @return list<array{id: int, name: string, name_en: ?string, code: string}>
     */
    public function subjects(?array $subjectIds): array;
}
