import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import QRCode from 'antd/es/qr-code';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import AntTable from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { adminApi } from '../../../shared/config/routes.js';
import { createManufacturingApi } from '../api/manufacturingApi.js';
import ManufacturingCommandDrawer, { resourceRows, rowId, rowLabel } from '../components/ManufacturingCommandDrawer.jsx';
import ManufacturingDocuments from '../components/ManufacturingDocuments.jsx';
import ManufacturingWarnings from '../components/ManufacturingWarnings.jsx';
import ManufacturingSettings from '../components/ManufacturingSettings.jsx';
import ManufacturingPlanningInsights, { ManufacturingCalendar } from '../components/ManufacturingPlanningInsights.jsx';
import { commands, sections, sectionForMenu, manufacturingOperationLabel } from '../utils/manufacturingForms.js';
import '../styles/manufacturing.css';

const Table = (props) => <AntTable locale={{ emptyText: <Empty description="Chưa có dữ liệu." /> }} {...props} />;

const statusLabels = { saleable: 'Được sử dụng', quarantine: 'Chờ kiểm', quality_hold: 'Giữ chờ chất lượng', subcontracted: 'Đang ở đối tác', sent: 'Đã gửi', final: 'Đã chốt', needs_adjustment: 'Cần đối chiếu', closed: 'Đã khóa', draft: 'Nháp', active: 'Đang làm', approved: 'Đã duyệt', released: 'Đã phát hành', planned: 'Đã lên kế hoạch', in_progress: 'Đang thực hiện', in_transit: 'Đang bàn giao', completed: 'Hoàn tất', accepted: 'Đạt', rejected: 'Không đạt', pending: 'Chờ xử lý', received: 'Đã nhận', issued: 'Đã cấp', reserved: 'Đã giữ', packed: 'Đã đóng thùng', shipped: 'Đã giao', hold: 'Giữ hàng', rework: 'Cần sửa', finished: 'Thành phẩm', open: 'Đang mở', dispatched: 'Đã gửi', cancelled: 'Đã hủy', inspected: 'Đã kiểm', partially_received: 'Nhận một phần' };
const statusTag = (value) => value ? <Tag color={['completed', 'approved', 'accepted', 'finished', 'shipped'].includes(value) ? 'green' : ['hold', 'rejected', 'rework'].includes(value) ? 'orange' : 'blue'}>{statusLabels[value] ?? value}</Tag> : '—';
const number = (value) => value === undefined || value === null ? '—' : new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 6 }).format(value);
const textValue = (value) => value === undefined || value === null || value === '' ? '—' : Array.isArray(value) ? value.join(', ') : String(value);
const metricLabels = { open_orders: 'Lệnh đang mở', wip_quantity: 'Đang sản xuất', quality_hold_quantity: 'Chờ xử lý chất lượng', shipped_quantity: 'Đã giao', material_quantity: 'Nguyên liệu khả dụng', cost_amount: 'Chi phí ghi nhận' };
const detailLabels = { code: 'Mã', name: 'Tên', status: 'Trạng thái', revision: 'Phiên bản kỹ thuật', version: 'Phiên bản dữ liệu', style_code: 'Mã hàng', style_name: 'Tên mã hàng', type: 'Loại nhu cầu', customer_name: 'Khách hàng / nhãn hàng', color: 'Màu', size: 'Kích cỡ', quantity: 'Số lượng', remaining_quantity: 'Còn lại', available_quantity: 'Khả dụng', good_quantity: 'Đạt', rework_quantity: 'Cần sửa', scrap_quantity: 'Loại bỏ', material_code: 'Mã vật tư', roll_code: 'Cuộn / kiện', lot_code: 'Lô', shade: 'Ánh màu', width: 'Khổ', uom: 'Đơn vị', unit_cost: 'Đơn giá', total_cost: 'Tổng giá thành', material_cost: 'Chi phí vật tư', labor_cost: 'Chi phí nhân công', overhead_cost: 'Chi phí chung', subcontract_cost: 'Chi phí gia công', output_quantity: 'Thành phẩm đạt', category: 'Khoản mục', amount: 'Số tiền', supplier_name: 'Nhà cung cấp', vendor_name: 'Đối tác gia công', process: 'Công đoạn', operation: 'Công đoạn hiện tại', operation_code: 'Mã công đoạn', planned_start: 'Bắt đầu kế hoạch', planned_end: 'Kết thúc kế hoạch', due_date: 'Hạn giao', reference: 'Tham chiếu', created_at: 'Tạo lúc', shipped_at: 'Ngày giao', notes: 'Ghi chú', reason: 'Lý do' };
const savedFactory = (actor) => { try { return globalThis.sessionStorage?.getItem(`aio.manufacturing.factory.${actor}`) ?? ''; } catch { return ''; } };

