<?php

namespace App\Application\Contracts;

/**
 * «حذف آمن»: removes a record for real only when nothing else in the system points at it.
 *
 * The record and the rows it owns outright (e.g. a student's addresses) go in one transaction; if any other
 * data references it (grades, attendance, schedules…) the whole delete is rolled back and refused.
 */
interface SafeDeleter
{
    /**
     * @param  string  $entity  a key the implementation knows (student, teacher, …)
     * @return array{status: 'deleted'|'not_found'|'linked', linked_to?: string}
     *                                                                           linked_to: the table that still references it
     */
    public function delete(string $entity, int $schoolId, int $id): array;

    /**
     * All or nothing: every record is deleted, or — at the first one refused — none is.
     *
     * @param  list<int>  $ids
     * @return array{status: 'deleted'|'not_found'|'linked', linked_to?: string, id?: int}
     */
    public function deleteMany(string $entity, int $schoolId, array $ids): array;

    /** @return list<string> entity keys this deleter can remove */
    public function entities(): array;
}
