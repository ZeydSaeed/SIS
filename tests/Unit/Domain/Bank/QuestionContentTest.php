<?php

namespace Tests\Unit\Domain\Bank;

use App\Domain\Bank\Exceptions\QuestionBankException;
use App\Domain\Bank\Services\QuestionReviewRules;
use App\Domain\Bank\Support\QuestionIdempotencyGuard;
use App\Domain\Bank\ValueObjects\QuestionContent;
use App\Domain\Bank\ValueObjects\QuestionVersionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** EXAMIS 7.3a — content of a question version (DEC-048/049) and the lifecycle rules (DEC-047/056/057). */
class QuestionContentTest extends TestCase
{
    #[Test]
    public function a_single_choice_question_is_normalised_with_option_keys(): void
    {
        $c = QuestionContent::fromInput('single_choice', "  ما عاصمة اليمن؟\r\n ", '2', ['options' => ['صنعاء', ' عدن '], 'correct' => ['0']]);

        $this->assertSame('ما عاصمة اليمن؟', $c->stem);
        $this->assertSame('2.00', $c->maxMark);
        $this->assertSame(['options' => [['key' => 'A', 'text' => 'صنعاء'], ['key' => 'B', 'text' => 'عدن']], 'correct' => ['A']], $c->payload);
        $this->assertSame(['options' => $c->payload['options']], $c->publicPayload(), 'the public payload has no answer key');
    }

    #[Test]
    public function a_multiple_choice_question_keeps_every_correct_option_once_and_sorted(): void
    {
        $c = QuestionContent::fromInput('multiple_choice', 'اختر الأعداد الأولية', 3, ['options' => ['2', '4', '5'], 'correct' => [2, 0, 2]]);

        $this->assertSame(['A', 'C'], $c->payload['correct']);
    }

    #[Test]
    public function true_false_and_long_answer_have_their_own_payloads(): void
    {
        $tf = QuestionContent::fromInput('true_false', 'الماء يغلي عند 100', 1, ['answer' => 'true', 'options' => ['ignored']]);
        $this->assertSame(['answer' => true], $tf->payload);
        $this->assertSame([], $tf->publicPayload());

        $long = QuestionContent::fromInput('long_answer', 'اشرح دورة الماء', '10.5', ['guidance' => " التبخر ثم التكاثف \x07"]);
        $this->assertSame(['guidance' => 'التبخر ثم التكاثف'], $long->payload, 'control characters are removed (plain text)');
        $this->assertSame('10.50', $long->maxMark);
        $this->assertSame([], $long->publicPayload());
    }

    /** @return iterable<string, array{string, string, float|int|string, array<string, mixed>}> */
    public static function invalidContent(): iterable
    {
        yield 'unknown type' => ['essay', 'سؤال', 1, []];
        yield 'empty stem' => ['long_answer', "  \n ", 1, []];
        yield 'stem too long' => ['long_answer', str_repeat('أ', 5001), 1, []];
        yield 'one option' => ['single_choice', 'سؤال', 1, ['options' => ['أ'], 'correct' => [0]]];
        yield 'nine options' => ['single_choice', 'سؤال', 1, ['options' => ['1', '2', '3', '4', '5', '6', '7', '8', '9'], 'correct' => [0]]];
        yield 'empty option' => ['single_choice', 'سؤال', 1, ['options' => ['أ', ' '], 'correct' => [0]]];
        yield 'duplicate options' => ['single_choice', 'سؤال', 1, ['options' => ['نعم', 'نعم'], 'correct' => [0]]];
        yield 'single with two correct' => ['single_choice', 'سؤال', 1, ['options' => ['أ', 'ب'], 'correct' => [0, 1]]];
        yield 'single with none correct' => ['single_choice', 'سؤال', 1, ['options' => ['أ', 'ب'], 'correct' => []]];
        yield 'multiple with none correct' => ['multiple_choice', 'سؤال', 1, ['options' => ['أ', 'ب'], 'correct' => []]];
        yield 'correct out of range' => ['single_choice', 'سؤال', 1, ['options' => ['أ', 'ب'], 'correct' => [2]]];
        yield 'correct not an index' => ['single_choice', 'سؤال', 1, ['options' => ['أ', 'ب'], 'correct' => ['A']]];
        yield 'true/false without answer' => ['true_false', 'سؤال', 1, []];
        yield 'zero mark' => ['long_answer', 'سؤال', 0, []];
        yield 'mark above 1000' => ['long_answer', 'سؤال', 1000.5, []];
        yield 'three decimals' => ['long_answer', 'سؤال', '1.255', []];
    }

