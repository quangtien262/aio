import { useEffect, useState } from 'react';
import ContactsOutlined from '@ant-design/icons/ContactsOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import IdcardOutlined from '@ant-design/icons/IdcardOutlined';
import LockOutlined from '@ant-design/icons/LockOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import SaveOutlined from '@ant-design/icons/SaveOutlined';
import SlidersOutlined from '@ant-design/icons/SlidersOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Divider from 'antd/es/divider';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Tabs from 'antd/es/tabs';
import Typography from 'antd/es/typography';
import { customerId, customerRows, serializeCustomerProfile } from '../utils/customerData';
import '../styles/customers-profiles.css';

export const fieldKey = (field) => `${field.namespace ?? 'customers'}.${field.key}`;
export function CustomerDynamicInput({ field, ...control }) {
    if (field.type === 'boolean') return <Switch {...control} />;
    if (field.type === 'number') return <InputNumber stringMode style={{ width: '100%' }} {...control} />;
    if (field.type === 'date') return <Input type="date" {...control} />;
    if (field.type === 'select') return <Select allowClear options={customerRows(field.options).map((item) => typeof item === 'string' ? { value: item, label: item } : item)} {...control} />;
    return <Input maxLength={2000} {...control} />;
}

export default function CustomerProfileEditor({ open, profile, fields = [], sensitiveAllowed = true, onClose, onSave }) {
    const [form] = Form.useForm();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const [tab, setTab] = useState('general');
    const sensitiveVisible = sensitiveAllowed && (!profile || profile.sensitive_visible !== false);
    const visibleFields = fields.filter((field) => field.status !== 'archived' && (!field.sensitive || sensitiveVisible));
    useEffect(() => {
        if (!open) return;
        form.resetFields(); setError(null); setTab('general');
        const contacts = customerRows(profile?.contacts);
        form.setFieldsValue({ type: 'person', status: 'active', ...profile,
            contacts: contacts.length ? contacts : [
                ...(profile?.phone ? [{ kind: 'phone', value: profile.phone, label: 'Chính', is_primary: true }] : []),
                ...(profile?.email ? [{ kind: 'email', value: profile.email, label: 'Chính', is_primary: true }] : []),
            ],
            addresses: customerRows(profile?.addresses), tags: customerRows(profile?.tags).map((tag) => typeof tag === 'string' ? tag : tag.name ?? tag.label),
            custom_fields: profile?.custom_fields ?? {},
        });
    }, [open, profile, form]);
    const submit = async (values) => {
        setBusy(true); setError(null);
        try {
            const payload = serializeCustomerProfile(values, { profile, fields, sensitiveVisible });
            await onSave(payload, profile ? customerId(profile) : null);
            onClose();
        } catch (failure) { setError(failure.message); }
        finally { setBusy(false); }
    };
    const general = <div className="customers-profile-form-section">
        <div className="customers-profile-section-heading"><IdcardOutlined /><div><Typography.Title level={5}>Thông tin nhận diện</Typography.Title><Typography.Text type="secondary">Thông tin cơ bản để các ứng dụng nhận diện đúng khách hàng.</Typography.Text></div></div>
        <Row gutter={16}><Col xs={24} sm={8}><Form.Item name="type" label="Loại hồ sơ" rules={[{ required: true }]}><Select options={[{ value: 'person', label: 'Cá nhân' }, { value: 'company', label: 'Doanh nghiệp' }]} /></Form.Item></Col><Col xs={24} sm={16}><Form.Item name="name" label="Tên hiển thị" rules={[{ required: true, whitespace: true, message: 'Nhập tên khách hàng.' }]}><Input autoFocus maxLength={255} placeholder="Ví dụ: Nguyễn Minh Anh hoặc Công ty An Phát" /></Form.Item></Col></Row>
        <Form.Item name="code" label="Mã khách hàng" extra="Để trống khi tạo mới để hệ thống cấp mã."><Input maxLength={40} placeholder="Tự động cấp mã khi tạo mới" /></Form.Item>
        <Form.Item noStyle shouldUpdate>{({ getFieldValue }) => getFieldValue('type') === 'company' ? <Row gutter={16}><Col xs={24} sm={16}><Form.Item name="legal_name" label="Tên pháp lý"><Input maxLength={255} /></Form.Item></Col>{sensitiveVisible && <Col xs={24} sm={8}><Form.Item name="tax_code" label="Mã số thuế"><Input maxLength={40} /></Form.Item></Col>}</Row> : sensitiveVisible && <Form.Item name="birthday" label="Ngày sinh"><Input type="date" /></Form.Item>}</Form.Item>
        <Form.Item name="tags" label="Nhãn"><Select mode="tags" tokenSeparators={[',']} placeholder="Nhập nhãn rồi Enter" /></Form.Item>
        {sensitiveVisible && <Form.Item name="notes" label="Ghi chú chung"><Input.TextArea rows={4} maxLength={4000} placeholder="Thông tin cần lưu ý khi phục vụ khách hàng…" /></Form.Item>}
        <Typography.Paragraph type="secondary">Ghi chú chung được chia sẻ theo quyền của danh bạ. Thông tin giao dịch được giữ tại ứng dụng nguồn.</Typography.Paragraph>
    </div>;
    const contacts = !sensitiveVisible ? <Alert showIcon type="info" message="Liên hệ và địa chỉ được bảo vệ" description="Bạn có thể cập nhật thông tin chung. Cần quyền dữ liệu riêng tư để xem và sửa liên hệ, địa chỉ." /> : <div className="customers-profile-form-section"><div className="customers-profile-section-heading"><ContactsOutlined /><div><Typography.Title level={5}>Kênh liên hệ & địa chỉ</Typography.Title><Typography.Text type="secondary">Giữ thông tin phục vụ khách hàng rõ ràng và có tổ chức.</Typography.Text></div></div><Typography.Paragraph type="secondary">Thêm nhiều liên hệ và địa chỉ. Chọn liên hệ chính cho từng loại; không thay đổi tài khoản đăng nhập website.</Typography.Paragraph>
        <Form.List name="contacts">{(items, { add, remove }) => <>{items.map(({ key, name }) => <div className="customers-form-block" key={key}><Row gutter={12}><Col xs={24} sm={8}><Form.Item name={[name, 'kind']} label="Loại" rules={[{ required: true }]}><Select options={[{ value: 'phone', label: 'Điện thoại' }, { value: 'email', label: 'Email' }]} /></Form.Item></Col><Col xs={24} sm={16}><Form.Item name={[name, 'value']} label="Liên hệ" rules={[{ required: true, message: 'Nhập thông tin liên hệ.' }]}><Input maxLength={255} /></Form.Item></Col><Col xs={16} sm={16}><Form.Item name={[name, 'label']} label="Nhãn"><Input placeholder="Cá nhân, công việc…" maxLength={80} /></Form.Item></Col><Col xs={8} sm={8}><Form.Item name={[name, 'is_primary']} label="Liên hệ chính" valuePropName="checked"><Switch /></Form.Item></Col></Row><Button danger size="small" onClick={() => remove(name)}>Bỏ liên hệ</Button></div>)}<Button type="dashed" block onClick={() => add({ kind: 'phone', label: '', is_primary: false })}>+ Thêm liên hệ</Button></>}</Form.List>
        <Divider>Địa chỉ</Divider><Form.List name="addresses">{(items, { add, remove }) => <>{items.map(({ key, name }) => <div className="customers-form-block" key={key}><Row gutter={12}><Col xs={24} sm={12}><Form.Item name={[name, 'label']} label="Tên địa chỉ"><Input placeholder="Nhà, công ty…" maxLength={80} /></Form.Item></Col><Col xs={24} sm={12}><Form.Item name={[name, 'kind']} label="Mục đích"><Select options={[{ value: 'shipping', label: 'Giao hàng' }, { value: 'billing', label: 'Thanh toán' }, { value: 'both', label: 'Cả hai' }]} /></Form.Item></Col><Col span={24}><Form.Item name={[name, 'line1']} label="Địa chỉ" rules={[{ required: true }]}><Input maxLength={500} /></Form.Item></Col><Col span={24}><Form.Item name={[name, 'line2']} label="Chi tiết bổ sung"><Input maxLength={500} /></Form.Item></Col><Col xs={24} sm={12}><Form.Item name={[name, 'city']} label="Tỉnh / thành phố"><Input maxLength={120} /></Form.Item></Col><Col xs={24} sm={12}><Form.Item name={[name, 'region']} label="Khu vực"><Input maxLength={120} /></Form.Item></Col><Col xs={12} sm={8}><Form.Item name={[name, 'country_code']} label="Mã quốc gia"><Input maxLength={2} placeholder="VN" /></Form.Item></Col><Col xs={12} sm={8}><Form.Item name={[name, 'postal_code']} label="Mã bưu chính"><Input maxLength={30} /></Form.Item></Col><Col xs={12} sm={8}><Form.Item name={[name, 'is_default']} label="Mặc định" valuePropName="checked"><Switch /></Form.Item></Col></Row><Button danger size="small" onClick={() => remove(name)}>Bỏ địa chỉ</Button></div>)}<Button block type="dashed" onClick={() => add({ kind: 'shipping', country_code: 'VN', is_default: false })}>+ Thêm địa chỉ</Button></>}</Form.List>
    </div>;
    const custom = <div className="customers-profile-form-section"><div className="customers-profile-section-heading"><SlidersOutlined /><div><Typography.Title level={5}>Thông tin mở rộng</Typography.Title><Typography.Text type="secondary">Các trường được cấu hình riêng cho danh bạ này.</Typography.Text></div></div>{visibleFields.map((field) => <Form.Item key={fieldKey(field)} name={['custom_fields', fieldKey(field)]} label={<Space>{field.label}{field.sensitive && <Typography.Text type="secondary">Riêng tư</Typography.Text>}</Space>} valuePropName={field.type === 'boolean' ? 'checked' : 'value'} rules={field.required ? [{ required: true, message: `Nhập ${field.label}.` }] : []}><CustomerDynamicInput field={field} /></Form.Item>)}{!visibleFields.length && <Typography.Paragraph type="secondary">Chưa có trường mở rộng trong phạm vi được cấp. Người quản lý có thể cấu hình tại Thiết lập danh bạ.</Typography.Paragraph>}</div>;
    return <Drawer rootClassName="customers-profile-drawer customers-profile-editor" open={open} title={<Space>{profile ? <EditOutlined /> : <PlusOutlined />}{profile ? 'Chỉnh sửa hồ sơ' : 'Thêm khách hàng'}</Space>} width={800} onClose={() => !busy && onClose()} maskClosable={false} footer={<div className="customers-profile-footer"><span><LockOutlined /> Chia sẻ theo quyền danh bạ</span><Space><Button disabled={busy} onClick={onClose}>Hủy</Button><Button type="primary" icon={<SaveOutlined />} loading={busy} onClick={() => form.submit()}>Lưu hồ sơ</Button></Space></div>}>
        <div className="customers-profile-editor-intro"><span className="customers-profile-editor-icon">{profile ? <EditOutlined /> : <IdcardOutlined />}</span><div><span className="customers-profile-kicker">HỒ SƠ KHÁCH HÀNG</span><Typography.Title level={3}>{profile?.name || 'Tạo hồ sơ mới'}</Typography.Title><Typography.Paragraph>Thông tin được lưu trong danh bạ hiện tại và sử dụng chung theo quyền của từng ứng dụng.</Typography.Paragraph></div></div>
        {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}
        <Form form={form} layout="vertical" disabled={busy} onFinish={submit} onFinishFailed={({ errorFields }) => { const first = errorFields[0]?.name?.[0]; setTab(['contacts', 'addresses'].includes(first) ? 'contacts' : first === 'custom_fields' ? 'custom' : 'general'); }}><Tabs activeKey={tab} onChange={setTab} items={[{ key: 'general', label: <Space><IdcardOutlined />Thông tin chung</Space>, forceRender: true, children: general }, { key: 'contacts', label: <Space><ContactsOutlined />Liên hệ & địa chỉ</Space>, forceRender: true, children: contacts }, { key: 'custom', label: <Space><SlidersOutlined />Trường mở rộng</Space>, forceRender: true, children: custom }]} /></Form>
    </Drawer>;
}
