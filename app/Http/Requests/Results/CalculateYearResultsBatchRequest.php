<?php

namespace App\Http\Requests\Results;

use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use Illuminate\Foundation\Http\FormRequest;

/** Calculate the operational annual results or year GPA of a class / section — results.calculate. */
class CalculateYearResultsBatchRequest extends FormRequest
{
    use ProcessYearResultsBatchRules;

    public function authorize(): bool
    {
        return $this->user()?->can('calculate', TermResultRecord::class) ?? false;
    }
}
