# AIO Project Bootstrap Prompt

```md

Đây là một base source website AIO (All In One) của HT Việt Nam. Mục tiêu sản phẩm là xây một **hệ sinh thái website + quản trị doanh nghiệp** đủ lớn để bán cho nhiều khách hàng. Mỗi khách hàng khi triển khai thực tế sẽ **clone ra 1 source code riêng**, không dùng chung runtime multi-tenant.

Base source này phải được thiết kế rất kỹ từ đầu để sau này mở rộng thành nhiều nhóm tính năng lớn mà vẫn giữ kiến trúc sạch, tách biệt, dễ cài đặt, dễ gỡ bỏ, dễ nâng cấp.

## 0. Trình tự đọc bắt buộc cho AI session mới

1. Đọc hết file bootstrap này để nắm định hướng sản phẩm, module, theme, website context và convention.
2. Nếu công việc liên quan tài khoản, đăng nhập, permission, middleware, module lifecycle hoặc dữ liệu admin, đọc tiếp `docs/architecture/admin-access-control.md` trước khi sửa code.
3. Kiểm tra migration đã chạy bằng `php artisan migrate:status`; không sửa migration production đã chạy để thay đổi hành vi, hãy tạo migration mới.
4. Kiểm tra `git status --short` trước khi sửa và không ghi đè thay đổi đang có của người dùng/session khác.
5. Với auth/RBAC, luôn đọc test `AccessControlSecurityTest`, `AuthSplitTest`, `AdminFoundationApiTest` để hiểu invariant đã được khóa.

Trạng thái chốt ngày 2026-07-21: kiến trúc quản lý tài khoản/RBAC mới đã được triển khai và migrate trên database local. Session admin cũ bị thu hồi; admin ID 1 phải đổi mật khẩu ở lần đăng nhập kế tiếp. Không được quay lại mô hình scope legacy.

Tôi là giám đốc CÔNG TY CP CÔNG NGHỆ VÀ TRUYỀN THÔNG HT VIỆT NAM, xuất phát đi lên từ kỹ thuật và đây là sản phẩm chiến lược của công ty nên tôi sẽ trực tiếp quản lý dự án này. hãy xưng em và gọi tôi là Sếp.

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
- Admin đăng nhập bằng `username` hoặc `email`; customer storefront đăng nhập bằng `email`.
- Modal login storefront hiện là form dùng chung cho cả admin và customer:
  - backend sẽ thử guard `admin` trước, lần lượt theo `username` và `email`
  - nếu không khớp admin thì mới fallback sang guard `customer` bằng `email`
  - UI field login trong modal nên được hiểu là `Email khách hàng / Username admin`, không quay lại hardcode thuần `email` cho flow login chung này.
- Phân quyền hiện dùng **RBAC theo từng module**. Mỗi assignment gắn role và scope ngay trên cùng một dòng trong `admin_role_assignments`.
- Scope hợp lệ chỉ có `global` và `website`. Không được đưa lại `tenant`, `owner`, `tenant_key`, `owner_key`, `admin_role` hoặc `admin_role_scopes`.
- Admin ID `1` là System Owner bất biến. Role `super-admin` là role hệ thống bất biến và luôn có toàn bộ permission active.
- Khi module bị gỡ, permission được đánh dấu inactive/deprecated để giữ lịch sử, không xóa vật lý.
- Tài liệu kiến trúc chuẩn: `docs/architecture/admin-access-control.md`.

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

- Không reintroduce kiến trúc multi-tenant cũ. `owner_key` và `tenant_key` đã bị xóa hoàn toàn khỏi runtime/schema mới; `website_key` chỉ dùng cho website context/demo-domain nội bộ.
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
- Theme `XD0301` là theme chuẩn nhất, nếu có chỗ nào khó hiểu thì hãy tham khảo code của theme này nhé, đặc biệt là cách cài đặt trang chủ và landingpage, xem chi tiết hơn ở file `docs/landing-page-builder.md` và `theme-starter-checklist.md`
- Locale manager cho phép xem `default/source/fallback`, bật/tắt `active/published`, đổi `default locale`, thêm locale custom và phân biệt locale built-in của theme.
- Translation drawer đã hỗ trợ locale động, tách `static` / `business content`, search, pagination, entity filter và edit từng entry.
- Business content translation đã phủ các nhóm chính như `site_profile`, `site_banner`, `cms_menu`, `cms_page`, `cms_post`, `cms_category`, `catalog_category`, `catalog_product`.
- Auth modal storefront hiện đã được đồng bộ shared login admin/customer trên tất cả các theme đang có engagement modal chính: `XD0301`, `XD0302`.....
- Rule cần giữ: login panel của các theme này phải dùng field identity chung `Email khách hàng / Username admin`, post về `customer.auth.store`, để backend thử admin trước rồi mới fallback customer.
- Có guide riêng để AI đọc trước khi dựng theme mới: `docs/theme-authoring-guide.md`.
- Có checklist 1 trang để AI/dev copy-paste khi bắt đầu dựng theme mới: `docs/theme-starter-checklist.md`.
- Storefront CMS route convention hiện tại: page thường giữ `/{slug}`, dịch vụ dùng `/s` + `/s/{slug}` + `/ser/{slug}`, tin tức dùng `/c` + `/c/{slug}` + `/n/{slug}`, liên hệ dùng `/contact`; theme/admin/demo data phải ưu tiên route helper và không sinh mới `/tin-tuc`, `/tin-tuc?category=...`, `/dich-vu`, `/lien-he`.
- File neo chính: `app/Support/BusinessContentTranslationService.php`, `app/Http/Controllers/Admin/Api/ThemeTranslationIndexController.php`, `resources/admin/src/modules/themes/components/ThemeTranslationDrawer.jsx`, `resources/admin/src/modules/themes/pages/ThemeManagerPage.jsx`.
- Đã có test/backstop cho phần này:
  - `tests/Feature/ThemeContentTranslationTest.php`
  - `tests/browser/admin-theme-translations.spec.js`
- Save palette hiện vẫn đi qua endpoint setup hiện có (`PUT /admin/api/setup`) chứ chưa tách API riêng.
- Palette persist trong `site_profiles.branding` với các key chính: `primary_color`, `primary_color_deep`, `accent_color`, `accent_soft_color`, `background_color`, `surface_color`, `surface_tint_color`.
- Setup flow liên quan: `app/Http/Controllers/Admin/Api/SetupProfileController.php`, `app/Http/Controllers/Admin/Api/SetupWizardStateController.php`, `app/Http/Controllers/Admin/Api/SetupStepController.php`.
- Có regression test cho save palette setup tại:
  - `tests/Feature/AdminFoundationApiTest.php` với case `test_admin_can_store_theme_palette_in_setup_branding`

### Setup
- `/admin/setup` đã được format lại cho gọn hơn, theo layout nhóm section rõ ràng
- Setup Wizard hiện chỉ giữ branding cơ bản như site/profile/logo/favicon/contact.


### Auth / Admin Accounts / RBAC — trạng thái hiện hành

- Dedicated `/admin/login` đã bị bỏ. Admin và customer dùng chung modal login storefront qua `customer.auth.store`; backend thử guard admin trước rồi mới fallback customer.
- Tất cả auth modal của các theme chính đã có field `two_factor_code`. Không tạo lại form login admin riêng.
- Login admin bị rate-limit 5 lần/phút theo định danh + IP; thông báo lỗi không tiết lộ tài khoản tồn tại, bị khóa hay bị vô hiệu hóa.
- Mật khẩu admin tạo mới/reset phải có tối thiểu 12 ký tự, chữ hoa/thường, số và ký hiệu. Tài khoản mới/reset phải đổi mật khẩu trước khi tiếp tục.
- Session mặc định 120 phút. `auth_version` thu hồi session cũ khi đổi/reset mật khẩu, đổi role/scope, khóa tài khoản hoặc thay đổi TOTP.
- TOTP theo RFC 6238 và recovery code dùng một lần đã có trong menu tài khoản admin. Secret được encrypted cast; recovery code được hash trước khi lưu.
- Admin ID `1` luôn là System Owner: active, không khóa, không sửa/xóa/reset mật khẩu từ màn quản lý. System Owner chỉ tự đổi mật khẩu sau khi xác nhận mật khẩu hiện tại.
- Role `super-admin` luôn `is_system=true`, `is_assignable=false`, `status=active`; không sửa, đổi key, xóa hoặc cấp thủ công. System Owner và tài khoản mang role này luôn có toàn bộ permission active.
- Role/scope dùng bảng `admin_role_assignments`; mỗi dòng gồm `admin_id`, `role_id`, `scope_type=global|website`, `scope_value`, `expires_at`.
- Audit log lưu actor/action/module/website/target/before/after/IP/user-agent/request-id. Logger lọc đệ quy mật khẩu, token, TOTP secret và recovery code.
- Permission module khi ngừng dùng chuyển inactive/deprecated, không xóa vật lý.
- Admin UI hiện có các khu vực: `Vai trò & quyền`, `Tài khoản quản trị`, `Nhật ký bảo mật`, đổi mật khẩu và xác thực hai lớp.
- Migration chốt kiến trúc:
  - `database/migrations/2026_07_21_000001_rebuild_admin_access_control.php`
  - `database/migrations/2026_07_21_000002_enforce_system_owner_invariants.php`
- File backend neo:
  - `app/Models/Admin.php`, `app/Models/Role.php`, `app/Models/Permission.php`
  - `app/Models/AdminRoleAssignment.php`, `app/Models/AuditLog.php`
  - `app/Http/Controllers/Admin/Api/AdminAccountController.php`
  - `app/Http/Controllers/Admin/Api/AdminTwoFactorController.php`
  - `app/Http/Middleware/EnsureAdminAccountIsActive.php`
  - `app/Http/Middleware/EnsureAdminWebsiteAccess.php`
  - `app/Support/AuditLogger.php`, `app/Support/Totp.php`
- Regression test bắt buộc:
  - `tests/Feature/AccessControlSecurityTest.php`
  - `tests/Feature/AuthSplitTest.php`
  - `tests/Feature/AdminFoundationApiTest.php`
- Tài liệu chi tiết và checklist production: `docs/architecture/admin-access-control.md`.

## 7.1. Theme demo / cấu hình domain nội bộ

Phần này rất quan trọng cho các session AI khác: hệ thống có cơ chế chạy nhiều theme demo trên nhiều subdomain trong cùng một source code, nhưng mục tiêu chính là **test nội bộ / demo theme**, không phải mô hình multi-tenant runtime để bán trực tiếp cho nhiều khách dùng chung source.

Nguyên tắc chốt:

- Không gắn data vào theme. Theme chỉ là giao diện.
- Data CMS gắn vào `website_key`.
- Domain demo quyết định cặp `website_key` + `theme_key`.
- Website khách triển khai thật thường chỉ dùng một theme và một bộ data mặc định; nếu không có cấu hình domain nào match thì hệ thống fallback về `website-main`.
- Không dùng cơ chế "data dùng chung" mặc định để tránh lẫn data. Nếu cần tận dụng nội dung/media từ website khác thì copy/chọn có chủ đích.

Các bảng/cột chính:

- `sites`: lưu domain -> `website_key` -> `theme_key`.
  - `domain`: ví dụ `xd0313.demo.htvietnam.vn`.
  - `website_key`: ví dụ `xd0313-demo-htvietnam-vn`.
  - `theme_key`: ví dụ `XD0313`.
  - `status`: chỉ site `active` mới được resolve ở storefront.
- `site_profiles`: có `website_key`, `active_theme_key`, branding/profile theo từng website.
- Các bảng CMS có prefix `cms_` lưu `website_key` để tách data, gồm các nhóm chính như pages, posts, services, projects, products/catalog, team members, testimonials, partners, menus, media.

Runtime resolve:

- Middleware chính: `app/Http/Middleware/ResolveCurrentSite.php`.
- Context chính: `app/Support/SiteContext.php`.
- Storefront public:
  - Resolve theo host/domain hiện tại trong bảng `sites`.
  - Nếu không match domain active nào thì fallback `website-main`.
  - Theme active lấy ưu tiên từ `SiteContext::themeKey()` / `sites.theme_key`, sau đó fallback profile/theme mặc định.
- Admin:
  - Header admin có bộ chọn "website đang quản trị".
  - Frontend admin gửi `X-Website-Key` trên mọi request admin.
  - Backend đọc `X-Website-Key` trong `ResolveCurrentSite` để set `SiteContext`.
  - Nhờ trait `App\Models\Concerns\HasWebsiteScope`, phần lớn model CMS tự lọc/tự gán `website_key` theo context hiện tại.

Admin UI liên quan:

- `/admin/themes` -> Theme Manager -> menu trái **Cấu hình domain**.
- Component chính: `resources/admin/src/modules/themes/components/SiteDomainMappingPanel.jsx`.
- API chính: `app/Http/Controllers/Admin/Api/SiteMappingController.php`.
- Routes chính:
  - `GET /admin/api/site-mappings`
  - `POST /admin/api/site-mappings`
  - `POST /admin/api/site-mappings/bulk`
  - `PUT /admin/api/site-mappings/{site}`
  - `DELETE /admin/api/site-mappings/{site}`
- Chức năng tạo nhanh subdomain:
  - User nhập domain chính, ví dụ `demo.htvietnam.vn`.
  - Hệ thống tạo cấu hình theo mã theme, ví dụ `XD0301.demo.htvietnam.vn`, `XD0302.demo.htvietnam.vn`, ...
  - Cấu hình đã tồn tại sẽ được bỏ qua, không ghi đè.

Media theo theme demo:

- Media cũng tách theo `website_key`, không dùng chung mặc định.
- Upload media mới lưu record theo website đang chọn và file vật lý dưới path dạng `cms/{website_key}/...`.
- Trang quản trị Media có option **Show toàn bộ** để xem media của tất cả website trong trường hợp cần copy URL dùng chung hình ảnh.
- Khi đang show toàn bộ, media thuộc website khác chỉ nên copy URL; thao tác sửa/xóa/chuyển thư mục bị chặn ở UI để tránh ảnh hưởng data website khác.
- Backend Media chính:
  - `app/Http/Controllers/Admin/Api/Cms/MediaIndexController.php`
  - `app/Http/Controllers/Admin/Api/Cms/MediaManagementController.php`
- Trang Media hiển thị cảnh báo/tag file chưa được dùng dựa trên các liên kết chuẩn bằng `featured_media_id` và `cms_media_id`. Nếu ảnh chỉ được copy URL rồi dán thủ công vào HTML/text thì hệ thống chưa thể nhận diện chắc chắn là đã dùng.

Lưu ý khi tiếp tục phát triển:

- Không thêm dropdown "data thuộc theme nào" vào từng form CMS; chọn website quản trị một lần ở header là hướng đúng.
- Khi thêm model CMS mới, nếu data cần tách theo website thì thêm `website_key` và dùng trait `HasWebsiteScope`.
- Khi query admin cần nhìn xuyên toàn bộ website, phải chủ động dùng `withoutGlobalScope('current_website')` và bảo vệ thao tác ghi/xóa thật kỹ.
- Không biến cơ chế demo domain thành multi-tenant thương mại shared runtime; đây là tiện ích nội bộ để review nhiều theme nhanh trên một source.

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
- `app/Http/Controllers/Admin/Api/AdminTwoFactorController.php`
- `app/Http/Controllers/Admin/Api/AuditLogIndexController.php`
- `app/Models/AdminRoleAssignment.php`
- `app/Support/AuditLogger.php`
- `docs/architecture/admin-access-control.md`
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
  - admin login dùng `username` hoặc `email`, customer dùng `email`
  - modal storefront login là form dùng chung `admin username` + `customer email`
  - các theme `TH0001`, `TH0002`, `SER0100`, `SER0101` đều đã đồng bộ flow shared login này
  - không reintroduce lại `/admin/login` hoặc dedicated admin login page riêng
  - không reintroduce `tenant`, `owner`, `tenant_key`, `owner_key`, `admin_role` hoặc `admin_role_scopes`
  - không cho phép sửa/xóa role `super-admin` hay sửa/khóa/xóa admin ID `1`
  - permission module phải inactive/deprecated khi gỡ module, không xóa vật lý
- Khi cần đề xuất kiến trúc, hãy ưu tiên các thiết kế có thể tái sử dụng cho nhiều module khác nhau trong hệ sinh thái AIO.
- Khi nói về roadmap hay solution, hãy nhớ CMS/theme marketplace là một trụ cột thương mại quan trọng của dự án.

## 11. Cách trả lời mong muốn

- Trả lời ngắn gọn, thực dụng, tập trung vào kết quả.
- Nếu cần nêu file, hãy ưu tiên chỉ đúng file sẽ sửa.
- Nếu có validation đã chạy, nói rõ cái gì pass, cái gì chỉ là warning không chặn chức năng.

Hãy dùng ngữ cảnh trên làm baseline và tiếp tục hỗ trợ tôi trên đúng codebase này.
```
