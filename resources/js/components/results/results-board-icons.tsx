import type { ReactNode, SVGProps } from 'react';

/**
 * The «النتائج» board's card pictures: line drawings in the card's own colour (`currentColor`) with a soft wash
 * inside a shape. Decorative only — the card's title names it. Drawn after the cyber_security control panel's cards
 * (owner's request, 2026-11-28); inside the round badge every line takes the badge's colour.
 */
export type ResultsBoardIcon = (props: SVGProps<SVGSVGElement>) => ReactNode;

function Frame({ children, ...props }: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 48 48"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden
            focusable="false"
            {...props}
        >
            {children}
        </svg>
    );
}

/** A soft wash of the card's colour inside a shape. */
const wash = { fill: 'currentColor', fillOpacity: 0.14 } as const;

/** A sheet and a pen. */
export const EntryIcon: ResultsBoardIcon = (props) => (
    <Frame {...props}>
        <rect x="8" y="9" width="22" height="30" rx="3" {...wash} />
        <path d="M14 9V6.5h10V9" />
        <path d="M13 18h12M13 24h8M13 30h6" />
        <path d="m27 33 10-10 3 3-10 10-4 1z" />
        <path d="m35 25 3 3" />
    </Frame>
);

/** Two chain links. */
export const LinksIcon: ResultsBoardIcon = (props) => (
    <Frame {...props}>
        <path d="M21 27a7 7 0 0 0 10 0l6-6a7 7 0 0 0-10-10l-2 2" {...wash} />
        <path d="M27 21a7 7 0 0 0-10 0l-6 6a7 7 0 0 0 10 10l2-2" />
        <path d="M38 34v6M35 37h6" />
    </Frame>
);

/** A person and a key. */
export const PersonKeyIcon: ResultsBoardIcon = (props) => (
    <Frame {...props}>
        <circle cx="18" cy="15" r="6" {...wash} />
        <path d="M7 39v-3a10 10 0 0 1 10-10h2a10 10 0 0 1 6 2" />
        <circle cx="34" cy="30" r="4" />
        <path d="m37 33 5 5m-2-2 2-2m-4 0 2-2" />
    </Frame>
);

/** A graduation cap over a ticked line. */
export const CapIcon: ResultsBoardIcon = (props) => (
    <Frame {...props}>
        <path d="M24 7 6 15l18 8 18-8z" {...wash} />
        <path d="M13 19v7c0 3 5 5 11 5s11-2 11-5v-7" />
        <path d="M42 15v9" />
        <path d="m17 39 3 3 6-6M30 40h8" />
    </Frame>
);

/** A shield with a person. */
export const ShieldIcon: ResultsBoardIcon = (props) => (
    <Frame {...props}>
        <path d="M24 6 9 12v10c0 10 6.5 17 15 20 8.5-3 15-10 15-20V12z" {...wash} />
        <circle cx="24" cy="19" r="4" />
        <path d="M17 31a7 7 0 0 1 14 0" />
    </Frame>
);

/** A speech bubble with a question mark. */
export const QuestionIcon: ResultsBoardIcon = (props) => (
    <Frame {...props}>
        <path
            d="M8 10h32a2 2 0 0 1 2 2v18a2 2 0 0 1-2 2H22l-8 7v-7H8a2 2 0 0 1-2-2V12a2 2 0 0 1 2-2z"
            {...wash}
        />
        <path d="M20 18a4 4 0 1 1 5 3.9c-.6.2-1 .8-1 1.4V25" />
        <circle cx="24" cy="28.5" r="1" fill="currentColor" />
    </Frame>
);

/** A sheet with a gear. */
export const SheetGearIcon: ResultsBoardIcon = (props) => (
    <Frame {...props}>
        <path d="M10 6h16l8 8v12" {...wash} />
        <path d="M10 6v34h14" />
        <path d="M26 6v8h8" />
        <path d="M15 19h10M15 25h7" />
        <circle cx="35" cy="35" r="3.5" />
        <path d="M35 27.5v2.5M35 40v2.5M27.5 35H30M40 35h2.5M29.7 29.7l1.8 1.8M38.5 38.5l1.8 1.8M29.7 40.3l1.8-1.8M38.5 31.5l1.8-1.8" />
    </Frame>
);

/** An eye. */
export const EyeIcon: ResultsBoardIcon = (props) => (
    <Frame {...props}>
        <path d="M4 24s7-13 20-13 20 13 20 13-7 13-20 13S4 24 4 24z" {...wash} />
        <circle cx="24" cy="24" r="6" />
        <circle cx="24" cy="24" r="2" fill="currentColor" />
    </Frame>
);

/** An open book. */
export const BookIcon: ResultsBoardIcon = (props) => (
    <Frame {...props}>
        <path d="M24 13c-4-3-10-4-18-3v26c8-1 14 0 18 3z" {...wash} />
        <path d="M24 13c4-3 10-4 18-3v26c-8-1-14 0-18 3z" />
        <path d="M11 18c3 0 6 .5 8 1.5M11 24c3 0 6 .5 8 1.5M29 19.5c2-1 5-1.5 8-1.5M29 25.5c2-1 5-1.5 8-1.5" />
    </Frame>
);