    /** @param  array<string, mixed>  $input */
    #[Test]
    #[DataProvider('invalidContent')]
    public function invalid_content_is_refused(string $type, string $stem, float|int|string $mark, array $input): void
    {
        try {
            QuestionContent::fromInput($type, $stem, $mark, $input);
            $this->fail('expected bank.content_invalid');
        } catch (QuestionBankException $e) {
            $this->assertSame('bank.content_invalid', $e->errorCode());
        }
    }

    #[Test]
    public function only_the_author_changes_a_draft(): void
    {
        QuestionReviewRules::assertAuthorCanChange(QuestionVersionStatus::Draft, 7, 7);
        $this->assertCode('bank.not_author', static fn () => QuestionReviewRules::assertAuthorCanChange(QuestionVersionStatus::Draft, 7, 8));
        $this->assertCode('bank.invalid_state', static fn () => QuestionReviewRules::assertAuthorCanChange(QuestionVersionStatus::InReview, 7, 7));
    }

    #[Test]
    public function a_version_is_decided_under_review_by_someone_else_and_a_rejection_needs_a_note(): void
    {
        QuestionReviewRules::assertCanDecide(QuestionVersionStatus::InReview, 7, 8, 'approve', '');
        $this->assertCode('bank.four_eyes', static fn () => QuestionReviewRules::assertCanDecide(QuestionVersionStatus::InReview, 7, 7, 'approve', ''));
        $this->assertCode('bank.note_required', static fn () => QuestionReviewRules::assertCanDecide(QuestionVersionStatus::InReview, 7, 8, 'reject', ' '));
        $this->assertCode('bank.invalid_state', static fn () => QuestionReviewRules::assertCanDecide(QuestionVersionStatus::Draft, 7, 8, 'approve', ''));
        $this->assertCode('bank.decision_invalid', static fn () => QuestionReviewRules::assertCanDecide(QuestionVersionStatus::InReview, 7, 8, 'maybe', ''));
        $this->assertCode('bank.question_retired', static fn () => QuestionReviewRules::assertQuestionActive('retired'));
    }

    #[Test]
    public function the_idempotency_guard_needs_a_key_and_refuses_another_request_under_the_same_key(): void
    {
        $this->assertCode('bank.idempotency_key_required', static fn () => QuestionIdempotencyGuard::requireKey('  '));
        $a = QuestionIdempotencyGuard::fingerprint('X', ['b' => 1, 'a' => 2]);
        $this->assertSame($a, QuestionIdempotencyGuard::fingerprint('X', ['a' => 2, 'b' => 1]), 'key order does not matter');
        QuestionIdempotencyGuard::assertSameRequest([QuestionIdempotencyGuard::FINGERPRINT => $a], $a, 'X');
        $this->assertCode('bank.idempotency_conflict', static fn () => QuestionIdempotencyGuard::assertSameRequest([QuestionIdempotencyGuard::FINGERPRINT => $a], QuestionIdempotencyGuard::fingerprint('X', ['a' => 3]), 'X'));
        $this->assertCode('bank.idempotency_conflict', static fn () => QuestionIdempotencyGuard::assertSameRequest([], $a, 'X'));
    }

    private function assertCode(string $code, callable $call): void
    {
        try {
            $call();
            $this->fail("expected {$code}");
        } catch (QuestionBankException $e) {
            $this->assertSame($code, $e->errorCode());
        }
    }
}
