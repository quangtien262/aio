# F&B POS — Tiến độ triển khai thực tế

Cập nhật: **2026-10-18**. Baseline: [Product Specification](fnb-pos-product-spec.md). Hợp đồng: [Runtime/API contract](../api/fnb-pos-runtime-contract.md). Vận hành: [Runbook](fnb-pos-runbook.md).

## Kết luận

Source hiện dùng **`fnb-pos@0.2.0-dev.1`**, mở rộng từ POS một cửa hàng sang quản trị chuỗi: thương hiệu/vùng/cửa hàng, phân quyền HQ và từng outlet, phát hành thực đơn bất biến, bảng giá POS, báo cáo tổng hợp và adapter Kho/Kế toán cục bộ có kiểm quyền. Mục tiêu thiết kế là chuỗi 10–50 cửa hàng; **chưa có bằng chứng tải/soak chứng minh quy mô này đã được nghiệm thu**.

Đây vẫn là bản phát triển nội bộ. Version biểu thị hợp đồng/schema mới, không thay thế nghiệm thu toàn bộ M0–M8, Pilot hay Production RC. Các gate chính sách, contention, thiết bị và vận hành còn thiếu vẫn phải hoàn thành.

## Đã có trong source

| Nhóm | Chức năng đã triển khai | Ranh giới hiện tại |
| --- | --- | --- |
| App Store/lifecycle | Package không hard-dependency; 11 migration `fnb_*`; install/upgrade/enable/disable; giữ lịch sử và chặn uninstall; exact security map theo installed version | Bundled code; chưa remote package/hot-load; crash/upgrade/soak matrix còn cần kiểm chứng |
| Chuỗi | Thương hiệu, vùng, provision thêm cửa hàng/terminal/Bar/bàn/phương thức thanh toán; version và re-auth cho thay đổi | Scope trong một website; chưa franchise/central kitchen hoặc SLA tải 50 cửa hàng |
| Quyền & audit | 58 permission, 9 exact preset ở `0.2.0-dev.1`; HQ + role theo outlet; giữ terminal membership; chặn mất HQ owner cuối; audit theo website/outlet | Chưa custom role; nhiều thao tác tiền vẫn cần chính sách duyệt nghiêm ngặt; cần UAT phân quyền thực tế |
| Menu/công thức | Master data HQ; size/topping/min-max/hết món/routing; recipe revision; snapshot publication và rollback theo outlet; bảng giá VND POS với khoảng hiệu lực | Chưa combo, promotion engine, menu import hàng loạt, multi-channel hoặc lịch lặp nâng cao |
| POS | Quầy/tại bàn/mang đi, gọi nhiều đợt, sửa dòng nháp, submit/chuyển bàn, optimistic version và idempotency | Discount cố định net trước thuế trên draft; khóa cấu trúc sau discount; chưa generic override/reopen/merge |
| KDS | Ticket theo trạm, waiting/preparing/ready/served, event/cursor, modifier snapshot; tải ticket/lines/modifiers theo batch; hủy/waste/partial compensation; timestamp UTC hiển thị theo timezone outlet | Attempt 1; chưa remake; đã smoke hai tab, SLA và journey hai người đầy đủ chưa nghiệm thu |
| Bill/thanh toán | Quantity split, finalize/plan/mixed tender/change/rounding/refund cap; zero-charge close có audit và consumption, không tạo payment giả | Manual confirmation; QR TPBank là payload chuyển khoản VND, không phải kết nối ngân hàng hoặc bằng chứng đã nhận tiền |
| Money invariant | Đóng session/ca kiểm toàn quantity không hủy đã vào bill closed có settlement paid; bill void không khóa hủy món; order projection xử lý cash rounding/quantity bill miễn phí | Multi-process MySQL races, overnight và soak vẫn là gate riêng |
| Compensation | Refund theo tender/component, giữ bill nguồn, reverse sale và waste khi đã pha; ledger retry không nhân | Manual synchronous; chưa provider-async uncertain/attention/group cancel |
| Khách hàng | Lookup che điện thoại, create/update/attach, snapshot và lịch sử bill theo outlet | Chưa CRM đầy đủ, merge lineage, loyalty hoặc retention/erasure đã duyệt |
| Báo cáo | Cash-flow theo ngày xử lý, closed sales, món/tender/ca; tổng hợp outlet/vùng/chuỗi cùng tiền tệ, operation projection không chứa tiền | Chưa báo cáo đầy đủ theo giờ/kênh/khuyến mãi và rebuild/soak reconciliation |
| Receipt/export | Snapshot/checksum, browser print + xác nhận operator, CSV private/expiry/escape formula, queue/recovery | Không phải hóa đơn thuế; chưa chứng minh máy in nhận giấy hoặc retention purge |
| Kho cục bộ | Adapter `inventory.documents.write.v1`: từng consumption sale/reversal/waste, chứng từ thật trong cùng DB transaction; giữ lineage/factor/kho/giá vốn; replay không nhân chứng từ | Chỉ item không batch/serial; quantity sau đổi đơn vị phải biểu diễn chính xác ở 3 decimals và trong giới hạn v1; chưa Inventory v2 hoặc kiểm kê/điều chuyển chuỗi nâng cao |
| Kế toán cục bộ | Mapping size → mặt hàng/pháp nhân; tạo một internal draft tổng tiền khớp bill VND, giữ vector tiền/số lượng nguồn, yêu cầu phân loại thuế | Không auto-approve/post/phát hành thuế; chưa ngoại tệ, refund credit-note tự động, HRM/Payroll hoặc Minvoice trực tiếp |
| Delivery worker | Lease, backoff, dead-letter, reconcile và opt-in automation gắn admin/auth-version; kiểm lại module/capability/quyền/mapping mỗi delivery | Chưa external-provider dispatcher; readiness không tự tuyên bố production-ready khi chỉ có mapping |

