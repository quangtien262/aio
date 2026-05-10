# SER0100 Service Theme Spec

## 1. Mục tiêu

- Tạo theme `SER0100` theo cùng pattern đóng gói của `TH0001` để có thể cài đặt, preview, kích hoạt, dịch nội dung và seed data test từ theme manager hiện tại.
- Định vị `SER0100` là theme `service-first` cho nhóm doanh nghiệp vận tải hành khách, thuê xe du lịch, shuttle doanh nghiệp và chở hàng nhẹ.
- Tận dụng database hiện có: CMS, Catalog, Banner, Menu, Translation, Orders. Không thay đổi schema trong phase này.

## 2. Website Type Và Switching Policy

- `SER0100` có `website_type = service`.
- Theme được phép đổi chéo với `TH0001` hoặc các theme khác loại website, nhưng khi đổi khác `website_type` phải có cảnh báo đỏ và xác nhận rõ ràng từ user.
- Dữ liệu cũ không bị xóa khi đổi theme, nhưng một phần nội dung có thể không còn tương thích hoàn toàn với block và mục đích sử dụng của theme mới.

## 3. Định vị sản phẩm

SER0100 là theme cho các doanh nghiệp như:

- Nhà xe thuê xe 4 đến 45 chỗ
- Xe hợp đồng cho đoàn khách du lịch
- Đưa đón sân bay
- Xe shuttle cho doanh nghiệp
- Xe cưới hỏi
- Xe chở học sinh hoặc nhân viên
- Dịch vụ chở hàng nhẹ, chở hàng tuyến ngắn, hàng sự kiện

Nguyên tắc hiển thị:

- Ưu tiên chuyển đổi lead thay vì bán hàng kiểu flash-sale
- Nổi bật hotline, CTA báo giá, form nhận lịch trình
- Tăng trust bằng fleet, khách hàng tiêu biểu, review, chỉ số vận hành
- Vẫn dùng `CatalogProduct` như các gói dịch vụ để tận dụng flow hiện có

## 4. Visual Direction

- Tông chính: xanh navy hoặc xanh petrol
- Tông nhấn: cam đậm hoặc đỏ gạch cho CTA
- Nền sáng, sạch, ít cảm giác marketplace
- Hình ảnh ưu tiên xe thật, tài xế, đoàn khách, tuyến đường, bãi xe, điểm đến
- Typography đậm, rõ, mang cảm giác doanh nghiệp vận hành thực tế

## 5. Block Đề xuất cho theme.json

- `service-hero-quote`
- `service-quick-actions`
- `fleet-capacity-grid`
- `service-category-strip`
- `popular-routes-table`
- `featured-service-packages`
- `trust-metrics`
- `customer-logo-wall`
- `testimonials`
- `service-blog-teasers`
- `footer-service-contact`

## 6. Content Architecture

### 6.1 Trang chủ

- Hero lớn với thông điệp, hotline và CTA báo giá nhanh
- Form ngắn: loại xe, số khách, điểm đi, điểm đến, ngày đi, số điện thoại
- Grid nhóm dịch vụ chính
- Fleet showcase theo số chỗ
- Tuyến phổ biến và giá tham khảo
- Gói dịch vụ nổi bật
- Lý do chọn nhà xe
- Chỉ số vận hành: số xe, số chuyến, tỷ lệ đúng giờ, hỗ trợ 24/7
- Review khách hàng
- Logo khách hàng doanh nghiệp
- Bài viết cẩm nang mới nhất
- CTA cuối trang

### 6.2 Trang tĩnh

- Giới thiệu
- Đội xe
- Dịch vụ
- Bảng giá tham khảo
- Quy trình thuê xe
- Chính sách thuê xe
- Liên hệ

### 6.3 Blog

- Kinh nghiệm thuê xe đi tour
- Chọn loại xe theo số người
- Checklist đi sân bay đúng giờ
- Tổ chức xe cho đoàn công ty
- Lưu ý khi thuê xe chở hàng

## 7. Cách map data hiện tại vào SER0100

### 7.1 CatalogCategory

Dùng cho các nhóm:

- Thuê xe 4 chỗ
- Thuê xe 7 chỗ
- Thuê xe 16 chỗ
- Thuê xe 29 chỗ
- Thuê xe 45 chỗ
- Limousine
- Đưa đón sân bay
- Xe du lịch
- Shuttle doanh nghiệp
- Chở hàng nhẹ

### 7.2 CatalogProduct

Dùng như “gói dịch vụ” hoặc “tuyến tham khảo”:

- Gói sân bay 4 chỗ
- Gói sân bay 7 chỗ
- Gói city tour 4 giờ
- Gói city tour 8 giờ
- Gói thuê xe 16 chỗ đi tỉnh trong ngày
- Gói thuê xe 29 chỗ cho đoàn công ty
- Gói xe cưới hỏi nửa ngày
- Gói shuttle công ty theo tháng
- Gói chở hàng nội thành theo chuyến
- Gói chở hàng tuyến tỉnh

