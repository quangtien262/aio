import CheckOutlined from '@ant-design/icons/CheckOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import SearchOutlined from '@ant-design/icons/SearchOutlined';
import StopOutlined from '@ant-design/icons/StopOutlined';
import UserAddOutlined from '@ant-design/icons/UserAddOutlined';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Popconfirm from 'antd/es/popconfirm';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useCallback, useState } from 'react';
import { createIdempotencyKey } from '../../api/fnbApi';
import FnbPageHeader from '../../components/FnbPageHeader';
import { FnbResourceError } from '../../components/FnbResourceState';
import FnbStatusTag from '../../components/FnbStatusTag';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, formatDateTime } from '../../utils/fnbFormat';

const { Text } = Typography;

function AssignmentDrawer({ open, form, candidate, roles, terminals, resolving, saving, onResolve, onClose, onSubmit }) {
    return (
        <Drawer
            open={open}
            title="Phân công nhân viên"
            width="min(620px, 96vw)"
            onClose={onClose}
            destroyOnHidden
            extra={<Button type="primary" icon={<UserAddOutlined />} disabled={!candidate || !roles.length} loading={saving} onClick={() => form.submit()}>Phân công</Button>}
        >
            <Alert
                type="info"
                showIcon
                message="Tìm chính xác bằng email hoặc tên đăng nhập"
                description="Kết quả được che bớt và mã ứng viên chỉ dùng một lần trong phiên hiện tại."
                style={{ marginBottom: 16 }}
            />
            <Form form={form} layout="vertical" onFinish={onSubmit}>
                <Form.Item label="Tài khoản quản trị" required>
                    <Space.Compact style={{ width: '100%' }}>
                        <Form.Item name="identifier" noStyle rules={[{ required: true, min: 3, message: 'Nhập email hoặc tên đăng nhập.' }]}>
                            <Input placeholder="email@congty.vn hoặc username" onChange={() => candidate && onResolve(null)} />
                        </Form.Item>
                        <Button icon={<SearchOutlined />} loading={resolving} onClick={() => onResolve(form.getFieldValue('identifier'))}>Kiểm tra</Button>
                    </Space.Compact>
                </Form.Item>
                {candidate ? (
                    <Alert type="success" showIcon message={candidate.display_name} description={`${candidate.masked_identifier} · mã xác nhận hết hạn ${formatDateTime(candidate.expires_at)}`} style={{ marginBottom: 16 }} />
                ) : null}
                <Form.Item name="role_key" label="Vai trò tại quán" rules={[{ required: true, message: 'Chọn vai trò.' }]}>
                    <Select options={roles.map((role) => ({ value: role.key, label: role.name, title: role.description }))} placeholder={roles.length ? 'Chọn vai trò' : 'Máy chủ chưa trả danh sách vai trò'} disabled={!roles.length} />
                </Form.Item>
                <Form.Item name="terminal_ids" label="Giới hạn theo quầy (tùy chọn)">
                    <Select mode="multiple" allowClear options={terminals.map((terminal) => ({ value: terminal.id, label: terminal.name }))} placeholder="Để trống để dùng toàn điểm bán" />
                </Form.Item>
                <Alert type="warning" showIcon message="Thay đổi quyền là thao tác bảo mật" description="Hệ thống yêu cầu xác thực lại; quyền thực tế luôn được máy chủ kiểm tra theo phiên bản module đang cài." />
            </Form>
        </Drawer>
    );
}

