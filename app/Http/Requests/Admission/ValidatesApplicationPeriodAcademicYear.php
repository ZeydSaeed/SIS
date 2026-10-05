<?php

namespace App\Http\Requests\Admission;

use App\Application\Admission\Support\ApplicationPeriodAcademicYearGuard;
use App\Database\SchemaHelper;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

trait ValidatesApplicationPeriodAcademicYear
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $yearId = (int) $this->input('academic_year_id');
            $startDate = (string) $this->input('start_date');
            $endDate = $this->filled('end_date') ? (string) $this->input('end_date') : null;

            if ($yearId < 1 || $startDate === '') {
                return;
            }

            try {
                app(ApplicationPeriodAcademicYearGuard::class)->assertDatesFit(
                    $yearId,
                    $startDate,
                    $endDate,
                );
            } catch (DomainException $exception) {
                if ($exception->getMessage() === 'Academic year not found.') {
                    $validator->errors()->add('academic_year_id', 'السنة الدراسية غير موجودة.');

                    return;
                }

                $message = 'تاريخ البداية وتاريخ النهاية يجب أن يقعا داخل السنة الدراسية المختارة.';
                $validator->errors()->add('start_date', $message);
                if ($endDate !== null) {
                    $validator->errors()->add('end_date', $message);
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'academic_year_id.required' => 'السنة الدراسية مطلوبة.',
            'academic_year_id.exists' => 'السنة الدراسية غير موجودة.',
        ];
    }

    protected function academicYearIdRule(): array
    {
        return [
            'required',
            'integer',
            'min:1',
            // Prefix the connection: Laravel reads "academic.academic_years" as connection "academic".
            'exists:'.DB::getDefaultConnection().'.'.SchemaHelper::qualified('academic', 'academic_years').',id',
        ];
    }
}
