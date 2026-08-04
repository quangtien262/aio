# Admin Access Control

> Trạng thái: đã triển khai và migrate trên database local ngày 2026-07-21. Đây là tài liệu nguồn chuẩn cho mọi thay đổi auth/RBAC tiếp theo.

## Mục tiêu

Hệ thống dùng một codebase riêng cho mỗi khách hàng, không có tenant/owner runtime. Phạm vi dữ liệu quản trị chỉ gồm `global` và `website`; quyền theo website được gắn ngay trên từng lần cấp role để tránh role và scope lệch nhau.

## Bất biến hệ thống

- Admin ID `1` là System Owner, luôn hoạt động, không thể khóa, sửa hoặc xóa qua ứng dụng.
- Role key `super-admin` là role hệ thống, không thể đổi key, sửa permission, cấp thủ công hoặc xóa.
- System Owner và mọi tài khoản có role hệ thống `super-admin` luôn có toàn bộ permission đang hoạt động, kể cả permission được module bổ sung sau này.
- Role key `platform-owner` là role toàn quyền có thể gán cho tài khoản bàn giao khách hàng. Role này nhận toàn bộ permission active và được đồng bộ khi module mới bổ sung permission, nhưng không phải role hệ thống và không kích hoạt bypass `isSuperAdmin()`.
- Module bị gỡ không xóa permission lịch sử; permission được đánh dấu inactive/deprecated để audit log và role cũ vẫn truy vết được.
- Không dùng lại `tenant_key`, `owner_key`, `admin_role` hay `admin_role_scopes`.

## Mô hình dữ liệu

- `admins`: trạng thái, khóa tài khoản, bắt buộc đổi mật khẩu, `auth_version`, lần đăng nhập gần nhất và dữ liệu TOTP được mã hóa.
- `roles`: role hệ thống/role có thể cấp, trạng thái hoạt động.
- `permissions`: key bất biến, module, mức rủi ro, trạng thái và thời điểm deprecated.
- `admin_role_assignments`: một dòng chứa đồng thời admin, role và scope `global|website`; có thể đặt thời hạn.
- `audit_logs`: actor, action, module, website, target, before/after đã lọc dữ liệu nhạy cảm, IP, user agent và request ID.

Migration chốt kiến trúc:

- `2026_07_21_000001_rebuild_admin_access_control.php`: tạo schema mới, backfill assignment cũ, xóa bảng/cột scope legacy và đăng ký permission bảo mật mới.
- `2026_07_21_000002_enforce_system_owner_invariants.php`: cố định admin ID 1, role `super-admin`, assignment global duy nhất và thu hồi session cũ.

Không sửa lại migration đã chạy để thay đổi hành vi production. Nếu cần thay đổi schema/invariant tiếp theo, tạo migration mới.

## Luồng xác thực

- Login bị giới hạn 5 lần/phút theo định danh + IP và trả lỗi chung khi sai/khóa để không lộ trạng thái tài khoản.
- Mật khẩu admin mới/reset phải có tối thiểu 12 ký tự, chữ hoa/thường, số và ký hiệu; lần đăng nhập đầu buộc đổi mật khẩu.
- `auth_version` thu hồi toàn bộ session cũ khi đổi/reset mật khẩu, đổi role/scope, khóa tài khoản hoặc thay đổi TOTP.
- TOTP chuẩn RFC 6238 có mã khôi phục dùng một lần. Secret và danh sách mã băm được mã hóa ở database.
- Session mặc định hết hạn sau 120 phút; production có thể cấu hình bằng `SESSION_LIFETIME`.

## Quy tắc phát triển module

- Permission key theo dạng `<module>.<resource>.<action>` và không được tái sử dụng cho ý nghĩa khác.
- Route admin phải có middleware permission cụ thể; truy vấn dữ liệu website vẫn phải đi qua `HasWebsiteScope`.
- Tác vụ quản trị quan trọng phải ghi audit log, không ghi mật khẩu, token, TOTP secret hay recovery code.
- UI chỉ hiển thị thao tác được phép, nhưng backend/Gate/middleware luôn là lớp quyết định cuối cùng.
- Với module nhân sự và tiền lương, đọc thêm `docs/architecture/hrm-and-payroll-modules.md`; đặc biệt phải giữ tách biệt giữa tài khoản `admins`, hồ sơ `hrm_employees`, RBAC và policy dữ liệu self-service.

## Bản đồ implementation

### Backend

