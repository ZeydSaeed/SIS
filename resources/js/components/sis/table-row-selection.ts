export type TableRowSelection = {
    selectedId: number | null;
    checkedIds: number[];
};

/** Clicking a row makes that row the selection for later actions. */
export function selectTableRow(id: number): TableRowSelection {
    return { selectedId: id, checkedIds: [id] };
}

/** Checking a row also makes it the current selection. */
export function toggleTableRowChecked(
    checkedIds: readonly number[],
    id: number,
): TableRowSelection {
    const isChecked = checkedIds.includes(id);
    const next = isChecked ? checkedIds.filter((item) => item !== id) : [...checkedIds, id];

    return {
        checkedIds: next,
        selectedId: isChecked ? (next[next.length - 1] ?? null) : id,
    };
}

export function toggleTableSelectAll(
    checkedIds: readonly number[],
    rowIds: readonly number[],
    selectedId: number | null,
): TableRowSelection {
    const visible = checkedIds.filter((id) => rowIds.includes(id));

    if (rowIds.length > 0 && visible.length === rowIds.length) {
        return { checkedIds: [], selectedId: null };
    }

    return {
        checkedIds: [...rowIds],
        selectedId:
            selectedId !== null && rowIds.includes(selectedId) ? selectedId : (rowIds[0] ?? null),
    };
}

export function tableActionIds(
    checkedIds: readonly number[],
    selectedId: number | null,
): number[] {
    if (checkedIds.length > 0) {
        return [...checkedIds];
    }

    return selectedId !== null ? [selectedId] : [];
}
