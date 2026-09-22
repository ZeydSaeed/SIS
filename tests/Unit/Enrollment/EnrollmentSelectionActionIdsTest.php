<?php

namespace Tests\Unit\Enrollment;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mirrors resources/js/components/sis/table-row-selection.ts action-id resolution
 * used by enrollment multi-select / select-all bulk placement.
 */
final class EnrollmentSelectionActionIdsTest extends TestCase
{
    /**
     * @param  list<int>  $checkedIds
     * @return list<int>
     */
    private function tableActionIds(array $checkedIds, ?int $selectedId): array
    {
        if ($checkedIds !== []) {
            return array_values($checkedIds);
        }

        return $selectedId !== null ? [$selectedId] : [];
    }

    /**
     * @param  list<int>  $checkedIds
     * @param  list<int>  $rowIds
     * @return array{checkedIds: list<int>, selectedId: int|null}
     */
    private function toggleSelectAll(array $checkedIds, array $rowIds, ?int $selectedId): array
    {
        $visible = array_values(array_filter(
            $checkedIds,
            static fn (int $id): bool => in_array($id, $rowIds, true),
        ));

        if ($rowIds !== [] && count($visible) === count($rowIds)) {
            return ['checkedIds' => [], 'selectedId' => null];
        }

        return [
            'checkedIds' => $rowIds,
            'selectedId' => $selectedId !== null && in_array($selectedId, $rowIds, true)
                ? $selectedId
                : ($rowIds[0] ?? null),
        ];
    }

    #[Test]
    public function single_row_selection_resolves_one_action_id(): void
    {
        $this->assertSame([42], $this->tableActionIds([], 42));
        $this->assertSame([42], $this->tableActionIds([42], 42));
    }

    #[Test]
    public function multi_row_selection_resolves_all_checked_ids(): void
    {
        $this->assertSame([10, 20, 30], $this->tableActionIds([10, 20, 30], 10));
    }

    #[Test]
    public function select_all_then_action_ids_include_every_row(): void
    {
        $rowIds = [1, 2, 3, 4, 5];
        $next = $this->toggleSelectAll([], $rowIds, null);

        $this->assertSame($rowIds, $next['checkedIds']);
        $this->assertSame(1, $next['selectedId']);
        $this->assertSame($rowIds, $this->tableActionIds($next['checkedIds'], $next['selectedId']));
    }

    #[Test]
    public function select_all_toggle_off_clears_action_ids(): void
    {
        $rowIds = [1, 2, 3];
        $selected = $this->toggleSelectAll([], $rowIds, null);
        $cleared = $this->toggleSelectAll($selected['checkedIds'], $rowIds, $selected['selectedId']);

        $this->assertSame([], $cleared['checkedIds']);
        $this->assertNull($cleared['selectedId']);
        $this->assertSame([], $this->tableActionIds($cleared['checkedIds'], $cleared['selectedId']));
    }
}
