import { useEffect, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import AppstoreOutlined from '@ant-design/icons/AppstoreOutlined';
import AuditOutlined from '@ant-design/icons/AuditOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import LinkOutlined from '@ant-design/icons/LinkOutlined';
import LockOutlined from '@ant-design/icons/LockOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import SlidersOutlined from '@ant-design/icons/SlidersOutlined';
import SwapOutlined from '@ant-design/icons/SwapOutlined';
import TeamOutlined from '@ant-design/icons/TeamOutlined';
import '../styles/customers-settings.css';
import { customerId, customerMigrationActions, customerRows } from '../utils/customerData';

export default function CustomerBookSettings({ api, book, onChanged }) {
    const [section, setSection] = useState('fields');
    const [loading, setLoading] = useState(true);
    const [fields, setFields] = useState([]);
    const [bindings, setBindings] = useState([]);
    const [grants, setGrants] = useState([]);
    const [settingsOptions, setSettingsOptions] = useState({ admins: [], scopes: [] });
    const [error, setError] = useState(null);
    const [editing, setEditing] = useState(null);
    const [migration, setMigration] = useState(null);
    const [report, setReport] = useState(null);
    const [busy, setBusy] = useState(false);
    const [form] = Form.useForm();
    const prefix = `books/${customerId(book)}`;
    const reload = async () => {
        setLoading(true);
        const results = await Promise.allSettled(['fields', 'bindings', 'grants', 'settings-options'].map((path) => api.read(`${prefix}/${path}`)));
        [setFields, setBindings, setGrants].forEach((setter, index) => setter(results[index].status === 'fulfilled' ? customerRows(results[index].value.data) : []));
        if (results[3].status === 'fulfilled') setSettingsOptions(results[3].value.data ?? {});
        setError(results.slice(0, 3).filter((item) => item.status === 'rejected').map((item) => item.reason.message).join(' · ') || null);
        setLoading(false);
    };
    useEffect(() => { reload(); }, [api, prefix]);
    const open = (kind, record = null) => {
        form.resetFields(); setError(null);
        form.setFieldsValue(kind === 'fields' ? { namespace: 'customers', type: 'text', status: 'active', options: [], required: false, sensitive: false, searchable: false, ...record }
            : kind === 'bindings' ? { scope_type: 'website', abilities: ['lookup', 'view'], ...record, _scope_choice: record ? `${record.module_key}:${record.scope_type}:${record.scope_key}` : undefined }
                : { role: 'reader', ...record });
        setEditing({ kind, record });
    };
    const save = async (values) => {
        setBusy(true); setError(null);
        try {
            const { _scope_choice, ...payload } = values;
            await api.command(`${prefix}/${editing.kind}`, { ...payload, ...(editing.record ? { expected_version: editing.record.version } : {}) });
            setEditing(null); await reload(); await onChanged?.();
        } catch (failure) { setError(failure.message); }
        finally { setBusy(false); }
    };
    const migrate = async (commit, cutover = false) => {
        if (cutover && !customerMigrationActions(report).canCutover) { setError('Nhập hết các đợt dữ liệu trước khi bật dùng chung.'); return; }
        setBusy(true); setError(null);
        try {
            const result = await api[commit ? 'command' : 'preview'](`bindings/${customerId(migration)}/migration`, { commit, cutover });
            let next = result.data;
            if (commit && next?.has_more === undefined && next?.replayed) {
                const current = (await api.preview(`bindings/${customerId(migration)}/migration`, { commit: false, cutover: false })).data;
                next = { ...next, ...current, created_count: next.created_count, committed: true, cutover: next.cutover === true, has_more: current.has_more || current.accepted_count > 0 };
            }
            setReport(next); if (commit) { await reload(); await onChanged?.(); }
        } catch (failure) { setError(failure.message); }
        finally { setBusy(false); }
    };
    const appNames = { 'fnb-pos': 'Quản lý quán Cafe', 'retail-pos': 'Quản lý siêu thị & tạp hóa' };
    const abilityNames = { lookup: 'Tra cứu', create: 'Tạo hồ sơ', view: 'Xem hồ sơ', update: 'Cập nhật', attach: 'Gắn giao dịch', history: 'Lịch sử' };
    const scopeNames = { website: 'Website', chain: 'Chuỗi', outlet: 'Điểm bán FNB', branch: 'Chi nhánh Retail', organization: 'Pháp nhân' };
    const typeNames = { text: 'Văn bản', number: 'Số', date: 'Ngày', boolean: 'Có / không', select: 'Lựa chọn' };
    const roleNames = { reader: 'Chỉ xem', editor: 'Biên tập', manager: 'Quản lý' };
    const groupOptions = [
        { key: 'fields', label: 'Trường mở rộng', description: 'Thiết kế thông tin hồ sơ', icon: <SlidersOutlined />, count: fields.length },
        { key: 'bindings', label: 'Kết nối ứng dụng', description: 'Chia sẻ và chuyển đổi dữ liệu', icon: <LinkOutlined />, count: bindings.length },
        { key: 'grants', label: 'Quyền danh bạ', description: 'Quản lý người được truy cập', icon: <TeamOutlined />, count: grants.length },
    ];
    const emptyState = (icon, title, description) => <div className="customers-settings-empty"><span className="customers-settings-empty-icon">{icon}</span><strong>{title}</strong><span>{description}</span></div>;
    const recordName = (grant) => grant.name ?? grant.admin_name ?? grant.admin?.name ?? `Quản trị viên #${grant.admin_id}`;
    return <>
        {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}
        <div className="customers-settings-layout">
            <aside className="customers-settings-sidebar">
                <div className="customers-settings-sidebar-heading"><span>QUẢN TRỊ DANH BẠ</span><strong>{book.name}</strong><small>Thiết lập cách thu thập, chia sẻ và bảo vệ hồ sơ khách hàng.</small></div>
                <nav className="customers-settings-nav" aria-label="Nhóm thiết lập khách hàng">{groupOptions.map((item) => <button type="button" key={item.key} className={`customers-settings-nav-item${section === item.key ? ' is-active' : ''}`} aria-current={section === item.key ? 'page' : undefined} onClick={() => setSection(item.key)}><span className="customers-settings-nav-icon">{item.icon}</span><span className="customers-settings-nav-text"><strong>{item.label}</strong><small>{item.description}</small></span><span className="customers-settings-nav-count">{loading ? '—' : item.count}</span></button>)}</nav>
                <div className="customers-settings-security-note"><SafetyCertificateOutlined /><div><strong>Chia sẻ có kiểm soát</strong><p>Quyền danh bạ kết hợp với quyền hệ thống. Các ứng dụng chỉ nhận phạm vi được cho phép.</p></div></div>
            </aside>
            <div className="customers-settings-content" aria-busy={loading}>
                {section === 'fields' && <Card className="customers-settings-card"><div className="customers-settings-panel-heading"><div><span className="customers-settings-kicker">CẤU TRÚC HỒ SƠ</span><h3>Thông tin phù hợp với doanh nghiệp</h3><p>Bổ sung trường dữ liệu để các ứng dụng cùng sử dụng một cấu trúc thống nhất.</p></div><Button type="primary" icon={<PlusOutlined />} onClick={() => open('fields')}>Thêm trường</Button></div><div className="customers-settings-inline-note"><AuditOutlined /><span>Mã trường được giữ ổn định. Ngừng sử dụng một trường vẫn bảo toàn thông tin đã ghi nhận.</span></div><Table loading={loading} rowKey={(field) => `${field.namespace}.${field.key}`} dataSource={fields} pagination={false} scroll={{ x: 680 }} columns={[
                    { title: 'Tên trường', render: (_, field) => <div className="customers-settings-record"><span className="customers-settings-record-icon"><SlidersOutlined /></span><div><strong>{field.label}</strong><code>{field.namespace}.{field.key}</code></div></div> },
                    { title: 'Kiểu dữ liệu', dataIndex: 'type', render: (value) => <span className="customers-settings-type">{typeNames[value] ?? value}</span> },
                    { title: 'Thuộc tính', render: (_, field) => <Space size={[4, 6]} wrap>{field.required && <Tag>Bắt buộc</Tag>}{field.sensitive && <Tag color="orange" icon={<LockOutlined />}>Riêng tư</Tag>}{field.searchable && <Tag color="cyan">Tra cứu</Tag>}{!field.required && !field.sensitive && !field.searchable && <span className="customers-muted">Tùy chọn</span>}</Space> },
                    { title: 'Trạng thái', dataIndex: 'status', render: (value) => <span className={`customers-settings-status${value === 'active' ? ' is-active' : ''}`}><i />{value === 'active' ? 'Đang dùng' : 'Ngừng dùng'}</span> },
                    { title: '', width: 110, render: (_, field) => <Button size="small" icon={<EditOutlined />} onClick={() => open('fields', field)}>Chỉnh sửa</Button> },
                ]} locale={{ emptyText: emptyState(<SlidersOutlined />, 'Thiết kế hồ sơ theo cách của bạn', 'Thêm trường đầu tiên để lưu thông tin đặc thù mà doanh nghiệp cần theo dõi.') }} /></Card>}
                {section === 'bindings' && <Card className="customers-settings-card"><div className="customers-settings-panel-heading"><div><span className="customers-settings-kicker">HỆ SINH THÁI AIO</span><h3>Một danh bạ, nhiều ứng dụng</h3><p>Kết nối các điểm bán và ứng dụng cần sử dụng chung hồ sơ khách hàng.</p></div><Button type="primary" icon={<PlusOutlined />} onClick={() => open('bindings')}>Kết nối ứng dụng</Button></div><div className="customers-settings-migration-note"><span className="customers-settings-migration-icon"><SwapOutlined /></span><div><strong>Chuyển đổi từng bước, giữ nguyên lịch sử</strong><p>Kiểm tra và nhập hồ sơ nguồn trước khi bật dùng chung. Thông tin khách trên giao dịch cũ được giữ nguyên.</p></div></div><Table loading={loading} rowKey={customerId} dataSource={bindings} pagination={false} scroll={{ x: 740 }} columns={[
                    { title: 'Ứng dụng & phạm vi', render: (_, item) => <div className="customers-settings-record"><span className="customers-settings-record-icon"><AppstoreOutlined /></span><div><strong>{appNames[item.module_key] ?? item.module_key}</strong><span className="customers-muted">{scopeNames[item.scope_type] ?? item.scope_type} · {item.scope_key}</span></div></div> },
                    { title: 'Quyền sử dụng', width: 250, dataIndex: 'abilities', render: (value) => <Space size={[4, 6]} wrap>{customerRows(value).map((ability) => <Tag key={ability}>{abilityNames[ability] ?? ability}</Tag>)}</Space> },
                    { title: 'Chế độ', dataIndex: 'mode', render: (value) => <Tag color={value === 'shared' ? 'green' : 'default'}>{value === 'shared' ? 'Đang dùng chung' : 'Dữ liệu hiện tại'}</Tag> },
                    { title: '', width: 190, render: (_, item) => <Space size={6}><Button size="small" onClick={() => open('bindings', item)}>Cấu hình</Button><Button size="small" icon={<SwapOutlined />} onClick={() => { setReport(null); setError(null); setMigration(item); }}>Chuyển đổi</Button></Space> },
                ]} locale={{ emptyText: emptyState(<LinkOutlined />, 'Kết nối danh bạ với hệ sinh thái', 'Chọn ứng dụng và phạm vi được chia sẻ. Bạn có thể kiểm tra dữ liệu trước khi chuyển sang dùng chung.') }} /></Card>}
                {section === 'grants' && <Card className="customers-settings-card"><div className="customers-settings-panel-heading"><div><span className="customers-settings-kicker">PHÂN QUYỀN TRUY CẬP</span><h3>Đúng người, đúng phạm vi</h3><p>Chủ động quản lý ai có thể xem, biên tập hoặc quản trị danh bạ này.</p></div><Button type="primary" icon={<PlusOutlined />} onClick={() => open('grants')}>Cấp quyền</Button></div><div className="customers-settings-role-guide"><div><strong>Chỉ xem</strong><span>Tra cứu hồ sơ được chia sẻ</span></div><div><strong>Biên tập</strong><span>Cập nhật thông tin khách hàng</span></div><div><strong>Quản lý</strong><span>Quản trị danh bạ theo quyền hệ thống</span></div></div><Table loading={loading} rowKey={(grant) => customerId(grant) || grant.admin_id} dataSource={grants} pagination={false} scroll={{ x: 640 }} columns={[
                    { title: 'Quản trị viên', render: (_, grant) => <div className="customers-settings-record"><span className="customers-settings-person">{recordName(grant).slice(0, 1)}</span><div><strong>{recordName(grant)}</strong><span className="customers-muted">Quản trị viên #{grant.admin_id}</span></div></div> },
                    { title: 'Vai trò', dataIndex: 'role', render: (value) => <Tag color={value === 'manager' ? 'cyan' : 'default'}>{roleNames[value] ?? value}</Tag> },
                    { title: 'Trạng thái', render: (_, grant) => <span className={`customers-settings-status${grant.is_active !== false && grant.is_active !== 0 ? ' is-active' : ''}`}><i />{grant.is_active === false || grant.is_active === 0 ? 'Đã thu hồi' : 'Có hiệu lực'}</span> },
                    { title: '', width: 195, render: (_, grant) => <Space size={6}><Button size="small" icon={<EditOutlined />} onClick={() => open('grants', grant)}>Đổi quyền</Button><Popconfirm title="Thu hồi quyền truy cập danh bạ?" onConfirm={async () => { setBusy(true); try { await api.command(`${prefix}/grants`, { admin_id: grant.admin_id, revoke: true }); await reload(); } catch (failure) { setError(failure.message); } finally { setBusy(false); } }}><Button danger size="small" disabled={busy}>Thu hồi</Button></Popconfirm></Space> },
                ]} locale={{ emptyText: emptyState(<TeamOutlined />, 'Chia sẻ danh bạ với đội ngũ', 'Cấp quyền riêng cho những người cần sử dụng danh bạ. Quyền này luôn kết hợp với quyền hệ thống hiện có.') }} /></Card>}
            </div>
        </div>
        <Drawer className="customers-settings-drawer" width={600} open={Boolean(editing)} title={editing?.kind === 'fields' ? 'Cấu hình trường mở rộng' : editing?.kind === 'bindings' ? 'Kết nối ứng dụng' : 'Cấp quyền danh bạ'} onClose={() => !busy && setEditing(null)} maskClosable={false} footer={<div className="customers-settings-drawer-footer"><Button disabled={busy} onClick={() => setEditing(null)}>Hủy</Button><Button type="primary" loading={busy} onClick={() => form.submit()}>Lưu cấu hình</Button></div>}>
            <div className="customers-settings-editor-intro"><span>{editing?.kind === 'fields' ? <SlidersOutlined /> : editing?.kind === 'bindings' ? <LinkOutlined /> : <TeamOutlined />}</span><div><strong>{editing?.kind === 'fields' ? 'Một cấu trúc thống nhất cho mọi ứng dụng' : editing?.kind === 'bindings' ? 'Chia sẻ đúng ứng dụng, đúng phạm vi' : 'Trao quyền phù hợp với trách nhiệm'}</strong><p>{editing?.kind === 'fields' ? 'Đặt mã ổn định và xác định cách thông tin được nhập, tra cứu và bảo vệ.' : editing?.kind === 'bindings' ? 'Kết nối này xác định ứng dụng nào được sử dụng danh bạ và các thao tác được cho phép.' : 'Quyền danh bạ luôn kết hợp với quyền hệ thống hiện có của quản trị viên.'}</p></div></div>
            {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}
            <Form form={form} layout="vertical" disabled={busy} onFinish={save}>
                {editing?.kind === 'fields' ? <>
                    <div className="customers-settings-form-section"><div className="customers-settings-form-section-heading"><span>01</span><h4>Thông tin trường</h4></div><div className="customers-settings-form-grid"><Form.Item name="namespace" label="Nhóm trường" rules={[{ required: true }]}><Input disabled={Boolean(editing.record)} maxLength={80} /></Form.Item><Form.Item name="key" label="Mã trường" rules={[{ required: true }, { pattern: /^[a-z][a-z0-9_]*$/, message: 'Dùng chữ thường, số và dấu gạch dưới; bắt đầu bằng chữ.' }]}><Input disabled={Boolean(editing.record)} maxLength={80} /></Form.Item></div><Form.Item name="label" label="Tên hiển thị" rules={[{ required: true }]}><Input maxLength={150} placeholder="Ví dụ: Hạng khách hàng" /></Form.Item><Form.Item name="type" label="Kiểu dữ liệu" rules={[{ required: true }]}><Select disabled={Boolean(editing.record)} options={[{ value: 'text', label: 'Văn bản' }, { value: 'number', label: 'Số' }, { value: 'date', label: 'Ngày' }, { value: 'boolean', label: 'Có / không' }, { value: 'select', label: 'Lựa chọn' }]} /></Form.Item><Form.Item noStyle shouldUpdate>{({ getFieldValue }) => getFieldValue('type') === 'select' && <Form.Item name="options" label="Các lựa chọn"><Select mode="tags" tokenSeparators={[',']} placeholder="Nhập lựa chọn rồi nhấn Enter" /></Form.Item>}</Form.Item></div>
                    <div className="customers-settings-form-section"><div className="customers-settings-form-section-heading"><span>02</span><h4>Quy tắc sử dụng</h4></div><div className="customers-settings-field-rules"><Form.Item name="required" label="Bắt buộc" valuePropName="checked"><Switch /></Form.Item><Form.Item name="sensitive" label="Dữ liệu riêng tư" valuePropName="checked"><Switch /></Form.Item><Form.Item name="searchable" label="Cho phép tra cứu" valuePropName="checked"><Switch /></Form.Item></div><Form.Item name="status" label="Trạng thái"><Select options={[{ value: 'active', label: 'Đang dùng' }, { value: 'archived', label: 'Ngừng dùng' }]} /></Form.Item></div>
                </> : editing?.kind === 'bindings' ? <>
                    <div className="customers-settings-form-section"><div className="customers-settings-form-section-heading"><span>01</span><h4>Ứng dụng và phạm vi</h4></div>
                        {customerRows(settingsOptions.scopes).length ? <><Form.Item name="_scope_choice" label="Ứng dụng và phạm vi" rules={[{ required: true }]}><Select disabled={Boolean(editing.record)} showSearch optionFilterProp="label" options={customerRows(settingsOptions.scopes).map((scope) => ({ value: `${scope.module_key}:${scope.scope_type}:${scope.scope_key}`, label: scope.label ?? `${appNames[scope.module_key] ?? scope.module_key} · ${scope.scope_key}` }))} onChange={(value) => { const chosen = customerRows(settingsOptions.scopes).find((scope) => `${scope.module_key}:${scope.scope_type}:${scope.scope_key}` === value); if (chosen) form.setFieldsValue({ module_key: chosen.module_key, scope_type: chosen.scope_type, scope_key: chosen.scope_key }); }} /></Form.Item><Form.Item name="module_key" hidden><Input /></Form.Item><Form.Item name="scope_type" hidden><Input /></Form.Item><Form.Item name="scope_key" hidden><Input /></Form.Item></> : <><Form.Item name="module_key" label="Ứng dụng" rules={[{ required: true }]}><Select disabled={Boolean(editing.record)} options={[{ value: 'fnb-pos', label: appNames['fnb-pos'] }, { value: 'retail-pos', label: appNames['retail-pos'] }]} /></Form.Item><Form.Item name="scope_type" label="Loại phạm vi" rules={[{ required: true }]}><Select disabled={Boolean(editing.record)} options={['website', 'chain'].map((value) => ({ value, label: scopeNames[value] }))} /></Form.Item><Form.Item name="scope_key" label="Mã định danh phạm vi" rules={[{ required: true }]} extra="Chọn đúng định danh website, chuỗi, điểm bán hoặc pháp nhân cần kết nối."><Input disabled={Boolean(editing.record)} maxLength={120} /></Form.Item></>}
                    </div><div className="customers-settings-form-section"><div className="customers-settings-form-section-heading"><span>02</span><h4>Quyền của ứng dụng</h4></div><Form.Item name="abilities" label="Cho phép ứng dụng" rules={[{ required: true }]}><Select mode="multiple" options={['lookup', 'create', 'view', 'update', 'attach', 'history'].map((value) => ({ value, label: abilityNames[value] }))} /></Form.Item><p className="customers-settings-form-caption">Chỉ cấp những thao tác ứng dụng cần sử dụng. Dữ liệu giao dịch tiếp tục được quản lý tại ứng dụng nguồn.</p></div>
                </> : <div className="customers-settings-form-section"><div className="customers-settings-form-section-heading"><span>01</span><h4>Người được chia sẻ</h4></div><Form.Item name="admin_id" label="Quản trị viên" rules={[{ required: true }]}>{customerRows(settingsOptions.admins).length ? <Select disabled={Boolean(editing?.record)} showSearch optionFilterProp="label" placeholder="Chọn quản trị viên" options={customerRows(settingsOptions.admins).map((admin) => ({ value: admin.id, label: admin.name }))} /> : <InputNumber min={1} precision={0} disabled={Boolean(editing?.record)} style={{ width: '100%' }} />}</Form.Item><Form.Item name="role" label="Vai trò" rules={[{ required: true }]}><Select options={[{ value: 'reader', label: 'Chỉ xem' }, { value: 'editor', label: 'Biên tập hồ sơ' }, { value: 'manager', label: 'Quản lý danh bạ' }]} /></Form.Item></div>}
            </Form>
        </Drawer>
        <Modal className="customers-settings-migration-modal" open={Boolean(migration)} width={760} title="Kiểm tra và chuyển đổi dữ liệu" onCancel={() => !busy && setMigration(null)} footer={<Space wrap><Button disabled={busy} onClick={() => setMigration(null)}>Đóng</Button><Button loading={busy} onClick={() => migrate(false)}>Kiểm tra trước</Button><Button disabled={!customerMigrationActions(report).canImport || busy} onClick={() => migrate(true)}>{report?.has_more ? 'Nhập đợt tiếp theo' : 'Nhập hồ sơ nguồn'}</Button><Popconfirm title="Bật dùng chung cho ứng dụng này?" description="Kiểm tra dữ liệu và nhập thành công trước khi xác nhận. Hệ thống sẽ kiểm tra điều kiện chuyển đổi." onConfirm={() => migrate(true, true)}><Button type="primary" disabled={!customerMigrationActions(report).canCutover || busy}>Bật dùng chung</Button></Popconfirm></Space>} maskClosable={false}>
            <Alert type="warning" showIcon message={`${appNames[migration?.module_key] ?? migration?.module_key ?? ''} · ${migration?.scope_key ?? ''}`} description="Mỗi đợt xử lý tối đa 500 hồ sơ nguồn. Nhập tiếp các đợt cho đến khi hết dữ liệu rồi mới bật dùng chung. Hồ sơ giữ liên kết nguồn để đối chiếu." style={{ marginBottom: 16 }} />
            {!report && <ol className="customers-settings-migration-steps"><li><span>1</span><div><strong>Kiểm tra nguồn</strong><small>Xem trước dữ liệu và các trường hợp cần đối chiếu.</small></div></li><li><span>2</span><div><strong>Nhập hồ sơ</strong><small>Xử lý lần lượt các đợt đến khi hết dữ liệu nguồn.</small></div></li><li><span>3</span><div><strong>Bật dùng chung</strong><small>Ứng dụng chuyển sang sử dụng danh bạ đã liên kết.</small></div></li></ol>}
            {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}{report && <>{report.has_more && <Alert type="info" showIcon message="Còn dữ liệu nguồn ở các đợt tiếp theo" description="Tiếp tục Nhập đợt tiếp theo. Chỉ bật dùng chung khi đã xử lý hết dữ liệu nguồn." style={{ marginBottom: 16 }} />}{report.cutover && <Alert type="success" showIcon message="Ứng dụng đã dùng chung danh bạ khách hàng." style={{ marginBottom: 16 }} />}<Descriptions bordered column={2} items={Object.entries(report).filter(([, value]) => ['string', 'number', 'boolean'].includes(typeof value)).map(([key, value]) => ({ key, label: ({ valid: 'Hợp lệ', row_count: 'Số dòng', created_count: 'Đã tạo', imported_count: 'Đã nhập', linked_count: 'Đã liên kết', committed: 'Đã ghi nhận', cutover: 'Dùng chung', source_count: 'Hồ sơ nguồn', matched_count: 'Đã khớp', status: 'Trạng thái', has_more: 'Còn đợt tiếp theo', existing_count: 'Đã có liên kết', accepted_count: 'Có thể nhập đợt này' })[key] ?? key, children: typeof value === 'boolean' ? value ? 'Có' : 'Không' : String(value) }))} /><Table style={{ marginTop: 16 }} rowKey={(item, index) => item.row ?? item.source_id ?? index} dataSource={customerRows(report.errors ?? report.conflicts)} pagination={{ pageSize: 10 }} columns={[{ title: 'Nguồn / dòng', render: (_, item) => item.row ?? item.source_id ?? '—' }, { title: 'Cần xử lý', render: (_, item) => item.message ?? Object.values(item.errors ?? {}).flat().join(' · ') }]} locale={{ emptyText: 'Không có lỗi được báo trong kết quả.' }} />{customerRows(report.warnings).length > 0 && <><Typography.Title level={5}>Liên hệ trùng cần đối chiếu</Typography.Title><Alert type="warning" showIcon message="Các hồ sơ này được tạo riêng, không tự hợp nhất" description="Nhập hoàn tất rồi kiểm tra mục Hồ sơ trùng để xác nhận danh tính." style={{ marginBottom: 12 }} /><Table rowKey={(item) => `${item.source_id}:${item.field}`} dataSource={customerRows(report.warnings)} pagination={{ pageSize: 10 }} columns={[{ title: 'Định danh nguồn', dataIndex: 'source_id' }, { title: 'Thông tin trùng', dataIndex: 'field', render: (value) => value === 'phone' ? 'Điện thoại' : value === 'email' ? 'Email' : value }, { title: 'Cách xử lý', render: () => 'Tạo hồ sơ riêng để đối chiếu' }]} /></>}</>}
        </Modal>
    </>;
}
