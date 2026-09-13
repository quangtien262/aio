import { useCallback, useEffect, useRef, useState } from 'react';

export default function useFnbResource({ enabled = true, loader, deps = [], pollMs = 0, keepData = true }) {
    const [data, setData] = useState(null);
    const [meta, setMeta] = useState({});
    const [loading, setLoading] = useState(Boolean(enabled));
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState(null);
    const requestSequence = useRef(0);
    const hasData = useRef(false);
    const inFlight = useRef(null);

    const load = useCallback(({ silent = false } = {}) => {
        if (!enabled) {
            setLoading(false);
            return Promise.resolve(null);
        }
        if (inFlight.current) {
            return inFlight.current;
        }

        const sequence = ++requestSequence.current;

        if (silent || (keepData && hasData.current)) {
            setRefreshing(true);
        } else {
            setLoading(true);
        }
        setError(null);

        let request;
        request = (async () => {
            try {
                const result = await loader();

                if (sequence === requestSequence.current) {
                    setData(result?.data ?? result ?? null);
                    hasData.current = true;
                    setMeta(result?.meta ?? {});
                }

                return result;
            } catch (exception) {
                if (sequence === requestSequence.current) {
                    setError(exception);
                }

                throw exception;
            } finally {
                if (sequence === requestSequence.current) {
                    setLoading(false);
                    setRefreshing(false);
                }
                if (inFlight.current === request) {
                    inFlight.current = null;
                }
            }
        })();
        inFlight.current = request;

        return request;
    }, [enabled, keepData, loader, ...deps]);

    useEffect(() => {
        requestSequence.current += 1;
        inFlight.current = null;
        hasData.current = false;
        setData(null);
        setMeta({});
        setError(null);
        setLoading(Boolean(enabled));
        setRefreshing(false);
    }, [enabled, ...deps]);

    useEffect(() => {
        let active = true;

        load().catch(() => undefined);

        if (!pollMs || !enabled) {
            return () => {
                active = false;
                requestSequence.current += 1;
                inFlight.current = null;
            };
        }

        const timer = window.setInterval(() => {
            if (active && document.visibilityState !== 'hidden') {
                load({ silent: true }).catch(() => undefined);
            }
        }, pollMs);

        return () => {
            active = false;
            window.clearInterval(timer);
            requestSequence.current += 1;
            inFlight.current = null;
        };
    }, [enabled, load, pollMs]);

    return {
        data,
        meta,
        loading,
        refreshing,
        error,
        reload: load,
        setData: (nextData) => {
            hasData.current = true;
            setData(nextData);
        },
    };
}
