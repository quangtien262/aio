# AIO Project Bootstrap Prompt

```md
Bạn đang hỗ trợ tôi trên project `E:\Project\aio`.

Đây là một base source website AIO (All In One) của HT Việt Nam. Mục tiêu sản phẩm là xây một **hệ sinh thái website + quản trị doanh nghiệp** đủ lớn để bán cho nhiều khách hàng. Mỗi khách hàng khi triển khai thực tế sẽ **clone ra 1 source code riêng**, không dùng chung runtime multi-tenant.

Base source này phải được thiết kế rất kỹ từ đầu để sau này mở rộng thành nhiều nhóm tính năng lớn mà vẫn giữ kiến trúc sạch, tách biệt, dễ cài đặt, dễ gỡ bỏ, dễ nâng cấp.

## 1. Tầm nhìn sản phẩm và định hướng phát triển

- Đây không chỉ là một website đơn lẻ, mà là một hệ thống AIO có thể phát triển thành nhiều mảng nghiệp vụ như:
  - quản lý dự án
  - quản lý khách hàng
  - mua hàng
  - kho
  - nhân sự
  - sale
  - kế toán
  - CMS website
  - và các phân hệ mở rộng khác về sau
- Mỗi mảng tính năng phải được tách thành **module riêng** để có thể bật, cài đặt, gỡ bỏ, nâng cấp độc lập.
- Hệ thống sẽ có một **store/module manager page** để người dùng cài thêm module khi cần.
- Đây là một nền tảng định hướng thương mại hóa lâu dài, nên khi thiết kế base source phải ưu tiên:
  - khả năng mở rộng
  - khả năng đóng gói module
  - ít coupling giữa các module
  - quyền hạn tách theo module
  - theme/frontend có thể thay đổi mà không làm mất dữ liệu lõi

## 2. Định hướng website builder / theme system

- Hệ thống website sẽ có nhiều loại giao diện/theme có thể cài đặt và chuyển đổi linh hoạt.
- Khi đổi theme, dữ liệu website không được mất.
- Việc đổi theme phải có kiểm soát theo **đúng loại website**, ví dụ:
  - thương mại điện tử
  - website dịch vụ
  - website giới thiệu doanh nghiệp
  - website tin tức
  - landing page
- CMS/theme là một trụ cột lớn của sản phẩm. Mục tiêu dài hạn là xây một kho giao diện website lớn, khoảng 100 mẫu trở lên, rồi mới bắt đầu đẩy mạnh thương mại hóa.
- Khi cài đặt mới hệ thống, cần có flow setup ban đầu như:
  - chọn loại website
  - chọn theme phù hợp
  - nhập các cấu hình nền tảng ban đầu

## 3. Mô hình tài khoản và phân quyền

- Hệ thống có 2 loại tài khoản chính:
  - `admin`: đăng nhập quản trị hệ thống
  - `customer` hoặc người dùng đăng ký trên website frontend
- Phân quyền phải đi theo hướng **RBAC theo từng module**, nghĩa là mỗi module có tập permission riêng.
- Khi module được cài đặt/gỡ bỏ thì permission liên quan cũng phải đồng bộ theo module đó.

## 4. Tech stack

- Backend: PHP 8.3+, Laravel 13
- Frontend admin: React 19 chạy như một phần/page của Laravel app, build bằng Vite 7, UI library chính là Ant Design 5
- Editor: CKEditor 5 free (`ckeditor5` + `@ckeditor/ckeditor5-react`)
- Test/build hay dùng:
  - `npm run build`
  - `php artisan optimize:clear`
  - `php artisan migrate`
  - `php artisan db:seed`

## 5. Cấu trúc repo quan trọng

- `app/`: core Laravel app, controllers, models, providers, support classes
- `modules/`: business modules dạng cài đặt/bật tắt được
- `themes/`: theme frontend public website
- `resources/admin/src/`: admin shell React + Ant Design
- `routes/admin.php`: các API admin quan trọng
- `database/migrations/`, `database/seeders/`: migration và seed dữ liệu hệ thống
- `docs/architecture/`: tài liệu sơ đồ

## 6. Kiến trúc và convention cần giữ

- Không reintroduce kiến trúc multi-tenant cũ. Các check/runtime field kiểu `website_key`, `owner_key`, `tenant_key` đã được dọn khỏi flow chính, giữ đúng tinh thần single-site.
- Mỗi khách hàng triển khai thực tế sẽ clone ra source riêng, nên ưu tiên thiết kế theo hướng **single-tenant by codebase**, không phải multi-tenant shared runtime.
- Tư duy đúng của dự án là: **core platform + module ecosystem + theme ecosystem**.
- Module phải đủ độc lập để có thể cài/xóa tùy ý qua store/install flow.
- Theme phải đổi được linh hoạt trong cùng nhóm website mà không làm hỏng dữ liệu business/CMS.
- Admin dùng React như một phần của Laravel app, không tách hẳn thành frontend project độc lập.
- Admin UI ưu tiên **drawer** cho form tạo/sửa nội dung CMS thay vì modal nếu cùng pattern hiện có.
- Giữ style thay đổi nhỏ, đúng codebase hiện tại, không refactor rộng nếu user không yêu cầu.
- UI admin đang dùng tiếng Việt cho label/nút/copy nên ưu tiên giữ tiếng Việt nhất quán.
- Nếu làm việc với media public thì hiện tại URL public đi theo hướng `/files/...`.
- Với frontend dev server, repo này từng gặp lỗi stale Vite optimize deps; script dev hiện dùng `vite --force`.
- Khi phân tích hoặc đề xuất kiến trúc mới, luôn cân nhắc khả năng scale về sau cho nhiều module nghiệp vụ khác nhau, không chỉ riêng CMS.

## 7. Các khu vực đã được làm đáng kể gần đây

### CMS / Posts
- File chính: `resources/admin/src/modules/cms/components/CmsPostFormModal.jsx`
- Drawer tạo/sửa bài viết CMS đã được tối ưu lại:
  - `Publish At` dùng `DatePicker`, mặc định thời gian hiện tại cho bài viết mới
  - form chia thành các card/group rõ ràng
  - SEO fields nằm trong `Collapse`
  - CKEditor 5 free dùng cho nội dung bài viết
  - có upload ảnh/video trực tiếp vào nội dung
  - có nút nhúng video YouTube vào editor
- `Ảnh đại diện bài viết` có 3 mode:
  1. upload ảnh trực tiếp
  2. chọn từ thư viện media có sẵn (modal + pagination)
  3. nhập URL để tạo media record rồi gán vào bài viết

### CMS / Media
- File backend chính: `app/Http/Controllers/Admin/Api/Cms/MediaManagementController.php`
- API media hiện hỗ trợ cả upload file và tạo record từ `file_url`
- Model liên quan: `app/Models/CmsMedia.php`
- `CmsMedia` có thể trả `file_url` trực tiếp nếu không có `file_path`

### CMS / Orders
- CMS đã có thêm khu quản lý đơn hàng dạng read-only trong workspace CMS
- Route alias có ở `routes/admin.php`
- UI chính ở `resources/admin/src/modules/cms/pages/CmsManagerPage.jsx`
- Quyền liên quan: `cms.order.view`

### CMS / Products
- Products đã được đưa vào CMS workspace
- Permission đã chuẩn hóa về `cms.product.*` thay vì dùng `catalog.*`

### Access / RBAC
- UI role/permission đã được chỉnh để hiển thị label thân thiện hơn, ví dụ `CMS Product ...`
- Có helper label ở backend/frontend:
  - `app/Support/PermissionLabel.php`
  - `resources/admin/src/modules/access/utils/permissionLabels.js`

### Themes
- `/admin/themes` đã đổi để preview theme chỉ mở khi click vào tiêu đề theme
- Preview hiển thị bằng drawer
- Nút `Kích hoạt theme` đã được đẩy lên đầu drawer để thao tác nhanh hơn

### Setup
- `/admin/setup` đã được format lại cho gọn hơn, theo layout nhóm section rõ ràng

## 8. Những capability nghiệp vụ cần luôn ghi nhớ khi làm việc

- Đây là hệ thống định hướng rất rộng, không phải chỉ có CMS.
- Các nhóm nghiệp vụ tiềm năng/định hướng dài hạn gồm:
  - CRM / khách hàng
  - Project management
  - Purchasing
  - Inventory / kho
  - HRM / nhân sự
  - Sales
  - Accounting
  - CMS / website builder / theme marketplace
- Vì vậy khi đề xuất model, route, permission, menu, settings, dashboard hay schema, cần nghĩ theo hướng có thể dùng chung pattern cho nhiều module khác nhau.

## 9. Các file quan trọng nên kiểm tra trước khi sửa

- `resources/admin/src/modules/cms/components/CmsPostFormModal.jsx`
- `resources/admin/src/modules/cms/pages/CmsManagerPage.jsx`
- `app/Http/Controllers/Admin/Api/Cms/MediaManagementController.php`
- `routes/admin.php`
- `modules/Cms/module.json`
- `app/Support/PermissionLabel.php`
- `resources/admin/src/styles/index.css`

## 10. Cách làm việc tôi muốn ở session này

- Trước khi sửa, hãy đọc đúng file/symbol liên quan thay vì khám phá rộng.
- Ưu tiên sửa nhỏ, đúng root cause, không đụng phần không liên quan.
- Sau khi sửa frontend, ưu tiên chạy `npm run build` nếu thay đổi đủ đáng kể.
- Sau khi sửa backend Laravel, ưu tiên chạy `php artisan optimize:clear` nếu phù hợp.
- Nếu có bug runtime ở UI admin, hãy kiểm tra cả import thiếu, prop sai, mismatch Ant Design/React, và những chỗ render component con trong drawer/modal.
- Nếu người dùng hỏi “tiếp tục phần trước”, hãy giả định ngữ cảnh gần nhất xoay quanh CMS admin, media, posts, permissions, themes, setup, và UX quản trị.
- Khi cần đề xuất kiến trúc, hãy ưu tiên các thiết kế có thể tái sử dụng cho nhiều module khác nhau trong hệ sinh thái AIO.
- Khi nói về roadmap hay solution, hãy nhớ CMS/theme marketplace là một trụ cột thương mại quan trọng của dự án.

## 11. Cách trả lời mong muốn

- Trả lời ngắn gọn, thực dụng, tập trung vào kết quả.
- Nếu cần nêu file, hãy ưu tiên chỉ đúng file sẽ sửa.
- Nếu có validation đã chạy, nói rõ cái gì pass, cái gì chỉ là warning không chặn chức năng.

Hãy dùng ngữ cảnh trên làm baseline và tiếp tục hỗ trợ tôi trên đúng codebase này.
```
