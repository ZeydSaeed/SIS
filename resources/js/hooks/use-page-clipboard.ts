/**
 * Page clipboard for the global Home ribbon — works across Inertia page content.
 * Tracks selection outside chrome (and in chrome text fields); never mutates
 * React text nodes outside editable controls (security + UI contract).
 */

const CHROME_SELECTOR =
    '.sis-chrome, .sis-ribbon, .sis-titlebar, [data-slot="sidebar"], [data-sidebar="sidebar"]';
const SURFACE_SELECTOR =
    '.sis-page-surface, [role="dialog"], [data-slot="dialog-content"], [data-slot="sheet-content"]';

type FieldTarget = {
    kind: 'field';
    element: HTMLInputElement | HTMLTextAreaElement;
    start: number;
    end: number;
};

type DomTarget = {
    kind: 'dom';
    range: Range;
    text: string;
};

type ClipboardTarget = FieldTarget | DomTarget;

let lastTarget: ClipboardTarget | null = null;
let lastEditable: HTMLInputElement | HTMLTextAreaElement | HTMLElement | null = null;
let lastSelectedText = '';
let sessionClipboard = '';
let initialized = false;

const isChrome = (node: EventTarget | null): boolean =>
    node instanceof Element && Boolean(node.closest(CHROME_SELECTOR));

const closestSurface = (element: Element): HTMLElement | null => {
    const surface = element.closest(SURFACE_SELECTOR);

    return surface instanceof HTMLElement ? surface : null;
};

/** True when the user has a non-empty text selection (for row-click guards). */
export function hasPageTextSelection(): boolean {
    const active = document.activeElement;

    if (isTextField(active)) {
        const start = active.selectionStart ?? 0;
        const end = active.selectionEnd ?? start;

        return start !== end;
    }

    const selection = window.getSelection();

    return Boolean(selection && !selection.isCollapsed && selection.toString() !== '');
}

const isPasswordField = (element: HTMLInputElement | HTMLTextAreaElement): boolean =>
    element instanceof HTMLInputElement && element.type === 'password';

const isTextField = (
    element: Element | null,
): element is HTMLInputElement | HTMLTextAreaElement => {
    if (element instanceof HTMLTextAreaElement) {
        return !element.disabled;
    }

    if (!(element instanceof HTMLInputElement) || element.disabled) {
        return false;
    }

    const type = element.type;

    return (
        type === 'text' ||
        type === 'search' ||
        type === 'url' ||
        type === 'tel' ||
        type === 'email' ||
        type === 'password' ||
        type === 'number' ||
        type === '' ||
        type === 'date' ||
        type === 'datetime-local' ||
        type === 'month' ||
        type === 'time' ||
        type === 'week'
    );
};

const isContentEditable = (element: Element | null): element is HTMLElement =>
    element instanceof HTMLElement && element.isContentEditable;

const isAllowedFieldHost = (element: Element): boolean =>
    Boolean(closestSurface(element) || isChrome(element));

const isAllowedDomHost = (element: Element): boolean => {
    if (isChrome(element) && !isTextField(element) && !isContentEditable(element)) {
        return false;
    }

    return Boolean(closestSurface(element) || element.closest('body'));
};

const cloneRange = (range: Range): Range => range.cloneRange();

const captureField = (element: HTMLInputElement | HTMLTextAreaElement): FieldTarget => {
    const start = element.selectionStart ?? 0;
    const end = element.selectionEnd ?? start;

    return { kind: 'field', element, start, end };
};

const captureFromDocument = (): ClipboardTarget | null => {
    const active = document.activeElement;

    // Chrome text fields (titlebar search) must remain copy/cut/paste targets.
    if (isTextField(active) && isAllowedFieldHost(active)) {
        lastEditable = active;

        return captureField(active);
    }

    if (isChrome(active) && !isTextField(active) && !isContentEditable(active)) {
        return lastTarget;
    }

    if (isContentEditable(active) && isAllowedDomHost(active)) {
        lastEditable = active;
        const selection = window.getSelection();

        if (selection && selection.rangeCount > 0 && active.contains(selection.anchorNode)) {
            const range = cloneRange(selection.getRangeAt(0));
            const text = range.toString();

            return { kind: 'dom', range, text };
        }
    }

    const selection = window.getSelection();

    if (
        selection &&
        !selection.isCollapsed &&
        selection.rangeCount > 0 &&
        selection.anchorNode
    ) {
        const anchor =
            selection.anchorNode instanceof Element
                ? selection.anchorNode
                : selection.anchorNode.parentElement;

        if (anchor && isAllowedDomHost(anchor) && !isChrome(anchor)) {
            const range = cloneRange(selection.getRangeAt(0));
            const text = range.toString();

            if (text !== '') {
                lastSelectedText = text;
            }

            return { kind: 'dom', range, text };
        }
    }

    return lastTarget;
};

