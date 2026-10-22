<?php

namespace App\Database;

/**
 * Rules for the disposable copies of the e2e dataset (`sis_e2e_<suffix>`, cloned from `sis_e2e`). Pure decisions —
 * the caller gathers the facts (environment, configured names, the live pg_database row, the creation marker), so
 * every refusal can be tested without a database.
 *
 * A copy may be dropped only with evidence that it is the very database the guarded `create` made for this run:
 * a marker written at creation holding the database's OID, which PostgreSQL never reuses for a re-created database
 * of the same name, plus the server it was created on.
 */
final class DisposableE2eDatabase
{
    public const TEMPLATE = 'sis_e2e';

    /** `sis_e2e_<suffix>` only: the live `sis`, the shared `sis_e2e` and every other name are refused. */
    public static function isDisposableName(string $database): bool
    {
        return preg_match('/^sis_e2e_[a-z0-9_]+$/', $database) === 1;
    }

    /**
     * @param  list<string>  $protectedNames
     * @param  list<string>  $allowedNames
     */
    public static function nameRefusal(string $database, bool $production, array $protectedNames, array $allowedNames): ?string
    {
        return match (true) {
            $production => 'production environment',
            ! self::isDisposableName($database) => "«{$database}» is not a disposable e2e copy (sis_e2e_<suffix>; sis and sis_e2e are never used)",
            in_array($database, $protectedNames, true) => "«{$database}» is a protected database",
            ! in_array($database, $allowedNames, true) => "«{$database}» is not in SIS_DESTRUCTIVE_ALLOWED_DATABASES",
            default => null,
        };
    }

    /**
     * @param  list<string>  $protectedNames
     * @param  list<string>  $allowedNames
     */
    public static function createRefusal(string $database, bool $production, array $protectedNames, array $allowedNames, bool $exists, bool $templateExists): ?string
    {
        return self::nameRefusal($database, $production, $protectedNames, $allowedNames) ?? match (true) {
            $exists => "«{$database}» already exists — drop it through this tool first (or pick another name)",
            ! $templateExists => '«'.self::TEMPLATE.'» (the template) does not exist',
            default => null,
        };
    }

    /**
     * @param  list<string>  $protectedNames
     * @param  list<string>  $allowedNames
     * @param  array<string, mixed>|null  $marker  the creation marker (null when missing or unreadable)
     * @param  array{oid: int, is_template: bool}|null  $live  the pg_database row (null when the database does not exist)
     * @param  array{host: string, port: string, connected_to: string}  $server  the server the drop would run on
     */
    public static function dropRefusal(string $database, bool $production, array $protectedNames, array $allowedNames, ?array $marker, ?array $live, array $server): ?string
    {
        return self::nameRefusal($database, $production, $protectedNames, $allowedNames) ?? match (true) {
            $live === null => "«{$database}» does not exist — nothing to drop",
            $live['is_template'] => "«{$database}» is a template database",
            $server['connected_to'] === $database => "the maintenance connection is connected to «{$database}» itself",
            $marker === null => "no creation marker for «{$database}»: it was not created by sis:e2e-disposable create on this machine",
            ($marker['database'] ?? null) !== $database => 'the creation marker names another database',
            ($marker['template'] ?? null) !== self::TEMPLATE => 'the creation marker does not record the e2e template',
            (int) ($marker['oid'] ?? 0) !== $live['oid'] => "«{$database}» is not the database the marker recorded (OID {$live['oid']} ≠ ".(int) ($marker['oid'] ?? 0).') — it was re-created by something else',
            (string) ($marker['host'] ?? '') !== $server['host'] || (string) ($marker['port'] ?? '') !== $server['port'] => 'the creation marker was written for another server',
            default => null,
        };
    }
}
