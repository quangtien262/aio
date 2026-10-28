import { useEffect, useRef, useState } from 'react';
import Select from 'antd/es/select';
import { recordId, recordLabel, resourceRows } from '../utils/pharmacyData';

export default function PharmacyRemoteSelect({ api, kind, value, onChange, disabled, label = 'Tìm và chọn', params = {}, onRecord, pickable = false, ...props }) {
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const generation = useRef(0);
    const queryKey = JSON.stringify(params);
    useEffect(() => {
        const ticket = ++generation.current;
        setOptions([]); setError(null);
        if (disabled) { setLoading(false); return; }
        const timer = globalThis.setTimeout(async () => {
            setLoading(true);
            try {
                const response = kind === 'patients' && query.trim()
                    ? await api.lookupPatients(query.trim())
                    : await api.read(kind, { ...JSON.parse(queryKey), ...(kind === 'patients' ? {} : { q: query, query }), page: 1, per_page: 30 });
                if (ticket === generation.current) setOptions([...new Map(resourceRows(response.data).filter((row) => (kind !== 'inventory' || !pickable || (Number(row.available) > 0 && !row.blocked)) && (kind !== 'shifts' || !params.status || row.status === params.status)).map((row) => [kind === 'inventory' ? String(row.batch_id ?? row.id) : kind === 'units' ? row.code : recordId(row), row])).values()].map((row) => ({ value: kind === 'inventory' ? String(row.batch_id ?? row.id) : kind === 'units' ? row.code : recordId(row), label: kind === 'inventory' ? [row.product_name ?? row.name, row.batch_code, row.expires_at ? `HSD ${row.expires_at}` : '', row.available != null ? `Khả dụng ${row.available}` : ''].filter(Boolean).join(' · ') : kind === 'units' ? `${row.name} (${row.code})` : kind === 'patients' ? [row.name, row.phone_masked, recordId(row).slice(-6)].filter(Boolean).join(' · ') : recordLabel(row), record: row })));
            } catch (failure) { if (ticket === generation.current) setError(failure.message); }
            finally { if (ticket === generation.current) setLoading(false); }
        }, 250);
        return () => { globalThis.clearTimeout(timer); generation.current += 1; };
    }, [api, kind, queryKey, query, disabled, pickable]);
    return <Select {...props} value={value} showSearch allowClear filterOption={false} disabled={disabled} loading={loading} placeholder={kind === 'patients' ? 'Tra cứu bằng số điện thoại chính xác' : label}
        options={options} onSearch={setQuery} notFoundContent={error ?? (loading ? 'Đang tìm…' : 'Chưa có kết quả phù hợp')}
        onChange={(next, option) => { onChange?.(next ?? null); onRecord?.(option?.record ?? null); }} style={{ width: '100%', ...props.style }} />;
}
