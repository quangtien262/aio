import { createContext, useContext } from 'react';
export const PharmacyWorkspaceContext = createContext(null);
export function usePharmacyWorkspace() {
    const context = useContext(PharmacyWorkspaceContext);
    if (!context) throw new Error('PharmacyWorkspaceContext chưa sẵn sàng.');
    return context;
}
