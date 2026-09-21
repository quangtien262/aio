# Rà soát dữ liệu mẫu các theme ngoài XD

Đã kiểm tra 68 theme qua ThemeDemoContentGenerator: tạo mẫu, tạo lại, menu theo ngôn ngữ, trang chủ, chi tiết sản phẩm/bài viết/dịch vụ khi có dữ liệu, ảnh sản phẩm cục bộ và xóa dữ liệu có đánh dấu.

## Các thay đổi chính

- Bổ sung bộ mẫu đúng ngành cho 15 theme trước đây dùng dữ liệu chung; BDS702 có bộ mẫu bất động sản riêng kế thừa cơ chế của BDS701.
- Sửa các trang chi tiết dùng sai biến, sai namespace view và các trang danh sách dự án còn thiếu.
- Sửa dọn đường dẫn bản dịch của NT504 khi tạo lại dữ liệu.
- Luồng tạo mẫu chung chỉ dọn dữ liệu của theme được chọn; slug, SKU và tên menu được tách theo theme để không xung đột.

## Áp dụng

Triển khai mã nguồn cùng public/theme-demo/complete và các ảnh được tham chiếu trong resources/demo/remaining-themes.json. Không có migration mới. Trong quản trị, chọn website và theme rồi bấm Tạo dữ liệu. Không cần chọn xóa toàn bộ. Các kiểm thử chạy bằng cơ sở dữ liệu riêng, không sửa dữ liệu website đang dùng.

## Kết quả theo theme

| Theme | Bộ mẫu | Kết quả |
|---|---|---|
| AUTO850 | auto850-euro-care | Đạt |
| AUTO851 | auto851-ohcar-marketplace | Đạt |
| AUTO852 | auto852-onyx-detailing | Đạt |
| AUTO853 | auto853-summit-cycle | Đạt |
| BDS701 | bds701-delta-platinum | Đạt |
| BDS702 | bds702-aurelia-estates | Đạt |
| BOOK920 | book920-bookle | Đạt |
| BZ501 | bz501-complete | Đạt |
| CA0050 | ca0050-sudes-aquarium | Đạt |
| DL750 | dl750-complete | Đạt |
| DN202 | dn202-delta-arc-interior | Đạt |
| DN302 | dn302-complete | Đạt |
| DN350 | dn350-cleaning | Đạt |
| DN351 | dn351-meatlers-market | Đạt |
| E800 | e800-sneaker-performance | Đạt |
| E801 | e801-velo-ride | Đạt |
| E802 | e802-moto-red | Đạt |
| E803 | e803-wolf-pc | Đạt |
| E804 | e804-marketplace | Đạt |
| E805 | e805-retail | Đạt |
| E806 | e806-electronics | Đạt |
| E807 | e807-pro-tools | Đạt |
| EC900 | ec900-smart-home | Đạt |
| EC901 | ec901-tempo-watch | Đạt |
| EC902 | ec902-novaphone | Đạt |
| EC903 | ec903-dealvui | Đạt |
| EC904 | ec904-pocomall | Đạt |
| EC905 | ec905-egohome | Đạt |
| EC906 | ec906-ega-minimart | Đạt |
| EC907 | ec907-ega-gear | Đạt |
| EC908 | ec908-ego-fitness | Đạt |
| EC909 | ec909-euro-sound | Đạt |
| EC910 | ec910-dola-watch | Đạt |
| EC911 | ec911-digitech | Đạt |
| EC912 | ec912-sudes-phone | Đạt |
| EC913 | ec913-novatech-mall | Đạt |
| EC914 | ec914-moc-nhien-craft | Đạt |
| EC915 | ec915-nd-interior | Đạt |
| EC916 | ec916-bach-hoa-xanh-plus | Đạt |
| EC917 | ec917-ega-furniture | Đạt |
| FOOT401 | foot401-complete | Đạt |
| FOOT403 | foot403-complete | Đạt |
| FOOT404 | foot404-complete | Đạt |
| FOOT405 | foot405-complete | Đạt |
| FOOT406 | foot406-complete | Đạt |
| FOOT407 | foot407-complete | Đạt |
| FOOT408 | foot408-complete | Đạt |
| FOOT409 | foot409-fast-food | Đạt |
| NEWS88 | news88-editorial | Đạt |
| NT501 | nt501-complete | Đạt |
| NT502 | nt502-dola-furniture | Đạt |
| NT503 | nt503-wolfbed | Đạt |
| NT504 | nt504-wolf-paint | Đạt |
| SER0101 | ser0101-complete | Đạt |
| SER102 | ser102-auto-detailing | Đạt |
| SER103 | ser103-bohu-wedding | Đạt |
| SHOP601 | shop601-bean-style | Đạt |
| SHOP602 | shop602-wolf-yoga | Đạt |
| SHOP603 | shop603-alena-fashion | Đạt |
| SHOP604 | shop604-bean-lingerie | Đạt |
| SHOP605 | shop605-oh-under | Đạt |
| SHOP606 | shop606-complete | Đạt |
| SPA111 | spa111-bean-spa | Đạt |
| SPA502 | spa502-complete | Đạt |
| TH0050 | th0050-premium-wellness | Đạt |
| TOOL750 | tool750-industrial | Đạt |
| TOOL751 | tool751-bee-store | Đạt |
| corporate-starter | corporate-starter-complete | Đạt |
