import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';

/**
 * «رجوع» / «تقدم» of the title bar: the unsaved edits of the open page — what was typed, chosen, ticked or cleared in
 * its fields — up to 30 steps back and 30 forward, like Office's undo / redo lists. Local only: a step never reverses
 * a saved operation. The list starts over when another page opens and when the page saves (a successful non-GET visit).
 *
 * Covers native fields (input, textarea, select, checkbox, radio) anywhere on the page. A value is written back the
 * way a user would write it (native setter + input/change event, or a click for check boxes), so React state follows.
 */
type Field = HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;

/** A radio step is one radio of a group taking over from another: undo turns `previous` back on. */
export type EditStep = {
    field: Field;
    label: string;
    before: string | boolean;
    after: string | boolean;
    previous?: HTMLInputElement;
    at: number;
};

type State = { done: EditStep[]; undone: EditStep[] };

export const EDIT_STEPS = 30;
/** Typing into the same field within this window is one step. */
const MERGE_MS = 1200;
const SKIPPED_TYPES = new Set([
    'button',
    'submit',
    'reset',
    'file',
    'hidden',
    'image',
    'password',
    'search',
]);

let state: State = { done: [], undone: [] };
let started = false;
let applying = false;
let visitMethod = 'get';
const known = new WeakMap<Field, string | boolean>();
const listeners = new Set<() => void>();

function emit(next: State): void {
    state = next;
    listeners.forEach((listener) => listener());
}

function asField(target: EventTarget | null): Field | null {
    // The title bar (search) and anything marked off are not page content.
    if (
        !(target instanceof Element) ||
        target.closest('.sis-titlebar, [data-edit-history="off"]')
    ) {
        return null;
    }
    if (
        target instanceof HTMLTextAreaElement ||
        target instanceof HTMLSelectElement
    ) {
        return target;
    }
    if (target instanceof HTMLInputElement && !SKIPPED_TYPES.has(target.type)) {
        return target;
    }

    return null;
}

const isToggle = (field: Field): field is HTMLInputElement =>
    field instanceof HTMLInputElement &&
    (field.type === 'checkbox' || field.type === 'radio');

const valueOf = (field: Field): string | boolean =>
    isToggle(field) ? field.checked : field.value;

/** What the field is called on screen: aria-label, its <label>, placeholder, then name. */
function labelOf(field: Field): string {
    const text =
        field.getAttribute('aria-label') ||
        field.labels?.[0]?.textContent ||
        field.getAttribute('placeholder') ||
        field.getAttribute('title') ||
        field.name ||
        '';

    return text.replace(/\s+/g, ' ').trim();
}

function radiosOf(radio: HTMLInputElement): HTMLInputElement[] {
    if (!radio.name) {
        return [radio];
    }

    return Array.from(
        (radio.form ?? document).querySelectorAll<HTMLInputElement>(
            `input[type="radio"][name="${CSS.escape(radio.name)}"]`,
        ),
    );
}

/** Remember the value a field had before the user touched it (and every radio of its group). */
function remember(field: Field): void {
    if (!known.has(field)) {
        known.set(field, valueOf(field));
    }
    if (field instanceof HTMLInputElement && field.type === 'radio') {
        radiosOf(field).forEach((radio) => {
            if (!known.has(radio)) {
                known.set(radio, radio.checked);
            }
        });
    }
}

function onFocus(event: Event): void {
    const field = asField(event.target);
    if (field) {
        remember(field);
    }
}

function onEdit(event: Event): void {
    const field = asField(event.target);
    if (applying || field === null) {
        return;
    }
    // A radio that turns on: the step is "this one instead of the one that was on".
    if (field instanceof HTMLInputElement && field.type === 'radio') {
        if (!field.checked) {
            return;
        }
        const group = radiosOf(field);
        const was = group.find(
            (radio) => radio !== field && known.get(radio) === true,
        );
        group.forEach((radio) => known.set(radio, radio.checked));
        // Nothing was on before: there is no radio to turn back on, so no step.
        if (was) {
            push({
                field,
                label: labelOf(field),
                before: false,
                after: true,
                previous: was,
                at: Date.now(),
            });
        }

        return;
    }

    const before = known.has(field) ? known.get(field)! : valueOf(field);
    const after = valueOf(field);
    known.set(field, after);
    if (before === after) {
        return;
    }

    const last = state.done[state.done.length - 1];
    if (
        last &&
        last.field === field &&
        !isToggle(field) &&
        Date.now() - last.at < MERGE_MS
    ) {
        const merged = { ...last, after, at: Date.now() };
        const done =
            merged.before === merged.after
                ? state.done.slice(0, -1)
                : [...state.done.slice(0, -1), merged];
        emit({ done, undone: [] });

        return;
    }

    push({ field, label: labelOf(field), before, after, at: Date.now() });
}

function push(step: EditStep): void {
    emit({
        done: [...state.done, step].slice(-EDIT_STEPS),
        undone: [],
    });
}

/** Write a value back as the user would, so React's onChange sees it. */
function write(field: Field, value: string | boolean): void {
    if (!field.isConnected) {
        return;
    }
    applying = true;
    try {
        if (isToggle(field)) {
            if (field.checked !== value) {
                field.click();
            }
        } else {
            const proto = Object.getPrototypeOf(field) as object;
            const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
            setter?.call(field, value);
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (field instanceof HTMLInputElement && field.type === 'radio') {
            radiosOf(field).forEach((radio) => known.set(radio, radio.checked));
        } else {
            known.set(field, valueOf(field));
        }
        field.focus({ preventScroll: false });
    } finally {
        applying = false;
    }
}

/** Undo the last `count` edits (1 = the button, more = a pick from the list). */
export function undoEdits(count = 1): void {
    const done = state.done.slice();
    const undone = state.undone.slice();
    for (let n = 0; n < count && done.length > 0; n++) {
        const step = done.pop()!;
        if (step.previous) {
            write(step.previous, true);
        } else {
            write(step.field, step.before);
        }
        undone.push(step);
    }
    emit({ done, undone: undone.slice(-EDIT_STEPS) });
}

/** Redo the next `count` undone edits. */
export function redoEdits(count = 1): void {
    const done = state.done.slice();
    const undone = state.undone.slice();
    for (let n = 0; n < count && undone.length > 0; n++) {
        const step = undone.pop()!;
        write(step.field, step.after);
        done.push(step);
    }
    emit({ done: done.slice(-EDIT_STEPS), undone });
}

function reset(): void {
    if (state.done.length > 0 || state.undone.length > 0) {
        emit({ done: [], undone: [] });
    }
}

/** Start listening to the page's fields (once, from the app shell). */
export function startEditHistory(): void {
    if (started || typeof window === 'undefined') {
        return;
    }
    started = true;
    document.addEventListener('focusin', onFocus, true);
    document.addEventListener('pointerdown', onFocus, true);
    // Bubble phase: React (on its root) has taken the edit before the list re-renders the title bar.
    document.addEventListener('input', onEdit);
    document.addEventListener('change', onEdit);

    let lastPath = window.location.pathname;
    router.on('start', (event) => {
        visitMethod = event.detail.visit.method;
    });
    router.on('success', () => {
        if (visitMethod !== 'get') {
            reset(); // saved — nothing left to take back locally
        }
    });
    router.on('navigate', (event) => {
        const path = new URL(event.detail.page.url, window.location.origin)
            .pathname;
        if (path !== lastPath) {
            lastPath = path;
            reset();
        }
    });
}

export function useEditHistory(): State {
    return useSyncExternalStore(
        (listener) => {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
        () => state,
        () => state,
    );
}
