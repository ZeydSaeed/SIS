import { Copy, Minus, Square, X } from 'lucide-react';
import { cn } from '@/lib/utils';

type WindowControlsProps = {
    label: string;
    minimizeLabel: string;
    maximizeLabel: string;
    restoreLabel: string;
    closeLabel: string;
    maximized?: boolean;
    minimizable?: boolean;
    maximizable?: boolean;
    closable?: boolean;
    onMinimize?: () => void;
    onMaximize?: () => void;
    onClose?: () => void;
    className?: string;
};

export function WindowControls({
    label,
    minimizeLabel,
    maximizeLabel,
    restoreLabel,
    closeLabel,
    maximized = false,
    minimizable = true,
    maximizable = true,
    closable = true,
    onMinimize,
    onMaximize,
    onClose,
    className,
}: WindowControlsProps) {
    return (
        <div
            className={cn('sis-window__controls', className)}
            role="group"
            aria-label={label}
            dir="ltr"
            onPointerDown={(event) => event.stopPropagation()}
        >
            {minimizable ? (
                <button
                    type="button"
                    className="sis-window__control"
                    aria-label={minimizeLabel}
                    onClick={onMinimize}
                >
                    <Minus className="size-3.5" aria-hidden />
                </button>
            ) : null}
            {maximizable ? (
                <button
                    type="button"
                    className="sis-window__control"
                    aria-label={maximized ? restoreLabel : maximizeLabel}
                    onClick={onMaximize}
                >
                    {maximized ? (
                        <Copy className="size-3.5" aria-hidden />
                    ) : (
                        <Square className="size-3.5" aria-hidden />
                    )}
                </button>
            ) : null}
            {closable ? (
                <button
                    type="button"
                    className="sis-window__control sis-window__control--close"
                    aria-label={closeLabel}
                    onClick={onClose}
                >
                    <X className="size-3.5" aria-hidden />
                </button>
            ) : null}
        </div>
    );
}
