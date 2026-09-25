import type { Auth } from '@/types/auth';
import type { HTMLAttributes } from 'react';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare namespace JSX {
    interface IntrinsicElements {
        'ion-icon': HTMLAttributes<HTMLElement> & {
            name?: string;
            class?: string;
            src?: string;
            dir?: string;
            'flip-rtl'?: string | boolean;
        };
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
