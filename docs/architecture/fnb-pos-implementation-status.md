# F&B POS — Tiến độ triển khai thực tế

Cập nhật: **2026-09-05**. Baseline: [Product Specification](fnb-pos-product-spec.md). Hợp đồng hiện hành: [Runtime/API contract](../api/fnb-pos-runtime-contract.md). Cài đặt và vận hành: [Runbook](fnb-pos-runbook.md).

## Kết luận

Đã tạo package **`fnb-pos@0.1.0-dev.1`**, cài/bật qua App Store, cùng backend và giao diện quản trị cho luồng bán hàng cafe online-first. Đây là **bản phát triển nội bộ**, không phải tuyên bố đã hoàn thành toàn bộ M0–M8, đạt parity KiotViet, đạt Pilot `0.1.0` hay Production RC `0.1.2`.

Sếp đã duyệt triển khai toàn bộ roadmap. Tính năng được phát triển theo thứ tự phụ thuộc; những quyết định chính sách, adapter và bằng chứng vận hành còn thiếu không được tự ghi là đã nghiệm thu.

## Đã có trong source

| Nhóm | Chức năng đã triển khai | Ranh giới hiện tại |
| --- | --- | --- |
| App Store | Manifest không hard-dependency; 9 package migrations/73 bảng `fnb_*`; install/enable/disable; giữ dữ liệu; chặn uninstall lịch sử giao dịch | Bundled code, chưa remote package/hot-load frontend |
| Platform lifecycle | Durable operation, advisory ownership, drain/recovery, security map theo installed version; 3 bảng core-neutral | Chưa có đầy đủ multi-process crash/soak matrix cho mọi cặp lifecycle |
| Quyền & nhân viên | 53 permission, 8 preset exact; website role + outlet/terminal membership; candidate grant; chống nâng quyền qua API core; re-auth một lần; phê duyệt hai người | Chưa custom role hay role khác nhau theo outlet; các thao tác critical đang dùng chính sách hai người nghiêm ngặt, chưa cấu hình threshold/một người |
| Khởi tạo | Website/outlet, terminal, Bar, khu vực/5 bàn, tiền mặt/chuyển khoản; menu mẫu tùy chọn; replay không nhân dữ liệu | Chưa timed UAT 30 phút hay import menu hàng loạt |
| Menu/công thức | Nhóm món, nhiều size, topping, min/max lựa chọn, trạng thái hết món, routing trạm; nguyên liệu/UOM; recipe revision bất biến, loss và modifier consumption | Chưa workflow publish/menu price-book hoàn chỉnh, combo hay lịch giá/kênh |
| POS | Quầy/tại bàn/mang đi; session gọi nhiều đợt; thêm/sửa/xóa dòng nháp, submit, chuyển bàn, hủy có duyệt; optimistic version và replay | Giảm giá cố định trên giá net trước thuế, chỉ order nháp; sau giảm giá khóa chỉnh cấu trúc dòng; chưa generic override hay reopen order |
| KDS | Ticket theo station, waiting/preparing/ready/served, cursor/event ledger, hủy và waste, quantity sau partial compensation | Attempt 1; chưa remake; chưa chứng minh SLA dưới tải thực tế |
| Bill/thanh toán | Chia bill theo món/số lượng; finalize, settlement plan; mixed tender; tiền khách đưa/tiền thừa; cash rounding; refund phân bổ có cap; check reopen/void có guard | Manual tender do người vận hành xác nhận; không có xác minh ngân hàng/provider tự động |
| Hủy món đã trả tiền | Refund theo từng tender + từng thành phần tiền; full/partial quantity; giữ check nguồn; reverse sale consumption và ghi waste khi đã pha; retry không nhân ledger | Chỉ synchronous manual tender; prior refund không tương thích bị chặn để đối soát; chưa asynchronous uncertain/attention/resolve/cancel workflow |
| Ca/két | Business day, mở/đóng/đối soát ca, số tiền đầu ca, cash in/out, expected/count/variance, payment/refund processing shift | Chưa measured overnight/soak và mọi trường hợp provider pending |
| Khách hàng | Lookup che số điện thoại; create/attach; profile update có version; lịch sử bill đã đóng theo outlet với cursor và snapshot cũ | API profile/history đã có; chưa đủ màn CRM và merge có lineage; không xóa lịch sử giao dịch |
| Báo cáo | Tách tiền thu/hoàn theo ngày xử lý và doanh số bill đã đóng; top món, tender, ca/két, lọc ngày/ca/terminal; operations response không chứa tiền | Chưa đầy đủ báo cáo kênh/giờ/discount/void/compensation và rebuild/soak reconciliation |
| Phiếu & export | Receipt snapshot/checksum, browser print và operator confirm; CSV private, checksum, hết hạn, chống formula injection, worker/recovery | Phiếu bán hàng không phải hóa đơn thuế; chưa chứng minh máy in nhận giấy; chưa purge theo retention được duyệt |
| Giao diện | Onboarding, tổng quan, POS, Bar/Bếp, thực đơn, ca/két, báo cáo, nhân viên, thiết lập; conflict và critical-action drawer | Chưa đủ E2E hai người/hai trình duyệt và toàn bộ màn quản trị nâng cao |
| Tích hợp | Core capability checker dùng chung; readiness rõ ràng; immutable outbox và theoretical consumption | Mọi adapter stock/accounting/workforce vẫn pending; không có posting giả hoặc gọi Minvoice trực tiếp |

