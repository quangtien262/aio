# Kiến trúc module Quản lý nhân sự và Tiền lương

## Phạm vi đã triển khai

Hệ thống có hai package độc lập trong App Store:

- `hrm`: HRM Core, quản lý cơ cấu tổ chức, hồ sơ nhân sự, hợp đồng, liên kết tài khoản, nghỉ phép, chấm công và cổng thông tin cá nhân.
- `payroll`: add-on tiền lương, phụ thuộc `hrm`, quản lý kỳ lương, phiếu lương và cổng xem phiếu lương cá nhân.

HRM không tạo một hệ thống đăng nhập riêng. `admins` vẫn là danh tính đăng nhập duy nhất; `hrm_employees.admin_id` là liên kết nullable, unique từ hồ sơ nhân sự tới tài khoản. Nhân sự không cần đăng nhập vẫn có thể tồn tại trong HRM.

## Ranh giới trách nhiệm

- `admins`: xác thực, khóa tài khoản, đổi mật khẩu, TOTP và thu hồi phiên đăng nhập.
- `hrm_employees`: danh tính nghiệp vụ của người lao động.
- RBAC: quyết định người dùng được thực hiện loại thao tác nào.
- Policy trong controller/query: quyết định người dùng được xem dữ liệu của ai.
- HRM là dữ liệu doanh nghiệp toàn cục, không gắn `website_key`. Một nhân viên truyền thông có thể dùng cùng tài khoản, nhận thêm role CMS theo website.

Không thêm `tenant`, `owner`, `tenant_key` hoặc `owner_key` vào HRM/Payroll.

## Lifecycle an toàn

- Mọi API HRM/Payroll đều qua middleware `module.enabled:{key}`. Module bị tắt trả về 404 và không thể gọi trực tiếp API.
- Khi disable, permission của module chuyển `is_active=false`; khi enable, permission được đồng bộ active trở lại.
- HRM và Payroll đặt `lifecycle.uninstall=false`. Dữ liệu nhân sự và lương không được rollback/xóa từ App Store.
- Payroll chỉ cài được khi HRM đã được cài và bật.
- Dữ liệu vẫn giữ nguyên qua chu trình disable/enable.

## Mô hình dữ liệu

HRM:

- `hrm_departments`: phòng ban dạng cây.
- `hrm_positions`: chức vụ.
- `hrm_employees`: hồ sơ nhân sự và liên kết `admin_id`.
- `hrm_contracts`: hợp đồng và lương cơ bản.
- `hrm_leave_requests`: đơn nghỉ phép và dấu vết người duyệt.
- `hrm_attendance_records`: ngày công, giờ vào/ra, số giờ, nguồn dữ liệu và người cập nhật.

Payroll:

- `payroll_periods`: kỳ lương theo trạng thái `draft -> approved -> published -> locked`.
- `payroll_payslips`: phiếu lương một nhân sự trong một kỳ; unique theo cặp kỳ lương/nhân sự.
- `payroll_payslip_lines`: dòng thu nhập/khấu trừ chi tiết.

Phiếu lương lưu `snapshot` thông tin nhân sự tại thời điểm tính để lịch sử không thay đổi khi hồ sơ hiện tại đổi phòng ban/chức vụ.

## Role mặc định

Khi cài/bật module, lifecycle hook đồng bộ các role:

- `hrm.employee-self`: hồ sơ cá nhân, tạo đơn nghỉ, xem chấm công của mình.
- `hrm.staff`: nghiệp vụ HR, hồ sơ/hợp đồng/tổ chức/nghỉ phép/chấm công.
- `hrm.manager`: toàn quyền HRM.
- `payroll.employee-self`: chỉ xem phiếu lương đã công bố của mình.
- `payroll.officer`: lập kỳ và phiếu lương nhưng không phê duyệt/công bố.
- `payroll.approver`: phê duyệt, công bố và khóa kỳ lương.

Các quyền lương là `sensitive`; quyền duyệt/công bố/khóa là `critical`. System Owner ID 1 vẫn có toàn quyền theo invariant chung của hệ thống.

## Chính sách dữ liệu và bảo mật

- Nhân viên chỉ xem/cập nhật trường liên hệ cho hồ sơ gắn đúng `admin_id` của mình.
- Nhân viên chỉ thấy đơn nghỉ và bản chấm công của mình.
- Nhân viên chỉ thấy phiếu lương có trạng thái `published` hoặc `locked` của mình.
- API quản trị phiếu lương không cấp cho role self-service.
- Kỳ lương chỉ sửa khi `draft`; chuyển trạng thái một chiều, mỗi bước kiểm tra permission riêng.
- Khi kết thúc làm việc, hồ sơ chuyển `terminated`, tài khoản liên kết bị archive/khóa và tăng `auth_version` để thu hồi phiên cũ.
- Tất cả thao tác ghi quan trọng được ghi `AuditLogger` theo module `hrm` hoặc `payroll`.

Tài liệu/hợp đồng dạng file trong giai đoạn tiếp theo phải dùng private storage và tải qua endpoint có authorization; không lưu tài liệu nhân sự nhạy cảm trực tiếp dưới `public/files`.

## Điểm neo trong source code

- Manifest: `modules/Hrm/module.json`, `modules/Payroll/module.json`.
- Migration module: `modules/Hrm/database/migrations`, `modules/Payroll/database/migrations`.
- Lifecycle hooks: `modules/Hrm/Hooks/HrmLifecycleHook.php`, `modules/Payroll/Hooks/PayrollLifecycleHook.php`.
- Middleware: `app/Http/Middleware/EnsureModuleIsEnabled.php`.
- API: `app/Http/Controllers/Admin/Api/Hrm`, `app/Http/Controllers/Admin/Api/Payroll`.
- Models: `app/Models/Hrm*.php`, `app/Models/Payroll*.php`.
- Admin UI: `resources/admin/src/modules/hrm`, `resources/admin/src/modules/payroll`.
- Routes: `routes/admin.php`.
- Regression tests: `tests/Feature/HrmModuleTest.php`.

## Hướng mở rộng không phá kiến trúc

- Thiết bị chấm công/import Excel chỉ ghi thêm `source` và đi qua service chuẩn hóa trước khi upsert `hrm_attendance_records`.
- Số dư phép nên là ledger phát sinh, không chỉ là một số dư có thể ghi đè.
- Payroll nâng cao nên tách rule engine, thuế/bảo hiểm và batch calculation khỏi controller hiện tại.
- Quy trình duyệt nhiều cấp nên dùng workflow engine dùng chung cho HRM, Purchasing và Accounting.
- Employee document, export lương và thông báo cần bổ sung hàng đợi, mã hóa/private storage và audit tải file.

