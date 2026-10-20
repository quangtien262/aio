import { useEffect, useState } from 'react';
import ApartmentOutlined from '@ant-design/icons/ApartmentOutlined';
import AppstoreOutlined from '@ant-design/icons/AppstoreOutlined';
import ClockCircleOutlined from '@ant-design/icons/ClockCircleOutlined';
import ContactsOutlined from '@ant-design/icons/ContactsOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import IdcardOutlined from '@ant-design/icons/IdcardOutlined';
import LinkOutlined from '@ant-design/icons/LinkOutlined';
import LockOutlined from '@ant-design/icons/LockOutlined';
import MailOutlined from '@ant-design/icons/MailOutlined';
import PhoneOutlined from '@ant-design/icons/PhoneOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import Alert from 'antd/es/alert';
import Avatar from 'antd/es/avatar';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import Skeleton from 'antd/es/skeleton';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Timeline from 'antd/es/timeline';
import Typography from 'antd/es/typography';
import CustomerPicker from './CustomerPicker';
import CustomerLinksPanel from './CustomerLinksPanel';
import { customerDate, customerId, customerOption, customerRows } from '../utils/customerData';
import '../styles/customers-profiles.css';

const labels = { created: 'Tạo hồ sơ', updated: 'Cập nhật hồ sơ', archived: 'Lưu trữ hồ sơ', restored: 'Khôi phục hồ sơ', merged: 'Hợp nhất hồ sơ', consent_changed: 'Thay đổi đồng ý liên hệ', granted: 'Đã đồng ý', withdrawn: 'Đã rút lại', denied: 'Không đồng ý', unknown: 'Chưa ghi nhận', active: 'Đang hoạt động', person: 'Cá nhân', company: 'Doanh nghiệp' };
const tabDetails = {
    general: { icon: <IdcardOutlined />, title: 'Thông tin hồ sơ', description: 'Thông tin nhận diện và dữ liệu dùng chung của khách hàng.' },
    contacts: { icon: <ContactsOutlined />, title: 'Liên hệ & địa chỉ', description: 'Các kênh liên hệ và địa chỉ phục vụ khách hàng.' },
    consents: { icon: <SafetyCertificateOutlined />, title: 'Lựa chọn liên hệ', description: 'Ghi nhận và tôn trọng lựa chọn thực tế của khách hàng.' },
    activity: { icon: <ClockCircleOutlined />, title: 'Dòng thời gian hoạt động', description: 'Theo dõi thay đổi hồ sơ và hoạt động được chia sẻ từ ứng dụng nguồn.' },
    relationships: { icon: <ApartmentOutlined />, title: 'Quan hệ khách hàng', description: 'Các mối liên hệ đã được ghi nhận trong danh bạ.' },
    sources: { icon: <AppstoreOutlined />, title: 'Ứng dụng liên kết', description: 'Tra cứu định danh hồ sơ tại các ứng dụng đang kết nối.' },
    links: { icon: <LinkOutlined />, title: 'Tài khoản & đối tác kế toán', description: 'Liên kết tường minh theo phạm vi được cấp quyền.' },
};
const text = (value) => value == null || value === '' ? '—' : typeof value === 'boolean' ? value ? 'Có' : 'Không' : Array.isArray(value) ? value.join(', ') : String(value);

