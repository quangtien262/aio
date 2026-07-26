# Theme Starter Checklist

Checklist này dùng khi tạo theme mới trong AIO. Mục tiêu là để AI/dev khác mở file ra là biết phải làm gì, cần đọc file nào, và phải kiểm thử gì trước khi báo xong.

## 0. Đọc nhanh trước khi code

- [ ] Đọc `docs/ai-session-bootstrap-prompt.md` để hiểu định hướng dự án.
- [ ] Đọc `docs/theme-authoring-guide.md` để hiểu theme registry, namespace Blade, route và translation.
- [ ] Đọc `docs/landing-page-builder.md` nếu theme có homepage/landing dạng block.
- [ ] Đọc `docs/theme-demo-data.md` nếu theme cần nút tạo data test.
- [ ] Xem theme gần nhất cùng loại website để copy pattern, ví dụ `XD0323`, `XD0322`, `XD0301`, `SER0100`.
- [ ] Nếu làm theme bất động sản, đọc `docs/architecture/real-estate-module-and-bds701-theme.md` và tái sử dụng module `real-estate`; không tạo bảng listing riêng theo theme.

## 1. Metadata cần chốt

- [ ] Theme key: `_____`, ví dụ `XD0324`.
- [ ] Theme name: `_____`.
- [ ] Website type: `ecommerce | service | corporate | news | landing | _____`.
- [ ] Theme parent: `null | _____`.
- [ ] Locale built-in: `vi`, `en`, `_____`.
- [ ] Có landing builder/homepage block: `yes | no`.
- [ ] Cần demo data/test data: `yes | no`.
- [ ] Cần auth modal: `yes | no`.
- [ ] Cần config riêng trong Theme Manager: `yes | no`.
- [ ] Nguồn dữ liệu động cần hỗ trợ: `products | posts | services | projects | service categories | product categories | post categories | custom`.

## 2. Folder và file tối thiểu

- [ ] `themes/{KEY}/theme.json`
- [ ] `themes/{KEY}/views/home.blade.php`
- [ ] `themes/{KEY}/views/layout.blade.php`
- [ ] `themes/{KEY}/views/partials/header.blade.php`
- [ ] `themes/{KEY}/views/partials/footer.blade.php`
- [ ] `themes/{KEY}/views/partials/styles.blade.php`
- [ ] `themes/{KEY}/views/partials/shell-scripts.blade.php`
- [ ] `themes/{KEY}/lang/vi.json`
- [ ] `themes/{KEY}/lang/en.json`

Các trang storefront phụ nên có đủ, vì `CmsSiteController` render trực tiếp theo namespace theme:

- [ ] `views/cms.blade.php`
- [ ] `views/category.blade.php`
- [ ] `views/product.blade.php`
- [ ] `views/search.blade.php`
- [ ] `views/cart.blade.php`
- [ ] `views/checkout.blade.php`
- [ ] `views/checkout-success.blade.php`
- [ ] `views/news.blade.php`
- [ ] `views/news-detail.blade.php`
- [ ] `views/services.blade.php`
- [ ] `views/service.blade.php`
- [ ] `views/contact.blade.php`

Preview/avatar cho `/admin/themes`:

- [ ] `public/theme-previews/{KEY}/avatar.png`
- [ ] `public/theme-previews/{KEY}/preview-{key}.png`
- [ ] `public/theme-previews/{KEY}/cover-{key}.png`

## 3. Manifest `theme.json`

Template:

```json
{
  "name": "_____",
  "key": "_____",
  "version": "0.1.0",
  "description": "_____",
  "website_type": "service",
  "parent": null,
  "preview": {
    "thumbnail": "preview-_____.png",
    "cover": "cover-_____.png"
  },
  "blocks": [
    "hero-slider",
    "about-experience"
  ],
  "supports": {
    "dark_mode": false,
    "multi_homepage": false,
    "custom_css": true,
    "block_mapping": true
  },
  "localization": {
    "default_locale": "vi",
    "supported_locales": ["vi", "en"]
  },
  "demo": {
    "content_path": "demo/content",
    "settings_path": "demo/settings",
    "default_preset": "_____-demo"
  }
}
```

