import { useEffect, useRef } from 'react';
import { toSlug } from '../utils/slug';

export default function useAutoSlug({ form, sourceValue, targetName = 'slug' }) {
    const lastSourceRef = useRef(String(sourceValue ?? ''));

    useEffect(() => {
        const nextSource = String(sourceValue ?? '');

        if (nextSource === lastSourceRef.current) {
            return;
        }

        form.setFieldValue(targetName, toSlug(nextSource));
        lastSourceRef.current = nextSource;
    }, [form, sourceValue, targetName]);

    return (source) => {
        lastSourceRef.current = String(source ?? '');
    };
}
