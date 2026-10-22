<?php

namespace Tests\Unit\Console;

use App\Database\DisposableE2eDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DisposableE2eDatabaseTest extends TestCase
{
    private const DB = 'sis_e2e_exams';

    private const PROTECTED = ['sis'];

    private const ALLOWED = ['sis_e2e_exams'];

    /** @return array{host: string, port: string, connected_to: string} */
    private static function server(array $overrides = []): array
    {
        return $overrides + ['host' => '127.0.0.1', 'port' => '5432', 'connected_to' => 'postgres'];
    }

    /** @return array<string, mixed> */
    private static function marker(array $overrides = []): array
    {
        return $overrides + ['database' => self::DB, 'oid' => 4242, 'template' => 'sis_e2e', 'host' => '127.0.0.1', 'port' => '5432'];
    }

    private static function live(int $oid = 4242, bool $template = false): array
    {
        return ['oid' => $oid, 'is_template' => $template];
    }

    #[Test]
    public function matching_evidence_allows_the_drop(): void
    {
        $this->assertNull(DisposableE2eDatabase::dropRefusal(self::DB, false, self::PROTECTED, self::ALLOWED, self::marker(), self::live(), self::server()));
    }

    /**
     * @return iterable<string, array{string, bool, list<string>, list<string>, ?array, ?array, array, string}>
     */
    public static function refusedDrops(): iterable
    {
        $m = self::marker();
        $l = self::live();
        $s = self::server();
        yield 'live database' => ['sis', false, ['sis'], ['sis'], self::marker(['database' => 'sis']), $l, $s, 'not a disposable e2e copy'];
        yield 'shared e2e database' => ['sis_e2e', false, [], ['sis_e2e'], self::marker(['database' => 'sis_e2e']), $l, $s, 'not a disposable e2e copy'];
        yield 'production' => [self::DB, true, [], self::ALLOWED, $m, $l, $s, 'production'];
        yield 'protected by configuration' => [self::DB, false, [self::DB], self::ALLOWED, $m, $l, $s, 'protected'];
        yield 'not on the allow-list' => [self::DB, false, [], [], $m, $l, $s, 'SIS_DESTRUCTIVE_ALLOWED_DATABASES'];
        yield 'does not exist' => [self::DB, false, [], self::ALLOWED, $m, null, $s, 'does not exist'];
        yield 'is a template' => [self::DB, false, [], self::ALLOWED, $m, self::live(template: true), $s, 'template database'];
        yield 'connected to the target' => [self::DB, false, [], self::ALLOWED, $m, $l, self::server(['connected_to' => self::DB]), 'connected to'];
        yield 'no marker' => [self::DB, false, [], self::ALLOWED, null, $l, $s, 'no creation marker'];
        yield 'marker of another database' => [self::DB, false, [], self::ALLOWED, self::marker(['database' => 'sis_e2e_other']), $l, $s, 'names another database'];
        yield 'marker without the e2e template' => [self::DB, false, [], self::ALLOWED, self::marker(['template' => 'sis']), $l, $s, 'e2e template'];
        yield 're-created under the same name' => [self::DB, false, [], self::ALLOWED, $m, self::live(9999), $s, 'not the database the marker recorded'];
        yield 'marker without OID' => [self::DB, false, [], self::ALLOWED, self::marker(['oid' => null]), $l, $s, 'not the database the marker recorded'];
        yield 'other server host' => [self::DB, false, [], self::ALLOWED, $m, $l, self::server(['host' => '10.0.0.5']), 'another server'];
        yield 'other server port' => [self::DB, false, [], self::ALLOWED, $m, $l, self::server(['port' => '5433']), 'another server'];
    }

    /**
     * @param  list<string>  $protected
     * @param  list<string>  $allowed
     */
    #[Test]
    #[DataProvider('refusedDrops')]
    public function drop_is_refused_without_full_evidence(string $db, bool $production, array $protected, array $allowed, ?array $marker, ?array $live, array $server, string $reason): void
    {
        $refusal = DisposableE2eDatabase::dropRefusal($db, $production, $protected, $allowed, $marker, $live, $server);

        $this->assertNotNull($refusal);
        $this->assertStringContainsString($reason, $refusal);
    }

    #[Test]
    public function create_needs_a_free_disposable_name_and_the_template(): void
    {
        $this->assertNull(DisposableE2eDatabase::createRefusal(self::DB, false, self::PROTECTED, self::ALLOWED, exists: false, templateExists: true));
        $this->assertStringContainsString('already exists', (string) DisposableE2eDatabase::createRefusal(self::DB, false, self::PROTECTED, self::ALLOWED, exists: true, templateExists: true));
        $this->assertStringContainsString('template', (string) DisposableE2eDatabase::createRefusal(self::DB, false, self::PROTECTED, self::ALLOWED, exists: false, templateExists: false));
        $this->assertStringContainsString('not a disposable', (string) DisposableE2eDatabase::createRefusal('sis_e2e', false, [], ['sis_e2e'], exists: false, templateExists: true));
        $this->assertStringContainsString('not a disposable', (string) DisposableE2eDatabase::createRefusal('sis', false, [], ['sis'], exists: false, templateExists: true));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function names(): iterable
    {
        yield 'sis' => ['sis', false];
        yield 'sis_e2e' => ['sis_e2e', false];
        yield 'sis_e2e_' => ['sis_e2e_', false];
        yield 'quote injection' => ['sis_e2e_x"; DROP DATABASE sis; --', false];
        yield 'upper case' => ['SIS_E2E_EXAMS', false];
        yield 'disposable' => ['sis_e2e_exams', true];
    }

    #[Test]
    #[DataProvider('names')]
    public function only_sis_e2e_suffixed_names_are_disposable(string $name, bool $disposable): void
    {
        $this->assertSame($disposable, DisposableE2eDatabase::isDisposableName($name));
    }
}
