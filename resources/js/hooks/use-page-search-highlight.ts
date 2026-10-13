import { useEffect } from 'react';
import { normalizeForSearch, searchNeedle } from '@/lib/search-normalize';

const MAX_RANGES = 1500;
const SKIP = new Set(['SCRIPT', 'STYLE', 'TEXTAREA', 'INPUT', 'SELECT']);

type HighlightRegistry = {
    set: (name: string, value: unknown) => void;
    delete: (name: string) => void;
};

/**
 * Titlebar search → every occurrence in the page is marked yellow (CSS Custom Highlight API — nothing in the DOM is
 * changed, so React is never disturbed). `filterRows`: for pages that registered no search of their own, table rows
 * that do not contain the word are hidden. Re-applied whenever the page content changes.
 */
export function usePageSearchHighlight(
    query: string,
    filterRows: boolean,
): void {
    useEffect(() => {
        const needle = searchNeedle(query);
        const registry = (
            typeof CSS !== 'undefined'
                ? (CSS as unknown as { highlights?: HighlightRegistry })
                      .highlights
                : undefined
        ) as HighlightRegistry | undefined;
        const Hl = (
            window as unknown as {
                Highlight?: new (...ranges: Range[]) => unknown;
            }
        ).Highlight;
        const hiddenRows = new Set<HTMLTableRowElement>();

        const root = (): Element | null =>
            document.querySelector('.sis-page-surface') ??
            document.querySelector('main');

        const showRows = () => {
            hiddenRows.forEach((row) => {
                row.hidden = false;
            });
            hiddenRows.clear();
        };

        const clear = () => {
            registry?.delete('sis-search');
            showRows();
        };

        const run = () => {
            const host = root();
            if (needle === '' || host === null) {
                clear();

                return;
            }
            const ranges: Range[] = [];
            const walker = document.createTreeWalker(
                host,
                NodeFilter.SHOW_TEXT,
            );
            for (
                let node = walker.nextNode();
                node !== null && ranges.length < MAX_RANGES;
                node = walker.nextNode()
            ) {
                const parent = node.parentElement;
                if (parent === null || SKIP.has(parent.tagName)) {
                    continue;
                }
                const text = node.nodeValue ?? '';
                if (text.trim() === '') {
                    continue;
                }
                const { norm, map } = normalizeForSearch(text);
                let at = norm.indexOf(needle);
                while (at !== -1 && ranges.length < MAX_RANGES) {
                    const range = document.createRange();
                    range.setStart(node, map[at]);
                    range.setEnd(node, map[at + needle.length - 1] + 1);
                    ranges.push(range);
                    at = norm.indexOf(needle, at + needle.length);
                }
            }
            if (Hl && registry) {
                if (ranges.length > 0) {
                    registry.set('sis-search', new Hl(...ranges));
                } else {
                    registry.delete('sis-search');
                }
            }
            if (filterRows) {
                host.querySelectorAll<HTMLTableRowElement>('tbody tr').forEach(
                    (row) => {
                        const match = normalizeForSearch(
                            row.textContent ?? '',
                        ).norm.includes(needle);
                        row.hidden = !match;
                        if (match) {
                            hiddenRows.delete(row);
                        } else {
                            hiddenRows.add(row);
                        }
                    },
                );
            } else {
                showRows();
            }
        };

        run();
        let timer = 0;
        const observer = new MutationObserver(() => {
            window.clearTimeout(timer);
            timer = window.setTimeout(run, 120);
        });
        const host = root();
        if (host !== null && needle !== '') {
            observer.observe(host, {
                childList: true,
                subtree: true,
                characterData: true,
            });
        }

        return () => {
            observer.disconnect();
            window.clearTimeout(timer);
            clear();
        };
    }, [query, filterRows]);
}
