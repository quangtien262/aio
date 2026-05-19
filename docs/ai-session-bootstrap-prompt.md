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
- Mỗi mảng tính năng phải tách thành **module riêng** để có thể bật/tắt, cài/gỡ, nâng cấp độc lập qua store/module manager.
- Ưu tiên xuyên suốt: mở rộng tốt, coupling thấp, quyền theo module, đổi theme không làm mất dữ liệu lõi.

## 2. Định hướng website builder / theme system

- Hệ thống có nhiều theme cài/chuyển linh hoạt nhưng không làm mất dữ liệu business/CMS.
- Theme quản lý giao diện + static/public copy theo locale cho storefront.
- Việc đổi theme phải bị ràng buộc theo **đúng loại website**.
- CMS/theme là trụ cột thương mại quan trọng; dài hạn hướng tới kho theme lớn.
- Flow setup khởi tạo nên có: chọn loại website, chọn theme, nhập cấu hình nền tảng ban đầu.

## 2.1. Định hướng đa ngôn ngữ và theme translation

- Hệ thống storefront hiện đi theo kiến trúc locale động, lấy registry từ bảng `system_locales` thay vì hardcode cố định `vi/en` trong code.
- `vi` hiện là `source locale`, `default locale` và `fallback locale` an toàn cho storefront. `en` đang được seed active/published để giữ tương thích với storefront hiện tại và các test/backward compatibility.
- Route public storefront vẫn đi theo dạng prefix locale như `/{locale}`, nhưng tập locale runtime phải lấy từ locale registry đang active/published thay vì giả định sẵn 2 ngôn ngữ.
- Phần đa ngôn ngữ phải tách 2 lớp: `static theme copy` và `business content`.
- Static copy đi theo dictionary của theme; built-in locale phải khai báo ở theme manifest qua `localization.default_locale` và `localization.supported_locales`.
- Business content translation phải map ra key chuẩn để admin override theo locale, không hardcode trong Blade/component.
- Cần tách `runtime storefront locales` và `editable locales`.
- Fallback an toàn: ưu tiên override của user, nếu thiếu thì dùng default entry từ dữ liệu gốc, cuối cùng rơi về source/fallback locale.
- `/admin/themes` hiện là điểm quản trị locale storefront luôn: user có thể bật/tắt locale, publish/draft, đổi default locale và thêm locale custom. Workspace switcher ở admin phải phản ánh nhanh locale storefront đang xem mà không lẫn với shell admin language.
- Admin UI nội bộ hiện vẫn ưu tiên tiếng Việt; phần đa ngôn ngữ vừa làm tập trung vào storefront/theme content, không phải dịch toàn bộ shell quản trị.

## 3. Mô hình tài khoản và phân quyền

- Hệ thống có 2 loại tài khoản chính:
  - `admin`: đăng nhập quản trị hệ thống
  - `customer` hoặc người dùng đăng ký trên website frontend