function SimpleRows({ rows, fields }) {
    return <Table size="small" rowKey={(row, index) => rowId(row) ?? index} dataSource={rows ?? []} pagination={false} scroll={{ x: true }} columns={fields.map(([key, label]) => ({ title: label, key, render: (_, row) => key === 'status' ? statusTag(row[key]) : ['quantity', 'amount', 'capacity_minutes'].includes(key) ? number(row[key]) : textValue(row[key] ?? (key === 'planned_end' ? row.due_date : undefined)) }))} />;
}

function RecordDetails({ record, onClose, api, contextKey, documentType, canDocuments, labelType }) {
    const data = record ? { ...(record.metadata ?? {}), ...record } : {};
    if (Array.isArray(data.lines)) data.lines = data.lines.map((line) => ({ ...(line.variant ?? {}), ...line }));
    const items = Object.entries(detailLabels).filter(([key]) => data[key] !== undefined && data[key] !== null).map(([key, label]) => ({ key, label, children: key === 'status' ? statusTag(data[key]) : textValue(data[key]) }));
    return <Drawer title={record ? rowLabel(record) || 'Chi tiết' : 'Chi tiết'} open={Boolean(record)} width={780} onClose={onClose} rootClassName="mfg-drawer" destroyOnClose><Descriptions column={2} bordered size="small" items={items} />
        {['sizes', 'colors'].map((key) => Array.isArray(data[key]) && <div className="mfg-detail-section" key={key}><strong>{key === 'sizes' ? 'Kích cỡ' : 'Màu sắc'}</strong><Space wrap>{data[key].map((value) => <Tag key={value}>{value}</Tag>)}</Space></div>)}
        {Array.isArray(data.lines) && <div className="mfg-detail-section"><Typography.Title level={5}>Chi tiết dòng</Typography.Title><SimpleRows rows={data.lines} fields={data.lines.some((row) => row.material_code) ? [['material_code', 'Vật tư'], ['name', 'Tên'], ['quantity', 'Số lượng'], ['uom', 'Đơn vị'], ['unit_cost', 'Đơn giá']] : [['color', 'Màu'], ['size', 'Kích cỡ'], ['quantity', 'Số lượng']]} /></div>}
        {Array.isArray(data.bom) && <div className="mfg-detail-section"><Typography.Title level={5}>Định mức</Typography.Title><SimpleRows rows={data.bom} fields={[[ 'material_code', 'Mã vật tư'], ['name', 'Tên'], ['quantity', 'Định mức'], ['uom', 'Đơn vị'], ['color', 'Màu'], ['size', 'Size']]} /></div>}
        {Array.isArray(data.routing) && <div className="mfg-detail-section"><Typography.Title level={5}>Quy trình</Typography.Title><SimpleRows rows={data.routing} fields={[[ 'code', 'Mã công đoạn'], ['name', 'Tên'], ['smv', 'Phút tiêu chuẩn']]} /></div>}
        {Array.isArray(data.items) && <div className="mfg-detail-section"><Typography.Title level={5}>Hàng trong thùng</Typography.Title><SimpleRows rows={data.items} fields={[[ 'bundle_code', 'Bó'], ['color', 'Màu'], ['size', 'Size'], ['quantity', 'Số lượng']]} /></div>}
        {Array.isArray(data.material_requirements) && <div className="mfg-detail-section"><Typography.Title level={5}>Nhu cầu nguyên phụ liệu</Typography.Title><SimpleRows rows={data.material_requirements} fields={[[ 'material_code', 'Vật tư'], ['required_quantity', 'Cần'], ['available_quantity', 'Có thể dùng'], ['shortage_quantity', 'Còn thiếu'], ['uom', 'Đơn vị']]} /></div>}
        {record && labelType && <div className="mfg-detail-section"><div className="mfg-print-label"><div><strong>{labelType}</strong><Typography.Title level={4}>{rowLabel(record)}</Typography.Title><div>{[record.color, record.size].filter(Boolean).join(' / ')}</div><div>Số lượng: {number(record.quantity)}</div><small>{rowId(record)}</small></div><QRCode value={String(rowId(record))} size={140} /></div><Button style={{ marginTop: 12 }} onClick={() => window.print()}>In nhãn / phiếu</Button></div>}
        {record && documentType && canDocuments && !record.custody_only && <ManufacturingDocuments api={api} resourceType={documentType} resourceId={rowId(record)} contextKey={contextKey} />}
    </Drawer>;
}

