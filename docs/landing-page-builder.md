# Landing Page Builder Architecture

Tài liệu này ghi lại quyết định thiết kế landing page builder để AI/dev ở session khác có thể tiếp tục đúng hướng.

## Mục Tiêu

Landing page trong hệ thống là một page có thể được lắp từ các block có sẵn của theme. User được phép:

- Tạo nhiều landing page.
- Sắp xếp thứ tự block.
- CRUD block instance, nhưng khi thêm mới chỉ chọn từ các block type do theme cung cấp.
- Sửa content trực tiếp ở storefront khi admin đã login và URL có `?mod=admin`.
- Dùng cùng engine cho trang chủ và các landing page khác.

## Trang Chủ

Không dùng `id = 0` cho trang chủ.

Trang chủ là một record bình thường trong `landing_pages`:

- `is_home = true`
- `page_type = home`
- `slug = home`
- `template = home`

Route `/vi` resolve landing page có `is_home = true`. Landing page thường dùng `/vi/land/{slug}` qua route `site.landing.show`; không dùng generic `/vi/{slug}`. Admin không nên cho xóa record home, chỉ cho sửa cấu trúc block.

## Đa Ngôn Ngữ

Landing builder dùng mô hình master/data:

- Bảng master lưu dữ liệu không phụ thuộc ngôn ngữ.
- Bảng `_data` lưu text theo locale.

Các bảng chính:

- `landing_pages`: master page, lưu identity và config không dịch như `website_key`, `theme_key`, `page_type`, `template`, `is_home`, `sort_order`, `settings`, `media`. Các cột `slug`, `status`, `published_at` còn tồn tại để tương thích với flow cũ nhưng không phải canonical đa locale.
- `landing_page_data`: lưu `slug`, title, excerpt, SEO text và workflow dịch theo `locale`, gồm `translation_status`, revision và các mốc review/publish.
- `landing_page_blocks`: master block, lưu `block_type`, `sort_order`, `is_visible`, `anchor_id`, `settings`, `media`.
- `landing_page_block_data`: title, subtitle, description, button label, content JSON và workflow dịch theo `locale`.

Canonical public path được đăng ký trong `localized_routes` theo website, resource và locale. Chỉ translation ở trạng thái `published` mới có route public; đổi slug đã publish sẽ giữ route cũ làm redirect về canonical mới.

Không nên lưu toàn bộ content đa ngôn ngữ vào một JSON duy nhất trong bảng master vì sẽ khó validate, fallback locale, import/export và edit theo tab ngôn ngữ.

## Master Vs Data

`landing_page_blocks.settings` dùng cho config không dịch:

- layout variant
- số lượng item
- category filter
- sort mode
- menu location
- autoplay timing

`landing_page_blocks.media` dùng cho media không dịch:

- image URL
- media id
- icon
- video URL
- logo/link kỹ thuật

`landing_page_block_data.content` dùng cho content dịch:

- slide title/summary/kicker
- service title/summary
- testimonial quote/name/role nếu cần dịch
- CTA copy
- text dài

## Block Động

Một số block không lưu danh sách item thủ công trong content:

- `latest_posts`: chỉ sửa heading/mô tả, phần item lấy từ `cms_posts`; settings quyết định category, limit, sort.
- `featured_products`: chỉ sửa heading/mô tả, phần item lấy từ catalog; settings quyết định category, limit, featured/newest.
- `menu_links`: item lấy từ `cms_menus`; settings quyết định menu location.

User chỉ cấu hình điều kiện hiển thị data, không edit trực tiếp item động trong landing block.

## Theme Và Block Type

Mỗi theme cung cấp danh sách block type có sẵn. Với `XD0301`, block mặc định gồm:

- `hero_slider`
- `about_experience`
- `content_mosaic`
- `featured_services`
- `project_gallery`
- `team_members`
- `testimonials`
- `partner_logos`
- `footer_contact`

Service hiện tại: `App\Support\LandingPages\LandingPageBuilder`.

Service chịu trách nhiệm:

- kiểm tra theme có hỗ trợ landing builder không
- seed homepage mặc định
- trả danh sách block type có thể thêm
- serialize block theo locale hiện tại
- resolve dynamic items cho block đặc thù

## Inline Edit

## XD0301 Dynamic Data Notes

