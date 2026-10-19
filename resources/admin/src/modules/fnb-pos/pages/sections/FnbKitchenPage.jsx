import CheckCircleOutlined from '@ant-design/icons/CheckCircleOutlined';
import ClockCircleOutlined from '@ant-design/icons/ClockCircleOutlined';
import PlayCircleOutlined from '@ant-design/icons/PlayCircleOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import FullscreenOutlined from '@ant-design/icons/FullscreenOutlined';
import Alert from 'antd/es/alert';
import Badge from 'antd/es/badge';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Empty from 'antd/es/empty';
import Segmented from 'antd/es/segmented';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createIdempotencyKey } from '../../api/fnbApi';
import FnbPageHeader from '../../components/FnbPageHeader';
import { FnbResourceError } from '../../components/FnbResourceState';
import FnbKitchenProgress from '../../components/FnbKitchenProgress';
import FnbCompleteOrderButton from '../../components/FnbCompleteOrderButton';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, formatDateTime, formatDuration, formatQuantity, parseServerDate } from '../../utils/fnbFormat';

const { Text, Title } = Typography;

const statusOptions = [
    { value: 'active', label: 'Đang làm' },
    { value: 'waiting', label: 'Chờ pha chế' },
    { value: 'preparing', label: 'Đang pha' },
    { value: 'ready', label: 'Chờ phục vụ' },
    { value: 'served', label: 'Hoàn thành' },
];

const lineStatus = (line) => line.status_rollup ?? line.status ?? 'waiting';
const ticketLines = (ticket) => asArray(ticket.lines ?? ticket.items);

function nextAction(status) {
    if (status === 'waiting') {
        return { status: 'preparing', label: 'Bắt đầu pha', icon: <PlayCircleOutlined /> };
    }
    if (status === 'preparing') {
        return { status: 'ready', label: 'Báo xong', icon: <CheckCircleOutlined /> };
    }
    if (status === 'ready') {
        return { status: 'served', label: 'Đã giao món', icon: <CheckCircleOutlined /> };
    }

    return null;
}

