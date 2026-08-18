# Theme Authoring Guide

Tài liệu này ghi lại cơ chế làm việc thực tế của theme trong AIO để khi vào một session khác, AI có thể đọc nhanh rồi tiếp tục làm một theme mới mà không phải lần lại toàn bộ source.

Nếu cần bắt tay làm ngay theo kiểu copy-paste từng bước, đọc thêm: `docs/theme-starter-checklist.md`.

## 1. Theme trong repo này là gì

- Mỗi theme là một package filesystem nằm dưới `themes/{THEME_KEY}`.
- Tối thiểu một theme đang chạy được cần có:
  - `theme.json`
  - `views/`
  - `lang/`
- Theme được kích hoạt theo từng website trong cùng codebase; theme chỉ quyết định render storefront, không nắm dữ liệu lõi.
- Dữ liệu CMS, Catalog, Banner, SiteProfile, Translation vẫn là dữ liệu chung của hệ thống; đổi theme không được làm mất dữ liệu đó.

## 2. Runtime đang nhận diện theme như thế nào

- `AppServiceProvider` tự quét toàn bộ thư mục `themes/*` khi boot app.
- Nếu một theme có `views/`, app đăng ký Blade namespace theo format:
  - `theme-{lowercase_key}::...`
  - ví dụ `themes/SER0101/views/home.blade.php` sẽ được gọi là `theme-ser0101::home`
- Nếu một theme có `lang/`, app nạp JSON translation của theme từ thư mục đó.
- Mốc code chính:
  - `app/Providers/AppServiceProvider.php`
  - `app/Core/Themes/ThemeRegistry.php`

## 3. Manifest `theme.json`

`theme.json` là nguồn sự thật để Theme Manager và Theme Registry biết theme tồn tại và có capability gì.

Các field đang được dùng thực tế:

- `name`: tên hiển thị trong admin
- `key`: mã theme, ví dụ `XD0301`, `SER0101`
- `version`: version nội bộ
- `description`: mô tả ngắn
- `website_type`: loại website, ví dụ `ecommerce`, `service`
- `parent`: theme cha nếu có, hiện đa số là `null`
- `preview.thumbnail`, `preview.cover`: tên file preview, map sang `public/theme-previews/{key}/...`
- `blocks`: danh sách block/section theme khai báo cho admin hiểu cấu trúc theme
- `supports`: feature flags như `custom_css`, `block_mapping`
- `localization.default_locale`, `localization.supported_locales`: built-in locale của theme
- `demo.content_path`, `demo.settings_path`: đường dẫn metadata cho demo data

Theme Registry đọc manifest trực tiếp từ `themes/*/theme.json`, rồi merge với trạng thái cài đặt trong DB (`theme_installations`) và số lượng record demo (`theme_demo_records`).

File neo:

- `app/Core/Themes/ThemeRegistry.php`
- `app/Core/Themes/ThemeManifest.php`
- ví dụ manifest thật: `themes/XD0301/theme.json`, `themes/SER0101/theme.json`

## 4. Theme được kích hoạt và lưu ở đâu

- Admin lấy danh sách theme qua `ThemeRegistryController`.
- Khi kích hoạt theme, `ThemeActivationController` sẽ:
  - đảm bảo có record trong `theme_installations`
  - lấy website hiện tại từ `SiteContext`
  - cập nhật đồng thời `sites.theme_key` và `site_profiles.active_theme_key` của đúng website đó
  - đồng bộ `site_profiles.website_type` nếu cần
  - tính lại `theme_installations.is_active` trên toàn hệ thống: một theme vẫn active nếu còn ít nhất một `site_profile` đang dùng theme đó
- `SiteContext::themeKey()` ưu tiên `sites.theme_key`; `site_profiles.active_theme_key` chỉ là fallback tương thích khi site chưa có `theme_key`.
- Storefront render theo theme của website hiện tại, không có route riêng cho từng theme và không được giả định chỉ có một active theme cho toàn bộ codebase.

File neo:

- `app/Http/Controllers/Admin/Api/ThemeRegistryController.php`
- `app/Http/Controllers/Admin/Api/ThemeActivationController.php`

## 5. Storefront chọn view theme như thế nào

