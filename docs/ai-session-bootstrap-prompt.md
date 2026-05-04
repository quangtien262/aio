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
- Theme không chỉ quản lý giao diện mà còn là nơi chứa phần copy/public content theo từng locale cho storefront.
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

## 2.1. Định hướng đa ngôn ngữ và theme translation

- Hệ thống storefront hiện đi theo kiến trúc locale động, lấy registry từ bảng `system_locales` thay vì hardcode cố định `vi/en` trong code.
- `vi` hiện là `source locale`, `default locale` và `fallback locale` an toàn cho storefront. `en` đang được seed active/published để giữ tương thích với storefront hiện tại và các test/backward compatibility.
- Route public storefront vẫn đi theo dạng prefix locale như `/{locale}`, nhưng tập locale runtime phải lấy từ locale registry đang active/published thay vì giả định sẵn 2 ngôn ngữ.
- Phần đa ngôn ngữ nên tách làm 2 lớp rõ ràng:
  - `static theme copy`: text tĩnh của giao diện như nút, heading, label, empty state, CTA
  - `business content`: dữ liệu nghiệp vụ/CMS hiển thị trên storefront như menu, page, post, category, product, banner, site profile
- Static copy của theme vẫn đi theo file dictionary trong theme, nhưng locale built-in nào được hỗ trợ phải khai báo ở theme manifest qua `localization.default_locale` và `localization.supported_locales`.
- Business content translation không hardcode trong Blade/component mà được map ra key chuẩn để có thể override theo locale. Locale dùng để editor/override phải tách khỏi khái niệm locale storefront đang bật thực tế.
- Tư duy đúng là: theme quyết định cách render, còn dữ liệu business/CMS phải có cơ chế dịch độc lập để khi đổi theme vẫn giữ được nội dung đã nhập.
- Cần phân biệt rõ 2 lớp locale:
  - `runtime storefront locales`: locale đang active để render route public và redirect từ homepage
  - `editable locales`: locale mà admin được phép chuẩn bị/cập nhật nội dung dịch, kể cả locale built-in hoặc preset chưa cần public ngay
- Fallback cần đi theo hướng an toàn:
  - ưu tiên override do user nhập trong admin
  - nếu chưa có override thì dùng default translation entry được build từ dữ liệu gốc
  - nếu locale editor mới chưa dịch đủ thì storefront vẫn phải rơi về source/fallback locale an toàn
- `/admin/themes` hiện là điểm quản trị locale storefront luôn: user có thể bật/tắt locale, publish/draft, đổi default locale và thêm locale custom. Workspace switcher ở admin phải phản ánh nhanh locale storefront đang xem mà không lẫn với shell admin language.
- Admin UI nội bộ hiện vẫn ưu tiên tiếng Việt; phần đa ngôn ngữ vừa làm tập trung vào storefront/theme content, không phải dịch toàn bộ shell quản trị.

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
- Với đa ngôn ngữ storefront, cần giữ tách biệt giữa `theme static translation` và `content translation override`, không trộn lẫn vào cùng một nguồn dữ liệu mơ hồ.
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
- `/admin/themes` hiện là nơi quản lý cả locale storefront lẫn translation của theme cho storefront
- Locale manager drawer mới cho phép:
  - xem `default`, `source`, `fallback` locale hiện tại
  - bật/tắt trạng thái `active` và `published` của từng locale storefront
  - đổi `default locale` động
  - thêm locale custom ngoài built-in locale của theme
  - nhìn rõ locale nào được theme hỗ trợ sẵn qua metadata trong theme manifest
- Drawer translation hỗ trợ các flow chính:
  - chọn locale động theo danh sách locale editor/runtime từ backend, không hardcode `vi/en`
  - chuyển nhanh giữa `static` và `business content`
  - search theo keyword/key
  - phân trang để tải nhanh
  - filter theo entity để dễ tìm đúng nhóm dữ liệu cần dịch
  - edit từng entry bằng modal gọn thay vì render full form quá nặng
- Với `business content`, hệ thống đã có lớp chuẩn hóa key để user dịch lại dữ liệu storefront mà không sửa trực tiếp record gốc. Các nhóm chính đã phủ gồm:
  - `site_profile`
  - `site_banner`
  - `cms_menu`
  - `cms_page`
  - `cms_post`
  - `cms_category`
  - `catalog_category`
  - `catalog_product`
- File/hàm quan trọng cần nhớ khi tiếp tục phần này:
  - `app/Support/BusinessContentTranslationService.php`
  - `app/Http/Controllers/Admin/Api/ThemeTranslationIndexController.php`
  - `resources/admin/src/modules/themes/components/ThemeTranslationDrawer.jsx`
  - `resources/admin/src/modules/themes/pages/ThemeManagerPage.jsx`
- Hướng xử lý đúng cho phần dịch lại data ngôn ngữ của theme là:
  - không chỉnh tay text trong Blade cho từng locale nếu đó là business content
  - chuẩn hóa key translation trước, rồi expose ra admin để user override
  - hỗ trợ save/load override nhanh theo locale để user có thể tinh chỉnh bất kỳ locale đích nào mà không chạm dữ liệu gốc tiếng Việt
  - ưu tiên UX đủ nhanh cho dữ liệu lớn: search, pagination, entity filter, edit từng dòng
- Đã có test/backstop cho phần này:
  - `tests/Feature/ThemeContentTranslationTest.php`
  - `tests/browser/admin-theme-translations.spec.js`

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