Nguồn: `modules/FnbPos/`, `routes/fnb.php`, `routes/fnb-chain.php`, `resources/admin/src/modules/fnb-pos/`; core bổ sung có lifecycle/security và audit-chain partition. Shared scope locks giảm tuần tự hóa toàn chuỗi; aggregate locks/idempotency vẫn giữ, nhưng kết luận về throughput cần benchmark MySQL.

## Trạng thái milestone

| Milestone | Hiện trạng | Gate còn thiếu |
| --- | --- | --- |
| M0 | Baseline phát triển được duyệt | Finance/Legal xác nhận tax/service charge/rounding; PII/retention/legal hold; backup và support ownership |
| M1 | Lifecycle/versioned security và upgrade có regression | MySQL lifecycle contention/crash-reclaim/skipped-version matrix và operational drill |
| M2–M4 | POS/KDS/menu/công thức và publication/price book đã có source | Import, timed onboarding, tải 500 món, conflict UX nhiều thiết bị và KDS SLA |
| M5 | Money/shift/report/compensation core và zero-charge invariants có regression | Taxable service charge/override, async provider, full two-person browser journey và soak/reconciliation |
| M5.1/M7 | Đã có một phần multi-outlet/HQ/menu rollout | Combo/merge/promotion, QR self-order, booking, loyalty, remake, workforce, offline/delivery còn thiếu |
| M6 | Có adapter Kho v1/Kế toán draft cục bộ và dispatcher opt-in | Inventory v2, external-provider uncertain reconciliation, production-like MySQL, observability/backup/restore/retention/support drill |
| M8 | Chưa triển khai | Central kitchen/forecast, analytics năng lực/payout/risk và mobile/sync cần business case/telemetry |

Các phần triển khai sớm của milestone sau không có nghĩa các milestone trước đã nghiệm thu.

## Invariant vận hành

- Website là ranh giới chuỗi/brand context; outlet là ranh giới tác nghiệp. Floor/session/order cộng tác trong cùng outlet; mutation tiền kiểm processing shift/terminal. HQ role không tự cấp quyền module Kho/Kế toán.
- Tiền là integer minor; quantity tối đa 6 decimals. Cash-flow không đồng nhất doanh thu kế toán/thuế; refund ngày sau thuộc ngày xử lý hoàn. Cash rounding thuộc bill, không tạo “nợ” giả ở order đã phân bổ đầy đủ.
- Menu publication/bill/recipe snapshot giữ lịch sử. KDS chỉ trả snapshot món/size/topping/note/quantity; không trả giá, PII, tender hoặc recipe cost.
- Consumption là ledger lý thuyết cho đến khi delivery `delivered` và consumption `posted` cùng tham chiếu chứng từ Kho. Adapter absent/off/mapping thiếu không rollback bill POS.
- Accounting draft giữ **gross bill**, chưa xử lý refund thành credit-note tự động. Draft giữ `requires_tax_classification=true`, chưa phải kê khai thuế, bút toán đã post hoặc hóa đơn điện tử.
- Automation mặc định tắt. Bật lại cần quyền HQ/F&B + quyền module đích, re-auth và explicit opt-in; thay item mapping sẽ tạm dừng automation. Admin inactive, mất quyền, đổi auth-version, mapping/module off sẽ chặn worker.
- Không cam kết full offline. Giữ command/idempotency identity khi mất response; không tạo giao dịch tiền mới để thử lại.

## Bằng chứng hiện hành và giới hạn

