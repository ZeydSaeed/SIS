import { cn } from '@/lib/utils';
import { APP_LOGO_SRC } from '@/components/app-logo-icon';

type AppLogoProps = {
    className?: string;
    /** Kept for callers; the round logo reads on dark and light surfaces alike. */
    tone?: 'on-dark' | 'on-light';
};

/** Program logo — the round Ishtar Tech seal, transparent outside the circle (sidebar, header, every sheet). */
export default function AppLogo({ className, tone = 'on-dark' }: AppLogoProps) {
    return (
        <div
            className={cn(
                'sis-app-logo',
                tone === 'on-light' ? 'sis-app-logo--on-light' : 'sis-app-logo--on-dark',
                className,
            )}
            aria-hidden="true"
        >
            <img className="sis-app-logo__img" src={APP_LOGO_SRC} alt="" decoding="async" draggable={false} />
        </div>
    );
}
