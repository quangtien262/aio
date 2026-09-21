# Dữ liệu mẫu cho các theme XD

## Cách áp dụng

- Chọn website cần tạo mẫu trong quản trị, vào Quản lý theme, chọn theme XD và bấm Tạo dữ liệu.
- Không cần chọn xóa toàn bộ dữ liệu mẫu. Tạo lại chỉ thay thế bản ghi đã đánh dấu của theme trên website đang chọn.
- Các nội dung tự nhập và dữ liệu của website khác được giữ nguyên. Landing page tự tạo không bị ghi đè.
- Dữ liệu gồm menu 7 liên kết, 4 dịch vụ, 3 sản phẩm, 3 bài viết, 3 dự án, 3 trang thông tin, 3 thành viên, 3 đánh giá, 6 đối tác và 2 banner. XD0307 giữ bộ mẫu riêng đã hoàn thiện.

## Triển khai

Triển khai mã nguồn cùng thư mục `public/theme-demo/xd-shared` và các ảnh sản phẩm được tham chiếu trong `resources/demo/xd-themes.json`. Chạy `php artisan view:clear` nếu máy chủ giữ bản view cũ. Không cần migration mới. Sau đó tạo dữ liệu mẫu cho từng website/theme cần áp dụng.

Giá, nhân sự, đối tác, dự án và đánh giá là dữ liệu minh họa. Ảnh lĩnh vực dùng minh họa khi chưa có ảnh sản phẩm riêng; mô tả sản phẩm ghi rõ điều này. Kiểm duyệt nội dung trước khi sử dụng chính thức.

## Cấu trúc

- `resources/demo/xd-themes.json`: ngành, tên dịch vụ/sản phẩm, ảnh và preset của từng theme.
- `XdCompleteDemoContentProvider`: tạo và xóa dữ liệu có đánh dấu, dùng phạm vi website hiện tại.
- `ThemeDemoWebsiteFinalizer`: giữ menu riêng và trang giới thiệu tương ứng.

## Nguồn ảnh

Ảnh minh họa tải từ Unsplash và lưu cục bộ. Các mã ảnh gốc được lưu trong cấu hình; logo đối tác mẫu và logo chữ được tạo bằng SVG trong dự án.

- `construction-1.jpg`: https://images.unsplash.com/photo-1504307651254-35680f356dfd
- `construction-2.jpg`: https://images.unsplash.com/photo-1541888946425-d81bb19240f5
- `construction-3.jpg`: https://images.unsplash.com/photo-1503387762-592deb58ef4e
- `solar-1.jpg`: https://images.unsplash.com/photo-1509391366360-2e959784a276
- `solar-2.jpg`: https://images.unsplash.com/photo-1466611653911-95081537e5b7
- `solar-3.jpg`: https://images.unsplash.com/photo-1497435334941-8c899ee9e8e9
- `operations-1.jpg`: https://images.unsplash.com/photo-1497366754035-f200968a6e72
- `operations-2.jpg`: https://images.unsplash.com/photo-1497366811353-6870744d04b2
- `operations-3.jpg`: https://images.unsplash.com/photo-1527515637462-cff94eecc1ac
- `logistics-1.jpg`: https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d
- `logistics-2.jpg`: https://images.unsplash.com/photo-1566576912321-d58ddd7a6088
- `logistics-3.jpg`: https://images.unsplash.com/photo-1494412519320-aa613dfb7738
- `business-1.jpg`: https://images.unsplash.com/photo-1454165804606-c3d57bc86b40
- `business-2.jpg`: https://images.unsplash.com/photo-1521737711867-e3b97375f902
- `business-3.jpg`: https://images.unsplash.com/photo-1460925895917-afdab827c52f
- `digital-1.jpg`: https://images.unsplash.com/photo-1558655146-d09347e92766
- `digital-2.jpg`: https://images.unsplash.com/photo-1460925895917-afdab827c52f
- `digital-3.jpg`: https://images.unsplash.com/photo-1547658719-da2b51169166
- `education-1.jpg`: https://images.unsplash.com/photo-1523240795612-9a054b0db644
- `education-2.jpg`: https://images.unsplash.com/photo-1523580846011-d3a5bc25702b
- `education-3.jpg`: https://images.unsplash.com/photo-1434030216411-0b793f4b4173
- `safety-1.jpg`: https://images.unsplash.com/photo-1504307651254-35680f356dfd
- `safety-2.jpg`: https://images.unsplash.com/photo-1581092921461-eab62e97a780
- `safety-3.jpg`: https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122
- `garden-1.jpg`: https://images.unsplash.com/photo-1416879595882-3373a0480b5b
- `garden-2.jpg`: https://images.unsplash.com/photo-1558904541-efa843a96f01
- `garden-3.jpg`: https://images.unsplash.com/photo-1416879595882-3373a0480b5b
- `accounting-1.jpg`: https://images.unsplash.com/photo-1554224155-6726b3ff858f
- `accounting-2.jpg`: https://images.unsplash.com/photo-1454165804606-c3d57bc86b40
- `accounting-3.jpg`: https://images.unsplash.com/photo-1460925895917-afdab827c52f
- `travel-1.jpg`: https://images.unsplash.com/photo-1488646953014-85cb44e25828
- `travel-2.jpg`: https://images.unsplash.com/photo-1436491865332-7a61a109cc05
- `travel-3.jpg`: https://images.unsplash.com/photo-1503220317375-aaad61436b1b
- `fitness-1.jpg`: https://images.unsplash.com/photo-1534438327276-14e5300c3a48
- `fitness-2.jpg`: https://images.unsplash.com/photo-1517836357463-d25dfeac3438
- `fitness-3.jpg`: https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b
- `cycling-1.jpg`: https://images.unsplash.com/photo-1485965120184-e220f721d03e
- `cycling-2.jpg`: https://images.unsplash.com/photo-1485965120184-e220f721d03e
- `cycling-3.jpg`: https://images.unsplash.com/photo-1532298229144-0ec0c57515c7
- `industry-1.jpg`: https://images.unsplash.com/photo-1581092921461-eab62e97a780
- `industry-2.jpg`: https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122
- `industry-3.jpg`: https://images.unsplash.com/photo-1581092160562-40aa08e78837
- `farm-1.jpg`: https://images.unsplash.com/photo-1500382017468-9049fed747ef
- `farm-2.jpg`: https://images.unsplash.com/photo-1500937386664-56d1dfef3854
- `farm-3.jpg`: https://images.unsplash.com/photo-1542838132-92c53300491e
- `architecture-1.jpg`: https://images.unsplash.com/photo-1600607687939-ce8a6c25118c
- `architecture-2.jpg`: https://images.unsplash.com/photo-1600210492486-724fe5c67fb0
- `architecture-3.jpg`: https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea
