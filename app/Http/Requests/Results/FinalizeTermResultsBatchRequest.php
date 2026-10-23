<?php

namespace App\Http\Requests\Results;

use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use Illuminate\Foundation\Http\FormRequest;

/** Finalize the official term results of a class / section — results.finalize. */
class FinalizeTermResultsBatchRequest extends FormRequest
{
    use ProcessTermResultsBatchRules;

    public function authorize(): bool
    {
        return $this->user()?->can('finalize', TermResultRecord::class) ?? false;
    }
}
