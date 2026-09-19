import { createContext, useContext, type ReactNode } from 'react';

const AdmissionSearchContext = createContext('');

export function AdmissionSearchProvider({
    value,
    children,
}: {
    value: string;
    children: ReactNode;
}) {
    return (
        <AdmissionSearchContext.Provider value={value}>
            {children}
        </AdmissionSearchContext.Provider>
    );
}

export function useAdmissionSearchQuery(): string {
    return useContext(AdmissionSearchContext);
}
