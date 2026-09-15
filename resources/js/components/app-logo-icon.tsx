import type { SVGAttributes } from 'react';
import { GraduationCap } from 'lucide-react';
import { cn } from '@/lib/utils';

/** SIS brand mark — student / academic system (replaces Laravel logo). */
export default function AppLogoIcon({
    className,
    ...props
}: SVGAttributes<SVGElement>) {
    return (
        <GraduationCap
            aria-hidden
            className={cn('size-5 shrink-0', className)}
            {...props}
        />
    );
}
