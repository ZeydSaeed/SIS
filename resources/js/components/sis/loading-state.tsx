import { Spinner } from '@/components/ui/spinner';

type LoadingStateProps = {
    label?: string;
};

export function LoadingState({ label = 'Loading…' }: LoadingStateProps) {
    return (
        <div className="flex items-center justify-center gap-2 px-4 py-12" role="status" aria-live="polite">
            <Spinner className="size-5" />
            <span className="text-muted-foreground text-sm">{label}</span>
        </div>
    );
}
