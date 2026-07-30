# Theme demo data

## Mục tiêu

Mỗi theme có thể cung cấp một bộ dữ liệu mẫu riêng để người quản trị xem đúng bố cục, nguồn dữ liệu và trạng thái hiển thị ngay sau khi áp dụng theme. Dữ liệu này luôn được đánh dấu bằng `theme_demo_records`, vì vậy chỉ các bản ghi mẫu mới bị thay thế hoặc xóa.

## Luồng sử dụng

1. Người quản trị chọn **Kích hoạt theme**.
2. Nếu manifest có `demo.default_preset`, hộp thoại hiển thị lựa chọn **Tạo dữ liệu mẫu mặc định cho theme này**.
3. Khi được chọn, API kích hoạt gọi `ThemeDemoContentGenerator` với preset mặc định của theme.
4. Generator tìm `ThemeDemoContentProvider` theo mã theme, tạo dữ liệu nghiệp vụ, menu, banner và landing blocks.
5. `ThemeDemoWebsiteFinalizer` hoàn thiện lớp website dùng được: trang Giới thiệu, menu chính bằng route thật, dữ liệu Cảm nhận khách hàng/Đối tác và nguồn động cho các block tương ứng.
6. Người quản trị vẫn có thể tạo lại hoặc xóa dữ liệu mẫu từ phần quản lý theme.

## Hợp đồng website hoàn chỉnh

Mọi preset, kể cả preset có provider riêng, đều đi qua `ThemeDemoWebsiteFinalizer`. Lớp này đảm bảo:

- Có trang CMS Giới thiệu đã xuất bản. Nếu người dùng đã tạo trang `gioi-thieu`, hệ thống tái sử dụng và không ghi đè.
- Có menu chính tại `primary-navigation` (riêng DN302 dùng `primary`) với các liên kết thật, có thể đổi ngôn ngữ: Trang chủ, Giới thiệu, nội dung phù hợp loại website và Liên hệ.
- Không sinh liên kết menu dạng `#`, trừ liên kết điều khiển cục bộ không thuộc menu chính.
- Chỉ đưa Sản phẩm, Dịch vụ, Dự án, Tin tức hoặc Tin rao vào menu khi theme/dữ liệu có khả năng tương ứng.
- Dữ liệu demo của theme khác không được dùng để suy luận khả năng hoặc nội dung của theme hiện tại. Nội dung thật không có marker demo vẫn được giữ và có thể tái sử dụng.
- Header theme phải đọc danh sách `themeShellData.top_menu`; không tự khai báo một menu chính khác trong Blade.
- Block có tên loại chứa `testimonial` (và `ec902_video_reviews`) dùng nguồn `cms_testimonials`.
- Block có tên loại chứa `partner` dùng nguồn `cms_partners`.
- Khi preset chưa tạo đủ Cảm nhận khách hàng/Đối tác, finalizer chuyển dữ liệu tùy chỉnh của block thành bản ghi CMS đã xuất bản để người quản trị có thể sửa và tái sử dụng.
- Với block Cảm nhận khách hàng/Đối tác, thứ tự ưu tiên là: demo của theme hiện tại, nội dung thật của người dùng; demo thuộc theme khác bị loại khỏi kết quả.

Các bản ghi bổ sung do finalizer tạo được đánh dấu bằng preset `website-shell:{preset}` trong `theme_demo_records`. Khi tạo lại hoặc xóa data test, chỉ các bản ghi này và các bản ghi demo của provider bị xóa; nội dung thật của người dùng được giữ nguyên.

## Điểm mở rộng cho theme mới

1. Khai báo preset mặc định trong `themes/{KEY}/theme.json`:

```json
"demo": {
  "default_preset": "my-theme-demo"
}
```

2. Tạo provider trong `app/Core/Themes/Demo/` implements `ThemeDemoContentProvider`.
3. Đăng ký provider tại `AppServiceProvider` trong `ThemeDemoContentProviderRegistry`.
4. Provider phải ghi nhận mọi model do nó tạo bằng `ThemeDemoRecord` và chỉ xóa những record đó trong `delete()`.
5. Header phải lấy menu từ `themeShellData.top_menu`.
6. Provider có thể tạo Testimonials/Partners riêng. Nếu không tạo, hãy để nội dung mẫu trong block; finalizer sẽ chuyển chúng sang đúng bảng CMS.

## XD0302

`Xd0302DemoContentProvider` là provider đầu tiên theo mô hình này. Preset `xd0302-solar-energy` tạo:

- 2 banner hero tại placement `xd0302-hero-slider`;
- 3 dịch vụ CMS, 5 dự án CMS, 3 tin tức và 3 sản phẩm;
- menu điều hướng XD0302;
- landingpage XD0302 với 8 block;
- hồ sơ thương hiệu Soler Panel.

Landingpage được định danh theo `website_key + theme_key + slug`, để landingpage của từng theme tồn tại độc lập và không ghi đè nhau.
