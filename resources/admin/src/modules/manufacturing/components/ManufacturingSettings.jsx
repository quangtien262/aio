import { useState } from 'react';
import ApartmentOutlined from '@ant-design/icons/ApartmentOutlined';
import ClockCircleOutlined from '@ant-design/icons/ClockCircleOutlined';
import DatabaseOutlined from '@ant-design/icons/DatabaseOutlined';
import FileSearchOutlined from '@ant-design/icons/FileSearchOutlined';
import InfoCircleOutlined from '@ant-design/icons/InfoCircleOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import TeamOutlined from '@ant-design/icons/TeamOutlined';
import Button from 'antd/es/button';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Tooltip from 'antd/es/tooltip';
import { manufacturingOperationLabel } from '../utils/manufacturingForms.js';
import ManufacturingOrganization from './ManufacturingOrganization.jsx';
import { manufacturingOrganization } from '../utils/manufacturingOrganization.js';
import '../styles/manufacturing-settings.css';

const id = (row) => row?.public_id ?? row?.id;
const same = (left, right) => String(left ?? '') === String(right ?? '');
const rows = (value) => Array.isArray(value) ? value : [];
const date = (value) => value && !Number.isNaN(Date.parse(value)) ? new Date(value).toLocaleString('vi-VN') : 'Không giới hạn';
const states = { active: 'Hoạt động', inactive: 'Vô hiệu hóa', pending: 'Chờ xử lý', processing: 'Đang xử lý', committed: 'Đã ghi nhận', delivered: 'Đã chuyển', failed: 'Lỗi', retry: 'Chờ thử lại' };
const stateTag = (value) => <Tag color={['active', 'committed', 'delivered'].includes(value) ? 'green' : ['failed', 'inactive'].includes(value) ? 'orange' : 'blue'}>{states[value] ?? value ?? '—'}</Tag>;

function Blank({ icon: Icon = ApartmentOutlined, title, description }) {
    return <div className="mfg-settings-empty"><span><Icon /></span><strong>{title}</strong><p>{description}</p></div>;
}

function Panel({ title, subtitle, help, icon: Icon, action, children, className = '' }) {
    return <section className={`mfg-settings-card ${className}`}>
        <div className="mfg-settings-card-heading"><div className="mfg-settings-card-title"><span className="mfg-settings-section-icon"><Icon /></span><div><h4>{title}{help && <Tooltip title={help} trigger={['hover', 'focus', 'click']}><button type="button" className="mfg-settings-title-help" aria-label={`Thông tin về ${title}`}><InfoCircleOutlined /></button></Tooltip>}</h4>{subtitle && <p>{subtitle}</p>}</div></div>{action && <div className="mfg-settings-card-actions">{action}</div>}</div>
        <div className="mfg-settings-card-content">{children}</div>
    </section>;
}

function DataTable({ data, columns, empty }) {
    return <Table size="small" rowKey={(row, index) => id(row) ?? row.event_id ?? index} dataSource={rows(data)} columns={columns} scroll={{ x: 540 }} pagination={{ pageSize: 10, hideOnSinglePage: true, showSizeChanger: false }} locale={{ emptyText: <div className="mfg-settings-table-empty">{empty ?? 'Chưa có dữ liệu.'}</div> }} />;
}

