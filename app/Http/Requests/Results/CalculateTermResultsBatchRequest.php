<?php

namespace App\Http\Requests\Results;

use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use Illuminate\Foundation\Http\FormRequest;

/** Calculate the operational term results of a class / section — results.calculate. */
class CalculateTermResultsBatchRequest extends FormRequest
{
    use ProcessTermResultsBatchRules;

    public function authorize(): bool
    {
        return $this->user()?->can('calculate', TermResultRecord::class) ?? false;
    }
}
