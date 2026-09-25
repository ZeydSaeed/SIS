import { Link } from '@inertiajs/react';
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
            <ion-icon
                name="arrow-up-right-box-outline"
                class="sis-titlebar__utility-icon sis-titlebar__home-ion"
                dir="ltr"
                flip-rtl="false"
                aria-hidden="true"
            ></ion-icon>
        </Link>
    );
}