Nguồn chính: `modules/FnbPos/`, `routes/fnb.php`, `resources/admin/src/modules/fnb-pos/`; phần core bổ sung nằm tại `app/Core/Modules/` và additive migration `2026_09_05_000001_create_module_lifecycle_security_tables.php`.

## Trạng thái milestone

| Milestone | Trạng thái | Điều kiện còn thiếu trước khi nghiệm thu |
| --- | --- | --- |
| M0 | Baseline được duyệt phát triển | Finance/Legal xác nhận tax/service charge/rounding; PII/retention/legal hold; policy một người và backup |
| M1 | Nền tảng đã có và có regression | Lifecycle contention/crash-resume matrix, upgrade skipped versions khi có release thực, operational drill |
| M2 | Nghiệp vụ cốt lõi đã có | Hoàn thiện publish/price-book, UI customer/recipe nâng cao, import và menu 500 món/load, timed onboarding |
| M3 | Các command POS và UI cốt lõi đã có | Multi-process MySQL concurrent edit/replay/transfer, kiểm thử conflict UX nhiều thiết bị |
| M4 | KDS và ledger đã có | Two-browser journey, event backlog/restart/refresh/load, SLA đo được, responsive/fullscreen UAT |
| M5 | Money/shift/report backend và UI chính đã có | Đầy đủ approval policy, service charge/override, async compensation/reconciliation, các báo cáo còn thiếu, full journey + soak/latency |
| M5.1 | Chưa triển khai | Combo nâng cao, merge session có lineage, lịch/kênh giá, bulk waste; chỉ sau Pilot ổn định |
| M6 | Có một phần seam, chưa có RC | Mapping/adapter Inventory v2/Accounting, dispatcher/dead-letter/reconcile, observability, retention, backup/restore và support drill |
| M7 | Chưa triển khai | QR self-order, booking, loyalty, multi-outlet RBAC, remake, workforce, provider/print agent, offline, delivery theo contract riêng |
| M8 | Chưa triển khai | Central kitchen/forecast, SLA/capacity analytics, payout/risk analytics, mobile/sync nâng cao; cần telemetry/business case |

Không tăng version để biểu thị milestone đã đạt khi mới có một phần source. Không tạo bảng rỗng hoặc endpoint giả cho tính năng M5.1/P1/P2.

## Quyết định kỹ thuật cho bản prerelease

- POS floor/session/order là không gian cộng tác **trong cùng outlet**, phục vụ gọi nhiều đợt/chuyển bàn/thu ngân tiếp nhận. Đây không phải chế độ floor riêng tư theo terminal. Header terminal vẫn phải thuộc phân công; mutation tiền/ca kiểm processing terminal. Danh sách ca và báo cáo theo terminal đang chọn; report/export không được đổi terminal qua query trái context.
- Quyền để mở projection nhạy cảm kiểm bằng exact F&B preset + outlet/terminal, không dựa riêng vào quyền core/global legacy. Provider reference, token/hash, recipe/buyer snapshot và idempotency evidence không được trả trong operational hoặc conflict response.
- `gross_collected_minor - refund_disbursed_minor = net_collected_minor` là cash-flow của kỳ xử lý; `closed_sales_minor` là sale snapshot. Không gọi tiền nhận được là doanh thu kế toán/thuế. Hoàn ngày sau hiện trong ngày xử lý hoàn; average bill dùng sale/check đã đóng.
- Đơn vị tiền là integer minor; quantity là decimal tối đa 6 chữ số; các test số học không thay thế xác nhận chính sách thuế. Toàn bộ dữ liệu test là synthetic.
- Nguyên liệu ở F&B là **tiêu hao lý thuyết**, chưa phải chứng từ giảm tồn Inventory hoặc chi phí AccountingTax. Readiness luôn nói rõ pending khi adapter chưa sẵn sàng.
- Bản này không cam kết offline. Mất mạng phải thông báo và giữ idempotency identity để retry đúng command, không tạo payment/order mới do mất response.

