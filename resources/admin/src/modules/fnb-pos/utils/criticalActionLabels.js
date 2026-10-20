const actionLabels = {
    'chain.menu.create': 'Tạo thực đơn dùng chung',
    'chain.menu.publish': 'Phát hành thực đơn',
    'chain.menu.rollback': 'Khôi phục phiên bản thực đơn',
    'chain.pricebook.save': 'Lưu bảng giá',
    'bank.account.save': 'Lưu tài khoản ngân hàng',
    'integration.mapping.save': 'Lưu liên kết dữ liệu tích hợp',
    'integration.item.save': 'Lưu món tích hợp',
    'integration.reconcile': 'Đối soát dữ liệu tích hợp',
    'chain.brand.save': 'Lưu thương hiệu',
    'chain.region.save': 'Lưu khu vực quản lý',
    'chain.outlet.save': 'Lưu điểm bán trong chuỗi',
    'chain.hq.revoke': 'Thu hồi quyền quản lý chuỗi',
    'chain.hq.assign': 'Cấp quyền quản lý chuỗi',
    'check.close_zero_charge': 'Đóng bill không thu tiền',
    onboard: 'Thiết lập điểm bán',
    'line.void': 'Hủy món',
    'check.void': 'Hủy bill',
    'check.reopen': 'Mở lại bill',
    'order.discount': 'Áp dụng giảm giá đơn hàng',
    'payment.refund': 'Hoàn tiền',
    'refund.cancel': 'Hủy yêu cầu hoàn tiền',
    'shift.reconcile': 'Đối soát ca bán hàng',
    'fulfillment.compensate': 'Xử lý bù trừ món',
    'cash.record': 'Ghi nhận thu chi tiền mặt',
    'staff.assign': 'Cấp quyền nhân viên',
    'staff.revoke': 'Thu hồi quyền nhân viên',
    'settings.save': 'Lưu cấu hình',
    'outlet.save': 'Lưu thông tin điểm bán',
    'terminal.save': 'Lưu cấu hình thiết bị bán hàng',
    'payment_method.save': 'Lưu phương thức thanh toán',
};

const policyLabels = {
    'chain.menu.change': 'Thay đổi thực đơn dùng chung',
    'settings.change': 'Thay đổi cấu hình',
    'chain.change': 'Thay đổi thông tin chuỗi',
    'chain.staff.change': 'Thay đổi quyền quản lý chuỗi',
    'check.close_zero_charge': 'Đóng bill không thu tiền',
    'outlet.change': 'Thay đổi thông tin điểm bán',
    'order.void': 'Hủy món hoặc bill',
    'order.reopen': 'Mở lại bill',
    'discount.override': 'Áp dụng giảm giá cần phê duyệt',
    'payment.refund': 'Hoàn tiền',
    'shift.reconcile': 'Đối soát ca bán hàng',
    'fulfillment.compensate': 'Bù trừ món đã phục vụ',
    'cash.adjust': 'Điều chỉnh tiền mặt',
    'staff.assign': 'Thay đổi quyền nhân viên',
    'terminal.bind_or_revoke': 'Thay đổi thiết bị bán hàng',
};

const approvalPermissionLabels = {
    'fnb.order.void.approve': 'Phê duyệt hủy món hoặc bill',
    'fnb.discount.override': 'Phê duyệt giảm giá',
    'fnb.payment.refund.approve': 'Phê duyệt hoàn tiền',
    'fnb.shift.reconcile': 'Đối soát ca bán hàng',
    'fnb.cash.adjust': 'Điều chỉnh tiền mặt',
};

const labelFor = (labels, code, fallback) => Object.hasOwn(labels, code) ? labels[code] : fallback;

export const criticalActionLabel = (code) => labelFor(actionLabels, code, 'Xác nhận thao tác đang thực hiện');
export const criticalPolicyLabel = (code) => labelFor(policyLabels, code, 'Bảo vệ thao tác quan trọng');
export const approvalPermissionLabel = (code) => labelFor(approvalPermissionLabels, code, 'Người có quyền phê duyệt thao tác này');
