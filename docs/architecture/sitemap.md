# Sitemap website

`/sitemap.xml` là danh mục sitemap tổng; các file `/sitemaps/{group}-{part}.xml` chứa URL nội dung và liên kết `hreflang` hai chiều giữa các bản ngôn ngữ. Các nhóm gồm: trang (`pages`), bài viết (`posts`), sản phẩm (`products`), dịch vụ (`services`), dự án (`projects`), danh mục (`categories`), chuyên đề (`topics`) và tags (`tags`). Chức năng này dùng chung cho mọi theme.

Cấu hình domain đang hoạt động xác định `website_key`. URL sử dụng `sites.domain` đang hoạt động của website với giao thức HTTPS; website chưa có cấu hình domain sẽ dùng `APP_URL`. Trên server, cần đặt `APP_URL` thành địa chỉ gốc chính thức của website; khi chạy local, thêm cổng nếu có. Hệ thống không dùng giá trị Host của request để tạo URL chuẩn. Mỗi website có khóa bộ nhớ đệm và phạm vi dữ liệu riêng.

Hệ thống chỉ xét các bản ghi `LocalizedRoute` là URL chuẩn, đã xuất bản, không chuyển hướng và thuộc ngôn ngữ được bật công khai của website. Khi tạo sitemap, hệ thống kiểm tra lại nội dung gốc, thời gian xuất bản, trạng thái module, bản dịch tương ứng, theme hiện tại của landing page và bài viết công khai gắn với tags. URL trang chủ, trang danh sách và trang liên hệ tiêu chuẩn của CMS được bổ sung riêng. Các đường dẫn riêng tư và tham số tìm kiếm không được đưa vào sitemap. Nội dung cũ cần được đăng ký đường dẫn chuẩn thông qua luồng xử lý đa ngôn ngữ hiện có.

Mỗi file được tách khi đạt 10.000 URL (có thể cấu hình trong `config/sitemap.php`, tối đa 50.000 URL) hoặc khoảng 49 MB dữ liệu XML, tùy giới hạn nào đến trước. `lastmod` lấy thời điểm sửa nội dung gốc hoặc bản dịch đã xuất bản, không lấy thời điểm tạo sitemap. Trang chủ không có thời điểm cập nhật nội dung sẽ không có `lastmod`.

Các sự kiện lưu, xóa hoặc khôi phục model làm mất hiệu lực bản sitemap trong bộ nhớ đệm. Hệ thống kiểm tra dấu hiệu thay đổi trong cơ sở dữ liệu để phát hiện cập nhật dữ liệu hoặc cấu hình hàng loạt. Bộ nhớ đệm hết hạn sau 60 giây, giúp xử lý cả cập nhật hàng loạt trong cùng một giây, thay đổi bảng liên kết và bài viết hẹn giờ xuất bản. Lệnh `sitemap:refresh` làm mới sitemap của tất cả hồ sơ website và được Laravel scheduler chạy mỗi 5 phút. Khi có yêu cầu truy cập sitemap, hệ thống cũng tự tạo lại nếu bộ nhớ đệm đã hết hạn, nên lần truy cập đầu tiên không cần tiến trình worker. Không cần chạy migration cơ sở dữ liệu cho chức năng này.

## Triển khai lên server

- Xóa file tĩnh `public/robots.txt` cũ khi triển khai. Nếu công cụ đồng bộ không tự xóa các file đã bị loại khỏi mã nguồn, cần xóa file này riêng. Đường dẫn `/robots.txt` hiện do Laravel trả về, kèm URL sitemap của website tương ứng.
- Cấu hình web server chuyển yêu cầu đến `robots.txt` và các file XML sitemap vào điểm tiếp nhận request của Laravel; không để quy tắc phục vụ file XML tĩnh chặn các đường dẫn này.
- Xóa bộ nhớ đệm route hiện có và tạo lại trong quy trình triển khai thông thường.
- Chạy `php artisan sitemap:refresh` để tạo sẵn bộ nhớ đệm sitemap.
- Cấu hình lịch hệ thống chạy `php artisan schedule:run` mỗi phút; Laravel sẽ gọi tác vụ làm mới sitemap mỗi 5 phút.
- Gửi `/sitemap.xml` của từng website vào thuộc tính tương ứng trong Google Search Console. Hệ thống không tự gửi sitemap lên Google Search Console.

## Quản lý trong admin

Vào **Cài đặt website → Hồ sơ website → Sitemap**. Quyền xem là `setup.view`; quyền làm mới là `setup.complete`, đồng thời vẫn áp dụng lớp kiểm tra quyền truy cập website hiện có. Modal hiển thị số URL, thời điểm tạo sitemap và liên kết đến từng file sitemap, kèm các thao tác sao chép, mở và làm mới.

## Kiểm thử

Chạy `php artisan test --filter='SitemapTest|CmsPageLocalizationTest'` để kiểm tra phía máy chủ. Kiểm thử giao diện trình duyệt nằm tại `tests/browser/sitemap-settings.spec.js`, sử dụng API giả lập và không thay đổi dữ liệu website.
