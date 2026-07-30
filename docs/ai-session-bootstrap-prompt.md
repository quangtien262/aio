# AIO Project Bootstrap Prompt

```md

Đây là một base source website AIO (All In One) của HT Việt Nam. Mục tiêu sản phẩm là xây một **hệ sinh thái website + quản trị doanh nghiệp** đủ lớn để bán cho nhiều khách hàng. Mỗi khách hàng khi triển khai thực tế sẽ **clone ra 1 source code riêng**, không dùng chung runtime multi-tenant.

Base source này phải được thiết kế rất kỹ từ đầu để sau này mở rộng thành nhiều nhóm tính năng lớn mà vẫn giữ kiến trúc sạch, tách biệt, dễ cài đặt, dễ gỡ bỏ, dễ nâng cấp.

## 0. Trình tự đọc bắt buộc cho AI session mới

1. Đọc hết file bootstrap này để nắm định hướng sản phẩm, module, theme, website context và convention.
2. Đọc thêm tài liệu đúng miền trước khi sửa:
   - Đa ngôn ngữ, CMS Pages, phần Nội dung, Landing Page, theme locale hoặc SEO: `docs/architecture/localization-foundation.md` và `docs/architecture/localization-rollout-runbook.md`.
   - Tài khoản, đăng nhập, permission, middleware, module lifecycle hoặc dữ liệu admin: `docs/architecture/admin-access-control.md`.
   - Nhân sự/tiền lương: `docs/architecture/hrm-and-payroll-modules.md`.
   - Bất động sản hoặc theme BDS701: `docs/architecture/real-estate-module-and-bds701-theme.md`.
3. Kiểm tra migration đã chạy bằng `php artisan migrate:status`; không sửa migration production đã chạy để thay đổi hành vi, hãy tạo migration mới.
4. Kiểm tra `git status --short` trước khi sửa và không ghi đè thay đổi đang có của người dùng/session khác.
5. Với auth/RBAC, luôn đọc test `AccessControlSecurityTest`, `AuthSplitTest`, `AdminFoundationApiTest` để hiểu invariant đã được khóa.

Trạng thái chốt ngày 2026-07-21: kiến trúc quản lý tài khoản/RBAC mới đã được triển khai và migrate trên database local. Session admin cũ bị thu hồi; admin ID 1 phải đổi mật khẩu ở lần đăng nhập kế tiếp. Không được quay lại mô hình scope legacy.

Trạng thái chốt ngày 2026-07-30: nền tảng đa ngôn ngữ, CMS Pages, 17 nhóm nội dung, Landing Page, theme contract và cơ chế rollout đã được triển khai trên database local. Kiến trúc và dữ liệu nguồn đang nhất quán, nhưng nội dung tiếng Anh chưa đủ điều kiện phát hành; phải đọc mục 7.2 và chạy release-readiness audit trước khi public thêm locale.

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

- `system_locales` là danh mục locale BCP 47 dùng chung; `website_locales` mới là nguồn sự thật về locale của từng website.
- Tách rõ ba khái niệm: locale nguồn, locale mặc định và fallback locale. Không giả định chúng luôn giống nhau, dù `website-main` hiện dùng `vi` cho cả ba.
- Locale được phép biên tập (`is_enabled_for_editing`) và locale được public (`is_published`) là hai trạng thái độc lập. Storefront chỉ chấp nhận locale có đủ cả hai cờ.
- Route public dùng prefix `/{locale}`. Locale runtime phải resolve qua `LocaleContext`, không hardcode `vi/en` và không đóng băng danh sách locale khi ứng dụng boot.
- Phần đa ngôn ngữ tách thành hai lớp:
  - `static theme copy`: dictionary trong `themes/{theme}/lang/{locale}.json`;
  - `business content`: bản dịch entity trong `cms_page_translations`, `content_translations`, `landing_page_data` và `landing_page_block_data`.
- CMS Pages dùng translation table riêng vì có slug, SEO và workflow riêng. Các nhóm nội dung còn lại dùng resource contract chung trong `config/localized-content.php`.
- URL canonical theo locale được đăng ký trong `localized_routes`; slug chỉ được public khi translation ở trạng thái `published`.
- Workflow chuẩn: `missing/needs_translation -> draft/machine_draft -> in_review -> ready -> published`; khi nguồn đổi, bản dịch cũ phải chuyển `outdated`.
- Bản dịch máy luôn bắt đầu ở `machine_draft`, không được tự publish. Chỉ trạng thái `published` được đọc trong public reader.
- Fallback về tiếng Việt được giữ để tương thích trong thời gian rollout, nhưng fallback không có nghĩa locale đích đã sẵn sàng kinh doanh.
- `/admin/themes` quản lý locale storefront và static translation. Các form Pages/Nội dung/Landing Page quản lý business translation theo locale đang biên tập.
- Admin UI nội bộ vẫn ưu tiên tiếng Việt; phạm vi hiện tại là nội dung public/storefront, không phải dịch toàn bộ admin shell.
- Feature flags và rollback nằm ở `config/localized-content.php`; không xóa reader/bảng cũ khi chưa hoàn tất ít nhất một chu kỳ vận hành ổn định.

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
- Storefront route luôn có locale prefix. CMS Page dùng `/{locale}/p/{slug}`; dịch vụ dùng `/{locale}/s`, `/{locale}/s/{slug}`, `/{locale}/ser/{slug}`; tin tức dùng `/{locale}/c`, `/{locale}/c/{slug}`, `/{locale}/n/{slug}`; liên hệ dùng `/{locale}/contact`. Theme/admin/demo data phải dùng named route/helper, không hardcode URL cũ hoặc tự sinh `/tin-tuc`, `/dich-vu`, `/lien-he`.
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

## 7.2. Đa ngôn ngữ — trạng thái hiện hành và handoff bắt buộc

Phần này là trạng thái thực tế sau các giai đoạn chuyển đổi ngày 2026-07-30. Không dùng lại mô tả cũ rằng CMS Pages/Nội dung/Landing Page “sẽ được làm ở giai đoạn sau”.

### Phạm vi đã triển khai

- Nền tảng locale theo website: `system_locales`, `website_locales`, `LocaleContext`, cache invalidation và validation BCP 47.
- Workflow dịch có revision, review/publish timestamp, trạng thái máy dịch và tự đánh dấu `outdated` khi nguồn thay đổi.
- Canonical/localized slug và SEO qua `localized_routes`, `localized-seo.blade.php` và sitemap theo locale.
- CMS Pages có model/table/service riêng: `CmsPageTranslation`, `CmsPageLocalization`, `CmsPageResolution`.
- `content_translations` và `LocalizedContentRepository` đang phục vụ 17 resource type:
  - `cms_category`, `cms_post`;
  - `cms_service_category`, `cms_service`;
  - `cms_project_category`, `cms_project`;
  - `catalog_category`, `catalog_product`;
  - `cms_team_member`, `cms_partner`, `cms_testimonial`;
  - `cms_menu`, `site_banner`, `cms_media`, `site_profile`;
  - `real_estate_listing`, `real_estate_property_type`.
- Landing Page đã tách identity/layout khỏi dữ liệu theo locale. Slug, status, SEO nằm trong `landing_page_data`; nội dung block nằm trong `landing_page_block_data`.
- Admin Pages, Nội dung, Catalog, Bất động sản và Landing Page đã có locale context/editor tương ứng.
- UX chuẩn cho form dịch là tab locale nằm ngay trong Drawer như `CmsPageFormModal`; không chỉ đặt locale selector ở bảng phía sau Drawer. Pattern này đã áp dụng cho CMS Pages, Products/Catalog, Tin tức, Dịch vụ, Dự án, Đội ngũ nhân sự, Đối tác và Cảm nhận khách hàng qua component dùng chung `LocalizedContentTabs`. Locale chưa có bản dịch phải để trống các field dịch; field dùng chung phải kế thừa từ bản gốc và bị khóa trong chế độ dịch. Slug phải hiện ở mọi tab có detail route và tự cập nhật mỗi khi tiêu đề/tên của chính locale đó thay đổi. Trạng thái `draft/published` là trạng thái riêng của từng locale, không phải dữ liệu dùng chung. Khi tạo mới, cả generic content/Product và CMS Pages đều cho nhập trước các locale đích, giữ draft phía client, rồi yêu cầu quay lại locale nguồn để tạo master và lần lượt lưu từng bản dịch; lựa chọn trạng thái của từng tab được giữ nguyên.
- Bộ lọc `Ngôn ngữ nội dung` tại list Pages, Sản phẩm, Tin tức, Dịch vụ, Dự án, Đội ngũ nhân sự, Đối tác và Cảm nhận khách hàng phải gửi `?locale=<code>` xuống API. Generic list dùng `AdminLocalizedContentList` để bulk-overlay từ `content_translations` (không N+1); bản dịch thiếu vẫn giữ nhãn nguồn làm fallback nhận diện nhưng phải trả `_translation_status=missing` để Admin hiển thị `Chưa có`. Page list resolve trực tiếp từ `cms_page_translations` theo locale được chọn.
- Controller/builder localize dữ liệu trước khi đưa vào theme. Theme không được tự query translation table.
- Theme static dictionary, SEO head và locale route contract đã được refactor trên các theme hiện có. Canary rollout là `BOOK920`, `DN302`, `BDS701`.
- Reader mới, dual-write và legacy fallback được điều khiển bằng:
  - `LOCALIZATION_CONTENT_READER=new`;
  - `LOCALIZATION_CONTENT_DUAL_WRITE=true`;
  - `LOCALIZATION_CONTENT_LEGACY_FALLBACK=true`.

### Sáu migration localization đã tạo và đã chạy trên database local

1. `2026_07_30_000001_create_localization_foundation_tables.php`
2. `2026_07_30_000002_create_cms_page_translations_table.php`
3. `2026_07_30_000003_create_content_translations_table.php`
4. `2026_07_30_000004_upgrade_landing_page_localization.php`
5. `2026_07_30_000005_reconcile_localized_content_payloads.php`
6. `2026_07_30_000006_quarantine_partial_landing_block_translations.php`

Migration số 6 không xóa nội dung. Nó hạ các block EN còn chứa dấu hiệu tiếng Việt từ `published` về `needs_translation`, xóa mốc review/publish và giữ nguyên payload để biên tập tiếp.

### Trạng thái dữ liệu website-main tại thời điểm chốt

| Nhóm | Bản nguồn cần có bản dịch | EN đạt release gate | EN còn thiếu/chưa đạt |
| --- | ---: | ---: | ---: |
| 17 nhóm Nội dung | 374 | 0 | 374 |
| CMS Pages | 2 | 0 | 2 |
| Landing Page metadata/SEO | 12 | 0 | 12 |
| Landing Page blocks | 114 | 81 | 33 |
| **Tổng** | **502** | **81** | **421** |

Coverage tự động là 16,1%. Kiến trúc/dữ liệu nguồn đang nhất quán nhưng EN chưa sẵn sàng phát hành. 81 block EN vượt kiểm tra revision/trạng thái vẫn cần người thật kiểm tra nội dung và giao diện trước khi mở toàn cầu.

`website-main` hiện dùng `vi` làm source/default/fallback. `en` có thể vẫn mang cờ public từ dữ liệu tương thích cũ; cờ này không đồng nghĩa EN đã sẵn sàng kinh doanh. Session sau không được dựa riêng vào `website_locales.is_published` để kết luận release-ready.

### Lệnh bắt buộc trước khi sửa hoặc phát hành

- Kiểm tra migration: `php artisan migrate:status`.
- Kiểm tra tính nhất quán cấu trúc: `php artisan localization:audit --website=website-main --strict`.
- Kiểm tra đủ nội dung để phát hành: `php artisan localization:audit --website=website-main --require-ready`.
- Xuất cho CI/máy đọc: thêm `--json`.
- Regression chính: `php artisan test`.
- Theme contract: `php artisan test --filter=ThemeLocalizationContractTest`.
- Compile Blade: `php artisan view:cache`.
- Build Admin: `npm run build`.

`--strict` chỉ chứng minh schema, nguồn, route và đối chiếu dữ liệu không mâu thuẫn. Nó không chứng minh một locale đã dịch xong. Chỉ `--require-ready` mới chặn release khi translation đích chưa `published` hoặc `source_revision` không khớp nguồn hiện tại.

Validation gần nhất: 314 tests/5.705 assertions pass; Blade compile pass; Admin build pass; localization strict audit có 0 issue. `--require-ready` trả exit code lỗi đúng thiết kế vì còn 421 mục EN.

### Invariant không được phá

- Không thêm cột kiểu `title_en`, `description_fr` vào bảng master.
- Không đọc draft, `machine_draft`, `needs_translation` hoặc `outdated` trên storefront.
- Không tự publish nội dung máy dịch hoặc bản copy từ locale nguồn.
- Không nhận `website_key` từ payload admin; luôn lấy website từ `SiteContext`.
- Không dùng `migrate:fresh`, không rollback/xóa bảng localization để “làm lại”, không sửa migration đã chạy; chỉ tạo migration tiến tới.
- Không tắt dual-write/legacy fallback và không xóa bảng/cơ chế cũ trước khi canary ổn định ít nhất một chu kỳ vận hành.
- Không coi fallback VI là bản dịch EN hoàn chỉnh và không public/marketing locale mới khi release-readiness audit chưa pass.
- Khi thêm resource dịch mới, phải cập nhật contract, Admin/API, frontend reader, route/SEO nếu có slug, backfill, audit và regression test.
- Khi đổi schema block không tương thích, tăng `schema_version` và viết migration/upcaster; không đổi âm thầm ý nghĩa payload cũ.

### File neo và test cần đọc

- Kiến trúc/runtime:
  - `app/Support/Localization/LocaleContext.php`
  - `app/Support/Localization/LocalizedContentRepository.php`
  - `app/Support/Localization/CmsPageLocalization.php`
  - `app/Support/Localization/LandingPageLocalization.php`
  - `app/Support/Localization/LocalizedRouteRegistry.php`
  - `app/Support/Localization/TranslationWorkflowManager.php`
  - `app/Support/BusinessContentTranslationService.php`
  - `config/localized-content.php`
- Audit/vận hành:
  - `app/Console/Commands/LocalizationAuditCommand.php`
  - `docs/architecture/localization-foundation.md`
  - `docs/architecture/localization-rollout-runbook.md`
- Regression:
  - `tests/Feature/LocalizationFoundationTest.php`
  - `tests/Feature/CmsPageLocalizationTest.php`
  - `tests/Feature/LocalizedContentAndLandingWorkflowTest.php`
  - `tests/Feature/ThemeContentTranslationTest.php`
  - `tests/Feature/ThemeLocalizationContractTest.php`

Working tree tại thời điểm handoff đang có phạm vi thay đổi localization/theme rất lớn và chưa commit. Session mới bắt buộc chạy `git status --short`, giữ nguyên thay đổi hiện có và không tự ý revert file chỉ vì thấy diff rộng.

## 8. Những capability nghiệp vụ cần luôn ghi nhớ khi làm việc

### Yêu cầu liên hệ theo domain/website

- Mọi form liên hệ public gửi về `CmsSiteController::submitContact`; client không được tự quyết định `website_key`.
- `ResolveCurrentSite` ánh xạ hostname trong `sites` và đặt `SiteContext`; backend lấy `site_id`/`website_key` từ context này.
- `contact_inquiries` lưu tập trung mọi yêu cầu `contact` và `quote_modal`, gồm `site_id`, `website_key`, `submitted_host`, người gửi, nội dung, trạng thái và thông tin đối soát request.
- Yêu cầu `quote_modal` đồng thời tạo `orders` và gắn cùng `website_key`; `contact_inquiries.order_id` liên kết về đơn báo giá.
- Email nhận liên hệ lấy từ `site_profiles.branding.support_email` của website đã resolve, không dùng domain/key do form gửi lên.
- Khi bổ sung nguồn form mới, phải mở rộng validation `source`, vẫn lưu qua `contact_inquiries` và có test xác nhận phân tách ít nhất hai domain.

- Đây là hệ thống định hướng rộng, không chỉ có CMS. Các nhóm trọng tâm dài hạn gồm CRM, Project, Purchasing, Inventory, HRM, Sales, Accounting, CMS / website builder / theme marketplace.
- Khi đề xuất model, route, permission, menu, settings, dashboard hay schema, phải ưu tiên pattern dùng lại được cho nhiều module.

## 8.1. Module Quản lý nhân sự và Tiền lương

- App Store có hai package mới: `hrm` (HRM Core) và `payroll` (add-on phụ thuộc HRM).
- HRM gồm phòng ban, chức vụ, hồ sơ nhân sự, hợp đồng, liên kết tài khoản, nghỉ phép, chấm công và hồ sơ cá nhân.
- Payroll gồm kỳ lương, phiếu lương và phiếu lương cá nhân; trạng thái một chiều `draft -> approved -> published -> locked`.
- Không tạo bảng tài khoản HR riêng: `hrm_employees.admin_id` nullable/unique liên kết tới `admins`. RBAC quyết định được làm gì; query policy quyết định được xem dữ liệu của ai.
- Dữ liệu HRM/Payroll là dữ liệu doanh nghiệp toàn cục, không gắn `website_key`; role CMS của cùng tài khoản vẫn có thể gắn theo website.
- API module bắt buộc qua `module.enabled:hrm|payroll`. Disable/module chưa cài trả 404 có chủ đích, làm permission inactive và giữ nguyên dữ liệu; UI không được gọi API hoặc crash khi module chưa enabled.
- Hai module không cho uninstall từ App Store để tránh rollback dữ liệu nhân sự/lương.
- Role mặc định: `hrm.employee-self`, `hrm.staff`, `hrm.manager`, `payroll.employee-self`, `payroll.officer`, `payroll.approver`.
- Nhân viên chỉ thấy hồ sơ, đơn nghỉ, chấm công và phiếu lương đã công bố của chính mình.
- Khi kết thúc làm việc, tài khoản liên kết bị archive/khóa và tăng `auth_version`.
- File neo: `modules/hrm`, `modules/payroll`, `app/Http/Controllers/Admin/Api/Hrm`, `app/Http/Controllers/Admin/Api/Payroll`, `resources/admin/src/modules/hrm`, `resources/admin/src/modules/payroll`.
- Test bắt buộc: `tests/Feature/HrmModuleTest.php`.
- Tài liệu đầy đủ: `docs/architecture/hrm-and-payroll-modules.md`.

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
- `resources/admin/src/modules/themes/components/ThemePaletteEditorDrawer.jsx`
- `docs/theme-authoring-guide.md`
- `docs/theme-starter-checklist.md`
- `config/localized-content.php`
- `app/Console/Commands/LocalizationAuditCommand.php`
- `app/Support/Localization/LocaleContext.php`
- `app/Support/Localization/LocalizedContentRepository.php`
- `app/Support/Localization/CmsPageLocalization.php`
- `app/Support/Localization/LandingPageLocalization.php`
- `app/Support/BusinessContentTranslationService.php`
- `docs/architecture/localization-foundation.md`
- `docs/architecture/localization-rollout-runbook.md`

## 10. Cách làm việc tôi muốn ở session này

- Trước khi sửa, hãy đọc đúng file/symbol liên quan thay vì khám phá rộng.
- Ưu tiên sửa nhỏ, đúng root cause, không đụng phần không liên quan.
- Sau khi sửa frontend, ưu tiên chạy `npm run build` nếu thay đổi đủ đáng kể.
- Sau khi sửa backend Laravel, ưu tiên chạy `php artisan optimize:clear` nếu phù hợp.
- Nếu có bug runtime ở UI admin, hãy kiểm tra cả import thiếu, prop sai, mismatch Ant Design/React, và những chỗ render component con trong drawer/modal.
- Nếu người dùng hỏi “tiếp tục phần trước”, hãy đọc `git status` và trạng thái tài liệu trước. Ngữ cảnh gần nhất hiện gồm CMS admin, media, posts, permissions, themes, setup, UX quản trị và đợt chuyển đổi đa ngôn ngữ toàn hệ thống ngày 2026-07-30.
- Ở ngữ cảnh gần đây hơn của repo này, hãy đặc biệt nhớ thêm:
  - admin login dùng `username` hoặc `email`, customer dùng `email`
  - modal storefront login là form dùng chung `admin username` + `customer email`
  - các theme `TH0001`, `SER0100`, `SER0101` đều đã đồng bộ flow shared login này
  - không reintroduce lại `/admin/login` hoặc dedicated admin login page riêng
  - không reintroduce `tenant`, `owner`, `tenant_key`, `owner_key`, `admin_role` hoặc `admin_role_scopes`
  - không cho phép sửa/xóa role `super-admin` hay sửa/khóa/xóa admin ID `1`
  - permission module phải inactive/deprecated khi gỡ module, không xóa vật lý
  - không coi fallback tiếng Việt là bản dịch tiếng Anh hoàn chỉnh
  - không public locale mới khi `localization:audit --require-ready` chưa pass
  - không dùng `migrate:fresh` và không xóa/rollback bảng localization trong rollout
- Khi cần đề xuất kiến trúc, hãy ưu tiên các thiết kế có thể tái sử dụng cho nhiều module khác nhau trong hệ sinh thái AIO.
- Khi nói về roadmap hay solution, hãy nhớ CMS/theme marketplace là một trụ cột thương mại quan trọng của dự án.

## 11. Cách trả lời mong muốn

- Trả lời ngắn gọn, thực dụng, tập trung vào kết quả.
- Nếu cần nêu file, hãy ưu tiên chỉ đúng file sẽ sửa.
- Nếu có validation đã chạy, nói rõ cái gì pass, cái gì chỉ là warning không chặn chức năng.

Hãy dùng ngữ cảnh trên làm baseline và tiếp tục hỗ trợ tôi trên đúng codebase này.
```
