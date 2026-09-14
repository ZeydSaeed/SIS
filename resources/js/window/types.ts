export type WindowSize = {
    width: number;
    height: number;
};

export type WindowPosition = {
    x: number;
    y: number;
};

export type SisWindowDescriptor = {
    windowId: string;
    title: string;
    href: string;
    resizable?: boolean;
    movable?: boolean;
    maximizable?: boolean;
    minimizable?: boolean;
    closable?: boolean;
    minimumSize?: WindowSize;
};

export type SisOpenWindow = SisWindowDescriptor & {
    instanceId: string;
    x: number;
    y: number;
    width: number;
    height: number;
    zIndex: number;
    minimized: boolean;
    maximized: boolean;
};

export type WindowManagerState = {
    windows: SisOpenWindow[];
    focusedInstanceId: string | null;
    nextZ: number;
};
