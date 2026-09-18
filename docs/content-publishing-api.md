# Content Publishing API

API này nhận bài viết từ workspace `tech_content` và ghi vào CMS của một website cố định.
Token quyết định `website_key`; client không được gửi hoặc thay đổi website context.

## Triển khai

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan content-api:issue-token "Tech Content Publisher" \
  --website=website-main \
  --source=tech-content \
  --abilities=posts.read,posts.write,posts.publish,media.write
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
`media.write` hoặc `*`. API giới hạn 60 request/phút cho mỗi token.

## Endpoints

### Danh mục

`GET /api/v1/cms/categories`

Chỉ trả danh mục thuộc website của token.

### Upload ảnh

`POST /api/v1/cms/media` dùng `multipart/form-data`:

- `external_id`: khóa ổn định từ hệ thống nguồn;
- `file`: JPG, PNG, WebP hoặc GIF, tối đa 5 MB;
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

### Đọc bài theo khóa nguồn

`GET /api/v1/cms/posts/{externalId}`

## Công cụ tech_content

Tạo `.publisher.env` từ `.publisher.env.example`, sau đó:

```bash
php tools/publish-article.php articles/ten-bai.html --dry-run
php tools/publish-article.php articles/ten-bai.html
```

Lệnh thứ hai tạo hoặc cập nhật và xuất bản bài viết. Dùng `--draft` khi cần lưu
bản nháp. Token mặc định cần được cấp `posts.publish`. Công cụ tự upload cover,
bỏ cover và H1 khỏi body, giữ SEO/tags và ánh xạ danh mục production.