- Quyền hiệu lực và website scope: `app/Models/Admin.php`.
- Bảo vệ invariant role hệ thống: `app/Models/Role.php`.
- CRUD tài khoản và assignment: `app/Http/Controllers/Admin/Api/AdminAccountController.php`.
- CRUD role/permission: `RoleManagementController.php`, `AdminRoleAssignmentController.php`.
- Kiểm tra trạng thái/session: `EnsureAdminAccountIsActive.php`.
- Chặn website ngoài phạm vi: `EnsureAdminWebsiteAccess.php`.
- TOTP/recovery code: `AdminTwoFactorController.php`, `app/Support/Totp.php`.
- Audit: `app/Support/AuditLogger.php`, `AuditLogIndexController.php`.
- Gate và rate limiter: `app/Providers/AppServiceProvider.php`.

### Admin UI

- Route/navigation shell: `resources/admin/src/layouts/AdminLayout.jsx`, `shared/config/navigation.js`.
- Quản lý tài khoản: `resources/admin/src/modules/admins/`.
- Quản lý role/quyền: `resources/admin/src/modules/access/`.
- Nhật ký bảo mật: `resources/admin/src/pages/routes/AuditLogsRoutePage.jsx`.
- Đổi mật khẩu bắt buộc: `shared/components/ChangePasswordModal.jsx`.
- Xác thực hai lớp: `shared/components/TwoFactorModal.jsx`.

### API quan trọng

- `GET/POST /admin/api/admins`, `PUT /admin/api/admins/{admin}`.
- `PUT /admin/api/admins/{admin}/password`, `POST .../sessions/revoke`, `POST .../lock|unlock`.
- `GET /admin/api/access`, CRUD `/admin/api/roles` và `PUT /admin/api/admins/{admin}/roles`.
- `GET /admin/api/audit-logs`.
- `PUT /admin/api/me/password`.
- `POST /admin/api/me/two-factor/setup`, `POST .../confirm`, `DELETE /admin/api/me/two-factor`.

## Quy tắc không được phá vỡ

- Không dựa vào việc ẩn nút ở React để bảo mật; route/backend luôn phải kiểm permission.
- Không gắn role và website scope ở hai request/bảng độc lập.
- Không cho phép payload client tự quyết định `website_key` khi ghi dữ liệu; lấy từ `SiteContext`.
- Khi cần query xuyên website, dùng `withoutGlobalScope('current_website')` có chủ đích và vẫn kiểm quyền trước khi ghi/xóa.
- Không log request thô nếu có password, token, TOTP hoặc recovery code.
- Không dùng `admin_role`/`admin_role_scopes` cho code mới.

## Kiểm thử bắt buộc

Sau thay đổi auth/RBAC phải chạy tối thiểu:

```bash
php artisan test tests/Feature/AuthSplitTest.php tests/Feature/AdminFoundationApiTest.php tests/Feature/AccessControlSecurityTest.php
npm run build
```

Các test cần tiếp tục bảo vệ: System Owner/super-admin bất biến, phân tách website, thu hồi session, mật khẩu hiện tại, loại bỏ schema legacy và TOTP bắt buộc sau khi bật.

## Vận hành production

1. Đặt `AIO_SYSTEM_OWNER_PASSWORD` trước lần seed đầu tiên ở môi trường không phải local/testing.
2. Chạy migration và seed; seeder không ghi đè mật khẩu System Owner đã tồn tại.
3. Đăng nhập ID 1, đổi mật khẩu bắt buộc và bật xác thực hai lớp.
4. Tạo role nghiệp vụ tối thiểu theo công việc, cấp theo website nếu không cần quyền global.
5. Theo dõi mục `Nhật ký bảo mật`, sao lưu database và kiểm tra định kỳ các tài khoản/assignment hết hạn.

## Checklist cho AI trước khi sửa

- Đọc đầy đủ file này và `docs/ai-session-bootstrap-prompt.md`.
- Xác nhận thay đổi có giữ bất biến admin ID 1 và role `super-admin` hay không.
- Xác nhận permission mới có key ổn định, thuộc đúng module và được route middleware kiểm tra.
- Nếu dữ liệu thuộc website, kiểm tra cả request `X-Website-Key`, `SiteContext`, global scope và truy cập ID trái phạm vi.
- Nếu thay đổi password/role/scope/trạng thái/TOTP, đánh giá có cần tăng `auth_version` để thu hồi session hay không.
- Ghi audit cho thao tác bảo mật quan trọng và kiểm tra payload đã được lọc dữ liệu nhạy cảm.
- Cập nhật test bảo mật cùng lúc với code; không chỉ kiểm tra UI.
