<?php

namespace App\Infrastructure\Attendance;

use Mpdf\Mpdf;

/**
 * Writes a report payload (GetAttendanceReportHandler export) as CSV (UTF-8 + BOM, opens in Excel) or as an RTL
 * Arabic PDF (mPDF). Every file states the report, the generation time, the filters and the calculation method.
 */
final class AttendanceReportFileWriter
{
    private const TITLES = [
        'students' => 'حضور الطلاب', 'student_detail' => 'سجل طالب تفصيلي', 'sections' => 'حسب الشعبة', 'subjects' => 'حسب المادة',
        'teachers' => 'كشوف المعلمين', 'daily' => 'الحضور اليومي', 'staff' => 'دوام المعلمين', 'lesson_types' => 'نظري مقابل عملي',
        'periods' => 'التأخير حسب اليوم والحصة', 'excuses' => 'الأعذار', 'risk' => 'مؤشر المخاطر الأكاديمية',
    ];

    private const COLUMNS = [
        'full_name' => 'الطالب', 'student_code' => 'الرقم', 'section_name' => 'الشعبة', 'class_name' => 'الصف', 'subject_name' => 'المادة',
        'teacher_name' => 'المعلم', 'date' => 'التاريخ', 'period_number' => 'الحصة', 'weekday' => 'اليوم', 'lesson_type' => 'النوع',
        'status' => 'الحالة', 'minutes_late' => 'دقائق التأخير', 'notes' => 'ملاحظة', 'present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر',
        'excused' => 'بعذر', 'left_early' => 'خروج مبكر', 'decided' => 'المحددة', 'attendance_rate' => 'نسبة الحضور %', 'absence_rate' => 'نسبة الغياب %',
        'unexcused_rate' => 'غياب بلا عذر %', 'late_rate' => 'نسبة التأخير %', 'sheets' => 'الكشوف', 'pending' => 'لم تُرسل', 'in_review' => 'قيد المراجعة',
        'approved' => 'معتمدة', 'rejected' => 'مرفوضة', 'resubmissions' => 'إعادات', 'late_submissions' => 'متأخرة الإرسال', 'days' => 'الأيام',
        'approved_leave' => 'إجازة', 'official_mission' => 'مهمة', 'excused_absence' => 'غياب مبرر', 'excuse_type' => 'نوع العذر', 'excuses' => 'العدد',
        'applied_records' => 'حالات مطبقة', 'sheet_status' => 'حالة الكشف', 'score' => 'الدرجة', 'band' => 'المستوى', 'reasons' => 'الأسباب',
    ];

    private const HIDDEN = ['student_id', 'section_id', 'subject_id', 'teacher_id', 'school_id'];

    /**
     * @param  array<string, mixed>  $report
     */
    public function csv(array $report): string
    {
        $out = fopen('php://temp', 'w+b');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($this->preamble($report) as $line) {
            fputcsv($out, $line);
        }
        $columns = $this->columns($report);
        fputcsv($out, array_map(fn (string $c): string => self::COLUMNS[$c] ?? $c, $columns));
        foreach ($report['rows'] as $row) {
            fputcsv($out, array_map(fn (string $c): string => $this->cell($row[$c] ?? null), $columns));
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function pdf(array $report): string
    {
        $columns = $this->columns($report);
        $head = implode('', array_map(fn (string $c): string => '<th>'.e(self::COLUMNS[$c] ?? $c).'</th>', $columns));
        $body = '';
        foreach ($report['rows'] as $row) {
            $body .= '<tr>'.implode('', array_map(fn (string $c): string => '<td>'.e($this->cell($row[$c] ?? null)).'</td>', $columns)).'</tr>';
        }
        $meta = implode('', array_map(static fn (array $l): string => '<p>'.e(implode(' — ', $l)).'</p>', $this->preamble($report)));
        $html = '<style>body{direction:rtl;font-size:9pt}table{border-collapse:collapse;width:100%}th,td{border:0.5pt solid #555;padding:3pt}th{background:#e8edf3}</style>'
            .'<h2>'.e(self::TITLES[$report['type']] ?? $report['type']).'</h2>'.$meta.'<table><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table>';

        $mpdf = new Mpdf($this->pdfConfig());
        $mpdf->SetDirectionality('rtl');
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->WriteHTML($html);

        return $mpdf->OutputBinaryData();
    }

    /**
     * @return array<string, mixed>
     */
    private function pdfConfig(): array
    {
        $config = ['mode' => 'utf-8', 'format' => 'A4-L', 'tempDir' => storage_path('app/mpdf'), 'default_font' => 'dejavusans'];
        // Use Tahoma (an approved family) when the server has it; otherwise mPDF's bundled Arabic-capable font.
        $tahoma = (string) config('attendance.pdf_font_path', 'C:\\Windows\\Fonts\\tahoma.ttf');
        if ($tahoma !== '' && is_file($tahoma)) {
            $config['fontDir'] = [dirname($tahoma)];
            $config['fontdata'] = ['tahoma' => ['R' => basename($tahoma), 'useOTL' => 0xFF, 'useKashida' => 75]];
            $config['default_font'] = 'tahoma';
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string>>
     */
    private function preamble(array $report): array
    {
        $lines = [
            ['تقرير الحضور', self::TITLES[$report['type']] ?? (string) $report['type']],
            ['تاريخ الاستخراج', (string) $report['generated_at']],
            ['المعايير', (string) json_encode($report['filters'], JSON_UNESCAPED_UNICODE)],
            ['طريقة الاحتساب', (string) $report['formula']],
        ];
        if (! empty($report['truncated'])) {
            $lines[] = ['تنبيه', 'تجاوز التصدير الحد الأقصى؛ ضيّق المعايير للحصول على كامل البيانات.'];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    private function columns(array $report): array
    {
        return array_values(array_filter(array_keys($report['rows'][0] ?? []), static fn (string $c): bool => ! in_array($c, self::HIDDEN, true)));
    }

    private function cell(mixed $value): string
    {
        return is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
