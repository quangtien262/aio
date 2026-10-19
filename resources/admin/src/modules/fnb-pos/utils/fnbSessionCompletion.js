const activeStatuses = new Set(['waiting', 'preparing', 'ready']);

export function pendingSessionOrders(session) {
    return (Array.isArray(session?.orders) ? session.orders : [])
        .filter((order) => ['draft', 'active'].includes(order.lifecycle_status ?? order.status))
        .map((order) => ({
            order,
            draft: (order.lifecycle_status ?? order.status) === 'draft',
            lines: (Array.isArray(order.lines) ? order.lines : []).filter((line) => {
                const status = line.status ?? line.fulfillment_status ?? 'draft';
                return (order.lifecycle_status ?? order.status) === 'draft' ? status === 'draft' : activeStatuses.has(status);
            }),
        }));
}

export function kitchenLineAction(status) {
    return {
        waiting: { status: 'preparing', label: 'Bắt đầu pha' },
        preparing: { status: 'ready', label: 'Đã làm xong' },
        ready: { status: 'served', label: 'Đã giao món' },
    }[status] ?? null;
}
