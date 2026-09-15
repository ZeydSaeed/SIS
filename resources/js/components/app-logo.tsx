import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                <AppLogoIcon className="size-5 text-current" />
            </div>
            <div className="ml-1 grid min-w-0 flex-1 text-start text-sm">
                <span className="truncate leading-tight font-normal">SIS</span>
            </div>
        </>
    );
}
