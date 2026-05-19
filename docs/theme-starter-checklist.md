# Theme Starter Checklist

Copy-paste checklist này khi bắt đầu làm một theme mới trong AIO.

## 1. Metadata nhanh

- Theme key: `_____`
- Theme name: `_____`
- Website type: `ecommerce | service | corporate | news | landing | _____`
- Theme parent: `null | _____`
- Locale built-in: `vi`, `en`, `_____`
- Cần demo data: `yes | no`
- Cần auth modal: `yes | no`
- Cần config riêng trong Theme Manager: `yes | no`

## 2. Folder tối thiểu

Tạo đủ:

- `themes/{KEY}/theme.json`
- `themes/{KEY}/views/`
- `themes/{KEY}/lang/vi.json`
- `themes/{KEY}/lang/en.json`

Nếu cần preview/avatar:

- `public/theme-previews/{KEY}/avatar.*`
- `public/theme-previews/{KEY}/preview-*`
- `public/theme-previews/{KEY}/cover-*`

## 3. Manifest template

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
    "_____",
    "_____"
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
    "settings_path": "demo/settings"
  }
}
```

## 4. View tối thiểu phải có

Tạo đủ các file storefront chính này trước khi đi sâu UI:

- `views/home.blade.php`
- `views/cms.blade.php`
- `views/category.blade.php`
- `views/product.blade.php`
- `views/search.blade.php`
- `views/cart.blade.php`
- `views/checkout.blade.php`
- `views/checkout-success.blade.php`

Nếu thiếu, storefront có thể `404` vì `CmsSiteController` render trực tiếp theo namespace theme.

## 5. Copy/translation rules

- Đưa static copy vào `lang/*.json`
- Dùng `@themeT(...)` hoặc `ThemeTranslationService`
- Không hardcode text tĩnh tràn lan trong Blade
- Không trộn business content translation vào static copy

## 6. Auth modal rules

Nếu theme có login modal, phải theo shared login hiện tại:

- label field login: `Email khách hàng / Username admin`
- input name: `login`
- submit vào `route('customer.auth.store')`
- backend sẽ thử admin trước, rồi fallback customer

Không làm lại:

- `/admin/login`
- form admin login riêng ngoài storefront
- validate login field như email-only

## 7. Nếu theme cần config riêng

- Config branding cơ bản: để setup giữ
- Config UI chuyên biệt theo theme: đưa vào Theme Manager
- Không để một loại dữ liệu chỉnh ở 2 nơi

Ví dụ pattern đúng: palette của `TH0002`

## 8. Nếu theme cần demo data

- khai báo `demo` trong `theme.json`
- thêm preset/mapping trong `ThemeDemoContentGenerator`
- chuẩn bị asset local nếu cần ở `public/theme-demo/...`

## 9. Validate cuối

Tick hết trước khi coi là xong phase đầu:

- [ ] Theme xuất hiện trong Theme Manager
- [ ] Preview/avatar hiện đúng
- [ ] Activate theme được
- [ ] Homepage render được
- [ ] CMS page render được
- [ ] Category/Product/Search render được
- [ ] Checkout flow render được
- [ ] Static copy lấy từ `lang/*.json`
- [ ] Translation drawer load được entries
- [ ] Auth modal đúng shared login flow nếu có
- [ ] Demo data chạy được nếu có
- [ ] Không đụng schema nếu chưa thật sự cần

## 10. File nên mở ngay khi bắt đầu

- `docs/theme-authoring-guide.md`
- `app/Core/Themes/ThemeRegistry.php`
- `app/Core/Themes/ThemeTranslationService.php`
- `app/Http/Controllers/Site/CmsSiteController.php`
- `themes/TH0001/theme.json`
- `themes/SER0100/theme.json`
