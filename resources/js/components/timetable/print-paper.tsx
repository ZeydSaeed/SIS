import { useEffect, useLayoutEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';

/** Paper sizes (portrait width × height, mm). */
export const PAPERS = {
    A3: { w: 297, h: 420 },
    A4: { w: 210, h: 297 },
    A5: { w: 148, h: 210 },
    B4: { w: 250, h: 353 },
    Letter: { w: 215.9, h: 279.4 },
    Legal: { w: 215.9, h: 355.6 },
} as const;

export type PaperName = keyof typeof PAPERS;

export type PrintSettings = {
    paper: PaperName;
    orientation: 'landscape' | 'portrait';
    /** Physical margins in mm (right / left are the sheet's right and left, not the reading direction). */
    margins: { top: number; right: number; bottom: number; left: number };
    /** page = the whole table on one sheet (text shrinks to fit) · width = the page width, continues on further sheets. */
    fit: 'page' | 'width';
    color: 'color' | 'mono';
    showHead: boolean;
    showDate: boolean;
};

export const DEFAULT_PRINT_SETTINGS: PrintSettings = {
    paper: 'A3',
    orientation: 'landscape',
    margins: { top: 8, right: 8, bottom: 8, left: 8 },
    fit: 'page',
    color: 'color',
    showHead: true,
    showDate: true,
};

const STORAGE_KEY = 'sis.timetable.printSettings';
const MM_TO_PX = 96 / 25.4;
const MIN_SCALE = 0.3;

/** Saved per browser (a viewer convenience); any read / parse failure falls back to the defaults. */
export function loadPrintSettings(): PrintSettings {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (raw === null) {
            return DEFAULT_PRINT_SETTINGS;
        }
        const saved = JSON.parse(raw) as Partial<PrintSettings>;

        return {
            ...DEFAULT_PRINT_SETTINGS,
            ...saved,
            paper: saved.paper !== undefined && saved.paper in PAPERS ? saved.paper : DEFAULT_PRINT_SETTINGS.paper,
            margins: { ...DEFAULT_PRINT_SETTINGS.margins, ...(saved.margins ?? {}) },
        };
    } catch {
        return DEFAULT_PRINT_SETTINGS;
    }
}

export function savePrintSettings(settings: PrintSettings): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    } catch {
        // Private mode / blocked storage: the settings simply are not remembered.
    }
}

/** Sheet size (mm) after orientation. */
export function paperSize(settings: PrintSettings): { w: number; h: number } {
    const { w, h } = PAPERS[settings.paper];

    return settings.orientation === 'landscape' ? { w: h, h: w } : { w, h };
}

/** The `@page` rule of the chosen sheet: exact size and margins, so every printed page matches the preview. */
export function pageRule(settings: PrintSettings): string {
    const { w, h } = paperSize(settings);
    const m = settings.margins;

    return `@page { size: ${w}mm ${h}mm; margin: ${m.top}mm ${m.right}mm ${m.bottom}mm ${m.left}mm; }`;
}

function paperStyle(settings: PrintSettings, scale: number, screenZoom: number): CSSProperties {
    const { w, h } = paperSize(settings);
    const m = settings.margins;

    return {
        '--sis-paper-w': `${w}mm`,
        '--sis-paper-h': `${h}mm`,
        '--sis-paper-content-w': `${w - m.left - m.right}mm`,
        '--sis-paper-content-h': `${h - m.top - m.bottom}mm`,
        '--sis-paper-pt': `${m.top}mm`,
        '--sis-paper-pr': `${m.right}mm`,
        '--sis-paper-pb': `${m.bottom}mm`,
        '--sis-paper-pl': `${m.left}mm`,
        '--sis-paper-scale': String(scale),
        '--sis-paper-screen-zoom': String(screenZoom),
    } as CSSProperties;
}

function paperClass(settings: PrintSettings): string {
    return [
        'sis-timetable-paper',
        `sis-timetable-paper--${settings.color}`,
        `sis-timetable-paper--fit-${settings.fit}`,
        settings.showHead ? '' : 'sis-timetable-paper--no-head',
    ]
        .filter((c) => c !== '')
        .join(' ');
}

/**
 * «تخطيط الطباعة»: the table shown in the preview, laid on a sheet of the chosen paper with its margins — what is seen
 * is what prints. On screen the sheet is zoomed to the window; the print copy (a direct child of body, the only thing
 * printed) uses the same fit scale measured here.
 */
