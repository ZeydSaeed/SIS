<?php

namespace App\Application\Imports\Contracts;

/** Reads uploaded spreadsheets (.xlsx / .csv) and builds downloadable ones (templates, error reports). */
interface SpreadsheetPort
{
    /**
     * The rows of the first sheet as strings (header row included); empty trailing rows dropped.
     *
     * @return list<list<string>>|null null when the file is not a readable .xlsx / .csv
     */
    public function read(string $contents, string $fileName): ?array;

    /**
     * An .xlsx workbook with one right-to-left sheet; the first row is bold (the header).
     *
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function write(string $sheetName, array $rows): string;
}
