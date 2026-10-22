import { useEffect, useState } from 'react';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import SaveOutlined from '@ant-design/icons/SaveOutlined';
import { uuid } from '../utils/retailFormat';

export default function RetailSupplierManager({ open, onClose, suppliers, api, onChanged, onCommand }) {
    const { message } = App.useApp();
    const [query, setQuery] = useState('');
    const [editor, setEditor] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const [form] = Form.useForm();
    useEffect(() => {
        if (open) { setQuery(''); setError(null); }
    }, [open]);
    const edit = (supplier = null) => {
        setError(null);
        setEditor({ supplier });
        form.resetFields();
        form.setFieldsValue(supplier ? { ...supplier, is_active: Boolean(supplier.is_active) } : { payment_terms_days: 0, is_active: true });
    };
    const mutate = async (supplier, values, archive = false) => {
        setBusy(true); setError(null);
        try {
            const path = supplier ? `suppliers/${uuid(supplier)}` : 'suppliers';
            await api.command(path, { ...values, ...(supplier ? { expected_revision: supplier.revision } : {}) }, archive ? 'DELETE' : supplier ? 'PUT' : 'POST');
            setEditor(null);
            message.success(archive ? 'Đã ngừng sử dụng nhà cung cấp.' : 'Đã lưu nhà cung cấp.');
            await onChanged();
        } catch (failure) { setError(failure.message); }
        finally { setBusy(false); onCommand?.(); }
    };
    const filtered = suppliers.filter((supplier) => `${supplier.code} ${supplier.name} ${supplier.phone ?? ''} ${supplier.email ?? ''}`.toLocaleLowerCase().includes(query.trim().toLocaleLowerCase()));
    return <>
        <Modal title="Quản lý nhà cung cấp" open={open} width={960} centered onCancel={onClose} closable={!busy && !editor} keyboard={!busy && !editor} maskClosable={false} footer={<Button disabled={busy || Boolean(editor)} onClick={onClose}>Đóng</Button>}>
            {error && !editor && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}
            <div className="retail-supplier-toolbar"><Input.Search value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Tìm mã, tên, điện thoại hoặc email" allowClear aria-label="Tìm nhà cung cấp" /><Button type="primary" icon={<PlusOutlined />} disabled={busy} onClick={() => edit()}>Thêm NCC</Button></div>
            <Table size="small" rowKey={uuid} dataSource={filtered} pagination={{ pageSize: 8, showSizeChanger: false }} scroll={{ x: 780 }} locale={{ emptyText: 'Chưa có nhà cung cấp phù hợp.' }} columns={[
                { title: 'Mã NCC', dataIndex: 'code', width: 110 },
                { title: 'Nhà cung cấp', dataIndex: 'name', width: 220 },
                { title: 'Điện thoại', dataIndex: 'phone', width: 130, render: (value) => value || '—' },
                { title: 'Hạn trả', dataIndex: 'payment_terms_days', width: 90, render: (value) => `${value} ngày` },
                { title: 'Trạng thái', dataIndex: 'is_active', width: 145, render: (value) => <Tag color={value ? 'green' : 'default'}>{value ? 'Đang sử dụng' : 'Ngừng sử dụng'}</Tag> },
                { title: 'Thao tác', width: 120, render: (_, supplier) => <Space size={4}>
                    <Button type="text" size="small" icon={<EditOutlined />} disabled={busy} onClick={() => edit(supplier)}>Sửa</Button>
                    {Boolean(supplier.is_active) && <Popconfirm title="Xóa nhà cung cấp khỏi danh sách sử dụng?" description="Giữ lịch sử đơn mua. Có thể kích hoạt lại trong form sửa." okText="Xóa" cancelText="Hủy" onConfirm={() => mutate(supplier, {}, true)}><Button type="text" size="small" danger icon={<DeleteOutlined />} disabled={busy} aria-label={`Xóa nhà cung cấp ${supplier.code}`} /></Popconfirm>}
                </Space> },
            ]} />
        </Modal>
        <Modal title={editor?.supplier ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp'} open={Boolean(editor)} width={620} centered zIndex={1100} onCancel={() => { if (!busy) { setEditor(null); setError(null); } }} closable={!busy} keyboard={!busy} maskClosable={false} forceRender footer={<Space><Button disabled={busy} onClick={() => { setEditor(null); setError(null); }}>Hủy</Button><Button type="primary" icon={<SaveOutlined />} loading={busy} onClick={() => form.submit()}>Lưu nhà cung cấp</Button></Space>}>
            {error && editor && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}
            <Form form={form} layout="vertical" disabled={busy} onFinish={(values) => mutate(editor.supplier, values)}>
                <Row gutter={16}>
                    <Col xs={24} sm={9}><Form.Item name="code" label="Mã NCC" rules={[{ required: true, whitespace: true, message: 'Nhập mã nhà cung cấp.' }]}><Input maxLength={60} placeholder="VD: NCC001" /></Form.Item></Col>
                    <Col xs={24} sm={15}><Form.Item name="name" label="Tên nhà cung cấp" rules={[{ required: true, whitespace: true, message: 'Nhập tên nhà cung cấp.' }]}><Input maxLength={255} /></Form.Item></Col>
                    <Col xs={24} sm={12}><Form.Item name="phone" label="Điện thoại"><Input maxLength={40} /></Form.Item></Col>
                    <Col xs={24} sm={12}><Form.Item name="email" label="Email" rules={[{ type: 'email', message: 'Email không hợp lệ.' }]}><Input maxLength={255} /></Form.Item></Col>
                    <Col span={24}><Form.Item name="address" label="Địa chỉ"><Input.TextArea rows={2} maxLength={4000} /></Form.Item></Col>
                    <Col xs={24} sm={12}><Form.Item name="payment_terms_days" label="Hạn thanh toán (ngày)" rules={[{ required: true, message: 'Nhập hạn thanh toán.' }]}><InputNumber min={0} max={365} precision={0} style={{ width: '100%' }} /></Form.Item></Col>
                    <Col xs={24} sm={12}><Form.Item name="is_active" label="Trạng thái"><Select options={[{ value: true, label: 'Đang sử dụng' }, { value: false, label: 'Ngừng sử dụng' }]} /></Form.Item></Col>
                </Row>
            </Form>
        </Modal>
    </>;
}
