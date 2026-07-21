# Kiến trúc module Quản lý nhân sự và Tiền lương

> Trạng thái: phiên bản nền tảng `1.0.0` đã được triển khai ngày 2026-07-21. Đây là tài liệu nguồn chuẩn cho mọi thay đổi HRM/Payroll tiếp theo. AI session mới phải đọc file này cùng `docs/ai-session-bootstrap-prompt.md` và `docs/architecture/admin-access-control.md` trước khi sửa code.

## Tóm tắt tiếp quản cho AI session mới

1. `hrm` và `payroll` là hai module App Store độc lập; `payroll` phụ thuộc `hrm`.
2. Module chỉ có route/API hoạt động khi `module_installations.status = enabled`. API trả 404 khi module chưa cài, đang tắt hoặc dependency chưa hợp lệ là hành vi bảo mật có chủ đích, không phải route bị thiếu.
3. Không chạy riêng migration trong `modules/` bằng tay. Cài module qua App Store/API lifecycle để migration, permission, role và hook được đồng bộ cùng nhau.
4. `admins` là tài khoản đăng nhập; `hrm_employees` là hồ sơ nghiệp vụ. Không tạo guard hoặc bảng tài khoản HR riêng.
5. RBAC trả lời “được làm gì”; policy/query trong controller trả lời “được xem dữ liệu của ai”. Không được thay policy bằng việc chỉ ẩn menu/nút ở React.
6. Dữ liệu HRM/Payroll là dữ liệu toàn doanh nghiệp (`global`), không gắn `website_key`, tenant hoặc owner.
7. Không cho uninstall hai module từ App Store. Disable chỉ chặn runtime và làm permission inactive, tuyệt đối không xóa dữ liệu.
8. Test bảo mật tối thiểu là `tests/Feature/HrmModuleTest.php`; sau thay đổi UI phải chạy thêm `npm run build`.

## Phạm vi đã triển khai

Hệ thống có hai package độc lập trong App Store:

- `hrm`: HRM Core, quản lý cơ cấu tổ chức, hồ sơ nhân sự, hợp đồng, liên kết tài khoản, nghỉ phép, chấm công và cổng thông tin cá nhân.
- `payroll`: add-on tiền lương, phụ thuộc `hrm`, quản lý kỳ lương, phiếu lương và cổng xem phiếu lương cá nhân.

HRM không tạo một hệ thống đăng nhập riêng. `admins` vẫn là danh tính đăng nhập duy nhất; `hrm_employees.admin_id` là liên kết nullable, unique từ hồ sơ nhân sự tới tài khoản. Nhân sự không cần đăng nhập vẫn có thể tồn tại trong HRM.

### Chức năng hiện có

| Phân hệ | Chức năng đã có |
| --- | --- |
| HRM Core | Tổng quan, phòng ban dạng cây, chức vụ, hồ sơ nhân sự, quản lý trực tiếp, hợp đồng, liên kết tài khoản admin, nghỉ phép, chấm công, hồ sơ cá nhân |
| Payroll | Kỳ lương, nhập/cập nhật phiếu lương theo nhân sự, dòng thu nhập/khấu trừ, duyệt/công bố/khóa kỳ lương, phiếu lương cá nhân |
| Nền tảng | Manifest App Store, dependency, migration theo lifecycle, menu động, permission/role mặc định, audit log và middleware module-enabled |

Chưa được xem là hoàn thiện cho production payroll: công thức thuế/bảo hiểm, rule engine, batch calculation, import máy chấm công, ledger phép, file tài liệu riêng tư, export và workflow nhiều cấp. Các phần này phải mở rộng trên cấu trúc hiện tại, không nhồi thêm logic vào controller.

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

### Cách cài/bật đúng

Thực hiện bằng màn hình App Store hoặc các endpoint lifecycle với tài khoản có quyền quản trị module:

