import { useState } from 'react';
import ApartmentOutlined from '@ant-design/icons/ApartmentOutlined';
import BankOutlined from '@ant-design/icons/BankOutlined';
import BuildOutlined from '@ant-design/icons/BuildOutlined';
import ClockCircleOutlined from '@ant-design/icons/ClockCircleOutlined';
import DatabaseOutlined from '@ant-design/icons/DatabaseOutlined';
import FileSearchOutlined from '@ant-design/icons/FileSearchOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import TeamOutlined from '@ant-design/icons/TeamOutlined';
import Button from 'antd/es/button';
import Input from 'antd/es/input';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import { commands } from '../utils/manufacturingForms.js';
import '../styles/manufacturing-settings.css';

const id = (row) => row?.public_id ?? row?.id;
const same = (left, right) => String(left ?? '') === String(right ?? '');
const rows = (value) => Array.isArray(value) ? value : [];
const format = (value) => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(value) || 0);
const date = (value) => value && !Number.isNaN(Date.parse(value)) ? new Date(value).toLocaleString('vi-VN') : 'Không giới hạn';
const states = { active: 'Hoạt động', inactive: 'Vô hiệu hóa', pending: 'Chờ xử lý', processing: 'Đang xử lý', committed: 'Đã ghi nhận', delivered: 'Đã chuyển', failed: 'Lỗi', retry: 'Chờ thử lại' };
const stateTag = (value) => <Tag color={['active', 'committed', 'delivered'].includes(value) ? 'green' : ['failed', 'inactive'].includes(value) ? 'orange' : 'blue'}>{states[value] ?? value ?? '—'}</Tag>;

function Blank({ icon: Icon = ApartmentOutlined, title, description }) {
    return <div className="mfg-settings-empty"><span><Icon /></span><strong>{title}</strong><p>{description}</p></div>;
}

function Panel({ title, subtitle, icon: Icon, action, children, className = '' }) {
    return <section className={`mfg-settings-card ${className}`}>
        <div className="mfg-settings-card-heading"><div className="mfg-settings-card-title"><span className="mfg-settings-section-icon"><Icon /></span><div><h4>{title}</h4>{subtitle && <p>{subtitle}</p>}</div></div>{action && <div className="mfg-settings-card-actions">{action}</div>}</div>
        <div className="mfg-settings-card-content">{children}</div>
    </section>;
}

function DataTable({ data, columns, empty }) {
    return <Table size="small" rowKey={(row, index) => id(row) ?? row.event_id ?? index} dataSource={rows(data)} columns={columns} scroll={{ x: 540 }} pagination={{ pageSize: 10, hideOnSinglePage: true, showSizeChanger: false }} locale={{ emptyText: <div className="mfg-settings-table-empty">{empty ?? 'Chưa có dữ liệu.'}</div> }} />;
}

