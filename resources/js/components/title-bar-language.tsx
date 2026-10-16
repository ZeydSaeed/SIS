import { Languages } from 'lucide-react';
import { locale, setLocale, t } from '@/i18n';

/** «English» / «عربي»: switches the whole interface (strings and direction) and remembers the choice. */
export function TitleBarLanguage() {
    const l = t().window.language;
    const next = locale() === 'ar' ? 'en' : 'ar';

    return (
        <button
            type="button"
            className="sis-window__control sis-titlebar__language"
            lang={next}
            aria-label={`${l.label}: ${l.switchHint}`}
            title={l.switchHint}
            onClick={() => setLocale(next)}
        >
            <span className="sis-titlebar__language-text">{l.switchTo}</span>
            <Languages className="size-4" aria-hidden />
        </button>
    );
}
