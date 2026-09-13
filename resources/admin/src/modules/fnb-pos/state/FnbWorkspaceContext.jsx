import { createContext, useContext } from 'react';

export const FnbWorkspaceContext = createContext(null);

export function useFnbWorkspace() {
    const value = useContext(FnbWorkspaceContext);

    if (!value) {
        throw new Error('useFnbWorkspace phải được dùng bên trong FnbWorkspaceContext.');
    }

    return value;
}

