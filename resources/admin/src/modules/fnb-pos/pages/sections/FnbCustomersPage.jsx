import { useCallback, useEffect, useState } from 'react';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import DownOutlined from '@ant-design/icons/DownOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import EyeOutlined from '@ant-design/icons/EyeOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import TeamOutlined from '@ant-design/icons/TeamOutlined';
import UndoOutlined from '@ant-design/icons/UndoOutlined';
import Avatar from 'antd/es/avatar';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Checkbox from 'antd/es/checkbox';
import Col from 'antd/es/col';
import DatePicker from 'antd/es/date-picker';
import viVN from 'antd/es/date-picker/locale/vi_VN';
import Descriptions from 'antd/es/descriptions';
import Dropdown from 'antd/es/dropdown';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Segmented from 'antd/es/segmented';
import Skeleton from 'antd/es/skeleton';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import dayjs from 'dayjs';
import 'dayjs/locale/vi';
import { createIdempotencyKey } from '../../api/fnbApi';
import FnbPageHeader from '../../components/FnbPageHeader';
import { FnbResourceError } from '../../components/FnbResourceState';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, formatDateTime, formatMinorMoney } from '../../utils/fnbFormat';

const { Text } = Typography;
const dateOnly = (value) => value ? String(value).slice(0, 10).split('-').reverse().join('/') : '—';
const customerStatus = (status) => status === 'archived' ? <Tag>Đã xóa</Tag> : <Tag color="green">Đang hoạt động</Tag>;

function CustomerDetails({ id, onClose, onEdit, canEdit }) {
    const { api, outletId, terminalId } = useFnbWorkspace();
    const loader = useCallback(async () => {
        const [profile, history] = await Promise.all([api.read(`customers/${id}`), api.read(`customers/${id}/history`, { limit: 25 })]);
        return { data: { profile: profile.data, history: history.data?.items ?? [] }, meta: history.meta };
    }, [api, id]);
    const resource = useFnbResource({ enabled: Boolean(id), loader, deps: [id, outletId, terminalId] });
    const [moreLoading, setMoreLoading] = useState(false);
    const [moreError, setMoreError] = useState(null);
    const [extra, setExtra] = useState({ items: [], cursor: null, hasMore: null });
    useEffect(() => { setExtra({ items: [], cursor: null, hasMore: null }); setMoreError(null); }, [id, outletId, terminalId]);
    const profile = resource.data?.profile;
    const more = extra.hasMore ?? resource.meta.has_more;
    const loadMore = async () => {
        setMoreLoading(true);
        setMoreError(null);
        try {
            const result = await api.read(`customers/${id}/history`, { limit: 25, before_id: extra.cursor ?? resource.meta.next_before_check_id });
            setExtra((current) => ({ items: [...current.items, ...asArray(result.data?.items)], cursor: result.meta.next_before_check_id, hasMore: result.meta.has_more }));
        } catch (error) { setMoreError(error); }
        finally { setMoreLoading(false); }
    };

    return <Modal open={Boolean(id)} title="Chi tiết khách hàng" width={800} centered onCancel={onClose} destroyOnHidden className="fnb-customer-details"
        footer={<Space><Button onClick={onClose}>Đóng</Button>{canEdit && profile ? <Button type="primary" icon={<EditOutlined />} onClick={() => onEdit(profile)}>Chỉnh sửa</Button> : null}</Space>}>
        {resource.loading ? <Skeleton active paragraph={{ rows: 6 }} /> : resource.error ? <FnbResourceError error={resource.error} onRetry={resource.reload} /> : profile ? <>
            <div className="fnb-customer-profile-heading"><Avatar size={48} icon={<TeamOutlined />} /><div><Text strong>{profile.name}</Text><div><Text type="secondary">{profile.code || 'Chưa có mã khách hàng'}</Text> {customerStatus(profile.status)}</div></div></div>
            <Descriptions size="small" column={{ xs: 1, sm: 2, md: 2, lg: 2, xl: 2, xxl: 2 }} items={[
                { key: 'phone', label: 'Điện thoại', children: profile.phone_normalized || '—' },
                { key: 'email', label: 'Email', children: profile.email || '—' },
                { key: 'birthday', label: 'Ngày sinh', children: dateOnly(profile.birthday) },
                { key: 'created', label: 'Ngày tạo', children: dateOnly(profile.created_at) },
                { key: 'privacy', label: 'Lưu thông tin', children: profile.privacy_consented_at ? 'Đã đồng ý' : 'Chưa ghi nhận' },
                { key: 'marketing', label: 'Nhận ưu đãi', children: profile.marketing_consented_at ? 'Đã đồng ý' : 'Chưa ghi nhận' },
                { key: 'notes', label: 'Ghi chú', span: 2, children: <span style={{ whiteSpace: 'pre-wrap' }}>{profile.notes || '—'}</span> },
            ]} />
            <div className="fnb-customer-history-heading"><Text strong>Lịch sử mua hàng</Text><Text type="secondary">Tại điểm bán đang chọn</Text></div>
            <Table size="small" rowKey="id" dataSource={[...asArray(resource.data?.history), ...extra.items]} pagination={false} scroll={{ y: 240, x: 570 }} locale={{ emptyText: 'Chưa có bill đã thanh toán tại điểm bán này.' }} columns={[
                { title: 'Bill', dataIndex: 'check_no', width: 200 },
                { title: 'Thanh toán lúc', render: (_, row) => formatDateTime(row.paid_at ?? row.closed_at, { timeZone: row.timezone_snapshot }) },
                { title: 'Đã thu', align: 'right', render: (_, row) => formatMinorMoney(row.gross_paid_total_minor, row.currency) },
                { title: 'Đã hoàn', align: 'right', render: (_, row) => formatMinorMoney(row.refunded_total_minor, row.currency) },
            ]} />
            {moreError ? <FnbResourceError error={moreError} onRetry={loadMore} /> : null}
            {more ? <Button block style={{ marginTop: 12 }} loading={moreLoading} onClick={loadMore}>Xem thêm lịch sử</Button> : null}
        </> : null}
    </Modal>;
}

