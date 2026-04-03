import { useCallback, useEffect, useRef, useState } from 'react';

export default function useAdminRouteResource({ enabled = true, loader, deps = [] }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(enabled);
    const [error, setError] = useState(null);
    const loaderRef = useRef(loader);

    useEffect(() => {
        loaderRef.current = loader;
    }, [loader]);

    const reload = useCallback(async () => {
        if (!enabled || !loaderRef.current) {
            setLoading(false);
            setData(null);
            setError(null);
            return null;
        }

        setLoading(true);
        setError(null);

        try {
            const nextData = await loaderRef.current();
            setData(nextData);

            return nextData;
        } catch (nextError) {
            setError(nextError instanceof Error ? nextError.message : 'Không tải được dữ liệu.');

            return null;
        } finally {
            setLoading(false);
        }
    }, [enabled]);

    useEffect(() => {
        reload();
    }, [reload, ...deps]);

    return {
        data,
        loading,
        error,
        reload,
    };
}