1. Cài và bật `hrm`.
2. Cài và bật `payroll` sau khi HRM đã enabled.
3. Kiểm tra `module_installations` bằng cột `key` và `status` (không có cột `module_key`).
4. Kiểm tra menu được trả về theo permission của tài khoản hiện tại.

Các endpoint nghiệp vụ đều nằm dưới `/admin/api/hrm/*` hoặc `/admin/api/payroll/*`. Nếu truy cập trực tiếp `/admin/payroll/my-payslips` khi Payroll chưa enabled, UI phải báo module chưa được bật và không gọi API lặp lại. Không tự động cài/bật module chỉ vì người dùng mở URL.

### Disable, enable và nâng cấp

- Disable: chặn toàn bộ API bằng 404, permission inactive, dữ liệu và role assignment được giữ lại.
- Enable: đồng bộ lại permission/role mặc định; Payroll gán role self-service cho các hồ sơ đã có `admin_id`.
- Upgrade: chạy migration mới và gọi lại lifecycle hook. Không sửa migration phiên bản đã phát hành; tạo migration mới trong đúng package module.
- Uninstall: manifest khai báo `false`; mọi nỗ lực gỡ phải bị từ chối.

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

### Bất biến dữ liệu quan trọng

- `hrm_employees.employee_code`, `hrm_departments.code`, `hrm_positions.code`, `hrm_contracts.contract_number` và `payroll_periods.code` là duy nhất.
- Mỗi admin chỉ liên kết tối đa một hồ sơ nhân sự (`hrm_employees.admin_id` unique); liên kết được phép null.
- Một nhân sự chỉ có một bản chấm công mỗi ngày.
- Một nhân sự chỉ có một phiếu lương trong một kỳ.
- `manager_employee_id` không được trỏ về chính hồ sơ hiện tại.
- Phiếu lương lịch sử phải đọc từ `snapshot` khi cần thông tin tại thời điểm chốt, không dựa hoàn toàn vào hồ sơ nhân sự đang thay đổi.

## Liên kết tài khoản và offboarding

- Endpoint gán tài khoản: `POST /admin/api/hrm/employees/{employee}/account`, yêu cầu `hrm.employee.account.assign`.
- Chỉ liên kết tới `admins`; không tạo tài khoản storefront/customer cho nhân viên.
- Khi gán tài khoản, HRM cấp role `hrm.employee-self`; nếu Payroll đang tồn tại thì cấp thêm `payroll.employee-self`.
- Role công việc khác, ví dụ nhân viên truyền thông được đăng bài, được cấp riêng bằng RBAC (`cms.*`) và có thể mang scope website. Không đưa quyền CMS vào role HRM mặc định.
- Khi archive/kết thúc làm việc, hồ sơ chuyển `terminated`; tài khoản liên kết bị archive/khóa và tăng `auth_version` để thu hồi phiên đăng nhập cũ.
- Không xóa cứng hồ sơ nhân sự chỉ để vô hiệu hóa tài khoản.

## Role mặc định

Khi cài/bật module, lifecycle hook đồng bộ các role:

- `hrm.employee-self`: hồ sơ cá nhân, tạo đơn nghỉ, xem chấm công của mình.
- `hrm.staff`: nghiệp vụ HR, hồ sơ/hợp đồng/tổ chức/nghỉ phép/chấm công.
- `hrm.manager`: toàn quyền HRM.
- `payroll.employee-self`: chỉ xem phiếu lương đã công bố của mình.
- `payroll.officer`: lập kỳ và phiếu lương nhưng không phê duyệt/công bố.
- `payroll.approver`: phê duyệt, công bố và khóa kỳ lương.

Các quyền lương là `sensitive`; quyền duyệt/công bố/khóa là `critical`. System Owner ID 1 vẫn có toàn quyền theo invariant chung của hệ thống.

### Ma trận quyền chính

