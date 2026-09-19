import { useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Typography from 'antd/es/typography';
import GlobalOutlined from '@ant-design/icons/GlobalOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import { adminApi } from '../../../shared/config/routes';

export default function SitemapSettings({ callAdminApi, canRefresh }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [data, setData] = useState(null);
    const [error, setError] = useState('');
    const load = async (refresh = false) => {
        setBusy(true);
        setError('');
        try {
            const result = await callAdminApi(adminApi(refresh ? 'sitemap/refresh' : 'sitemap'), { method: refresh ? 'POST' : 'GET' });
            setData(result.data ?? result);
        } catch (err) {
            setError(err.message || 'Không tải được sitemap. Vui lòng thử lại.');
        } finally { setBusy(false); }
    };
    return <>
        <Button icon={<GlobalOutlined />} onClick={() => { setOpen(true); load(); }}>Sitemap</Button>
        <Modal title="Sitemap website" open={open} onCancel={() => setOpen(false)} footer={null} width={680}>
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Typography.Text type="secondary">Danh sách URL công khai để công cụ tìm kiếm khám phá nội dung website.</Typography.Text>
                {error && <Alert type="error" showIcon message={error} />}
                {data && <>
                    <Typography.Paragraph copyable={{ text: data.url }} style={{ overflowWrap: 'anywhere', margin: 0 }}>{data.url}</Typography.Paragraph>
                    <Typography.Text>{data.total} URL · Cập nhật: {new Date(data.generated_at).toLocaleString('vi-VN')}</Typography.Text>
                    <Table rowKey="name" size="small" dataSource={data.files} pagination={false} columns={[
                        { title: 'Sitemap', dataIndex: 'name', render: (name, row) => <a href={row.url} target="_blank" rel="noreferrer">{name}</a> },
                        { title: 'Số URL', dataIndex: 'count', width: 90 },
                    ]} />
                </>}
                <Space wrap>
                    {data && <Button href={data.url} target="_blank" rel="noreferrer">Mở sitemap</Button>}
                    <Button icon={<ReloadOutlined />} loading={busy} disabled={!canRefresh} onClick={() => load(true)}>Làm mới</Button>
                    {error && <Button disabled={busy} onClick={() => load()}>Thử lại</Button>}
                </Space>
            </Space>
        </Modal>
    </>;
}