export default function CustomerProfileDrawer({ profileId, bookId, api, fields, can, nativePermissions, organizationOptions, onClose, onEdit, onChanged }) {
    const [profile, setProfile] = useState(null);
    const [activity, setActivity] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [activityError, setActivityError] = useState(null);
    const [action, setAction] = useState(null);
    const [busy, setBusy] = useState(false);
    const [actionError, setActionError] = useState(null);
    const [form] = Form.useForm();
    const path = `books/${bookId}/profiles/${profileId}`;
    const reload = async () => {
        const response = await api.read(path);
        setProfile(response.data);
        try { setActivity(customerRows((await api.read(`${path}/timeline`)).data)); setActivityError(null); }
        catch (failure) { setActivityError(failure.message); }
    };
    useEffect(() => {
        let cancelled = false;
        setProfile(null); setActivity([]); setError(null); setActivityError(null); setAction(null);
        if (!profileId || !bookId) return undefined;
        setLoading(true);
        api.read(path).then(async (response) => {
            if (cancelled) return;
            setProfile(response.data);
            try { const history = await api.read(`${path}/timeline`); if (!cancelled) setActivity(customerRows(history.data)); }
            catch (failure) { if (!cancelled) setActivityError(failure.message); }
        }).catch((failure) => { if (!cancelled) setError(failure.message); }).finally(() => { if (!cancelled) setLoading(false); });
        return () => { cancelled = true; };
    }, [profileId, bookId, api]);
    const start = (kind) => { form.resetFields(); setActionError(null); setAction(kind); };
    const submit = async (values) => {
        setBusy(true); setActionError(null);
        try {
            await api.command(`${path}/${action === 'consent' ? 'consents' : 'relationships'}`, { ...values, expected_version: profile.version });
            setAction(null); await reload(); await onChanged?.();
        } catch (failure) { setActionError(failure.message); }
        finally { setBusy(false); }
    };
    const tags = customerRows(profile?.tags);
    const tabs = profile ? [
        { key: 'general', label: 'Hồ sơ', children: <><Descriptions bordered size="small" column={{ xs: 1, sm: 2 }} items={[
            { key: 'code', label: 'Mã khách hàng', children: profile.code ?? '—' }, { key: 'type', label: 'Loại hồ sơ', children: labels[profile.type] ?? profile.type },
            { key: 'phone', label: 'Điện thoại chính', children: profile.phone ?? '—' }, { key: 'email', label: 'Email chính', children: profile.email ?? '—' },
            { key: 'legal', label: 'Tên pháp lý', children: profile.legal_name ?? '—' }, { key: 'tax', label: 'Mã số thuế', children: profile.tax_code ?? '—' },
            { key: 'birthday', label: 'Ngày sinh', children: profile.birthday ? String(profile.birthday).slice(0, 10) : '—' }, { key: 'created', label: 'Ngày tạo', children: customerDate(profile.created_at) },
            { key: 'tags', label: 'Nhãn', span: 'filled', children: tags.length ? tags.map((tag) => <Tag key={typeof tag === 'string' ? tag : tag.name}>{typeof tag === 'string' ? tag : tag.name ?? tag.label}</Tag>) : '—' },
            { key: 'notes', label: 'Ghi chú', span: 'filled', children: <span style={{ whiteSpace: 'pre-wrap' }}>{profile.notes || '—'}</span> },
        ]} /><Typography.Title level={5}>Trường mở rộng</Typography.Title>{Object.keys(profile.custom_fields ?? {}).length ? <Descriptions bordered size="small" column={1} items={Object.entries(profile.custom_fields ?? {}).map(([key, value]) => ({ key, label: fields.find((field) => `${field.namespace ?? 'customers'}.${field.key}` === key)?.label ?? key, children: text(value) }))} /> : <Typography.Text type="secondary">Chưa có dữ liệu trường mở rộng.</Typography.Text>}</> },
        { key: 'contacts', label: 'Liên hệ & địa chỉ', children: <><Table scroll={{ x: 620 }} rowKey={(item) => customerId(item) || `${item.kind}:${item.value}`} pagination={false} size="small" dataSource={customerRows(profile.contacts)} columns={[{ title: 'Loại', dataIndex: 'kind', render: (value) => value === 'phone' ? 'Điện thoại' : 'Email' }, { title: 'Liên hệ', dataIndex: 'value' }, { title: 'Nhãn', dataIndex: 'label' }, { title: 'Ưu tiên', dataIndex: 'is_primary', render: (value) => value ? <Tag color="green">Chính</Tag> : '—' }]} locale={{ emptyText: 'Chưa có liên hệ.' }} /><Typography.Title level={5}>Địa chỉ</Typography.Title><Space direction="vertical" style={{ width: '100%' }}>{customerRows(profile.addresses).map((address, index) => <Card key={customerId(address) || index} size="small" title={<Space>{address.label || 'Địa chỉ'}{address.is_default && <Tag color="green">Mặc định</Tag>}</Space>}><div>{[address.line1, address.line2, address.region, address.city, address.postal_code, address.country_code].filter(Boolean).join(', ')}</div><Typography.Text type="secondary">{address.kind === 'billing' ? 'Thanh toán' : address.kind === 'both' ? 'Giao hàng & thanh toán' : 'Giao hàng'}</Typography.Text></Card>)}</Space>{!customerRows(profile.addresses).length && <Empty description="Chưa có địa chỉ." />}</> },
        { key: 'consents', label: 'Đồng ý liên hệ', children: <><Alert type="info" showIcon message="Ghi nhận lựa chọn thực tế của khách" description="Tạo hồ sơ hoặc nhập dữ liệu không đồng nghĩa với đồng ý nhận quảng cáo." style={{ marginBottom: 16 }} />{can('customers.consent.manage') && <Button onClick={() => start('consent')} style={{ marginBottom: 16 }}>Ghi nhận lựa chọn</Button>}<Table scroll={{ x: 620 }} rowKey={(item) => customerId(item) || `${item.purpose}:${item.channel}`} dataSource={customerRows(profile.consents)} pagination={false} columns={[{ title: 'Mục đích', dataIndex: 'purpose' }, { title: 'Kênh', dataIndex: 'channel' }, { title: 'Trạng thái', render: (_, item) => labels[item.status ?? item.state] ?? item.status ?? item.state ?? '—' }, { title: 'Thời điểm', render: (_, item) => customerDate(item.occurred_at ?? item.recorded_at ?? item.updated_at ?? item.created_at) }, { title: 'Nguồn', dataIndex: 'source' }]} locale={{ emptyText: 'Chưa ghi nhận lựa chọn liên hệ.' }} /></> },
        { key: 'activity', label: 'Hoạt động', children: <>{activityError && <Alert type="error" message={activityError} showIcon style={{ marginBottom: 16 }} />}<Typography.Paragraph type="secondary">Chỉ hiển thị hoạt động trong phạm vi được cấp quyền. Thông tin giao dịch được giữ tại ứng dụng nguồn.</Typography.Paragraph>{activity.length ? <Timeline items={activity.map((item) => ({ key: customerId(item), children: <div><Space wrap><Typography.Text strong>{labels[String(item.event_type).replace(/^customer\./, '').replace(/\.v\d+$/, '')] ?? item.title ?? item.event_type ?? item.type}</Typography.Text>{(item.module_key ?? item.source_module) && <Tag>{item.module_key ?? item.source_module}</Tag>}</Space><div className="customers-muted">{customerDate(item.occurred_at ?? item.created_at)}{item.branch_name ? ` · ${item.branch_name}` : ''}</div>{item.summary && <div>{item.summary}</div>}{item.source_url?.startsWith('/admin/') && <Button type="link" href={item.source_url}>Mở giao dịch nguồn</Button>}</div> }))} /> : <Empty description="Chưa có hoạt động trong phạm vi này." />}</> },
        { key: 'relationships', label: 'Quan hệ', children: <>{can('customers.update') && <Button style={{ marginBottom: 16 }} onClick={() => start('relationship')}>Thêm quan hệ</Button>}<Table scroll={{ x: 620 }} rowKey={(item) => customerId(item) || item.target_profile_id} pagination={false} dataSource={customerRows(profile.relationships)} columns={[{ title: 'Quan hệ', render: (_, item) => item.role ?? item.type ?? item.relationship_type }, { title: 'Hồ sơ liên quan', render: (_, item) => item.name ?? item.target?.name ?? item.related_profile?.name ?? item.target_name ?? item.related_profile_id }, { title: 'Từ ngày', dataIndex: 'starts_at' }, { title: 'Đến ngày', dataIndex: 'ends_at' }]} locale={{ emptyText: 'Chưa có quan hệ.' }} /></> },
        { key: 'sources', label: 'Ứng dụng liên kết', children: <Table scroll={{ x: 620 }} rowKey={(item) => customerId(item) || `${item.module_key}:${item.source_id}`} dataSource={customerRows(profile.sources)} pagination={false} columns={[{ title: 'Ứng dụng', dataIndex: 'module_key' }, { title: 'Định danh nguồn', render: (_, item) => item.source_id ?? item.legacy_id ?? item.external_id ?? '—' }, { title: 'Phạm vi', render: (_, item) => item.scope_key ?? item.scope_type ?? '—' }, { title: 'Liên kết lúc', dataIndex: 'created_at', render: customerDate }]} locale={{ emptyText: 'Chưa có ứng dụng nguồn liên kết.' }} /> },
        { key: 'links', label: 'Tài khoản & kế toán', children: <CustomerLinksPanel key={customerId(profile)} api={api} bookId={bookId} profile={profile} can={can} nativePermissions={nativePermissions} organizationOptions={organizationOptions} /> },
    ] : [];
    const styledTabs = tabs.map((item) => ({
        ...item,
        label: <Space size={7}>{tabDetails[item.key].icon}{item.label}</Space>,
        children: <div className="customers-profile-pane"><div className="customers-profile-section-heading">{tabDetails[item.key].icon}<div><Typography.Title level={5}>{tabDetails[item.key].title}</Typography.Title><Typography.Text type="secondary">{tabDetails[item.key].description}</Typography.Text></div></div>{item.children}</div>,
    }));
    return <><Drawer rootClassName="customers-profile-drawer customers-profile-detail" open={Boolean(profileId)} width={960} title={<Space><IdcardOutlined />Hồ sơ khách hàng</Space>} onClose={onClose} extra={profile && can('customers.update') && <Button type="primary" icon={<EditOutlined />} onClick={() => onEdit(profile)}>Chỉnh sửa</Button>} footer={<div className="customers-profile-footer"><span><LockOutlined /> Hiển thị theo quyền được cấp</span><Button onClick={onClose}>Đóng hồ sơ</Button></div>}>
        {error && <Alert type="error" showIcon message={error} />}{loading && !profile ? <Skeleton active paragraph={{ rows: 8 }} /> : profile && <><div className="customers-profile-hero"><div className="customers-profile-heading"><Avatar size={72} shape={profile.type === 'company' ? 'square' : 'circle'}>{profile.name?.slice(0, 1)}</Avatar><div><span className="customers-profile-kicker">{profile.type === 'company' ? 'KHÁCH HÀNG DOANH NGHIỆP' : 'KHÁCH HÀNG CÁ NHÂN'}</span><Typography.Title level={3}>{profile.name}</Typography.Title><Space wrap><Typography.Text className="customers-profile-code">{profile.code ?? customerId(profile).slice(-8)}</Typography.Text><Tag color={profile.status === 'active' ? 'green' : 'default'}>{profile.status === 'active' ? 'Đang hoạt động' : profile.status === 'merged' ? 'Đã hợp nhất' : 'Đã lưu trữ'}</Tag></Space></div></div><div className="customers-profile-quick-contacts"><span><PhoneOutlined />{profile.phone || 'Chưa có điện thoại'}</span><span><MailOutlined />{profile.email || 'Chưa có email'}</span></div></div><Tabs className="customers-profile-tabs" items={styledTabs} destroyOnHidden /></>}
    </Drawer><Modal rootClassName="customers-profile-modal" open={Boolean(action)} title={<Space>{action === 'consent' ? <SafetyCertificateOutlined /> : <ApartmentOutlined />}{action === 'consent' ? 'Ghi nhận đồng ý liên hệ' : 'Thêm quan hệ'}</Space>} onCancel={() => !busy && setAction(null)} onOk={() => form.submit()} confirmLoading={busy} okText="Lưu" cancelText="Hủy" destroyOnHidden maskClosable={false}>
        {actionError && <Alert type="error" showIcon message={actionError} style={{ marginBottom: 16 }} />}<Form form={form} layout="vertical" onFinish={submit} disabled={busy}>{action === 'consent' ? <><Form.Item name="purpose" label="Mục đích" rules={[{ required: true }]}><Select options={[{ value: 'marketing', label: 'Nhận thông tin / ưu đãi' }, { value: 'service', label: 'Liên hệ phục vụ giao dịch' }]} /></Form.Item><Form.Item name="channel" label="Kênh" rules={[{ required: true }]}><Select options={[{ value: 'email', label: 'Email' }, { value: 'sms', label: 'SMS' }, { value: 'phone', label: 'Điện thoại' }]} /></Form.Item><Form.Item name="state" label="Lựa chọn" rules={[{ required: true }]}><Select options={[{ value: 'granted', label: 'Đồng ý' }, { value: 'withdrawn', label: 'Rút lại đồng ý' }]} /></Form.Item><Form.Item name="brand" label="Thương hiệu / đơn vị" rules={[{ required: true }]}><Input placeholder="Tên thương hiệu đang ghi nhận lựa chọn" maxLength={100} /></Form.Item><Form.Item noStyle shouldUpdate>{({ getFieldValue }) => <Form.Item name="policy_version" label="Phiên bản chính sách" rules={getFieldValue('state') === 'granted' ? [{ required: true, message: 'Nhập phiên bản chính sách khách đã đồng ý.' }] : []}><Input maxLength={100} placeholder="Ví dụ: privacy-2026-01" /></Form.Item>}</Form.Item><Form.Item name="evidence" label="Bằng chứng ghi nhận"><Input.TextArea rows={2} maxLength={2000} /></Form.Item></> : <><Form.Item name="related_profile_id" label="Hồ sơ liên quan" rules={[{ required: true }]}><CustomerPicker loadOptions={async (query) => customerRows((await api.read(`books/${bookId}/profiles`, { q: query, status: 'active', per_page: 20 })).data).filter((item) => customerId(item) !== profileId).map(customerOption)} /></Form.Item><Form.Item name="role" label="Loại quan hệ" rules={[{ required: true }]}><Select options={[{ value: 'employee', label: 'Nhân viên / công ty' }, { value: 'household', label: 'Thành viên gia đình' }, { value: 'contact', label: 'Người liên hệ' }]} /></Form.Item><Form.Item name="starts_at" label="Có hiệu lực từ"><Input type="date" /></Form.Item><Form.Item name="ends_at" label="Có hiệu lực đến"><Input type="date" /></Form.Item></>}</Form>
    </Modal></>;
}
