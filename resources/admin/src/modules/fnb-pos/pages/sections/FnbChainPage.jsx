import { useCallback, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Popconfirm from 'antd/es/popconfirm';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import FnbPageHeader from '../../components/FnbPageHeader';
import { FnbResourceError } from '../../components/FnbResourceState';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, formatDateTime } from '../../utils/fnbFormat';
import FnbChainMenu from '../../components/FnbChainMenu';
import { createIdempotencyKey } from '../../api/fnbApi';

const names = { brands: 'thương hiệu', regions: 'vùng', outlets: 'điểm bán', staff: 'quản lý chuỗi' };

export default function FnbChainPage() {
    const { baseApi: api, can, runCommand, reloadBootstrap } = useFnbWorkspace();
    const load = useCallback(() => api.chain(), [api]);
    const resource = useFnbResource({ loader: load, deps: [api] });
    const data = resource.data ?? {};
    const [editor, setEditor] = useState(null);
    const [saving, setSaving] = useState(false);
    const [auditEnabled, setAuditEnabled] = useState(false);
    const [auditOutlet, setAuditOutlet] = useState('');
    const [auditCursor, setAuditCursor] = useState('');
    const [form] = Form.useForm();
    const loadAudit = useCallback(() => api.read('chain/audit', { outlet_id: auditOutlet || undefined, cursor: auditCursor || undefined }), [api, auditOutlet, auditCursor]);
    const audit = useFnbResource({ enabled: auditEnabled, loader: loadAudit, deps: [api, auditEnabled, auditOutlet, auditCursor] });
    const permitted = (permission) => can(permission) || asArray(data.hq_permissions).includes(permission);
    const open = (kind, record = null) => {
        form.resetFields();
        form.setFieldsValue(record ?? { status: 'active', timezone: 'Asia/Ho_Chi_Minh', currency: 'VND', table_count: 5, terminal_code: 'POS01', terminal_name: 'Quầy 1', station_code: 'BAR', station_name: 'Bar' });
        setEditor({ kind, record });
    };
    const save = async (values) => {
        const idempotencyKey = createIdempotencyKey('chain-save');
        const { kind, record } = editor;
        let payload = { ...values, ...(record ? { expected_version: record.version } : {}) };
        if (kind === 'outlets' && !record) {
            payload = { ...payload, terminal: { code: values.terminal_code, name: values.terminal_name, type: 'pos' }, station: { code: values.station_code, name: values.station_name, sla_seconds: 600 } };
            ['terminal_code', 'terminal_name', 'station_code', 'station_name'].forEach((key) => delete payload[key]);
        }
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command(`chain/${kind}${record && kind !== 'staff' ? `/${record.id}` : ''}`, payload, { method: record && kind !== 'staff' ? 'PUT' : 'POST', idempotencyKey, ...auth }),
                authApi: api,
                title: `${record ? 'Cập nhật' : 'Thêm'} ${names[kind]}`,
                successMessage: `Đã lưu ${names[kind]}.`,
                onSuccess: async () => { setEditor(null); await Promise.all([resource.reload(), reloadBootstrap()]); },
                onConflict: resource.reload,
            });
        } finally { setSaving(false); }
    };
    const revokeHq = async (record) => {
        const idempotencyKey = createIdempotencyKey('chain-staff-revoke');
        await runCommand({ execute: (auth) => api.command(`chain/staff/${record.id}`, { expected_version: record.version }, { method: 'DELETE', idempotencyKey, ...auth }), authApi: api,
            title: 'Thu hồi quyền quản lý chuỗi', successMessage: 'Đã thu hồi quyền quản lý chuỗi.', onSuccess: async () => { await Promise.all([resource.reload(), reloadBootstrap()]); }, onConflict: resource.reload });
    };
    if (resource.error && !resource.data) return <FnbResourceError error={resource.error} onRetry={resource.reload} />;
    const entityTab = (kind) => <>
        <div className="fnb-table-toolbar"><span>{kind === 'outlets' ? 'Điểm bán có quầy, trạm pha chế và ngày kinh doanh riêng.' : `Danh sách ${names[kind]} dùng chung trong chuỗi.`}</span>{permitted('fnb.chain.manage') ? <Button type="primary" onClick={() => open(kind)}>Thêm {names[kind]}</Button> : null}</div>
        <Table loading={resource.loading} rowKey="id" dataSource={asArray(data[kind])} scroll={{ x: 700 }} columns={[
            { title: 'Mã', dataIndex: 'code' }, { title: 'Tên', dataIndex: 'name' },
            ...(kind === 'outlets' ? [
                { title: 'Thương hiệu', render: (_, item) => asArray(data.brands).find((brand) => String(brand.id) === String(item.brand_id))?.name ?? '—' },
                { title: 'Vùng', render: (_, item) => asArray(data.regions).find((region) => String(region.id) === String(item.region_id))?.name ?? '—' },
            ] : []),
            { title: 'Trạng thái', dataIndex: 'status', render: (value) => <Tag color={value === 'active' ? 'green' : 'default'}>{value === 'active' ? 'Hoạt động' : 'Tạm ngưng'}</Tag> },
            { title: '', render: (_, item) => permitted('fnb.chain.manage') ? <Button onClick={() => open(kind, item)}>Sửa</Button> : null },
        ]} />
    </>;
    return <div className="fnb-page-stack">
        <FnbPageHeader title="Điều hành chuỗi" eyebrow="Trụ sở chính" description="Quản lý thương hiệu, vùng, điểm bán và phân quyền theo từng điểm bán." actions={<Button onClick={() => resource.reload()}>Đồng bộ</Button>} />
        {resource.error ? <Alert type="warning" showIcon message={resource.error.message} /> : null}
        <Card className="fnb-panel"><Tabs onChange={(key) => { if (key === 'audit') setAuditEnabled(true); }} items={[
            { key: 'outlets', label: `Điểm bán (${asArray(data.outlets).length})`, children: entityTab('outlets') },
            { key: 'regions', label: `Vùng (${asArray(data.regions).length})`, children: entityTab('regions') },
            { key: 'brands', label: `Thương hiệu (${asArray(data.brands).length})`, children: entityTab('brands') },
            ...(permitted('fnb.menu.master.manage') ? [{ key: 'menu', label: 'Thực đơn & bảng giá', children: <FnbChainMenu outlets={asArray(data.outlets)} /> }] : []),
            ...(permitted('fnb.chain.staff.manage') ? [{ key: 'staff', label: 'Quản lý chuỗi', children: <Space direction="vertical" style={{ width: '100%' }}><Alert type="info" showIcon message="Vai trò tại điểm bán được phân công riêng ở màn Nhân viên của từng điểm bán." description="Quản lý chuỗi được cấp quyền theo phạm vi trụ sở; mọi thay đổi đều cần xác thực lại." /><Button onClick={() => open('staff')} type="primary">Phân công quản lý chuỗi</Button><Table rowKey="id" dataSource={asArray(data.hq_staff)} columns={[
                { title: 'Nhân viên', dataIndex: 'display_name' }, { title: 'Vai trò', render: (_, item) => item.role_key === 'fnb-owner' ? 'Chủ chuỗi' : 'Quản lý chuỗi' },
                { title: 'Trạng thái', render: (_, item) => <Tag color={item.status === 'active' ? 'green' : 'default'}>{item.status === 'active' ? 'Đang phân công' : 'Đã thu hồi'}</Tag> },
                { title: '', render: (_, item) => <Space wrap><Button onClick={() => open('staff', item)}>{item.status === 'active' ? 'Đổi vai trò' : 'Cấp lại'}</Button>{item.status === 'active' ? <Popconfirm title="Thu hồi quyền quản lý chuỗi?" description="Các phân công riêng tại điểm bán vẫn được quản lý tại màn Nhân viên." onConfirm={() => revokeHq(item)} okText="Thu hồi" cancelText="Hủy"><Button danger>Thu hồi</Button></Popconfirm> : null}</Space> },
            ]} /></Space> }] : []),
            { key: 'audit', label: 'Nhật ký', children: <>
                <Select allowClear placeholder="Tất cả điểm bán" value={auditOutlet || undefined} onChange={(value) => { setAuditOutlet(value ?? ''); setAuditCursor(''); }} options={asArray(data.outlets).map((outlet) => ({ value: outlet.id, label: outlet.name }))} style={{ minWidth: 220, marginBottom: 16 }} />
                {audit.error ? <Alert type="error" message={audit.error.message} /> : null}
                <Table loading={audit.loading} rowKey={(item) => item.id ?? item.event_id} dataSource={asArray(audit.data?.items)} scroll={{ x: 850 }} pagination={false} columns={[
                    { title: 'Thời gian', render: (_, item) => formatDateTime(item.created_at ?? item.occurred_at) },
                    { title: 'Thao tác', dataIndex: 'action' }, { title: 'Người thực hiện', render: (_, item) => item.actor_name ?? item.actor_admin_id ?? item.actor_id ?? '—' },
                    { title: 'Đối tượng', render: (_, item) => item.target_type ? `${item.target_type} #${item.target_id}` : item.subject_type ? `${item.subject_type} #${item.subject_id}` : '—' },
                ]} />
                {audit.data?.next_cursor ? <Button style={{ marginTop: 12 }} onClick={() => setAuditCursor(audit.data.next_cursor)}>Xem cũ hơn</Button> : null}
                {auditCursor ? <Button onClick={() => setAuditCursor('')}>Mới nhất</Button> : null}
            </> },
        ]} /></Card>
        <Drawer open={Boolean(editor)} destroyOnHidden width="min(620px, 96vw)" onClose={saving ? undefined : () => setEditor(null)} title={`${editor?.record ? 'Cập nhật' : 'Thêm'} ${names[editor?.kind] ?? ''}`} extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Lưu</Button>}>
            <Form form={form} layout="vertical" onFinish={save}>
                {editor?.kind === 'staff' ? <>
                    <Form.Item name="identifier" label="Email hoặc tên đăng nhập" rules={[{ required: true }]}><Input disabled={Boolean(editor?.record)} autoComplete="off" /></Form.Item>
                    <Form.Item name="role_key" label="Vai trò" rules={[{ required: true }]}><Select options={[{ value: 'fnb-hq-manager', label: 'Quản lý chuỗi' }, { value: 'fnb-owner', label: 'Chủ chuỗi' }]} /></Form.Item>
                </> : <>
                    <Form.Item name="code" label="Mã" rules={[{ required: true }]}><Input disabled={Boolean(editor?.record)} /></Form.Item>
                    <Form.Item name="name" label="Tên" rules={[{ required: true }]}><Input /></Form.Item>
                    {editor?.kind === 'outlets' ? <>
                        <Form.Item name="brand_id" label="Thương hiệu"><Select allowClear options={asArray(data.brands).map((item) => ({ value: item.id, label: item.name }))} /></Form.Item>
                        <Form.Item name="region_id" label="Vùng"><Select allowClear options={asArray(data.regions).map((item) => ({ value: item.id, label: item.name }))} /></Form.Item>
                        <Form.Item name="phone" label="Điện thoại"><Input /></Form.Item><Form.Item name="address" label="Địa chỉ"><Input.TextArea /></Form.Item>
                        {!editor?.record ? <>
                            <Form.Item name="timezone" label="Múi giờ" rules={[{ required: true }]}><Input /></Form.Item>
                            <Form.Item name="currency" label="Tiền tệ"><Select options={[{ value: 'VND', label: 'VND' }]} /></Form.Item>
                            <Form.Item name="table_count" label="Số bàn khởi tạo"><InputNumber min={0} max={100} /></Form.Item>
                            <Form.Item name="terminal_code" label="Mã quầy POS" rules={[{ required: true }]}><Input /></Form.Item><Form.Item name="terminal_name" label="Tên quầy POS" rules={[{ required: true }]}><Input /></Form.Item>
                            <Form.Item name="station_code" label="Mã trạm Bar" rules={[{ required: true }]}><Input /></Form.Item><Form.Item name="station_name" label="Tên trạm Bar" rules={[{ required: true }]}><Input /></Form.Item>
                        </> : null}
                    </> : null}
                    <Form.Item name="status" label="Trạng thái"><Select options={[{ value: 'active', label: 'Hoạt động' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item>
                </>}
            </Form>
        </Drawer>
    </div>;
}