Checklist:

- [ ] `key` phải trùng tên thư mục theme.
- [ ] `preview.thumbnail` và `preview.cover` phải đúng tên file trong `public/theme-previews/{KEY}`.
- [ ] `blocks` phải phản ánh đúng các block homepage/landing thực tế.
- [ ] Nếu có data test riêng, phải có `demo.default_preset`.
- [ ] JSON phải hợp lệ, không BOM/lỗi encoding.

## 4. Namespace Blade và layout

Namespace theme được auto-register theo format:

- `themes/XD0323/views/home.blade.php` => `theme-xd0323::home`

Checklist:

- [ ] Tất cả `@extends(...)` dùng namespace lowercase: `theme-{lowercase_key}::layout`.
- [ ] Không còn namespace copy nhầm từ theme cũ, ví dụ `theme-xd0322`.
- [ ] `layout.blade.php` include header/footer dùng chung cho toàn website.
- [ ] Header/footer có fallback dữ liệu nếu chưa có `SiteProfile`/menu.
- [ ] Dùng route helper, không hardcode URL legacy:
  - `route('site.home')`
  - `route('site.catalog.search')`
  - `route('site.cart.index')`
  - `route('site.blog.show', ['slug' => ...])`
  - `route('site.services.show', ['slug' => ...])`
  - `route('site.catalog.product', ['slug' => ...])`

## 5. Translation/static copy

- [ ] Static copy đặt trong `themes/{KEY}/lang/vi.json` và `themes/{KEY}/lang/en.json`.
- [ ] Blade dùng `ThemeTranslationService` hoặc `@themeT(...)` cho copy tĩnh.
- [ ] Không trộn business content vào static copy của theme.
- [ ] Không hardcode tiếng Việt dài trong layout/header/footer nếu đó là copy cố định.
- [ ] Kiểm tra JSON bằng:

```bash
php -r "json_decode(file_get_contents('themes/{KEY}/lang/vi.json'), true, 512, JSON_THROW_ON_ERROR); echo 'ok';"
```

## 5.1. Encoding UTF-8 và modal đăng nhập

Lỗi chữ kiểu `TÃ`, `Ä‘`, `áº`, `á»` là lỗi nội dung bị chuyển mã (mojibake), không phải lỗi font CSS. Không được sửa bằng cách đổi font hoặc thêm fallback font.

- [ ] Tất cả file Blade, JSON, PHP và JavaScript chứa tiếng Việt phải được lưu dưới dạng UTF-8; không chuyển qua ANSI/Windows-1252.
- [ ] Không copy lại chuỗi tiếng Việt đang hiển thị mojibake từ terminal, trình duyệt, log hoặc theme cũ.
- [ ] Nút/link **Đăng nhập** trên header phải dùng trigger `data-xd-auth-open="login"` và khi click phải mở auth modal ở tab **Đăng nhập**; không điều hướng sang trang login riêng.
- [ ] Nút/link **Đăng ký** trên header phải dùng trigger `data-xd-auth-open="register"` và khi click phải mở cùng auth modal ở tab **Đăng ký**; không điều hướng sang trang đăng ký riêng.
- [ ] Nếu header có thêm icon tài khoản hoặc nút auth ở mobile menu, mọi điểm bấm đó cũng phải mở đúng modal và đúng tab tương ứng.
- [ ] `layout.blade.php` phải nạp partial modal và script điều khiển trigger trên tất cả trang storefront, không chỉ homepage.
- [ ] Nếu copy `partials/auth-modal.blade.php` từ theme khác, phải mở cả hai tab **Đăng nhập** và **Đăng ký** để kiểm tra tiêu đề, nhãn, placeholder, trạng thái đang xử lý và thông báo lỗi/thành công.
- [ ] Ưu tiên dùng translation hoặc partial dùng chung cho nội dung modal; CSS riêng của theme chỉ chịu trách nhiệm trình bày.
- [ ] Kiểm thử tương tác thật trên desktop và mobile: click **Đăng nhập**, xác nhận modal hiển thị và tab đăng nhập được chọn; đóng modal; click **Đăng ký**, xác nhận modal hiển thị và tab đăng ký được chọn.
- [ ] Sau khi tạo hoặc sửa theme, chạy audit encoding cho toàn bộ modal đăng nhập:

```bash
php artisan test tests/Feature/ThemeAuthModalEncodingTest.php
```
- [ ] Test trên phải pass trước khi bàn giao; không thêm ngoại lệ/whitelist cho theme mới.

## 5.2. Font chữ và hiển thị tiếng Việt

Phải phân biệt đúng hai nhóm lỗi trước khi sửa:

- **Thiếu/sai font:** chữ vẫn đọc đúng nhưng kiểu chữ không đúng thiết kế, glyph bị ô vuông hoặc font tải lỗi.
- **Sai mã hóa (mojibake):** nội dung xuất hiện các chuỗi như `TÃ`, `Ãƒ`, `Ã„`, `Ã¡`, `Ã¢â`, `Â`; đổi font không thể sửa được lỗi này.

Checklist bắt buộc:

- [ ] `<meta charset="utf-8">` phải nằm trong `<head>` và xuất hiện trước title, style hoặc nội dung có tiếng Việt.
- [ ] Blade, PHP, JSON, JavaScript và CSS phải là UTF-8 hợp lệ, không BOM và không qua bước chuyển mã ANSI/Windows-1252.
- [ ] Font chính phải hỗ trợ đầy đủ glyph tiếng Việt; nếu dùng webfont thì kiểm tra đúng bộ chữ Vietnamese và không có request font trả về 404/CORS.
- [ ] Luôn khai báo fallback an toàn, ví dụ `"Segoe UI", Roboto, Arial, Helvetica, sans-serif`; font tiêu đề riêng cũng phải có fallback.
- [ ] Không chèn icon bằng ký tự đã bị chuyển mã trong CSS `content`; ưu tiên SVG/icon component hoặc ký tự Unicode đã được xác nhận UTF-8.
- [ ] Kiểm tra trực tiếp các chuỗi có dấu khó: `Đăng ký – Sản phẩm – Chính sách – Ứng dụng – Nguyễn`.
- [ ] Mở DevTools, kiểm tra computed `font-family` của heading, body, nút và form; xác nhận font thực tế đang được trình duyệt dùng.
- [ ] Kiểm tra cả homepage thường và homepage `?mod=admin`, vì modal chỉnh block/toolbar cũng có thể mang copy tiếng Việt bị lỗi.
- [ ] Sau khi tạo demo data, kiểm tra cả dữ liệu trong provider, landing block đã lưu và HTML trả về; sửa file nguồn thôi là chưa đủ nếu database vẫn chứa nội dung lỗi cũ.
- [ ] `data.vi`, nội dung mẫu trong provider và fallback Blade phải dùng tiếng Việt có dấu đầy đủ; chuỗi không dấu như `Dang ky`, `San pham`, `Tin tuc` cũng được xem là lỗi nội dung dù vẫn là UTF-8 hợp lệ.
- [ ] Audit encoding trên **toàn bộ storefront của theme** (homepage, trang sản phẩm/danh mục, tin tức, liên hệ, giỏ hàng, thanh toán, header/footer và inline editor), không chỉ kiểm tra file homepage.
- [ ] Thêm regression test `assertDontSee` cho các marker mojibake và `assertSee` ít nhất ba chuỗi tiếng Việt đúng trên homepage của theme.
- [ ] Không báo hoàn tất nếu console còn lỗi tải font, HTML còn marker mojibake hoặc font fallback làm vỡ layout ở desktop/mobile.

