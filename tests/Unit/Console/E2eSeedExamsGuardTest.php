<?php

namespace Tests\Unit\Console;

use App\Console\Commands\E2eSeedExamsCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class E2eSeedExamsGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function names(): iterable
    {
        yield 'live database' => ['sis', false];
        yield 'shared e2e dataset' => ['sis_e2e', false];
        yield 'shared e2e with trailing underscore' => ['sis_e2e_', false];
        yield 'phpunit database' => ['sis_test', false];
        yield 'other e2e-looking name' => ['sis_e2eexams', false];
        yield 'injection attempt' => ['sis_e2e_x; drop', false];
        yield 'upper case' => ['SIS_E2E_EXAMS', false];
        yield 'empty' => ['', false];
        yield 'disposable copy' => ['sis_e2e_exams', true];
        yield 'disposable copy with digits' => ['sis_e2e_run_42', true];
    }

    #[Test]
    #[DataProvider('names')]
    public function only_disposable_sis_e2e_copies_are_accepted(string $database, bool $accepted): void
    {
        $this->assertSame($accepted, E2eSeedExamsCommand::isDisposableCopy($database));
    }
}