- `featured_services` defaults to `cms_services`; settings can switch source to latest posts or featured products.
- `content_mosaic` defaults to `cms_posts`; settings can switch source to products, services, or projects.
- `project_gallery` defaults to `cms_projects`; settings can switch source to latest posts, featured products, or CMS services.
- `team_members` defaults to `cms_team_members`; team members use a master table plus image table for featured avatar/gallery images and alt text.
- `testimonials` defaults to `cms_testimonials`; each item stores customer name, role/company, quote, avatar image, alt text, featured flag and sort order.
- `partner_logos` defaults to `cms_partners`; each partner stores title, description, logo/image, alt text, link URL, featured flag and sort order.
- `cms_services` and `cms_projects` both use a master table plus image table, where one image can be marked as featured and every image can store alt text.

## Reusable Dynamic Content Source

Các block dạng danh sách/card/slider không nên hardcode nguồn dữ liệu trong Blade. Nguồn dữ liệu được cấu hình bằng `landing_page_blocks.settings.source` và resolve tập trung trong `App\Support\LandingPages\LandingPageBuilder`.

Các source chuẩn đang dùng:

- `cms_services`: lấy từ bảng dịch vụ.
- `cms_projects`: lấy từ bảng dự án.
- `cms_posts`: lấy từ bảng tin tức/bài viết.
- `cms_products`: lấy từ catalog sản phẩm.

Các setting đi kèm:

- `limit`: số item hiển thị, giới hạn an toàn trong khoảng 1-12.
- `category_id`: dùng khi source là tin tức hoặc sản phẩm để lọc theo danh mục.
- `featured_only`: dùng cho các source có cờ nổi bật như dịch vụ/dự án.

Modal inline edit của XD0301 đọc `settings_schema.source` từ block payload để tự render dropdown "Nguồn nội dung". Cách này có thể tái sử dụng cho nhiều block khác: chỉ cần khai báo `settings_schema.source.options` trong định nghĩa block và backend resolve qua helper nguồn chung.

Frontend chỉ hiển thị nút sửa khi:

```php
auth('admin')->check() && request('mod') === 'admin'
```

Mỗi block render wrapper:

```html
<section data-landing-block-id="..." data-block-type="...">
```

Modal edit sửa:

- locale hiện tại
- title
- subtitle
- description
- button label
- content JSON theo locale
- settings JSON chung
- media JSON chung
- anchor
- visibility

API hiện tại:

- `GET /admin/api/landing/pages/{landingPage}/blocks`
- `POST /admin/api/landing/pages/{landingPage}/blocks`
- `PUT /admin/api/landing/pages/{landingPage}/blocks/reorder`
- `POST /admin/api/landing/pages/{landingPage}/translations/{locale}/transition`
- `PUT /admin/api/landing/blocks/{block}`
- `POST /admin/api/landing/blocks/{block}/translations/{locale}/transition`
- `DELETE /admin/api/landing/blocks/{block}`

Workflow authoring cần giữ:

- Nội dung và slug từng locale được ghi trong `data_by_locale` vào `landing_page_data`, không lấy slug master làm canonical cho locale đích.
- Trạng thái locale chuyển qua endpoint `translations/{locale}/transition` theo workflow `draft` → `in_review`/`ready` → `published`; chỉ `published` mới được public resolver và language switcher sử dụng.
- Top-level `slug`/`status` trên API hiện còn hỗ trợ source-locale và compatibility, nhưng code mới không được bypass workflow bằng cách chỉ sửa master.
- Dynamic item trong landing block chỉ dùng canonical route đã publish của locale đang render. Nếu target locale chưa có route đó, item phải về localized index phù hợp (`/c`, `/s`, `/pj`, `/bds`) hoặc homepage của target locale; không dùng source slug dưới prefix locale đích.

## Menu Landingpage

Landing page có thể dùng menu kiểu `landingpage`. Menu item trỏ tới anchor của block:

- Trang chủ: `#gioi-thieu`
- Landing page khác: `/vi/land/{localized-slug}#gioi-thieu`

Anchor nằm ở `landing_page_blocks.anchor_id`, vì vậy khi đổi thứ tự block thì menu vẫn đúng. Base URL của menu được lấy từ canonical localized route; nếu locale đích chưa có bản publish thì về homepage locale đích thay vì ghép với slug nguồn.

## Nguyên Tắc Tiếp Tục

- Không hardcode content dài trong Blade khi đã có landing data.
- Không dùng `id = 0` cho trang chủ.
- Không nhét landing block instance vào `theme_block` translation hiện tại; `theme_block` chỉ phù hợp copy cố định theo theme.
- Khi thêm block type mới, cần định nghĩa default content, settings schema và render case trong theme/partial tương ứng.
- Không lấy `landing_pages.slug/status` làm nguồn canonical đa locale; mọi slug, publish state và link public phải đi qua `landing_page_data` cùng `localized_routes`.