## 6. Landing Page Builder

Nếu theme dùng homepage/landing dạng block:

- [ ] Thêm theme key vào `LandingPageBuilder::supportsTheme`.
- [ ] Thêm case trong `defaultBlocksForTheme`.
- [ ] Tạo hàm default blocks riêng, ví dụ `xd0323DefaultBlocks()`.
- [ ] Mỗi block có đủ:
  - `block_type`
  - `label`
  - `description`
  - `anchor_id`
  - `settings`
  - `settings_schema` nếu có cấu hình nguồn dữ liệu
  - `data.vi`
  - `data.en`
  - `media` nếu cần ảnh/background
- [ ] Block động dùng `settings.source`, không hardcode nguồn trong Blade.
- [ ] Nếu cần nguồn danh mục, đảm bảo builder resolve được:
  - `catalog_categories`
  - `cms_categories`
  - `cms_service_categories`
- [ ] Homepage Blade ưu tiên `dynamic_items`, fallback về `data.content.items`.
- [ ] Các block sửa nhanh dùng `data.content.items` để user tự nhập được.

## 7. Header/footer dùng chung

- [ ] Header và footer nằm trong layout, không chỉ nằm trong homepage.
- [ ] Logo header/footer phải đọc từ `site_profiles.branding.logo_url` (dữ liệu lưu tại **Cài đặt website**); chỉ hiển thị logo chữ/icon mặc định khi `logo_url` rỗng.
- [ ] Không hardcode logo thương hiệu bằng text, SVG hoặc ảnh demo nếu `branding.logo_url` đã có giá trị.
- [ ] CSS cho logo upload phải giới hạn `max-width`, `max-height` và dùng `object-fit: contain` để không làm vỡ header/footer.
- [ ] Provider tạo dữ liệu demo phải giữ nguyên `branding.logo_url` hiện có; không được gán `logo_url => null` hoặc thay logo người dùng đã cài.
- [ ] Kiểm thử tích hợp: cài một URL logo tùy chỉnh, chạy/tạo lại demo data, mở storefront và xác nhận header/footer vẫn render đúng URL logo đó.
- [ ] Header đọc menu từ `primary-navigation` hoặc `primary`.
- [ ] Có fallback menu nếu chưa tạo menu.
- [ ] Nếu đang login admin, có thể show link `Admin` mở tab mới nếu theme yêu cầu.
- [ ] Nếu có account/cart/search, dùng route đúng của hệ thống.
- [ ] Mobile menu có script hoạt động, không phụ thuộc thư viện ngoài chưa nạp.
- [ ] Footer không được tạo thanh cuộn ngang: container phải có `max-width: 100%`, các cột grid dùng `minmax(0, ...)`, phần tử con có `min-width: 0`, và email/địa chỉ dài phải xuống dòng an toàn.
- [ ] Kiểm tra footer ở tối thiểu các mốc 1440px, 1024px, 768px và 375px; số cột phải giảm phù hợp và không có nội dung, nút hoặc logo vượt khỏi viewport.

## 7.1. Sửa nhanh block ở storefront admin mode

Mọi theme có landing builder phải hỗ trợ chỉnh block khi admin mở homepage/landing với `?mod=admin`. Không được coi việc có modal editor trong source là đủ; phải kiểm tra HTML render thực tế.

