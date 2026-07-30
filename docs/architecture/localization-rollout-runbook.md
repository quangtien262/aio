# Localization rollout runbook

Tài liệu này là quy trình vận hành cho kiến trúc đa ngôn ngữ sau các giai đoạn chuyển đổi
CMS Pages, toàn bộ nhóm Nội dung, Landing Page và theme.

## Hợp đồng đã đóng băng

- `website_locales` là nguồn sự thật về locale theo từng website.
- Nội dung nguồn vẫn nằm ở bảng hiện tại để tương thích trong giai đoạn chuyển đổi.
- Nội dung dịch của Pages nằm ở `cms_page_translations`.
- Nội dung dịch của các entity còn lại nằm ở `content_translations`, phân biệt bằng
  `website_key + resource_type + resource_id + locale`.
- Landing Page giữ identity/layout ở bảng master; slug, SEO và nội dung nằm ở
  `landing_page_data`; nội dung block nằm ở `landing_page_block_data`.
- Public reader chỉ đọc bản `published`. Draft, machine draft, needs translation và
  outdated không được rò ra storefront.
- URL chuẩn và lịch sử URL được quản lý bằng `localized_routes`.
- Theme chỉ nhận dữ liệu đã localize từ controller/builder; không tự truy vấn bảng dịch.

## Feature flags và đường rollback

Các biến môi trường:

```dotenv
LOCALIZATION_CONTENT_READER=new
LOCALIZATION_CONTENT_DUAL_WRITE=true
LOCALIZATION_CONTENT_LEGACY_FALLBACK=true
```

- `READER=new`: đọc từ translation table mới.
- `DUAL_WRITE=true`: mọi thay đổi ở dữ liệu nguồn tiếp tục cập nhật cả translation nguồn.
- `LEGACY_FALLBACK=true`: cho phép đọc override cũ khi bản mới chưa có trong thời gian rollout.

Nếu phát hiện lỗi nghiêm trọng, đổi `LOCALIZATION_CONTENT_READER=legacy`, giữ dual-write và
legacy fallback, sau đó xóa cache cấu hình. Không rollback migration và không xóa bảng mới.

Có thể bật/tắt reader theo module, website hoặc theme trong
`config/localized-content.php`. Ba theme canary là `BOOK920`, `DN302`, `BDS701`.

## Trình tự rollout

1. Tạo snapshot database có thể khôi phục.
2. Chạy migration tiến tới; không dùng `migrate:fresh`.
3. Chạy:

   ```text
   php artisan localization:audit --website=website-main --strict
   ```

4. Sửa mọi lỗi đối chiếu trước khi bật reader mới.
5. Smoke test `BOOK920`, `DN302`, `BDS701` với ít nhất VI và EN.
6. Mở rộng lần lượt: XD, EC, SHOP/NT, SER/DN, nhóm còn lại.
7. Sau mỗi nhóm, chạy contract test theme, visual QA và audit lại dữ liệu.
8. Giữ dual-write, reader cũ và bảng cũ ít nhất một chu kỳ vận hành ổn định.
9. Trước khi public một locale, chạy `localization:audit --website=... --require-ready`.
10. Chỉ lập kế hoạch xóa cơ chế cũ bằng migration riêng sau khi báo cáo audit sạch và không
    còn rollback trong thời gian theo dõi.

## Audit và tiêu chí chặn release

Lệnh audit hỗ trợ:

```text
php artisan localization:audit
php artisan localization:audit --website=website-main --json
php artisan localization:audit --website=website-main --strict
php artisan localization:audit --website=website-main --require-ready
php artisan localization:audit --website=website-main --require-ready --json
```

`--strict` là kiểm tra tính nhất quán cấu trúc/dữ liệu. Nó trả mã lỗi nếu phát hiện:

- entity thiếu bản nguồn;
- slug dịch bị trùng;
- nội dung đã publish trong locale chưa public;
- override cũ chưa được nhập hoặc khác giá trị;
- Landing Page/block thiếu bản nguồn;
- nội dung public thiếu canonical route.

`--require-ready` chạy toàn bộ kiểm tra trên và bổ sung release-readiness theo từng locale
public. Một translation chỉ được tính là sẵn sàng khi:

- có record đích;
- trạng thái là `published`;
- `source_revision` của bản dịch khớp revision của nguồn hiện tại.

Các nhóm được tính trong release gate:

- mọi resource trong `config/localized-content.php`;
- CMS Pages;
- Landing Page metadata/SEO;
- Landing Page blocks.

Không được dùng kết quả `--strict` để kết luận locale đã dịch xong. Release bị chặn nếu
test workflow, theme contract, visual QA canary, Admin build, strict audit hoặc
`--require-ready` không đạt.

## Checkpoint dữ liệu ngày 2026-07-30

Sáu migration localization `2026_07_30_000001` đến `2026_07_30_000006` đã chạy trên
database local. Migration số 6 chỉ hạ bản EN còn chứa dấu hiệu tiếng Việt về
`needs_translation`, không xóa payload.

Kết quả gần nhất của `website-main`:

| Nhóm | Required | Ready EN | Pending EN |
| --- | ---: | ---: | ---: |
| Generic content | 374 | 0 | 374 |
| CMS Pages | 2 | 0 | 2 |
| Landing Pages | 12 | 0 | 12 |
| Landing blocks | 114 | 81 | 33 |
| **Tổng** | **502** | **81** | **421** |

- Structural audit: 0 issue.
- Release coverage EN: 16,1%; `--require-ready` đang trả exit code lỗi đúng thiết kế.
- 81 block EN đạt gate tự động vẫn cần human/visual QA.
- Regression gần nhất: 314 tests, 5.705 assertions pass; Blade compile và Admin build pass.
- Không public/marketing EN cho tới khi hoàn tất 421 mục còn lại, duyệt 81 block hiện có
  và `--require-ready` pass.

## Quy tắc thay đổi sau này

- Thêm field dịch: khai báo vào resource contract, thêm vào form/API, backfill và test;
  không thêm cột `*_en`, `*_fr` vào bảng master.
- Thêm loại nội dung: đăng ký một `resource_type` mới và dùng cùng repository/workflow.
- Thêm block: tăng `schema_version` khi payload thay đổi không tương thích và cung cấp
  migration/upcaster; không sửa âm thầm ý nghĩa schema cũ.
- Thêm locale: tạo locale ở cấp hệ thống, bật editing cho website, hoàn tất nội dung,
  sau đó mới bật public.
- Đổi slug đã publish: luôn qua workflow để URL cũ trở thành redirect 301.
- Bản dịch máy luôn bắt đầu ở `machine_draft`; không được tự publish.
