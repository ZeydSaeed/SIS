<?php

namespace Tests\Unit\Imports;

use App\Application\Imports\Contracts\ImportProfile;
use App\Application\Imports\Support\ImportContext;
use App\Application\Imports\Support\ImportEngine;
use App\Application\Imports\Support\ImportValues;
use App\Infrastructure\Spreadsheet\SimpleSpreadsheet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** «استيراد Excel»: cell parsing, header matching and the dependency-free spreadsheet adapter. */
final class ImportToolsTest extends TestCase
{
    #[Test]
    public function cells_understand_arabic_and_english_values(): void
    {
        $this->assertSame('2015-09-01', ImportValues::date('1/9/2015'));
        $this->assertSame('2015-09-01', ImportValues::date('2015-09-01'));
        $this->assertSame('2015-09-01', ImportValues::date('42248'), 'Excel serial');
        $this->assertNull(ImportValues::date('32/1/2020'));
        $this->assertSame(1, ImportValues::status('نشط'));
        $this->assertSame(2, ImportValues::status('غير نشط'));
        $this->assertSame(1, ImportValues::status(''));
        $this->assertNull(ImportValues::status('ربما'));
        $this->assertSame(12, ImportValues::int('١٢', 1, 40));
        $this->assertNull(ImportValues::int('41', 1, 40));
        $this->assertSame(['حاسوب', 'رياضيات', 'كهرباء'], ImportValues::list('حاسوب، رياضيات; كهرباء'));
        $this->assertSame(ImportValues::header('الرقم الوظيفي *'), ImportValues::header('الرقم_الوظيفي'));
        $this->assertSame(ImportValues::header('اسم الأب'), ImportValues::header('اسم الاب'));
    }

    #[Test]
    public function headers_map_by_label_key_or_alias_and_missing_required_are_reported(): void
    {
        $profile = new class implements ImportProfile
        {
            public function kind(): string
            {
                return 'x';
            }

            public function ability(): string
            {
                return 'x';
            }

            public function needsYear(): bool
            {
                return false;
            }

            public function columns(): array
            {
                return [
                    ['key' => 'code', 'label' => 'الرمز', 'required' => true, 'aliases' => ['code'], 'example' => ''],
                    ['key' => 'name', 'label' => 'الاسم', 'required' => true, 'aliases' => [], 'example' => ''],
                    ['key' => 'note', 'label' => 'ملاحظة', 'required' => false, 'aliases' => [], 'example' => ''],
                ];
            }

            public function plan(ImportContext $context, array $row): array
            {
                return ['action' => 1, 'errors' => [], 'key' => null, 'entity_id' => null, 'data' => []];
            }

            public function commit(ImportContext $context, array $data, int $action, ?int $entityId, string $idempotencyKey): array
            {
                return ['ok' => true, 'entity_id' => null, 'error' => null];
            }
        };

        [$map, $missing] = ImportEngine::mapHeader($profile, ['Code', 'ملاحظة']);
        $this->assertSame(['code' => 0, 'note' => 1], $map);
        $this->assertSame(['الاسم'], $missing);
    }

    #[Test]
    public function the_spreadsheet_adapter_round_trips_arabic_and_reads_csv(): void
    {
        $sheets = new SimpleSpreadsheet;
        $xlsx = $sheets->write('المعلمون', [['الرمز', 'الاسم'], ['T-1', 'نور الهدى'], [7, 'a & <b>']]);

        $this->assertStringStartsWith('PK', $xlsx);
        $this->assertSame([['الرمز', 'الاسم'], ['T-1', 'نور الهدى'], ['7', 'a & <b>']], $sheets->read($xlsx, 'file.xlsx'));
        $this->assertSame([['a', 'b'], ['1', 'نور']], $sheets->read("\xEF\xBB\xBFa;b\n1;نور\n\n", 'file.csv'));
        $this->assertNull($sheets->read('not a zip', 'file.xlsx'));
    }
}
