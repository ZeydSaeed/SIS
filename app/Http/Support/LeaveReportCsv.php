<?php

namespace App\Http\Support;

/**
 * «التقارير» as a CSV file (UTF-8 with a BOM so Excel reads the Arabic). The rows are the very ones the page shows —
 * already scoped to the school and masked for sensitive types — so the file can never carry more than the screen.
 * Cells that a spreadsheet would run as a formula are neutralised.
 */
final class LeaveReportCsv
{
    /** Rows beyond this are not written (a report is an aggregate or a short list; this is a guard, not a feature). */
    public const MAX_ROWS = 5000;

    private const KINDS = [1 => 'معلم', 2 => 'موظف إداري'];

    private const HOLIDAY_TYPES = [1 => 'رسمية', 2 => 'مدرسية', 3 => 'انقطاع بين فصلين'];

    private const STATUSES = [1 => 'مسودة', 2 => 'بانتظار الموافقة', 3 => 'مطلوب معلومات', 4 => 'معتمدة', 5 => 'مرفوضة', 6 => 'ملغاة', 7 => 'مسحوبة'];

    private const UNITS = [1 => 'أيام', 2 => 'ساعات'];

    /** @var array<string, array<string, string>> column key → heading, per report */
    private const COLUMNS = [
        'by_person' => ['person_name' => 'الموظف', 'requester_kind' => 'الفئة', 'type_name' => 'نوع الإجازة', 'requests' => 'الطلبات', 'quantity' => 'المدة المعتمدة', 'unit' => 'الوحدة'],
        'by_type' => ['type_name' => 'نوع الإجازة', 'open' => 'معلّقة', 'approved' => 'معتمدة', 'rejected' => 'مرفوضة', 'closed' => 'ملغاة / مسحوبة', 'approved_quantity' => 'المدة المعتمدة', 'unit' => 'الوحدة'],
        'pending' => ['request_no' => 'رقم الطلب', 'person_name' => 'صاحب الطلب', 'type_name' => 'نوع الإجازة', 'date_from' => 'من', 'date_to' => 'إلى', 'quantity' => 'المدة', 'unit' => 'الوحدة', 'status' => 'الحالة', 'approved_levels' => 'المراحل المنجزة', 'approval_levels' => 'مراحل الموافقة', 'submitted_at' => 'تاريخ التقديم', 'age_days' => 'العمر (يوم)'],
        'approval_time' => ['type_name' => 'نوع الإجازة', 'decided' => 'طلبات مُقرَّرة', 'average_hours' => 'متوسط الساعات', 'longest_hours' => 'أطول مدة (ساعة)'],
        'holidays' => ['name' => 'العطلة', 'start_date' => 'من', 'end_date' => 'إلى', 'holiday_type' => 'نوع العطلة', 'global' => 'النطاق'],
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function build(string $kind, array $rows): string
    {
        $columns = self::COLUMNS[$kind] ?? [];
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_values($columns));
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            fputcsv($out, array_map(static fn (string $key): string => self::cell($key, $row), array_keys($columns)));
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }

    public static function supports(string $kind): bool
    {
        return isset(self::COLUMNS[$kind]);
    }

    /** @param  array<string, mixed>  $row */
    private static function cell(string $key, array $row): string
    {
        $value = $row[$key] ?? null;
        $text = match (true) {
            $key === 'type_name' && ($row['masked'] ?? false) => 'محمية',
            $key === 'requester_kind' => self::KINDS[(int) $value] ?? '',
            $key === 'unit' => self::UNITS[(int) $value] ?? '',
            $key === 'status' => self::STATUSES[(int) $value] ?? '',
            $key === 'holiday_type' => self::HOLIDAY_TYPES[(int) $value] ?? '',
            $key === 'global' => $value ? 'عامة' : 'المدرسة',
            is_bool($value) => $value ? '1' : '0',
            $value === null => '',
            default => (string) $value,
        };

        // Spreadsheet formula injection: a leading = + - @ (or tab / CR) would be run as a formula.
        return $text !== '' && str_contains("=+-@\t\r", $text[0]) ? "'".$text : $text;
    }
}
