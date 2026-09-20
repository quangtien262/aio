# Sửa tiếng Việt trên XD0310

Các chuỗi như `Logistics ViÃ¡Â»â€¡t` là nội dung UTF-8 đã bị đọc sai theo Windows-1252 rồi lưu lại, có thể nhiều lần. Thay font hoặc chỉ xóa cache không sửa được nội dung này. Mã nguồn theme hiện tại đã có tiếng Việt đúng; dữ liệu đã tạo trước đó cần được sửa riêng.

Sau khi triển khai mã mới, chạy tại thư mục gốc Laravel trên server:

```sh
php artisan themes:repair-xd0310-encoding xd0310.demo.htvietnam.vn
php artisan themes:repair-xd0310-encoding xd0310.demo.htvietnam.vn --write
php artisan themes:repair-xd0310-encoding xd0310.demo.htvietnam.vn
```

Lệnh đầu chỉ liệt kê bản ghi và trường cần sửa. Lệnh thứ hai lưu bản sao các giá trị trước/sau vào `storage/app/private/encoding-backups/` rồi cập nhật trong một giao dịch. Lệnh cuối nên báo 0 bản ghi cần sửa. Tài khoản chạy lệnh phải ghi được thư mục sao lưu.

Phạm vi gồm thông tin website, branding XD0310, menu, banner XD0310, dịch vụ, dự án, đối tác, tin tức, bản dịch và nội dung landing page của đúng website gắn với domain. Không tạo lại demo, không đổi ID, slug, trạng thái xuất bản hay thời gian cập nhật. Công cụ chỉ chấp nhận chuyển đổi đảo ngược được và làm giảm dấu hiệu lỗi; chữ đúng được giữ nguyên. Những ký tự đã bị thay bằng dấu hỏi hoặc mất dữ liệu không thể tự khôi phục bằng cách này.

Sau khi chạy, kiểm tra menu, địa chỉ, banner và nội dung cuối trang. Nếu có bộ nhớ đệm HTML ở CDN/proxy thì làm mới cache của domain này. Bản sao lưu chứa nội dung riêng của website, không đưa vào thư mục public.

Nếu cần khôi phục, dùng các mục `table`, `id`, `before` trong tệp sao lưu để cập nhật lại đúng trường, sau khi đối chiếu để tránh ghi đè chỉnh sửa mới của biên tập viên.