const refreshTarget = (): ClipboardTarget | null => {
    const next = captureFromDocument();

    if (next) {
        lastTarget = next;

        if (next.kind === 'field') {
            const text = selectedText(next);

            if (text !== '') {
                lastSelectedText = text;
            }
        } else if (next.text !== '') {
            lastSelectedText = next.text;
        }
    }

    return lastTarget;
};

const selectedText = (target: ClipboardTarget | null): string => {
    if (!target) {
        return lastSelectedText;
    }

    if (target.kind === 'field') {
        if (!document.contains(target.element) || isPasswordField(target.element)) {
            return lastSelectedText;
        }

        const { value } = target.element;
        const start = Math.min(target.start, target.end);
        const end = Math.max(target.start, target.end);

        if (start === end) {
            return lastSelectedText;
        }

        return value.slice(start, end);
    }

    try {
        const live = target.range.toString();

        return live !== '' ? live : target.text || lastSelectedText;
    } catch {
        return target.text || lastSelectedText;
    }
};

const writeClipboard = async (text: string): Promise<boolean> => {
    if (text === '') {
        return false;
    }

    sessionClipboard = text;
    lastSelectedText = text;

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);

            return true;
        }
    } catch {
        // Fall through to legacy path.
    }

    try {
        const holder = document.createElement('textarea');
        holder.value = text;
        holder.setAttribute('readonly', '');
        holder.style.position = 'fixed';
        holder.style.top = '0';
        holder.style.left = '0';
        holder.style.width = '1px';
        holder.style.height = '1px';
        holder.style.padding = '0';
        holder.style.border = 'none';
        holder.style.outline = 'none';
        holder.style.boxShadow = 'none';
        holder.style.background = 'transparent';
        holder.style.opacity = '0';
        document.body.appendChild(holder);
        holder.focus({ preventScroll: true });
        holder.select();
        holder.setSelectionRange(0, text.length);
        const ok = document.execCommand('copy');
        document.body.removeChild(holder);

        return ok || sessionClipboard !== '';
    } catch {
        return sessionClipboard !== '';
    }
};

const readClipboard = async (): Promise<string> => {
    try {
        if (navigator.clipboard?.readText) {
            const text = await navigator.clipboard.readText();

            if (text !== '') {
                sessionClipboard = text;

                return text;
            }
        }
    } catch {
        // Permission may be denied outside a user gesture; use session fallback.
    }

    return sessionClipboard || lastSelectedText;
};

const dispatchInput = (element: HTMLElement): void => {
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
};

const restoreFieldSelection = (target: FieldTarget): boolean => {
    if (!document.contains(target.element) || target.element.disabled) {
        return false;
    }

    target.element.focus({ preventScroll: true });

    try {
        target.element.setSelectionRange(target.start, target.end);
    } catch {
        // Some input types (e.g. number) may reject setSelectionRange.
    }

    return true;
};

const restoreDomSelection = (target: DomTarget): boolean => {
    try {
        const selection = window.getSelection();

        if (!selection) {
            return false;
        }

        selection.removeAllRanges();
        selection.addRange(target.range);

        const root = target.range.commonAncestorContainer;
        const element = root instanceof Element ? root : root.parentElement;

        if (isContentEditable(element)) {
            element.focus({ preventScroll: true });
        }

        return true;
    } catch {
        return false;
    }
};

const resolvePasteField = (): HTMLInputElement | HTMLTextAreaElement | null => {
    const active = document.activeElement;

    if (isTextField(active) && isAllowedFieldHost(active) && !active.readOnly) {
        return active;
    }

    if (
        lastEditable instanceof HTMLInputElement ||
        lastEditable instanceof HTMLTextAreaElement
    ) {
        if (
            document.contains(lastEditable) &&
            !lastEditable.disabled &&
            !lastEditable.readOnly &&
            isAllowedFieldHost(lastEditable)
        ) {
            return lastEditable;
        }
    }

    return null;
};

