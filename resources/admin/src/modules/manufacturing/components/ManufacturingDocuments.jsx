import { useCallback, useEffect, useRef, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Typography from 'antd/es/typography';

export default function ManufacturingDocuments({ api, resourceType, resourceId, contextKey }) {
    const [data, setData] = useState({ items: [], total: 0 });
    const [page, setPage] = useState(1);
    const [file, setFile] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const input = useRef(null);
    const generation = useRef(0);
    const load = useCallback(async () => {
        const ticket = ++generation.current;
        try {
            const result = await api.read('documents', { resource_type: resourceType, resource_id: resourceId, page, per_page: 10 });
            if (ticket === generation.current) { setData(result.data ?? { items: [], total: 0 }); setError(''); }
        } catch (err) { if (ticket === generation.current) setError(err.message || 'Không tải được tài liệu.'); }
    }, [api, resourceType, resourceId, page]);
    useEffect(() => { setFile(null); setBusy(false); setError(''); load(); return () => { generation.current += 1; }; }, [load, contextKey]);
    const upload = async () => {
        if (!file || busy) return;
        const ticket = generation.current;
        setBusy(true); setError('');
        try {
            await api.uploadDocument(resourceType, resourceId, file);
            if (ticket === generation.current) { setBusy(false); setFile(null); if (input.current) input.current.value = ''; await load(); }
        } catch (err) { if (ticket === generation.current) { setBusy(false); setError(err.message || 'Chưa rõ kết quả tải lên. Chọn lại đúng tệp để thử lại an toàn.'); } }
    };
    return <div className="mfg-detail-section"><Typography.Title level={5}>Tài liệu đính kèm</Typography.Title><Typography.Paragraph type="secondary">PDF, hình ảnh, văn bản, Office hoặc ZIP; tối đa 20 MB mỗi tệp.</Typography.Paragraph>{error && <Alert type="error" showIcon message={error} style={{ marginBottom: 12 }} />}<Space wrap style={{ marginBottom: 16 }}><input ref={input} type="file" aria-label="Chọn tài liệu sản xuất" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.csv,.docx,.xlsx,.zip" disabled={busy} onChange={(event) => setFile(event.target.files?.[0] ?? null)} /><Button type="primary" loading={busy} disabled={!file} onClick={upload}>Tải tài liệu lên</Button></Space><Table size="small" rowKey="id" dataSource={data.items ?? []} pagination={{ current: page, pageSize: 10, total: data.total ?? 0, onChange: setPage, showSizeChanger: false }} columns={[{ title: 'Tên tài liệu', dataIndex: 'original_name' }, { title: 'Dung lượng', dataIndex: 'size', render: (size) => `${new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 }).format(Number(size || 0) / 1024)} KB` }, { title: '', render: (_, row) => <Button size="small" onClick={async () => { try { await api.downloadDocument(row.id, row.original_name); } catch (err) { setError(err.message); } }}>Tải xuống</Button> }]} /></div>;
}
