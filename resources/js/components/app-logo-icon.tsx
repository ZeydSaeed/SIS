import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/** The round Ishtar Tech seal (transparent outside the circle) — SSOT image for every logo in the program. */
export const APP_LOGO_SRC = '/ishtar-logo-192.png?v=ishtar1';

export default function AppLogoIcon({
    className,
    alt = '',
    src = APP_LOGO_SRC,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src={src}
            alt={alt}
            decoding="async"
            draggable={false}
            className={cn('size-5 shrink-0 rounded-full object-contain', className)}
            {...props}
        />
    );
}
