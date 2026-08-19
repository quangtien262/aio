import { useCallback, useEffect, useRef, useState } from 'react';
import { adminApi } from '../config/routes';

export default function useLocalizedAutoSlug({
    form,
    sourceValue,
    locale,
    resourceType,
    resourceId = null,
    fallbackSlug = '',
    callAdminApi,
    enabled = true,
    targetName = 'slug',
}) {
    const manualRef = useRef(false);
    const lastSourceRef = useRef(String(sourceValue ?? ''));
    const requestRef = useRef(0);
    const [loading, setLoading] = useState(false);

    const suggest = useCallback(async (value, { markManual = false } = {}) => {
        const input = String(value ?? '').trim();

        if (!enabled || !input || typeof callAdminApi !== 'function') return '';

        if (markManual) manualRef.current = true;
        const requestId = ++requestRef.current;
        setLoading(true);

        try {
            const response = await callAdminApi(adminApi('localization/slug-suggest'), {
                method: 'POST',
                body: JSON.stringify({
                    value: input,
                    locale,
                    resource_type: resourceType,
                    resource_id: resourceId ? String(resourceId) : null,
                    fallback_slug: fallbackSlug || null,
                }),
            });
            const slug = String(response?.data?.slug ?? '');

            if (requestId === requestRef.current && slug) {
                form.setFieldValue(targetName, slug);
            }

            return slug;
        } finally {
            if (requestId === requestRef.current) setLoading(false);
        }
    }, [callAdminApi, enabled, fallbackSlug, form, locale, resourceId, resourceType, targetName]);

    useEffect(() => {
        const nextSource = String(sourceValue ?? '');

        if (!enabled || manualRef.current || nextSource === lastSourceRef.current) return undefined;

        lastSourceRef.current = nextSource;
        const timer = window.setTimeout(() => {
            void suggest(nextSource);
        }, 300);

        return () => window.clearTimeout(timer);
    }, [enabled, sourceValue, suggest]);

    const reset = useCallback((source, { manual = false } = {}) => {
        requestRef.current += 1;
        lastSourceRef.current = String(source ?? '');
        manualRef.current = manual;
        setLoading(false);
    }, []);

    return {
        loading,
        markManual: () => { manualRef.current = true; },
        regenerate: () => {
            manualRef.current = false;
            return suggest(sourceValue);
        },
        normalizeManual: (value) => suggest(value, { markManual: true }),
        reset,
    };
}
