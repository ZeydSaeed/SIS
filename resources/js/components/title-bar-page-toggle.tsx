import { PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePageTitlebarToggle } from '@/components/sis/page-titlebar-toggle-context';

/**
 * The page's own show/hide switch (e.g. the attendance and leave section rail), next to the close (X) button, in the same
 * button as the sidebar's trigger — nothing is shown on pages that register none.
 */
export function TitleBarPageToggle() {
    const toggle = usePageTitlebarToggle();

    if (toggle === null) {
        return null;
    }

    return (
        <Button
            type="button"
            data-sidebar="trigger"
            data-slot="page-titlebar-toggle"
            variant="ghost"
            size="icon"
            className="sis-titlebar__trigger size-7 shrink-0"
            aria-pressed={toggle.pressed}
            title={toggle.label}
            onClick={toggle.onToggle}
        >
            {toggle.pressed ? <PanelLeftClose /> : <PanelLeftOpen />}
            <span className="sr-only">{toggle.label}</span>
        </Button>
    );
}