export default function FnbKitchenPage() {
    const { api, outletId, can, runCommand } = useFnbWorkspace();
    const [statusFilter, setStatusFilter] = useState('active');
    const kitchenView = statusFilter === 'served' ? 'completed' : 'active';
    const [stationId, setStationId] = useState('');
    const [pendingLine, setPendingLine] = useState(null);
    const cursorState = useRef({ scope: '', cursor: 0 });
    const boardRef = useRef(null);
    const [fullscreen, setFullscreen] = useState(false);
    useEffect(() => {
        const changed = () => setFullscreen(document.fullscreenElement === boardRef.current);
        document.addEventListener('fullscreenchange', changed);
        return () => document.removeEventListener('fullscreenchange', changed);
    }, []);
    const loadKitchen = useCallback(async () => {
        const scope = `${outletId}:${stationId || 'all'}:${kitchenView}`;
        if (cursorState.current.scope !== scope) {
            cursorState.current = { scope, cursor: 0 };
        }
        const requestCursor = cursorState.current.cursor;
        const result = await api.kitchen({ cursor: requestCursor, limit: 200, view: kitchenView });
        const nextCursor = Number(result?.data?.cursor);
        if (cursorState.current.scope === scope && Number.isSafeInteger(nextCursor) && nextCursor >= requestCursor) {
            cursorState.current.cursor = nextCursor;
        }

        return result;
    }, [api, outletId, stationId, kitchenView]);
    const resource = useFnbResource({
        enabled: Boolean(outletId),
        loader: loadKitchen,
        deps: [outletId, stationId, kitchenView],
        pollMs: 1000,
        backgroundPolling: true,
    });
    const kitchen = resource.data ?? {};
    const tickets = asArray(kitchen.tickets);
    const stations = useMemo(() => {
        const found = new Map();
        asArray(kitchen.stations).forEach((station) => found.set(String(station.id), station));
        tickets.forEach((ticket) => {
            const id = ticket.prep_station_id ?? ticket.station_id;
            if (id && !found.has(String(id))) {
                found.set(String(id), { id, name: ticket.station_name ?? ticket.station?.name ?? `Trạm #${id}` });
            }
        });
        return [...found.values()];
    }, [kitchen.stations, tickets]);
    const visibleTickets = tickets.map((ticket) => ({
        ...ticket,
        displayLines: ticketLines(ticket).filter((line) => statusFilter === 'active'
            ? ['waiting', 'preparing', 'ready'].includes(lineStatus(line))
            : lineStatus(line) === statusFilter),
    })).filter((ticket) => ticket.displayLines.length > 0 && (!stationId || String(ticket.prep_station_id ?? ticket.station_id) === String(stationId)));

    const transition = async (ticket, line, action) => {
        if (pendingLine !== null) return;
        const idempotencyKey = createIdempotencyKey(`kds-${action.status}`);
        setPendingLine(line.id);
        try {
            await runCommand({
                execute: (auth) => api.command(`kitchen/lines/${line.id}/transition`, {
                    status: action.status,
                    expected_version: ticket.version,
                    expected_versions: { [`kitchen_ticket:${ticket.public_id ?? ticket.id}`]: ticket.version },
                }, { idempotencyKey, ...auth }),
                successMessage: action.status === 'preparing' ? 'Đã nhận pha món.' : action.status === 'ready' ? 'Đã báo món sẵn sàng.' : 'Đã xác nhận giao món.',
                onSuccess: () => resource.reload({ silent: true }),
                onConflict: resource.reload,
            });
        } finally {
            setPendingLine(null);
        }
    };

    const completeOrder = async (orderId, versions) => {
        if (pendingLine !== null) return;
        setPendingLine(`order-${orderId}`);
        try {
            await runCommand({
                execute: (auth) => api.command(`kitchen/orders/${orderId}/complete`, { expected_ticket_versions: versions }, { idempotencyKey: createIdempotencyKey('kds-complete-order'), ...auth }),
                successMessage: 'Đã hoàn thành tất cả món trong đơn.',
                onSuccess: () => resource.reload({ silent: true }), onConflict: resource.reload,
            });
        } finally { setPendingLine(null); }
    };

    if (resource.error && !resource.data) {
        return <FnbResourceError error={resource.error} onRetry={resource.reload} title="Không tải được màn hình Bar / Bếp" />;
    }

    return (
        <div ref={boardRef} className={`fnb-page-stack fnb-kds-page ${fullscreen ? 'is-fullscreen' : ''}`}>
            <FnbPageHeader
                eyebrow="Màn hình chế biến"
                title="Bar / Bếp"
                actions={<Space wrap>{resource.updatedAt ? <Text type="secondary" className="fnb-kds-updated-time">Cập nhật lúc {new Date(resource.updatedAt).toLocaleTimeString('vi-VN')}</Text> : null}<Button icon={<FullscreenOutlined />} onClick={async () => {
                    try {
                        if (document.fullscreenElement) await document.exitFullscreen();
                        else await boardRef.current?.requestFullscreen?.();
                    } catch { /* Browser may deny fullscreen; the board remains usable. */ }
                }}>{fullscreen ? 'Thoát toàn màn hình' : 'Toàn màn hình'}</Button><Button icon={<ReloadOutlined />} loading={resource.refreshing} onClick={() => resource.reload({ silent: true })}>Đồng bộ</Button></Space>}
            />

            {resource.error ? <Alert type="warning" showIcon message="Mất kết nối tạm thời" description={`${resource.error.message} Dữ liệu đang thấy là lần đồng bộ gần nhất.`} /> : null}

            <Card className="fnb-panel">
                <div className="fnb-kds-toolbar">
                    <Segmented options={statusOptions} value={statusFilter} onChange={setStatusFilter} />
                    <Select
                        allowClear
                        value={stationId || undefined}
                        onChange={(value) => setStationId(value ?? '')}
                        placeholder="Tất cả trạm"
                        options={stations.map((station) => ({ value: station.id, label: station.name }))}
                        style={{ minWidth: 190 }}
                    />
                    <Badge status={resource.error ? 'error' : resource.refreshing ? 'processing' : 'success'} text={resource.error ? 'Mất kết nối' : resource.refreshing ? 'Đang đồng bộ' : 'Đã kết nối'} />
                </div>
            </Card>

            {kitchenView === 'completed' && tickets.length >= 300 ? <Text type="secondary">Hiển thị tối đa 300 phiếu có món đã giao gần nhất.</Text> : null}
            {visibleTickets.length ? (
                <div className="fnb-kds-board">
                    {visibleTickets.map((ticket) => {
                        const station = stations.find((item) => String(item.id) === String(ticket.prep_station_id ?? ticket.station_id));
                        const overdue = kitchenView === 'active' && Date.now() - parseServerDate(ticket.fired_at ?? ticket.created_at).getTime() > Number(station?.sla_seconds ?? 600) * 1000;
                        return (
                        <Card
                            key={ticket.public_id ?? ticket.id}
                            className={`fnb-kds-ticket ${Number(ticket.priority) > 0 || overdue ? 'is-priority' : ''}`}
                            title={<Space><Title level={5} style={{ margin: 0 }}>{ticket.ticket_no ?? `Phiếu #${ticket.id}`}</Title>{Number(ticket.priority) > 0 ? <Tag color="red">Ưu tiên</Tag> : null}</Space>}
                            extra={<Space direction="vertical" size={0} align="end"><Text strong>{ticket.table_name ?? ticket.session_label ?? ticket.order_no ?? (ticket.order_id ? `Order #${ticket.order_id}` : 'Phiếu chế biến')}</Text><Text type="secondary"><ClockCircleOutlined /> {formatDuration(ticket.fired_at ?? ticket.created_at)}</Text></Space>}
                        >
                            <div className="fnb-kds-order-actions">{kitchenView === 'active' ? <FnbCompleteOrderButton orderId={ticket.order_id} tickets={tickets} disabled={pendingLine !== null || Boolean(resource.error)} loading={pendingLine === `order-${ticket.order_id}`} onComplete={(versions) => completeOrder(ticket.order_id, versions)} /> : null}</div>
                            <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                {ticket.displayLines.map((line) => {
                                    const status = lineStatus(line);
                                    const action = nextAction(status);
                                    const modifiers = asArray(line.modifiers ?? line.modifier_snapshots);

                                    return (
                                        <div className={`fnb-kds-line is-${status}`} key={line.id}>
                                            <div className="fnb-kds-line-copy">
                                                <div className="fnb-kds-line-title">
                                                    <span className="fnb-kds-quantity">{formatQuantity(line.quantity)}×</span>
                                                    <Text strong>{line.item_name_snapshot ?? line.item_name ?? line.name}</Text>
                                                </div>
                                                {line.variant_name_snapshot && line.variant_name_snapshot !== 'Mặc định' ? <Text type="secondary">Size: {line.variant_name_snapshot}</Text> : null}
                                                {modifiers.length ? <Text type="secondary">{modifiers.map((item) => item.name_snapshot ?? item.name).join(', ')}</Text> : null}
                                                {line.note ? <div className="fnb-kds-note">Ghi chú: {line.note}</div> : null}
                                            </div>
                                            {action && can('fnb.kitchen.update') ? (
                                                <Button
                                                    type={action.status === 'ready' ? 'primary' : 'default'}
                                                    icon={action.icon}
                                                    loading={pendingLine === line.id}
                                                    disabled={pendingLine !== null || Boolean(resource.error)}
                                                    onClick={() => transition(ticket, line, action)}
                                                    className="fnb-touch-button"
                                                >
                                                    {action.label}
                                                </Button>
                                            ) : null}
                                            <FnbKitchenProgress status={status} />
                                            {status === 'served' && line.served_at ? <Text type="secondary" className="fnb-kds-completed-time">Đã giao lúc {formatDateTime(line.served_at)}</Text> : null}
                                        </div>
                                    );
                                })}
                            </Space>
                        </Card>
                    ); })}
                </div>
            ) : <Card className="fnb-panel"><Empty description="Không có món ở trạng thái đang chọn" /></Card>}
        </div>
    );
}