- `CmsSiteController` resolve active theme qua `SiteContext::themeKey()`, tức ưu tiên `sites.theme_key` rồi mới fallback `site_profiles.active_theme_key`.
- Sau đó controller cố gắng render view theo namespace theme:
  - homepage: `theme-{key}::home`
  - CMS page/content: view chuyên biệt theo content type (`services`, `service`, `projects`, `project`, `news`, `news-detail`, `contact`), rồi fallback về `theme-{key}::cms`
  - catalog views khác: `theme-{key}::{viewKey}`
- Nếu theme không có CMS view chuyên biệt lẫn `cms`, controller fallback về shared view `site-cms`.
- Với Catalog, controller ưu tiên view của active theme, sau đó fallback về view cùng tên trong `theme-shop601`; chỉ `abort(404)` nếu cả hai đều không có.
- Fallback giúp storefront không vỡ ngay khi thiếu view, nhưng theme mới vẫn nên cung cấp các view phù hợp với trải nghiệm và contract riêng của theme thay vì phụ thuộc lâu dài vào fallback chung.

Các view storefront chính thường phải có tối thiểu:

- `home.blade.php`
- `cms.blade.php`
- `category.blade.php`
- `product.blade.php`
- `search.blade.php`
- `cart.blade.php`
- `checkout.blade.php`
- `checkout-success.blade.php`

File neo:

- `app/Http/Controllers/Site/CmsSiteController.php`

## 6. Translation của theme hoạt động ra sao

- Theme có 2 lớp translation cần phân biệt rõ:
  - `static theme copy`: text giao diện của riêng theme
  - `business content`: dữ liệu CMS/Catalog/Banner/SiteProfile được expose ra translation key riêng
- `ThemeTranslationService` chỉ xử lý lớp `static theme copy` của theme.
- Nguồn dữ liệu static copy gồm:
  - file JSON trong `themes/{key}/lang/{locale}.json`
  - override trong DB bảng `theme_translations`
- Fallback thực tế:
  - lấy file ở fallback locale
  - merge file locale hiện tại nếu có
  - đè bằng DB override nếu có
- Ở Blade, theme thường lấy text bằng 2 cách:
  - gọi trực tiếp `app(ThemeTranslationService::class)->bladeText(...)`
  - dùng directive `@themeT(...)`

Rule khi làm theme mới:

- text tĩnh của giao diện phải đi vào `lang/*.json`, không hardcode tràn lan trong Blade
- business content không được hardcode translation trực tiếp trong Blade; phải đi theo key chuẩn của flow translation manager hiện có
- header phải include `partials.storefront-language-switcher`, trừ khi theme có UI riêng nhưng vẫn tuân thủ contract `data-storefront-language-switcher`
- không hardcode `VI/EN`, icon cờ hoặc query `?locale=`; locale public lấy từ `FrontendLocalization::localeOptions()`
- URL đổi locale dùng `FrontendRouteUrl::localeSwitchUrls()`/`switchLocale()` để giữ đúng canonical slug của từng bản dịch và về homepage locale đích nếu resource chưa được dịch
- Với item động, chỉ tạo detail/category URL khi locale đích có canonical route đã publish; nếu chưa có thì link về index hoặc homepage của locale đích, tuyệt đối không ghép locale đích với slug master/source

File neo:

- `app/Core/Themes/ThemeTranslationService.php`
- `app/Http/Controllers/Admin/Api/ThemeTranslationIndexController.php`
- `app/Http/Controllers/Admin/Api/ThemeTranslationManagementController.php`

## 7. Theme Manager đang quản lý gì

`/admin/themes` hiện không chỉ là danh sách theme. Đây là điểm quản trị tập trung cho:

- registry theme
- preview theme
- activate theme
- locale storefront
- translation của theme
- demo data cho theme
- palette/theme-specific customization cho các theme cần cấu hình riêng

Khi làm theme mới, nên nghĩ ngay từ đầu theme đó sẽ xuất hiện trong Theme Manager với các nhu cầu:

- có manifest rõ ràng
- có preview/avatar
- có translation entries hợp lệ
- có thể generate demo data nếu theme cần preset demo
- nếu có palette/config riêng, phải đặt ở Theme Manager thay vì nhét lại vào Setup Wizard

File neo:

- `resources/admin/src/modules/themes/pages/ThemeManagerPage.jsx`
- `resources/admin/src/modules/themes/components/ThemeTranslationDrawer.jsx`
- `resources/admin/src/modules/themes/components/ThemePaletteEditorDrawer.jsx`
- `resources/admin/src/pages/routes/ThemesRoutePage.jsx`

