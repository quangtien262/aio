import PlusOutlined from '@ant-design/icons/PlusOutlined';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Typography from 'antd/es/typography';
import { useState } from 'react';
import { createIdempotencyKey } from '../api/fnbApi';
import { useFnbWorkspace } from '../state/FnbWorkspaceContext';

export default function FnbMenuChoiceManagerModal({ open, kind, definitions = [], onClose, onSaved, onCreated }) {
    const { api, runCommand } = useFnbWorkspace();
    const [form] = Form.useForm();
    const [editing, setEditing] = useState(null);
    const [busy, setBusy] = useState(false);
    const [search, setSearch] = useState('');
    const noun = kind === 'size' ? 'size' : 'topping';
    const reset = () => { setEditing(null); form.resetFields(); };
    const close = () => { reset(); setSearch(''); onClose(); };
    const save = async (values) => {
        const key = createIdempotencyKey(`${kind}-definition-save`);
        setBusy(true);
        try {
            await runCommand({
                execute: (auth) => api.command(`menu/${kind}-definitions${editing ? `/${editing.id}` : ''}`, { ...values, ...(editing ? { expected_version: editing.version } : {}) }, { method: editing ? 'PUT' : 'POST', idempotencyKey: key, ...auth }),
                successMessage: `Đã lưu ${noun}.`,
                onSuccess: async (result) => { await onSaved(); reset(); if (!editing) onCreated?.(result.data); },
                onConflict: onSaved,
            });
        } finally { setBusy(false); }
    };
    const archive = async (record) => {
        const key = createIdempotencyKey(`${kind}-definition-archive`);
        setBusy(true);
        try {
            await runCommand({
                execute: (auth) => api.command(`menu/${kind}-definitions/${record.id}/archive`, { expected_version: record.version }, { idempotencyKey: key, ...auth }),
                successMessage: `Đã xóa ${noun} khỏi danh mục.`,
                onSuccess: async () => { await onSaved(); if (editing?.id === record.id) reset(); }, onConflict: onSaved,
            });
        } finally { setBusy(false); }
    };
    const fold = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').toLowerCase();
    return <Modal title={`Quản lý ${noun}`} open={open} width={640} centered zIndex={1100} onCancel={busy ? undefined : close} closable={!busy} maskClosable={!busy} keyboard={!busy} footer={<Button disabled={busy} onClick={close}>Đóng</Button>} styles={{ body: { maxHeight: '70vh', overflowY: 'auto' } }}>
        <Typography.Paragraph type="secondary">Danh mục mã và tên dùng chung. Giá được đặt riêng trên từng {kind === 'size' ? 'món' : 'nhóm topping'}; sửa hoặc xóa tại đây không thay đổi các lựa chọn đã gán.</Typography.Paragraph>
        <Form name={`fnb-${kind}-definition`} form={form} layout="vertical" disabled={busy} onFinish={save}>
            <Row gutter={12}>
                <Col xs={24} sm={14}><Form.Item name="name" label={`Tên ${noun}`} rules={[{ required: true, whitespace: true, message: `Nhập tên ${noun}.` }, { max: 255 }]}><Input placeholder={kind === 'size' ? 'Ví dụ: Nhỏ, Vừa, Lớn' : 'Ví dụ: Trân châu trắng'} /></Form.Item></Col>
                <Col xs={24} sm={10}><Form.Item name="code" label={`Mã ${noun}`} normalize={(value) => value.trim()} rules={[{ max: 40 }, { pattern: /^[A-Za-z0-9_-]+$/, message: 'Chỉ dùng chữ không dấu, số, - hoặc _.' }]}><Input placeholder={editing ? 'Giữ mã hiện tại' : 'Để trống để tự tạo'} maxLength={40} /></Form.Item></Col>
            </Row>
            <Space style={{ marginBottom: 20 }}><Button type="primary" htmlType="submit" loading={busy} icon={editing ? undefined : <PlusOutlined />}>{editing ? 'Lưu thay đổi' : `Thêm ${noun}`}</Button>{editing ? <Button onClick={reset}>Hủy sửa</Button> : null}</Space>
        </Form>
        <Input.Search aria-label={`Tìm ${noun}`} placeholder={`Tìm tên hoặc mã ${noun}`} value={search} onChange={(event) => setSearch(event.target.value)} allowClear style={{ marginBottom: 12 }} />
        <Table size="small" rowKey="id" pagination={false} scroll={{ x: 450, y: 240 }} dataSource={definitions.filter((row) => fold(`${row.name} ${row.code}`).includes(fold(search)))} locale={{ emptyText: `Chưa có ${noun}` }} columns={[
            { title: 'Tên', dataIndex: 'name' }, { title: 'Mã', dataIndex: 'code', width: 140 },
            { title: '', width: 130, render: (_, row) => <Space size={4}><Button size="small" type="text" disabled={busy} onClick={() => { setEditing(row); form.setFieldsValue(row); }}>Sửa</Button><Popconfirm title={`Xóa ${noun} khỏi danh mục?`} description="Các lựa chọn đã gán vẫn được giữ nguyên." onConfirm={() => archive(row)}><Button size="small" type="text" danger disabled={busy}>Xóa</Button></Popconfirm></Space> },
        ]} />
    </Modal>;
}