export default function FnbStaffPage() {
    const { message } = App.useApp();
    const { api, outletId, can, runCommand } = useFnbWorkspace();
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [candidate, setCandidate] = useState(null);
    const [resolving, setResolving] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form] = Form.useForm();
    const loadStaff = useCallback(() => api.staff(), [api]);
    const loadSettings = useCallback(() => api.settings(), [api]);
    const loadApprovals = useCallback(() => api.read(api.outletPath('approvals')), [api]);
    const staffResource = useFnbResource({ enabled: Boolean(outletId), loader: loadStaff, deps: [outletId] });
    const settingsResource = useFnbResource({ enabled: Boolean(outletId && can('fnb.settings.view')), loader: loadSettings, deps: [outletId] });
    const approvalsResource = useFnbResource({ enabled: Boolean(outletId && can('fnb.outlet.view')), loader: loadApprovals, deps: [outletId], pollMs: 10000 });
    const staffData = staffResource.data ?? {};
    const roles = asArray(staffData.role_presets ?? staffData.roles);
    const terminals = asArray(settingsResource.data?.terminals);

    const reload = () => Promise.all([staffResource.reload({ silent: true }), approvalsResource.reload({ silent: true })]);

    const resolveCandidate = async (identifier) => {
        if (identifier === null) {
            setCandidate(null);
            return;
        }
        try {
            await form.validateFields(['identifier']);
        } catch {
            return;
        }
        setResolving(true);
        try {
            const result = await api.command(api.outletPath('staff-candidates/resolve'), { identifier: String(identifier).trim() }, { idempotencyKey: createIdempotencyKey('staff-resolve') });
            const resolved = result.data?.candidate ?? null;
            setCandidate(resolved);
            if (!resolved) message.warning(result.data?.message ?? 'Không tìm thấy tài khoản có thể phân công.');
        } catch (error) {
            message.error(error.message || 'Không kiểm tra được tài khoản.');
        } finally {
            setResolving(false);
        }
    };

    const assign = async (values) => {
        if (!candidate?.candidate_token) return;
        const key = createIdempotencyKey('staff-assign');
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command(api.outletPath('staff-assignments'), {
                    candidate_token: candidate.candidate_token,
                    role_key: values.role_key,
                    terminal_ids: values.terminal_ids ?? [],
                }, { idempotencyKey: key, ...auth }),
                successMessage: 'Đã phân công nhân viên.',
                onSuccess: async () => { setDrawerOpen(false); setCandidate(null); form.resetFields(); await reload(); },
                onConflict: reload,
            });
        } finally {
            setSaving(false);
        }
    };

    const revoke = async (record) => {
        const key = createIdempotencyKey('staff-revoke');
        await runCommand({
            execute: (auth) => api.command(api.outletPath(`staff-assignments/${record.id}`), {
                expected_binding_version: record.binding_version,
                expected_membership_version: record.version,
                expected_versions: {
                    [`staff_membership:${record.id}`]: record.version,
                    [`staff_binding:${record.id}`]: record.binding_version,
                },
            }, { method: 'DELETE', idempotencyKey: key, ...auth }),
            successMessage: 'Đã thu hồi phân công tại điểm bán.',
            onSuccess: reload,
            onConflict: reload,
        });
    };

    const approve = async (approval) => {
        const key = createIdempotencyKey('approval-approve');
        await runCommand({
            execute: (auth) => api.command(`approvals/${approval.id}/approve`, {
                expected_version: approval.version,
                expected_versions: { [`approval:${approval.public_id ?? approval.id}`]: approval.version },
                note: 'Đã kiểm tra yêu cầu trên hộp thư phê duyệt F&B.',
            }, { idempotencyKey: key, ...auth }),
            successMessage: 'Đã phê duyệt yêu cầu.',
            onSuccess: () => approvalsResource.reload({ silent: true }),
            onConflict: approvalsResource.reload,
        });
    };

    if (staffResource.error && !staffResource.data) {
        return <FnbResourceError error={staffResource.error} onRetry={staffResource.reload} title="Không tải được nhân viên điểm bán" />;
    }

    const staffTab = <>
        <div className="fnb-table-toolbar">
            <Text type="secondary">Vai trò là preset theo đúng phiên bản module, không cấp từng quyền rời rạc tại màn hình này.</Text>
            {can('fnb.staff.assign') ? <Button type="primary" icon={<UserAddOutlined />} onClick={() => { form.resetFields(); setCandidate(null); setDrawerOpen(true); }}>Phân công</Button> : null}
        </div>
        {!roles.length && can('fnb.staff.assign') ? <Alert type="warning" showIcon message="Máy chủ chưa cung cấp danh sách vai trò có thể gán" description="Nút xác nhận sẽ khóa để tránh gửi role_key đoán từ giao diện." style={{ marginBottom: 12 }} /> : null}
        <Table
            rowKey="id"
            loading={staffResource.loading}
            dataSource={asArray(staffData.items)}
            columns={[
                { title: 'Nhân viên', dataIndex: 'name', render: (value) => <Text strong>{value}</Text> },
                { title: 'Vai trò', render: (_, record) => <Space direction="vertical" size={0}><Text>{record.role_name ?? 'Chưa gán vai trò'}</Text><Text type="secondary">{record.role_key}</Text></Space> },
                { title: 'Hiệu lực đến', dataIndex: 'expires_at', render: (value) => value ? formatDateTime(value) : 'Không giới hạn' },
                { title: '', render: (_, record) => can('fnb.staff.assign') ? <Popconfirm title="Thu hồi quyền tại điểm bán?" description="Tài khoản vẫn tồn tại trong hệ thống, chỉ mất phân công F&B tại quán này." onConfirm={() => revoke(record)}><Button danger size="small" icon={<StopOutlined />}>Thu hồi</Button></Popconfirm> : null },
            ]}
        />
    </>;

    const approvalTab = <>
        <Alert type="info" showIcon message="Người yêu cầu và người phê duyệt dùng hai phiên đăng nhập riêng" description="Màn hình không chuyển mật khẩu hoặc mã phê duyệt giữa hai người." style={{ marginBottom: 14 }} />
        {asArray(approvalsResource.data?.items).length ? <Table
            rowKey="id"
            loading={approvalsResource.loading}
            dataSource={asArray(approvalsResource.data?.items)}
            scroll={{ x: 850 }}
            columns={[
                { title: 'Thao tác', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.action}</Text><Text type="secondary">{record.subject_id ?? record.subject}</Text></Space> },
                { title: 'Lý do', dataIndex: 'reason' },
                { title: 'Quyền phê duyệt', render: (_, record) => asArray(record.required_permissions).map((permission) => <Tag key={permission}>{permission}</Tag>) },
                { title: 'Hết hạn', dataIndex: 'expires_at', render: formatDateTime },
                { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                { title: '', fixed: 'right', render: (_, record) => record.status === 'pending' ? <Button type="primary" size="small" icon={<CheckOutlined />} onClick={() => approve(record)}>Phê duyệt</Button> : null },
            ]}
        /> : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Không có yêu cầu chờ bạn xử lý" />}
    </>;

    return (
        <div className="fnb-page-stack">
            <FnbPageHeader
                eyebrow="Phân quyền theo điểm bán"
                title="Nhân viên & phê duyệt"
                description="Phân công vai trò preset, thu hồi quyền và xử lý thao tác nhạy cảm với dấu vết đầy đủ."
                actions={<Button icon={<ReloadOutlined />} loading={staffResource.refreshing} onClick={reload}>Làm mới</Button>}
            />
            <Card className="fnb-panel">
                <Tabs items={[
                    { key: 'staff', label: 'Nhân viên', children: staffTab },
                    { key: 'approvals', label: <Space><SafetyCertificateOutlined /> Phê duyệt</Space>, children: approvalTab },
                ]} />
            </Card>
            <AssignmentDrawer open={drawerOpen} form={form} candidate={candidate} roles={roles} terminals={terminals} resolving={resolving} saving={saving} onResolve={resolveCandidate} onClose={() => setDrawerOpen(false)} onSubmit={assign} />
        </div>
    );
}