- Gate tổng phạm vi thay đổi 2026-10-18: `tests/Feature/Fnb* + tests/Feature/AuditChain* + tests/Unit/Fnb*`: **83 tests / 3.385 assertions pass**, PHP 8.5.5, SQLite in-memory, 15,961 giây. Bao phủ chain/role/upgrade, immutable menu/pricebook, TPBank QR manual, 50-outlet report query budget, recovery key ambiguity, stock sale/reversal/waste/cost/replay, lease/dead-letter/automation fairness, accounting exact draft, session/shift/zero-charge/rounding và audit partition/legacy/tamper.
- `FreshProductionInstallTest` hai method fresh lifecycle tới schema mới và refresh core lặp: **2 tests / 61 assertions pass**. Đây là SQLite subprocess, không phải fresh MySQL.
- `node --test tests/UnitJavaScript/fnb-command-unit.test.js`: **14 tests pass**; build Vite pass. Có mô phỏng thu một phần đã commit nhưng mất response rồi refresh: client kiểm key cũ, không gửi mutation tiền thứ hai; unknown chỉ thay version có thể replay đúng body/key cũ, còn intent đổi vẫn giữ pending. Journal chỉ giữ hash và optimistic-version metadata số, không lưu amount/reference/reason hoặc payload. PHP lint 60 file thay đổi pass; Pint và whitespace pass.
- Smoke giao diện trên SQLite riêng: onboarding → thêm món giả lập → POS gửi order → tab KDS thứ hai preparing/ready/served → tạo/finalize bill → thu tiền mặt giả lập → bill closed/receipt snapshot; HQ tạo outlet thứ hai qua re-auth; báo cáo chuỗi hiển thị đúng 30.000 VND ở outlet thứ nhất, 0 ở outlet thứ hai. QR TPBank hiển thị được với tài khoản synthetic; sau tạo QR vẫn chỉ có một payment tiền mặt trước đó, bill QR giữ `finalized`, intent `pending` và audit hợp lệ. Timestamp phiên sau sửa hiển thị 14:55 giờ Việt Nam thay vì UTC 07:55. Chưa chạy fault-injection browser spec hoặc in giấy USB.
- Full PHPUnit: **887 tests / 26.133 assertions; 30 errors, 51 failures**, 414,508 giây; không có failure/error trong các class F&B/audit. Log `/tmp/aio-fnb-full-suite.log`, JUnit `/tmp/aio-fnb-full-suite.xml`. Bốn method lỗi Admin/CMS/DL750/DN302 đã tái hiện trên HEAD sạch `2a36d207` (landing route mặc định, CMS-only thiếu `catalog_products`, category array bị đọc như object, bộ dữ liệu DN302 không hợp lệ). Các lỗi còn lại thuộc CMS/theme/landing chưa phân loại hết; không suy rộng bằng chứng bốn method thành toàn bộ lỗi broad suite đều có sẵn. Full-suite vẫn là gate chưa xanh.
- Bằng chứng lịch sử **2026-09-05** của `0.1.0-dev.1`: full PHPUnit 514 tests/11.076 assertions; MySQL 8.0.43 upgrade-path smoke 170 FK/53 permission với mixed tender/refund/compensation/receipt; một browser fixture smoke. Đây không phải bằng chứng MySQL/E2E cho schema và adapter `0.2.0-dev.1` hiện tại.
- CMS migration từng bị nêu là chặn fresh MySQL hiện đã có guard trong source; vẫn cần chạy fresh-production/MySQL hiện hành trước tuyên bố đã giải quyết runtime.
- Không có bằng chứng kết nối TPBank, xác minh tiền ngân hàng, provider certification, giấy in thực tế hoặc tải chuỗi 10–50 cửa hàng. Không phát hành hóa đơn thuế hay gửi provider payment trong các test nêu trên.

## Thứ tự tiếp tục

1. Hoàn tất release gates M0 và acceptance dataset; rà soát chứng từ Kho/Kế toán trong quán pilot với owner nghiệp vụ.
2. Chạy MySQL competing-process/lease-crash/lifecycle/concurrent quantity/payment/refund matrix cho code hiện hành; hướng dẫn tại `tests/Support/fnb-mysql-gates.md`. Mục tiêu nghiệm thu dự kiến: 50 outlet × (2 POS + 1 KDS) = 150 thiết bị, 100 bill/giờ/outlet = 5.000 bill/giờ toàn chuỗi, kiểm thêm 2× peak và soak; đây là giả định tải để đo, chưa phải capacity đã chứng minh. Harness domain hiện có một worker/outlet, không thay thế tải HTTP/150 thiết bị.
3. Chạy journey hai người/hai trình duyệt: gọi món/topping → Bar → split/mixed/zero bill → receipt → refund/compensation → đóng ca → reconcile stock/draft và kiểm mất mạng/retry.
4. Diễn tập backup/restore, worker/scheduler monitoring, disable/upgrade/recovery; chỉ bật automation sau đối soát thủ công và cấp grant cho tài khoản có owner vận hành rõ ràng.
5. Triển khai riêng các contract chưa có: Inventory v2, accounting refund adjustments, provider ngân hàng/print, QR self-order/offline/loyalty/central kitchen. Không dùng version hoặc UI đã có để thay nghiệm thu.