## Bằng chứng kiểm thử

- Targeted backend: API từ onboarding → gọi món → KDS → bill/payment → receipt/export → đóng ca; partial/full compensation; immutable recipe và menu snapshot; customer isolation/history; exact preset và approval evidence; signed monetary vectors và CSV projection.
- Fresh-production SQLite: core migrate không tự cài F&B; cài/bật qua lifecycle mới đưa package vào hoạt động với exact permission/role map.
- MySQL 8.0.43 isolated upgrade-path smoke: install/enable/disable/re-enable, **170 foreign keys**, **53 permission**; mixed payment 40.000 tiền mặt + 18.000 chuyển khoản, refund 29.000, compensation 29.000 qua 2 tender; sale 30/reversal 15/waste 15; két trở về tiền đầu ca; receipt checksum qua JSON MySQL. Database scratch được xóa trong `finally`, không sao chép dữ liệu người dùng.
- Frontend production build **pass, 4.562 modules**; chỉ còn cảnh báo chunk lớn không chặn của bundle toàn hệ thống. Browser fixture smoke **1/1 pass**: login/permission/navigation, onboarding/cấu hình, tạo nhóm món + món nhiều size, mở ngày/ca/session và thêm/sửa/xóa dòng nháp. Ảnh QA: `test-results/fnb-pos-pilot.png`. Smoke chưa thanh toán/receipt/export hoặc hoàn tiền hai người trên trình duyệt, không thay thế full two-browser money journey.
- Full PHPUnit: **514 tests, 11.076 assertions pass**, PHP 8.3.30, 5 phút 43 giây, 158 MB. Sau các sửa nhỏ cuối ở HTTP projection/permission, gate `FnbAuthorizationHttpTest + FnbPosApiTest + FnbSecurityFoundationTest` tiếp tục **19 tests, 377 assertions pass**; không cộng hai bộ số vì có test trùng nhau.
- Pint pass; kiểm tra whitespace của thay đổi trong phạm vi pass. Whitespace sẵn có của `docs/theme.txt` không thuộc thay đổi này và được giữ nguyên.

Giới hạn môi trường đã xác minh: fresh MySQL core hiện vướng migration lịch sử `2026_07_17_000003_make_cms_media_file_path_nullable.php` vì giả định `cms_media` tồn tại. Smoke F&B MySQL dùng schema core của deployment hiện hữu nhưng **không copy dòng dữ liệu**. Không sửa migration lịch sử đã áp dụng để che lỗi; fresh MySQL deployment cần xử lý riêng trước phát hành.

## Trạng thái workspace/local

Chỉ additive **core lifecycle/security migration** đã chạy trên database local của Sếp. F&B **chưa được cài, bật hoặc onboarding** trên database này; module được cài thử trong database cô lập. Không ghi giao dịch quán thật, không gửi provider payment, email, hóa đơn điện tử hoặc deploy. Chưa commit/push. Thay đổi có sẵn của Sếp ở `docs/theme.txt` được giữ nguyên.

Test server loopback đã dừng; SQLite browser fixture được xóa sau QA và có thể dựng lại bằng support script. Cache PHPUnit và trạng thái Playwright có sẵn được giữ ngoài thay đổi bàn giao. Ảnh QA giữ lại làm artifact kiểm chứng, không chứa tài khoản hoặc dữ liệu quán thật.

## Thứ tự tiếp tục

1. Chốt M0 policy với owner nghiệp vụ; hoàn thiện các khoảng trống M2–M5 nêu trên, không bỏ test để đẩy version.
2. Chạy critical journey hai người/hai trình duyệt và MySQL contention/soak theo acceptance dataset; đo baseline 10 terminal, 100 bàn, 500 item, 5.000 bill/ngày.
3. Chỉ sau Pilot ổn định mới phát triển cumulative parity `0.1.1`; ký adapter readiness để mở mapping/integration `0.1.2`.
4. Lựa chọn từng workstream P1/P2 có contract và exit gate cụ thể; không suy diễn provider, pháp lý, offline conflict policy hoặc thiết bị được hỗ trợ.
