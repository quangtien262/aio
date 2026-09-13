import CheckCircleOutlined from '@ant-design/icons/CheckCircleOutlined';
import ClockCircleOutlined from '@ant-design/icons/ClockCircleOutlined';
import PlayCircleOutlined from '@ant-design/icons/PlayCircleOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
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
import { useCallback, useMemo, useRef, useState } from 'react';
import { createIdempotencyKey } from '../../api/fnbApi';
import FnbPageHeader from '../../components/FnbPageHeader';
import { FnbResourceError } from '../../components/FnbResourceState';
import FnbStatusTag from '../../components/FnbStatusTag';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, formatDuration, formatQuantity } from '../../utils/fnbFormat';

const { Text, Title } = Typography;

const statusOptions = [
    { value: 'active', label: 'Đang làm' },
    { value: 'waiting', label: 'Chờ pha chế' },
    { value: 'preparing', label: 'Đang pha' },
    { value: 'ready', label: 'Chờ phục vụ' },
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
    const [stationId, setStationId] = useState('');
    const [pendingLine, setPendingLine] = useState(null);
    const cursorState = useRef({ scope: '', cursor: 0 });
    const loadKitchen = useCallback(async () => {
        const scope = `${outletId}:${stationId || 'all'}`;
        if (cursorState.current.scope !== scope) {
            cursorState.current = { scope, cursor: 0 };
        }
        const requestCursor = cursorState.current.cursor;
        const result = await api.kitchen({ cursor: requestCursor, station_id: stationId || undefined, limit: 200 });
        const nextCursor = Number(result?.data?.cursor);
        if (cursorState.current.scope === scope && Number.isSafeInteger(nextCursor) && nextCursor >= requestCursor) {
            cursorState.current.cursor = nextCursor;
        }

        return result;
    }, [api, outletId, stationId]);
    const resource = useFnbResource({
        enabled: Boolean(outletId),
        loader: loadKitchen,
        deps: [outletId, stationId],
        pollMs: 1000,
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
    })).filter((ticket) => ticket.displayLines.length > 0);

    const transition = async (ticket, line, action) => {
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

    if (resource.error && !resource.data) {
        return <FnbResourceError error={resource.error} onRetry={resource.reload} title="Không tải được màn hình Bar / Bếp" />;
    }

    return (
        <div className="fnb-page-stack fnb-kds-page">
            <FnbPageHeader
                eyebrow="Màn hình chế biến"
                title="Bar / Bếp"
                description="Tự cập nhật mỗi giây. Thao tác chỉ thay đổi trạng thái chế biến, không hiển thị giá hay thông tin thanh toán."
                actions={<Button icon={<ReloadOutlined />} loading={resource.refreshing} onClick={() => resource.reload({ silent: true })}>Đồng bộ</Button>}
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
                    <Badge status={resource.refreshing ? 'processing' : 'success'} text={resource.refreshing ? 'Đang đồng bộ' : 'Đã kết nối'} />
                </div>
            </Card>

            {visibleTickets.length ? (
                <div className="fnb-kds-board">
                    {visibleTickets.map((ticket) => (
                        <Card
                            key={ticket.public_id ?? ticket.id}
                            className={`fnb-kds-ticket ${Number(ticket.priority) > 0 ? 'is-priority' : ''}`}
                            title={<Space><Title level={5} style={{ margin: 0 }}>{ticket.ticket_no ?? `Phiếu #${ticket.id}`}</Title>{Number(ticket.priority) > 0 ? <Tag color="red">Ưu tiên</Tag> : null}</Space>}
                            extra={<Space direction="vertical" size={0} align="end"><Text strong>{ticket.table_name ?? ticket.session_label ?? ticket.order_no ?? (ticket.order_id ? `Order #${ticket.order_id}` : 'Phiếu chế biến')}</Text><Text type="secondary"><ClockCircleOutlined /> {formatDuration(ticket.fired_at ?? ticket.created_at)}</Text></Space>}
                        >
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
                                                <FnbStatusTag status={status} />
                                            </div>
                                            {action && can('fnb.kitchen.update') ? (
                                                <Button
                                                    type={action.status === 'ready' ? 'primary' : 'default'}
                                                    icon={action.icon}
                                                    loading={pendingLine === line.id}
                                                    onClick={() => transition(ticket, line, action)}
                                                    className="fnb-touch-button"
                                                >
                                                    {action.label}
                                                </Button>
                                            ) : null}
                                        </div>
                                    );
                                })}
                            </Space>
                        </Card>
                    ))}
                </div>
            ) : <Card className="fnb-panel"><Empty description="Không có món ở trạng thái đang chọn" /></Card>}
        </div>
    );
}
