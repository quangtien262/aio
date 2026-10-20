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

export const paymentModeForMethod = (check, method) => check?.settlement_mode ?? (method?.kind === 'cash' ? 'single_cash' : 'single_non_cash');

export const paymentMethodAllowed = (check, method) => check?.settlement_mode === 'single_cash'
    ? method.kind === 'cash'
    : check?.settlement_mode === 'single_non_cash' ? method.kind !== 'cash' : true;

export function paymentMethodLabel(method, methods) {
    const label = { cash: 'Tiền mặt', transfer: 'Chuyển khoản', card: 'Cà thẻ' }[method.kind] ?? method.name;
    return methods.filter((item) => item.kind === method.kind).length > 1 ? `${label} · ${method.name}` : label;
}

export function rebalancePaymentParts(rows, changedIndex, amount, total, methods) {
    const next = rows.map((row, index) => ({ ...row, amount_minor: index === changedIndex ? amount : row.amount_minor }));
    if (rows.length > 1 && Number.isSafeInteger(amount) && amount >= 0 && amount <= total && Number.isSafeInteger(total)) {
        const others = next.map((_, index) => index).filter((index) => index !== changedIndex);
        const balanceIndex = others.pop();
        const committed = others.reduce((sum, index) => sum + (Number(next[index].amount_minor) || 0), amount);
        next[balanceIndex].amount_minor = Math.max(0, total - committed);
        let excess = Math.max(0, committed - total);
        for (const index of others.reverse()) {
            const reduction = Math.min(excess, Math.max(0, Number(next[index].amount_minor) || 0));
            next[index].amount_minor -= reduction;
            excess -= reduction;
        }
    }
    return next.map((row, index) => {
        const cash = methods.find((method) => String(method.id) === String(row.payment_method_id))?.kind === 'cash';
        if (cash && row.amount_minor !== rows[index].amount_minor && (rows[index].tendered_minor == null || rows[index].tendered_minor === rows[index].amount_minor)) {
            return { ...row, tendered_minor: row.amount_minor };
        }
        return row;
    });
}

export function paymentDefaults(check, methods) {
    const mode = check?.settlement_mode ?? (methods[0]?.kind === 'cash' ? 'single_cash' : 'single_non_cash');
    const method = methods.find((item) => mode === 'single_cash' ? item.kind === 'cash' : mode === 'single_non_cash' ? item.kind !== 'cash' : true);
    const due = serverAmountDue(check);
    return { settlement_mode: mode, payment_method_id: method?.id, amount_minor: due, tendered_minor: method?.kind === 'cash' ? due : undefined,
        payments: method ? [{ payment_method_id: method.id, amount_minor: due, tendered_minor: method.kind === 'cash' ? due : undefined }] : [] };
}

export function preparePaymentParts(check, rows, methods) {
    if (!Array.isArray(rows) || !rows.length || rows.length > 10) throw new Error('Chọn từ 1 đến 10 hình thức thanh toán.');
    const seen = new Set();
    const payments = rows.map((row) => {
        const method = methods.find((item) => String(item.id) === String(row.payment_method_id));
        if (!method || !paymentMethodAllowed(check, method) || seen.has(String(method.id))) throw new Error('Hình thức thanh toán không hợp lệ hoặc bị trùng.');
        seen.add(String(method.id));
        if (!Number.isSafeInteger(row.amount_minor) || row.amount_minor <= 0) throw new Error('Nhập số tiền nguyên lớn hơn 0 cho mỗi hình thức.');
        const tendered = tenderedAmount(row, method);
        if (!Number.isSafeInteger(tendered) || tendered < row.amount_minor) throw new Error('Khách đưa không được nhỏ hơn phần tiền mặt cần thu.');
        if (method.requires_reference && !String(row.reference ?? '').trim()) throw new Error('Nhập mã giao dịch hoặc chờ QR chuyển khoản.');
        return { payment_method_id: method.id, amount_minor: row.amount_minor, tendered_minor: tendered, reference: row.reference || null };
    });
    if (payments.reduce((total, row) => total + row.amount_minor, 0) !== serverAmountDue(check)) throw new Error('Tổng các hình thức thanh toán phải bằng số tiền cần thu.');
    if (payments.length > 1 && check?.settlement_mode && check.settlement_mode !== 'mixed') throw new Error('Bill đã chốt hình thức thu một lần.');
    return { payments, settlement_mode: check?.settlement_mode ?? (payments.length > 1 ? 'mixed' : paymentModeForMethod(check, methods.find((method) => String(method.id) === String(payments[0].payment_method_id)))) };
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
