import { memo, useEffect, useRef, useState } from 'react';

export const SIS_SEARCH_DEBOUNCE_MS = 300;

type SisSearchFieldProps = {
    committedQuery: string;
    label: string;
    placeholder: string;
    onCommit: (query: string) => void;
    onDraftChange?: (query: string) => void;
};

export const SisSearchField = memo(function SisSearchField({
    committedQuery,
    label,
    placeholder,
    onCommit,
    onDraftChange,
}: SisSearchFieldProps) {
    const [draft, setDraft] = useState(committedQuery);
    const draftRef = useRef(draft);
    const committedRef = useRef(committedQuery);
    const focusedRef = useRef(false);
    const composingRef = useRef(false);
    const onCommitRef = useRef(onCommit);
    const onDraftChangeRef = useRef(onDraftChange);

    draftRef.current = draft;
    committedRef.current = committedQuery;
    onCommitRef.current = onCommit;
    onDraftChangeRef.current = onDraftChange;

    useEffect(() => {
        onDraftChangeRef.current?.(draft);
    }, [draft]);

    useEffect(() => {
        if (focusedRef.current || composingRef.current) {
            return;
        }

        setDraft(committedQuery);
    }, [committedQuery]);

    useEffect(() => {
        if (composingRef.current) {
            return;
        }

        if (draft.trim() === committedQuery.trim()) {
            return;
        }

        const timer = window.setTimeout(() => {
            onCommitRef.current(draftRef.current);
        }, SIS_SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [committedQuery, draft]);

    return (
        <label className="sis-admission-search">
            <span className="sr-only">{label}</span>
            <input
                type="search"
                value={draft}
                maxLength={80}
                autoComplete="off"
                autoCorrect="off"
                autoCapitalize="off"
                spellCheck={false}
                placeholder={placeholder}
                aria-label={label}
                className="sis-admission-search__input"
                onFocus={() => {
                    focusedRef.current = true;
                }}
                onBlur={() => {
                    focusedRef.current = false;

                    if (draftRef.current.trim() !== committedRef.current.trim()) {
                        onCommitRef.current(draftRef.current);
                    }
                }}
                onCompositionStart={() => {
                    composingRef.current = true;
                }}
                onCompositionEnd={(event) => {
                    composingRef.current = false;
                    setDraft(event.currentTarget.value);
                }}
                onChange={(event) => setDraft(event.target.value)}
            />
        </label>
    );
});