export default function ManufacturingSettings({ data, bootstrap, factoryId, can, paused, loading, onOpen, onSelectFactory }) {
    const [tab, setTab] = useState('organization');
    const companies = rows(data?.companies ?? bootstrap?.companies);
    const factories = rows(data?.factories ?? bootstrap?.factories);
    const factory = factories.find((item) => same(id(item), factoryId));
    const inFactory = (item) => Boolean(factoryId) && same(item.factory_id, factoryId);
    const allWorkshops = rows(data?.workshops ?? bootstrap?.workshops);
    const allLines = rows(data?.lines ?? bootstrap?.lines);
    const organizationGroups = manufacturingOrganization(factories, allWorkshops, allLines);
    const shifts = rows(data?.shifts ?? bootstrap?.shifts).filter(inFactory);
    const members = factoryId ? rows(data?.memberships) : [];
    const runtimeKnown = Boolean(bootstrap?.runtime);
    const runtimeLabel = !runtimeKnown ? 'Chưa xác định trạng thái' : paused ? 'Tạm dừng ghi dữ liệu' : 'Đang vận hành';
    const create = (operation, label, primary = false, initial) => can(operation) ? <Button aria-label={label} icon={<PlusOutlined />} type={primary ? 'primary' : 'default'} disabled={paused} onClick={() => onOpen(operation, null, initial ? { initial } : {})}>{label}</Button> : null;
    const scopeNote = factory ? `Dữ liệu tại ${factory.name}` : 'Chọn nhà máy để xem chi tiết';

    const organization = <ManufacturingOrganization companies={companies} groups={organizationGroups} factoryId={factoryId} can={can} paused={paused} create={create} onOpen={onOpen} onSelectFactory={onSelectFactory} />;

    const people = <Panel title="Thành viên & phân quyền" subtitle="Quản lý quyền toàn công ty và quyền tại nhà máy đang chọn" icon={TeamOutlined} action={factoryId && create('membership.save', 'Phân quyền', true, { company_id: factory?.company_id, factory_id: factoryId })}>
        {!factoryId ? <Blank icon={TeamOutlined} title="Chọn nhà máy để xem thành viên" description="Danh sách thành viên được hiển thị theo phạm vi nhà máy được chọn." /> : <DataTable data={members} empty={loading ? 'Đang tải danh sách thành viên…' : 'Chưa có thành viên trong phạm vi này.'} columns={[
            { title: 'Thành viên', key: 'member', render: (_, row) => <div className="mfg-settings-person"><span>{(row.admin_name ?? '#').trim().charAt(0).toLocaleUpperCase('vi')}</span><div><strong>{row.admin_name ?? `Tài khoản #${row.admin_id}`}</strong><small>ID {row.admin_id}</small></div></div> },
            { title: 'Vai trò', dataIndex: 'role', render: (value) => <Tag color={value === 'manager' ? 'purple' : value === 'operator' ? 'blue' : 'default'}>{{ manager: 'Quản lý', operator: 'Vận hành', reader: 'Chỉ xem' }[value] ?? value}</Tag> },
            { title: 'Phạm vi', key: 'scope', render: (_, row) => row.factory_id ? factory?.name ?? 'Nhà máy' : 'Toàn công ty' },
            { title: 'Trạng thái', dataIndex: 'status', render: stateTag },
            { title: 'Hết hạn', dataIndex: 'expires_at', render: date },
        ]} />}
    </Panel>;

    const catalog = <div className="mfg-settings-catalog">
        <Panel title="Ca làm việc" subtitle="Khung giờ làm việc tại nhà máy đang chọn" icon={ClockCircleOutlined} action={factoryId && create('shift.create', 'Tạo ca', true)}>
            <DataTable data={shifts} empty={factoryId ? 'Chưa có ca làm việc. Tạo ca để hoàn thiện lịch vận hành.' : 'Chọn nhà máy để xem ca làm việc.'} columns={[
                { title: 'Mã ca', dataIndex: 'code' }, { title: 'Tên ca', dataIndex: 'name' }, { title: 'Bắt đầu', dataIndex: 'starts_at' }, { title: 'Kết thúc', dataIndex: 'ends_at' },
            ]} />
        </Panel>
        <div className="mfg-settings-catalog-side">
            <Panel title="Nguyên phụ liệu" subtitle="Danh mục dùng chung trong công ty" icon={DatabaseOutlined} action={factoryId && create('sku.save', 'Tạo mã nguyên liệu')}>
                <p className="mfg-settings-muted">Tạo mã nguyên phụ liệu thống nhất để sử dụng khi lập định mức, mua hàng và quản lý tồn kho.</p>
                {Array.isArray(data?.skus) && <DataTable data={data.skus} columns={[{ title: 'Mã vật tư', dataIndex: 'sku' }, { title: 'Tên', dataIndex: 'name' }, { title: 'Đơn vị', dataIndex: 'base_unit' }]} />}
            </Panel>
            <Panel title="Điều khiển vận hành" icon={SafetyCertificateOutlined}>
                <div className={`mfg-settings-runtime${paused ? ' is-paused' : ''}${!runtimeKnown ? ' is-unknown' : ''}`}><span className="mfg-settings-status-dot" /><strong>{runtimeLabel}</strong></div>
                <p className="mfg-settings-muted">Tạm dừng ghi dữ liệu áp dụng cho toàn app khi cần bảo trì hoặc đối chiếu. Các màn hình vẫn cho phép xem dữ liệu.</p>
                {can(paused ? 'runtime.resume' : 'runtime.pause') && <Button block danger={!paused} type={paused ? 'primary' : 'default'} onClick={() => onOpen(paused ? 'runtime.resume' : 'runtime.pause')}>{paused ? 'Tiếp tục vận hành' : 'Tạm dừng ghi dữ liệu'}</Button>}
            </Panel>
        </div>
    </div>;

    const logs = <div className="mfg-settings-logs">
        <Panel title="Giao dịch gần đây" subtitle="Các giao dịch của tài khoản tại nhà máy đang chọn" icon={FileSearchOutlined}>
            <DataTable data={data?.operations?.commands} empty={factoryId ? 'Chưa có giao dịch được hiển thị.' : 'Chọn nhà máy để xem nhật ký.'} columns={[
                { title: 'Thao tác', dataIndex: 'operation', render: manufacturingOperationLabel }, { title: 'Người tạo', dataIndex: 'actor_name', render: (value, row) => String(value ?? '').trim() || (row.actor_id ? `Tài khoản #${row.actor_id}` : 'Không xác định') }, { title: 'Trạng thái', dataIndex: 'status', render: stateTag }, { title: 'Thời điểm', dataIndex: 'created_at', render: (value) => value ? date(value) : '—' },
            ]} />
        </Panel>
        <Panel title="Sự kiện tích hợp" subtitle="Theo dõi trạng thái chuyển giao dữ liệu giữa các ứng dụng" icon={DatabaseOutlined}>
            <DataTable data={data?.operations?.outbox} empty="Chưa có sự kiện tích hợp trong phạm vi đang chọn." columns={[
                { title: 'Sự kiện', dataIndex: 'event_type', render: manufacturingOperationLabel }, { title: 'Đích', dataIndex: 'destination' }, { title: 'Trạng thái', dataIndex: 'status', render: stateTag }, { title: 'Số lần thử', dataIndex: 'attempts' }, { title: 'Lần xử lý kế tiếp', dataIndex: 'available_at', render: (value) => value ? date(value) : '—' }, { title: 'Lỗi gần nhất', dataIndex: 'last_error', ellipsis: true },
            ]} />
        </Panel>
    </div>;

    return <div className="mfg-settings">
        <div className="mfg-settings-tabs"><Tabs activeKey={tab} onChange={setTab} tabBarExtraContent={tab === 'organization' ? null : <span className="mfg-settings-scope-note">{scopeNote}</span>} items={[
            { key: 'organization', label: <><ApartmentOutlined /> Cơ cấu tổ chức</>, children: organization },
            { key: 'people', label: <><TeamOutlined /> Nhân sự & quyền</>, children: people },
            { key: 'catalog', label: <><ClockCircleOutlined /> Ca & danh mục</>, children: catalog },
            { key: 'logs', label: <><FileSearchOutlined /> Nhật ký</>, children: logs },
        ]} /></div>
    </div>;
}