- [ ] Điều kiện bật editor phải yêu cầu đồng thời `auth('admin')->check()` và `request('mod') === 'admin'`.
- [ ] Mỗi block đang render có ID thật và nút mang `data-xd-edit-block="{BLOCK_ID}"`; không ánh xạ chỉ bằng `block_type` vì một trang có thể có nhiều block cùng loại.
- [ ] Section nên có `data-landing-block-id` và `data-block-type` để debug, định vị và kiểm thử dễ hơn.
- [ ] Homepage truyền đủ `blockPayload`, `blockUpdateUrlTemplate`, `blockSourcePreviewUrlTemplate` và danh sách `editorLocales` đang active.
- [ ] Layout nạp đủ modal editor, CSS editor và script xử lý mở/lưu; không chỉ render nút không hoạt động.
- [ ] Chế độ thường không được render nút/modal editor.
- [ ] Theme legacy chưa tích hợp editor riêng phải hoạt động qua safety net `InjectLandingAdminEditor`; theme mới vẫn nên render nút cạnh đúng section để trải nghiệm chỉnh sửa trực quan hơn toolbar fallback.
- [ ] Script editor dùng chung phải có fallback cho biến Blade tùy chọn (`$heroSlides ?? []`, `$blockPayload ?? []`, URL rỗng...) để không làm homepage trả HTTP 500.
- [ ] Chạy audit toàn bộ theme sau khi thêm/sửa theme:

```bash
php artisan test tests/Feature/LandingAdminEditorCoverageTest.php
```

Debug riêng một theme:

```bash
THEME_AUDIT_KEY=NT501 php artisan test tests/Feature/LandingAdminEditorCoverageTest.php
```

## 8. Demo data/test data

Nếu theme cần nút **Tạo data test**:

- [ ] Thêm `demo.default_preset` trong `themes/{KEY}/theme.json`.
- [ ] Tạo provider trong `app/Core/Themes/Demo/{StudlyKey}DemoContentProvider.php`.
- [ ] Provider implements `ThemeDemoContentProvider`.
- [ ] Provider có:
  - `themeKey()`
  - `defaultPreset()`
  - `preset()`
  - `generate()`
  - `delete()`
- [ ] Đăng ký provider trong `AppServiceProvider` tại `ThemeDemoContentProviderRegistry`.
- [ ] Mọi model do provider tạo phải được ghi vào `theme_demo_records`.
- [ ] `delete()` chỉ xóa record demo của theme đó, không xóa dữ liệu user nhập tay.
- [ ] Nếu tạo `CmsMedia`, phải set `file_path`; không chỉ set `file_url`.
- [ ] Nếu media dùng file local, đặt trong `storage/app/public/...` để `CmsMedia::file_url` resolve đúng `/storage/...`.
- [ ] Tạo menu `primary-navigation`.
- [ ] Tạo `SiteBanner` đúng `placement` mà block hero đang dùng.
- [ ] Gọi `LandingPageBuilder->resolveHome('website-main', KEY, true)` để sinh landing page mặc định.
- [ ] Nếu muốn drawer chi tiết hiện nút riêng, kiểm tra `ThemePreviewDetailsPanel.jsx` có cho theme key đó mở `onOpenDemoCreate`.

Kiểm thử provider không làm bẩn DB bằng transaction rollback:

