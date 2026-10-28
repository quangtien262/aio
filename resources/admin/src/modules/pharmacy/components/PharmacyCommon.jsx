import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Empty from 'antd/es/empty';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { capabilityReady, dateLabel, recordId, resourceRows, statusLabels } from '../utils/pharmacyData';

export function StatusTag({ value }) {
    if (!value) return <span className="ph-muted">—</span>;
    const color = ['paid', 'approved', 'reviewed', 'verified', 'handed_over', 'completed', 'saleable', 'accepted', 'reconciled'].includes(value) ? 'green'
        : ['uncertain', 'quarantine', 'hold', 'exception', 'recalled', 'invalidated', 'rejected', 'failed'].includes(value) ? 'orange' : 'blue';
    return <Tag color={color}>{statusLabels[value] ?? value}</Tag>;
}
export function PharmacyError({ error, onRetry }) {
    if (!error) return null;
    const conflict = Number(error.status) === 409;
    return <Alert type={conflict ? 'warning' : 'error'} showIcon className="ph-resource-error" message={conflict ? 'Dữ liệu vừa thay đổi' : error.message || 'Chưa tải được dữ liệu'} description={<>
        {conflict && <div>Thao tác chưa được ghi đè. Tải bản mới nhất, đối chiếu thay đổi rồi thực hiện lại.</div>}
        {error.code && <div>Mã: {error.code}</div>}
        {error.requestId && <Typography.Text copyable>Mã đối soát: {error.requestId}</Typography.Text>}
    </>} action={onRetry ? <Button onClick={onRetry}>{conflict ? 'Tải bản mới nhất' : 'Thử lại'}</Button> : null} />;
}
export function PharmacyTable({ resource, columns, page, perPage = 20, onPageChange, onRow, empty = 'Chưa có dữ liệu trong phạm vi này.', rowKey = recordId }) {
    const data = resourceRows(resource.data);
    const meta = resource.meta ?? resource.data?.meta ?? {};
    return <><PharmacyError error={resource.error} onRetry={resource.reload} /><Table rowKey={rowKey} size="middle" loading={resource.loading} dataSource={data} columns={columns}
        onRow={onRow} scroll={{ x: 'max-content' }} locale={{ emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={empty} /> }}
        pagination={onPageChange ? { current: page, pageSize: Number(meta.per_page ?? perPage), total: Number(meta.total ?? resource.data?.total ?? data.length), showSizeChanger: true, pageSizeOptions: [20, 50, 100], showTotal: (total) => `${total} bản ghi`, onChange: onPageChange } : false} /></>;
}
export function CapabilityCard({ title, capability, children }) {
    return <div className="ph-capability-card"><div className="ph-capability-heading"><strong>{title}</strong><StatusTag value={capabilityReady(capability) ? 'ready' : 'disabled'} /></div>{children}<p className="ph-muted">{capability?.reason ?? 'Chỉ mở nghiệp vụ khi phiên bản, quyền, cấu hình và các điều kiện nghiệm thu đã sẵn sàng.'}</p></div>;
}
export function PendingCommands({ entries, onRecover, recovering }) {
    if (!entries.length) return null;
    return <Alert type="warning" showIcon className="ph-pending" message={`${entries.length} thao tác cần xác nhận kết quả`} description={<>
        <p>Không tạo lại giao dịch khi kết quả chưa rõ. Kiểm tra bằng mã gốc; hệ thống chỉ hoàn tất khi máy chủ xác nhận.</p>
        {entries.map((entry) => <div className="ph-pending-row" key={entry.key}><Space wrap><span>{dateLabel(entry.created_at)}</span><Typography.Text copyable>{entry.key}</Typography.Text><Button size="small" loading={recovering === entry.key} onClick={() => onRecover(entry)}>Kiểm tra kết quả</Button></Space></div>)}
    </>} />;
}
