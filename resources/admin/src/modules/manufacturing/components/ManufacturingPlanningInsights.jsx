import { useCallback, useEffect, useRef, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Drawer from 'antd/es/drawer';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Typography from 'antd/es/typography';

const titles = { 'plan-capacity': 'Năng lực theo ngày', 'plan-history': 'Lịch sử cam kết kế hoạch', 'engineering-impact': 'Ảnh hưởng của phiên bản kỹ thuật' };
const format = (value) => value === undefined || value === null ? '—' : typeof value === 'boolean' ? value ? 'Có' : 'Không' : String(value);
const table = (rows, fields) => <Table size="small" rowKey={(row, index) => row.id ?? row.public_id ?? `${row.line_id}:${row.work_date}:${index}`} dataSource={rows ?? []} scroll={{ x: true }} pagination={{ pageSize: 20, showSizeChanger: false }} columns={fields.map(([key, title, render]) => ({ title, key, render: (_, row) => render ? render(row) : format(row[key]) }))} />;

export function ManufacturingCalendar({ api, contextKey, revision, canEdit, onEdit }) {
    const [data, setData] = useState([]);
    const [error, setError] = useState('');
    const generation = useRef(0);
    useEffect(() => {
        const ticket = ++generation.current;
        setData([]); setError('');
        api.read('plan-calendar').then((response) => { if (ticket === generation.current) setData(response.data?.items ?? []); }).catch((err) => { if (ticket === generation.current) setError(err.message); });
        return () => { generation.current += 1; };
    }, [api, contextKey, revision]);
    return <>{error && <Alert showIcon type="error" message={error} />}{table(data, [['work_date', 'Ngày'], ['scope_type', 'Phạm vi', (row) => row.line_code ?? row.workshop_code ?? ({ factory: 'Toàn nhà máy', workshop: 'Phân xưởng', line: 'Chuyền' }[row.scope_type]) ?? 'Toàn nhà máy'], ['is_working', 'Có làm việc'], ['available_minutes', 'Phút khả dụng'], ['action', 'Thao tác', (row) => canEdit && <Button size="small" onClick={() => onEdit(row)}>Sửa lịch</Button>]])}</>;
}

export default function ManufacturingPlanningInsights({ insight, onClose, api, contextKey }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [dates, setDates] = useState({ start: '', end: '' });
    const generation = useRef(0);
    const load = useCallback(async (override) => {
        if (!insight) return;
        const ticket = ++generation.current;
        setLoading(true); setError('');
        const params = insight.kind === 'engineering-impact' ? { style_id: insight.record.id } : { order_id: insight.record.id };
        if (override?.start) params.planned_start = override.start;
        if (override?.end) params.planned_end = override.end;
        try { const response = await api.read(insight.kind, params); if (ticket === generation.current) setData(response.data ?? {}); }
        catch (err) { if (ticket === generation.current) { setData(null); setError(err.message); } }
        finally { if (ticket === generation.current) setLoading(false); }
    }, [api, insight]);
    useEffect(() => { setData(null); setDates({ start: insight?.record.planned_start ?? '', end: insight?.record.planned_end ?? '' }); load(); return () => { generation.current += 1; }; }, [load, contextKey]);
    return <Drawer title={titles[insight?.kind] ?? ''} open={Boolean(insight)} onClose={onClose} width={880} rootClassName="mfg-drawer" destroyOnClose>
        <Typography.Paragraph strong>{insight?.record.code}</Typography.Paragraph>
        {error && <Alert type="error" showIcon message={error} />}
        {insight?.kind === 'plan-capacity' && <><Alert type="info" showIcon message="Tải được phân bổ đều theo ngày làm việc từ số lượng × phút tiêu chuẩn của routing. Báo cáo gồm các phân bổ nháp; lịch chưa xác nhận dùng năng lực danh nghĩa." /><Space wrap style={{ marginBlock: 16 }}><Input type="date" aria-label="Ngày đầu kiểm tra năng lực" value={dates.start} onChange={(event) => setDates((value) => ({ ...value, start: event.target.value }))} /><Input type="date" aria-label="Ngày cuối kiểm tra năng lực" value={dates.end} onChange={(event) => setDates((value) => ({ ...value, end: event.target.value }))} /><Button loading={loading} onClick={() => load(dates)}>Kiểm tra khoảng ngày</Button></Space>{(data?.warnings ?? []).map((warning, index) => <Alert key={index} type="warning" showIcon style={{ marginBottom: 8 }} message={[warning.work_date, warning.message].filter(Boolean).join(' · ')} />)}{table(data?.days ?? data?.items, [['line_code', 'Chuyền'], ['work_date', 'Ngày'], ['planned_minutes', 'Phút đã phân bổ'], ['available_minutes', 'Phút khả dụng'], ['calendar_confirmed', 'Lịch xác nhận'], ['overloaded', 'Vượt năng lực']])}</>}
        {insight?.kind === 'plan-history' && table(data?.items, [['action', 'Thao tác'], ['reason', 'Lý do'], ['before', 'Trước thay đổi', (row) => `${row.before_snapshot?.planned_start ?? '—'} → ${row.before_snapshot?.planned_end ?? '—'}`], ['after', 'Sau thay đổi', (row) => `${row.after_snapshot?.planned_start ?? '—'} → ${row.after_snapshot?.planned_end ?? '—'}`], ['created_at', 'Thời điểm']])}
        {insight?.kind === 'engineering-impact' && <><Alert type="info" showIcon message="Báo cáo chỉ hiển thị phạm vi được cấp quyền. Lệnh đã phát hành giữ nguyên snapshot kỹ thuật; danh sách đơn mua là các ứng viên cùng vật tư, cần đối chiếu phân bổ thực tế." /><Typography.Title level={5}>Lệnh còn mở</Typography.Title>{table(data?.orders, [['code', 'Lệnh'], ['status', 'Trạng thái'], ['uses_target_revision', 'Đang dùng phiên bản hiện tại']])}<Typography.Title level={5}>Vật tư đang giữ</Typography.Title>{table(data?.holds, [['public_id', 'Tham chiếu giữ'], ['order_ref', 'Lệnh'], ['quantity', 'Số lượng']])}<Typography.Title level={5}>Bó đang xử lý</Typography.Title>{table(data?.wip, [['code', 'Bó'], ['status', 'Trạng thái'], ['quantity', 'Chưa hoàn tất']])}<Typography.Title level={5}>Đơn mua cần đối chiếu</Typography.Title>{table(data?.candidate_purchase_orders, [['code', 'Đơn mua'], ['status', 'Trạng thái'], ['expected_at', 'Dự kiến nhận']])}</>}
        {loading && <Typography.Paragraph type="secondary">Đang kiểm tra dữ liệu…</Typography.Paragraph>}
    </Drawer>;
}
