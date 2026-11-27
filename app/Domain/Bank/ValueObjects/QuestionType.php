<?php

namespace App\Domain\Bank\ValueObjects;

/** Question types of the first release (DEC-048): bank.question_versions.type. */
enum QuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case LongAnswer = 'long_answer';

    public function hasOptions(): bool
    {
        return $this === self::SingleChoice || $this === self::MultipleChoice;
    }

    /** Marked by the server from the answer key; a long answer is marked by a person. */
    public function isAutoMarked(): bool
    {
        return $this !== self::LongAnswer;
    }
}
