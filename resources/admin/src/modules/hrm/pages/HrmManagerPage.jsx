import PlusOutlined from '@ant-design/icons/PlusOutlined';
import UserAddOutlined from '@ant-design/icons/UserAddOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useCallback, useEffect, useMemo, useState } from 'react';

const { Title, Text } = Typography;
const statusColors = { active: 'green', probation: 'gold', leave: 'blue', suspended: 'red', terminated: 'default', pending: 'gold', approved: 'green', rejected: 'red' };
const statusLabels = { active: 'Đang làm việc', probation: 'Thử việc', leave: 'Tạm nghỉ', suspended: 'Tạm khóa', terminated: 'Đã nghỉ', pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối' };

function PageHeader({ eyebrow, title, description, action }) {
    return <Card><Row justify="space-between" align="middle" gutter={[16, 16]}><Col><Text type="secondary">{eyebrow}</Text><Title level={3} style={{ margin: '4px 0' }}>{title}</Title><Text type="secondary">{description}</Text></Col><Col>{action}</Col></Row></Card>;
}

export default function HrmManagerPage({ moduleMenu, callAdminApi, runAdminAction, currentPermissions = [] }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [drawer, setDrawer] = useState(null);
    const [contracts, setContracts] = useState([]);
    const [form] = Form.useForm();
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

    const openDrawer = (type, record = null) => {
        setDrawer({ type, record });
        form.resetFields();
        if (record) form.setFieldsValue(record);
        else if (type === 'employee') form.setFieldsValue({ employment_status: 'active' });
        else if (type === 'leave') form.setFieldsValue({ leave_type: 'annual', days: 1 });
        else if (type === 'attendance') form.setFieldsValue({ status: 'present', worked_hours: 8 });
        else if (type === 'department' || type === 'position') form.setFieldsValue({ is_active: true });
    };
    const openContracts = async (employee) => {
        setDrawer({ type: 'contracts', record: employee });
        form.resetFields();
        const response = await callAdminApi(`/admin/api/hrm/employees/${employee.id}/contracts`);
        setContracts(response.data ?? []);
    };
    const save = async () => {
        const values = await form.validateFields();
        const record = drawer.record;
        let url; let method = record ? 'PUT' : 'POST';
        if (drawer.type === 'employee') url = record ? `/admin/api/hrm/employees/${record.id}` : '/admin/api/hrm/employees';
        if (drawer.type === 'department' || drawer.type === 'position') {
            const type = `${drawer.type}s`; url = record ? `/admin/api/hrm/organization/${type}/${record.id}` : `/admin/api/hrm/organization/${type}`;
        }
        if (drawer.type === 'leave') url = '/admin/api/hrm/leave';
        if (drawer.type === 'attendance') url = '/admin/api/hrm/attendance';
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
                { title: 'Nhân sự', render: (_, r) => <Space direction="vertical" size={0}><Text strong>{r.full_name}</Text><Text type="secondary">{r.employee_code}</Text></Space> },
                { title: 'Phòng ban', render: (_, r) => r.department?.name || '—' }, { title: 'Chức vụ', render: (_, r) => r.position?.name || '—' },
                { title: 'Liên hệ', render: (_, r) => r.work_email || r.phone || '—' }, { title: 'Trạng thái', render: (_, r) => <Tag color={statusColors[r.employment_status]}>{statusLabels[r.employment_status] || r.employment_status}</Tag> },
                { title: 'Tài khoản', render: (_, r) => r.admin ? <Tag color="blue">{r.admin.username || r.admin.email}</Tag> : <Text type="secondary">Chưa cấp</Text> },
                { title: 'Tác vụ', fixed: 'right', render: (_, r) => <Space>{can('hrm.employee.update') && <Button size="small" onClick={() => openDrawer('employee', r)}>Sửa</Button>}{can('hrm.contract.view') && <Button size="small" onClick={() => openContracts(r)}>Hợp đồng</Button>}{can('hrm.employee.account.assign') && !r.admin && <Button size="small" icon={<UserAddOutlined />} onClick={() => openDrawer('account', r)}>Cấp tài khoản</Button>}{can('hrm.employee.archive') && r.employment_status !== 'terminated' && <Popconfirm title="Kết thúc làm việc và khóa tài khoản?" onConfirm={() => runAdminAction(() => callAdminApi(`/admin/api/hrm/employees/${r.id}/archive`, { method: 'POST' }), 'Đã lưu thay đổi.', load)}><Button size="small" danger>Nghỉ việc</Button></Popconfirm>}</Space> },
            ]} /></Card>
            <Drawer title={drawer?.record ? 'Cập nhật hồ sơ nhân sự' : 'Thêm nhân sự'} open={drawer?.type === 'employee'} width="min(720px, 92vw)" onClose={() => setDrawer(null)} extra={<Button type="primary" onClick={save}>Lưu</Button>}>
                <Form form={form} layout="vertical"><Row gutter={16}><Col span={12}><Form.Item name="employee_code" label="Mã nhân sự" rules={[{ required: true }]}><Input /></Form.Item></Col><Col span={12}><Form.Item name="full_name" label="Họ và tên" rules={[{ required: true }]}><Input /></Form.Item></Col><Col span={12}><Form.Item name="department_id" label="Phòng ban"><Select allowClear options={(refs.departments ?? []).map(x => ({ value: x.id, label: x.name }))} /></Form.Item></Col><Col span={12}><Form.Item name="position_id" label="Chức vụ"><Select allowClear options={(refs.positions ?? []).map(x => ({ value: x.id, label: x.name }))} /></Form.Item></Col><Col span={12}><Form.Item name="manager_employee_id" label="Quản lý trực tiếp"><Select allowClear options={(refs.managers ?? []).filter(x => x.id !== drawer?.record?.id).map(x => ({ value: x.id, label: `${x.employee_code} · ${x.full_name}` }))} /></Form.Item></Col><Col span={12}><Form.Item name="employment_status" label="Trạng thái" rules={[{ required: true }]}><Select options={Object.entries(statusLabels).filter(([k]) => !['pending','approved','rejected'].includes(k)).map(([value,label]) => ({ value,label }))} /></Form.Item></Col><Col span={12}><Form.Item name="work_email" label="Email công việc"><Input /></Form.Item></Col><Col span={12}><Form.Item name="phone" label="Điện thoại"><Input /></Form.Item></Col><Col span={24}><Form.Item name="work_location" label="Nơi làm việc"><Input /></Form.Item></Col><Col span={24}><Form.Item name="note" label="Ghi chú"><Input.TextArea rows={3} /></Form.Item></Col></Row></Form>
            </Drawer>
            <Drawer title={`Cấp tài khoản · ${drawer?.record?.full_name ?? ''}`} open={drawer?.type === 'account'} width="min(560px, 92vw)" onClose={() => setDrawer(null)} extra={<Button type="primary" onClick={async () => { const values = await form.validateFields(); await runAdminAction(() => callAdminApi(`/admin/api/hrm/employees/${drawer.record.id}/account`, { method: 'POST', body: JSON.stringify(values) }), 'Đã cấp tài khoản.', async () => { setDrawer(null); await load(); }); }}>Cấp tài khoản</Button>}>
                <Alert showIcon type="info" message="Tài khoản sẽ được gán quyền xem hồ sơ cá nhân. Người dùng phải đổi mật khẩu ở lần đăng nhập đầu tiên." style={{ marginBottom: 16 }} />
                <Form form={form} layout="vertical"><Form.Item name="admin_id" label="Dùng tài khoản quản trị hiện có"><Select allowClear placeholder="Hoặc tạo tài khoản mới bên dưới" options={(refs.available_admins ?? []).map(x => ({ value: x.id, label: `${x.name} · ${x.username || x.email}` }))} /></Form.Item><Form.Item name="name" label="Tên hiển thị"><Input /></Form.Item><Form.Item name="username" label="Tên đăng nhập"><Input /></Form.Item><Form.Item name="email" label="Email"><Input /></Form.Item><Form.Item name="password" label="Mật khẩu tạm"><Input.Password /></Form.Item><Form.Item name="password_confirmation" label="Nhập lại mật khẩu"><Input.Password /></Form.Item></Form>
            </Drawer>
            <Drawer title={`Hợp đồng · ${drawer?.record?.full_name ?? ''}`} open={drawer?.type === 'contracts'} width="min(760px, 92vw)" onClose={() => setDrawer(null)} extra={can('hrm.contract.manage') && <Button type="primary" onClick={async () => { const values = await form.validateFields(); await runAdminAction(() => callAdminApi(`/admin/api/hrm/employees/${drawer.record.id}/contracts`, { method:'POST', body:JSON.stringify(values) }), 'Đã tạo hợp đồng.', async () => { const response = await callAdminApi(`/admin/api/hrm/employees/${drawer.record.id}/contracts`); setContracts(response.data ?? []); form.resetFields(); }); }}>Thêm hợp đồng</Button>}>
                <Table rowKey="id" size="small" pagination={false} dataSource={contracts} columns={[{title:'Số hợp đồng',dataIndex:'contract_number'},{title:'Loại',dataIndex:'contract_type'},{title:'Bắt đầu',dataIndex:'start_date'},{title:'Kết thúc',dataIndex:'end_date'},{title:'Lương cơ bản',render:(_,r)=>new Intl.NumberFormat('vi-VN').format(Number(r.base_salary||0))},{title:'Trạng thái',dataIndex:'status'}]} />
                {can('hrm.contract.manage') && <Card title="Thông tin hợp đồng mới" size="small" style={{marginTop:16}}><Form form={form} layout="vertical"><Row gutter={16}><Col span={12}><Form.Item name="contract_number" label="Số hợp đồng" rules={[{required:true}]}><Input/></Form.Item></Col><Col span={12}><Form.Item name="contract_type" label="Loại hợp đồng" rules={[{required:true}]}><Select options={[['probation','Thử việc'],['fixed_term','Có thời hạn'],['indefinite','Không thời hạn'],['seasonal','Thời vụ'],['service','Dịch vụ']].map(([value,label])=>({value,label}))}/></Form.Item></Col><Col span={12}><Form.Item name="start_date" label="Ngày bắt đầu" rules={[{required:true}]}><Input type="date"/></Form.Item></Col><Col span={12}><Form.Item name="end_date" label="Ngày kết thúc"><Input type="date"/></Form.Item></Col><Col span={12}><Form.Item name="base_salary" label="Lương cơ bản" rules={[{required:true}]}><InputNumber min={0} style={{width:'100%'}}/></Form.Item></Col><Col span={12}><Form.Item name="status" label="Trạng thái" rules={[{required:true}]} initialValue="draft"><Select options={[{value:'draft',label:'Nháp'},{value:'active',label:'Đang hiệu lực'},{value:'expired',label:'Hết hạn'},{value:'terminated',label:'Đã chấm dứt'}]}/></Form.Item></Col></Row></Form></Card>}
            </Drawer>
        </Space>;
    }
    if (menuKey === 'hrm-organization') {
        const columns = (type) => [{ title: 'Mã', dataIndex: 'code' }, { title: 'Tên', dataIndex: 'name' }, { title: 'Số nhân sự', dataIndex: 'employees_count' }, { title: 'Trạng thái', render: (_, r) => <Tag color={r.is_active ? 'green' : 'default'}>{r.is_active ? 'Đang dùng' : 'Tạm ngưng'}</Tag> }, { title: 'Tác vụ', render: (_, r) => <Button size="small" onClick={() => openDrawer(type, r)}>Sửa</Button> }];
        content = <Space direction="vertical" size={16} style={{ width: '100%' }}><PageHeader eyebrow="TỔ CHỨC" title="Cơ cấu tổ chức" description="Quản lý phòng ban và chức vụ trong doanh nghiệp." /><Row gutter={[16,16]}><Col xs={24} xl={12}><Card title="Phòng ban" extra={<Button icon={<PlusOutlined />} onClick={() => openDrawer('department')}>Thêm</Button>}><Table rowKey="id" pagination={false} dataSource={data.departments ?? []} columns={columns('department')} /></Card></Col><Col xs={24} xl={12}><Card title="Chức vụ" extra={<Button icon={<PlusOutlined />} onClick={() => openDrawer('position')}>Thêm</Button>}><Table rowKey="id" pagination={false} dataSource={data.positions ?? []} columns={columns('position')} /></Card></Col></Row><Drawer title={drawer?.type === 'department' ? 'Thông tin phòng ban' : 'Thông tin chức vụ'} open={['department','position'].includes(drawer?.type)} width="min(520px, 92vw)" onClose={() => setDrawer(null)} extra={<Button type="primary" onClick={save}>Lưu</Button>}><Form form={form} layout="vertical"><Form.Item name="code" label="Mã" rules={[{ required: true }]}><Input /></Form.Item><Form.Item name="name" label="Tên" rules={[{ required: true }]}><Input /></Form.Item>{drawer?.type === 'department' && <Form.Item name="parent_id" label="Phòng ban cấp trên"><Select allowClear options={(data.departments ?? []).filter(x => x.id !== drawer?.record?.id).map(x => ({ value:x.id,label:x.name }))} /></Form.Item>}<Form.Item name="description" label="Mô tả"><Input.TextArea rows={4} /></Form.Item><Form.Item name="is_active" label="Trạng thái" rules={[{ required:true }]}><Select options={[{value:true,label:'Đang sử dụng'},{value:false,label:'Tạm ngưng'}]} /></Form.Item></Form></Drawer></Space>;
    }
    if (menuKey === 'hrm-leave') {
        content = <Space direction="vertical" size={16} style={{ width: '100%' }}><PageHeader eyebrow="NGHỈ PHÉP" title="Đơn nghỉ phép" description="Gửi và theo dõi trạng thái đơn nghỉ phép." action={<Button type="primary" icon={<PlusOutlined />} onClick={() => openDrawer('leave')}>Tạo đơn</Button>} /><Card><Table rowKey="id" dataSource={data.items ?? []} columns={[{ title:'Nhân sự',render:(_,r)=>r.employee?.full_name },{title:'Loại nghỉ',dataIndex:'leave_type'},{title:'Từ ngày',dataIndex:'start_date'},{title:'Đến ngày',dataIndex:'end_date'},{title:'Số ngày',dataIndex:'days'},{title:'Trạng thái',render:(_,r)=><Tag color={statusColors[r.status]}>{statusLabels[r.status] || r.status}</Tag>},{title:'Duyệt',render:(_,r)=>can('hrm.leave.approve')&&r.status==='pending'?<Space><Button size="small" type="primary" onClick={()=>runAdminAction(()=>callAdminApi(`/admin/api/hrm/leave/${r.id}/review`,{method:'PUT',body:JSON.stringify({status:'approved'})}),'Đã duyệt đơn.',load)}>Duyệt</Button><Button size="small" danger onClick={()=>runAdminAction(()=>callAdminApi(`/admin/api/hrm/leave/${r.id}/review`,{method:'PUT',body:JSON.stringify({status:'rejected'})}),'Đã từ chối đơn.',load)}>Từ chối</Button></Space>:'—'}]} /></Card><Drawer title="Tạo đơn nghỉ phép" open={drawer?.type==='leave'} width="min(520px, 92vw)" onClose={()=>setDrawer(null)} extra={<Button type="primary" onClick={save}>Gửi đơn</Button>}><Form form={form} layout="vertical">{(data.employees??[]).length>0&&<Form.Item name="employee_id" label="Nhân sự"><Select allowClear options={data.employees.map(x=>({value:x.id,label:`${x.employee_code} · ${x.full_name}`}))}/></Form.Item>}<Form.Item name="leave_type" label="Loại nghỉ" rules={[{required:true}]}><Select options={[['annual','Phép năm'],['sick','Nghỉ ốm'],['unpaid','Không lương'],['maternity','Thai sản'],['paternity','Chế độ cha'],['other','Khác']].map(([value,label])=>({value,label}))}/></Form.Item><Form.Item name="start_date" label="Từ ngày" rules={[{required:true}]}><Input type="date" /></Form.Item><Form.Item name="end_date" label="Đến ngày" rules={[{required:true}]}><Input type="date" /></Form.Item><Form.Item name="days" label="Số ngày" rules={[{required:true}]}><InputNumber min={0.5} step={0.5} style={{width:'100%'}} /></Form.Item><Form.Item name="reason" label="Lý do"><Input.TextArea rows={4}/></Form.Item></Form></Drawer></Space>;
    }
    if (menuKey === 'hrm-attendance') {
        const attendanceLabels = { present:'Đủ công',late:'Đi muộn',remote:'Làm từ xa',leave:'Nghỉ phép',absent:'Vắng mặt',holiday:'Ngày lễ' };
        content = <Space direction="vertical" size={16} style={{ width:'100%' }}><PageHeader eyebrow="CHẤM CÔNG" title="Theo dõi ngày công" description="Quản lý giờ vào, giờ ra và trạng thái làm việc theo ngày." action={can('hrm.attendance.manage')&&<Button type="primary" icon={<PlusOutlined/>} onClick={()=>openDrawer('attendance')}>Nhập chấm công</Button>} /><Card><Table rowKey="id" dataSource={data.items??[]} columns={[{title:'Ngày',dataIndex:'work_date'},{title:'Nhân sự',render:(_,r)=>r.employee?.full_name},{title:'Giờ vào',dataIndex:'check_in_at',render:v=>v||'—'},{title:'Giờ ra',dataIndex:'check_out_at',render:v=>v||'—'},{title:'Số giờ',dataIndex:'worked_hours'},{title:'Trạng thái',render:(_,r)=><Tag color={r.status==='present'?'green':r.status==='absent'?'red':'blue'}>{attendanceLabels[r.status]||r.status}</Tag>},{title:'Ghi chú',dataIndex:'note'}]} /></Card><Drawer title="Nhập dữ liệu chấm công" open={drawer?.type==='attendance'} width="min(560px,92vw)" onClose={()=>setDrawer(null)} extra={<Button type="primary" onClick={save}>Lưu</Button>}><Form form={form} layout="vertical"><Form.Item name="employee_id" label="Nhân sự" rules={[{required:true}]}><Select showSearch optionFilterProp="label" options={(data.employees??[]).map(x=>({value:x.id,label:`${x.employee_code} · ${x.full_name}`}))}/></Form.Item><Form.Item name="work_date" label="Ngày làm việc" rules={[{required:true}]}><Input type="date"/></Form.Item><Row gutter={16}><Col span={12}><Form.Item name="check_in_at" label="Giờ vào"><Input type="time"/></Form.Item></Col><Col span={12}><Form.Item name="check_out_at" label="Giờ ra"><Input type="time"/></Form.Item></Col></Row><Form.Item name="worked_hours" label="Số giờ làm" rules={[{required:true}]}><InputNumber min={0} max={24} step={0.5} style={{width:'100%'}}/></Form.Item><Form.Item name="status" label="Trạng thái" rules={[{required:true}]}><Select options={Object.entries(attendanceLabels).map(([value,label])=>({value,label}))}/></Form.Item><Form.Item name="note" label="Ghi chú"><Input.TextArea rows={3}/></Form.Item></Form></Drawer></Space>;
    }
    if (menuKey === 'hrm-my-profile') {
        content = <Space direction="vertical" size={16} style={{width:'100%'}}><PageHeader eyebrow="CỔNG THÔNG TIN CÁ NHÂN" title="Hồ sơ của tôi" description="Thông tin công việc và liên hệ của bạn." action={can('hrm.profile.self.update')&&<Button onClick={()=>openDrawer('profile',data)}>Cập nhật liên hệ</Button>} /><Card><Descriptions bordered column={{xs:1,md:2}} items={[{key:'code',label:'Mã nhân sự',children:data.employee_code},{key:'name',label:'Họ và tên',children:data.full_name},{key:'dept',label:'Phòng ban',children:data.department?.name||'—'},{key:'position',label:'Chức vụ',children:data.position?.name||'—'},{key:'manager',label:'Quản lý trực tiếp',children:data.manager?.full_name||'—'},{key:'status',label:'Trạng thái',children:<Tag color={statusColors[data.employment_status]}>{statusLabels[data.employment_status]||data.employment_status}</Tag>},{key:'email',label:'Email công việc',children:data.work_email||'—'},{key:'phone',label:'Điện thoại',children:data.phone||'—'}]} /></Card><Card title="Hợp đồng"><Table rowKey="id" pagination={false} dataSource={data.contracts??[]} columns={[{title:'Số hợp đồng',dataIndex:'contract_number'},{title:'Loại',dataIndex:'contract_type'},{title:'Bắt đầu',dataIndex:'start_date'},{title:'Kết thúc',dataIndex:'end_date'},{title:'Trạng thái',dataIndex:'status'}]} /></Card><Drawer title="Cập nhật thông tin liên hệ" open={drawer?.type==='profile'} width="min(520px,92vw)" onClose={()=>setDrawer(null)} extra={<Button type="primary" onClick={save}>Lưu</Button>}><Form form={form} layout="vertical"><Form.Item name="personal_email" label="Email cá nhân"><Input /></Form.Item><Form.Item name="phone" label="Điện thoại"><Input /></Form.Item><Form.Item name="address" label="Địa chỉ"><Input.TextArea rows={4}/></Form.Item></Form></Drawer></Space>;
    }
    return content;
}
