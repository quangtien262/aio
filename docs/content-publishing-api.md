# Content Publishing API

API này nhận bài viết từ workspace `tech_content` và ghi vào CMS của một website cố định.
Token quyết định `website_key`; client không được gửi hoặc thay đổi website context.

Runbook vận hành production, category ID, quy tắc ảnh và trạng thái dữ liệu hiện
tại nằm tại `../../docs/content-production-runbook.md`. Phải đọc runbook trước khi
đăng hoặc cập nhật bài legacy.

## Triển khai

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan content-api:issue-token "Tech Content Publisher" \
  --website=website-main \
  --source=tech-content \
  --abilities=posts.read,posts.write,posts.publish,media.write,translations.read,translations.write,translations.publish
```

Token chỉ hiển thị một lần và được lưu dạng SHA-256 trong database. Thu hồi token:

```bash
php artisan content-api:revoke-token ID
```

## Xác thực

Mọi request dùng:

```http
Authorization: Bearer ctk_...
Accept: application/json
```

Token có thể có các abilities: `posts.read`, `posts.write`, `posts.publish`,
`media.write`, `translations.read`, `translations.write`,
`translations.publish` hoặc `*`. API giới hạn 60 request/phút cho mỗi token.

`translations.publish` là quyền tin cậy cao dành riêng cho publisher tự động.
Nó cho phép bản dịch do AI tạo đi thẳng qua `machine_draft -> ready -> published`;
luồng dịch trong admin vẫn giữ nguyên quy trình duyệt.
Để tương thích với token publisher đã cấp trước API đa ngôn ngữ, `posts.read`,
`posts.write`, `posts.publish` lần lượt cũng cho phép đọc, ghi và publish bản dịch
của chính bài viết trong cùng website. Token cấp mới vẫn nên khai báo rõ ba quyền
`translations.*` để contract dễ kiểm tra.

## Endpoints

### Danh mục

`GET /api/v1/cms/categories`

Chỉ trả danh mục thuộc website của token.

### Upload ảnh

`POST /api/v1/cms/media` dùng `multipart/form-data`:

- `external_id`: khóa ổn định từ hệ thống nguồn;
- `file`: JPG, PNG, WebP hoặc GIF, tối đa 1 MB;
- `payload_hash`: SHA-256 của file để retry không tạo media trùng;
- `title`, `alt_text`: metadata tùy chọn.

### Tạo hoặc cập nhật bài

`POST /api/v1/cms/posts/upsert` dùng JSON. Các field chính:

```json
{
  "external_id": "tech-content:huong-dan-core-web-vitals",
  "category_id": 5,
  "title": "Hướng dẫn kiểm tra Core Web Vitals",
  "slug": "huong-dan-kiem-tra-core-web-vitals",
  "excerpt": "Mô tả ngắn",
  "body": "<p>Nội dung CKEditor</p>",
  "meta_title": "Meta title riêng",
  "meta_keywords": "Core Web Vitals, LCP, INP, CLS",
  "meta_description": "Meta description",
  "tags": ["Core Web Vitals", "SEO kỹ thuật"],
  "featured_media_id": 123,
  "status": "draft",
  "is_highlight": false
}
```

`external_id` được ánh xạ theo `source_key + website_key`, nên retry hoặc sửa bài
sẽ cập nhật đúng record. `category_id` và `featured_media_id` luôn được xác nhận
thuộc website của token. `published` yêu cầu ability `posts.publish`.

### Liên kết bài legacy với external ID

`POST /api/v1/cms/posts/link` dùng ability `posts.write`:

```json
{
  "external_id": "tech-content:legacy-article",
  "post_id": 1,
  "expected_slug": "legacy-article"
}
```

Endpoint chỉ tìm bài trong website của token, bắt buộc slug xác nhận phải khớp
và từ chối nếu `external_id` đã trỏ tới bài khác. Dùng thao tác này trước khi
publisher cập nhật một bài được tạo thủ công từ trước, nhằm tránh tạo bản trùng.
Nếu link cũ trỏ tới một `cms_posts` đã bị xóa, endpoint được phép chuyển link mồ
côi sang bài đích đã xác nhận; link tới bài vẫn tồn tại luôn bị từ chối.

### Đọc bài theo khóa nguồn

`GET /api/v1/cms/posts/{externalId}`

### Tạo hoặc cập nhật bản dịch bài viết

`POST /api/v1/cms/posts/{externalId}/translations/{locale}/upsert` dùng JSON:

```json
{
  "title": "How to Improve Core Web Vitals",
  "slug": "improve-core-web-vitals",
  "excerpt": "A practical performance guide.",
  "body": "<p>English CKEditor content.</p>",
  "meta_title": "Improve Core Web Vitals: A Practical Guide",
  "meta_keywords": "Core Web Vitals, LCP, INP, CLS",
  "meta_description": "Measure and improve Core Web Vitals.",
  "publish": true,
  "is_machine_translated": true
}
```

`title` và `body` là bắt buộc. `publish` mặc định là `true` và yêu cầu
`translations.publish`; bài nguồn cũng phải ở trạng thái `published`. Locale
nguồn `vi` bị từ chối vì phải cập nhật qua API bài chính. Locale đích phải được
bật chỉnh sửa và bật công khai trong `website_locales` mới có thể publish.

Upsert cùng `externalId + locale` cập nhật đúng một `content_translations` và
đồng bộ canonical route. Response có `canonical_path` và `public_path`, ví dụ
`/en/n/improve-core-web-vitals`. Hệ thống lưu `is_machine_translated=true` để
không làm mất nguồn gốc nội dung.

Đọc lại bản dịch:

`GET /api/v1/cms/posts/{externalId}/translations/{locale}`

## Công cụ tech_content

Tạo `.publisher.env` từ `.publisher.env.example`, sau đó:

```bash
php tools/publish-article.php articles/ten-bai.html --dry-run
php tools/publish-article.php articles/ten-bai.html
```

Mặc định công cụ yêu cầu bản tiếng Anh ở
`articles/en/ten-bai.html`, đăng bài nguồn trước rồi tự publish bản dịch. Có thể
truyền file khác bằng `--english=<path>`. File tiếng Anh dùng cùng template và
đặt slug riêng trong `#article-slug`. Chỉ dùng `--vi-only` khi chủ động đăng
riêng tiếng Việt.

Lệnh thứ hai tạo hoặc cập nhật và xuất bản cả hai ngôn ngữ. Dùng `--draft` khi
cần lưu cả hai dưới dạng nháp. Token mặc định cần đủ `posts.publish`,
`translations.write` và `translations.publish`. Công cụ tự upload cover của bài
nguồn, bỏ cover và H1 khỏi body, giữ SEO/tags và ánh xạ danh mục production.

Không chạy nhiều publisher song song vì việc đồng bộ `localized_routes` có thể
deadlock trên MySQL. Retry tuần tự an toàn với bài đã có resource link.

Không giả định bài legacy có thể upsert: bài được tạo trước Content API có thể
chưa có dòng trong `content_api_resource_links`. Cần backfill mapping hoặc cập
nhật qua admin trước, nếu không API sẽ tạo một bài mới.

Publisher local kiểm tra cover dưới 1 MB trước khi upload. API cũng validation
`max:1024`; thay đổi giới hạn backend phải được deploy trước khi coi production
đã chặn ở tầng server.
