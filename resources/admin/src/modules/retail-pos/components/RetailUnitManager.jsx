import { useState } from 'react';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import { uuid } from '../utils/retailFormat';

export default function RetailUnitManager({ open, onClose, units, api, branchId, onChanged, onCommand, disabled }) {
    const { message } = App.useApp();
    const [editor, setEditor] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const [form] = Form.useForm();
    const edit = (unit = null) => {
        setError(null); setEditor({ unit }); form.resetFields();
        form.setFieldsValue(unit ? { ...unit, is_active: Boolean(unit.is_active) } : { is_active: true });
    };
    const save = async (unit, values, archive = false) => {
        setBusy(true); setError(null);
        try {
            const response = await api.command(unit ? `units/${uuid(unit)}` : 'units', { ...values, branch_id: branchId, ...(unit ? { expected_version: unit.version } : {}) }, archive ? 'DELETE' : unit ? 'PUT' : 'POST');
            setEditor(null);
            await onChanged(!unit ? response.data : null);
            message.success(archive ? 'Đã ngừng sử dụng đơn vị.' : 'Đã lưu đơn vị.');
        } catch (failure) { setError(failure.message); }
        finally { setBusy(false); onCommand?.(); }
    };
    return <>
        <Modal title="Quản lý đơn vị" open={open} centered width={720} zIndex={1100} maskClosable={false} closable={!busy && !editor} keyboard={!busy && !editor} onCancel={onClose} footer={<Button disabled={busy || Boolean(editor)} onClick={onClose}>Đóng</Button>}>
            <div className="retail-unit-toolbar"><span>Danh mục đơn vị dùng chung trong chuỗi.</span><Button type="primary" icon={<PlusOutlined />} disabled={busy || disabled} onClick={() => edit()}>Thêm đơn vị</Button></div>
            {error && !editor && <Alert type="error" showIcon message={error} style={{ marginBottom: 12 }} />}
            <Table size="small" rowKey={uuid} dataSource={units} pagination={{ pageSize: 6, showSizeChanger: false }} scroll={{ x: 560 }} columns={[
                { title: 'Mã', dataIndex: 'code', width: 80 }, { title: 'Tên đơn vị', dataIndex: 'name' },
                { title: 'Trạng thái', width: 145, render: (_, unit) => <Tag color={unit.in_use ? 'blue' : unit.is_active ? 'green' : 'default'}>{unit.in_use ? 'Đã sử dụng' : unit.is_active ? 'Đang dùng' : 'Ngừng dùng'}</Tag> },
                { title: 'Thao tác', width: 115, render: (_, unit) => <Space size={0}><Button size="small" type="text" icon={<EditOutlined />} disabled={busy || disabled} onClick={() => edit(unit)}>Sửa</Button><Popconfirm title="Ngừng sử dụng đơn vị này?" description="Có thể kích hoạt lại trong form sửa." okText="Xóa" cancelText="Hủy" onConfirm={() => save(unit, {}, true)}><Button size="small" type="text" danger icon={<DeleteOutlined />} aria-label={`Xóa đơn vị ${unit.code}`} disabled={busy || disabled || unit.in_use || !unit.is_active} /></Popconfirm></Space> },
            ]} />
            <div className="retail-muted">Đơn vị đã sử dụng chỉ được đổi tên hiển thị; mã đơn vị được giữ nguyên.</div>
        </Modal>
        <Modal title={editor?.unit ? 'Sửa đơn vị' : 'Thêm đơn vị'} open={Boolean(editor)} centered width={480} zIndex={1200} forceRender maskClosable={false} closable={!busy} keyboard={!busy} onCancel={() => { if (!busy) { setEditor(null); setError(null); } }} footer={<Space><Button disabled={busy} onClick={() => { setEditor(null); setError(null); }}>Hủy</Button><Button type="primary" loading={busy} onClick={() => form.submit()}>Lưu đơn vị</Button></Space>}>
            {error && editor && <Alert type="error" showIcon message={error} style={{ marginBottom: 12 }} />}
            <Form name="retail-unit-editor" form={form} layout="vertical" disabled={busy || disabled} onFinish={(values) => save(editor.unit, values)} style={{ marginTop: 16 }}>
                <Row gutter={16}><Col span={8}><Form.Item name="code" label="Mã đơn vị" rules={[{ required: true, message: 'Nhập mã đơn vị.' }, { pattern: /^[A-Za-z0-9._-]+$/, message: 'Dùng chữ không dấu, số, dấu . _ -' }]}><Input maxLength={24} disabled={Boolean(editor?.unit)} placeholder="VD: box" /></Form.Item></Col><Col span={16}><Form.Item name="name" label="Tên đơn vị" rules={[{ required: true, whitespace: true, message: 'Nhập tên đơn vị.' }]}><Input maxLength={100} placeholder="VD: Hộp" /></Form.Item></Col></Row>
                <Form.Item name="is_active" label="Trạng thái"><Select disabled={Boolean(editor?.unit?.in_use)} options={[{ value: true, label: 'Đang dùng' }, { value: false, label: 'Ngừng dùng' }]} /></Form.Item>
            </Form>
        </Modal>
    </>;
}
