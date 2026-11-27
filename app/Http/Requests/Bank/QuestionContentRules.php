<?php

namespace App\Http\Requests\Bank;

use App\Domain\Bank\ValueObjects\QuestionContent;
use App\Domain\Bank\ValueObjects\QuestionType;
use Illuminate\Validation\Rule;

/** Shape of the content fields; the per-type rules (correct options, answer) live in QuestionContent. */
final class QuestionContentRules
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(QuestionType::class)],
            'stem' => ['required', 'string', 'max:'.QuestionContent::STEM_MAX],
            'max_mark' => ['required', 'numeric', 'gt:0', 'max:'.(int) QuestionContent::MARK_MAX, 'decimal:0,2'],
            'options' => ['nullable', 'array', 'max:'.QuestionContent::OPTIONS_MAX],
            'options.*' => ['string', 'max:'.QuestionContent::OPTION_TEXT_MAX],
            'correct' => ['nullable', 'array', 'max:'.QuestionContent::OPTIONS_MAX],
            'correct.*' => ['integer', 'min:0', 'max:'.(QuestionContent::OPTIONS_MAX - 1)],
            'answer' => ['nullable', 'boolean'],
            'guidance' => ['nullable', 'string', 'max:'.QuestionContent::STEM_MAX],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function content(array $validated): array
    {
        $answer = $validated['answer'] ?? null;

        return [
            'options' => array_values(array_map('strval', (array) ($validated['options'] ?? []))),
            'correct' => array_values(array_map('intval', (array) ($validated['correct'] ?? []))),
            'answer' => $answer === null ? null : filter_var($answer, FILTER_VALIDATE_BOOLEAN),
            'guidance' => (string) ($validated['guidance'] ?? ''),
        ];
    }
}
