import PlusOutlined from '@ant-design/icons/PlusOutlined';
import UserAddOutlined from '@ant-design/icons/UserAddOutlined';
import CalendarOutlined from '@ant-design/icons/CalendarOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import EnvironmentOutlined from '@ant-design/icons/EnvironmentOutlined';
import FileTextOutlined from '@ant-design/icons/FileTextOutlined';
import MailOutlined from '@ant-design/icons/MailOutlined';
import MoreOutlined from '@ant-design/icons/MoreOutlined';
import PhoneOutlined from '@ant-design/icons/PhoneOutlined';
import StopOutlined from '@ant-design/icons/StopOutlined';
import UserOutlined from '@ant-design/icons/UserOutlined';
import Alert from 'antd/es/alert';
import Avatar from 'antd/es/avatar';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import DatePicker from 'antd/es/date-picker';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Dropdown from 'antd/es/dropdown';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Radio from 'antd/es/radio';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import TimePicker from 'antd/es/time-picker';
import Typography from 'antd/es/typography';
import { useCallback, useEffect, useMemo, useState } from 'react';

const { Title, Text } = Typography;
const statusColors = { active: 'green', probation: 'gold', leave: 'blue', suspended: 'red', terminated: 'default', pending: 'gold', approved: 'green', rejected: 'red' };
const statusLabels = { active: 'Đang làm việc', probation: 'Thử việc', leave: 'Tạm nghỉ', suspended: 'Tạm khóa', terminated: 'Đã nghỉ', pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối' };
const genderLabels = { male: 'Nam', female: 'Nữ', other: 'Khác' };
const contractTypeLabels = { probation: 'Thử việc', fixed_term: 'Có thời hạn', indefinite: 'Không thời hạn', seasonal: 'Thời vụ', service: 'Dịch vụ' };
const contractStatusLabels = { draft: 'Nháp', active: 'Đang hiệu lực', expired: 'Hết hạn', terminated: 'Đã chấm dứt' };

const formatProfileDate = (value) => {
    if (! value) return '—';
    const normalizedValue = typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value;
    const date = new Date(normalizedValue);

    return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat('vi-VN').format(date);
};
const employeeInitials = (name = '') => name.trim().split(/\s+/).slice(-2).map((part) => part[0]).join('').toUpperCase() || 'NS';

function ProfileInfoLine({ icon, label, value, href }) {
    const content = href && value ? <a href={href}>{value}</a> : (value || 'Chưa cập nhật');
    return <div style={{ display: 'grid', gridTemplateColumns: '38px minmax(0, 1fr)', gap: 12, alignItems: 'center', padding: '12px 0', borderBottom: '1px solid #edf1f0' }}>
        <span style={{ width: 38, height: 38, display: 'grid', placeItems: 'center', borderRadius: 12, color: '#087f6b', background: '#e9f7f3', fontSize: 17 }}>{icon}</span>
        <span style={{ minWidth: 0 }}><Text type="secondary" style={{ display: 'block', fontSize: 12 }}>{label}</Text><Text strong style={{ overflowWrap: 'anywhere' }}>{content}</Text></span>
    </div>;
}

export function EmployeeProfileDrawer({ employee, open, onClose, onEdit, canEdit, canViewContracts, contracts, contractsLoading }) {
    if (! employee) return null;
    const contractColumns = [
        { title: 'Số hợp đồng', dataIndex: 'contract_number', render: (value) => <Text strong>{value}</Text> },
        { title: 'Loại', dataIndex: 'contract_type', render: (value) => contractTypeLabels[value] || value },
        { title: 'Thời hạn', render: (_, record) => <Text>{formatProfileDate(record.start_date)} – {record.end_date ? formatProfileDate(record.end_date) : 'Không thời hạn'}</Text> },
        { title: 'Trạng thái', dataIndex: 'status', render: (value) => <Tag color={value === 'active' ? 'green' : value === 'expired' || value === 'terminated' ? 'default' : 'gold'}>{contractStatusLabels[value] || value}</Tag> },
    ];

    return <Drawer
        title="Hồ sơ nhân viên"
        open={open}
        width="min(980px, 96vw)"
        onClose={onClose}
        styles={{ body: { padding: 0, background: '#f4f7f6' }, header: { borderBottom: 0 } }}
        extra={canEdit && <Button type="primary" icon={<EditOutlined />} onClick={onEdit}>Chỉnh sửa hồ sơ</Button>}
    >
        <section style={{ padding: '30px clamp(20px, 4vw, 42px)', color: '#fff', background: 'linear-gradient(135deg, #0c3e5a 0%, #087f6b 100%)', position: 'relative', overflow: 'hidden' }}>
            <span aria-hidden style={{ position: 'absolute', width: 240, height: 240, borderRadius: '50%', right: -70, top: -120, border: '42px solid rgba(255,255,255,.08)' }} />
            <Space size={20} align="center" wrap>
                <Avatar size={92} style={{ color: '#0c665a', background: '#fff', fontSize: 30, fontWeight: 800, boxShadow: '0 14px 36px rgba(0,0,0,.2)' }}>{employeeInitials(employee.full_name)}</Avatar>
                <div>
                    <Space size={10} wrap><Tag color={statusColors[employee.employment_status]}>{statusLabels[employee.employment_status] || employee.employment_status}</Tag><Text style={{ color: 'rgba(255,255,255,.78)' }}>{employee.employee_code}</Text></Space>
                    <Title level={2} style={{ color: '#fff', margin: '8px 0 4px' }}>{employee.full_name}</Title>
                    <Text style={{ color: 'rgba(255,255,255,.82)', fontSize: 15 }}>{[employee.position?.name, employee.department?.name].filter(Boolean).join(' · ') || 'Chưa cập nhật vị trí công việc'}</Text>
                </div>
            </Space>
        </section>

        <div style={{ padding: '24px clamp(16px, 3vw, 30px) 34px' }}>
            <Row gutter={[20, 20]}>
                <Col xs={24} lg={16}>
                    <Space direction="vertical" size={20} style={{ width: '100%' }}>
                        <Card title="Thông tin công việc" bordered={false}>
                            <Descriptions column={{ xs: 1, sm: 2 }} layout="vertical" items={[
                                { key: 'department', label: 'Phòng ban', children: employee.department?.name || 'Chưa phân phòng ban' },
                                { key: 'position', label: 'Chức vụ', children: employee.position?.name || 'Chưa phân chức vụ' },
                                { key: 'manager', label: 'Quản lý trực tiếp', children: employee.manager?.full_name || 'Chưa thiết lập' },
                                { key: 'location', label: 'Nơi làm việc', children: employee.work_location || 'Chưa cập nhật' },
                                { key: 'join', label: 'Ngày vào làm', children: formatProfileDate(employee.join_date) },
                                { key: 'termination', label: 'Ngày nghỉ việc', children: formatProfileDate(employee.termination_date) },
                            ]} />
                        </Card>
                        <Card title="Hợp đồng lao động" bordered={false} loading={contractsLoading}>
                            {canViewContracts ? (contracts.length > 0 ? <Table rowKey="id" size="small" pagination={false} scroll={{ x: 620 }} dataSource={contracts} columns={contractColumns} /> : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa có hợp đồng nào" />) : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Bạn chưa được cấp quyền xem hợp đồng" />}
                        </Card>
                    </Space>
                </Col>
                <Col xs={24} lg={8}>
                    <Space direction="vertical" size={20} style={{ width: '100%' }}>
                        <Card title="Thông tin liên hệ" bordered={false}>
                            <ProfileInfoLine icon={<MailOutlined />} label="Email công việc" value={employee.work_email} href={employee.work_email ? `mailto:${employee.work_email}` : null} />
                            <ProfileInfoLine icon={<PhoneOutlined />} label="Điện thoại" value={employee.phone} href={employee.phone ? `tel:${employee.phone}` : null} />
                            <ProfileInfoLine icon={<EnvironmentOutlined />} label="Địa chỉ" value={employee.address} />
                        </Card>
                        <Card title="Thông tin cá nhân" bordered={false}>
                            <ProfileInfoLine icon={<CalendarOutlined />} label="Ngày sinh" value={employee.date_of_birth ? formatProfileDate(employee.date_of_birth) : null} />
                            <ProfileInfoLine icon={<UserOutlined />} label="Giới tính" value={genderLabels[employee.gender]} />
                            {employee.identity_number && <ProfileInfoLine icon={<UserOutlined />} label="Số giấy tờ" value={employee.identity_number} />}
                        </Card>
                        <Card title="Tài khoản hệ thống" bordered={false}>
                            {employee.admin ? <><Tag color="blue">Đã liên kết</Tag><Title level={5} style={{ margin: '12px 0 2px' }}>{employee.admin.name || employee.full_name}</Title><Text type="secondary">{employee.admin.username ? `@${employee.admin.username}` : employee.admin.email}</Text></> : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa cấp tài khoản đăng nhập" />}
                        </Card>
                        {employee.note && <Card title="Ghi chú" bordered={false}><Text style={{ whiteSpace: 'pre-wrap' }}>{employee.note}</Text></Card>}
                    </Space>
                </Col>
            </Row>
        </div>
    </Drawer>;
}

function PageHeader({ eyebrow, title, description, action }) {
    return <Card><Row justify="space-between" align="middle" gutter={[16, 16]}><Col><Text type="secondary">{eyebrow}</Text><Title level={3} style={{ margin: '4px 0' }}>{title}</Title><Text type="secondary">{description}</Text></Col><Col>{action}</Col></Row></Card>;
}

export default function HrmManagerPage({ moduleMenu, callAdminApi, runAdminAction, currentPermissions = [] }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [drawer, setDrawer] = useState(null);
    const [contracts, setContracts] = useState([]);
    const [profileContracts, setProfileContracts] = useState([]);
    const [profileContractsLoading, setProfileContractsLoading] = useState(false);
    const [contractModalOpen, setContractModalOpen] = useState(false);
    const [form] = Form.useForm();
    const [contractForm] = Form.useForm();
    const leaveStartDate = Form.useWatch('start_date', form);
    const leaveEndDate = Form.useWatch('end_date', form);
    const attendanceCheckIn = Form.useWatch('check_in_at', form);
    const attendanceCheckOut = Form.useWatch('check_out_at', form);
    const attendanceStatus = Form.useWatch('status', form);
    const menuKey = moduleMenu?.key ?? 'hrm-dashboard';
    const can = useCallback((permission) => currentPermissions.includes(permission), [currentPermissions]);
    const endpoint = useMemo(() => ({
        'hrm-dashboard': '/admin/api/hrm/dashboard',
        'hrm-employees': '/admin/api/hrm/employees',
        'hrm-organization': '/admin/api/hrm/organization',
        'hrm-leave': '/admin/api/hrm/leave',
        'hrm-attendance': '/admin/api/hrm/attendance',
        'hrm-my-profile': '/admin/api/hrm/me',
    })[menuKey], [menuKey]);
    const load = useCallback(async () => {
        if (! endpoint) return;
        setLoading(true); setError('');
        try { const response = await callAdminApi(endpoint); setData(response.data); }
        catch (exception) { setError(exception.message || 'Không thể tải dữ liệu nhân sự.'); }
        finally { setLoading(false); }
    }, [callAdminApi, endpoint]);
    useEffect(() => { load(); }, [load]);
    useEffect(() => {
        if (drawer?.type !== 'leave') return;
        if (! leaveStartDate || ! leaveEndDate) {
            form.setFieldValue('days', undefined);
            return;
        }

        const difference = leaveEndDate.startOf('day').diff(leaveStartDate.startOf('day'), 'day');
        form.setFieldValue('days', difference >= 0 ? difference + 1 : undefined);
    }, [drawer?.type, form, leaveEndDate, leaveStartDate]);
    useEffect(() => {
        if (drawer?.type !== 'attendance') return;
        if (attendanceCheckIn && attendanceCheckOut) {
            const minutes = attendanceCheckOut.diff(attendanceCheckIn, 'minute');
            form.setFieldValue('worked_hours', minutes > 0 ? Math.round((minutes / 60) * 100) / 100 : undefined);
            return;
        }

        form.setFieldValue('worked_hours', ['leave', 'absent', 'holiday'].includes(attendanceStatus) ? 0 : undefined);
    }, [attendanceCheckIn, attendanceCheckOut, attendanceStatus, drawer?.type, form]);

    const openDrawer = (type, record = null) => {
        setDrawer({ type, record });
        form.resetFields();
        if (record) form.setFieldsValue(record);
        else if (type === 'employee') form.setFieldsValue({ employment_status: 'active' });
        else if (type === 'leave') form.setFieldsValue({ leave_type: 'annual' });
        else if (type === 'attendance') form.setFieldsValue({ status: 'present' });
        else if (type === 'department' || type === 'position') form.setFieldsValue({ is_active: true });
    };
    const openContracts = async (employee) => {
        setDrawer({ type: 'contracts', record: employee });
        setContractModalOpen(false);
        const response = await callAdminApi(`/admin/api/hrm/employees/${employee.id}/contracts`);
        setContracts(response.data ?? []);
    };
    const openContractModal = () => {
        contractForm.resetFields();
        contractForm.setFieldsValue({ status: 'draft' });
        setContractModalOpen(true);
    };
    const saveContract = async () => {
        const values = await contractForm.validateFields();
        const payload = {
            ...values,
            start_date: values.start_date?.format ? values.start_date.format('YYYY-MM-DD') : values.start_date,
            end_date: values.end_date?.format ? values.end_date.format('YYYY-MM-DD') : values.end_date,
        };
        await runAdminAction(
            () => callAdminApi(`/admin/api/hrm/employees/${drawer.record.id}/contracts`, { method: 'POST', body: JSON.stringify(payload) }),
            'Đã tạo hợp đồng.',
            async () => {
                const response = await callAdminApi(`/admin/api/hrm/employees/${drawer.record.id}/contracts`);
                setContracts(response.data ?? []);
                setContractModalOpen(false);
                contractForm.resetFields();
            },
        );
    };
    const employeeActionItems = (employee) => {
        const items = [];
        if (can('hrm.employee.update')) items.push({ key: 'edit', icon: <EditOutlined />, label: 'Chỉnh sửa hồ sơ' });
        if (can('hrm.contract.view')) items.push({ key: 'contracts', icon: <FileTextOutlined />, label: 'Quản lý hợp đồng' });
        if (can('hrm.employee.account.assign') && ! employee.admin) items.push({ key: 'account', icon: <UserAddOutlined />, label: 'Cấp tài khoản đăng nhập' });
        if (can('hrm.employee.archive') && employee.employment_status !== 'terminated') {
            if (items.length) items.push({ type: 'divider' });
            items.push({ key: 'archive', icon: <StopOutlined />, label: 'Kết thúc làm việc', danger: true });
        }

        return items;
    };
    const handleEmployeeAction = ({ key }, employee) => {
        if (key === 'edit') openDrawer('employee', employee);
        if (key === 'contracts') openContracts(employee);
        if (key === 'account') openDrawer('account', employee);
        if (key === 'archive') {
            Modal.confirm({
                title: 'Xác nhận nhân sự nghỉ việc?',
                content: 'Hồ sơ sẽ chuyển sang trạng thái đã nghỉ và tài khoản đăng nhập liên kết sẽ bị khóa.',
                okText: 'Xác nhận nghỉ việc',
                cancelText: 'Hủy',
                okButtonProps: { danger: true },
                onOk: () => runAdminAction(() => callAdminApi(`/admin/api/hrm/employees/${employee.id}/archive`, { method: 'POST' }), 'Đã lưu thay đổi.', load),
            });
        }
    };
    const openEmployeeProfile = async (employee) => {
        setDrawer({ type: 'employee-profile', record: employee });
        setProfileContracts([]);
        if (! can('hrm.contract.view')) return;
        setProfileContractsLoading(true);
        try {
            const response = await callAdminApi(`/admin/api/hrm/employees/${employee.id}/contracts`);
            setProfileContracts(response.data ?? []);
        } finally {
            setProfileContractsLoading(false);
        }
    };
    const save = async () => {
        const values = await form.validateFields();
        const record = drawer.record;
        let url; let method = record ? 'PUT' : 'POST';
        if (drawer.type === 'employee') url = record ? `/admin/api/hrm/employees/${record.id}` : '/admin/api/hrm/employees';
        if (drawer.type === 'department' || drawer.type === 'position') {
            const type = `${drawer.type}s`; url = record ? `/admin/api/hrm/organization/${type}/${record.id}` : `/admin/api/hrm/organization/${type}`;
        }
        if (drawer.type === 'leave') {
            url = '/admin/api/hrm/leave';
            values.start_date = values.start_date?.format ? values.start_date.format('YYYY-MM-DD') : values.start_date;
            values.end_date = values.end_date?.format ? values.end_date.format('YYYY-MM-DD') : values.end_date;
        }
        if (drawer.type === 'attendance') {
            url = '/admin/api/hrm/attendance';
            values.work_date = values.work_date?.format ? values.work_date.format('YYYY-MM-DD') : values.work_date;
            values.check_in_at = values.check_in_at?.format ? values.check_in_at.format('HH:mm') : values.check_in_at;
            values.check_out_at = values.check_out_at?.format ? values.check_out_at.format('HH:mm') : values.check_out_at;
        }
        if (drawer.type === 'contracts') url = `/admin/api/hrm/employees/${drawer.record.id}/contracts`;
        if (drawer.type === 'profile') url = '/admin/api/hrm/me';
        await runAdminAction(() => callAdminApi(url, { method, body: JSON.stringify(values) }), 'Đã lưu thông tin.', async () => { setDrawer(null); await load(); });
    };

    if (error) return <Alert type="error" showIcon message={error} action={<Button onClick={load}>Thử lại</Button>} />;
    if (loading || ! data) return <Card loading />;

    let content = null;
    if (menuKey === 'hrm-dashboard') {
        content = <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <PageHeader eyebrow="QUẢN LÝ NHÂN SỰ" title="Tổng quan nhân sự" description="Theo dõi nhanh tình hình nhân sự và các việc đang chờ xử lý." />
            <Row gutter={[16, 16]}>
                <Col xs={24} md={6}><Card><Statistic title="Tổng nhân sự" value={data.total_employees ?? 0} /></Card></Col>
                <Col xs={24} md={6}><Card><Statistic title="Đang làm việc" value={data.active_employees ?? 0} valueStyle={{ color: '#07836f' }} /></Card></Col>
                <Col xs={24} md={6}><Card><Statistic title="Đang thử việc" value={data.probation_employees ?? 0} /></Card></Col>
                <Col xs={24} md={6}><Card><Statistic title="Đơn chờ duyệt" value={data.pending_leave_requests ?? 0} valueStyle={{ color: '#d48806' }} /></Card></Col>
            </Row>
            <Card title="Nhân sự mới"><Table rowKey="id" pagination={false} dataSource={data.recent_employees ?? []} columns={[{ title: 'Mã', dataIndex: 'employee_code' }, { title: 'Họ và tên', dataIndex: 'full_name' }, { title: 'Phòng ban', render: (_, r) => r.department?.name || '—' }, { title: 'Trạng thái', render: (_, r) => <Tag color={statusColors[r.employment_status]}>{statusLabels[r.employment_status] || r.employment_status}</Tag> }]} /></Card>
        </Space>;
    }
    if (menuKey === 'hrm-employees') {
        const refs = data.references ?? {};
        content = <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <PageHeader eyebrow="HỒ SƠ NHÂN SỰ" title="Danh sách nhân sự" description="Quản lý hồ sơ công việc và liên kết tài khoản đăng nhập." action={can('hrm.employee.create') && <Button type="primary" icon={<PlusOutlined />} onClick={() => openDrawer('employee')}>Thêm nhân sự</Button>} />
            <Card><Table rowKey="id" dataSource={data.items ?? []} scroll={{ x: 900 }} columns={[
                { title: 'Nhân sự', render: (_, r) => <Button type="link" aria-label={`Xem hồ sơ ${r.full_name}`} onClick={() => openEmployeeProfile(r)} style={{ height: 'auto', padding: 0, textAlign: 'left' }}><Space direction="vertical" size={0} align="start"><Text strong style={{ color: '#123d55' }}>{r.full_name}</Text><Text type="secondary">{r.employee_code}</Text></Space></Button> },
                { title: 'Phòng ban', render: (_, r) => r.department?.name || '—' }, { title: 'Chức vụ', render: (_, r) => r.position?.name || '—' },
                { title: 'Liên hệ', render: (_, r) => r.work_email || r.phone || '—' }, { title: 'Trạng thái', render: (_, r) => <Tag color={statusColors[r.employment_status]}>{statusLabels[r.employment_status] || r.employment_status}</Tag> },
                { title: 'Tài khoản', render: (_, r) => r.admin ? <Tag color="blue">{r.admin.username || r.admin.email}</Tag> : <Text type="secondary">Chưa cấp</Text> },
                { title: 'Tác vụ', fixed: 'right', width: 130, render: (_, r) => employeeActionItems(r).length ? <Dropdown trigger={['click']} placement="bottomRight" overlayStyle={{ minWidth: 220 }} menu={{ items: employeeActionItems(r), onClick: (event) => handleEmployeeAction(event, r) }}><Button size="small" icon={<MoreOutlined />} style={{ borderRadius: 999, fontWeight: 650, paddingInline: 14 }}>Tác vụ</Button></Dropdown> : <Text type="secondary">—</Text> },
            ]} /></Card>
            <EmployeeProfileDrawer
                employee={drawer?.type === 'employee-profile' ? drawer.record : null}
                open={drawer?.type === 'employee-profile'}
                onClose={() => setDrawer(null)}
                canEdit={can('hrm.employee.update')}
                onEdit={() => openDrawer('employee', drawer.record)}
                canViewContracts={can('hrm.contract.view')}
                contracts={profileContracts}
                contractsLoading={profileContractsLoading}
            />
            <Drawer title={drawer?.record ? 'Cập nhật hồ sơ nhân sự' : 'Thêm nhân sự'} open={drawer?.type === 'employee'} width="min(720px, 92vw)" onClose={() => setDrawer(null)} extra={<Button type="primary" onClick={save}>Lưu</Button>}>
                <Form form={form} layout="vertical"><Row gutter={16}><Col span={12}><Form.Item name="employee_code" label="Mã nhân sự" rules={[{ required: true }]}><Input /></Form.Item></Col><Col span={12}><Form.Item name="full_name" label="Họ và tên" rules={[{ required: true }]}><Input /></Form.Item></Col><Col span={12}><Form.Item name="department_id" label="Phòng ban"><Select allowClear options={(refs.departments ?? []).map(x => ({ value: x.id, label: x.name }))} /></Form.Item></Col><Col span={12}><Form.Item name="position_id" label="Chức vụ"><Select allowClear options={(refs.positions ?? []).map(x => ({ value: x.id, label: x.name }))} /></Form.Item></Col><Col span={12}><Form.Item name="manager_employee_id" label="Quản lý trực tiếp"><Select allowClear options={(refs.managers ?? []).filter(x => x.id !== drawer?.record?.id).map(x => ({ value: x.id, label: `${x.employee_code} · ${x.full_name}` }))} /></Form.Item></Col><Col span={12}><Form.Item name="employment_status" label="Trạng thái" rules={[{ required: true }]}><Select options={Object.entries(statusLabels).filter(([k]) => !['pending','approved','rejected'].includes(k)).map(([value,label]) => ({ value,label }))} /></Form.Item></Col><Col span={12}><Form.Item name="work_email" label="Email công việc"><Input /></Form.Item></Col><Col span={12}><Form.Item name="phone" label="Điện thoại"><Input /></Form.Item></Col><Col span={24}><Form.Item name="work_location" label="Nơi làm việc"><Input /></Form.Item></Col><Col span={24}><Form.Item name="note" label="Ghi chú"><Input.TextArea rows={3} /></Form.Item></Col></Row></Form>
            </Drawer>
            <Drawer title={`Cấp tài khoản · ${drawer?.record?.full_name ?? ''}`} open={drawer?.type === 'account'} width="min(560px, 92vw)" onClose={() => setDrawer(null)} extra={<Button type="primary" onClick={async () => { const values = await form.validateFields(); await runAdminAction(() => callAdminApi(`/admin/api/hrm/employees/${drawer.record.id}/account`, { method: 'POST', body: JSON.stringify(values) }), 'Đã cấp tài khoản.', async () => { setDrawer(null); await load(); }); }}>Cấp tài khoản</Button>}>
                <Alert showIcon type="info" message="Tài khoản sẽ được gán quyền xem hồ sơ cá nhân. Người dùng phải đổi mật khẩu ở lần đăng nhập đầu tiên." style={{ marginBottom: 16 }} />
                <Form form={form} layout="vertical"><Form.Item name="admin_id" label="Dùng tài khoản quản trị hiện có"><Select allowClear placeholder="Hoặc tạo tài khoản mới bên dưới" options={(refs.available_admins ?? []).map(x => ({ value: x.id, label: `${x.name} · ${x.username || x.email}` }))} /></Form.Item><Form.Item name="name" label="Tên hiển thị"><Input /></Form.Item><Form.Item name="username" label="Tên đăng nhập"><Input /></Form.Item><Form.Item name="email" label="Email"><Input /></Form.Item><Form.Item name="password" label="Mật khẩu tạm"><Input.Password /></Form.Item><Form.Item name="password_confirmation" label="Nhập lại mật khẩu"><Input.Password /></Form.Item></Form>
            </Drawer>
            <Drawer title={`Hợp đồng · ${drawer?.record?.full_name ?? ''}`} open={drawer?.type === 'contracts'} width="min(760px, 92vw)" onClose={() => { setContractModalOpen(false); setDrawer(null); }} extra={can('hrm.contract.manage') && <Button type="primary" icon={<PlusOutlined />} onClick={openContractModal}>Thêm hợp đồng</Button>}>
                <Table rowKey="id" size="small" pagination={false} dataSource={contracts} columns={[{title:'Số hợp đồng',dataIndex:'contract_number'},{title:'Loại',dataIndex:'contract_type'},{title:'Bắt đầu',dataIndex:'start_date'},{title:'Kết thúc',dataIndex:'end_date'},{title:'Lương cơ bản',render:(_,r)=>new Intl.NumberFormat('vi-VN').format(Number(r.base_salary||0))},{title:'Trạng thái',dataIndex:'status'}]} />
            </Drawer>
            <Modal title={`Thêm hợp đồng · ${drawer?.record?.full_name ?? ''}`} open={contractModalOpen && drawer?.type === 'contracts'} width={720} okText="Tạo hợp đồng" cancelText="Hủy" onOk={saveContract} onCancel={() => setContractModalOpen(false)} destroyOnHidden>
                <Form form={contractForm} layout="vertical" style={{ marginTop: 20 }}><Row gutter={16}><Col xs={24} md={12}><Form.Item name="contract_number" label="Số hợp đồng" extra="Để trống để hệ thống tự sinh."><Input placeholder="Tự động nếu để trống"/></Form.Item></Col><Col xs={24} md={12}><Form.Item name="contract_type" label="Loại hợp đồng" rules={[{required:true}]}><Select options={[['probation','Thử việc'],['fixed_term','Có thời hạn'],['indefinite','Không thời hạn'],['seasonal','Thời vụ'],['service','Dịch vụ']].map(([value,label])=>({value,label}))}/></Form.Item></Col><Col xs={24} md={12}><Form.Item name="start_date" label="Ngày bắt đầu" rules={[{required:true}]}><DatePicker format="DD/MM/YYYY" placeholder="Chọn ngày bắt đầu" style={{width:'100%'}}/></Form.Item></Col><Col xs={24} md={12}><Form.Item name="end_date" label="Ngày kết thúc"><DatePicker format="DD/MM/YYYY" placeholder="Chọn ngày kết thúc" style={{width:'100%'}}/></Form.Item></Col><Col xs={24} md={12}><Form.Item name="base_salary" label="Lương cơ bản" rules={[{required:true}]}><InputNumber min={0} precision={0} controls={false} addonAfter="đ" formatter={(value) => value === undefined || value === null || value === '' ? '' : String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.')} parser={(value) => value ? value.replace(/\./g, '') : ''} style={{width:'100%'}}/></Form.Item></Col><Col span={24}><Form.Item name="status" label="Trạng thái" rules={[{required:true}]}><Radio.Group optionType="button" buttonStyle="solid" options={[{value:'draft',label:'Nháp'},{value:'active',label:'Đang hiệu lực'},{value:'expired',label:'Hết hạn'},{value:'terminated',label:'Đã chấm dứt'}]}/></Form.Item></Col></Row></Form>
            </Modal>
        </Space>;
    }
    if (menuKey === 'hrm-organization') {
        const columns = (type) => [{ title: 'Mã', dataIndex: 'code' }, { title: 'Tên', dataIndex: 'name' }, { title: 'Số nhân sự', dataIndex: 'employees_count' }, { title: 'Trạng thái', render: (_, r) => <Tag color={r.is_active ? 'green' : 'default'}>{r.is_active ? 'Đang dùng' : 'Tạm ngưng'}</Tag> }, { title: 'Tác vụ', render: (_, r) => <Button size="small" onClick={() => openDrawer(type, r)}>Sửa</Button> }];
        content = <Space direction="vertical" size={16} style={{ width: '100%' }}><PageHeader eyebrow="TỔ CHỨC" title="Cơ cấu tổ chức" description="Quản lý phòng ban và chức vụ trong doanh nghiệp." /><Row gutter={[16,16]}><Col xs={24} xl={12}><Card title="Phòng ban" extra={<Button icon={<PlusOutlined />} onClick={() => openDrawer('department')}>Thêm</Button>}><Table rowKey="id" pagination={false} dataSource={data.departments ?? []} columns={columns('department')} /></Card></Col><Col xs={24} xl={12}><Card title="Chức vụ" extra={<Button icon={<PlusOutlined />} onClick={() => openDrawer('position')}>Thêm</Button>}><Table rowKey="id" pagination={false} dataSource={data.positions ?? []} columns={columns('position')} /></Card></Col></Row><Modal title={drawer?.record ? (drawer?.type === 'department' ? 'Cập nhật phòng ban' : 'Cập nhật chức vụ') : (drawer?.type === 'department' ? 'Thêm phòng ban' : 'Thêm chức vụ')} open={['department','position'].includes(drawer?.type)} width={560} okText="Lưu thông tin" cancelText="Hủy" onOk={save} onCancel={() => setDrawer(null)} destroyOnHidden><Form form={form} layout="vertical" style={{ marginTop: 20 }}><Form.Item name="name" label="Tên" rules={[{ required: true }]}><Input placeholder={drawer?.type === 'department' ? 'Nhập tên phòng ban' : 'Nhập tên chức vụ'} /></Form.Item>{drawer?.type === 'department' && <Form.Item name="parent_id" label="Phòng ban cấp trên"><Select allowClear placeholder="Không có phòng ban cấp trên" options={(data.departments ?? []).filter(x => x.id !== drawer?.record?.id).map(x => ({ value:x.id,label:x.name }))} /></Form.Item>}<Form.Item name="description" label="Mô tả"><Input.TextArea rows={4} placeholder="Mô tả ngắn gọn vai trò, nhiệm vụ" /></Form.Item><Form.Item name="is_active" label="Trạng thái" rules={[{ required:true }]}><Select options={[{value:true,label:'Đang sử dụng'},{value:false,label:'Tạm ngưng'}]} /></Form.Item></Form></Modal></Space>;
    }
    if (menuKey === 'hrm-leave') {
        content = <Space direction="vertical" size={16} style={{ width: '100%' }}><PageHeader eyebrow="NGHỈ PHÉP" title="Đơn nghỉ phép" description="Gửi và theo dõi trạng thái đơn nghỉ phép." action={<Button type="primary" icon={<PlusOutlined />} onClick={() => openDrawer('leave')}>Tạo đơn</Button>} /><Card><Table rowKey="id" dataSource={data.items ?? []} columns={[{ title:'Nhân sự',render:(_,r)=>r.employee?.full_name },{title:'Loại nghỉ',dataIndex:'leave_type'},{title:'Từ ngày',dataIndex:'start_date'},{title:'Đến ngày',dataIndex:'end_date'},{title:'Số ngày',dataIndex:'days'},{title:'Trạng thái',render:(_,r)=><Tag color={statusColors[r.status]}>{statusLabels[r.status] || r.status}</Tag>},{title:'Duyệt',render:(_,r)=>can('hrm.leave.approve')&&r.status==='pending'?<Space><Button size="small" type="primary" onClick={()=>runAdminAction(()=>callAdminApi(`/admin/api/hrm/leave/${r.id}/review`,{method:'PUT',body:JSON.stringify({status:'approved'})}),'Đã duyệt đơn.',load)}>Duyệt</Button><Button size="small" danger onClick={()=>runAdminAction(()=>callAdminApi(`/admin/api/hrm/leave/${r.id}/review`,{method:'PUT',body:JSON.stringify({status:'rejected'})}),'Đã từ chối đơn.',load)}>Từ chối</Button></Space>:'—'}]} /></Card><Modal title="Tạo đơn nghỉ phép" open={drawer?.type==='leave'} width={640} okText="Gửi đơn" cancelText="Hủy" onOk={save} onCancel={()=>setDrawer(null)} destroyOnHidden><Form form={form} layout="vertical" style={{marginTop:20}}>{(data.employees??[]).length>0&&<Form.Item name="employee_id" label="Nhân sự"><Select allowClear placeholder="Chọn nhân sự" options={data.employees.map(x=>({value:x.id,label:`${x.employee_code} · ${x.full_name}`}))}/></Form.Item>}<Form.Item name="leave_type" label="Loại nghỉ" rules={[{required:true}]}><Select options={[['annual','Phép năm'],['sick','Nghỉ ốm'],['unpaid','Không lương'],['maternity','Thai sản'],['paternity','Chế độ cha'],['other','Khác']].map(([value,label])=>({value,label}))}/></Form.Item><Row gutter={16}><Col xs={24} md={12}><Form.Item name="start_date" label="Từ ngày" rules={[{required:true}]}><DatePicker format="DD/MM/YYYY" placeholder="Chọn ngày bắt đầu" style={{width:'100%'}} /></Form.Item></Col><Col xs={24} md={12}><Form.Item name="end_date" label="Đến ngày" rules={[{required:true}]}><DatePicker format="DD/MM/YYYY" placeholder="Chọn ngày kết thúc" disabledDate={(current)=>Boolean(leaveStartDate && current.startOf('day').isBefore(leaveStartDate.startOf('day')))} style={{width:'100%'}} /></Form.Item></Col></Row><Form.Item name="days" label="Số ngày nghỉ" rules={[{required:true}]} extra="Tự động tính theo khoảng thời gian, bao gồm cả ngày bắt đầu và ngày kết thúc."><InputNumber readOnly controls={false} addonAfter="ngày" placeholder="Chọn khoảng thời gian" style={{width:'100%'}} /></Form.Item><Form.Item name="reason" label="Lý do"><Input.TextArea rows={4} placeholder="Nhập lý do nghỉ phép" /></Form.Item></Form></Modal></Space>;
    }
    if (menuKey === 'hrm-attendance') {
        const attendanceLabels = { present:'Đủ công',late:'Đi muộn',remote:'Làm từ xa',leave:'Nghỉ phép',absent:'Vắng mặt',holiday:'Ngày lễ' };
        content = <Space direction="vertical" size={16} style={{ width:'100%' }}><PageHeader eyebrow="CHẤM CÔNG" title="Theo dõi ngày công" description="Quản lý giờ vào, giờ ra và trạng thái làm việc theo ngày." action={can('hrm.attendance.manage')&&<Button type="primary" icon={<PlusOutlined/>} onClick={()=>openDrawer('attendance')}>Nhập chấm công</Button>} /><Card><Table rowKey="id" dataSource={data.items??[]} columns={[{title:'Ngày',dataIndex:'work_date'},{title:'Nhân sự',render:(_,r)=>r.employee?.full_name},{title:'Giờ vào',dataIndex:'check_in_at',render:v=>v||'—'},{title:'Giờ ra',dataIndex:'check_out_at',render:v=>v||'—'},{title:'Số giờ',dataIndex:'worked_hours'},{title:'Trạng thái',render:(_,r)=><Tag color={r.status==='present'?'green':r.status==='absent'?'red':'blue'}>{attendanceLabels[r.status]||r.status}</Tag>},{title:'Ghi chú',dataIndex:'note'}]} /></Card><Modal title="Nhập dữ liệu chấm công" open={drawer?.type==='attendance'} width={640} okText="Lưu chấm công" cancelText="Hủy" onOk={save} onCancel={()=>setDrawer(null)} destroyOnHidden><Form form={form} layout="vertical" style={{marginTop:20}}><Form.Item name="employee_id" label="Nhân sự" rules={[{required:true}]}><Select showSearch optionFilterProp="label" placeholder="Chọn nhân sự" options={(data.employees??[]).map(x=>({value:x.id,label:`${x.employee_code} · ${x.full_name}`}))}/></Form.Item><Form.Item name="work_date" label="Ngày làm việc" rules={[{required:true}]}><DatePicker format="DD/MM/YYYY" placeholder="Chọn ngày làm việc" style={{width:'100%'}}/></Form.Item><Row gutter={16}><Col xs={24} md={12}><Form.Item name="check_in_at" label="Giờ vào"><TimePicker format="HH:mm" minuteStep={5} placeholder="Chọn giờ vào" style={{width:'100%'}}/></Form.Item></Col><Col xs={24} md={12}><Form.Item name="check_out_at" label="Giờ ra"><TimePicker format="HH:mm" minuteStep={5} placeholder="Chọn giờ ra" style={{width:'100%'}}/></Form.Item></Col></Row><Form.Item name="worked_hours" label="Số giờ làm" rules={[{required:true}]} extra="Tự động tính theo giờ vào và giờ ra."><InputNumber readOnly controls={false} min={0} max={24} addonAfter="giờ" placeholder="Chọn giờ vào và giờ ra" style={{width:'100%'}}/></Form.Item><Form.Item name="status" label="Trạng thái" rules={[{required:true}]}><Select options={Object.entries(attendanceLabels).map(([value,label])=>({value,label}))}/></Form.Item><Form.Item name="note" label="Ghi chú"><Input.TextArea rows={3} placeholder="Nhập ghi chú nếu có"/></Form.Item></Form></Modal></Space>;
    }
    if (menuKey === 'hrm-my-profile') {
        content = <Space direction="vertical" size={16} style={{width:'100%'}}><PageHeader eyebrow="CỔNG THÔNG TIN CÁ NHÂN" title="Hồ sơ của tôi" description="Thông tin công việc và liên hệ của bạn." action={can('hrm.profile.self.update')&&<Button onClick={()=>openDrawer('profile',data)}>Cập nhật liên hệ</Button>} /><Card><Descriptions bordered column={{xs:1,md:2}} items={[{key:'code',label:'Mã nhân sự',children:data.employee_code},{key:'name',label:'Họ và tên',children:data.full_name},{key:'dept',label:'Phòng ban',children:data.department?.name||'—'},{key:'position',label:'Chức vụ',children:data.position?.name||'—'},{key:'manager',label:'Quản lý trực tiếp',children:data.manager?.full_name||'—'},{key:'status',label:'Trạng thái',children:<Tag color={statusColors[data.employment_status]}>{statusLabels[data.employment_status]||data.employment_status}</Tag>},{key:'email',label:'Email công việc',children:data.work_email||'—'},{key:'phone',label:'Điện thoại',children:data.phone||'—'}]} /></Card><Card title="Hợp đồng"><Table rowKey="id" pagination={false} dataSource={data.contracts??[]} columns={[{title:'Số hợp đồng',dataIndex:'contract_number'},{title:'Loại',dataIndex:'contract_type'},{title:'Bắt đầu',dataIndex:'start_date'},{title:'Kết thúc',dataIndex:'end_date'},{title:'Trạng thái',dataIndex:'status'}]} /></Card><Drawer title="Cập nhật thông tin liên hệ" open={drawer?.type==='profile'} width="min(520px,92vw)" onClose={()=>setDrawer(null)} extra={<Button type="primary" onClick={save}>Lưu</Button>}><Form form={form} layout="vertical"><Form.Item name="personal_email" label="Email cá nhân"><Input /></Form.Item><Form.Item name="phone" label="Điện thoại"><Input /></Form.Item><Form.Item name="address" label="Địa chỉ"><Input.TextArea rows={4}/></Form.Item></Form></Drawer></Space>;
    }
    return content;
}