const insertIntoField = (
    element: HTMLInputElement | HTMLTextAreaElement,
    text: string,
    start: number,
    end: number,
): void => {
    const value = element.value;
    const from = Math.min(start, end);
    const to = Math.max(start, end);
    element.value = value.slice(0, from) + text + value.slice(to);
    const caret = from + text.length;

    try {
        element.setSelectionRange(caret, caret);
    } catch {
        // ignore
    }

    dispatchInput(element);
};

const deleteFieldSelection = (target: FieldTarget): void => {
    if (target.element.readOnly || isPasswordField(target.element)) {
        return;
    }

    if (target.start === target.end) {
        return;
    }

    insertIntoField(target.element, '', target.start, target.end);
    target.start = Math.min(target.start, target.end);
    target.end = target.start;
};

const insertIntoContentEditable = (range: Range, text: string): void => {
    range.deleteContents();
    const node = document.createTextNode(text);
    range.insertNode(node);
    range.setStartAfter(node);
    range.collapse(true);

    const selection = window.getSelection();
    selection?.removeAllRanges();
    selection?.addRange(range);
};

/**
 * Keep page selection when clicking ribbon clipboard buttons.
 * Call from button `onMouseDown` with preventDefault.
 */
export function preservePageClipboardSelection(): void {
    refreshTarget();
}

export async function pageClipboardCopy(): Promise<void> {
    const target = refreshTarget();
    const text = selectedText(target);

    if (text === '') {
        return;
    }

    if (target?.kind === 'field') {
        restoreFieldSelection(target);
    } else if (target?.kind === 'dom') {
        restoreDomSelection(target);
    }

    await writeClipboard(text);
}

export async function pageClipboardCut(): Promise<void> {
    const target = refreshTarget();
    const text = selectedText(target);

    if (text === '') {
        return;
    }

    await writeClipboard(text);

    if (!target) {
        return;
    }

    if (target.kind === 'field') {
        if (!restoreFieldSelection(target) || target.element.readOnly) {
            return;
        }

        deleteFieldSelection(target);

        return;
    }

    const root = target.range.commonAncestorContainer;
    const host = root instanceof Element ? root : root.parentElement;

    if (!isContentEditable(host)) {
        // Read-only page text: cut acts as copy only (no DOM mutation).
        return;
    }

    if (!restoreDomSelection(target)) {
        return;
    }

    target.range.deleteContents();
}

export async function pageClipboardPaste(): Promise<void> {
    const text = await readClipboard();

    if (text === '') {
        return;
    }

    const target = refreshTarget();

    if (target?.kind === 'field' && !target.element.readOnly && !isPasswordField(target.element)) {
        if (!restoreFieldSelection(target)) {
            return;
        }

        insertIntoField(target.element, text, target.start, target.end);
        lastTarget = captureField(target.element);

        return;
    }

    if (target?.kind === 'dom') {
        const root = target.range.commonAncestorContainer;
        const host = root instanceof Element ? root : root.parentElement;

        if (isContentEditable(host) && restoreDomSelection(target)) {
            insertIntoContentEditable(target.range, text);

            return;
        }
    }

    const field = resolvePasteField();

    if (!field || isPasswordField(field)) {
        return;
    }

    field.focus({ preventScroll: true });
    const start = field.selectionStart ?? field.value.length;
    const end = field.selectionEnd ?? start;
    insertIntoField(field, text, start, end);
    lastEditable = field;
    lastTarget = captureField(field);
}

const onSelectionChange = (): void => {
    const active = document.activeElement;

    if (isChrome(active) && !isTextField(active)) {
        return;
    }

    refreshTarget();
};

const onSelectionOrFocus = (event: Event): void => {
    if (isChrome(event.target) && !isTextField(event.target as Element)) {
        return;
    }

    refreshTarget();
};

export function initializePageClipboard(): void {
    if (typeof window === 'undefined' || initialized) {
        return;
    }

    initialized = true;
    document.addEventListener('selectionchange', onSelectionChange);
    document.addEventListener('focusin', onSelectionOrFocus);
    document.addEventListener('pointerup', onSelectionOrFocus, true);
}

export function teardownPageClipboard(): void {
    if (!initialized) {
        return;
    }

    document.removeEventListener('selectionchange', onSelectionChange);
    document.removeEventListener('focusin', onSelectionOrFocus);
    document.removeEventListener('pointerup', onSelectionOrFocus, true);
    initialized = false;
    lastTarget = null;
    lastEditable = null;
    lastSelectedText = '';
}
