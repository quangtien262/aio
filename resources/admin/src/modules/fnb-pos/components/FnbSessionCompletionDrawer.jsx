import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import CheckOutlined from '@ant-design/icons/CheckOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import { useCallback, useState } from 'react';
import { createIdempotencyKey } from '../api/fnbApi';
import useFnbResource from '../hooks/useFnbResource';
import { useFnbWorkspace } from '../state/FnbWorkspaceContext';
import { asArray, formatQuantity } from '../utils/fnbFormat';
import { kitchenLineAction, pendingSessionOrders } from '../utils/fnbSessionCompletion';
import FnbStatusTag from './FnbStatusTag';
import FnbKitchenProgress from './FnbKitchenProgress';

const { Text } = Typography;

export default function FnbSessionCompletionDrawer({ open, session, title, refreshing, error, saving, onReload, onClose, onSubmitOrder, onRelease }) {
    const { api, outletId, terminalId, can, runCommand } = useFnbWorkspace();
    const [pending, setPending] = useState(null);
    const loadKitchen = useCallback(() => api.kitchen({ limit: 200 }), [api]);
    const kitchen = useFnbResource({ enabled: open && can('fnb.kitchen.view'), loader: loadKitchen, deps: [outletId, terminalId, session?.id], pollMs: 5000, backgroundPolling: true });
    const groups = pendingSessionOrders(session);
    const openChecks = asArray(session?.checks).filter((check) => !['closed', 'void'].includes(check.lifecycle_status ?? check.status));
    const busy = saving || Boolean(pending);
    const refresh = () => Promise.all([onReload(), can('fnb.kitchen.view') ? kitchen.reload({ silent: true, force: true }) : null]);
    const perform = async (key, action) => {
        if (pending) return;
        setPending(key);
        try { await action(); } finally { setPending(null); }
    };
    const transition = (ticket, line, action) => perform(`line-${line.id}`, () => runCommand({
        execute: (auth) => api.command(`kitchen/lines/${line.id}/transition`, {
            status: action.status,
            expected_version: ticket.version,
            expected_versions: { [`kitchen_ticket:${ticket.public_id ?? ticket.id}`]: ticket.version },
        }, { idempotencyKey: createIdempotencyKey(`pos-kitchen-${action.status}`), ...auth }),
        successMessage: action.status === 'served' ? 'Đã xác nhận giao món.' : 'Đã cập nhật trạng thái món.',
        onSuccess: refresh,
        onConflict: refresh,
    }));
    const tickets = asArray(kitchen.data?.tickets);

    return <Drawer open={open} width="min(650px, 100vw)" title={`Hoàn tất phục vụ · ${title}`} push={false}
        closable={!busy} maskClosable={!busy} keyboard={!busy} onClose={onClose} destroyOnHidden
        extra={<Button icon={<ReloadOutlined />} disabled={busy} loading={refreshing || kitchen.refreshing} onClick={() => refresh().catch(() => {})}>Cập nhật</Button>}
        footer={<Button block type="primary" size="large" icon={<CheckOutlined />} loading={saving} disabled={busy || refreshing || Boolean(error) || groups.length > 0 || openChecks.length > 0} onClick={onRelease}>Xác nhận {session?.service_type === 'dine_in' ? 'trả bàn' : 'kết thúc phiên'}</Button>}
        className="fnb-completion-drawer">
        <Alert showIcon type={groups.length ? 'warning' : 'success'} message={groups.length ? 'Cần hoàn tất các món bên dưới trước khi trả bàn' : 'Các món đã hoàn tất phục vụ'} description="Chỉ xác nhận đã giao khi khách thực sự nhận món. Trả bàn là bước riêng sau khi hoàn tất phục vụ và thanh toán." />
        {error ? <Alert type="error" showIcon message="Chưa tải được trạng thái bàn mới nhất. Hãy cập nhật lại." /> : null}
        {kitchen.error ? <Alert type="warning" showIcon message="Chưa tải được trạng thái Bar / Bếp. Hãy cập nhật trước khi đổi trạng thái món." /> : null}
        {!can('fnb.kitchen.view') || !can('fnb.kitchen.update') ? <Alert type="info" showIcon message="Cần nhân viên có quyền Bar / Bếp xác nhận trạng thái món." /> : null}
        {groups.map(({ order, lines, draft }) => <section className="fnb-completion-order" key={order.id}>
            <div className="fnb-completion-order-heading"><Text strong>{order.order_no ?? `Order #${order.id}`}</Text><FnbStatusTag status={draft ? 'draft' : 'active'} /></div>
            {lines.map((line) => {
                const ticket = tickets.find((candidate) => asArray(candidate.lines).some((item) => String(item.order_line_id) === String(line.id) && kitchenLineAction(item.status_rollup ?? item.status)));
                const ticketLine = asArray(ticket?.lines).find((item) => String(item.order_line_id) === String(line.id) && kitchenLineAction(item.status_rollup ?? item.status));
                const status = ticketLine?.status_rollup ?? ticketLine?.status ?? line.status ?? line.fulfillment_status ?? 'draft';
                const action = !draft && kitchenLineAction(status);
                return <div className="fnb-completion-line" key={line.id}>
                    <div><Text strong>{formatQuantity(line.ordered_quantity ?? line.quantity)}× {line.item_name_snapshot ?? line.item_name}</Text>
                        {line.variant_name_snapshot || line.note ? <div><Text type="secondary">{[line.variant_name_snapshot, line.note].filter(Boolean).join(' · ')}</Text></div> : null}
                    </div>
                    {action && ticketLine && can('fnb.kitchen.update') ? <Button type={action.status === 'served' ? 'primary' : 'default'} disabled={busy || refreshing || kitchen.loading || Boolean(kitchen.error)} loading={pending === `line-${ticketLine.id}`} onClick={() => transition(ticket, ticketLine, action)}>{action.label}</Button> : null}
                    {action && !ticketLine && can('fnb.kitchen.view') && !kitchen.loading ? <Text type="secondary">Cập nhật Bar / Bếp để kiểm tra</Text> : null}
                    <div className="fnb-completion-line-progress"><FnbKitchenProgress status={status} /></div>
                </div>;
            })}
            {!lines.length ? <Text type="secondary">Order chưa kết thúc. Cập nhật lại hoặc kiểm tra chi tiết order.</Text> : null}
            {draft ? <Space direction="vertical" style={{ width: '100%' }}><Text type="secondary">Order nháp cần được kiểm tra và gửi Bar / Bếp, hoặc xóa món nháp trong chi tiết phiên nếu khách không dùng.</Text>
                {can('fnb.order.submit') && lines.length > 0 ? <Button disabled={busy || refreshing} loading={pending === `order-${order.id}`} onClick={() => perform(`order-${order.id}`, async () => { await onSubmitOrder(order); if (can('fnb.kitchen.view')) await kitchen.reload({ silent: true, force: true }); })}>Gửi Bar / Bếp</Button> : null}
            </Space> : null}
        </section>)}
        {!groups.length ? <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Không còn món chờ xử lý" /> : null}
        {openChecks.length ? <Alert type="warning" showIcon message={`Còn ${openChecks.length} bill chưa hoàn tất`} description="Quay lại chi tiết phiên để hoàn tất thanh toán hoặc xử lý giao dịch trước khi trả bàn." /> : null}
        <Button block onClick={onClose} disabled={busy}>Quay lại chi tiết phiên</Button>
    </Drawer>;
}
