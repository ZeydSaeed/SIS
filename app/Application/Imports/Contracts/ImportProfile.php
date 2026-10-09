<?php

namespace App\Application\Imports\Contracts;

use App\Application\Imports\Support\ImportContext;

/**
 * One importable kind («المعلمون», «الفروع والاختصاصات», «الطلاب», «المواد والمناهج»). Each profile lives in the
 * context that owns the data and writes only through that context's own handlers, so an import obeys exactly the
 * rules of the page that owns the entity (guards, idempotency, outbox, audit).
 *
 * plan()   validates one normalised row WITHOUT writing: errors, the duplicate key, and what commit would do
 *          (create / update / skip, with the existing entity id).
 * commit() applies one planned row; a refusal comes back as an error code (never an exception for business rules).
 */
interface ImportProfile
{
    public const CREATE = 1;

    public const UPDATE = 2;

    public const SKIP = 3;

    public function kind(): string;

    /** The policy ability (Gate) a user needs to import this kind. */
    public function ability(): string;

    /** True when rows belong to an academic year (teachers' memberships, curriculum links). */
    public function needsYear(): bool;

    /**
     * Template / header columns. `aliases` are accepted header spellings (Arabic and English).
     *
     * @return list<array{key: string, label: string, required: bool, aliases: list<string>, example: string, hint?: string}>
     */
    public function columns(): array;

    /**
     * @param  array<string, string>  $row  normalised by column key (trimmed strings, '' = empty)
     * @return array{action: int, errors: list<string>, key: string|null, entity_id: int|null, data: array<string, mixed>}
     */
    public function plan(ImportContext $context, array $row): array;

    /**
     * @param  array<string, mixed>  $data  the planned data
     * @return array{ok: bool, entity_id: int|null, error: string|null}
     */
    public function commit(ImportContext $context, array $data, int $action, ?int $entityId, string $idempotencyKey): array;
}
