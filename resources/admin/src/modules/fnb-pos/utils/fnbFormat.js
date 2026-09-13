export const asArray = (value) => (Array.isArray(value) ? value : []);

export const formatMoney = (value, currency = 'VND') => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return String(value);
    }

    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency,
        maximumFractionDigits: currency === 'VND' ? 0 : 2,
    }).format(amount);
};

export const formatMinorMoney = (minor, currency = 'VND') => {
    if (minor === null || minor === undefined || minor === '') {
        return '—';
    }

    const numericMinor = Number(minor);

    if (!Number.isFinite(numericMinor)) {
        return String(minor);
    }

    // The first release only exposes VND. Keep the conversion isolated here so
    // currencies with fractional minor units can be introduced by server metadata.
    const divisor = currency === 'VND' ? 1 : 100;

    return formatMoney(numericMinor / divisor, currency);
};

export const formatQuantity = (value) => {
    const quantity = Number(value);

    if (!Number.isFinite(quantity)) {
        return value ?? '—';
    }

    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 6 }).format(quantity);
};

export const formatDateTime = (value, options = {}) => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return new Intl.DateTimeFormat('vi-VN', {
        dateStyle: options.dateStyle ?? 'short',
        timeStyle: options.timeStyle ?? 'short',
    }).format(date);
};

export const formatDuration = (startedAt, now = Date.now()) => {
    if (!startedAt) {
        return '—';
    }

    const started = new Date(startedAt).getTime();

    if (!Number.isFinite(started)) {
        return '—';
    }

    const totalMinutes = Math.max(0, Math.floor((now - started) / 60000));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    return hours ? `${hours}g ${minutes}p` : `${minutes} phút`;
};

export const compactIdentifier = (record) => record?.public_id ?? record?.id ?? null;

export const statusLabel = (status) => ({
    active: 'Đang hoạt động',
    inactive: 'Tạm ngưng',
    archived: 'Đã lưu trữ',
    open: 'Đang mở',
    opening: 'Đang mở',
    closing: 'Đang đóng',
    closed: 'Đã đóng',
    reconciled: 'Đã đối soát',
    closed_pending_reconciliation: 'Chờ đối soát',
    draft: 'Nháp',
    waiting: 'Chờ pha chế',
    preparing: 'Đang pha chế',
    ready: 'Sẵn sàng phục vụ',
    served: 'Đã phục vụ',
    completed: 'Hoàn tất',
    in_progress: 'Đang thực hiện',
    paid: 'Đã thanh toán',
    partially_paid: 'Thanh toán một phần',
    unpaid: 'Chưa thanh toán',
    voided: 'Đã hủy',
    compensation_pending: 'Đang bồi hoàn',
    cancelled_compensated: 'Đã hủy và hoàn tiền',
    finalized: 'Đã chốt',
    succeeded: 'Thành công',
    failed: 'Thất bại',
    uncertain: 'Cần đối soát',
    attention: 'Cần xử lý',
    pending: 'Đang chờ',
    processing: 'Đang xử lý',
    generated: 'Đã tạo phiếu',
    print_requested: 'Đã yêu cầu in',
    user_confirmed: 'Đã xác nhận in',
    published: 'Đã phát hành',
    available: 'Sẵn sàng',
    sold_out: 'Hết món',
}[status] ?? status ?? 'Không xác định');

export const statusColor = (status) => ({
    active: 'green',
    open: 'green',
    reconciled: 'green',
    succeeded: 'green',
    served: 'green',
    completed: 'green',
    paid: 'green',
    partially_paid: 'gold',
    unpaid: 'default',
    in_progress: 'blue',
    ready: 'cyan',
    published: 'green',
    waiting: 'gold',
    preparing: 'blue',
    pending: 'gold',
    processing: 'blue',
    generated: 'blue',
    print_requested: 'gold',
    user_confirmed: 'green',
    opening: 'blue',
    closing: 'orange',
    closed_pending_reconciliation: 'orange',
    uncertain: 'orange',
    attention: 'red',
    failed: 'red',
    voided: 'default',
    cancelled_compensated: 'purple',
    archived: 'default',
    inactive: 'default',
}[status] ?? 'default');

export const toMinor = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const normalized = String(value).replace(/[^0-9-]/g, '');
    const parsed = Number.parseInt(normalized, 10);

    return Number.isFinite(parsed) ? parsed : null;
};