## 8. Demo data của theme

- Theme có thể khai báo metadata demo trong `theme.json` qua `demo.content_path` và `demo.settings_path`.
- Admin gọi `ThemeDemoDataController`, controller này dùng `ThemeDemoContentGenerator` để generate hoặc delete demo data.
- `ThemeRegistry` cũng dùng `theme_demo_records` để biết theme nào đang có demo data.
- Nếu làm theme mới mà cần nút generate demo data trong Theme Manager, phải thiết kế preset và mapping tương ứng trong generator.

File neo:

- `app/Http/Controllers/Admin/Api/ThemeDemoDataController.php`
- `app/Core/Themes/ThemeDemoContentGenerator.php`
- `app/Models/ThemeDemoRecord.php`
- `docs/theme-demo-curated-assets.md`

## 9. Storefront route conventions for CMS/content pages

Keep CMS/content routes explicit. Do not route blog, service, landing, or contact pages through a generic `/{slug}` fallback.

- Normal CMS page: `/p/{slug}` via `site.pages.show`.
- Landing page: `/land/{slug}` via `site.landing.show`; homepage remains `/` via `site.home` inside the locale prefix.
- Service index/category: `/s` and `/s/{slug}` via `site.services.index` / `site.services.category`.
- Service detail: `/ser/{slug}` via `site.services.show`.
- News index/category: `/c` and `/c/{slug}` via `site.blog.index` / `site.blog.category`.
- News detail: `/n/{slug}` via `site.blog.show`.
- Contact page/form: `/contact` via `site.contact` / `site.contact.submit`.
- Product category/detail currently keep the localized catalog route helpers: `site.catalog.category` and `site.catalog.product`.

Slug và trạng thái public của CMS page/landing page là dữ liệu theo locale trong `cms_page_translations` / `landing_page_data`, được publish thành canonical path trong `localized_routes`. Các cột slug/status ở master chỉ còn phục vụ tương thích; theme không được tự coi chúng là canonical của mọi locale.

Theme Blade files should use route helpers or canonical URLs returned by the localization layer instead of hard-coded legacy/source URLs. Avoid reintroducing `/tin-tuc`, `/tin-tuc?category=...`, `/dich-vu`, `/lien-he`, generic `/{slug}`, or landing `/{slug}`; saved legacy links may redirect, but new theme/admin/demo data must use the route names above. Do not add `.html` suffixes unless the whole route strategy is changed consistently with canonical/redirect rules.

## 10. Shared auth modal là convention storefront mới

- Dedicated admin login page riêng đã bị bỏ.
- Flow login storefront hiện là shared login giữa admin và customer.
- Backend nhận request qua `customer.auth.store`, sau đó:
  - thử guard `admin` trước bằng `username`
  - nếu không khớp admin mới fallback sang `customer` bằng `email`
- Vì vậy các theme có auth modal nên dùng login field chung kiểu:
  - label: `Email khách hàng / Username admin`
  - input name: `login`
- Các theme hiện đã đồng bộ flow này:
  - `SHOP601`
  - `SER0101`

Khi tạo theme mới có auth modal, không reintroduce lại:

- `/admin/login`
- form login admin riêng trong storefront
- validation ép field login phải là email

File neo:

- `app/Http/Controllers/Customer/AuthenticatedSessionController.php`
- `app/Http/Controllers/Admin/AuthenticatedSessionController.php`
- `bootstrap/app.php`

## 11. Theme-specific configuration

- Cấu hình chuyên biệt của theme được lưu trong `site_profiles.branding`.
- Editor tương ứng phải được đặt ở Theme Manager thay vì Setup Wizard.
- Đây là pattern dùng chung cho cấu hình mang tính theme-specific.

Rule cần giữ:

- cấu hình branding cơ bản có thể nằm ở setup
- cấu hình UI chuyên biệt của từng theme nên ở Theme Manager
- không để một loại dữ liệu bị chỉnh ở 2 nơi

Branding public của storefront phải tuân theo contract dùng chung:

