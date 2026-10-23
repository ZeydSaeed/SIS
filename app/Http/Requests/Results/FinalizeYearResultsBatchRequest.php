<?php

namespace App\Http\Requests\Results;

use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use Illuminate\Foundation\Http\FormRequest;

/** Finalize (official) the annual results or year GPA of a class / section — results.finalize. */
class FinalizeYearResultsBatchRequest extends FormRequest
{
    use ProcessYearResultsBatchRules;

    public function authorize(): bool
    {
        return $this->user()?->can('finalize', TermResultRecord::class) ?? false;
    }
}