| Nhóm | Quyền tiêu biểu | Ý nghĩa |
| --- | --- | --- |
| Hồ sơ HR | `hrm.employee.view/create/update/archive` | Quản lý vòng đời hồ sơ |
| Dữ liệu nhạy cảm | `hrm.employee.sensitive.view` | Xem trường định danh/cá nhân nhạy cảm |
| Tài khoản | `hrm.employee.account.assign` | Gán tài khoản đăng nhập cho nhân sự |
| Tổ chức/hợp đồng | `hrm.organization.manage`, `hrm.contract.view/manage` | Cơ cấu và hợp đồng |
| Nghỉ phép | `hrm.leave.request/team.view/approve` | Tự gửi, xem đội nhóm, phê duyệt |
| Chấm công | `hrm.attendance.self.view/view/manage` | Tự xem, xem toàn bộ, cập nhật |
| Self-service | `hrm.profile.self.view/update` | Hồ sơ của chính tài khoản đăng nhập |
| Kỳ lương | `payroll.dashboard.view`, `payroll.period.manage` | Xem và quản lý kỳ |
| Quy trình lương | `payroll.run.calculate/review/approve/publish/lock` | Tách nhiệm vụ lập–duyệt–công bố–khóa |
| Phiếu lương | `payroll.payslip.view`, `payroll.payslip.self.view` | Toàn bộ hoặc chỉ của mình |

Nguyên tắc phân tách nhiệm vụ: `payroll.officer` không có quyền approve/publish/lock; `payroll.approver` không mặc nhiên có quyền calculate. Không gộp hai role này thành một role rộng nếu khách hàng cần kiểm soát nội bộ.

## Chính sách dữ liệu và bảo mật

- Nhân viên chỉ xem/cập nhật trường liên hệ cho hồ sơ gắn đúng `admin_id` của mình.
- Nhân viên chỉ thấy đơn nghỉ và bản chấm công của mình.
- Nhân viên chỉ thấy phiếu lương có trạng thái `published` hoặc `locked` của mình.
- API quản trị phiếu lương không cấp cho role self-service.
- Kỳ lương chỉ sửa khi `draft`; chuyển trạng thái một chiều, mỗi bước kiểm tra permission riêng.
- Khi kết thúc làm việc, hồ sơ chuyển `terminated`, tài khoản liên kết bị archive/khóa và tăng `auth_version` để thu hồi phiên cũ.
- Tất cả thao tác ghi quan trọng được ghi `AuditLogger` theo module `hrm` hoặc `payroll`.

Tài liệu/hợp đồng dạng file trong giai đoạn tiếp theo phải dùng private storage và tải qua endpoint có authorization; không lưu tài liệu nhân sự nhạy cảm trực tiếp dưới `public/files`.

### Quy tắc trạng thái Payroll

Luồng kỳ lương chỉ đi một chiều:

`draft -> approved -> published -> locked`

- Chỉ kỳ `draft` được sửa dữ liệu nền.
- Mỗi transition kiểm tra permission tương ứng ở backend.
- Self-service chỉ trả phiếu có trạng thái `published` hoặc `locked` và thuộc hồ sơ có `admin_id` đúng với tài khoản hiện tại.
- Endpoint quản trị `/payroll/payslips` không được dùng làm nguồn cho trang “Phiếu lương của tôi”.
- Không cho phép client truyền `employee_id` để vượt policy self-service.

## API và màn hình hiện có

### HRM API

- `GET /admin/api/hrm/dashboard`
- CRUD có kiểm quyền tại `/admin/api/hrm/employees`, archive và account assignment theo employee.
- Hợp đồng tại `/admin/api/hrm/employees/{employee}/contracts` và `/admin/api/hrm/contracts/{contract}`.
- Cơ cấu tại `/admin/api/hrm/organization`.
- Nghỉ phép tại `/admin/api/hrm/leave` và endpoint review.
- Chấm công tại `/admin/api/hrm/attendance`.
- Self-service tại `GET|PUT /admin/api/hrm/me`.