export default function FnbCustomersPage() {
    const { api, can, runCommand, outletId, terminalId } = useFnbWorkspace();
    const [form] = Form.useForm();
    const [keyword, setKeyword] = useState('');
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('active');
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(20);
    const [editor, setEditor] = useState(null);
    const [detailsId, setDetailsId] = useState(null);
    const [statusConfirmation, setStatusConfirmation] = useState(null);
    const [saving, setSaving] = useState(false);
    const canUpdate = can('fnb.customer.update');
    useEffect(() => {
        const timer = window.setTimeout(() => { setQuery(keyword.trim()); setPage(1); }, 300);
        return () => window.clearTimeout(timer);
    }, [keyword]);
    useEffect(() => { setEditor(null); setDetailsId(null); setStatusConfirmation(null); setPage(1); }, [outletId, terminalId]);
    const loader = useCallback(() => api.read(api.outletPath('customer-profiles'), { query, status, page, per_page: perPage }), [api, query, status, page, perPage]);
    const resource = useFnbResource({ loader, deps: [outletId, terminalId, query, status, page, perPage] });
    const integrationLoader = useCallback(() => api.read(api.outletPath('customers-integration')), [api]);
    const integration = useFnbResource({ loader: integrationLoader, deps: [outletId, terminalId] });
    const sharedBlocked = integration.data?.mode === 'shared' && !integration.data?.ready;
    const reload = () => resource.reload();
    const openEditor = (record = null) => {
        setDetailsId(null);
        form.resetFields();
        form.setFieldsValue(record ? { ...record, phone: record.phone_normalized, privacy_consent: Boolean(record.privacy_consented_at), marketing_consent: Boolean(record.marketing_consented_at) } : { privacy_consent: false, marketing_consent: false });
        setEditor({ record });
    };
    const save = async (values) => {
        const record = editor?.record;
        const payload = { name: values.name.trim(), phone: values.phone?.trim() || null, email: values.email?.trim() || null,
            ...(canUpdate ? { birthday: values.birthday || null, notes: values.notes?.trim() || null, privacy_consent: Boolean(values.privacy_consent), marketing_consent: Boolean(values.marketing_consent) } : {}),
            ...(record ? { expected_version: record.version } : {}) };
        setSaving(true);
        try {
            const key = createIdempotencyKey('customer-save');
            await runCommand({ execute: (auth) => api.command(record ? `customers/${record.id}` : 'customers', payload, { method: record ? 'PUT' : 'POST', idempotencyKey: key, ...auth }),
                successMessage: record ? 'Đã cập nhật khách hàng.' : 'Đã thêm khách hàng.',
                onSuccess: async () => { setEditor(null); await reload(); }, onConflict: async () => { setEditor(null); await reload(); } });
        } finally { setSaving(false); }
    };
    const changeStatus = async (record, archived) => {
        const key = createIdempotencyKey(archived ? 'customer-delete' : 'customer-restore');
        setSaving(true);
        try {
            await runCommand({ execute: (auth) => api.command(`customers/${record.id}`, { expected_version: record.version, ...(archived ? {} : { status: 'active' }) }, { method: archived ? 'DELETE' : 'PUT', idempotencyKey: key, ...auth }),
                successMessage: archived ? 'Đã chuyển khách hàng vào mục Đã xóa.' : 'Đã khôi phục khách hàng.', onSuccess: async () => {
                    if (asArray(resource.data?.items).length === 1 && page > 1) setPage(page - 1);
                    else await reload();
                }, onConflict: reload });
        } finally { setSaving(false); }
    };

    return <div className="fnb-page-stack fnb-customers-page">
        <FnbPageHeader eyebrow="CHĂM SÓC KHÁCH HÀNG" title="Khách hàng" description="Quản lý hồ sơ và lịch sử mua hàng, dùng chung với màn hình bán hàng." actions={<>
            <Button icon={<ReloadOutlined />} loading={resource.refreshing} onClick={reload}>Làm mới</Button>
            {can('fnb.customer.create') ? <Button type="primary" disabled={sharedBlocked} icon={<PlusOutlined />} onClick={() => openEditor()}>Thêm khách hàng</Button> : null}
        </>} />
        {integration.error ? <FnbResourceError error={integration.error} onRetry={integration.reload} /> : <Alert showIcon type={sharedBlocked ? 'error' : integration.data?.mode === 'shared' ? 'success' : 'info'}
            message={integration.data?.mode === 'shared' ? 'Hồ sơ dùng chung từ Customers' : 'Hồ sơ khách hàng riêng của quán'}
            description={sharedBlocked ? integration.data?.reason || 'Customers đang dừng. Không thể chuyển về hồ sơ riêng để ghi thay thế.' : integration.data?.mode === 'shared' ? 'Thông tin chung được quản lý tại Customers; ghi chú phục vụ và sự đồng ý với quán được giữ riêng tại F&B.' : 'Cài đặt và cấu hình Customers để dùng chung hồ sơ giữa các ứng dụng. Dữ liệu và lịch sử hiện tại vẫn được giữ.'}
            action={<Button href="/admin/modules/customers" size="small">{integration.data?.available ? 'Quản lý Customers' : 'Cài đặt Customers'}</Button>} />}
        <Card>
            <div className="fnb-customer-toolbar"><Input.Search allowClear maxLength={120} value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm tên, mã, điện thoại hoặc email" aria-label="Tìm khách hàng" />
                <Segmented value={status} onChange={(value) => { setStatus(value); setPage(1); }} options={[{ value: 'active', label: 'Đang hoạt động' }, { value: 'archived', label: 'Đã xóa' }, { value: 'all', label: 'Tất cả' }]} />
            </div>
            <div className="fnb-customer-list-heading"><Text strong>Danh sách khách hàng <span className="fnb-menu-editor-count">{resource.meta.total ?? 0}</span></Text><Text type="secondary">Hồ sơ dùng chung trong hệ thống quán</Text></div>
            {resource.error ? <FnbResourceError error={resource.error} onRetry={reload} /> : <Table rowKey="id" loading={resource.loading || resource.refreshing} dataSource={asArray(resource.data?.items)} scroll={{ x: 930 }}
                locale={{ emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={query ? 'Không tìm thấy khách hàng phù hợp.' : status === 'archived' ? 'Chưa có khách hàng đã xóa.' : 'Chưa có khách hàng.'} /> }}
                pagination={{ current: page, pageSize: perPage, total: resource.meta.total ?? 0, showSizeChanger: true, pageSizeOptions: [10, 20, 50, 100], showTotal: (total) => `${total} khách hàng`, onChange: (next, size) => { setPage(size !== perPage ? 1 : next); setPerPage(size); } }}
                columns={[
                    { title: 'Khách hàng', width: 260, render: (_, record) => <div className="fnb-customer-name"><Avatar>{record.name?.trim()?.slice(0, 1).toUpperCase() || 'K'}</Avatar><div><Button type="link" onClick={() => setDetailsId(record.id)}>{record.name}</Button><Text type="secondary">{record.code || 'Chưa có mã'}</Text></div></div> },
                    { title: 'Liên hệ', width: 250, render: (_, record) => <Space direction="vertical" size={2}><Text>{record.phone_normalized || '—'}</Text><Text type="secondary">{record.email || 'Chưa có email'}</Text></Space> },
                    { title: 'Ngày sinh', width: 110, render: (_, record) => dateOnly(record.birthday) },
                    { title: 'Trạng thái', width: 150, render: (_, record) => customerStatus(record.status) },
                    { title: 'Thao tác', fixed: 'right', width: 140, render: (_, record) => <Dropdown trigger={['click']} menu={{
                        items: [
                            { key: 'view', label: 'Xem chi tiết', icon: <EyeOutlined /> },
                            ...(canUpdate ? [
                                { key: 'edit', label: 'Chỉnh sửa', icon: <EditOutlined /> },
                                { type: 'divider' },
                                record.status === 'archived'
                                    ? { key: 'restore', label: 'Khôi phục', icon: <UndoOutlined /> }
                                    : { key: 'delete', label: 'Xóa khách hàng', icon: <DeleteOutlined />, danger: true },
                            ] : []),
                        ],
                        onClick: ({ key }) => {
                            if (key === 'view') setDetailsId(record.id);
                            else if (canUpdate && key === 'edit') openEditor(record);
                            else if (canUpdate && ['delete', 'restore'].includes(key)) setStatusConfirmation({ record, archived: key === 'delete' });
                        },
                    }}><Button size="small" disabled={saving} aria-label={`Thao tác khách hàng ${record.name}`}>Thao tác <DownOutlined /></Button></Dropdown> },
                ]} />}
        </Card>
        <Modal open={Boolean(statusConfirmation)} centered width={420} destroyOnHidden
            title={statusConfirmation?.archived ? 'Xóa khách hàng?' : 'Khôi phục khách hàng?'}
            okText={statusConfirmation?.archived ? 'Xóa khách hàng' : 'Khôi phục'} cancelText="Hủy"
            okButtonProps={{ danger: Boolean(statusConfirmation?.archived) }} confirmLoading={saving}
            cancelButtonProps={{ disabled: saving }} closable={!saving} maskClosable={!saving} keyboard={!saving}
            onCancel={() => { if (!saving) setStatusConfirmation(null); }}
            onOk={async () => { if (!statusConfirmation || saving) return; await changeStatus(statusConfirmation.record, statusConfirmation.archived); setStatusConfirmation(null); }}>
            <Text strong>{statusConfirmation?.record.name}</Text>
            <p>{statusConfirmation?.archived ? 'Có thể khôi phục trong mục Đã xóa. Lịch sử giao dịch vẫn được giữ.' : 'Khách hàng sẽ xuất hiện lại trong danh sách đang hoạt động và tra cứu tại POS.'}</p>
        </Modal>
        <Modal open={Boolean(editor)} title={<Space><span className="fnb-customer-editor-icon"><TeamOutlined /></span>{editor?.record ? 'Cập nhật khách hàng' : 'Thêm khách hàng'}</Space>} width={620} centered destroyOnHidden className="fnb-customer-editor"
            onCancel={saving ? undefined : () => setEditor(null)} closable={!saving} maskClosable={!saving} keyboard={!saving}
            footer={<Space><Button disabled={saving} onClick={() => setEditor(null)}>Hủy</Button><Button type="primary" loading={saving} onClick={() => form.submit()}>Lưu khách hàng</Button></Space>}>
            <Form form={form} layout="vertical" disabled={saving} onFinish={save}>
                <Row gutter={[16, 0]}>
                    <Col xs={24} sm={canUpdate ? 16 : 24}><Form.Item name="name" label="Tên khách hàng" rules={[{ required: true, whitespace: true, message: 'Nhập tên khách hàng.' }]}><Input maxLength={255} placeholder="Họ và tên" /></Form.Item></Col>
                    {canUpdate ? <Col xs={24} sm={8}><Form.Item name="birthday" label="Ngày sinh"
                        getValueProps={(value) => ({ value: value ? dayjs(value).locale('vi') : null })}
                        getValueFromEvent={(value) => value ? value.format('YYYY-MM-DD') : null}>
                        <DatePicker locale={viVN} format="DD/MM/YYYY" placeholder="DD/MM/YYYY"
                            disabledDate={(date) => date && !date.isBefore(dayjs().startOf('day'))} style={{ width: '100%' }} />
                    </Form.Item></Col> : null}
                    <Col xs={24} sm={12}><Form.Item name="phone" label="Số điện thoại" rules={[{ validator: (_, value) => !value || (/^[+()\d\s.-]+$/.test(value) && /^\d{7,15}$/.test(value.replace(/\D/g, ''))) ? Promise.resolve() : Promise.reject(new Error('Nhập số điện thoại từ 7 đến 15 chữ số.')) }]}><Input type="tel" maxLength={40} placeholder="Nhập số điện thoại" /></Form.Item></Col>
                    <Col xs={24} sm={12}><Form.Item name="email" label="Email" rules={[{ type: 'email', message: 'Nhập email hợp lệ.' }]}><Input type="email" maxLength={255} placeholder="email@example.com" /></Form.Item></Col>
                    {canUpdate ? <>
                        <Col span={24}><Form.Item name="notes" label="Ghi chú"><Input.TextArea autoSize={{ minRows: 2, maxRows: 3 }} maxLength={2000} placeholder="Sở thích, yêu cầu phục vụ…" /></Form.Item></Col>
                        <Col span={24}><div className="fnb-customer-consents"><Form.Item name="privacy_consent" valuePropName="checked"><Checkbox>Khách đồng ý lưu thông tin tại quán hiện tại</Checkbox></Form.Item><Form.Item name="marketing_consent" valuePropName="checked"><Checkbox>Khách đồng ý nhận ưu đãi từ quán hiện tại</Checkbox></Form.Item></div></Col>
                    </> : null}
                </Row>
            </Form>
        </Modal>
        <CustomerDetails key={`${detailsId}:${outletId}:${terminalId}`} id={detailsId} onClose={() => setDetailsId(null)} onEdit={openEditor} canEdit={canUpdate} />
    </div>;
}
