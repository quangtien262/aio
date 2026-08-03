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
- Menu public chỉ được đọc qua `CmsMenuResolver`; Admin chỉ ghi bản dịch Menu qua
  `/admin/cms/menus` với `item_key`, không dùng lại editor `cms_menu.<location>.<index>`.

## Feature flags và đường rollback

Các biến môi trường:

```dotenv
LOCALIZATION_CONTENT_READER=new
LOCALIZATION_CONTENT_DUAL_WRITE=true
LOCALIZATION_CONTENT_LEGACY_FALLBACK=true
LOCALIZATION_MENU_ROLLOUT_STAGE=all
```

- `READER=new`: đọc từ translation table mới.
- `DUAL_WRITE=true`: mọi thay đổi ở dữ liệu nguồn tiếp tục cập nhật cả translation nguồn.
- `LEGACY_FALLBACK=true`: cho phép đọc override cũ khi bản mới chưa có trong thời gian rollout.
- `MENU_ROLLOUT_STAGE=legacy|canary|all`: rollback riêng Menu, chỉ bật ba theme
  canary, hoặc bật reader mới cho toàn bộ theme.

Nếu lỗi chỉ thuộc Menu, đổi `LOCALIZATION_MENU_ROLLOUT_STAGE=legacy`, giữ
dual-write/legacy fallback, sau đó xóa cache cấu hình. Nếu lỗi ảnh hưởng toàn bộ
localization mới đổi `LOCALIZATION_CONTENT_READER=legacy`. Không rollback
migration và không xóa bảng mới. Khi reader Menu là legacy, dữ liệu Menu cũ là
nguồn chính ngay cả khi cờ fallback của reader mới đang tắt.

Có thể bật/tắt reader theo module, website hoặc theme trong
`config/localized-content.php`. Website override thắng theme override để làm
công tắc rollback khẩn cấp. Ba theme canary là `BOOK920`, `DN302`, `BDS701`.
Kiểm tra quyết định thực tế bằng:

```text
php artisan localization:menu-rollout-status --website=website-main
php artisan localization:menu-rollout-status --website=website-main --theme=BOOK920 --json
```

## Trình tự rollout

1. Tạo snapshot database có thể khôi phục.
2. Chạy migration tiến tới; không dùng `migrate:fresh`.
3. Với database có dữ liệu Menu cũ, xem trước báo cáo backfill:

   ```text
   php artisan localization:backfill-menus --website=website-main --dry-run
   ```

   Migration `2026_07_31_000002` tự chạy backfill lần đầu. Lệnh không có
   `--dry-run` chỉ dùng khi cần nhập lại dữ liệu legacy phát sinh sau migration.
4. Chạy:

   ```text
   php artisan localization:audit --website=website-main --strict
   ```

5. Sửa mọi lỗi đối chiếu trước khi bật reader mới.
6. Đặt `LOCALIZATION_MENU_ROLLOUT_STAGE=canary`, xóa cache cấu hình rồi smoke
   test `BOOK920`, `DN302`, `BDS701` với ít nhất VI và EN.
   Với mỗi theme phải kiểm tra cả nhãn Menu, node con và link nội bộ đi đúng locale/canonical slug.
7. Mở rộng lần lượt: XD, EC, SHOP/NT, SER/DN, nhóm còn lại.
8. Sau mỗi nhóm, chạy contract test theme, visual QA và audit lại dữ liệu. Chỉ
   chuyển `LOCALIZATION_MENU_ROLLOUT_STAGE=all` khi tất cả nhóm đã đạt.
9. Giữ dual-write, reader cũ và bảng cũ ít nhất một chu kỳ vận hành ổn định.
10. Trước khi public một locale, chạy `localization:audit --website=... --require-ready`.
11. Chỉ lập kế hoạch xóa cơ chế cũ bằng migration riêng sau khi báo cáo audit sạch và không
    còn rollback trong thời gian theo dõi.

## Audit và tiêu chí chặn release

Lệnh audit hỗ trợ:

```text
php artisan localization:audit
php artisan localization:audit --website=website-main --json
php artisan localization:audit --website=website-main --strict
php artisan localization:audit --website=website-main --require-ready
php artisan localization:audit --website=website-main --require-ready --json
php artisan localization:repair-navigation --website=website-main --dry-run --json
php artisan localization:repair-navigation --website=website-main --json
```

`--strict` là kiểm tra tính nhất quán cấu trúc/dữ liệu. Nó trả mã lỗi nếu phát hiện:

- entity thiếu bản nguồn;
- slug dịch bị trùng;
- nội dung đã publish trong locale chưa public;
- override cũ chưa được nhập hoặc khác giá trị;
- Menu source/revision bị lệch, payload locale đích chưa lên schema v2, có `item_key`
  mồ côi, resource identity thiếu/lệch, bản published thiếu nhãn/trùng hoàn toàn nguồn
  hoặc legacy Menu chưa được nhập;
- Landing Page/block thiếu bản nguồn;
- nội dung public thiếu canonical route, có nhiều canonical hoặc canonical path
  không khớp slug của locale.

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

## Checkpoint dữ liệu ngày 2026-07-31

Sáu migration localization `2026_07_30_000001` đến `2026_07_30_000006` đã chạy trên
database local. Migration số 6 chỉ hạ bản EN còn chứa dấu hiệu tiếng Việt về
`needs_translation`, không xóa payload.

Ba migration Menu/navigation đã chạy:

- `2026_07_31_000001_add_stable_item_keys_to_cms_menus.php`;
- `2026_07_31_000002_backfill_cms_menu_translations.php`;
- `2026_07_31_000003_repair_localized_navigation_contract.php`.

Không rollback ba migration này: `item_key` và resource identity là dữ liệu bắt buộc để
bản dịch Menu không lệch khi sắp xếp/chèn/xóa node và để URL giữ đúng locale. Backfill
không xóa dữ liệu index cũ; bản EN trống vẫn là `missing`, bản sao nguyên VI bị hạ về
`needs_translation` và không tự public.

Kết quả gần nhất của `website-main`:

| Nhóm | Required | Ready EN | Pending EN |
| --- | ---: | ---: | ---: |
| Generic content | 99 | 1 | 98 |
| CMS Pages | 2 | 1 | 1 |
| Landing Pages | 1 | 1 | 0 |
| Landing blocks | 10 | 4 | 6 |
| **Tổng** | **112** | **7** | **105** |

- Structural audit: 0 issue.
- Release coverage EN: 6,3%; `--require-ready` đang trả exit code lỗi đúng thiết kế.
- 7 mục EN đạt gate tự động vẫn cần human/visual QA.
- Menu `primary` EN hiện `published`. Page Giới thiệu có canonical VI
  `/p/gioi-thieu`, canonical EN `/p/about` và Menu lưu identity `cms_page:2`.
  Smoke test bắt buộc click About từ `/en/p/about` vẫn ở `/en/p/about`; resource
  chưa có bản EN public phải về `/en`, không được rơi về `/vi`.
- Menu regression Bước 5 gần nhất: 69 tests liên quan, 3.333 assertions pass;
  contract theo nhóm theme và smoke test Menu của ba canary đều pass; các stage
  `all`, `canary`, `legacy`, override và cache isolation có regression test;
  backfill dry-run sau migration không tạo thêm thay đổi; strict audit có
  `issue_count=0`; Blade compile và Admin build pass.
- Không public/marketing EN cho tới khi hoàn tất 106 mục còn lại, duyệt 6 mục hiện có
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