```bash
php artisan tinker --execute="DB::beginTransaction(); try { `$result = app('App\\Core\\Themes\\ThemeDemoContentGenerator')->generate('{KEY}', '{PRESET}'); dump(`$result['counts']); } finally { DB::rollBack(); }"
```

## 9. Admin Theme Manager

- [ ] Theme xuất hiện ở `/admin/themes`.
- [ ] Ảnh đại diện/preview/cover hiển thị đúng.
- [ ] Drawer chi tiết mở được.
- [ ] Nút kích hoạt hoạt động.
- [ ] Nếu có data test, nút **Tạo data test** xuất hiện và dùng đúng preset.
- [ ] Nếu có palette/config riêng, editor nằm trong Theme Manager, không nhét vào Setup Wizard nếu chỉ phục vụ riêng theme đó.
- [ ] Sau khi sửa React admin, chạy `npm run build`.

## 10. Kiểm thử cuối

Chạy các kiểm tra tối thiểu:

```bash
php -l app/Support/LandingPages/LandingPageBuilder.php
php -l app/Providers/AppServiceProvider.php
php artisan optimize:clear
npm run build
```

Nếu có provider demo:

```bash
php -l app/Core/Themes/Demo/{StudlyKey}DemoContentProvider.php
php artisan tinker --execute="dump(app('App\\Core\\Themes\\ThemeDemoContentGenerator')->defaultPresetForTheme('{KEY}'));"
```

Render view nhanh:

```bash
php artisan tinker --execute="URL::defaults(['locale'=>'vi']); view('theme-{lowercase_key}::home', ['landingBlocks'=>[], 'landingPage'=>[], 'siteProfile'=>[], 'menus'=>[]])->render(); dump('render-ok');"
```

Checklist UI:

- [ ] Homepage render được.
- [ ] CMS page render được.
- [ ] Category/Product/Search render được.
- [ ] Cart/Checkout render được.
- [ ] Header/footer xuất hiện trên các trang phụ.
- [ ] Click **Đăng nhập** trên header mở auth modal ở tab đăng nhập; không reload hoặc đổi trang.
- [ ] Click **Đăng ký** trên header mở auth modal ở tab đăng ký; không reload hoặc đổi trang.
- [ ] Đóng modal rồi mở lại từng flow vẫn đúng tab; kiểm tra cả header desktop và mobile menu/icon tài khoản nếu có.
- [ ] Static copy lấy từ `lang/*.json`.
- [ ] Block động hiển thị dữ liệu thật hoặc fallback ổn.
- [ ] Đăng nhập admin, mở `?mod=admin`: mọi landing theme có nút **Sửa khối**, modal mở được và lưu đúng block ID.
- [ ] Mở lại không có `mod=admin`: không còn nút/modal quản trị trong HTML.
- [ ] Tạp chí/tin tức không hiện ngày/người đăng nếu yêu cầu thiết kế nói bỏ.
- [ ] Mobile không vỡ layout.
- [ ] Không còn text mojibake như `Ä`, `áº`, `Ã`.
- [ ] Không còn namespace/theme key copy nhầm.
- [ ] Không đụng schema nếu chưa thật sự cần.

## 11. Bài học từ XD0323

- [ ] Nếu copy theme cũ sang theme mới, phải thay toàn bộ namespace trong tất cả view phụ, không chỉ `home.blade.php`.
- [ ] `theme.json` có preview thôi chưa đủ; phải có file thật trong `public/theme-previews/{KEY}`.
- [ ] `avatar.png` được Theme Manager ưu tiên ở một số danh sách, nên nên tạo cả `avatar.png`, `preview-*.png`, `cover-*.png`.
- [ ] Nếu thêm nút **Tạo data test** trong drawer, backend cũng phải có provider riêng; nếu không sẽ rơi về preset chung và data không khớp layout.
- [ ] Provider demo phải chạy thử bằng transaction rollback trước khi báo xong.
- [ ] Với `CmsMedia`, thiếu `file_path` sẽ lỗi SQL ở MySQL strict mode.
- [ ] Sau khi sửa admin React, phải chạy `npm run build`; chỉ sửa source JSX chưa đủ cho bản đang chạy bằng build assets.

## 12. File nên mở khi bắt đầu

- `docs/theme-authoring-guide.md`
- `docs/landing-page-builder.md`
- `docs/theme-demo-data.md`
- `app/Core/Themes/ThemeRegistry.php`
- `app/Core/Themes/ThemeTranslationService.php`
- `app/Support/LandingPages/LandingPageBuilder.php`
- `app/Http/Controllers/Site/CmsSiteController.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/Admin/Api/ThemeDemoDataController.php`
- `resources/admin/src/modules/themes/pages/ThemeManagerPage.jsx`
- `resources/admin/src/modules/themes/components/ThemePreviewDetailsPanel.jsx`
- `resources/admin/src/modules/themes/components/ThemeDemoDataModal.jsx`
- `themes/XD0323/theme.json`
- `themes/XD0323/views/home.blade.php`
- `app/Core/Themes/Demo/Xd0323DemoContentProvider.php`
