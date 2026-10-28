import { useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Descriptions from 'antd/es/descriptions';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Typography from 'antd/es/typography';
import { useNavigate } from 'react-router-dom';
import { CapabilityCard, PharmacyError, PharmacyTable, StatusTag } from '../components/PharmacyCommon';
import usePharmacyResource from '../hooks/usePharmacyResource';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';
import { dateLabel, money, recordId } from '../utils/pharmacyData';

const capabilities = [
    ['controlled', 'Thuốc kiểm soát đặc biệt', 'Điều kiện cơ sở, nhân sự, hồ sơ và sổ theo từng nhóm.'],
    ['ecommerce', 'Kênh online & chăm sóc', 'Kênh hợp lệ, giữ tồn đa kênh, giao nhận và mục đích liên hệ.'],
    ['cold_chain', 'Chuỗi lạnh & IoT', 'Bảo quản, bằng chứng nhiệt độ, hiệu chuẩn và xử lý sai lệch.'],
    ['clinical_knowledge', 'Kiến thức chuyên môn', 'Nguồn có quyền sử dụng, phiên bản và nội dung được người có trách nhiệm duyệt.'],
];
export default function PharmacyOperationsPage({ section }) {
    const { api, pharmacyId, pharmacy, bootstrap, revision, can, openCommand } = usePharmacyWorkspace();
    const navigate = useNavigate();
    const kind = section === 'operations' ? 'reports' : section;
    const [from, setFrom] = useState(''); const [to, setTo] = useState('');
    const resource = usePharmacyResource(api, kind, { from, to }, Boolean(pharmacyId), revision);
    if (section === 'settings') return <div className="ph-settings-grid"><Card title="Tác nghiệp tại nhà thuốc"><Descriptions column={1} items={[
        { key: 'mode', label: 'Chế độ', children: (pharmacy?.mode ?? pharmacy?.settings?.workflow_mode) === 'separated_queue' ? 'Hàng đợi theo bước' : 'Quầy đơn / theo chính sách máy chủ' },
        { key: 'scope', label: 'Phạm vi', children: 'Native chain / nhà thuốc / kho / quầy' },
    ]} /><Space wrap>{can('settings.save') && <Button onClick={() => openCommand('settings.save', pharmacy, {}, { initial: { ...pharmacy?.settings, license_number: pharmacy?.license_number } })}>Chế độ vận hành</Button>}{can('pharmacy.create') && <Button onClick={() => openCommand('pharmacy.create')}>Thêm nhà thuốc</Button>}</Space></Card><Card title="Nhân sự & điều kiện chuyên môn"><Typography.Paragraph>Quyền quản trị, phạm vi được phân công và điều kiện chuyên môn là những lớp kiểm tra riêng.</Typography.Paragraph><Space wrap>{can('counter.save') && <Button onClick={() => openCommand('counter.save')}>Thêm quầy</Button>}{can('membership.save') && <Button onClick={() => openCommand('membership.save')}>Phân công phạm vi</Button>}{can('credential.save') && <Button onClick={() => openCommand('credential.save')}>Điều kiện chuyên môn</Button>}</Space><PharmacyTable resource={{ data: resource.data?.memberships ?? [] }} rowKey={recordId} columns={[
        { title: 'Tài khoản', dataIndex: 'admin_id' }, { title: 'Phạm vi', render: (_, row) => row.pharmacy_id ? `Nhà thuốc ${row.pharmacy_id}` : 'Toàn chuỗi' }, { title: 'Hiệu lực', render: (_, row) => <StatusTag value={row.status} /> }, { title: 'Thao tác', render: (_, row) => can('membership.save') && <Button onClick={() => openCommand('membership.save', row, { membership_id: recordId(row) }, { title: 'Cập nhật phân công', initial: { chain_wide: row.pharmacy_id == null } })}>Cập nhật</Button> },
    ]} /><PharmacyTable resource={{ data: resource.data?.credentials ?? [] }} rowKey={recordId} columns={[
        { title: 'Tài khoản', dataIndex: 'admin_id' }, { title: 'Số chứng chỉ', dataIndex: 'license_number' }, { title: 'Có hiệu lực đến', render: (_, row) => dateLabel(row.valid_until) }, { title: 'Hiệu lực', render: (_, row) => <StatusTag value={row.status} /> }, { title: 'Thao tác', render: (_, row) => can('credential.save') && <Button onClick={() => openCommand('credential.save', row, { credential_id: recordId(row) }, { title: 'Cập nhật điều kiện chuyên môn' })}>Cập nhật / ngừng hiệu lực</Button> },
    ]} /></Card><Card title="Dữ liệu & vận hành"><PharmacyError error={resource.error} onRetry={resource.reload} /><Descriptions column={1} items={[
        { key: 'version', label: 'Phiên bản', children: bootstrap.module?.version ?? '—' }, { key: 'ready', label: 'Runtime', children: <StatusTag value={bootstrap.runtime?.paused ? 'hold' : bootstrap.runtime?.status} /> },
    ]} /><Alert type="info" showIcon message="Hồ sơ nhạy cảm được lưu phía máy chủ" description="Mọi thao tác đọc, in, xuất và thay đổi dữ liệu cần quyền tương ứng. Không lưu đơn thuốc hoặc thông tin sức khỏe vào localStorage." /></Card></div>;
    if (section === 'integrations') return <><PharmacyError error={resource.error} onRetry={resource.reload} /><div className="ph-settings-grid">{Object.entries(bootstrap.integrations ?? resource.data?.integrations ?? {}).map(([key, integration]) => <CapabilityCard key={key} title={integration.name ?? key} capability={integration}><StatusTag value={integration.status} /><Typography.Paragraph>{integration.description}</Typography.Paragraph></CapabilityCard>)}</div><Card title="Gói nghiệp vụ có điều kiện" className="ph-advanced"><div className="ph-capability-grid">{capabilities.map(([key, title, description]) => <CapabilityCard key={key} title={title} capability={bootstrap.capabilities?.[key]}><p>{description}</p></CapabilityCard>)}</div></Card><Alert type="info" showIcon message="Trạng thái kết nối theo bằng chứng thực" description="Đã gửi không đồng nghĩa đã được hệ thống ngoài tiếp nhận. Các gói chưa đủ điều kiện luôn bị chặn ở máy chủ." /></>;
    const data = resource.data ?? {};
    const metrics = data.metrics ?? data.summary ?? data;
    const metricSpec = [['sale_count', 'Giao dịch trong kỳ'], ['pending_handovers', 'Chờ bàn giao'], ['active_holds', 'Lô đang giữ'], ['open_shifts', 'Ca đang mở']];
    return <><PharmacyError error={resource.error} onRetry={resource.reload} />{section === 'reports' && <div className="ph-toolbar"><label>Từ ngày<Input type="date" value={from} onChange={(event) => setFrom(event.target.value)} aria-label="Báo cáo từ ngày" /></label><label>Đến ngày<Input type="date" value={to} onChange={(event) => setTo(event.target.value)} aria-label="Báo cáo đến ngày" /></label><Typography.Text type="secondary">{dateLabel(data.from)} – {dateLabel(data.to)} · {data.period_timezone ?? pharmacy?.timezone}</Typography.Text></div>}<div className="ph-metrics">{metricSpec.map(([key, label]) => <Card key={key} loading={resource.loading}><Statistic title={label} value={metrics[key] ?? '—'} /></Card>)}</div><Card title={section === 'operations' ? 'Điểm vào tác nghiệp' : 'Báo cáo nhà thuốc hiện tại'} extra={<Button onClick={resource.reload}>Làm mới</Button>}>
        {section === 'operations' ? <div className="ph-operation-links">{[['work', 'Hồ sơ cần xử lý', 'Tiếp nhận, chuẩn bị và bàn giao theo từng bước'], ['quality', 'Chất lượng & thu hồi', 'Kiểm tra các lô đang giữ và công việc thu hồi'], ['shifts', 'Ca & tiền quầy', 'Kiểm đếm và đối soát tiền thực tế'], ['inventory', 'Tồn kho theo lô', 'Khả dụng, hạn dùng và vị trí hàng']].map(([path, title, description]) => <button type="button" key={path} onClick={() => navigate(`/modules/pharmacy/${path}`)}><strong>{title}</strong><span>{description}</span></button>)}</div> : <Descriptions column={2} items={[
            { key: 'sales', label: 'Doanh thu ghi nhận', children: money(metrics.sales_minor ?? metrics.revenue_minor) }, { key: 'refunds', label: 'Tiền hoàn', children: money(metrics.refunds_minor ?? metrics.refund_minor) }, { key: 'asof', label: 'Cập nhật đến', children: dateLabel(data.as_of ?? resource.meta?.as_of) },
        ]} />}
        {(data.exceptions ?? []).length > 0 && <PharmacyTable resource={{ data: data.exceptions }} columns={[{ title: 'Hồ sơ', render: (_, row) => row.code ?? recordId(row) }, { title: 'Cần xử lý', render: (_, row) => row.message ?? row.reason }, { title: 'Trạng thái', render: (_, row) => <StatusTag value={row.status} /> }]} />}
        <Typography.Paragraph className="ph-muted">Số liệu tổng hợp có thể cập nhật chậm hơn giao dịch. Quyết định tồn và thu tiền luôn được kiểm lại từ nguồn nghiệp vụ khi ghi.</Typography.Paragraph>
    </Card>{section === 'reports' && <Card title="Các nhà thuốc được phép xem báo cáo" className="ph-subsection"><PharmacyTable resource={{ data: data.sites ?? [] }} columns={[{ title: 'Nhà thuốc', render: (_, row) => [row.code, row.name].filter(Boolean).join(' · ') }, { title: 'Giao dịch', dataIndex: 'sale_count' }, { title: 'Doanh thu', render: (_, row) => money(row.revenue_minor) }, { title: 'Tiền hoàn', render: (_, row) => money(row.refunds_minor) }, { title: 'Chờ bàn giao', dataIndex: 'pending_handovers' }]} /></Card>}</>;
}
