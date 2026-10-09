<?php

namespace Tests\Unit\Domain;

use App\Domain\Organization\Data\RoomDetails;
use App\Domain\Organization\ValueObjects\RoomKind;
use App\Domain\Shared\Services\AbbreviationSuggester;
use App\Domain\Shared\ValueObjects\DisplayAppearance;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** «الاختصار واللون»: one rule set for every entity the timetable shows. */
final class DisplayAppearanceTest extends TestCase
{
    #[Test]
    public function abbreviations_are_trimmed_and_blank_means_default(): void
    {
        $this->assertSame('ر.ح', DisplayAppearance::of('  ر.ح  ', null)->abbreviation);
        $this->assertNull(DisplayAppearance::of('   ', 10)->abbreviation);
        $this->assertSame('نور الهدى', DisplayAppearance::of("نور \t الهدى", null)->abbreviation);
    }

    #[Test]
    public function length_and_hue_are_bounded(): void
    {
        $this->assertNull(DisplayAppearance::of(str_repeat('أ', 20), 359)->rejection());
        $this->assertSame('appearance.abbreviation_too_long', DisplayAppearance::of(str_repeat('أ', 21), null)->rejection());
        $this->assertSame('appearance.color_invalid', DisplayAppearance::of(null, 360)->rejection());
        $this->assertSame('appearance.color_invalid', DisplayAppearance::of(null, -1)->rejection());
        $this->assertSame(['abbreviation' => null, 'color_hue' => null], DisplayAppearance::of('', null)->toArray());
    }

    #[Test]
    public function people_get_their_given_name_including_compound_names(): void
    {
        $s = new AbbreviationSuggester;

        $this->assertSame('عباس', $s->suggest('عباس فاضل حسين', AbbreviationSuggester::PERSON));
        $this->assertSame('عبد الأمير', $s->suggest('عبد الأمير كاظم', AbbreviationSuggester::PERSON));
        $this->assertSame('نور الهدى', $s->suggest('نور الهدى علي', AbbreviationSuggester::PERSON));
        $this->assertNull($s->suggest('   ', AbbreviationSuggester::PERSON));
    }

    #[Test]
    public function labels_keep_short_names_and_use_initials_for_long_ones(): void
    {
        $s = new AbbreviationSuggester;

        $this->assertSame('رياضيات', $s->suggest('رياضيات'));
        $this->assertSame('م.ح', $s->suggest('مبادئ حاسوب'));
        $this->assertSame('ت.ح', $s->suggest('تجميع الحاسوب'));
        $this->assertSame('الطبي', $s->suggest('الطبيعيات'));
        $this->assertLessThanOrEqual(20, mb_strlen((string) $s->suggest(str_repeat('كلمة ', 40))));
    }

    #[Test]
    public function a_room_keeps_the_solver_class_in_step_with_practical_support(): void
    {
        $details = new RoomDetails('مختبر الحاسوب', DisplayAppearance::of('م.ح', 190), ' 12 ', 3, 30, 'A', 1, null, null, true, '', null, ' ');
        $columns = $details->toColumns();

        $this->assertSame(2, $columns['room_type']);
        $this->assertTrue($columns['supports_practical']);
        $this->assertSame('12', $columns['room_number']);
        $this->assertNull($columns['equipment']);
        $this->assertNull($columns['notes']);
        $this->assertTrue(RoomKind::Workshop->practicalByDefault());
        $this->assertFalse(RoomKind::Hall->practicalByDefault());
        $this->assertSame(1, RoomKind::legacyRoomType(false));
    }
}
