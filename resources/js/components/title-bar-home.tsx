import { Link } from '@inertiajs/react';
import { Home } from 'lucide-react';
import { usePageTitlebarHome } from '@/components/sis/page-titlebar-home-context';

/** Icon-only admission home — matches title-bar utility icons next to close (X). */
export function TitleBarHome() {
    const home = usePageTitlebarHome();

    if (!home) {
        return null;
    }

    return (
        <Link
            href={home.href}
            prefetch
            className="sis-titlebar__utility"
            aria-label={home.ariaLabel}
            title={home.ariaLabel}
        >
            <Home className="sis-titlebar__utility-icon" aria-hidden />
        </Link>
    );
}
