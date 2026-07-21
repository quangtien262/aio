const ACRONYM_SEGMENTS = {
    api: 'API',
    cms: 'CMS',
    rbac: 'RBAC',
    seo: 'SEO',
    sms: 'SMS',
    ui: 'UI',
};

const FRIENDLY_SEGMENTS = {
    account: 'Tài khoản',
    assign: 'Gán quyền',
    category: 'Danh mục',
    create: 'Thêm mới',
    complete: 'Hoàn tất cấu hình',
    delete: 'Xóa',
    disable: 'Tắt',
    enable: 'Bật',
    export: 'Xuất dữ liệu',
    install: 'Cài đặt',
    lock: 'Khóa',
    manage: 'Quản lý toàn bộ',
    media: 'Thư viện hình ảnh',
    menu: 'Menu website',
    newsletter: 'Đăng ký nhận tin',
    order: 'Đơn hàng',
    page: 'Trang nội dung',
    partner: 'Đối tác',
    post: 'Bài viết',
    product: 'Sản phẩm',
    project: 'Dự án',
    publish: 'Xuất bản',
    reset_password: 'Đặt lại mật khẩu',
    role: 'Vai trò',
    scope: 'Phạm vi truy cập',
    service: 'Dịch vụ',
    settings: 'Thiết lập',
    team: 'Nhân sự',
    testimonial: 'Cảm nhận khách hàng',
    uninstall: 'Gỡ cài đặt',
    unlock: 'Mở khóa',
    update: 'Chỉnh sửa',
    view: 'Xem',
};

function formatSegment(segment) {
    const normalizedSegment = String(segment ?? '').trim().toLowerCase();

    if (FRIENDLY_SEGMENTS[normalizedSegment]) {
        return FRIENDLY_SEGMENTS[normalizedSegment];
    }

    if (ACRONYM_SEGMENTS[normalizedSegment]) {
        return ACRONYM_SEGMENTS[normalizedSegment];
    }

    return String(segment ?? '')
        .replace(/[-_]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

export function formatPermissionLabel(permissionKey) {
    const segments = String(permissionKey ?? '')
        .split('.')
        .filter(Boolean);
    const businessSegments = segments.slice(1);

    if (!businessSegments.length) {
        return segments.map(formatSegment).join(' · ');
    }

    return businessSegments.map(formatSegment).join(' · ');
}
