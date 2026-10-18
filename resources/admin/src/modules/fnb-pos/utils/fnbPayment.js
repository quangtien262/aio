export const serverAmountDue = (check) => {
    const explicit = check?.available_to_collect_minor ?? check?.balance_due_minor ?? check?.amount_due_minor;
    if (explicit !== null && explicit !== undefined) return Number(explicit);
    const total = check?.settlement_total_minor ?? check?.grand_total_minor ?? check?.total_minor;
    if (total === null || total === undefined) return null;
    const projection = check?.projection ?? check?.financial_projection;
    if (projection) return Math.max(0, Number(total) - Number(projection.gross_paid_total_minor ?? 0));
    if (Array.isArray(check?.payments) && check.payments.length) return null;
    return Number(total);
};

export const isZeroCharge = (check) => {
    const total = check?.settlement_total_minor ?? check?.grand_total_minor;
    return total !== null && total !== undefined && Number(total) === 0;
};

export function paymentDefaults(check, methods) {
    const mode = check?.settlement_mode ?? (methods[0]?.kind === 'cash' ? 'single_cash' : 'single_non_cash');
    const method = methods.find((item) => mode === 'single_cash' ? item.kind === 'cash' : mode === 'single_non_cash' ? item.kind !== 'cash' : true);
    const due = serverAmountDue(check);
    return { settlement_mode: mode, payment_method_id: method?.id, amount_minor: due, tendered_minor: method?.kind === 'cash' ? due : undefined };
}

export function tenderedAmount(values, method) {
    return method?.kind === 'cash' ? (values.tendered_minor ?? values.amount_minor) : values.amount_minor;
}

export function refundAllocations(lines, selectedIds, quantities = {}) {
    const micros = (value) => {
        const match = String(value ?? '').match(/^(\d+)(?:\.(\d{1,6}))?$/);
        return match ? BigInt(match[1]) * 1000000n + BigInt((match[2] ?? '').padEnd(6, '0')) : -1n;
    };
    return lines.filter((line) => selectedIds.some((id) => String(id) === String(line.id))).map((line) => {
        const quantity = String(quantities[String(line.id)] ?? '');
        const limit = line.refundable_quantity ?? line.allocated_quantity ?? line.quantity;
        if (micros(quantity) <= 0n || micros(quantity) > micros(limit)) throw new Error('Số lượng hoàn vượt phần còn lại của dòng bill.');
        return { check_line_id: line.id, quantity };
    });
}
