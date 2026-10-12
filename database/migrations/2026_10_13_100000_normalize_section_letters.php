<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «الشعب» — one source of truth A / B / C (2026-10-13). Data only, no schema change:
 * section names spelled in Arabic (أ / ب / ج, «شعبة أ») become the letter, and the code follows (SEC-A …)
 * when that code is free in the class. Ids, links and history are untouched. Names that are not a section
 * letter are left as they are (the application refuses them on the next edit).
 *
 * Reversible: down() maps the letters back to the Arabic spelling (codes keep the SEC-<letter> form,
 * which they had before for seeded data).
 */
return new class extends Migration
{
    private const TO_LETTER = [
        'A' => 'A', 'أ' => 'A', 'ا' => 'A', 'إ' => 'A', 'آ' => 'A', 'شعبة أ' => 'A', 'شعبة ا' => 'A', 'شعبة A' => 'A',
        'B' => 'B', 'ب' => 'B', 'شعبة ب' => 'B', 'شعبة B' => 'B',
        'C' => 'C', 'ج' => 'C', 'شعبة ج' => 'C', 'شعبة C' => 'C',
        'a' => 'A', 'b' => 'B', 'c' => 'C',
    ];

    private const TO_ARABIC = ['A' => 'أ', 'B' => 'ب', 'C' => 'ج'];

    public function up(): void
    {
        $table = SchemaHelper::qualified('enrollment', 'sections');
        foreach (DB::table($table)->orderBy('id')->get(['id', 'class_id', 'code', 'name']) as $section) {
            $letter = self::TO_LETTER[trim((string) $section->name)] ?? null;
            if ($letter === null) {
                continue;
            }
            $sameLetter = DB::table($table)->where('class_id', $section->class_id)->where('id', '!=', $section->id)
                ->get(['name', 'code'])
                ->contains(fn (object $other): bool => (self::TO_LETTER[trim((string) $other->name)] ?? null) === $letter || $other->code === 'SEC-'.$letter);
            // Two sections of one class spelling the same letter: left for a human decision (never merged here).
            if ($sameLetter) {
                continue;
            }
            DB::table($table)->where('id', $section->id)->update(['name' => $letter, 'code' => 'SEC-'.$letter]);
        }
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('enrollment', 'sections');
        foreach (self::TO_ARABIC as $letter => $arabic) {
            DB::table($table)->where('name', $letter)->update(['name' => $arabic]);
        }
    }
};
