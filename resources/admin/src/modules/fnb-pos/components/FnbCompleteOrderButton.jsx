import CheckOutlined from '@ant-design/icons/CheckOutlined';
import Button from 'antd/es/button';
import Popconfirm from 'antd/es/popconfirm';
import { asArray } from '../utils/fnbFormat';
import { useFnbWorkspace } from '../state/FnbWorkspaceContext';

export default function FnbCompleteOrderButton({ orderId, tickets, disabled, loading, onComplete }) {
    const { can } = useFnbWorkspace();
    const pendingTickets = asArray(tickets).filter((ticket) => String(ticket.order_id) === String(orderId)
        && asArray(ticket.lines).some((line) => ['waiting', 'preparing', 'ready'].includes(line.status_rollup ?? line.status)));
    if (!can('fnb.kitchen.update') || !pendingTickets.length) return null;
    const versions = Object.fromEntries(pendingTickets.map((ticket) => [ticket.id, ticket.version]));
    return <Popconfirm title="Hoàn thành tất cả món trong đơn?"
        description="Chỉ xác nhận khi khách đã nhận đủ món ở tất cả các trạm."
        okText="Xác nhận hoàn thành" cancelText="Quay lại" disabled={disabled || loading}
        getPopupContainer={() => document.fullscreenElement || document.body}
        onConfirm={() => onComplete(versions)}>
        <Button icon={<CheckOutlined />} type="primary" disabled={disabled} loading={loading}>Hoàn thành</Button>
    </Popconfirm>;
}
