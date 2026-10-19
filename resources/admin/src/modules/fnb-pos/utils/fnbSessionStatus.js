const rows = (value) => Array.isArray(value) ? value : [];
const status = (record) => record.lifecycle_status ?? record.status;
const quantity = (value) => {
    const match = String(value ?? '').match(/^(\d+)(?:\.(\d{1,6}))?$/);
    return match ? BigInt(match[1]) * 1000000n + BigInt((match[2] ?? '').padEnd(6, '0')) : null;
};

// A session can stay open/settling after payment while service and table
// release continue. Closed bills prove payment; zero collectable capacity
// alone does not, since pending payments can reserve the entire balance.
export function sessionDisplayStatus(session) {
    const lifecycle = session?.status ?? 'open';
    if (['closed', 'cancelled', 'merged'].includes(lifecycle)) return lifecycle;
    const bills = rows(session?.checks).filter((bill) => !['void', 'voided', 'cancelled'].includes(status(bill)));
    if (!bills.length || bills.some((bill) => status(bill) !== 'closed')) return lifecycle;

    const allocated = new Map();
    for (const bill of bills) {
        for (const line of rows(bill.lines)) {
            const amount = quantity(line.allocated_quantity ?? line.quantity);
            if (amount === null) return lifecycle;
            const id = String(line.order_line_id);
            allocated.set(id, (allocated.get(id) ?? 0n) + amount);
        }
    }
    for (const order of rows(session?.orders)) {
        for (const line of rows(order.lines)) {
            const ordered = quantity(line.ordered_quantity ?? line.quantity);
            const voided = quantity(line.voided_quantity ?? '0');
            if (ordered === null || voided === null || voided > ordered) return lifecycle;
            if (ordered - voided > (allocated.get(String(line.id)) ?? 0n)) return lifecycle;
        }
    }
    return 'paid';
}
