import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const SIS_MARK_SRC = '/sis-mark.png?v=sis8';

/** SIS brand mark — black mark on yellow field (SSOT image). */
export default function AppLogoIcon({
    className,
    alt = '',
    src = SIS_MARK_SRC,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src={src}
            alt={alt}
            decoding="async"
            draggable={false}
            className={cn('size-5 shrink-0 object-contain', className)}
            {...props}
        />
    );
}