export default function ManufacturingManagerPage({ moduleMenu, callAdminApi, currentAdmin, currentPermissions = [] }) {
    const { message } = App.useApp();
    const section = sectionForMenu(moduleMenu);
    const config = sections[section];
    const [factoryId, setFactoryId] = useState(() => savedFactory(currentAdmin?.id));
    const [bootstrap, setBootstrap] = useState(null);
    const [bootLoading, setBootLoading] = useState(true);
    const [bootError, setBootError] = useState('');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(20);
    const [search, setSearch] = useState('');
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('');
    const [action, setAction] = useState(null);
    const [details, setDetails] = useState(null);
    const [insight, setInsight] = useState(null);
    const [calendarRevision, setCalendarRevision] = useState(0);
    const [pending, setPending] = useState([]);
    const [recovering, setRecovering] = useState('');
    const [stocktakeLoading, setStocktakeLoading] = useState('');
    const bootGeneration = useRef(0);
    const resourceGeneration = useRef(0);
    const contextKey = `${currentAdmin?.id ?? ''}:${factoryId}`;
    const contextRef = useRef(contextKey);
    contextRef.current = contextKey;
    const api = useMemo(() => createManufacturingApi(callAdminApi, currentAdmin?.id, factoryId), [callAdminApi, currentAdmin?.id, factoryId]);
    useEffect(() => { try { const key = `aio.manufacturing.factory.${currentAdmin?.id}`; if (factoryId) globalThis.sessionStorage?.setItem(key, factoryId); else globalThis.sessionStorage?.removeItem(key); } catch { /* Selection remains in memory. */ } }, [factoryId, currentAdmin?.id]);
    const refreshPending = useCallback(() => api.pending().then((items) => { if (contextRef.current === contextKey) setPending(items); }).catch(() => { if (contextRef.current === contextKey) setPending([]); }), [api, contextKey]);
    const loadBootstrap = useCallback(async () => {
        const ticket = ++bootGeneration.current;
        setBootLoading(true); setBootError('');
        try {
            const result = await callAdminApi(adminApi(`manufacturing/bootstrap${factoryId ? `?factory_id=${encodeURIComponent(factoryId)}` : ''}`));
            if (bootGeneration.current !== ticket) return;
            const next = result.data ?? {};
            setBootstrap(next);
            if (!factoryId && next.current_factory_id) setFactoryId(next.current_factory_id);
        } catch (err) { if (bootGeneration.current === ticket) { setBootstrap(null); if (factoryId && [403, 404].includes(Number(err.status))) setFactoryId(''); else setBootError(err.message || 'Không tải được ngữ cảnh sản xuất.'); } }
        finally { if (bootGeneration.current === ticket) setBootLoading(false); }
    }, [callAdminApi, factoryId]);
    useEffect(() => { setBootstrap(null); loadBootstrap(); return () => { bootGeneration.current += 1; }; }, [loadBootstrap, currentAdmin?.id]);
    useEffect(() => { refreshPending(); }, [refreshPending]);
    useEffect(() => {
        resourceGeneration.current += 1;
        setAction(null); setDetails(null); setInsight(null); setData(null); setError(''); setStocktakeLoading(''); setPage(1); setSearch(''); setQuery(''); setStatus('');
    }, [contextKey, section]);
    const load = useCallback(async () => {
        if (!bootstrap || (section !== 'settings' && !factoryId)) return;
        const ticket = ++resourceGeneration.current;
        setLoading(true); setError('');
        try {
            const response = await api.read(config.kind, { page, per_page: pageSize, search: query, status });
            if (resourceGeneration.current === ticket) setData(response.data ?? {});
        } catch (err) { if (resourceGeneration.current === ticket) { setData(null); setError(err.message || 'Không tải được dữ liệu.'); } }
        finally { if (resourceGeneration.current === ticket) setLoading(false); }
    }, [api, bootstrap, config.kind, factoryId, page, pageSize, query, section, status]);
    useEffect(() => { load(); return () => { resourceGeneration.current += 1; }; }, [load]);
    const permissions = Array.isArray(bootstrap?.permissions) ? bootstrap.permissions : currentPermissions;
    const can = (operation, record = null) => operation === 'company.update'
        ? Boolean(bootstrap && !bootLoading && String(bootstrap.current_factory_id ?? '') === String(factoryId) && bootstrap.companies?.some((company) => rowId(company) === rowId(record) && company.can_manage === true))
        : Boolean(bootstrap && !bootLoading && String(bootstrap.current_factory_id ?? '') === String(factoryId)
        && permissions.includes(commands[operation]?.permission)
        && (!operation.startsWith('runtime.') || bootstrap.can_pause)
        && (operation !== 'subcontract.dispatch' || permissions.includes('manufacturing.cost.manage'))
        && (!['company.create', 'factory.create', 'sku.save'].includes(operation) || !factoryId || (bootstrap.company_permissions ?? permissions).includes('manufacturing.settings')));
    const scopedBootstrap = useMemo(() => ({ ...bootstrap,
        workshops: (bootstrap?.workshops ?? []).filter((row) => !row.factory_id || row.factory_id === factoryId),
        lines: (bootstrap?.lines ?? []).filter((row) => !row.factory_id || row.factory_id === factoryId),
        handover_workshops: (bootstrap?.workshops ?? []).filter((row) => row.company_id === (bootstrap?.factories ?? []).find((factory) => rowId(factory) === factoryId)?.company_id).map((row) => ({ ...row, name: `${(bootstrap?.factories ?? []).find((factory) => rowId(factory) === row.factory_id)?.name ?? ''} / ${row.name}` })),
    }), [bootstrap, factoryId]);
    const paused = Boolean(bootstrap?.runtime?.paused);
    const open = (operation, record = null, extra = {}) => {
        if (!can(operation, record)) return;
        setAction({ operation, record, ...extra });
    };
    const saved = async (response, operation) => {
        message.success('Đã ghi nhận giao dịch.');
        if (operation === 'plan.calendar.save') setCalendarRevision((value) => value + 1);
        if (['company.create', 'company.update', 'factory.create', 'workshop.create', 'line.create', 'membership.save', 'runtime.pause', 'runtime.resume'].includes(operation)) {
            if (operation === 'factory.create' && response?.data?.id) setFactoryId(response.data.id);
            else await loadBootstrap();
        }
        await load(); refreshPending();
    };
    const recover = async (entry) => {
        setRecovering(entry.key);
        try { const result = await api.recover(entry); message.info(result?.data?.status === 'committed' ? 'Giao dịch đã ghi nhận thành công.' : `Trạng thái: ${result?.data?.status ?? 'Chưa xác định'}`); await refreshPending(); await load(); }
        catch (err) { message.error(err.message || 'Chưa kiểm tra được giao dịch.'); }
        finally { setRecovering(''); }
    };
    const openStocktake = async (record) => {
        const ticket = resourceGeneration.current;
        setStocktakeLoading(rowId(record));
        try {
            let lines = record.lines ?? [];
            if (record.lines_truncated || Number(record.lines_total) > lines.length) {
                lines = [];
                let next = 1;
                let total = Number(record.lines_total ?? 0);
                do {
                    const response = await api.read('materials', { allocation_type: 'stocktake-lines', stocktake_id: rowId(record), page: next++, per_page: 100 });
                    if (ticket !== resourceGeneration.current) return;
                    const batch = response.data?.items ?? [];
                    total = Number(response.data?.total ?? total);
                    lines.push(...batch);
                    if (!batch.length && lines.length < total) throw new Error('Chưa tải đủ snapshot kiểm kê. Làm mới trước khi nhập số đếm.');
                } while (lines.length < total);
            }
            if (ticket !== resourceGeneration.current) return;
            open('material.stocktake.post', record, { initial: { stocktake_id: rowId(record), counts: lines.map((line) => ({ balance_id: line.balance_id, label: [line.roll_code ?? line.code, line.material_code ?? line.name, line.uom ?? line.primary_unit, line.secondary_unit ? `Đơn vị phụ: ${line.secondary_unit}` : ''].filter(Boolean).join(' · '), expected_quantity: line.expected_quantity })) } });
        } catch (err) { if (ticket === resourceGeneration.current) message.error(err.message); }
        finally { setStocktakeLoading((current) => current === rowId(record) ? '' : current); }
    };
    const actionButton = (operation, record, label, extra = {}) => can(operation) && <Button key={operation} size="small" disabled={paused && operation !== 'runtime.resume'} onClick={() => open(operation, record, extra)}>{label ?? commands[operation].title}</Button>;
    const rowActions = (row) => {
        const state = row.status;
        const actions = [];
        if (section === 'engineering') {
            actions.push(actionButton('style.save', row, state === 'approved' ? 'Phiên bản mới' : 'Sửa'), actionButton('sample.save', null, 'Tạo mẫu', { initial: { style_id: rowId(row), type: 'pp' } }));
            if (['draft', undefined].includes(state)) actions.push(actionButton('style.approve', row, 'Duyệt'));
            if (permissions.includes('manufacturing.engineering')) actions.push(<Button key="impact" size="small" onClick={() => setInsight({ kind: 'engineering-impact', record: row })}>Ảnh hưởng</Button>);
        } else if (section === 'demand' && ['draft', 'open', undefined].includes(state)) actions.push(actionButton('demand.save', row, 'Sửa'));
        else if (section === 'planning') {
            if (!row.custody_only) {
                if (['draft', 'planned', undefined].includes(state)) actions.push(actionButton('order.save', row, 'Sửa'), actionButton('order.release', row, 'Phát hành'));
                if (['draft', 'planned', 'released'].includes(state)) actions.push(actionButton('order.cancel', row, 'Hủy lệnh'));
                if (['draft', 'released', 'in_progress'].includes(state)) actions.push(actionButton('procurement.create', null, 'Lập mua vật tư', { initial: { order_id: rowId(row), source_revision: row.version } }));
                if (['draft', 'released', 'in_progress', 'partially_shipped'].includes(state)) {
                    const committed = row.schedule?.status === 'committed';
                    actions.push(actionButton(committed ? 'plan.reschedule' : 'plan.commit', row, committed ? 'Đổi lịch' : 'Cam kết lịch', { initial: { freeze_until: row.schedule?.freeze_until } }));
                }
                if (permissions.includes('manufacturing.plan')) actions.push(<Button key="capacity" size="small" onClick={() => setInsight({ kind: 'plan-capacity', record: row })}>Năng lực</Button>, <Button key="history" size="small" onClick={() => setInsight({ kind: 'plan-history', record: row })}>Lịch sử</Button>);
                actions.push(<Button key="mrp" size="small" onClick={async () => { const ticket = resourceGeneration.current; try { const result = await api.read('order-mrp', { order_id: rowId(row) }); if (ticket === resourceGeneration.current) setDetails({ ...row, material_requirements: result.data?.items ?? result.data?.requirements ?? result.data?.material_requirements ?? [] }); } catch (err) { if (ticket === resourceGeneration.current) message.error(err.message); } }}>Nhu cầu vật tư</Button>);
            }
        } else if (section === 'materials') actions.push(actionButton('material.inspect', row, 'Kiểm nhận'), actionButton('material.reserve', row, 'Giữ vật tư'));
        else if (section === 'execution') actions.push(actionButton('operation.record', row, 'Công đoạn', { initial: { operation_code: row.operation_key ?? row.operation, quantity: row.quantity, good_quantity: row.quantity, scrap_quantity: 0 } }), actionButton('bundle.handover', row, 'Bàn giao', { initial: { to_operation: row.operation_key ?? row.operation, quantity: row.quantity } }), actionButton('bundle.split', row, 'Tách bó'), actionButton('bundle.merge', row, 'Gộp bó'), actionButton(row.status === 'quality_hold' ? 'quality.release' : 'quality.hold', row, row.status === 'quality_hold' ? 'Giải phóng' : 'Giữ QC'), actionButton('bundle.finish', row, 'Nhập thành phẩm'));
        else if (section === 'quality' && row.bundle_id) actions.push(actionButton('quality.rework', null, 'Tái kiểm', { initial: { bundle_id: row.bundle_id, expected_version: row.bundle_version }, resource: row.bundle_id }));
        else if (section === 'subcontract' && !['completed', 'cancelled'].includes(state)) actions.push(actionButton('subcontract.receive', row, 'Nhận hàng'));
        else if (section === 'fulfillment' && !['shipped', 'cancelled'].includes(state)) actions.push(actionButton('shipment.dispatch', row, 'Giao hàng'), actionButton('shipment.cancel', row, 'Hủy đóng gói'));
        return <Space wrap size={4}><Button size="small" type="text" onClick={() => setDetails(row)}>Chi tiết</Button>{actions.filter(Boolean)}</Space>;
    };
    const columns = [
        { title: 'Mã / tên', key: 'name', width: 220, render: (_, row) => <div className="mfg-row-name"><strong>{row.code ?? row.roll_code ?? row.number ?? rowId(row)}</strong><span>{row.name ?? row.style_name ?? row.customer_name ?? row.vendor_name ?? row.supplier_name ?? ''}</span></div> },
        { title: 'Trạng thái', dataIndex: 'status', width: 145, render: statusTag },
        ...(section === 'materials' ? [{ title: 'Lô / ánh màu', key: 'lot', render: (_, row) => [row.lot_code, row.shade].filter(Boolean).join(' · ') || '—' }, { title: 'Còn lại', key: 'remaining', render: (_, row) => `${number(row.remaining_quantity ?? row.available_quantity ?? row.quantity)} ${row.uom ?? ''}` }] : []),
        ...(['cutting', 'execution', 'quality'].includes(section) ? [{ title: 'Màu / size', key: 'variant', render: (_, row) => [row.color, row.size].filter(Boolean).join(' / ') || '—' }] : []),
        ...(section === 'engineering' ? [{ title: 'Phiên bản', dataIndex: 'revision' }, { title: 'Size / màu', key: 'variants', render: (_, row) => `${(row.sizes ?? []).join(', ')} / ${(row.colors ?? []).join(', ')}` }] : [{ title: section === 'costing' ? 'Số tiền' : 'Số lượng', key: 'quantity', align: 'right', render: (_, row) => number(row.total_cost ?? row.amount ?? row.quantity ?? row.total_quantity ?? row.remaining_quantity ?? row.lines?.reduce((total, line) => total + Number(line.quantity || 0), 0)) }]),
        ...(['demand', 'planning', 'subcontract'].includes(section) ? [{ title: 'Hạn hoàn thành', key: 'due', render: (_, row) => row.due_date ?? row.planned_end ?? '—' }] : []),
        { title: 'Thao tác', key: 'actions', width: section === 'execution' ? 360 : 270, render: (_, row) => rowActions(row) },
    ];
    const rows = resourceRows(data, config.kind);
    const samples = data?.samples ?? rows.flatMap((row) => (row.samples ?? []).map((sample) => ({ ...sample, style_code: row.code, style_name: row.name })));
    const table = <Table rowKey={rowId} dataSource={rows} loading={loading} columns={columns} scroll={{ x: 1000 }} locale={{ emptyText: <Empty description="Chưa có dữ liệu trong phạm vi đang chọn." /> }} pagination={{ current: Number(data?.page ?? page), pageSize, total: Number(data?.total ?? rows.length), showSizeChanger: true, showTotal: (total) => `${number(total)} bản ghi`, onChange: (next, size) => { setPage(next); setPageSize(size); } }} />;
    const summary = data?.summary ?? {};
    const reportMetrics = Array.isArray(data?.metrics) ? data.metrics : Object.entries(metricLabels).filter(([key]) => summary[key] !== undefined).map(([key, label]) => ({ label, value: summary[key] }));
    return <div className={`mfg-workspace${section === 'settings' ? ' mfg-settings-workspace' : ''}`}>
        <header className={`mfg-page-header${section === 'settings' ? ' mfg-settings-header' : ''}`}><div><div className="mfg-kicker">QUẢN LÝ SẢN XUẤT</div><Typography.Title level={3}>{config.title}</Typography.Title><Typography.Paragraph type="secondary">{config.description}</Typography.Paragraph></div><Space wrap><Select aria-label="Nhà máy sản xuất" value={factoryId || undefined} placeholder="Chọn nhà máy" loading={bootLoading} style={{ minWidth: 230 }} options={(bootstrap?.factories ?? []).map((row) => ({ value: rowId(row), label: rowLabel(row) }))} onChange={(value) => { setFactoryId(value); setAction(null); setDetails(null); setData(null); }} /><Button onClick={() => { loadBootstrap(); load(); refreshPending(); }} loading={loading || bootLoading}>Làm mới</Button></Space></header>
        {bootError && <Alert type="error" showIcon message={bootError} action={<Button onClick={loadBootstrap}>Thử lại</Button>} />}
        <ManufacturingWarnings key={contextKey} readiness={bootstrap?.readiness} paused={paused} loading={bootLoading} error={bootError} onCheck={loadBootstrap} onResume={can('runtime.resume') ? () => open('runtime.resume') : undefined} />
        {pending.length > 0 && <Card size="small" title="Giao dịch cần kiểm tra"><Typography.Paragraph type="secondary">Kết quả lần gửi trước chưa rõ. Kiểm tra trước khi gửi nội dung khác cho cùng thao tác.</Typography.Paragraph><Space direction="vertical" style={{ width: '100%' }}>{pending.map((entry) => <div className="mfg-pending-row" key={entry.key}><span>{manufacturingOperationLabel(entry.operation)} · {new Date(entry.created_at).toLocaleString('vi-VN')}</span><Button loading={recovering === entry.key} onClick={() => recover(entry)}>Kiểm tra kết quả</Button></div>)}</Space></Card>}
        {error && <Alert type="error" showIcon message={error} action={<Button onClick={load}>Thử lại</Button>} />}
        {bootLoading && !bootstrap ? <Card loading /> : !factoryId && section !== 'settings' ? <Card><Empty description="Chưa có nhà máy được chọn. Mở Thiết lập để tạo công ty, nhà máy và phân xưởng hoặc chọn một nhà máy đã được cấp quyền." /></Card> : <>
            {section === 'overview' ? <><div className="mfg-metrics">{reportMetrics.map((metric, index) => <Card key={metric.key ?? index} size="small"><Statistic title={metric.label ?? metric.name} value={metric.value ?? 0} suffix={metric.unit} formatter={(value) => number(value)} /></Card>)}</div>{!loading && !reportMetrics.length && !error && <Card><Empty description="Chưa có số liệu sản xuất tại nhà máy này." /></Card>}<Card title="Theo dõi đơn hàng" loading={loading}><SimpleRows rows={data?.recent_orders ?? data?.orders ?? []} fields={[[ 'code', 'Lệnh sản xuất'], ['status', 'Trạng thái'], ['quantity', 'Số lượng'], ['planned_end', 'Kế hoạch kết thúc']]} /></Card><Alert type="info" showIcon message="Số liệu cập nhật từ các giao dịch đã ghi nhận tại nhà máy đang chọn." /></>
                : section === 'settings' ? <ManufacturingSettings key={contextKey} data={data} bootstrap={bootstrap} factoryId={factoryId} can={can} paused={paused} loading={loading} onOpen={open} onSelectFactory={(value) => { setFactoryId(value); setAction(null); setDetails(null); setData(null); }} />
                    : <Card className="mfg-resource-card" title={config.title} extra={<Space wrap>{config.create && actionButton(config.create, null, commands[config.create]?.title)}{section === 'materials' && actionButton('material.receive', null, 'Nhập tồn đầu kỳ')}</Space>}><div className="mfg-toolbar"><Input.Search value={search} onChange={(event) => setSearch(event.target.value)} onSearch={(value) => { setQuery(value.trim()); setPage(1); }} allowClear placeholder={['cutting', 'execution'].includes(section) ? 'Quét mã bó / UUID và nhấn Enter…' : 'Tìm mã, tên…'} style={{ maxWidth: 360 }} enterButton="Tìm" /><Select value={status || undefined} onChange={(value) => { setStatus(value || ''); setPage(1); }} allowClear placeholder="Trạng thái" style={{ minWidth: 175 }} options={Object.entries(statusLabels).map(([value, label]) => ({ value, label }))} /></div>{table}</Card>}
            {section === 'costing' && <Card title="Chi phí và đối chiếu" extra={<Space wrap>{actionButton('costing.adjust', null, 'Phân bổ bổ sung')}{actionButton('costing.close', null, 'Tính và chốt giá thành')}</Space>}><SimpleRows rows={data?.entries ?? []} fields={[[ 'type', 'Khoản mục'], ['amount', 'Số tiền'], ['description', 'Diễn giải'], ['status', 'Trạng thái']]} /></Card>}
            {section === 'planning' && permissions.includes('manufacturing.plan') && <Card title="Lịch làm việc và năng lực được xác nhận" extra={actionButton('plan.calendar.save', null, 'Thêm lịch làm việc')}><ManufacturingCalendar api={api} contextKey={contextKey} revision={calendarRevision} canEdit={can('plan.calendar.save') && !paused} onEdit={(record) => open('plan.calendar.save', record)} /></Card>}
            {section === 'engineering' && <Card title="Hồ sơ mẫu theo phiên bản" extra={<Space>{actionButton('sample.save', null, 'Tạo mẫu')}{actionButton('sku.save', null, 'Tạo mã nguyên liệu')}</Space>}><Typography.Paragraph type="secondary">Mẫu PP cần được duyệt cho đúng phiên bản trước khi duyệt kỹ thuật.</Typography.Paragraph><Table rowKey={rowId} dataSource={samples} pagination={false} columns={[{ title: 'Mã hàng', render: (_, row) => row.style_code ?? row.style_name ?? row.style_id }, { title: 'Mẫu', dataIndex: 'type', render: (value) => ({ proto: 'Ban đầu', fit: 'Thử dáng', size_set: 'Bộ size', pp: 'Trước sản xuất (PP)' }[value] ?? value) }, { title: 'Phiên bản', dataIndex: 'revision' }, { title: 'Trạng thái', dataIndex: 'status', render: statusTag }, { title: 'Thao tác', render: (_, row) => row.status === 'draft' && actionButton('sample.approve', row, 'Duyệt mẫu') }]} /></Card>}
            {section === 'materials' && Array.isArray(data?.purchase_orders) && <Card title="Đơn mua nguyên phụ liệu"><Table rowKey={rowId} dataSource={data.purchase_orders} pagination={false} scroll={{ x: 750 }} columns={[{ title: 'Đơn mua', render: (_, row) => rowLabel(row) }, { title: 'Nhà cung cấp', dataIndex: 'supplier_name' }, { title: 'Trạng thái', dataIndex: 'status', render: statusTag }, { title: 'Thao tác', render: (_, row) => <Space wrap><Button size="small" onClick={() => setDetails(row)}>Chi tiết</Button>{['draft', 'submitted'].includes(row.status) && actionButton('procurement.approve', row, 'Duyệt')}{['approved', 'partially_received'].includes(row.status) && actionButton('procurement.receive', row, 'Nhận hàng', { options: { order_line_id: (row.lines ?? []).map((line) => ({ value: rowId(line), label: [line.material_code ?? line.code, line.name, `${number(line.quantity ?? line.ordered_quantity)} ${line.uom ?? line.primary_unit ?? ''}`].filter(Boolean).join(' · ') })) } })}{(row.receipt_lines ?? []).some((line) => Number(line.returnable_quantity) > 0) && actionButton('procurement.return', row, 'Trả nhà cung cấp', { options: { receipt_line_id: (row.receipt_lines ?? []).filter((line) => Number(line.returnable_quantity) > 0).map((line) => ({ value: rowId(line), label: [line.roll_code ?? line.code, line.material_code ?? line.name, `Còn trả: ${number(line.returnable_quantity)} ${line.uom ?? line.primary_unit ?? ''}`].filter(Boolean).join(' · ') })) } })}</Space> }]} /></Card>}
            {section === 'materials' && Array.isArray(data?.reservations ?? data?.holds) && <Card title="Vật tư đã giữ cho lệnh"><Table rowKey={rowId} dataSource={data.reservations ?? data.holds} pagination={false} columns={[{ title: 'Tham chiếu', render: (_, row) => row.code ?? row.order_ref ?? rowId(row) }, { title: 'Số lượng', dataIndex: 'quantity', render: number }, { title: 'Trạng thái', dataIndex: 'status', render: statusTag }, { title: 'Thao tác', render: (_, row) => <Space wrap>{actionButton('material.issue', row, 'Cấp vật tư', { initial: { reservation_id: rowId(row), quantity: row.remaining_quantity ?? row.quantity } })}{actionButton('material.release', row, 'Giải phóng', { initial: { reservation_id: rowId(row), quantity: row.remaining_quantity ?? row.quantity } })}</Space> }]} /></Card>}
            {section === 'materials' && Array.isArray(data?.issues) && <Card title="Vật tư đã cấp"><Table rowKey={rowId} dataSource={data.issues} pagination={false} columns={[{ title: 'Phiếu cấp', render: (_, row) => row.code ?? rowId(row) }, { title: 'Số lượng', dataIndex: 'quantity', render: number }, { title: 'Trạng thái', dataIndex: 'status', render: statusTag }, { title: 'Thao tác', render: (_, row) => actionButton('material.return', row, 'Trả dư', { initial: { issue_id: rowId(row) } }) }]} /></Card>}
            {section === 'materials' && <Card title="Kiểm kê kho nhà máy" extra={actionButton('material.stocktake', null, 'Bắt đầu kiểm kê')}><Typography.Paragraph type="secondary">Đếm theo từng cuộn / vị trí tồn và đối chiếu cả đơn vị phụ nếu có. Người bắt đầu và người duyệt số đếm phải khác nhau.</Typography.Paragraph><Table rowKey={rowId} dataSource={data?.stocktakes ?? []} pagination={false} columns={[{ title: 'Đợt kiểm kê', render: (_, row) => row.code ?? rowId(row) }, { title: 'Trạng thái', dataIndex: 'status', render: statusTag }, { title: 'Bắt đầu', dataIndex: 'created_at' }, { title: 'Thao tác', render: (_, row) => ['draft', 'counting'].includes(row.status) && can('material.stocktake.post') && <Button size="small" disabled={paused || Boolean(stocktakeLoading)} loading={stocktakeLoading === rowId(row)} onClick={() => openStocktake(row)}>Nhập số đếm và duyệt</Button> }]} /></Card>}
            {section === 'execution' && Array.isArray(data?.handovers) && <Card title="Bàn giao đang chờ"><Table rowKey={rowId} dataSource={data.handovers} pagination={false} columns={[{ title: 'Bàn giao', render: (_, row) => rowLabel(row) }, { title: 'Số lượng', dataIndex: 'quantity', render: number }, { title: 'Công đoạn nhận', dataIndex: 'to_operation' }, { title: 'Trạng thái', dataIndex: 'status', render: statusTag }, { title: 'Thao tác', render: (_, row) => !['received', 'completed'].includes(row.status) && actionButton('handover.receive', row, 'Xác nhận nhận', { initial: { quantity: row.quantity } }) }]} /></Card>}
            {section === 'quality' && Array.isArray(data?.reworks) && <Card title="Phiếu sửa đang mở"><Table rowKey={rowId} dataSource={data.reworks.filter((row) => row.status === 'open')} pagination={false} scroll={{ x: 650 }} columns={[{ title: 'Bó hàng', dataIndex: 'bundle_code' }, { title: 'Cần sửa', dataIndex: 'quantity', render: number }, { title: 'Đã xử lý', dataIndex: 'completed_quantity', render: number }, { title: 'Lý do', dataIndex: 'reason' }, { title: 'Thao tác', render: (_, row) => actionButton('quality.rework', null, 'Tái kiểm', { initial: { bundle_id: row.bundle_id, rework_id: rowId(row) }, resource: row.bundle_id }) }]} /></Card>}
        </>}
        <ManufacturingCommandDrawer action={action} onClose={() => setAction(null)} api={api} bootstrap={scopedBootstrap} contextKey={contextKey} onSaved={saved} onPendingChange={refreshPending} />
        <RecordDetails record={details} onClose={() => setDetails(null)} api={api} contextKey={contextKey} documentType={section === 'engineering' ? 'style' : section === 'planning' ? 'order' : null} canDocuments={permissions.includes('manufacturing.documents')} labelType={['execution', 'cutting'].includes(section) ? 'Bó sản xuất' : section === 'fulfillment' ? 'Phiếu đóng gói / giao hàng' : null} />
        <ManufacturingPlanningInsights insight={insight} onClose={() => setInsight(null)} api={api} contextKey={contextKey} />
    </div>;
}
