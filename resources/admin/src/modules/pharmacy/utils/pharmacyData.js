export const recordId = (value) => String(value?.public_id ?? value?.uuid ?? value?.id ?? '');
export const resourceRows = (value) => Array.isArray(value) ? value : value?.records ?? value?.items ?? value?.data ?? [];
export const recordLabel = (value) => [value?.code ?? value?.reference, value?.name ?? value?.product_name].filter(Boolean).join(' · ') || recordId(value);
export const money = (value) => {
    if (value == null || value === '') return '—';
    try { return `${new Intl.NumberFormat('vi-VN').format(BigInt(String(value)))} ₫`; } catch { return '—'; }
};
export const quantity = (value) => value == null ? '—' : String(value).replace(/(\.\d*?[1-9])0+$|\.0+$/, '$1');
export const quantityFormatter = (value, info) => info?.userTyping ? info.input : value == null || value === '' ? '' : quantity(value);
export const unitLabel = (code) => ({ vien: 'viên', vi: 'vỉ', hop: 'hộp', chai: 'chai', ong: 'ống', goi: 'gói', tuyp: 'tuýp', lo: 'lọ', ml: 'ml' }[code] ?? code ?? '');
export const pharmacyFlag = (value) => value === true || value === 1 || value === '1';
export const dateLabel = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short' }).format(date);
};
export const statusLabels = {
    draft: 'Bản nháp', active: 'Đang hiệu lực', inactive: 'Ngừng hiệu lực', manual_review: 'Cần đối soát', preview: 'Đã kiểm tra — chưa áp dụng', proposed: 'Đề xuất', applied: 'Đã áp dụng', open: 'Đang mở', closed: 'Đã đóng', reconciled: 'Đã đối soát',
    unassigned: 'Chưa phân công', assigned: 'Đã nhận', waiting: 'Đang chờ', done: 'Hoàn tất', completed: 'Hoàn tất',
    pending: 'Chờ xử lý', needs_information: 'Cần bổ sung', approved: 'Đã duyệt', rejected: 'Từ chối', invalidated: 'Cần kiểm tra lại',
    unreserved: 'Chưa giữ hàng', reserved: 'Đã giữ hàng', picked: 'Đã chuẩn bị', prepared: 'Đã chuẩn bị', committed: 'Đã ghi nhận',
    unpaid: 'Chưa thu tiền', paid: 'Đã thu tiền', uncertain: 'Chưa rõ kết quả', refund_pending: 'Chờ hoàn tiền', refunded: 'Đã hoàn tiền',
    pending_handover: 'Chờ bàn giao', ready: 'Sẵn sàng', handed_over: 'Đã bàn giao', cancelled: 'Đã hủy', exception: 'Cần xử lý',
    reviewed: 'Đã duyệt chuyên môn', finalized: 'Đã chốt giao dịch', verified: 'Đã xác minh', unverified: 'Chưa xác minh',
    saleable: 'Được bán', quarantine: 'Cách ly', damaged: 'Hư hỏng', hold: 'Đang giữ', released: 'Đã giải phóng', recalled: 'Thu hồi',
    dispatched: 'Đã xuất chuyển', in_transit: 'Đang chuyển', received: 'Đã nhận', inspected: 'Đã kiểm', partially_received: 'Nhận một phần',
    queued: 'Chờ gửi', submitting: 'Đang gửi', accepted: 'Đã được tiếp nhận', failed: 'Không thành công', disabled: 'Chưa bật',
};
export const actionPermissions = {
    setup: 'pharmacy.settings', 'pharmacy.create': 'pharmacy.settings', 'counter.save': 'pharmacy.settings', 'product.save': 'pharmacy.product.manage', 'price.save': 'pharmacy.price.manage',
    'unit.save': 'pharmacy.product.manage', 'patient.lookup': 'pharmacy.patient.view', 'patient.save': 'pharmacy.patient.manage', 'prescription.save': 'pharmacy.prescription.manage', 'prescription.verify': 'pharmacy.prescription.manage',
    'prescription.transfer': 'pharmacy.prescription.transfer', 'prescription.transfer-revoke': 'pharmacy.prescription.transfer',
    'dispensing.create': 'pharmacy.dispensing.manage', 'dispensing.review': 'pharmacy.dispensing.review',
    'dispensing.prepare': 'pharmacy.dispensing.manage', 'dispensing.finalize': 'pharmacy.payment.collect',
    'dispensing.handover': 'pharmacy.dispensing.handover', 'dispensing.cancel': 'pharmacy.dispensing.manage',
    'dispensing.return': 'pharmacy.return.manage', 'dispensing.refund': 'pharmacy.return.manage',
    'recovery.resolve': 'pharmacy.dispensing.manage', 'dispensing.assign': 'pharmacy.dispensing.manage', 'dispensing.parties': 'pharmacy.dispensing.manage', 'dispensing.counsel': 'pharmacy.dispensing.manage', 'dispensing.handover-failed': 'pharmacy.dispensing.handover',
    'shift.open': 'pharmacy.shift.manage', 'shift.close': 'pharmacy.shift.manage', 'shift.reconcile': 'pharmacy.shift.manage',
    'receipt.create': 'pharmacy.receipt.manage', 'receipt.inspect': 'pharmacy.receipt.manage',
    'quality.hold': 'pharmacy.quality.manage', 'quality.release': 'pharmacy.quality.manage', 'recall.create': 'pharmacy.recall.manage',
    'quality.disposition': 'pharmacy.quality.manage', 'quality.destroy': 'pharmacy.quality.manage', 'quality.supplier-return': 'pharmacy.quality.manage', 'recall.confirm': 'pharmacy.recall.manage', 'temperature.record': 'pharmacy.quality.manage',
    'transfer.create': 'pharmacy.transfer.manage', 'transfer.approve': 'pharmacy.transfer.manage', 'transfer.dispatch': 'pharmacy.transfer.manage', 'transfer.receive': 'pharmacy.transfer.manage',
    'stocktake.create': 'pharmacy.stocktake.manage', 'stocktake.post': 'pharmacy.stocktake.manage',
    'purchasing.create': 'pharmacy.purchasing.manage', 'purchasing.approve': 'pharmacy.purchasing.manage', 'purchasing.receive': 'pharmacy.purchasing.manage',
    'supplier.save': 'pharmacy.purchasing.manage',
    'shift.cash-movement': 'pharmacy.shift.manage', 'replenishment.propose': 'pharmacy.purchasing.manage',
    'import.preview': 'pharmacy.product.manage', 'import.commit': 'pharmacy.product.manage', 'price.publish': 'pharmacy.price.manage', 'filter.save': 'pharmacy.view',
    'credential.save': 'pharmacy.professional.manage', 'membership.save': 'pharmacy.settings', 'settings.save': 'pharmacy.settings',
    'print.create': 'pharmacy.documents.manage', 'print.confirm': 'pharmacy.documents.manage',
    'customer.mapping.save': 'pharmacy.integrations.manage', 'accounting.mapping.save': 'pharmacy.integrations.manage', 'accounting.draft': 'pharmacy.integrations.manage', 'connection.save': 'pharmacy.integrations.manage', 'integration.queue': 'pharmacy.integrations.manage', 'integration.reconcile': 'pharmacy.integrations.manage',
};
export const hasActionPermission = (permissions, action) => permissions.includes('pharmacy.*') || permissions.includes(actionPermissions[action] ?? `pharmacy.${action}`);
export const canViewPharmacySection = (permissions, section) => permissions.includes('pharmacy.*') || permissions.includes(({ work: 'pharmacy.dispensing.view', dispensings: 'pharmacy.dispensing.view', pos: 'pharmacy.dispensing.manage', prescriptions: 'pharmacy.prescription.view', sales: 'pharmacy.sale.view', products: 'pharmacy.view', prices: 'pharmacy.price.manage', inventory: 'pharmacy.inventory.view', receipts: 'pharmacy.inventory.view', transfers: 'pharmacy.inventory.view', stocktakes: 'pharmacy.inventory.view', quality: 'pharmacy.view', purchasing: 'pharmacy.purchasing.manage', customers: 'pharmacy.patient.view', shifts: 'pharmacy.shift.manage', operations: 'pharmacy.report.view', reports: 'pharmacy.report.view', integrations: 'pharmacy.integrations.manage', settings: 'pharmacy.settings' })[section]);
export function actionAllowed(record, action) {
    if (!record || !Object.hasOwn(record, 'allowed_actions')) return true;
    const allowed = record.allowed_actions;
    return Array.isArray(allowed) ? allowed.includes(action) || allowed.includes(action.replace('dispensing.', '')) : allowed?.[action] === true;
}
export function decimalInput(value, { positive = true } = {}) {
    const text = String(value ?? '').trim();
    if (!/^\d+(?:\.\d{1,6})?$/.test(text)) throw new Error('Nhập số thập phân, tối đa 6 chữ số sau dấu chấm.');
    if (positive && !/[1-9]/.test(text)) throw new Error('Số lượng phải lớn hơn 0.');
    return text;
}
export function addDecimal(left, right) {
    const micros = (value) => { const [whole, fraction = ''] = decimalInput(value, { positive: false }).split('.'); return BigInt(whole) * 1000000n + BigInt(fraction.padEnd(6, '0')); };
    const result = micros(left) + micros(right);
    return `${result / 1000000n}.${String(result % 1000000n).padStart(6, '0')}`.replace(/\.?0+$/, '');
}
export const capabilityReady = (capability) => capability === true || Boolean(capability && capability.ready === true && capability.enabled !== false);
export const sections = [
    ['work', 'Hàng đợi', 'Hồ sơ cần xử lý và công việc của nhà thuốc'], ['pos', 'Quầy thuốc', 'Bắt đầu một hồ sơ bán hoặc cấp thuốc'],
    ['prescriptions', 'Đơn thuốc', 'Tiếp nhận, xác minh và theo dõi lượng đã cấp'], ['sales', 'Giao dịch', 'Đối soát thu tiền, cấp thuốc và bàn giao'],
    ['products', 'Hàng hóa', 'Danh mục thuốc, quy cách và mã vạch'], ['prices', 'Giá bán', 'Giá theo nhà thuốc và phiên bản áp dụng'],
    ['inventory', 'Tồn kho', 'Theo dõi khả dụng, lô và hạn dùng'], ['receipts', 'Nhập hàng', 'Nhận hàng, kiểm tra và giải phóng lô'],
    ['transfers', 'Điều chuyển', 'Yêu cầu, xuất chuyển và nhận hàng'], ['stocktakes', 'Kiểm kê', 'Đếm theo lô, đối chiếu và xử lý chênh lệch'],
    ['quality', 'Chất lượng', 'Cách ly, thu hồi và các việc cần xử lý'], ['purchasing', 'Mua hàng', 'Nhu cầu bổ sung và đơn mua'],
    ['customers', 'Người bệnh & khách', 'Hồ sơ riêng và vai trò người mua hộ'], ['shifts', 'Ca & tiền quầy', 'Mở ca, kiểm đếm và đối soát'],
    ['operations', 'Điều hành', 'Tổng quan ngoại lệ và hoạt động chuỗi'], ['reports', 'Báo cáo', 'Số liệu có phạm vi và thời điểm cập nhật'],
    ['integrations', 'Tích hợp', 'Theo dõi khả năng kết nối và kết quả liên thông'], ['settings', 'Thiết lập', 'Cơ sở, nhân sự và chính sách vận hành'],
];
export const sectionForPath = (pathname) => pathname.split('/modules/pharmacy/')[1]?.split('/')[0] || 'work';