- Admin hiện đã đi theo hướng đăng nhập bằng `username` thay vì `email` để thao tác nhanh hơn khi login.
- Customer storefront vẫn đăng nhập bằng `email`.
- Modal login storefront hiện là form dùng chung cho cả admin và customer:
  - backend sẽ thử guard `admin` trước bằng `username`
  - nếu không khớp admin thì mới fallback sang guard `customer` bằng `email`
  - UI field login trong modal nên được hiểu là `Email khách hàng / Username admin`, không quay lại hardcode thuần `email` cho flow login chung này.
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
- Mỗi khách hàng triển khai thực tế sẽ clone ra source riêng, nên đây là **single-tenant by codebase**, không phải multi-tenant shared runtime.
- Tư duy đúng của dự án: **core platform + module ecosystem + theme ecosystem**.
- Module phải đủ độc lập để cài/xóa tùy ý; theme phải đổi được trong cùng nhóm website mà không hỏng dữ liệu business/CMS.
- Với storefront i18n, luôn giữ tách biệt giữa `theme static translation` và `content translation override`.
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
- Drawer post đã được tối ưu lại với `Publish At` dùng `DatePicker`, form chia card/group, SEO trong `Collapse`, nội dung dùng CKEditor 5 free, có upload media và nhúng YouTube.
- Ảnh đại diện bài viết hỗ trợ 3 mode: upload trực tiếp, chọn từ thư viện media, hoặc nhập URL để tạo media record.

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
- `/admin/themes` hiện quản lý cả preview theme, locale storefront và translation. Preview mở bằng drawer khi click tiêu đề theme; nút `Kích hoạt theme` nằm ở đầu drawer.
- Locale manager cho phép xem `default/source/fallback`, bật/tắt `active/published`, đổi `default locale`, thêm locale custom và phân biệt locale built-in của theme.
- Translation drawer đã hỗ trợ locale động, tách `static` / `business content`, search, pagination, entity filter và edit từng entry.
- Business content translation đã phủ các nhóm chính như `site_profile`, `site_banner`, `cms_menu`, `cms_page`, `cms_post`, `cms_category`, `catalog_category`, `catalog_product`.
- Auth modal storefront hiện đã được đồng bộ shared login admin/customer trên tất cả các theme đang có engagement modal chính: `TH0001`, `TH0002`, `SER0100`, `SER0101`.
- Rule cần giữ: login panel của các theme này phải dùng field identity chung `Email khách hàng / Username admin`, post về `customer.auth.store`, để backend thử admin trước rồi mới fallback customer.
- Có guide riêng để AI đọc trước khi dựng theme mới: `docs/theme-authoring-guide.md`.
- Có checklist 1 trang để AI/dev copy-paste khi bắt đầu dựng theme mới: `docs/theme-starter-checklist.md`.
- File neo chính: `app/Support/BusinessContentTranslationService.php`, `app/Http/Controllers/Admin/Api/ThemeTranslationIndexController.php`, `resources/admin/src/modules/themes/components/ThemeTranslationDrawer.jsx`, `resources/admin/src/modules/themes/pages/ThemeManagerPage.jsx`.
- Đã có test/backstop cho phần này:
  - `tests/Feature/ThemeContentTranslationTest.php`
  - `tests/browser/admin-theme-translations.spec.js`
- TH0002 hiện đã được làm sâu hơn ở phần theme configurability:
- TH0002 hiện đọc palette từ `site_profiles.branding`, đã có partial token chung `themes/TH0002/views/partials/palette-tokens.blade.php`, và homepage đã có shared badge token cho pill/badge.
- Palette editor của TH0002 đã tách khỏi Setup Wizard sang Theme Manager. File neo chính: `resources/admin/src/modules/themes/components/ThemePaletteEditorDrawer.jsx`, `resources/admin/src/modules/themes/pages/ThemeManagerPage.jsx`, `resources/admin/src/pages/routes/ThemesRoutePage.jsx`.
- Save palette hiện vẫn đi qua endpoint setup hiện có (`PUT /admin/api/setup`) chứ chưa tách API riêng.
- Palette persist trong `site_profiles.branding` với các key chính: `primary_color`, `primary_color_deep`, `accent_color`, `accent_soft_color`, `background_color`, `surface_color`, `surface_tint_color`.
- Setup flow liên quan: `app/Http/Controllers/Admin/Api/SetupProfileController.php`, `app/Http/Controllers/Admin/Api/SetupWizardStateController.php`, `app/Http/Controllers/Admin/Api/SetupStepController.php`.
- Có regression test cho save palette setup tại:
  - `tests/Feature/AdminFoundationApiTest.php` với case `test_admin_can_store_theme_palette_in_setup_branding`

### Setup
- `/admin/setup` đã được format lại cho gọn hơn, theo layout nhóm section rõ ràng
- Setup Wizard hiện chỉ giữ branding cơ bản như site/profile/logo/favicon/contact.
- Không đưa palette editor đầy đủ của TH0002 quay lại Setup Wizard; palette chi tiết chỉnh ở Theme Manager.

### Auth / Admin Accounts
- Admin account hiện có field persisted `username` trong bảng `admins`.
- Migration bổ sung gần đây: `database/migrations/2026_05_13_000001_add_username_to_admins_table.php`
- Default admin seed hiện là `username=admin`, `email=admin@aio.local`, `password=password`.
- Seeder/factory đã được cập nhật để admin luôn có `username`: `database/seeders/DatabaseSeeder.php`, `database/factories/AdminFactory.php`.
- Dedicated admin login page riêng đã được bỏ để giảm bề mặt truy cập; admin login đi qua shared storefront login modal ngoài website.
- Khi admin hết session hoặc logout, hệ thống phải quay về storefront homepage thay vì `/admin/login`.
- File neo auth: `app/Http/Controllers/Admin/AuthenticatedSessionController.php`, `app/Http/Controllers/Customer/AuthenticatedSessionController.php`, `bootstrap/app.php`, `resources/admin/src/layouts/AdminLayout.jsx`.
- Admin account management UI/API đã được cập nhật để tạo/sửa/list/drawer đều có `username`: `app/Http/Controllers/Admin/Api/AdminAccountController.php`, `resources/admin/src/modules/admins/components/AdminAccountFormModal.jsx`, `resources/admin/src/modules/admins/components/AdminAccountsTableCard.jsx`, `resources/admin/src/modules/admins/components/AdminAccountDetailsDrawer.jsx`, `resources/admin/src/modules/admins/pages/AdminAccountsPage.jsx`.
- Ở admin account table/drawer, UX hiện ưu tiên hiển thị `username` rõ hơn `email`.
- Test đã khóa phần auth/account mới tại:
  - `tests/Feature/AuthSplitTest.php`
  - `tests/Feature/AdminFoundationApiTest.php`