export function TimetablePaper({ settings, children, footer }: { settings: PrintSettings; children: ReactNode; footer: string }) {
    const stageRef = useRef<HTMLDivElement | null>(null);
    const boxRef = useRef<HTMLDivElement | null>(null);
    const contentRef = useRef<HTMLDivElement | null>(null);
    const footRef = useRef<HTMLElement | null>(null);
    const [scale, setScale] = useState(1);
    const [screenZoom, setScreenZoom] = useState(1);
    const { w } = paperSize(settings);

    // Screen: the whole sheet width fits the window.
    useEffect(() => {
        const stage = stageRef.current;
        if (stage === null) {
            return;
        }
        const update = () => setScreenZoom(Math.min(1, Math.max(0.1, (stage.clientWidth - 16) / (w * MM_TO_PX))));
        update();
        const observer = new ResizeObserver(update);
        observer.observe(stage);

        return () => observer.disconnect();
    }, [w]);

    // Fit to page: shrink the content (text included) until it is no taller than the sheet's content area.
    useLayoutEffect(() => {
        const box = boxRef.current;
        const content = contentRef.current;
        if (box === null || content === null) {
            return;
        }
        if (settings.fit !== 'page') {
            if (scale !== 1) {
                setScale(1);
            }

            return;
        }
        // Smaller text also wraps less, so height is not proportional to the scale: search the largest scale that fits.
        content.setAttribute('data-measuring', '');
        const fits = (value: number) => {
            content.style.setProperty('zoom', String(value));
            const available = box.getBoundingClientRect().height - (footRef.current?.getBoundingClientRect().height ?? 0);

            return content.getBoundingClientRect().height <= available + 0.5;
        };
        let next = 1;
        if (!fits(1)) {
            let low = MIN_SCALE;
            let high = 1;
            for (let i = 0; i < 12 && high - low > 0.004; i++) {
                const mid = (low + high) / 2;
                if (fits(mid)) {
                    low = mid;
                } else {
                    high = mid;
                }
            }
            next = low;
        }
        content.style.removeProperty('zoom');
        content.removeAttribute('data-measuring');
        if (Math.abs(next - scale) > 0.001) {
            setScale(next);
        }
    });

    const sheet = (print: boolean) => (
        <div className={paperClass(settings)} style={paperStyle(settings, scale, screenZoom)}>
            <div className="sis-timetable-paper__box" ref={print ? undefined : boxRef}>
                <div className="sis-timetable-paper__content" ref={print ? undefined : contentRef}>
                    {children}
                </div>
                {settings.showDate ? (
                    <footer className="sis-timetable-paper__foot" ref={print ? undefined : footRef}>
                        {footer}
                    </footer>
                ) : null}
            </div>
        </div>
    );

    return (
        <>
            <div className="sis-timetable-paper-stage" ref={stageRef}>
                {sheet(false)}
            </div>
            {typeof document !== 'undefined' ? createPortal(<div className="sis-timetable-print-root">{sheet(true)}</div>, document.body) : null}
        </>
    );
}

/** «إعدادات الطباعة»: paper, orientation, margins, fit, colour, heading and date — with the project's sheet fields. */
export function PrintSettingsPanel({ settings, onChange, onClose }: { settings: PrintSettings; onChange: (next: PrintSettings) => void; onClose: () => void }) {
    const p = t().timetable.printSetup;
    const select = (label: string, value: string, options: Array<{ value: string; label: string }>, change: (v: string) => void) => (
        <label className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            <SisListSelect
                value={value}
                options={options}
                onChange={change}
                ariaLabel={label}
                className="sis-admission-sheet-list-select"
                triggerClassName="sis-admission-sheet__control sis-admission-draft-select"
                menuClassName="sis-admission-sheet-list-select__menu"
            />
        </label>
    );
    const margin = (side: keyof PrintSettings['margins']) => (
        <label className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{p.margins[side]}</span>
            <input
                className="sis-admission-sheet__control"
                type="number"
                dir="ltr"
                min={0}
                max={50}
                value={settings.margins[side]}
                aria-label={p.margins[side]}
                onChange={(e) => {
                    const value = Math.min(50, Math.max(0, Number(e.target.value) || 0));
                    onChange({ ...settings, margins: { ...settings.margins, [side]: value } });
                }}
            />
        </label>
    );
    const check = (label: string, checked: boolean, change: (v: boolean) => void) => (
        <label className="sis-timetable-audit__filter">
            <input type="checkbox" checked={checked} onChange={(e) => change(e.target.checked)} />
            {label}
        </label>
    );

    return (
        <div className="sis-timetable-print-panel sis-admission-sheet" role="dialog" aria-label={p.title} dir="rtl">
            <h3 className="sis-admission-sheet__banner sis-admission-sheet__banner--accent">{p.title}</h3>
            <div className="sis-timetable-print-panel__body">
                {select(p.paper, settings.paper, (Object.keys(PAPERS) as PaperName[]).map((name) => ({ value: name, label: `${name} (${PAPERS[name].w} × ${PAPERS[name].h} ${p.mm})` })), (v) => onChange({ ...settings, paper: v as PaperName }))}
                {select(p.orientation, settings.orientation, [{ value: 'landscape', label: p.landscape }, { value: 'portrait', label: p.portrait }], (v) => onChange({ ...settings, orientation: v as PrintSettings['orientation'] }))}
                {margin('top')}
                {margin('bottom')}
                {margin('right')}
                {margin('left')}
                {select(p.fit, settings.fit, [{ value: 'page', label: p.fitPage }, { value: 'width', label: p.fitWidth }], (v) => onChange({ ...settings, fit: v as PrintSettings['fit'] }))}
                {select(p.color, settings.color, [{ value: 'color', label: p.colorFull }, { value: 'mono', label: p.colorMono }], (v) => onChange({ ...settings, color: v as PrintSettings['color'] }))}
                <span className="sis-timetable-print-panel__checks">
                    {check(p.showHead, settings.showHead, (v) => onChange({ ...settings, showHead: v }))}
                    {check(p.showDate, settings.showDate, (v) => onChange({ ...settings, showDate: v }))}
                </span>
            </div>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" onClick={() => onChange(DEFAULT_PRINT_SETTINGS)}>
                    {p.reset}
                </Button>
                <Button type="button" onClick={onClose}>
                    {p.done}
                </Button>
            </div>
        </div>
    );
}
