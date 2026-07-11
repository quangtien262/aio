# Theme demo data

## Mục tiêu

Mỗi theme có thể cung cấp một bộ dữ liệu mẫu riêng để người quản trị xem đúng bố cục, nguồn dữ liệu và trạng thái hiển thị ngay sau khi áp dụng theme. Dữ liệu này luôn được đánh dấu bằng `theme_demo_records`, vì vậy chỉ các bản ghi mẫu mới bị thay thế hoặc xóa.

## Luồng sử dụng

1. Người quản trị chọn **Kích hoạt theme**.
2. Nếu manifest có `demo.default_preset`, hộp thoại hiển thị lựa chọn **Tạo dữ liệu mẫu mặc định cho theme này**.
3. Khi được chọn, API kích hoạt gọi `ThemeDemoContentGenerator` với preset mặc định của theme.
4. Generator tìm `ThemeDemoContentProvider` theo mã theme, tạo dữ liệu nghiệp vụ, menu, banner và landing blocks.
5. Người quản trị vẫn có thể tạo lại hoặc xóa dữ liệu mẫu từ phần quản lý theme.

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

## XD0302

`Xd0302DemoContentProvider` là provider đầu tiên theo mô hình này. Preset `xd0302-solar-energy` tạo:

- 2 banner hero tại placement `xd0302-hero-slider`;
- 3 dịch vụ CMS, 5 dự án CMS, 3 tin tức và 3 sản phẩm;
- menu điều hướng XD0302;
- landingpage XD0302 với 8 block;
- hồ sơ thương hiệu Soler Panel.

Landingpage được định danh theo `website_key + theme_key + slug`, để landingpage của từng theme tồn tại độc lập và không ghi đè nhau.