export default function ManufacturingSettings({ data, bootstrap, factoryId, can, paused, loading, onOpen, onSelectFactory }) {
    const [tab, setTab] = useState('organization');
    const [factorySearch, setFactorySearch] = useState('');
    const companies = rows(data?.companies ?? bootstrap?.companies);
    const factories = rows(data?.factories ?? bootstrap?.factories);
    const factory = factories.find((item) => same(id(item), factoryId));
    const company = companies.find((item) => same(id(item), factory?.company_id));
    const inFactory = (item) => Boolean(factoryId) && same(item.factory_id, factoryId);
    const workshops = rows(data?.workshops ?? bootstrap?.workshops).filter(inFactory);
    const lines = rows(data?.lines ?? bootstrap?.lines).filter(inFactory);
    const shifts = rows(data?.shifts ?? bootstrap?.shifts).filter(inFactory);
    const members = factoryId ? rows(data?.memberships) : [];
    const visibleFactories = factories.filter((item) => `${item.code ?? ''} ${item.name ?? ''}`.toLocaleLowerCase('vi').includes(factorySearch.toLocaleLowerCase('vi').trim()));
    const workshopIds = new Set(workshops.map((item) => String(id(item))));
    const otherLines = lines.filter((item) => !workshopIds.has(String(item.workshop_id)));
    const runtimeKnown = Boolean(bootstrap?.runtime);
    const runtimeLabel = !runtimeKnown ? 'Chưa xác định trạng thái' : paused ? 'Tạm dừng ghi dữ liệu' : 'Đang vận hành';
    const create = (operation, label, primary = false, initial) => can(operation) ? <Button aria-label={label} icon={<PlusOutlined />} type={primary ? 'primary' : 'default'} disabled={paused} onClick={() => onOpen(operation, null, initial ? { initial } : {})}>{label}</Button> : null;
    const lineList = (items) => <div className="mfg-settings-line-list">{items.map((line) => <div className="mfg-settings-line" key={id(line)}><span className="mfg-settings-line-marker" /><div><strong>{line.name}</strong><small>{line.code}</small></div><span className="mfg-settings-capacity">{format(line.capacity_minutes)}<small>phút / ca</small></span></div>)}</div>;
    const scopeNote = factory ? `Dữ liệu tại ${factory.name}` : 'Chọn nhà máy để xem chi tiết';

    const organization = <div className="mfg-settings-organization">
        <aside className="mfg-settings-entities">
            <Panel title="Doanh nghiệp" icon={BankOutlined} action={create('company.create', 'Tạo công ty')}>
                {companies.length ? <div className="mfg-settings-company-list">{companies.map((item) => <div key={id(item)} className={`mfg-settings-company${same(id(item), factory?.company_id) ? ' is-current' : ''}`}><small>{item.code}</small><strong>{item.name}</strong></div>)}</div> : <Blank icon={BankOutlined} title="Chưa có công ty" description="Tạo công ty để bắt đầu tổ chức hệ thống sản xuất." />}
            </Panel>
            <Panel title="Nhà máy" icon={BuildOutlined} action={create('factory.create', 'Tạo nhà máy', false, company ? { company_id: id(company) } : undefined)}>
                {factories.length > 5 && <Input.Search aria-label="Tìm nhà máy" placeholder="Tìm mã hoặc tên nhà máy" value={factorySearch} onChange={(event) => setFactorySearch(event.target.value)} allowClear />}
                {factories.length ? <div className="mfg-settings-factory-list">{visibleFactories.map((item) => <button type="button" key={id(item)} className={`mfg-settings-factory${same(id(item), factoryId) ? ' is-selected' : ''}`} aria-pressed={same(id(item), factoryId)} onClick={() => onSelectFactory(id(item))}><span className="mfg-settings-factory-symbol"><BuildOutlined /></span><span><strong>{item.name}</strong><small>{item.code}</small></span>{same(id(item), factoryId) && <span className="mfg-settings-selected-dot" />}</button>)}{!visibleFactories.length && <p className="mfg-settings-muted">Không tìm thấy nhà máy phù hợp.</p>}</div> : <Blank icon={BuildOutlined} title="Chưa có nhà máy" description="Thêm nhà máy trực thuộc công ty để quản lý phân xưởng và chuyền." />}
            </Panel>
        </aside>
        <div className="mfg-settings-structure">
            <Panel title="Phân xưởng & chuyền" subtitle="Cơ cấu sản xuất tại nhà máy đang chọn" icon={ApartmentOutlined} action={factoryId && <>{create('workshop.create', 'Tạo phân xưởng', true)}{create('line.create', 'Tạo chuyền')}</>}>
                {!factoryId ? <Blank title="Chọn nhà máy để thiết lập cơ cấu" description="Chọn một nhà máy trong danh sách bên trái hoặc tạo nhà máy mới." /> : workshops.length || lines.length ? <>
                    <div className="mfg-settings-workshops">{workshops.map((workshop) => {
                        const children = lines.filter((line) => same(line.workshop_id, id(workshop)));
                        return <article className="mfg-settings-workshop" key={id(workshop)}><div className="mfg-settings-workshop-heading"><span className="mfg-settings-workshop-icon"><ApartmentOutlined /></span><div><h5>{workshop.name}</h5><small>{workshop.code}</small></div><span className="mfg-settings-count">{children.length} chuyền</span></div>{children.length ? lineList(children) : <p className="mfg-settings-no-lines">Chưa có chuyền trong phân xưởng này.</p>}</article>;
                    })}</div>
                    {otherLines.length > 0 && <div className="mfg-settings-other-lines"><h5>Chuyền khác</h5>{lineList(otherLines)}</div>}
                </> : <Blank title="Sẵn sàng thiết lập phân xưởng" description="Tạo phân xưởng, sau đó thêm các chuyền may để tổ chức kế hoạch và theo dõi năng lực sản xuất." />}
            </Panel>
            <div className="mfg-settings-guide"><SafetyCertificateOutlined /><div><strong>Thiết lập theo cơ cấu thực tế</strong><p>Công ty → Nhà máy → Phân xưởng → Chuyền. Phân quyền theo phạm vi để từng bộ phận làm việc đúng nhà máy được giao.</p></div></div>
        </div>
    </div>;

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
                { title: 'Thao tác', dataIndex: 'operation', render: (value) => commands[value]?.title ?? value }, { title: 'Trạng thái', dataIndex: 'status', render: stateTag }, { title: 'Thời điểm', dataIndex: 'created_at', render: (value) => value ? date(value) : '—' },
            ]} />
        </Panel>
        <Panel title="Sự kiện tích hợp" subtitle="Theo dõi trạng thái chuyển giao dữ liệu giữa các ứng dụng" icon={DatabaseOutlined}>
            <DataTable data={data?.operations?.outbox} empty="Chưa có sự kiện tích hợp trong phạm vi đang chọn." columns={[
                { title: 'Sự kiện', dataIndex: 'event_type' }, { title: 'Đích', dataIndex: 'destination' }, { title: 'Trạng thái', dataIndex: 'status', render: stateTag }, { title: 'Số lần thử', dataIndex: 'attempts' }, { title: 'Lần xử lý kế tiếp', dataIndex: 'available_at', render: (value) => value ? date(value) : '—' }, { title: 'Lỗi gần nhất', dataIndex: 'last_error', ellipsis: true },
            ]} />
        </Panel>
    </div>;

    return <div className="mfg-settings">
        <div className="mfg-settings-context"><div className="mfg-settings-context-icon"><BuildOutlined /></div><div className="mfg-settings-context-copy"><small>NHÀ MÁY ĐANG QUẢN LÝ</small><h4>{factory?.name ?? 'Chưa chọn nhà máy'}</h4><p>{factory ? [factory.code, company?.name, factory.timezone].filter(Boolean).join(' · ') : 'Tạo công ty và nhà máy để bắt đầu thiết lập hệ thống.'}</p></div><span className={`mfg-settings-health${paused ? ' is-paused' : ''}${!runtimeKnown ? ' is-unknown' : ''}`}><span className="mfg-settings-status-dot" />{runtimeLabel}</span></div>
        <div className="mfg-settings-metrics">{[
            [BuildOutlined, factories.length, 'Nhà máy được cấp', 'Trong phạm vi quản lý'],
            [ApartmentOutlined, factoryId ? workshops.length : '—', 'Phân xưởng', 'Tại nhà máy đang chọn'],
            [DatabaseOutlined, factoryId ? lines.length : '—', 'Chuyền sản xuất', 'Tại nhà máy đang chọn'],
            [ClockCircleOutlined, factoryId ? shifts.length : '—', 'Ca làm việc', 'Tại nhà máy đang chọn'],
        ].map(([Icon, value, label, hint]) => <div className="mfg-settings-metric" key={label}><span><Icon /></span><div><strong>{value}</strong><h5>{label}</h5><small>{hint}</small></div></div>)}</div>
        <div className="mfg-settings-tabs"><Tabs activeKey={tab} onChange={setTab} tabBarExtraContent={<span className="mfg-settings-scope-note">{scopeNote}</span>} items={[
            { key: 'organization', label: <><ApartmentOutlined /> Cơ cấu tổ chức</>, children: organization },
            { key: 'people', label: <><TeamOutlined /> Nhân sự & quyền</>, children: people },
            { key: 'catalog', label: <><ClockCircleOutlined /> Ca & danh mục</>, children: catalog },
            { key: 'logs', label: <><FileSearchOutlined /> Nhật ký</>, children: logs },
        ]} /></div>
    </div>;
}
