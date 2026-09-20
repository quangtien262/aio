# Chuyên đề tin tức

Chuyên đề là cách gom bài viết độc lập với danh mục. Một bài có thể thuộc nhiều chuyên đề. Dữ liệu tách theo `website_key`; xóa chuyên đề chỉ gỡ liên kết, không xóa bài viết.

## Sử dụng

- Vào **Nội dung → Tin tức → QL chuyên đề** để tạo, sửa hoặc xóa chuyên đề. Quyền quản lý dùng chung `cms.category.manage` với danh mục tin tức; quyền xem danh sách là `cms.view`.
- Chuyên đề có tên, slug, mô tả, URL ảnh đại diện, SEO Title, SEO Description và trạng thái hiển thị. Trong cửa sổ sửa có thể chọn ngôn ngữ để lưu nháp hoặc xuất bản bản dịch. Ảnh và trạng thái hiển thị dùng chung giữa các ngôn ngữ.
- Form thêm/sửa bài viết có trường **Chuyên đề**, cho phép chọn nhiều. Các liên kết chuyên đề dùng chung cho bản gốc và các bản dịch của bài viết.
- Trong form thêm/sửa mục menu, chọn loại link **Chuyên đề tin tức**, sau đó chọn chuyên đề. Menu lưu định danh chuyên đề, nên có thể lấy đúng URL theo ngôn ngữ và khi slug thay đổi.
- Trang công khai có dạng `/{locale}/topics/{slug}`, ví dụ `/vi/topics/song-xanh`. NEWS88 hiển thị ảnh, tên, mô tả và danh sách bài có phân trang, dùng chung header/footer của theme.
- Danh sách chỉ lấy bài thuộc cùng website, đã xuất bản và đến giờ hiển thị; ngôn ngữ khác chỉ lấy bản dịch đã xuất bản còn hợp lệ. Thứ tự là thời gian xuất bản mới nhất, sau đó ID giảm dần. Chuyên đề bị ẩn hoặc không tồn tại trả về 404.
- Sitemap có thêm nhóm `topics`. URL chuẩn và liên kết ngôn ngữ dùng chung hệ thống đa ngôn ngữ hiện có.

## Triển khai

Bản CMS `0.2.8` bổ sung hai bảng `cms_topics` và `cms_post_topic`. Nâng cấp ứng dụng CMS trong App Store để hệ thống chạy migration của module. Không chỉ chạy `php artisan migrate` thông thường, vì migration module được quản lý riêng.

Nếu triển khai theo quy trình chạy migration thủ công, chạy đúng file:

```sh
php artisan migrate --path=modules/Cms/database/migrations/2026_09_20_000001_create_cms_topics_tables.php --force
```

Sau khi cập nhật mã nguồn, làm mới bộ nhớ đệm cấu hình/route theo quy trình triển khai và build lại giao diện admin. Máy local đã được tạo hai bảng mới. Nếu server chưa cập nhật bảng, danh sách bài viết và menu cũ vẫn hoạt động; phần quản lý chuyên đề sẽ thông báo cần nâng cấp CMS.

## Kiểm thử

```sh
php artisan test --compact tests/Feature/CmsTopicsTest.php tests/Feature/CmsPostTagsTest.php tests/Feature/SitemapTest.php
node node_modules/@playwright/test/cli.js test tests/browser/cms-topics.spec.js
```

Kiểm thử trình duyệt dùng API giả lập, không tạo dữ liệu trên website thật.
# Đa ngôn ngữ trong quản trị chuyên đề

Trong **Cài đặt tin tức → QL chuyên đề**, chọn ngôn ngữ của danh sách. Ngôn ngữ gốc cho phép thêm/sửa/xóa chuyên đề; ở ngôn ngữ khác, bấm **Dịch** để nhập tên, slug, mô tả và SEO riêng, sau đó **Lưu nháp** hoặc **Xuất bản bản dịch**. Chuyên đề mới luôn được tạo ở ngôn ngữ gốc trước khi dịch.

Danh sách thể hiện trạng thái **Chưa dịch**, **Bản nháp**, **Đã xuất bản**. Tên gốc được dùng để đối chiếu khi chưa có bản dịch trong quản trị. Bộ chọn chuyên đề của bài viết, bộ lọc và loại liên kết chuyên đề trong quản lý menu lấy tên theo ngôn ngữ nội dung đang chọn.

Ảnh, quan hệ bài viết–chuyên đề và trạng thái hiển thị gốc dùng chung. Việc xóa chuyên đề thực hiện tại ngôn ngữ gốc và xóa toàn bộ bản dịch. Ngoài website chỉ sử dụng bản dịch đã xuất bản và ngôn ngữ đã bật công khai.
