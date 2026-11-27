<?php

namespace App\Domain\Bank\ValueObjects;

use App\Domain\Bank\Exceptions\QuestionBankException;

/**
 * The content of one question version, validated and normalised (DEC-048/049: plain text, four types).
 *
 * Payload shapes stored in bank.question_versions.payload:
 *  - single_choice / multiple_choice: {"options": [{"key": "A", "text": "…"}, …], "correct": ["A", …]}
 *  - true_false: {"answer": true|false}
 *  - long_answer: {"guidance": "…"} (marking guidance, optional, may be empty)
 * The answer key (`correct`, `answer`, `guidance`) is only shown to authors and approvers.
 */
final readonly class QuestionContent
{
    public const STEM_MAX = 5000;

    public const OPTION_TEXT_MAX = 1000;

    public const OPTIONS_MIN = 2;

    public const OPTIONS_MAX = 8;

    public const MARK_MAX = 1000.0;

    private const OPTION_KEYS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    /** @param  array<string, mixed>  $payload */
    private function __construct(
        public QuestionType $type,
        public string $stem,
        public array $payload,
        public string $maxMark,
    ) {}

    /**
     * Builds content from user input: options arrive as a list of texts and `correct` as a list of 0-based indexes
     * (choice types); `answer` as a boolean (true/false); `guidance` as text (long answer).
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(string $type, string $stem, float|int|string $maxMark, array $input): self
    {
        $questionType = QuestionType::tryFrom($type) ?? throw QuestionBankException::invalidContent('unknown type');
        $stem = self::plainText($stem);
        if ($stem === '') {
            throw QuestionBankException::invalidContent('stem is empty');
        }
        if (mb_strlen($stem) > self::STEM_MAX) {
            throw QuestionBankException::invalidContent('stem is too long');
        }

        $payload = match ($questionType) {
            QuestionType::SingleChoice, QuestionType::MultipleChoice => self::choicePayload($questionType, $input),
            QuestionType::TrueFalse => self::trueFalsePayload($input),
            QuestionType::LongAnswer => self::longAnswerPayload($input),
        };

        return new self($questionType, $stem, $payload, self::mark($maxMark));
    }

    /**
     * Rebuilds content already stored (no re-validation of rules that may tighten later).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromStored(string $type, string $stem, array $payload, string $maxMark): self
    {
        return new self(
            QuestionType::tryFrom($type) ?? throw QuestionBankException::invalidContent('unknown type'),
            $stem,
            $payload,
            $maxMark,
        );
    }

    /**
     * The payload without the answer key, for people who may see the question but not its key.
     *
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        if ($this->type->hasOptions()) {
            return ['options' => $this->payload['options'] ?? []];
        }

        return [];
    }

    /** @return array<string, mixed> */
    public function canonical(): array
    {
        return ['type' => $this->type->value, 'stem' => $this->stem, 'payload' => $this->payload, 'max_mark' => $this->maxMark];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{options: list<array{key: string, text: string}>, correct: list<string>}
     */
    private static function choicePayload(QuestionType $type, array $input): array
    {
        $rawOptions = $input['options'] ?? null;
        if (! is_array($rawOptions) || ! array_is_list($rawOptions)) {
            throw QuestionBankException::invalidContent('options must be a list');
        }
        $count = count($rawOptions);
        if ($count < self::OPTIONS_MIN || $count > self::OPTIONS_MAX) {
            throw QuestionBankException::invalidContent('a choice question needs 2 to 8 options');
        }

        $options = [];
        $seen = [];
        foreach ($rawOptions as $index => $text) {
            if (! is_string($text)) {
                throw QuestionBankException::invalidContent('an option must be text');
            }
            $text = self::plainText($text);
            if ($text === '') {
                throw QuestionBankException::invalidContent('an option is empty');
            }
            if (mb_strlen($text) > self::OPTION_TEXT_MAX) {
                throw QuestionBankException::invalidContent('an option is too long');
            }
            $folded = mb_strtolower($text);
            if (isset($seen[$folded])) {
                throw QuestionBankException::invalidContent('two options are the same');
            }
            $seen[$folded] = true;
            $options[] = ['key' => self::OPTION_KEYS[$index], 'text' => $text];
        }

        $rawCorrect = $input['correct'] ?? null;
        if (! is_array($rawCorrect)) {
            throw QuestionBankException::invalidContent('correct options must be a list');
        }
        $correct = [];
        foreach ($rawCorrect as $index) {
            if (! is_int($index) && ! (is_string($index) && ctype_digit($index))) {
                throw QuestionBankException::invalidContent('a correct option is not an option index');
            }
            $index = (int) $index;
            if ($index < 0 || $index >= $count) {
                throw QuestionBankException::invalidContent('a correct option is out of range');
            }
            $correct[self::OPTION_KEYS[$index]] = true;
        }
        $correct = array_keys($correct);
        sort($correct);

        if ($type === QuestionType::SingleChoice && count($correct) !== 1) {
            throw QuestionBankException::invalidContent('a single choice question needs exactly one correct option');
        }
        if ($type === QuestionType::MultipleChoice && $correct === []) {
            throw QuestionBankException::invalidContent('a multiple choice question needs at least one correct option');
        }

        return ['options' => $options, 'correct' => $correct];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{answer: bool}
     */
    private static function trueFalsePayload(array $input): array
    {
        $answer = $input['answer'] ?? null;
        if (is_string($answer) && in_array($answer, ['true', 'false', '1', '0'], true)) {
            $answer = $answer === 'true' || $answer === '1';
        }
        if (is_int($answer) && ($answer === 0 || $answer === 1)) {
            $answer = $answer === 1;
        }
        if (! is_bool($answer)) {
            throw QuestionBankException::invalidContent('a true/false question needs its answer');
        }

        return ['answer' => $answer];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{guidance: string}
     */
    private static function longAnswerPayload(array $input): array
    {
        $guidance = $input['guidance'] ?? '';
        if (! is_string($guidance)) {
            throw QuestionBankException::invalidContent('marking guidance must be text');
        }
        $guidance = self::plainText($guidance);
        if (mb_strlen($guidance) > self::STEM_MAX) {
            throw QuestionBankException::invalidContent('marking guidance is too long');
        }

        return ['guidance' => $guidance];
    }

    private static function mark(float|int|string $value): string
    {
        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^\d{1,4}(\.\d{1,2})?$/', $value) !== 1) {
                throw QuestionBankException::invalidContent('the mark is not a number with at most 2 decimals');
            }
            $value = (float) $value;
        }
        $value = (float) $value;
        if (! is_finite($value) || $value <= 0 || $value > self::MARK_MAX || round($value, 2) !== $value) {
            throw QuestionBankException::invalidContent('the mark must be above 0 and at most 1000');
        }

        return number_format($value, 2, '.', '');
    }

    /** Plain text only (DEC-049): control characters other than new lines and tabs are removed; ends are trimmed. */
    private static function plainText(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = (string) preg_replace('/[^\P{C}\n\t]/u', '', $value);

        return trim($value);
    }
}
