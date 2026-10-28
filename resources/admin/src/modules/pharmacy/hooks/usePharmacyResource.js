import { useCallback, useEffect, useRef, useState } from 'react';

export default function usePharmacyResource(api, kind, params = {}, enabled = true, revision = 0) {
    const [state, setState] = useState({ data: null, meta: null, loading: false, error: null });
    const generation = useRef(0);
    const queryKey = JSON.stringify(params);
    const load = useCallback(async () => {
        const ticket = ++generation.current;
        if (!enabled || !kind) { setState({ data: null, meta: null, loading: false, error: null }); return; }
        setState({ data: null, meta: null, loading: true, error: null });
        try {
            const response = await api.read(kind, JSON.parse(queryKey));
            if (ticket === generation.current) setState({ data: response?.data ?? null, meta: response?.meta ?? null, loading: false, error: null });
        } catch (error) { if (ticket === generation.current) setState({ data: null, meta: null, loading: false, error }); }
    }, [api, kind, queryKey, enabled, revision]);
    useEffect(() => { load(); return () => { generation.current += 1; }; }, [load]);
    return { ...state, reload: load };
}
