import { cn } from '@/lib/utils';

type AppLogoProps = {
    className?: string;
    /** on-dark = Pearl mark (sidebar). on-light = Night mark (pearl surfaces). */
    tone?: 'on-dark' | 'on-light';
};

/**
 * SIS mark without yellow plate — mask-colored to chrome tokens.
 */
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
            <span className="sis-app-logo__mark" />
        </div>
    );
}