- Blade đọc từ `themeShellData.branding`, không query database trực tiếp.
- `ThemeBrandingResolver` ưu tiên `site_theme_profiles.branding` của đúng website/theme và fallback dữ liệu legacy trong `site_profiles.branding`.
- Logo trong ảnh/asset demo không phải logo runtime. Header và footer chỉ render `branding.logo_url`; nếu rỗng có thể dùng `branding.company_name` hoặc `site_name` làm fallback chữ.
- Provider demo không được xóa hay thay `logo_url` đã cấu hình.
- Theme mới bắt buộc chạy `ThemeBrandingContractTest` bên cạnh localization contract.

File neo:

- `resources/admin/src/modules/themes/components/ThemePaletteEditorDrawer.jsx`
- `app/Http/Controllers/Admin/Api/SetupProfileController.php`

## 12. Checklist tối thiểu khi tạo theme mới

1. Tạo thư mục `themes/{KEY}` với ít nhất `theme.json`, `views/`, `lang/`.
2. Viết manifest đúng các field admin đang dùng: `key`, `name`, `website_type`, `preview`, `blocks`, `supports`, `localization`, `demo` nếu có.
3. Tạo đầy đủ các view storefront chính để `CmsSiteController` render được; `layout.blade.php` bắt buộc dùng `<x-storefront-head>` và chỉ truyền font/CSS/script đặc thù qua slot.
4. Đưa static copy vào `lang/vi.json`, `lang/en.json` hoặc locale built-in tương ứng.
5. Dùng `ThemeTranslationService` / `@themeT` cho copy tĩnh thay vì hardcode tràn lan.
6. Include bộ chọn ngôn ngữ storefront dùng chung trong header và kiểm tra desktop/mobile.
7. Nếu theme có auth modal, dùng shared login admin/customer convention.
8. Nếu theme cần demo data, map preset trong `ThemeDemoContentGenerator`.
9. Nếu theme cần config riêng như palette, đặt editor ở Theme Manager.
10. Thêm preview/avatar tương ứng trong `public/theme-previews/{KEY}`.
11. Kiểm tra theme có xuất hiện đúng trong Theme Manager, activate được, render được các route storefront chính, translation hoạt động, và nếu có thì demo data chạy được.

### Shared document head contract

- Mọi theme layout dùng `resources/views/components/storefront-head.blade.php`; không tự khai báo `<head>` hoặc `<title>`.
- Component chung sở hữu charset, viewport, CSRF, title, description, keywords, favicon, robots, Open Graph, Twitter metadata, canonical, hreflang và `@stack('head')`.
- Theme chỉ đặt font, icon library, stylesheet, preload hoặc script cần chạy sớm trong slot của component.
- Không đưa Google Fonts hay Font Awesome thành dependency mặc định của component vì theme có thể dùng font đóng gói riêng.
- Title ưu tiên SEO title thực sự của trang, sau đó `site_profiles.site_name`, `branding.company_name` và fallback trung tính. Tiêu đề landing mẫu tự sinh theo mã theme không được ghi đè tên website trong DB.
- Contract bắt buộc: `ThemeDocumentHeadContractTest`, `ThemeLocalizationContractTest` và `ThemeBrandingContractTest`.

## 13. Các file nên đọc trước khi bắt tay làm theme mới

- `app/Core/Themes/ThemeRegistry.php`
- `app/Core/Themes/ThemeTranslationService.php`
- `app/Http/Controllers/Site/CmsSiteController.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/Admin/Api/ThemeRegistryController.php`
- `app/Http/Controllers/Admin/Api/ThemeActivationController.php`
- `app/Http/Controllers/Admin/Api/ThemeDemoDataController.php`
- `resources/admin/src/modules/themes/pages/ThemeManagerPage.jsx`
- `themes/SER0101/theme.json`
- `themes/SER0101/views/partials/engagement-modals.blade.php`

## 14. Nguyên tắc thiết kế khi AI làm theme mới trong repo này

- Bám pattern theme đã có, không tự phát minh một engine song song.
- Ưu tiên reuse flow CMS/Catalog/Banner/Menu/Translation hiện tại trước khi nghĩ tới schema mới.
- Theme chỉ nên khác nhau ở render, block composition, copy, visual language, và một số config theme-specific thật cần thiết.
- Nếu chưa rõ route/view nào bắt buộc, đọc `CmsSiteController` trước khi code.
- Nếu chưa rõ translation/static copy nên đặt ở đâu, đọc `ThemeTranslationService` và xem `XD0301`, `SER0101` trước.