### Payroll API

- `GET|POST /admin/api/payroll/periods`, `PUT /periods/{period}` và `POST /periods/{period}/transition`.
- `GET|POST /admin/api/payroll/payslips` cho nghiệp vụ quản trị.
- `GET /admin/api/payroll/me/payslips` cho nhân viên xem phiếu đã công bố của chính mình.

### Admin UI

- HRM: `/admin/hrm/dashboard`, `/employees`, `/organization`, `/leave`, `/attendance`, `/my-profile`.
- Payroll: `/admin/payroll/periods`, `/payslips`, `/my-payslips`.
- Menu được sinh từ `module.json` và chỉ hiện khi module enabled + tài khoản có permission của menu.
- API chuẩn trả dữ liệu dưới khóa `data`; UI phải kiểm `Array.isArray` trước khi `.map()` và phải hiển thị trạng thái module-disabled thay vì crash khi nhận 404.

## Điểm neo trong source code

- Manifest: `modules/hrm/module.json`, `modules/payroll/module.json`.
- Migration module: `modules/hrm/database/migrations`, `modules/payroll/database/migrations`.
- Lifecycle hooks: `modules/hrm/Hooks/HrmLifecycleHook.php`, `modules/payroll/Hooks/PayrollLifecycleHook.php`.
- Middleware: `app/Http/Middleware/EnsureModuleIsEnabled.php`.
- API: `app/Http/Controllers/Admin/Api/Hrm`, `app/Http/Controllers/Admin/Api/Payroll`.
- Models: `app/Models/Hrm*.php`, `app/Models/Payroll*.php`.
- Admin UI: `resources/admin/src/modules/hrm`, `resources/admin/src/modules/payroll`.
- Routes: `routes/admin.php`.
- Regression tests: `tests/Feature/HrmModuleTest.php`.

Lưu ý: thư mục package dùng chữ thường trên filesystem, còn PHP namespace trong manifest là `Modules\Hrm` và `Modules\Payroll`.

## Kiểm thử và chẩn đoán

Sau thay đổi backend/lifecycle/RBAC:

```bash
php artisan test tests/Feature/HrmModuleTest.php
php artisan test tests/Feature/AccessControlSecurityTest.php
```

Sau thay đổi React/admin:

```bash
npm run build
```

Khi gặp 404 ở HRM/Payroll, kiểm tra theo thứ tự:

1. Route có tồn tại trong `php artisan route:list --path=admin/api/hrm` hoặc `--path=admin/api/payroll`.
2. Bản ghi `module_installations` dùng `key=hrm|payroll` có `status=enabled` hay không.
3. Payroll dependency HRM có đang enabled không.
4. Tài khoản có permission route yêu cầu hay không (thiếu quyền thường là 403; module chưa bật là 404).
5. Frontend có đọc đúng `response.data` và kiểm đúng kiểu array/object không.

Các invariant đang được khóa bằng test:

- Disable HRM chặn API nhưng không làm mất hồ sơ; uninstall bị từ chối.
- Nhân viên không đọc được đơn nghỉ/chấm công của người khác.
- Nhân viên chỉ đọc được phiếu lương đã công bố của chính mình.
- Role self-service được cấp đúng khi lifecycle/link tài khoản chạy.

## Hướng mở rộng không phá kiến trúc

- Thiết bị chấm công/import Excel chỉ ghi thêm `source` và đi qua service chuẩn hóa trước khi upsert `hrm_attendance_records`.
- Số dư phép nên là ledger phát sinh, không chỉ là một số dư có thể ghi đè.
- Payroll nâng cao nên tách rule engine, thuế/bảo hiểm và batch calculation khỏi controller hiện tại.
- Quy trình duyệt nhiều cấp nên dùng workflow engine dùng chung cho HRM, Purchasing và Accounting.
- Employee document, export lương và thông báo cần bổ sung hàng đợi, mã hóa/private storage và audit tải file.
