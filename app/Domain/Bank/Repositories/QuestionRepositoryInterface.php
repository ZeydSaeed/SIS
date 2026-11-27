<?php

namespace App\Domain\Bank\Repositories;

use App\Domain\Bank\ValueObjects\QuestionContent;

/**
 * Question bank persistence (bank.questions + bank.question_versions). Every read and write is scoped to the school;
 * nothing is ever deleted.
 *
 * @phpstan-type Question array{id: int, school_id: int, subject_id: int, status: string, created_by: ?int}
 * @phpstan-type Version array{
 *     id: int, question_id: int, school_id: int, version_no: int, type: string, stem: string,
 *     payload: array<string, mixed>, max_mark: string, status: string, created_by: int
 * }
 */
interface QuestionRepositoryInterface
{
    /** @return int the new question id */
    public function insertQuestion(int $schoolId, int $subjectId, int $createdBy): int;

    /** @return int the new version id (status draft) */
    public function insertVersion(int $questionId, int $schoolId, int $versionNo, QuestionContent $content, int $createdBy): int;

    /** @return Question|null locked FOR UPDATE inside the caller's transaction */
    public function lockQuestion(int $schoolId, int $questionId): ?array;

    /** @return Version|null locked FOR UPDATE inside the caller's transaction */
    public function lockVersion(int $schoolId, int $versionId): ?array;

    /** @return Version|null the latest version of the question (highest version_no), any status */
    public function latestVersion(int $schoolId, int $questionId): ?array;

    public function hasOpenVersion(int $schoolId, int $questionId): bool;

    /** @return int|null id of the approved version of the question */
    public function approvedVersionId(int $schoolId, int $questionId): ?int;

    public function updateDraft(int $versionId, QuestionContent $content): void;

    public function submit(int $versionId, int $submittedBy): void;

    public function approve(int $versionId, int $decidedBy, string $note): void;

    public function reject(int $versionId, int $decidedBy, string $note): void;

    public function supersede(int $versionId): void;

    public function retire(int $questionId, int $retiredBy, string $reason): void;
}
