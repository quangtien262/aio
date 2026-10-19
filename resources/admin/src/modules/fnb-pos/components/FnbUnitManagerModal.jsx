import PlusOutlined from '@ant-design/icons/PlusOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Radio from 'antd/es/radio';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import { useState } from 'react';
import { createIdempotencyKey } from '../api/fnbApi';
import { useFnbWorkspace } from '../state/FnbWorkspaceContext';

const dimensions = { mass: 'Khối lượng (g)', volume: 'Thể tích (ml)', count: 'Số lượng (cái)' };

export default function FnbUnitManagerModal({ open, units, loading, onClose, onSaved }) {
    const { api, can, runCommand } = useFnbWorkspace();
    const [editor, setEditor] = useState(null);
    const [busy, setBusy] = useState(false);
    const [form] = Form.useForm();
    const dimension = Form.useWatch('dimension', form) ?? 'count';
    const editable = can('fnb.recipe.manage') && can('fnb.recipe.master.manage');
    const edit = (record = null) => {
        form.resetFields();
        form.setFieldsValue(record ?? { dimension: 'count', factor: '1', status: 'active' });
        setEditor({ record });
    };
    const save = async (values) => {
        const record = editor?.record;
        const key = createIdempotencyKey('unit-save');
        setBusy(true);
        try {
            await runCommand({
                execute: (auth) => api.command(record ? `units/${record.id}` : 'units', { ...values, ...(record ? { expected_version: record.version } : {}) }, { method: record ? 'PUT' : 'POST', idempotencyKey: key, ...auth }),
                successMessage: 'Đã lưu đơn vị.',
                onSuccess: async () => { await onSaved(); setEditor(null); },
                onConflict: onSaved,
            });
        } finally { setBusy(false); }
    };
    const archive = async (record) => {
        const key = createIdempotencyKey('unit-archive');
        setBusy(true);
        try {
            await runCommand({
                execute: (auth) => api.command(`units/${record.id}/archive`, { expected_version: record.version }, { idempotencyKey: key, ...auth }),
                successMessage: 'Đã xóa đơn vị khỏi danh sách.', onSuccess: onSaved, onConflict: onSaved,
            });
        } finally { setBusy(false); }
    };
    const close = () => { setEditor(null); onClose(); };
    const conversionLocked = editor?.record?.is_system || editor?.record?.in_use;
    return <Modal title={editor ? (editor.record ? 'Cập nhật đơn vị' : 'Thêm đơn vị') : 'Quản lý đơn vị'} open={open} width={editor ? 560 : 900} centered onCancel={busy ? undefined : close} closable={!busy} maskClosable={!busy} styles={{ body: { maxHeight: '65vh', overflowY: 'auto' } }} footer={editor ? <Space><Button disabled={busy} onClick={() => setEditor(null)}>Quay lại</Button><Button type="primary" loading={busy} onClick={() => form.submit()}>Lưu đơn vị</Button></Space> : <Button onClick={close} disabled={busy}>Đóng</Button>}>
        {editor ? <Form form={form} disabled={busy} layout="vertical" onFinish={save}>
            <Row gutter={16}>
                <Col xs={24} sm={9}><Form.Item name="code" label="Mã đơn vị" normalize={(value) => value.trim().toLowerCase()} rules={[{ required: true, message: 'Nhập mã đơn vị.' }, { pattern: /^[a-z][a-z0-9_-]*$/, message: 'Bắt đầu bằng chữ; dùng chữ không dấu, số, - hoặc _.' }, { max: 30 }]}><Input disabled={Boolean(editor.record)} maxLength={30} placeholder="Ví dụ: chai, hop" /></Form.Item></Col>
                <Col xs={24} sm={15}><Form.Item name="name" label="Tên đơn vị" rules={[{ required: true, message: 'Nhập tên đơn vị.' }, { max: 100 }]}><Input placeholder="Ví dụ: Chai 500 ml" /></Form.Item></Col>
            </Row>
            <Form.Item name="dimension" label="Nhóm quy đổi" rules={[{ required: true }]}><Select disabled={Boolean(conversionLocked)} options={Object.entries(dimensions).map(([value, label]) => ({ value, label }))} /></Form.Item>
            <Form.Item name="factor" label={`1 đơn vị bằng bao nhiêu ${dimension === 'mass' ? 'g' : dimension === 'volume' ? 'ml' : 'cái'}?`} rules={[{ required: true, message: 'Nhập hệ số quy đổi.' }, { pattern: /^\d+(\.\d{1,6})?$/, message: 'Nhập số dương, tối đa 6 chữ số thập phân.' }, { validator: (_, value) => Number(value) > 0 && Number(value) <= 1000000 ? Promise.resolve() : Promise.reject(new Error('Hệ số phải lớn hơn 0 và không quá 1.000.000.')) }]}><Input disabled={Boolean(conversionLocked)} inputMode="decimal" /></Form.Item>
            {conversionLocked ? <Alert type="info" showIcon message="Đơn vị chuẩn hoặc đã sử dụng được giữ nguyên nhóm quy đổi và hệ số." style={{ marginBottom: 16 }} /> : null}
            <Form.Item name="status" label="Trạng thái"><Radio.Group options={[{ value: 'active', label: 'Đang dùng' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item>
        </Form> : <>
            <div className="fnb-table-toolbar"><span>Đơn vị chuẩn có sẵn; thêm đơn vị riêng để dùng cho nguyên liệu và công thức.</span>{editable ? <Button icon={<PlusOutlined />} type="primary" disabled={busy || loading} onClick={() => edit()}>Thêm đơn vị</Button> : null}</div>
            <Table size="small" rowKey="id" loading={loading} dataSource={units} scroll={{ x: 670 }} columns={[
                { title: 'Mã', dataIndex: 'code', width: 100 },
                { title: 'Tên đơn vị', render: (_, row) => <Space wrap>{row.name}{row.is_system ? <Tag>Chuẩn</Tag> : null}</Space> },
                { title: 'Quy đổi', render: (_, row) => `${row.factor} ${row.dimension === 'mass' ? 'g' : row.dimension === 'volume' ? 'ml' : 'cái'}` },
                { title: 'Trạng thái', render: (_, row) => <Tag color={row.status === 'active' ? 'green' : undefined}>{row.status === 'active' ? 'Đang dùng' : 'Tạm ngưng'}</Tag> },
                ...(editable ? [{ key: 'actions', width: 150, render: (_, row) => <Space><Button size="small" disabled={busy} onClick={() => edit(row)}>Sửa</Button><Popconfirm title="Xóa đơn vị chưa sử dụng này?" onConfirm={() => archive(row)}><Button size="small" danger disabled={busy || Boolean(row.is_system) || row.in_use}>Xóa</Button></Popconfirm></Space> }] : []),
            ]} />
        </>}
    </Modal>;
}
