import { useCallback, useEffect, useRef, useState } from 'react';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import ExperimentOutlined from '@ant-design/icons/ExperimentOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Empty from 'antd/es/empty';
import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import { adminApi } from '../../../shared/config/routes';

export default function ModuleTestDataManager({ moduleCard, callAdminApi, onAction, pendingAction, onClose }) {
    const [batches, setBatches] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [removal, setRemoval] = useState(null);
    const requestId = useRef(crypto.randomUUID());
    const requestSequence = useRef(0);
    const moduleKey = moduleCard?.key ?? null;
    const endpoint = moduleKey ? `modules/${moduleKey}/demo-data` : 'modules/demo-data';
    const busy = Boolean(pendingAction);
    const reload = useCallback(async () => {
        const sequence = ++requestSequence.current;
        setLoading(true);
        setError(null);
        try {
            const payload = await callAdminApi(adminApi(endpoint));
            if (sequence === requestSequence.current) setBatches(payload.data?.batches ?? []);
        } catch (failure) {
            if (sequence === requestSequence.current) setError(failure.message ?? 'Không tải được danh sách data demo.');
        } finally {
            if (sequence === requestSequence.current) setLoading(false);
        }
    }, [callAdminApi, endpoint]);
    useEffect(() => {
        reload();
        return () => { requestSequence.current += 1; };
    }, [reload]);

    const create = async () => {
        if (busy || moduleCard?.status !== 'enabled' || !moduleCard.test_data?.supported) return;
        const saved = await onAction(moduleKey, 'demo-data', { request_id: requestId.current });
        if (saved !== false) {
            requestId.current = crypto.randomUUID();
            await reload();
        }
    };
    const remove = async () => {
        if (busy || !removal) return;
        const saved = await onAction(moduleKey ?? '__all__', 'delete-demo-data', { confirmed: true, ...(removal.id ? { batch_id: removal.id } : {}) });
        if (saved !== false) {
            setRemoval(null);
            await reload();
        }
    };
    const recordCount = batches.reduce((total, batch) => total + batch.record_count, 0);

    return <>
        <Modal open zIndex={1250} title={<Space><ExperimentOutlined />{moduleCard ? `Data demo · ${moduleCard.name}` : 'Quản lý data demo'}</Space>}
            onCancel={() => { if (!busy && !removal) onClose(); }}
            closable={!busy} maskClosable={!busy && !removal} keyboard={!busy && !removal}
            footer={<Button disabled={busy} onClick={onClose}>Đóng</Button>} width="min(880px, 96vw)" centered destroyOnHidden className="module-test-data-modal">
            <Alert type="info" showIcon message="Mỗi lần tạo là một đợt demo riêng"
                description="Dữ liệu demo được đánh dấu và ghi nhận theo đợt để dọn nhanh. Hệ thống không ghi đè dữ liệu có sẵn và sẽ chặn xóa nếu dữ liệu bên ngoài đang sử dụng đợt demo." />
            {moduleCard ? <div className="module-test-data-preview">
                <div><strong>Dữ liệu sẽ tạo</strong><span>Trong website đang chọn · chỉ tạo bản ghi mới</span></div>
                <ul>{(moduleCard.test_data?.preview ?? []).map((item) => <li key={item}>{item}</li>)}</ul>
                <Button type="primary" icon={<PlusOutlined />} onClick={create} loading={pendingAction === 'demo-data'} disabled={busy || loading || moduleCard.status !== 'enabled' || !moduleCard.test_data?.supported || !moduleCard.test_data?.storage_ready}>Tạo data demo</Button>
            </div> : null}
            <div className="module-test-data-toolbar">
                <div><strong>{batches.length} đợt demo</strong><span>{recordCount} bản ghi được đánh dấu</span></div>
                <Space wrap>
                    <Button icon={<ReloadOutlined />} onClick={reload} disabled={busy} loading={loading}>Tải lại</Button>
                    <Button danger icon={<DeleteOutlined />} disabled={busy || loading || !batches.length} onClick={() => setRemoval({ label: moduleCard ? `toàn bộ data demo của ${moduleCard.name}` : 'toàn bộ data demo trong website đang chọn' })}>Xóa toàn bộ data demo</Button>
                </Space>
            </div>
            {error ? <Alert type="error" showIcon message={error} style={{ marginBottom: 12 }} /> : null}
            <Table size="small" rowKey="id" loading={loading} dataSource={batches} scroll={{ x: 620 }}
                locale={{ emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa có đợt data demo nào" /> }}
                pagination={{ pageSize: 8, hideOnSinglePage: true, showSizeChanger: false }}
                columns={[
                    { title: 'Ứng dụng / Đợt demo', render: (_, batch) => <div className="module-test-data-batch"><strong>{batch.module_name}</strong><span><Tag color="purple">DEMO</Tag>{batch.id}</span></div> },
                    { title: 'Bản ghi', dataIndex: 'record_count', width: 75, align: 'right' },
                    { title: 'Ngày tạo', dataIndex: 'created_at', width: 135, render: (value) => {
                        const date = new Date(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value) ? value.replace(' ', 'T') + 'Z' : value);
                        return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' });
                    } },
                    { title: '', width: 45, render: (_, batch) => <Button type="text" danger icon={<DeleteOutlined />} disabled={busy} aria-label={`Xóa đợt demo ${batch.id}`} onClick={() => setRemoval({ id: batch.id, label: `đợt demo ${batch.id}` })} /> },
                ]} />
        </Modal>
        <Modal open={Boolean(removal)} title="Xóa dữ liệu demo" centered width={490} zIndex={1300}
            onCancel={() => { if (!busy) setRemoval(null); }} onOk={remove} okText="Xóa data demo" cancelText="Hủy"
            confirmLoading={pendingAction === 'delete-demo-data'} okButtonProps={{ danger: true }} closable={!busy} maskClosable={!busy} keyboard={!busy}>
            <p>Xóa {removal?.label}?</p>
            <p>Chỉ các bản ghi được hệ thống đánh dấu trong những đợt demo này sẽ bị xóa. Thao tác không thể hoàn tác. Nếu có liên kết từ dữ liệu bên ngoài, hệ thống sẽ dừng và giữ nguyên dữ liệu.</p>
        </Modal>
    </>;
}