## 8. Những capability nghiệp vụ cần luôn ghi nhớ khi làm việc

- Đây là hệ thống định hướng rộng, không chỉ có CMS. Các nhóm trọng tâm dài hạn gồm CRM, Project, Purchasing, Inventory, HRM, Sales, Accounting, CMS / website builder / theme marketplace.
- Khi đề xuất model, route, permission, menu, settings, dashboard hay schema, phải ưu tiên pattern dùng lại được cho nhiều module.

## 9. Các file quan trọng nên kiểm tra trước khi sửa

- `resources/admin/src/modules/cms/components/CmsPostFormModal.jsx`
- `resources/admin/src/modules/cms/pages/CmsManagerPage.jsx`
- `app/Http/Controllers/Admin/Api/Cms/MediaManagementController.php`
- `routes/admin.php`
- `modules/Cms/module.json`
- `app/Support/PermissionLabel.php`
- `resources/admin/src/styles/index.css`
- `app/Http/Controllers/Customer/AuthenticatedSessionController.php`
- `app/Http/Controllers/Admin/Api/AdminAccountController.php`
- `themes/TH0002/views/partials/palette-tokens.blade.php`
- `themes/TH0002/views/partials/engagement-modals.blade.php`
- `resources/admin/src/modules/themes/components/ThemePaletteEditorDrawer.jsx`
- `docs/theme-authoring-guide.md`
- `docs/theme-starter-checklist.md`

## 10. Cách làm việc tôi muốn ở session này

- Trước khi sửa, hãy đọc đúng file/symbol liên quan thay vì khám phá rộng.
- Ưu tiên sửa nhỏ, đúng root cause, không đụng phần không liên quan.
- Sau khi sửa frontend, ưu tiên chạy `npm run build` nếu thay đổi đủ đáng kể.
- Sau khi sửa backend Laravel, ưu tiên chạy `php artisan optimize:clear` nếu phù hợp.
- Nếu có bug runtime ở UI admin, hãy kiểm tra cả import thiếu, prop sai, mismatch Ant Design/React, và những chỗ render component con trong drawer/modal.
- Nếu người dùng hỏi “tiếp tục phần trước”, hãy giả định ngữ cảnh gần nhất xoay quanh CMS admin, media, posts, permissions, themes, setup, và UX quản trị.
- Ở ngữ cảnh gần đây hơn của repo này, hãy đặc biệt nhớ thêm:
  - palette TH0002 đã nằm trong Theme Manager, không còn ở Setup Wizard
  - admin login dùng `username`
  - modal storefront login là form dùng chung `admin username` + `customer email`
  - các theme `TH0001`, `TH0002`, `SER0100`, `SER0101` đều đã đồng bộ flow shared login này
  - không reintroduce lại `/admin/login` hoặc dedicated admin login page riêng
- Khi cần đề xuất kiến trúc, hãy ưu tiên các thiết kế có thể tái sử dụng cho nhiều module khác nhau trong hệ sinh thái AIO.
- Khi nói về roadmap hay solution, hãy nhớ CMS/theme marketplace là một trụ cột thương mại quan trọng của dự án.

## 11. Cách trả lời mong muốn

- Trả lời ngắn gọn, thực dụng, tập trung vào kết quả.
- Nếu cần nêu file, hãy ưu tiên chỉ đúng file sẽ sửa.
- Nếu có validation đã chạy, nói rõ cái gì pass, cái gì chỉ là warning không chặn chức năng.

Hãy dùng ngữ cảnh trên làm baseline và tiếp tục hỗ trợ tôi trên đúng codebase này.
```
