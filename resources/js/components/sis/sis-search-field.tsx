import { memo, useEffect, useRef, useState } from 'react';
import { Search } from 'lucide-react';
import { t } from '@/i18n';

export const SIS_SEARCH_DEBOUNCE_MS = 300;

type SisSearchFieldProps = {
    committedQuery: string;
    label: string;
    placeholder: string;
    onCommit: (query: string) => void;
    onDraftChange?: (query: string) => void;
};

/**
 * App titlebar search — Google-style pill chrome with SIS mark (not Google mic).
 */
export const SisSearchField = memo(function SisSearchField({
    committedQuery,
    label,
    placeholder,
    onCommit,
    onDraftChange,
}: SisSearchFieldProps) {
    const i18n = t();
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

    const hasQuery = draft.trim() !== '';

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

    const clearSearch = () => {
        composingRef.current = false;
        setDraft('');
        onCommitRef.current('');
    };

    return (
        <label
            className={`sis-admission-search sis-admission-search--pill${hasQuery ? ' sis-admission-search--has-clear' : ''}`}
        >
            <span className="sr-only">{label}</span>
            <span className="sis-admission-search__icon" aria-hidden="true">
                <Search className="sis-admission-search__icon-svg" strokeWidth={2} />
            </span>
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
                onKeyDown={(event) => {
                    if (event.key === 'Escape' && draftRef.current.trim() !== '') {
                        event.preventDefault();
                        clearSearch();
                    }
                }}
            />
            {hasQuery ? (
                <button
                    type="button"
                    className="sis-admission-search__clear"
                    aria-label={i18n.common.clearSearch}
                    title={i18n.common.clearSearch}
                    onMouseDown={(event) => {
                        event.preventDefault();
                    }}
                    onClick={clearSearch}
                >
                    ×
                </button>
            ) : null}
            <span className="sis-admission-search__brand" aria-hidden="true" title={i18n.brand}>
                <span className="sis-admission-search__brand-mark" />
            </span>
        </label>
    );
});
