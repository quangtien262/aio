import { useCallback, useEffect, useRef, useState } from 'react';

function resolveInitialData(enabled, initialData, cacheKey) {
    if (!enabled) {
        return null;
    }

    if (initialData !== null && initialData !== undefined) {
        return initialData;
    }

    if (!cacheKey || typeof window === 'undefined') {
        return null;
    }

    try {
        const cachedPayload = window.sessionStorage.getItem(cacheKey);

        return cachedPayload ? JSON.parse(cachedPayload) : null;
    } catch {
        return null;
    }
}

export default function useAdminRouteResource({ enabled = true, loader, deps = [], initialData = null, cacheKey = null }) {
    const initialDataRef = useRef(undefined);

    if (initialDataRef.current === undefined) {
        initialDataRef.current = resolveInitialData(enabled, initialData, cacheKey);
    }

    const [data, setData] = useState(initialDataRef.current);
    const [loading, setLoading] = useState(enabled && initialDataRef.current === null);
    const [error, setError] = useState(null);
    const dataRef = useRef(initialDataRef.current);
    const loaderRef = useRef(loader);

    useEffect(() => {
        loaderRef.current = loader;
    }, [loader]);

    const reload = useCallback(async () => {
        if (!enabled || !loaderRef.current) {
            setLoading(false);
            dataRef.current = null;
            setData(null);
            setError(null);
            return null;
        }

        // Chỉ dùng skeleton toàn màn hình cho lần tải đầu tiên. Khi đổi locale
        // hoặc làm mới dữ liệu, giữ nguyên giao diện hiện tại trong lúc tải nền.
        setLoading(dataRef.current === null || dataRef.current === undefined);
        setError(null);

        try {
            const nextData = await loaderRef.current();
            dataRef.current = nextData;
            setData(nextData);

            return nextData;
        } catch (nextError) {
            setError(nextError instanceof Error ? nextError.message : 'Không tải được dữ liệu.');

            return null;
        } finally {
            setLoading(false);
        }
    }, [enabled]);

    const mutateData = (updater) => {
        setData((currentData) => {
            const nextData = typeof updater === 'function' ? updater(currentData) : updater;

            dataRef.current = nextData;

            return nextData;
        });
    };

    useEffect(() => {
        reload();
    }, [reload, ...deps]);

    useEffect(() => {
        if (!cacheKey || typeof window === 'undefined' || data === null || data === undefined) {
            return;
        }

        try {
            window.sessionStorage.setItem(cacheKey, JSON.stringify(data));
        } catch {
            // Ignore storage failures and keep the route functional.
        }
    }, [cacheKey, data]);

    useEffect(() => {
        if (enabled || !cacheKey || typeof window === 'undefined') {
            return;
        }

        try {
            window.sessionStorage.removeItem(cacheKey);
        } catch {
            // Ignore storage failures and keep the route functional.
        }
    }, [cacheKey, enabled]);

    return {
        data,
        loading,
        error,
        reload,
        mutateData,
    };
}
