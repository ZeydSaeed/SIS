type ErrorStateProps = {
    title: string;
    description?: string;
};

export function ErrorState({ title, description }: ErrorStateProps) {
    return (
        <div
            className="border-destructive/30 bg-destructive/5 rounded-lg border px-4 py-6 text-center"
            role="alert"
        >
            <p className="text-destructive font-medium">{title}</p>
            {description ? (
                <p className="text-muted-foreground mt-1 text-sm">{description}</p>
            ) : null}
        </div>
    );
}
