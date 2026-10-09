<?php

namespace App\Infrastructure\Spreadsheet;

use App\Application\Imports\Contracts\SpreadsheetPort;

/**
 * A small, dependency-free spreadsheet adapter (ext-zip + ext-xml, already required by the platform):
 * reads the first worksheet of an .xlsx (shared / inline strings, numbers, booleans) or a UTF-8 .csv, and writes a
 * single right-to-left .xlsx sheet with a bold header. Enough for import templates and error reports — no formulas,
 * no styles beyond the header.
 */
final class SimpleSpreadsheet implements SpreadsheetPort
{
    private const MAX_ROWS = 20_000;

    private const MAX_COLUMNS = 60;

    public function read(string $contents, string $fileName): ?array
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($extension === 'csv' || $extension === 'txt') {
            return $this->readCsv($contents);
        }
        if (! str_starts_with($contents, 'PK')) {
            return null;
        }

        return $this->readXlsx($contents);
    }

    public function write(string $sheetName, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::xml(mb_substr($sheetName, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Segoe UI"/></font><font><b/><sz val="11"/><name val="Segoe UI"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>');

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView rightToLeft="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols><col min="1" max="'.max(1, count($rows[0] ?? [])).'" width="22" customWidth="1"/></cols><sheetData>';
        foreach ($rows as $r => $row) {
            $sheet .= '<row r="'.($r + 1).'">';
            foreach (array_values($row) as $c => $value) {
                $ref = self::column($c).($r + 1);
                $style = $r === 0 ? ' s="1"' : '';
                if (is_int($value) || is_float($value)) {
                    $sheet .= '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
                } elseif ($value !== null && $value !== '') {
                    $sheet .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.self::xml((string) $value).'</t></is></c>';
                }
            }
            $sheet .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet.'</sheetData></worksheet>');
        $zip->close();
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    /** @return list<list<string>>|null */
    private function readCsv(string $contents): ?array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        if (! mb_check_encoding($contents, 'UTF-8')) {
            return null;
        }
        $firstLine = strtok($contents, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : (substr_count($firstLine, "\t") > substr_count($firstLine, ',') ? "\t" : ',');
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false && count($rows) < self::MAX_ROWS) {
            $rows[] = array_map(static fn ($v): string => trim((string) $v), array_slice($row, 0, self::MAX_COLUMNS));
        }
        fclose($stream);

        return self::trimRows($rows);
    }

    /** @return list<list<string>>|null */
    private function readXlsx(string $contents): ?array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $contents);
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            @unlink($path);

            return null;
        }
        $shared = $this->sharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));
        $sheetXml = $zip->getFromName($this->firstSheetPath($zip));
        $zip->close();
        @unlink($path);
        if ($sheetXml === false) {
            return null;
        }

        $reader = new \XMLReader;
        if (! $reader->XML($sheetXml, null, LIBXML_NONET | LIBXML_COMPACT)) {
            return null;
        }
        $rows = [];
        $row = null;
        $cellRef = '';
        $cellType = '';
        $value = '';
        $inValue = false;
        while ($reader->read() && count($rows) < self::MAX_ROWS) {
            if ($reader->nodeType === \XMLReader::ELEMENT) {
                if ($reader->localName === 'row') {
                    $row = [];
                } elseif ($reader->localName === 'c') {
                    $cellRef = (string) $reader->getAttribute('r');
                    $cellType = (string) $reader->getAttribute('t');
                    $value = '';
                    if ($reader->isEmptyElement && $row !== null) {
                        $row[self::columnIndex($cellRef)] = '';
                    }
                } elseif ($reader->localName === 'v' || $reader->localName === 't') {
                    $inValue = true;
                }
            } elseif (($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA || $reader->nodeType === \XMLReader::SIGNIFICANT_WHITESPACE) && $inValue) {
                $value .= $reader->value;
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT) {
                if ($reader->localName === 'v' || $reader->localName === 't') {
                    $inValue = false;
                } elseif ($reader->localName === 'c' && $row !== null) {
                    $index = self::columnIndex($cellRef);
                    if ($index < self::MAX_COLUMNS) {
                        $row[$index] = trim(match ($cellType) {
                            's' => $shared[(int) $value] ?? '',
                            'b' => $value === '1' ? '1' : '0',
                            default => $value,
                        });
                    }
                } elseif ($reader->localName === 'row' && $row !== null) {
                    $dense = [];
                    $max = $row === [] ? -1 : max(array_keys($row));
                    for ($i = 0; $i <= $max; $i++) {
                        $dense[] = $row[$i] ?? '';
                    }
                    $rows[] = $dense;
                    $row = null;
                }
            }
        }
        $reader->close();

        return self::trimRows($rows);
    }

    /** @return list<string> */
    private function sharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }
        $reader = new \XMLReader;
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        $strings = [];
        $current = null;
        $inText = false;
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'si') {
                $current = '';
            } elseif ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 't') {
                $inText = ! $reader->isEmptyElement;
            } elseif ($inText && ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::SIGNIFICANT_WHITESPACE)) {
                $current .= $reader->value;
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 't') {
                $inText = false;
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $strings[] = (string) $current;
                $current = null;
            }
        }
        $reader->close();

        return $strings;
    }

    private function firstSheetPath(\ZipArchive $zip): string
    {
        $rels = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        if (preg_match('/<sheet[^>]*r:id="([^"]+)"/', $workbook, $sheet) === 1
            && preg_match('/<Relationship[^>]*Id="'.preg_quote($sheet[1], '/').'"[^>]*Target="([^"]+)"/', $rels, $target) === 1) {
            $path = ltrim($target[1], '/');

            return str_starts_with($path, 'xl/') ? $path : 'xl/'.$path;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param  list<list<string>>  $rows
     * @return list<list<string>>
     */
    private static function trimRows(array $rows): array
    {
        while ($rows !== [] && implode('', end($rows)) === '') {
            array_pop($rows);
        }

        return $rows;
    }

    private static function columnIndex(string $ref): int
    {
        $letters = preg_replace('/\d+/', '', strtoupper($ref)) ?? '';
        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return max(0, $index - 1);
    }

    private static function column(int $index): string
    {
        $name = '';
        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $name = chr(65 + ($i - 1) % 26).$name;
        }

        return $name;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