### 7.3 CMS Pages

- Giới thiệu nhà xe
- Liên hệ
- Quy trình thuê xe
- Chính sách đặt cọc và hủy chuyến
- Dịch vụ doanh nghiệp

### 7.4 CMS Posts

- Cẩm nang đi tour bằng xe thuê riêng
- Thuê xe sân bay cần lưu ý gì
- Gợi ý loại xe cho đoàn 10-45 người
- Kinh nghiệm tổ chức shuttle cho công ty
- Mẹo chở hàng nhẹ an toàn

### 7.5 SiteBanner

- Hero chính
- Tuyến hot mùa lễ
- Ưu đãi thuê xe hè
- CTA cho doanh nghiệp
- CTA chở hàng nhanh

## 8. Preset Demo Data

### 8.1 `ser-airport-city`

- Tập trung dịch vụ sân bay, city transfer, xe gia đình
- Xe chính: 4, 7, 16 chỗ
- Tone nội dung: nhanh, hiện đại, phục vụ cá nhân và khách công tác

Branding đề xuất:

- Company: `Saigon Airport Cars`
- Domain: `saigonairportcars.demo`
- Theme flavor: `airport transfer service`
- Hero title: `Đưa đón sân bay và city transfer đúng giờ mỗi ngày`

Category đề xuất:

- Đưa đón sân bay 4 chỗ
- Đưa đón sân bay 7 chỗ
- Xe gia đình nội đô
- City tour nửa ngày
- City tour trọn ngày
- Xe đón khách VIP
- Đưa đón khách sạn
- Xe công tác ngắn hạn

### 8.2 `ser-tour-coach`

- Tập trung xe đoàn du lịch, trường học, team building
- Xe chính: 16, 29, 45 chỗ
- Tone nội dung: năng lực vận hành đoàn, nhiều tuyến tỉnh, hình ảnh tour

Branding đề xuất:

- Company: `Viet Tour Coach`
- Domain: `viettourcoach.demo`
- Theme flavor: `tour transport service`
- Hero title: `Thuê xe du lịch đoàn, tour công ty và hành trình tỉnh`

Category đề xuất:

- Thuê xe 16 chỗ
- Thuê xe 29 chỗ
- Thuê xe 45 chỗ
- Xe đi Vũng Tàu
- Xe đi Đà Lạt
- Xe đi Miền Tây
- Team building doanh nghiệp
- Xe đoàn học sinh

### 8.3 `ser-business-cargo`

- Tập trung shuttle công ty, hợp đồng tháng, chở hàng nhẹ
- Xe chính: 7, 16 chỗ và các gói vận chuyển nhẹ
- Tone nội dung: B2B, ổn định, SLA, hợp đồng dài hạn

Branding đề xuất:

- Company: `Metro Shuttle Logistics`
- Domain: `metroshuttle.demo`
- Theme flavor: `business shuttle and cargo`
- Hero title: `Shuttle doanh nghiệp và vận chuyển hàng nhẹ theo hợp đồng`

Category đề xuất:

- Shuttle công ty
- Xe đưa đón nhân sự
- Xe chở hàng nội thành
- Xe chở hàng tuyến tỉnh
- Hợp đồng tháng
- Xe phục vụ sự kiện
- Xe giao booth và thiết bị
- Dịch vụ vận chuyển kết hợp người và hàng

## 9. Dữ liệu seed tối thiểu cho mỗi preset

- 8 đến 10 category
- 24 đến 36 catalog products dạng gói dịch vụ
- 2 đến 4 CMS pages
- 4 đến 6 CMS posts
- 4 đến 5 banners
- 2 menus chính

## 10. Kế hoạch file cho SER0100

- `themes/SER0100/theme.json`
- `themes/SER0100/lang/vi.json`
- `themes/SER0100/lang/en.json`
- `themes/SER0100/views/home.blade.php`
- `themes/SER0100/views/cms.blade.php`
- `themes/SER0100/views/category.blade.php`
- `themes/SER0100/views/product.blade.php`
- `themes/SER0100/views/search.blade.php`
- `themes/SER0100/views/cart.blade.php`
- `themes/SER0100/views/checkout.blade.php`
- `themes/SER0100/views/checkout-success.blade.php`
- `themes/SER0100/views/partials/*`

## 11. Scope triển khai phase này

- Không đổi schema database
- Không tạo booking engine riêng
- Không tạo module mới
- Tận dụng theme engine hiện tại
- Tận dụng business content translation hiện tại
- Tận dụng ThemeDemoContentGenerator hiện tại bằng cách thêm preset service
