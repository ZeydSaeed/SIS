<?php

namespace Tests\Unit\Attendance;

use App\Domain\Attendance\ValueObjects\CalendarDate;
use App\Domain\Enrollment\Services\EnrollmentStructureGuard;
use App\Domain\Enrollment\ValueObjects\SectionLetter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every refusal the attendance module (and the section / teacher-account rules it relies on) can raise reaches the
 * user as the error dialog: its code must have an Arabic text in resources/js/i18n/ar.ts, else the dialog would show
 * the raw code. Also the two input rules added on 2026-10-13 (section letters, calendar dates).
 */
final class AttendanceErrorDialogCoverageTest extends TestCase
{
    private const SOURCES = [
        'app/Domain/Attendance',
        'app/Application/Attendance',
        'app/Http/Controllers/Attendance',
        'app/Http/Requests/Attendance',
        'app/Application/Teachers/Commands/LinkTeacherUserAccountHandler.php',
        'app/Application/Enrollment/Commands/CreateSectionHandler.php',
        'app/Application/Enrollment/Commands/UpdateSectionHandler.php',
        'app/Domain/Enrollment/Services/EnrollmentStructureGuard.php',
    ];

    #[Test]
    public function every_raised_error_code_has_an_arabic_dialog_text(): void
    {
        $root = dirname(__DIR__, 3);
        preg_match_all("/'([a-z_]+\\.[a-z0-9_.]+)':\\s*'/u", (string) file_get_contents($root.'/resources/js/i18n/ar.ts'), $known);
        $known = array_flip($known[1]);

        $raised = [];
        foreach (self::SOURCES as $source) {
            $files = is_dir($root.'/'.$source)
                ? iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$source, \FilesystemIterator::SKIP_DOTS)))
                : [new \SplFileInfo($root.'/'.$source)];
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $php = (string) file_get_contents($file->getPathname());
                // withCode('x') · new self("…", 'x') · failure('x') · return 'x' (guards) · validation messages 'x'
                preg_match_all("/(?:withCode\\(|new self\\([^;]*?,\\s*|failure\\(|return\\s+|=>\\s*)'((?:attendance|teachers|enrollment\\.section)[a-z_]*\\.[a-z_]+)'/s", $php, $m);
                foreach ($m[1] as $code) {
                    $raised[$code] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }
        // Audit / route names that share the prefix are not user-facing codes.
        $raised = array_filter($raised, static fn (string $file, string $code): bool => ! preg_match('/^attendance\.(web|sheets|staff|show|reports|excuses|alerts|review|settings|lessons|my|dashboard)\./', $code) && $code !== 'attendance.show', ARRAY_FILTER_USE_BOTH);

        $missing = array_diff_key($raised, $known);
        $this->assertGreaterThan(30, count($raised));
        $this->assertSame([], $missing, 'Error codes without an Arabic dialog text: '.json_encode($missing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function sectionNames(): iterable
    {
        yield 'latin' => ['A', 'A'];
        yield 'lowercase' => ['b', 'B'];
        yield 'arabic alif hamza' => ['أ', 'A'];
        yield 'arabic alif' => ['ا', 'A'];
        yield 'arabic beh' => ['ب', 'B'];
        yield 'arabic jeem' => ['ج', 'C'];
        yield 'with the word' => ['شعبة ج', 'C'];
        yield 'with spaces' => ['  C ', 'C'];
        yield 'code form' => ['SEC-B', 'B'];
        yield 'a fourth letter' => ['D', null];
        yield 'a number' => ['1', null];
        yield 'free text' => ['شعبة 1', null];
        yield 'empty' => ['', null];
    }

    #[Test]
    #[DataProvider('sectionNames')]
    public function sections_are_named_by_one_of_the_letters_a_b_c(string $input, ?string $letter): void
    {
        $this->assertSame($letter, SectionLetter::normalize($input));
        $guard = new EnrollmentStructureGuard;
        $this->assertSame($letter === null ? 'enrollment.section_letter_invalid' : null, $guard->sectionNameError($input, false));
        if ($letter !== null) {
            $this->assertSame('enrollment.section_name_taken', $guard->sectionNameError($input, true));
            $this->assertSame('SEC-'.$letter, SectionLetter::code($letter));
        }
    }

    #[Test]
    public function only_real_calendar_dates_pass(): void
    {
        foreach (['2026-10-13', '2028-02-29', '2026-12-31'] as $date) {
            $this->assertTrue(CalendarDate::valid($date), $date);
        }
        foreach (['2026-02-30', '2026-13-01', '2027-02-29', '0000-01-01', '2026-1-1', '', null, ['2026-10-13'], 20261013, '2026-10-13 00:00'] as $value) {
            $this->assertFalse(CalendarDate::valid($value), json_encode($value));
        }
    }
}
